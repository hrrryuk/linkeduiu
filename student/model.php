<?php
require_once '../portal_db.php';

function get_student_data($student_id, $portal)
{
    $sql = "SELECT u.id, u.name, u.email, u.photo, s.resume
            FROM users u
            JOIN students s ON u.id = s.student_id
            WHERE u.id = ? AND u.role = 'student'";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function update_student_photo($portal, $student_id, $new_photo_filename)
{
    $sql = "SELECT photo FROM users WHERE id = ? AND role = 'student'";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $old_photo = $stmt->get_result()->fetch_assoc()['photo'] ?? null;
    $stmt->close();

    $sql = "UPDATE users SET photo = ? WHERE id = ? AND role = 'student'";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param("ss", $new_photo_filename, $student_id);
    $success = $stmt->execute();
    $stmt->close();

    return [$success, $old_photo];
}

function update_student_resume($student_id, $new_resume_filename, $portal)
{
    $sql = "SELECT resume FROM students WHERE student_id = ?";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $old_resume = $stmt->get_result()->fetch_assoc()['resume'] ?? null;
    $stmt->close();

    $sql = "UPDATE students SET resume = ? WHERE student_id = ?";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param("ss", $new_resume_filename, $student_id);
    $success = $stmt->execute();
    $stmt->close();

    return [$success, $old_resume];
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

function get_student_endorsements($student_id, $portal)
{
    $sql = "SELECT f.faculty_id, u.photo AS faculty_photo
            FROM endorsements e
            JOIN faculty f ON e.faculty_id = f.faculty_id
            JOIN users u ON f.faculty_id = u.id
            WHERE e.student_id = ?";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param("s", $student_id);
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
    $end   = $schedule_rows[0]['end_time'];

    return implode('+', $days) . " ({$start} - {$end})";
}

function cancel_job_application($student_id, $course_code, $section, $faculty_id, $portal)
{
    $sql = "DELETE FROM job_applications WHERE student_id = ? AND faculty_id = ? AND course_code = ? AND section = ?";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param("ssss", $student_id, $faculty_id, $course_code, $section);
    return $stmt->execute();
}

function request_resignation($student_id, $course_code, $section, $faculty_id, $portal)
{
    $sql = "UPDATE hired_applicants SET resign_request = TRUE WHERE student_id = ? AND faculty_id = ? AND course_code = ? AND section = ?";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param("ssss", $student_id, $faculty_id, $course_code, $section);
    return $stmt->execute();
}

function get_job_deadline($faculty_id, $course_code, $section, $portal)
{
    $sql = "SELECT deadline FROM job_postings WHERE faculty_id = ? AND course_code = ? AND section = ?";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param("sss", $faculty_id, $course_code, $section);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['deadline'];  // e.g. '2025-07-01 23:59:59'
    }
    return null;
}

function get_applied_jobs($student_id, $portal)
{
    $sql = "SELECT 
                jp.job_role, jp.course_code, jp.section, jp.deadline,
                u.name AS faculty_name, u.email AS faculty_email, u.photo AS faculty_photo,
                c.course_name,
                CASE 
                    WHEN ha.student_id IS NOT NULL THEN 'accepted'
                    ELSE 'pending'
                END AS status,
                COALESCE(ha.resign_request, FALSE) AS resign_request,
                f.faculty_id
            FROM job_applications ja
            JOIN job_postings jp ON ja.faculty_id = jp.faculty_id AND ja.course_code = jp.course_code AND ja.section = jp.section
            JOIN faculty f ON f.faculty_id = ja.faculty_id
            JOIN users u ON u.id = f.faculty_id
            JOIN courses c ON c.course_code = jp.course_code
            LEFT JOIN hired_applicants ha 
                ON ha.student_id = ja.student_id 
                AND ha.course_code = ja.course_code 
                AND ha.section = ja.section 
                AND ha.faculty_id = ja.faculty_id
            WHERE ja.student_id = ?
              AND (ja.status = 'pending' OR ha.student_id IS NOT NULL)";

    $stmt = $portal->prepare($sql);
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        $schedule_rows = get_section_schedule($row['course_code'], $row['section'], $portal);
        $row['schedule_text'] = format_schedule($schedule_rows);
        $jobs[] = $row;
    }
    return $jobs;
}

