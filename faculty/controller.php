<?php
require_once '../auth.php';
auth_guard('faculty');

function set_flash($message, $type = 'error', $where = 'general')
{
    $_SESSION['alert'] = [
        'type' => $type,
        'message' => $message,
        'where' => $where
    ];
}

require_once 'model.php';

$faculty_id = $_SESSION['username'];
$data = [];

$faculty = get_faculty_data($faculty_id, $portal);
$data['faculty_name'] = $faculty['name'];
$data['faculty_email'] = $faculty['email'];
$data['faculty_photo'] = $faculty['photo'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $redirect = false;

    // Upload new profile photo
    if (isset($_FILES['faculty_photo']) && $_FILES['faculty_photo']['error'] === UPLOAD_ERR_OK) {
        $file_name = save_uploaded_file($_FILES['faculty_photo'], $faculty_id, 'profile_', '../uploads/img/');
        if ($file_name) {
            // Assume update_faculty_photo updated to return [success, old_photo]
            list($success, $old_photo) = update_faculty_photo($portal, $faculty_id, $file_name);
            if ($success) {
                if ($old_photo && $old_photo !== 'user.png' && $old_photo !== $file_name) {
                    $old_path = '../uploads/img/' . $old_photo;
                    if (file_exists($old_path)) unlink($old_path);
                }
                $redirect = true;
            }
        }
    }

    if ($redirect) {
        header("Location: controller.php");
        exit;
    }
}

// Get faculty courses
$data['courses'] = get_faculty_courses($faculty_id, $portal);

// Handle job post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish'])) {
    $job_role = $_POST['select_job_role'] ?? 'UA';
    list($course_code, $section) = explode('|', $_POST['select_course']);
    $min_cgpa = $_POST['required_cgpa'];
    $min_credit = $_POST['completed_credit'];
    $min_grade = $_POST['obtained_grade'];
    $deadline = $_POST['deadline'];

    $errors = [];

    if (!is_numeric($min_cgpa) || $min_cgpa < 0 || $min_cgpa > 4) {
        $errors[] = "Invalid cgpa";
    }
    if (!is_numeric($min_credit) || $min_credit < 0 || $min_credit > 120) {
        $errors[] = "Required credit must be between 0 and 120";
    }
    if (!preg_match('/^[A-F][+-]?$/', $min_grade)) {
        $errors[] = "Invalid grade";
    }

    if (strtotime($deadline) < strtotime(date('Y-m-d'))) {
        $errors[] = "Deadline cannot be in the past.";
    }

    if (!empty($errors)) {
        set_flash(implode("<br>", $errors), "error", "job-post-form");
        header("Location: controller.php");
        exit;
    }

    $result = insert_job_posting([
        'faculty_id' => $faculty_id,
        'course_code' => $course_code,
        'section' => $section,
        'job_role' => $job_role,
        'min_cgpa' => $min_cgpa,
        'min_credit' => $min_credit,
        'min_grade' => $min_grade,
        'deadline' => $deadline
    ], $portal);

    if ($result === true) {
    } else if ($result === "duplicate") {
        set_flash("Job already exists for this course, section, and role", "error", "job-post");
    } else {
        set_flash("Failed to publish job, please try again", "error", "job-post");
    }
    header("Location: controller.php");
    exit;
}

