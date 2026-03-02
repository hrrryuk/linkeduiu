<?php
require_once '../portal_db.php';

function get_faculty_data($faculty_id, $portal)
{
    $sql = "SELECT id, name, email, photo FROM users WHERE id = ? AND role = 'faculty'";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param("s", $faculty_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function update_faculty_photo($portal, $faculty_id, $new_photo_filename)
{
    $sql = "SELECT photo FROM users WHERE id = ? AND role = 'faculty'";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param("s", $faculty_id);
    $stmt->execute();
    $old_photo = $stmt->get_result()->fetch_assoc()['photo'] ?? null;
    $stmt->close();

    $sql = "UPDATE users SET photo = ? WHERE id = ? AND role = 'faculty'";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param("ss", $new_photo_filename, $faculty_id);
    $success = $stmt->execute();
    $stmt->close();

    return [$success, $old_photo];
}

function save_uploaded_file($file, $user_id, $prefix, $directory)
{
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return false;

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = $prefix . $user_id . '.' . strtolower($ext);
    $target_file = rtrim($directory, '/') . '/' . $file_name;

    // If a file with the same name exists, overwrite it (replace behavior)
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return $file_name;
    }

    return false;
}

function get_faculty_courses($faculty_id, $portal)
{
    $sql = "SELECT s.course_code, s.section, c.course_name
            FROM sections s
            JOIN courses c ON s.course_code = c.course_code
            WHERE s.faculty_id = ?";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param("s", $faculty_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function insert_job_posting($data, $portal)
{
    try {
        $sql = "INSERT INTO job_postings (faculty_id, course_code, section, job_role, min_cgpa, min_credit, min_grade, deadline)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $portal->prepare($sql);
        $stmt->bind_param(
            "ssssddss",
            $data['faculty_id'],
            $data['course_code'],
            $data['section'],
            $data['job_role'],
            $data['min_cgpa'],
            $data['min_credit'],
            $data['min_grade'],
            $data['deadline']
        );
        return $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) {
            return "duplicate";
        }
        return false;
    }
}

function update_job_posting($data, $portal)
{
    $sql = "UPDATE job_postings SET min_cgpa = ?, min_credit = ?, min_grade = ?, deadline = ?
            WHERE faculty_id = ? AND course_code = ? AND section = ? AND job_role = ?";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param(
        "ddssssss",
        $data['min_cgpa'],
        $data['min_credit'],
        $data['min_grade'],
        $data['deadline'],
        $data['faculty_id'],
        $data['course_code'],
        $data['section'],
        $data['job_role']
    );
    return $stmt->execute();
}

function delete_job_posting($faculty_id, $course_code, $section, $job_role, $portal)
{
    $sql = "DELETE FROM job_postings 
            WHERE faculty_id = ? AND course_code = ? AND section = ? AND job_role = ?";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param("ssss", $faculty_id, $course_code, $section, $job_role);
    return $stmt->execute();
}