// apply_for_job (with endorsements support)
function apply_for_job($student_id, $faculty_id, $course_code, $section, $resume_file_name, $endorsements, $portal)
{
    // Check for existing application
    $check = $portal->prepare("SELECT 1 FROM job_applications WHERE student_id = ? AND faculty_id = ? AND course_code = ? AND section = ?");
    $check->bind_param("ssss", $student_id, $faculty_id, $course_code, $section);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) {
        return ['success' => false, 'error' => 'already_applied'];
    }

    // Get job schedule
    $job_schedule = get_section_schedule($course_code, $section, $portal);

    // Get student schedule
    $student_schedule = [];
    $sql_sched = "SELECT day_of_week, start_time, end_time FROM student_schedules WHERE student_id = ?";
    $stmt_sched = $portal->prepare($sql_sched);
    $stmt_sched->bind_param("s", $student_id);
    $stmt_sched->execute();
    $res_sched = $stmt_sched->get_result();
    while ($row = $res_sched->fetch_assoc()) {
        $student_schedule[] = $row;
    }
    $stmt_sched->close();

    // Check for time conflict
    if (has_time_conflict($student_schedule, $job_schedule)) {
        return ['success' => false, 'error' => 'time_conflict'];
    }

    $sql_job = "SELECT min_cgpa, min_credit, min_grade, course_code FROM job_postings WHERE faculty_id = ? AND course_code = ? AND section = ?";
    $stmt_job = $portal->prepare($sql_job);
    $stmt_job->bind_param("sss", $faculty_id, $course_code, $section);
    $stmt_job->execute();
    $job_data = $stmt_job->get_result()->fetch_assoc();
    $stmt_job->close();
    if (!$job_data) {
        return ['success' => false, 'error' => 'job_not_found'];
    }

    $profile = get_student_profile_for_scoring($student_id, $portal);

    if (!student_meets_requirements($profile, $job_data, $portal)) {
        return ['success' => false, 'error' => 'requirements_not_met'];
    }

    // Encode endorsements as JSON string
    $endorsements_json = null;
    if (!empty($endorsements)) {
        if (is_string($endorsements)) {
            $endorsements = json_decode($endorsements, true);
        }
        if (is_array($endorsements)) {
            $endorsements_json = json_encode($endorsements);
        }
    }

    // Insert application
    $sql = "INSERT INTO job_applications (student_id, faculty_id, course_code, section, resume, endorsements)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param("ssssss", $student_id, $faculty_id, $course_code, $section, $resume_file_name, $endorsements_json);
    $success = $stmt->execute();
    $stmt->close();

    return ['success' => $success];
}

function save_job_resume($file, $student_id, $upload_dir)
{
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $unique_id = uniqid();
    $filename = "resume_{$student_id}_{$unique_id}." . $ext;

    $destination = $upload_dir . $filename;
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return $filename;
    }

    return null;
}

function has_time_conflict($student_schedule, $job_schedule)
{
    foreach ($student_schedule as $stu) {
        $stu_day   = $stu['day_of_week'];
        $stu_start = strtotime($stu['start_time']);
        $stu_end   = strtotime($stu['end_time']);

        foreach ($job_schedule as $job) {
            $job_day   = $job['day_of_week'];
            $job_start = strtotime($job['start_time']);
            $job_end   = strtotime($job['end_time']);

            if ($stu_day === $job_day) {
                // Check if times overlap
                if (!($stu_end <= $job_start || $job_end <= $stu_start)) {
                    return true;
                }
            }
        }
    }
    return false;
}

function student_meets_requirements($profile, $job, $portal)
{
    if ($profile['cgpa'] < $job['min_cgpa']) return false;
    if ($profile['credits'] < $job['min_credit']) return false;

    $min_grade = $job['min_grade'];
    $stmt = $portal->prepare("SELECT point FROM grades WHERE grade = ?");
    $stmt->bind_param("s", $min_grade);
    $stmt->execute();
    $min_point = $stmt->get_result()->fetch_assoc()['point'] ?? 0;
    $stmt->close();

    $student_point = $profile['grade_points'][$job['course_code']] ?? 0;
    return $student_point >= $min_point;
}

