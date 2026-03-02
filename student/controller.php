<?php
require_once '../auth.php';
auth_guard('student');

require_once 'model.php';

$student_id = $_SESSION['username'];
$data = [];

$student = get_student_data($student_id, $portal);
$data['student_name'] = $student['name'];
$data['student_email'] = $student['email'];
$data['student_photo'] = $student['photo'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $redirect = false;

    // profile photo update
    if (isset($_FILES['student_photo']) && $_FILES['student_photo']['error'] === UPLOAD_ERR_OK) {
        $file_name = save_uploaded_file($_FILES['student_photo'], $student_id, 'profile_', '../uploads/img/');
        if ($file_name) {
            list($success, $old_photo) = update_student_photo($portal, $student_id, $file_name);
            if ($success) {
                if ($old_photo && $old_photo !== 'user.png' && $old_photo !== $file_name) {
                    $old_path = '../uploads/img/' . $old_photo;
                    if (file_exists($old_path)) unlink($old_path);
                }
                $redirect = true;
            }
        }
    }

    // general resume update
    if (isset($_FILES['student_resume']) && $_FILES['student_resume']['error'] === UPLOAD_ERR_OK) {
        $file_name = save_uploaded_file($_FILES['student_resume'], $student_id, 'resume_', '../uploads/resumes/');
        if ($file_name) {
            list($success, $old_resume) = update_student_resume($student_id, $file_name, $portal);
            if ($success) {
                if ($old_resume && $old_resume !== $file_name) {
                    $old_path = '../uploads/resumes/' . $old_resume;
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

// // Fetch all endorsements
$data['endorsements'] = get_student_endorsements($student_id, $portal);

$data['applied_jobs'] = get_applied_jobs($student_id, $portal);
foreach ($data['applied_jobs'] as &$job) {
    $deadline = get_job_deadline($job['faculty_id'], $job['course_code'], $job['section'], $portal);
    $job['cancel_allowed'] = ($deadline && (date('Y-m-d H:i:s') <= $deadline));
}
unset($job);
//Handle Cancel or Resignation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['job_action'], $_POST['faculty_id'], $_POST['course_code'], $_POST['section'])) {
        $faculty_id = $_POST['faculty_id'];
        $course_code = $_POST['course_code'];
        $section = $_POST['section'];

        if ($_POST['job_action'] === 'cancel') {
            // Check application deadline before canceling
            $deadline = get_job_deadline($faculty_id, $course_code, $section, $portal);
            if ($deadline && date('Y-m-d H:i:s') > $deadline) {
                $apply_error = "Deadline has passed. You cannot cancel your application.";
                $apply_error_job = compact('faculty_id', 'course_code', 'section');
            } else {
                cancel_job_application($student_id, $course_code, $section, $faculty_id, $portal);
                header("Location: controller.php");
                exit;
            }
        } elseif ($_POST['job_action'] === 'resign') {
            request_resignation($student_id, $course_code, $section, $faculty_id, $portal);
            header("Location: controller.php");
            exit;
        }
    }
}

//Handle Job Application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['job_action'] ?? '') === 'apply') {
    $faculty_id = $_POST['faculty_id'] ?? '';
    $course_code = $_POST['course_code'] ?? '';
    $section = $_POST['section'] ?? '';
    $selected_endorsements = $_POST['selected_endorsements'] ?? '';

    $resume_file_name = null;

    // Use custom uploaded resume if provided
    if (!empty($_FILES['selected_resume']['name']) && $_FILES['selected_resume']['error'] === UPLOAD_ERR_OK) {
        $resume_file_name = save_job_resume($_FILES['selected_resume'], $student_id, '../uploads/resumes/');
    }

    // Otherwise fallback to general resume
    if (!$resume_file_name) {
        $student_info = get_student_data($student_id, $portal);
        if (!empty($student_info['resume'])) {
            $resume_file_name = $student_info['resume'];
        } else {
            $apply_error = "Please upload a resume or set a general resume first";
            $apply_error_job = compact('faculty_id', 'course_code', 'section');
        }
    }

    // Proceed if resume is ready
    if ($resume_file_name) {
        $result = apply_for_job($student_id, $faculty_id, $course_code, $section, $resume_file_name, $selected_endorsements, $portal);

        if ($result['success']) {
            header("Location: controller.php?apply_success=1");
            exit;
        } else {
            switch ($result['error'] ?? '') {
                case 'already_applied':
                    $apply_error = "Already applied for this job";
                    break;
                case 'time_conflict':
                    $apply_error = "Time conflict";
                    break;
                case 'requirements_not_met':
                    $apply_error = "Requirements did not met";
                    break;
                default:
                    $apply_error = "Failed to apply for this job";
            }
            $apply_error_job = compact('faculty_id', 'course_code', 'section');
        }
    }
}

// Fetch all available jobs regardless eligibility
$data['all_jobs'] = get_available_jobs($student_id, $portal);

//Handle Job Filters & Sorting
$filters = [
    'select_role' => $_POST['select_role'] ?? 'select',
    'select_department' => $_POST['select_department'] ?? 'select',
    'select_day' => $_POST['select_day'] ?? 'select',
    'select_time' => $_POST['select_time'] ?? 'select',
    'eligible_jobs' => !empty($_POST['eligible_jobs']),
];

$data['time_slots'] = get_time_slot_options($portal);

$sort_mode = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sort_jobs'])) {
    $sort_mode = 'recommended';

    $student_dept = get_student_department($student_id, $portal);

    // If filters are default, override department to student's department
    if ($filters['select_department'] === 'select') {
        $filters['select_department'] = $student_dept;
    }

    // Default to eligible jobs if not set
    if (!$filters['eligible_jobs']) {
        $filters['eligible_jobs'] = true;
    }
}

//Load Jobs According to Filters/Sort
if ($sort_mode === 'recommended') {
    $data['available_jobs'] = get_recommended_jobs($student_id, $filters, $portal);
} else {
    $data['available_jobs'] = get_filtered_jobs($student_id, $filters, $portal);
}

// Passing Error Messages to View
if (isset($apply_error)) {
    $data['apply_error'] = $apply_error;
    $data['apply_error_job'] = $apply_error_job ?? null;
}
require_once 'view.php';