// Handle job update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_job'])) {
    $course_code = $_POST['edit_course'];
    $section = $_POST['edit_section'];
    $job_role = $_POST['edit_job_role'];
    $min_cgpa = $_POST['edit_required_cgpa'];
    $min_credit = $_POST['edit_completed_credit'];
    $min_grade = $_POST['edit_obtained_grade'];
    $deadline = $_POST['edit_deadline'];
    $errors = [];

    if (!is_numeric($min_cgpa) || $min_cgpa < 0 || $min_cgpa > 4) {
        $errors[] = "Invalid cgpa";
    }
    if (!is_numeric($min_credit) || $min_credit < 0 || $min_credit > 120) {
        $errors[] = "Required credit must be between 0 and 120";
    }
    if (!preg_match('/^[A-F][+-]?$/', $min_grade)) {
        $errors[] = "Invalid grade";
    }

    if (strtotime($deadline) < strtotime(date('Y-m-d'))) {
        $errors[] = "Deadline cannot be in the past.";
    }

    if (!empty($errors)) {
        set_flash(implode("<br>", $errors), "error", "job-action-edit");
        header("Location: controller.php");
        exit;
    }
    update_job_posting([
        'faculty_id' => $faculty_id,
        'course_code' => $course_code,
        'section' => $section,
        'job_role' => $job_role,
        'min_cgpa' => $min_cgpa,
        'min_credit' => $min_credit,
        'min_grade' => $min_grade,
        'deadline' => $deadline
    ], $portal);
    header("Location: controller.php");
    exit;
}

// Handle job delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_action_delete'])) {
    list($course_code, $section, $job_role) = explode('|', $_POST['job_action_delete']);
    delete_job_posting($faculty_id, $course_code, $section, $job_role, $portal);
    header("Location: controller.php");
    exit;
}

// Handle applicant sort
$sort_key = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sort_applicants'])) {
    $sort_key = $_POST['sort_applicants'];
}

// Get job key
function job_key($job)
{
    return $job['course_code'] . '|' . $job['section'] . '|' . $job['job_role'];
}

// Get posted jobs with applicants
$data['jobs'] = get_posted_jobs($faculty_id, $portal);
foreach ($data['jobs'] as &$job) {

    // Fetch section schedule
    $job['schedule'] = get_section_schedule($job['course_code'], $job['section'], $portal);
    $job['schedule_text'] = format_schedule($job['schedule']);

    // Fetch applicants
    $job['applicants'] = get_applicants_for_job($faculty_id, $job['course_code'], $job['section'], $job['job_role'], $portal);

    if (!empty($job['applicants'])) {
        foreach ($job['applicants'] as &$app) {
            $app['score'] = calculate_applicant_score($app);

            // Decode selected endorsements from JSON
            $selected_ids = json_decode($app['endorsements'], true);
            if (is_array($selected_ids) && count($selected_ids) > 0) {
                // Fetch faculty photos by selected IDs
                $app['selected_endorsement_photos'] = get_faculty_photos_by_ids($selected_ids, $portal);
            } else {
                $app['selected_endorsement_photos'] = null;
            }
        }
        unset($app);

        // Sort only if this job was requested, in decreasing order
        if (job_key($job) === $sort_key) {
            usort($job['applicants'], function ($a, $b) {
                return $b['score'] <=> $a['score'];
            });
        }
    }
}
unset($job);

// Handle applicant accept/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['applicant_accept'])) {
        $result = accept_applicant($_POST['student_id'], $faculty_id, $_POST['course_code'], $_POST['section'], $_POST['job_role'], $portal);

        if (isset($result['error']) && $result['error'] === 'conflict') {
            set_flash("Time conflict", "error", "job-action-accept");
        }
        header("Location: controller.php");
        exit;
    } elseif (isset($_POST['applicant_reject'])) {
        reject_applicant($_POST['student_id'], $faculty_id, $_POST['course_code'], $_POST['section'], $_POST['job_role'], $portal);
        header("Location: controller.php");
        exit;
    }
}

// Get hired students
$data['hired'] = get_hired_students($faculty_id, $portal);

// Handle endorse/terminate
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['endorse'])) {
        endorse_student($faculty_id, $_POST['student_id'], $portal);
        header("Location: controller.php");
        exit;
    } elseif (isset($_POST['terminate'])) {
        terminate_student($faculty_id, $_POST['student_id'], $_POST['course_code'], $_POST['section'], $_POST['job_role'], $portal);
        header("Location: controller.php");
        exit;
    }
}

// Finally, load the view
require_once 'view.php';
