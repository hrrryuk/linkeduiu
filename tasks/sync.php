<?php
// sync.php — Portal DB <== Sync from university_db at each new semester

// --- STEP 1: Identify current university semester ---
function get_current_semester($university)
{
    $sql = "SELECT semester, year FROM semesters WHERE CURDATE() BETWEEN start_date AND end_date LIMIT 1";
    $res = $university->query($sql);
    return ($res && $row = $res->fetch_assoc()) ? [$row['semester'], (int)$row['year']] : [null, null];
}

// --- STEP 2: Get current synced semester from portal ---
function get_synced_semester($portal)
{
    $sql = "SELECT synced_semester, synced_year FROM sync_info LIMIT 1";
    $res = $portal->query($sql);
    return ($res && $row = $res->fetch_assoc()) ? [$row['synced_semester'], (int)$row['synced_year']] : [null, null];
}

// --- STEP 3: Update sync_info ---
function update_sync_info($portal, $semester, $year)
{
    $stmt = $portal->prepare("
        REPLACE INTO sync_info (synced_semester, synced_year)
        VALUES (?, ?)
    ");
    if (!$stmt) {
        echo "Prepare failed (sync_info): " . $portal->error . "\n";
        return false;
    }
    $stmt->bind_param("si", $semester, $year);
    if (!$stmt->execute()) {
        echo "Execute failed (sync_info): " . $stmt->error . "\n";
        $stmt->close();
        return false;
    }
    $stmt->close();
    return true;
}

// --- HELPER: safe prepare and execute ---
function safe_prepare_execute($db, $query, $types = null, $params = [])
{
    $stmt = $db->prepare($query);
    if (!$stmt) {
        echo "Prepare failed: " . $db->error . "\n";
        return false;
    }
    if ($types !== null && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        echo "Execute failed: " . $stmt->error . "\n";
        $stmt->close();
        return false;
    }
    $stmt->close();
    return true;
}

function get_portal_user_ids($portal)
{
    $students = [];
    $faculty = [];

    $res = $portal->query("SELECT id, role FROM users");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if ($row['role'] === 'student') {
                $students[] = $row['id'];
            } elseif ($row['role'] === 'faculty') {
                $faculty[] = $row['id'];
            }
        }
    }

    return [$students, $faculty];
}

