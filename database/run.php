<?php

//require_once '../university_db.php'; // $university
// inserting into university database

// === Optional: Truncate tables to start fresh ===
function truncate_tables($university)
{
    $tables = [
        "student_grades",
        "student_enrollments",
        "section_schedules",
        "sections",
        "semesters",
        "grades",
        "courses",
        "students",
        "faculty",
        "users"
    ];
    foreach ($tables as $table) {
        $university->query("SET FOREIGN_KEY_CHECKS=0;");
        if (!$university->query("TRUNCATE TABLE $table")) {
            error_log("Failed to truncate $table: " . $university->error);
        }
        $university->query("SET FOREIGN_KEY_CHECKS=1;");
    }
    echo "Tables truncated successfully.\n";
}

require_once 'insert.php'; // insert_functions

// Data arrays
$grades = [
    ['A', 4.00],
    ['A-', 3.67],
    ['B+', 3.33],
    ['B', 3.00],
    ['B-', 2.67],
    ['C+', 2.33],
    ['C', 2.00],
    ['C-', 1.67],
    ['D+', 1.33],
    ['D', 1.00],
    ['F', 0.00]
];

$semesters = [
    ['fall', 2024, '2024-10-20', '2025-02-10'],
    ['spring', 2025, '2025-02-20', '2025-07-20']
];

$faculty = [
    ['hrr', 'Hasibur Rahman Rifat', 'DS', 'hrr@cse.uiu.ac.bd'],
    ['ijk', 'Israt Jahan Khan', 'CSE', 'ijk@cse.uiu.ac.bd']
];

$students = [
    ['0112320004', 'Hasibur Rahman Rifat', 'DS', 'hasibur@bscse.uiu.ac.bd', 3.80, 85],
    ['0112320155', 'Anika Tasnim Taz', 'CSE', 'taz@bscse.uiu.ac.bd', 3.45, 78]
];

$courses = [
    ['CSE3522', 'Database Management Systems Laboratory', 1, 'CSE'],
    ['DS2118', 'Advanced Object Oriented Programming Laboratory', 1, 'DS']
];

$sections = [
    ['CSE3522', 'A', 'spring', 2025, 'ijk', 'sunday', '11:11:00', '13:40:00'],
    ['CSE3522', 'B', 'spring', 2025, 'ijk', 'tuesday', '11:11:00', '13:40:00'],
    ['DS2118', 'A', 'spring', 2025, 'hrr', 'saturday', '08:30:00', '11:00:00'],
    ['DS2118', 'A', 'fall', 2024, 'hrr', 'saturday', '08:30:00', '11:00:00']
];

$enrollments = [
    ['0112320155', 'CSE3522', 'B', 'spring', 2025, 'enrolled'],
    ['0112320004', 'DS2118', 'A', 'spring', 2025, 'enrolled'],
    ['0112320004', 'DS2118', 'A', 'fall', 2024, 'completed', 'A-'],
];

// === Start Inserting ===

// Uncomment to clear existing data before Inserting
//truncate_tables($university);

echo "Inserting grades...\n";
foreach ($grades as $g) {
    if (!insert_grade($university, $g[0], $g[1])) {
        error_log("Failed to insert grade: {$g[0]}");
    }
}

echo "Inserting semesters...\n";
foreach ($semesters as $s) {
    if (!insert_semester($university, $s[0], $s[1], $s[2], $s[3])) {
        error_log("Failed to insert semester: {$s[0]} {$s[1]}");
    }
}

echo "Inserting faculty...\n";
foreach ($faculty as $f) {
    if (!insert_faculty($university, $f[0], $f[1], $f[2], $f[3])) {
        error_log("Failed to insert faculty: {$f[0]}");
    }
}

echo "Inserting students...\n";
foreach ($students as $s) {
    if (!insert_student($university, $s[0], $s[1], $s[2], $s[3], $s[4], $s[5])) {
        error_log("Failed to insert student: {$s[0]}");
    }
}

echo "Inserting courses...\n";
foreach ($courses as $c) {
    if (!insert_course($university, $c[0], $c[1], $c[2], $c[3])) {
        error_log("Failed to insert course: {$c[0]}");
    }
}

echo "Inserting sections with schedules...\n";
foreach ($sections as $sec) {
    if (!insert_section_with_schedule($university, $sec[0], $sec[1], $sec[2], $sec[3], $sec[4], $sec[5], $sec[6], $sec[7])) {
        error_log("Failed to insert section with schedule: {$sec[0]} {$sec[1]}");
    }
}

echo "Inserting enrollments and grades...\n";
foreach ($enrollments as $e) {
    $grade = isset($e[6]) ? $e[6] : null;
    if (!insert_enrollment_and_maybe_grade($university, $e[0], $e[1], $e[2], $e[3], $e[4], $e[5], $grade)) {
        error_log("Failed to insert enrollment/grade: {$e[0]} {$e[1]}");
    }
}

echo "Inserting completed successfully.\n";

$university->close();
