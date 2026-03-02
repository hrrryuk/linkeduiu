<?php
require_once 'portal_db.php';
require_once 'university_db.php';

function get_role($id)
{
    if (ctype_digit($id)) return 'student';
    if (ctype_alpha($id)) return 'faculty';
    return false;
}

function validate_input($username, $password, $confirm)
{
    if (empty($username) || empty($password) || empty($confirm)) {
        exit("All fields are required.");
    }
    if ($password !== $confirm) {
        exit("Passwords do not match.");
    }
    $role = get_role($username);
    if (!$role) {
        exit("Invalid username format.");
    }
    return $role;
}

function user_exists_in_portal($portal, $id)
{
    $stmt = $portal->prepare("SELECT 1 FROM users WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}

function user_exists_in_university($university, $role, $id)
{
    $table = $role === 'student' ? 'students' : 'faculty';
    $col = $role === 'student' ? 'student_id' : 'faculty_id';
    $stmt = $university->prepare("SELECT 1 FROM $table WHERE $col = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}

function get_user_info($university, $id)
{
    $stmt = $university->prepare("SELECT id, name, department, email, role, photo FROM users WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res;
}

function insert_user_to_portal($portal, $info, $hashed_password)
{
    $stmt = $portal->prepare("INSERT INTO users (id, name, department, email, password, role, photo) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $info['id'], $info['name'], $info['department'], $info['email'], $hashed_password, $info['role'], $info['photo']);
    $stmt->execute();
    $stmt->close();
}

function insert_role_table($portal, $role, $id, $university)
{
    if ($role === 'student') {
        $stmt = $university->prepare("SELECT cgpa, credits FROM students WHERE student_id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $portal->prepare("INSERT INTO students (student_id, cgpa, credits) VALUES (?, ?, ?)");
        $stmt->bind_param("sdi", $id, $row['cgpa'], $row['credits']);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $portal->prepare("INSERT INTO faculty (faculty_id) VALUES (?)");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $stmt->close();
    }
}

function sync_completed_courses($portal, $university, $student_id)
{
    $res = $university->prepare("
        SELECT sg.course_code, sg.obtained_grade
        FROM student_grades sg
        JOIN student_enrollments se ON
            sg.student_id = se.student_id AND
            sg.course_code = se.course_code AND
            sg.section = se.section AND
            sg.semester = se.semester AND
            sg.year = se.year
        WHERE se.status = 'completed' AND sg.student_id = ?
    ");
    $res->bind_param("s", $student_id);
    $res->execute();
    $result = $res->get_result();

    $stmt = $portal->prepare("REPLACE INTO completed_courses (student_id, course_code, obtained_grade) VALUES (?, ?, ?)");
    while ($row = $result->fetch_assoc()) {
        $stmt->bind_param("sss", $student_id, $row['course_code'], $row['obtained_grade']);
        $stmt->execute();
    }
    $stmt->close();
    $res->close();
}

function get_current_semester($university)
{
    $res = $university->query("SELECT semester, year FROM semesters WHERE CURDATE() BETWEEN start_date AND end_date LIMIT 1");
    return ($res && $row = $res->fetch_assoc()) ? [$row['semester'], (int)$row['year']] : [null, null];
}

function sync_student_schedule($portal, $university, $student_id, $sem, $year)
{
    $query = "
        SELECT ss.day_of_week, ss.start_time, ss.end_time
        FROM student_enrollments se
        JOIN section_schedules ss ON
            se.course_code = ss.course_code AND
            se.section = ss.section AND
            se.semester = ss.semester AND
            se.year = ss.year
        WHERE se.status = 'enrolled'
        AND se.student_id = ?
        AND se.semester = ?
        AND se.year = ?
    ";
    $stmt = $university->prepare($query);
    $stmt->bind_param("ssi", $student_id, $sem, $year);
    $stmt->execute();
    $result = $stmt->get_result();

    $insert = $portal->prepare("INSERT INTO student_schedules (student_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)");
    while ($row = $result->fetch_assoc()) {
        $insert->bind_param("ssss", $student_id, $row['day_of_week'], $row['start_time'], $row['end_time']);
        $insert->execute();
    }
    $insert->close();
    $stmt->close();
}

function sync_faculty_sections($portal, $university, $faculty_id, $sem, $year)
{
    $res = $university->prepare("SELECT course_code, section FROM sections WHERE faculty_id = ? AND semester = ? AND year = ?");
    $res->bind_param("ssi", $faculty_id, $sem, $year);
    $res->execute();
    $sections = $res->get_result();

    $insert_section = $portal->prepare("INSERT INTO sections (course_code, section, faculty_id) VALUES (?, ?, ?)");
    while ($row = $sections->fetch_assoc()) {
        $insert_section->bind_param("sss", $row['course_code'], $row['section'], $faculty_id);
        $insert_section->execute();
    }
    $insert_section->close();
    $res->close();

    // Sync section schedules
    $res = $university->prepare("SELECT course_code, section, day_of_week, start_time, end_time FROM section_schedules WHERE semester = ? AND year = ? AND (course_code, section) IN (SELECT course_code, section FROM sections WHERE faculty_id = ? AND semester = ? AND year = ?)");
    $res->bind_param("sissi", $sem, $year, $faculty_id, $sem, $year);
    $res->execute();
    $schedules = $res->get_result();

    $insert_sched = $portal->prepare("INSERT INTO section_schedules (course_code, section, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?, ?)");
    while ($row = $schedules->fetch_assoc()) {
        $insert_sched->bind_param("sssss", $row['course_code'], $row['section'], $row['day_of_week'], $row['start_time'], $row['end_time']);
        $insert_sched->execute();
    }
    $insert_sched->close();
    $res->close();
}

// === MAIN LOGIC ===
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['register'])) {
    $username = strtolower(trim($_POST['username'])); // <-- normalize to lowercase
    $password = trim($_POST['password']);
    $confirm = trim($_POST['confirm']);

    $role = validate_input($username, $password, $confirm);

    if (user_exists_in_portal($portal, $username)) {
        exit("User already registered.");
    }

    if (!user_exists_in_university($university, $role, $username)) {
        exit("User not found in university database.");
    }

    $info = get_user_info($university, $username);
    if (!$info) exit("Failed to fetch user data.");

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    insert_user_to_portal($portal, $info, $hashed);
    insert_role_table($portal, $role, $username, $university);

    [$sem, $year] = get_current_semester($university);

    if ($role === 'student') {
        sync_completed_courses($portal, $university, $username);
        sync_student_schedule($portal, $university, $username, $sem, $year);
    } else {
        sync_faculty_sections($portal, $university, $username, $sem, $year);
    }

    echo "Registration successful.";
    header("Location: /$role/controller.php");
} else {
    exit("Access denied.");
}
