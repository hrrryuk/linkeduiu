<?php
session_start();
require_once 'portal_db.php';

// Redirect to dashboard based on role
function user_redirect($role)
{
    if ($role === 'student') {
        header("Location: student/controller.php");
    } elseif ($role === 'faculty') {
        header("Location: faculty/controller.php");
    } else {
        exit("Invalid role.");
    }
}

// Fetch user data from portal users table
function fetch_user_by_id($portal, $id)
{
    $stmt = $portal->prepare("SELECT id, password, role FROM users WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user;
}

// === MAIN LOGIN LOGIC ===
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['login'])) {
    $username = strtolower(trim($_POST['username']));
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        exit("All fields are required.");
    }

    $user = fetch_user_by_id($portal, $username);
    $portal->close();

    if (!$user) {
        exit("User not registered.");
    }

    if (!password_verify($password, $user['password'])) {
        exit("Incorrect password.");
    }

    // Set session
    $_SESSION['username'] = $user['id'];
    $_SESSION['role'] = $user['role'];

    // Redirect to dashboard
    user_redirect($user['role']);
} else {
    exit("Access denied.");
}
