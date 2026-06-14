<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $user_type = $_POST['user_type'] ?? '';

    // Validate inputs
    if (empty($username) || empty($password) || $user_type === 'Select User Type') {
        header("Location: login.php?error=" . urlencode("All fields are required."));
        exit();
    }

    // Map selected user type to database role
    $expected_role = '';
    if ($user_type === 'Admin') {
        $expected_role = 'admin';
    } elseif ($user_type === 'Officer') {
        $expected_role = 'staff';
    } elseif ($user_type === 'Citizen') {
        $expected_role = 'citizen';
    } else {
        header("Location: login.php?error=" . urlencode("Invalid user type selected."));
        exit();
    }

    // Prepare statement to fetch user
    $query = "SELECT id, username, password, full_name, role FROM users WHERE username = ?";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        header("Location: login.php?error=" . urlencode("Database error. Please try again later."));
        exit();
    }

    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($user = mysqli_fetch_assoc($result)) {
        // Check password and role
        if (password_verify($password, $user['password'])) {
            if ($user['role'] === $expected_role) {
                // Regenerate session ID to prevent fixation
                session_regenerate_id(true);

                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: admin_dashboard.php");
                } elseif ($user['role'] === 'staff') {
                    // Fetch staff ID as well
                    $staff_query = "SELECT id FROM staff WHERE user_id = ?";
                    $st_stmt = mysqli_prepare($conn, $staff_query);
                    mysqli_stmt_bind_param($st_stmt, "i", $user['id']);
                    mysqli_stmt_execute($st_stmt);
                    $st_res = mysqli_stmt_get_result($st_stmt);
                    if ($st_row = mysqli_fetch_assoc($st_res)) {
                        $_SESSION['staff_id'] = $st_row['id'];
                    }
                    mysqli_stmt_close($st_stmt);

                    header("Location: staff_dashboard.php");
                } else {
                    header("Location: citizen_dashboard.php");
                }
                exit();
            } else {
                header("Location: login.php?error=" . urlencode("Role mismatch. Please select the correct user type."));
                exit();
            }
        } else {
            header("Location: login.php?error=" . urlencode("Invalid username or password."));
            exit();
        }
    } else {
        header("Location: login.php?error=" . urlencode("Invalid username or password."));
        exit();
    }
    mysqli_stmt_close($stmt);
} else {
    header("Location: login.php");
    exit();
}
?>
