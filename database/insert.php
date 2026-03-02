<?php

function insert_user($university, $id, $name, $department, $email, $role, $photo = 'user.png')
{
    $query = "INSERT INTO users (id, name, department, email, role, photo) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $university->prepare($query);
    $stmt->bind_param("ssssss", $id, $name, $department, $email, $role, $photo);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function insert_student($university, $id, $name, $department, $email, $cgpa, $credits, $photo = 'user.png')
{
    $userInserted = insert_user($university, $id, $name, $department, $email, 'student', $photo);
    if (!$userInserted) return false;

    $query = "INSERT INTO students (student_id, cgpa, credits) VALUES (?, ?, ?)";
    $stmt = $university->prepare($query);
    $stmt->bind_param("sdi", $id, $cgpa, $credits);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function insert_faculty($university, $id, $name, $department, $email, $photo = 'user.png')
{
    $userInserted = insert_user($university, $id, $name, $department, $email, 'faculty', $photo);
    if (!$userInserted) return false;

    $query = "INSERT INTO faculty (faculty_id) VALUES (?)";
    $stmt = $university->prepare($query);
    $stmt->bind_param("s", $id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function insert_course($university, $course_code, $course_name, $credit, $department)
{
    $query = "INSERT INTO courses (course_code, course_name, credit, department) VALUES (?, ?, ?, ?)";
    $stmt = $university->prepare($query);
    $stmt->bind_param("ssis", $course_code, $course_name, $credit, $department);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function insert_grade($university, $grade, $point)
{
    $query = "INSERT INTO grades (grade, point) VALUES (?, ?)";
    $stmt = $university->prepare($query);
    $stmt->bind_param("sd", $grade, $point);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function insert_semester($university, $semester, $year, $start_date, $end_date)
{
    $query = "INSERT INTO semesters (semester, year, start_date, end_date) VALUES (?, ?, ?, ?)";
    $stmt = $university->prepare($query);
    $stmt->bind_param("siss", $semester, $year, $start_date, $end_date);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function insert_section_with_schedule($university, $course_code, $section, $semester, $year, $faculty_id, $day_of_week, $start_time, $end_time)
{
    // Start transaction
    $university->begin_transaction();

    try {
        // Insert into sections
        $query1 = "INSERT INTO sections (course_code, section, semester, year, faculty_id) VALUES (?, ?, ?, ?, ?)";
        $stmt1 = $university->prepare($query1);
        $stmt1->bind_param("sssis", $course_code, $section, $semester, $year, $faculty_id);
        $stmt1->execute();
        $stmt1->close();

        // Insert into section_schedules
        $query2 = "INSERT INTO section_schedules (course_code, section, semester, year, day_of_week, start_time, end_time)
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt2 = $university->prepare($query2);
        $stmt2->bind_param("sssisss", $course_code, $section, $semester, $year, $day_of_week, $start_time, $end_time);
        $stmt2->execute();
        $stmt2->close();

        // Commit if both succeed
        $university->commit();
        return true;
    } catch (Exception $e) {
        // Rollback on failure
        $university->rollback();
        return false;
    }
}

function insert_enrollment_and_maybe_grade($university, $student_id, $course_code, $section, $semester, $year, $status, $obtained_grade = null)
{
    // Step 0: Check if already enrolled in this course (any section) for the same semester and year
    $checkQuery = "SELECT COUNT(*) FROM student_enrollments 
                   WHERE student_id = ? AND course_code = ? AND semester = ? AND year = ?";
    $count = 0; // initialize
    $stmtCheck = $university->prepare($checkQuery);
    $stmtCheck->bind_param("sssi", $student_id, $course_code, $semester, $year);
    $stmtCheck->execute();
    $stmtCheck->bind_result($count);
    $stmtCheck->fetch();
    $stmtCheck->close();

    // If already enrolled in the same course (any section), block the insertion
    if ($count > 0) {
        error_log("Duplicate enrollment attempt for student_id=$student_id, course=$course_code, term=$semester $year");
        return false;
    }

    // Step 0.5: Check if the student's department matches the course's department
    $deptCheckQuery = "
        SELECT u.department AS student_dept, c.department AS course_dept
        FROM students s
        JOIN users u ON s.student_id = u.id
        JOIN courses c ON c.course_code = ?
        WHERE s.student_id = ?
    ";
    $stmtDept = $university->prepare($deptCheckQuery);
    $stmtDept->bind_param("ss", $course_code, $student_id);
    $stmtDept->execute();
    $result = $stmtDept->get_result();
    $row = $result->fetch_assoc();
    $stmtDept->close();

    if (!$row || $row['student_dept'] !== $row['course_dept']) {
        error_log("Department mismatch: student_id=$student_id (" . $row['student_dept'] . ") cannot enroll in course $course_code (" . $row['course_dept'] . ")");
        return false;
    }

    // --- New Step: Check schedule conflicts with already enrolled/completed courses ---

    // 1. Get the schedule of the new section
    $queryNewSchedule = "
        SELECT day_of_week, start_time, end_time
        FROM section_schedules
        WHERE course_code = ? AND section = ? AND semester = ? AND year = ?
    ";
    $stmtNew = $university->prepare($queryNewSchedule);
    $stmtNew->bind_param("sssi", $course_code, $section, $semester, $year);
    $stmtNew->execute();
    $resultNew = $stmtNew->get_result();
    $newSchedules = $resultNew->fetch_all(MYSQLI_ASSOC);
    $stmtNew->close();

    if (empty($newSchedules)) {
        error_log("No schedule found for course_code=$course_code, section=$section, term=$semester $year");
        return false; // Or handle this case as you see fit
    }

    // 2. Get only enrolled sections for the student in the same semester/year
    $queryExistingSchedules = "
        SELECT ss.day_of_week, ss.start_time, ss.end_time, se.course_code, se.section
        FROM student_enrollments se
        JOIN section_schedules ss ON se.course_code = ss.course_code
                                AND se.section = ss.section
                                AND se.semester = ss.semester
                                AND se.year = ss.year
        WHERE se.student_id = ? AND se.semester = ? AND se.year = ? AND se.status = 'enrolled'
    ";
    $stmtExist = $university->prepare($queryExistingSchedules);
    $stmtExist->bind_param("ssi", $student_id, $semester, $year);
    $stmtExist->execute();
    $resultExist = $stmtExist->get_result();
    $existingSchedules = $resultExist->fetch_all(MYSQLI_ASSOC);
    $stmtExist->close();

    // 3. Check for any time overlap on same day between new section schedule and existing enrolled schedules
    foreach ($newSchedules as $new) {
        $newDay = $new['day_of_week'];
        $newStart = strtotime($new['start_time']);
        $newEnd = strtotime($new['end_time']);

        foreach ($existingSchedules as $exist) {
            if ($exist['day_of_week'] === $newDay) {
                $existStart = strtotime($exist['start_time']);
                $existEnd = strtotime($exist['end_time']);

                // Overlap condition: newStart < existEnd AND newEnd > existStart
                if (($newStart < $existEnd) && ($newEnd > $existStart)) {
                    error_log("Schedule conflict detected for student_id=$student_id between $course_code-$section and existing {$exist['course_code']}-{$exist['section']} on $newDay");
                    return false;
                }
            }
        }
    }

    // --- No conflicts found, proceed with enrollment ---

    // Start transaction
    $university->begin_transaction();

    try {
        // Step 1: Insert enrollment
        $enrollQuery = "INSERT INTO student_enrollments 
                        (student_id, course_code, section, semester, year, status)
                        VALUES (?, ?, ?, ?, ?, ?)";
        $stmt1 = $university->prepare($enrollQuery);
        $stmt1->bind_param("ssssis", $student_id, $course_code, $section, $semester, $year, $status);
        $stmt1->execute();
        $stmt1->close();

        // Step 2: If completed, insert grade
        if ($status === 'completed' && $obtained_grade !== null) {
            $gradeQuery = "INSERT INTO student_grades 
                           (student_id, course_code, section, semester, year, obtained_grade)
                           VALUES (?, ?, ?, ?, ?, ?)";
            $stmt2 = $university->prepare($gradeQuery);
            $stmt2->bind_param("ssssis", $student_id, $course_code, $section, $semester, $year, $obtained_grade);
            $stmt2->execute();
            $stmt2->close();
        }

        // Commit transaction
        $university->commit();
        return true;
    } catch (Exception $e) {
        error_log("Enrollment transaction failed: " . $e->getMessage());
        $university->rollback();
        return false;
    }
}