function get_student_profile_for_scoring($student_id, $portal)
{
    $sql = "SELECT s.cgpa, s.credits,
                   (SELECT COUNT(*) FROM endorsements WHERE student_id = ?) AS endorsement_count
            FROM students s WHERE s.student_id = ?";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param("ss", $student_id, $student_id);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Get completed course grade points
    $sql2 = "SELECT cc.course_code, g.point AS grade_point
             FROM completed_courses cc
             JOIN grades g ON cc.obtained_grade = g.grade
             WHERE cc.student_id = ?";
    $stmt2 = $portal->prepare($sql2);
    $stmt2->bind_param("s", $student_id);
    $stmt2->execute();
    $res = $stmt2->get_result();

    $grade_points = [];
    while ($row = $res->fetch_assoc()) {
        $grade_points[$row['course_code']] = $row['grade_point'];
    }
    $stmt2->close();

    $profile['grade_points'] = $grade_points;
    return $profile;
}

function get_available_jobs($student_id, $portal)
{
    $profile = get_student_profile_for_scoring($student_id, $portal);

    $sql = "SELECT jp.faculty_id, jp.course_code, jp.section, jp.job_role, jp.min_cgpa, jp.min_credit, jp.min_grade, jp.deadline,
                   c.course_name, u.name AS faculty_name, u.photo AS faculty_photo
            FROM job_postings jp
            JOIN sections s ON jp.course_code = s.course_code AND jp.section = s.section
            JOIN faculty f ON jp.faculty_id = f.faculty_id
            JOIN users u ON f.faculty_id = u.id
            JOIN courses c ON jp.course_code = c.course_code
            WHERE jp.deadline >= CURDATE()
            ORDER BY jp.deadline ASC";

    $result = $portal->query($sql);
    $jobs = [];

    while ($row = $result->fetch_assoc()) {
        //if (!student_meets_requirements($profile, $row, $portal)) continue;

        $schedule = get_section_schedule($row['course_code'], $row['section'], $portal);
        $row['schedule_text'] = format_schedule($schedule);
        $jobs[] = $row;
    }

    return $jobs;
}

