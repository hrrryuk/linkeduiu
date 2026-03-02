<?php
session_start();

function auth_guard($expected_role = null)
{
    if (!isset($_SESSION['role'])) {
        // Not logged in, redirect to root index page
        header("Location: /index.php");
        exit;
    }

    if ($expected_role === null || $_SESSION['role'] === $expected_role) {
        // Access granted, continue loading page
        return;
    }

    // Role mismatch, redirect to their equivalent page if exists
    $current_path = $_SERVER['PHP_SELF']; // full path including folders
    $user_role = $_SESSION['role'];

    if ($user_role === 'student') {
        $target = str_replace('/faculty/', '/student/', $current_path);
    } else {
        $target = str_replace('/student/', '/faculty/', $current_path);
    }

    if (file_exists($_SERVER['DOCUMENT_ROOT'] . $target)) {
        header("Location: $target");
    } else {
        // fallback: go to their dashboard
        if ($user_role === 'student') {
            header("Location: /student/controller.php");
        } else {
            header("Location: /faculty/controller.php");
        }
    }
    exit;
}