function get_posted_jobs($faculty_id, $portal)
{
    $sql = "SELECT j.*, c.course_name
            FROM job_postings j
            JOIN courses c ON j.course_code = c.course_code
            WHERE j.faculty_id = ?";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param("s", $faculty_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function get_section_schedule($course_code, $section, $portal)
{
    $sql = "SELECT day_of_week, 
                   TIME_FORMAT(start_time, '%H:%i') AS start_time, 
                   TIME_FORMAT(end_time, '%H:%i') AS end_time
            FROM section_schedules
            WHERE course_code = ? AND section = ?
            ORDER BY FIELD(day_of_week, 'saturday','sunday','monday','tuesday','wednesday','thursday','friday')";

    $stmt = $portal->prepare($sql);
    $stmt->bind_param("ss", $course_code, $section);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function format_schedule($schedule_rows)
{
    if (empty($schedule_rows)) return "Schedule not set";

    $days = array_map(function ($row) {
        return ucfirst(substr($row['day_of_week'], 0, 3));
    }, $schedule_rows);

    $start = $schedule_rows[0]['start_time'];
    $end = $schedule_rows[0]['end_time'];

    return implode('+', $days) . " ({$start} - {$end})";
    /*
    $formatted = array_map(function ($row) {
        $day = ucfirst(substr($row['day_of_week'], 0, 3));
        return "{$day} ({$row['start_time']} - {$row['end_time']})";
    }, $schedule_rows);

    return implode(', ', $formatted);
    */
}

function get_applicants_for_job($faculty_id, $course_code, $section, $job_role, $portal)
{
    $sql = "SELECT a.*, a.endorsements, u.name AS student_name, u.photo, u.email, s.cgpa, s.credits, cc.obtained_grade, g.point AS grade_point,
                EXISTS (
                    SELECT 1 
                    FROM experiences e
                    WHERE e.student_id = a.student_id 
                      AND e.course_code = a.course_code 
                      AND e.job_role = ?
                ) AS has_experience,
                (
                    SELECT COUNT(*) 
                    FROM endorsements e 
                    WHERE e.student_id = a.student_id
                ) AS endorsement_count
            FROM job_applications a
            JOIN users u ON u.id = a.student_id
            JOIN students s ON s.student_id = a.student_id
            LEFT JOIN completed_courses cc ON cc.student_id = a.student_id AND cc.course_code = a.course_code
            LEFT JOIN grades g ON g.grade = cc.obtained_grade
            WHERE a.faculty_id = ? AND a.course_code = ? AND a.section = ? AND a.job_role = ?";

    $stmt = $portal->prepare($sql);
    $stmt->bind_param("sssss", $job_role, $faculty_id, $course_code, $section, $job_role);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function calculate_applicant_score($applicant)
{
    $cgpa_score = ($applicant['cgpa'] / 4.0) * 30;
    $credit_score = ($applicant['credits'] / 137) * 15;
    $endorsement_score = min($applicant['endorsement_count'], 5) * 3;
    $experience_score = $applicant['has_experience'] ? 20 : 0;
    $grade_score = ($applicant['grade_point'] ?? 0) * 3.75;

    return round($cgpa_score + $credit_score + $endorsement_score + $experience_score + $grade_score, 2);
}

function get_faculty_photos_by_ids(array $faculty_ids, $portal)
{
    if (empty($faculty_ids)) return [];

    $placeholders = implode(',', array_fill(0, count($faculty_ids), '?'));
    $types = str_repeat('s', count($faculty_ids));

    $sql = "SELECT id, photo FROM users WHERE role = 'faculty' AND id IN ($placeholders)";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param($types, ...$faculty_ids);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $photos = [];
    foreach ($result as $row) {
        $photos[$row['id']] = $row['photo'];
    }
    return $photos;
}

function has_schedule_conflict($student_id, $course_code, $section, $portal)
{
    $sql = "
        SELECT 1
        FROM section_schedules sec
        JOIN student_schedules ss
          ON ss.student_id = ?
         AND ss.day_of_week = sec.day_of_week
         AND NOT (ss.end_time <= sec.start_time OR ss.start_time >= sec.end_time)
        WHERE sec.course_code = ? AND sec.section = ?
        LIMIT 1
    ";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param("sss", $student_id, $course_code, $section);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0; // true = conflict exists
}

function accept_applicant($student_id, $faculty_id, $course_code, $section, $job_role, $portal)
{
    $portal->begin_transaction();
    try {
        // STEP 1: Time conflict check
        if (has_schedule_conflict($student_id, $course_code, $section, $portal)) {
            $portal->rollback();
            return ['success' => false, 'error' => 'conflict'];
        }

        // STEP 2: Update job application status
        $stmt = $portal->prepare("UPDATE job_applications SET status = 'accepted' 
            WHERE student_id = ? AND faculty_id = ? AND course_code = ? AND section = ? AND job_role = ?");
        $stmt->bind_param("sssss", $student_id, $faculty_id, $course_code, $section, $job_role);
        $stmt->execute();
        $stmt->close();

        // STEP 3: Insert into hired_applicants
        $stmt = $portal->prepare("INSERT INTO hired_applicants (student_id, faculty_id, course_code, section, job_role)
            VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $student_id, $faculty_id, $course_code, $section, $job_role);
        $stmt->execute();
        $stmt->close();

        // STEP 4: Add section schedule into student_schedules
        $stmt = $portal->prepare("
            INSERT INTO student_schedules (student_id, day_of_week, start_time, end_time)
            SELECT ?, day_of_week, start_time, end_time
            FROM section_schedules
            WHERE course_code = ? AND section = ?
        ");
        $stmt->bind_param("sss", $student_id, $course_code, $section);
        $stmt->execute();
        $stmt->close();

        $portal->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $portal->rollback();
        return ['success' => false, 'error' => 'Transaction failed'];
    }
}

function reject_applicant($student_id, $faculty_id, $course_code, $section, $job_role, $portal)
{
    $sql = "UPDATE job_applications SET status = 'rejected' 
            WHERE student_id = ? AND faculty_id = ? AND course_code = ? AND section = ? AND job_role = ?";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param("sssss", $student_id, $faculty_id, $course_code, $section, $job_role);
    return $stmt->execute();
}

function get_hired_students($faculty_id, $portal)
{
    $sql = "SELECT h.*, u.name, u.email, u.photo, c.course_name
            FROM hired_applicants h
            JOIN users u ON u.id = h.student_id
            JOIN courses c ON c.course_code = h.course_code
            WHERE h.faculty_id = ?";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param("s", $faculty_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function endorse_student($faculty_id, $student_id, $portal)
{
    $sql = "INSERT IGNORE INTO endorsements (faculty_id, student_id) VALUES (?, ?)";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param("ss", $faculty_id, $student_id);
    return $stmt->execute();
}

function terminate_student($faculty_id, $student_id, $course_code, $section, $job_role, $portal)
{
    $portal->begin_transaction();
    try {
        // 1. Delete from hired_applicants
        $stmt = $portal->prepare("DELETE FROM hired_applicants 
                                  WHERE faculty_id = ? AND student_id = ? AND course_code = ? AND section = ? AND job_role = ?");
        $stmt->bind_param("sssss", $faculty_id, $student_id, $course_code, $section, $job_role);
        $stmt->execute();
        $stmt->close();

        // 2. Delete student schedule linked to the section
        $stmt = $portal->prepare("
            DELETE ss
            FROM student_schedules ss
            JOIN section_schedules sec 
              ON sec.course_code = ? 
             AND sec.section = ?
             AND ss.day_of_week = sec.day_of_week 
             AND ss.start_time = sec.start_time 
             AND ss.end_time = sec.end_time
            WHERE ss.student_id = ?
        ");
        $stmt->bind_param("sss", $course_code, $section, $student_id);
        $stmt->execute();
        $stmt->close();

        // 3. Insert into experiences (ignore duplicates)
        $stmt = $portal->prepare("INSERT IGNORE INTO experiences (student_id, course_code, job_role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $student_id, $course_code, $job_role);
        $stmt->execute();
        $stmt->close();

        $portal->commit();
        return true;
    } catch (Exception $e) {
        $portal->rollback();
        return false;
    }
}