function run_sync($portal, $university)
{
    [$uni_sem, $uni_year] = get_current_semester($university);
    [$synced_sem, $synced_year] = get_synced_semester($portal);

    if ($uni_sem === null || $uni_year === null) {
        exit("Could not detect current university semester.\n");
    }

    echo "Current university semester: $uni_sem $uni_year\n";

    if ($uni_sem === $synced_sem && $uni_year === $synced_year) {
        echo "Already synced for $uni_sem $uni_year.\n";
        exit;
    }

    // Fetch portal user IDs
    [$portal_students, $portal_faculty] = get_portal_user_ids($portal);
    $portal_students_set = array_flip($portal_students);
    $portal_faculty_set = array_flip($portal_faculty);

    echo "Semester sync initiated: $uni_sem $uni_year\n";

    $portal->begin_transaction();

    try {
        // STEP 4: Update students CGPA and credits
        echo "Updating student CGPAs and credits...\n";
        $res = $university->query("SELECT student_id, cgpa, credits FROM students");
        while ($row = $res->fetch_assoc()) {
            if (!isset($portal_students_set[$row['student_id']])) continue;
            $success = safe_prepare_execute(
                $portal,
                "UPDATE students SET cgpa = ?, credits = ? WHERE student_id = ?",
                "dis",
                [$row['cgpa'], $row['credits'], $row['student_id']]
            );
            if (!$success) {
                throw new Exception("Failed to update student " . $row['student_id']);
            }
        }

        // STEP 5: Sync courses
        echo "Syncing courses...\n";
        $res = $university->query("SELECT course_code, course_name, department FROM courses");
        while ($row = $res->fetch_assoc()) {
            $success = safe_prepare_execute(
                $portal,
                "INSERT INTO courses (course_code, course_name, department)
                 VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE course_name = VALUES(course_name), department = VALUES(department)",
                "sss",
                [$row['course_code'], $row['course_name'], $row['department']]
            );
            if (!$success) {
                throw new Exception("Failed to sync course " . $row['course_code']);
            }
        }

        // STEP 6: Sync grades
        echo "Updating grade scale...\n";
        $res = $university->query("SELECT grade, point FROM grades");
        while ($row = $res->fetch_assoc()) {
            $success = safe_prepare_execute(
                $portal,
                "INSERT INTO grades (grade, point)
                 VALUES (?, ?) ON DUPLICATE KEY UPDATE point = VALUES(point)",
                "sd",
                [$row['grade'], $row['point']]
            );
            if (!$success) {
                throw new Exception("Failed to sync grade " . $row['grade']);
            }
        }

        // STEP 7: Sync completed courses
        echo "Syncing completed courses...\n";
        $res = $university->query("
            SELECT sg.student_id, sg.course_code, sg.obtained_grade
            FROM student_grades sg
            JOIN student_enrollments se
            ON sg.student_id = se.student_id
            AND sg.course_code = se.course_code
            AND sg.section = se.section
            AND sg.semester = se.semester
            AND sg.year = se.year
            WHERE se.status = 'completed'
        ");
        while ($row = $res->fetch_assoc()) {
            if (!isset($portal_students_set[$row['student_id']])) continue;
            $success = safe_prepare_execute(
                $portal,
                "REPLACE INTO completed_courses (student_id, course_code, obtained_grade)
                 VALUES (?, ?, ?)",
                "sss",
                [$row['student_id'], $row['course_code'], $row['obtained_grade']]
            );
            if (!$success) {
                throw new Exception("Failed to sync completed course for student " . $row['student_id']);
            }
        }

        // STEP 8: Build student schedules
        echo "Building student schedules...\n";
        if (!$portal->query("DELETE FROM student_schedules")) {
            throw new Exception("Failed to clear student_schedules: " . $portal->error);
        }

        $query = "
            SELECT se.student_id, ss.day_of_week, ss.start_time, ss.end_time
            FROM student_enrollments se
            JOIN section_schedules ss
            ON se.course_code = ss.course_code
            AND se.section = ss.section
            AND se.semester = ss.semester
            AND se.year = ss.year
            WHERE se.status = 'enrolled'
              AND se.semester = ? AND se.year = ?
        ";
        $stmt = $university->prepare($query);
        $stmt->bind_param("si", $uni_sem, $uni_year);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if (!isset($portal_students_set[$row['student_id']])) continue;
            $success = safe_prepare_execute(
                $portal,
                "INSERT INTO student_schedules (student_id, day_of_week, start_time, end_time)
                 VALUES (?, ?, ?, ?)",
                "ssss",
                [$row['student_id'], $row['day_of_week'], $row['start_time'], $row['end_time']]
            );
            if (!$success) {
                throw new Exception("Failed to insert schedule for student " . $row['student_id']);
            }
        }
        $stmt->close();

        // STEP 9: Refresh sections
        echo "Replacing sections and schedules...\n";
        $portal->query("DELETE FROM section_schedules");
        $portal->query("DELETE FROM sections");

        $res = $university->query("SELECT * FROM sections WHERE semester = '$uni_sem' AND year = $uni_year");
        while ($row = $res->fetch_assoc()) {
            if (!isset($portal_faculty_set[$row['faculty_id']])) continue;
            $success = safe_prepare_execute(
                $portal,
                "INSERT INTO sections (course_code, section, faculty_id) VALUES (?, ?, ?)",
                "sss",
                [$row['course_code'], $row['section'], $row['faculty_id']]
            );
            if (!$success) {
                throw new Exception("Failed to insert section " . $row['course_code']);
            }
        }

        // Build a quick lookup of inserted sections (based on available faculty)
        $valid_sections_res = $portal->query("SELECT course_code, section FROM sections");
        $valid_sections = [];
        while ($srow = $valid_sections_res->fetch_assoc()) {
            $key = $srow['course_code'] . "-" . $srow['section'];
            $valid_sections[$key] = true;
        }

        // Now insert only schedules for valid sections
        $res = $university->query("SELECT * FROM section_schedules WHERE semester = '$uni_sem' AND year = $uni_year");
        while ($row = $res->fetch_assoc()) {
            $key = $row['course_code'] . "-" . $row['section'];
            if (!isset($valid_sections[$key])) continue; // Skip orphan schedules

            $success = safe_prepare_execute(
                $portal,
                "INSERT INTO section_schedules (course_code, section, day_of_week, start_time, end_time)
         VALUES (?, ?, ?, ?, ?)",
                "sssss",
                [$row['course_code'], $row['section'], $row['day_of_week'], $row['start_time'], $row['end_time']]
            );
            if (!$success) {
                throw new Exception("Failed to insert section_schedule for " . $row['course_code'] . " " . $row['section']);
            }
        }

        // STEP 10: Convert hires to experiences
        echo "Converting hires to experiences...\n";
        $res = $portal->query("SELECT student_id, course_code, job_role FROM hired_applicants");
        while ($row = $res->fetch_assoc()) {
            $success = safe_prepare_execute(
                $portal,
                "INSERT IGNORE INTO experiences (student_id, course_code, job_role)
                 VALUES (?, ?, ?)",
                "sss",
                [$row['student_id'], $row['course_code'], $row['job_role']]
            );
            if (!$success) {
                throw new Exception("Failed to convert hire for " . $row['student_id']);
            }
        }

        // STEP 11: Clear job-related tables
        echo "Cleaning job_postings, applications, hires...\n";
        foreach (['job_applications', 'job_postings', 'hired_applicants'] as $table) {
            if (!$portal->query("DELETE FROM $table")) {
                throw new Exception("Failed to clear $table: " . $portal->error);
            }
        }

        // STEP 12: Update sync_info
        if (!update_sync_info($portal, $uni_sem, $uni_year)) {
            throw new Exception("Failed to update sync_info");
        }

        $portal->commit();
        echo "Semester sync completed successfully for $uni_sem $uni_year\n";
        echo "Synced " . count($portal_students) . " students and " . count($portal_faculty) . " faculty users.\n";
    } catch (Exception $e) {
        $portal->rollback();
        echo "Sync failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}