// Fetch jobs with filters applied, and exclude already applied jobs
function get_filtered_jobs($student_id, $filters, $portal)
{
    $role       = $filters['select_role'] ?? 'select';
    $department = $filters['select_department'] ?? 'select';
    $day        = $filters['select_day'] ?? 'select';
    $time       = $filters['select_time'] ?? 'select';
    $eligible = !empty($filters['eligible_jobs']);

    $profile = get_student_profile_for_scoring($student_id, $portal);

    // Get student's current schedule for conflict checks
    $student_schedule = [];
    $sql_sched = "SELECT day_of_week, start_time, end_time FROM student_schedules WHERE student_id = ?";
    $stmt_sched = $portal->prepare($sql_sched);
    $stmt_sched->bind_param("s", $student_id);
    $stmt_sched->execute();
    $res_sched = $stmt_sched->get_result();
    while ($row = $res_sched->fetch_assoc()) {
        $student_schedule[] = $row;
    }
    $stmt_sched->close();

    // Base SQL to fetch jobs excluding already applied ones
    $sql = "SELECT jp.faculty_id, jp.course_code, jp.section, jp.job_role, jp.min_cgpa, jp.min_credit, jp.min_grade, jp.deadline,
                   c.course_name, u.name AS faculty_name, u.photo AS faculty_photo
            FROM job_postings jp
            JOIN sections s ON jp.course_code = s.course_code AND jp.section = s.section
            JOIN faculty f ON jp.faculty_id = f.faculty_id
            JOIN users u ON f.faculty_id = u.id
            JOIN courses c ON jp.course_code = c.course_code
            WHERE jp.deadline >= CURDATE()
              AND NOT EXISTS (
                SELECT 1 FROM job_applications ja
                WHERE ja.student_id = ?
                  AND ja.faculty_id = jp.faculty_id
                  AND ja.course_code = jp.course_code
                  AND ja.section = jp.section
              )";

    $params = [$student_id];
    $types = "s";

    // Apply filters
    if ($role !== 'select') {
        $sql .= " AND jp.job_role = ?";
        $params[] = $role;
        $types .= "s";
    }

    if ($department !== 'select') {
        $sql .= " AND jp.course_code LIKE CONCAT(?, '%')";
        $params[] = $department;
        $types .= "s";
    }

    if ($day !== 'select') {
        $sql .= " AND EXISTS (
                    SELECT 1 FROM section_schedules ss
                    WHERE ss.course_code = jp.course_code AND ss.section = jp.section AND ss.day_of_week = ?
                 )";
        $params[] = $day;
        $types .= "s";
    }

    if ($time !== 'select') {
        list($start_time, $end_time) = explode('-', $time);
        $sql .= " AND EXISTS (
                    SELECT 1 FROM section_schedules ss2
                    WHERE ss2.course_code = jp.course_code AND ss2.section = jp.section
                    AND TIME_FORMAT(ss2.start_time, '%H:%i') = ?
                    AND TIME_FORMAT(ss2.end_time, '%H:%i') = ?
                 )";
        $params[] = $start_time;
        $params[] = $end_time;
        $types .= "ss";
    }

    $sql .= " ORDER BY jp.deadline ASC";

    $stmt = $portal->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        if ($eligible && !student_meets_requirements($profile, $row, $portal)) continue;

        $job_schedule = get_section_schedule($row['course_code'], $row['section'], $portal);
        if (has_time_conflict($student_schedule, $job_schedule)) continue; // skip conflict jobs

        $row['schedule_text'] = format_schedule($job_schedule);
        $jobs[] = $row;
    }

    return $jobs;
}

// Fetch distinct time slots for job filters, used in view.php filter
function get_time_slot_options($portal)
{
    $sql = "SELECT DISTINCT 
                   TIME_FORMAT(start_time, '%H:%i') AS start_time,
                   TIME_FORMAT(end_time, '%H:%i') AS end_time
            FROM section_schedules ss
            JOIN job_postings jp ON jp.course_code = ss.course_code AND jp.section = ss.section
            WHERE jp.deadline >= CURDATE()
            ORDER BY start_time";

    $result = $portal->query($sql);
    $slots = [];

    while ($row = $result->fetch_assoc()) {
        $slots[] = $row['start_time'] . '-' . $row['end_time'];
    }

    return array_unique($slots);
}

function get_student_department($student_id, $portal)
{
    $sql = "SELECT u.department FROM students s JOIN users u ON s.student_id = u.id WHERE s.student_id = ?";
    $stmt = $portal->prepare($sql);
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['department'];
    }
    return null;
}

// Fetch recommended jobs sorted by score
function get_recommended_jobs($student_id, $filters, $portal)
{
    $jobs = get_filtered_jobs($student_id, $filters, $portal);
    $profile = get_student_profile_for_scoring($student_id, $portal);

    foreach ($jobs as &$job) {
        $job['endorsement_count'] = $profile['endorsement_count'];
        $job['has_experience'] = false;
        $job['grade_point'] = $profile['grade_points'][$job['course_code']] ?? 0;
        $job['cgpa'] = $profile['cgpa'];
        $job['credits'] = $profile['credits'];
        $job['score'] = calculate_applicant_score($job);
    }
    unset($job);

    // Sort descending by score
    usort($jobs, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    return $jobs;
}

// Calculate applicant score based on multiple factors
function calculate_applicant_score($applicant)
{
    $cgpa_score        = ($applicant['cgpa'] / 4.0) * 30;
    $credit_score      = ($applicant['credits'] / 137) * 15;
    $endorsement_score = min($applicant['endorsement_count'], 5) * 3;
    $experience_score  = $applicant['has_experience'] ? 20 : 0;
    $grade_score       = ($applicant['grade_point'] ?? 0) * 3.75;

    return round($cgpa_score + $credit_score + $endorsement_score + $experience_score + $grade_score, 2);
}
