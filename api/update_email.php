<?php
session_start();
include_once '../db_connection.php';

// 1. Check if logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../profile.php?error=Not+logged+in");
    exit();
}

// 2. Check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../profile.php");
    exit();
}

// 3. Get Data
$logged_in_user_id = $_SESSION['user_id'];
$new_email = trim($_POST['new_email'] ?? '');
$password = $_POST['password'] ?? '';

// 4. Validation
if (empty($new_email) || empty($password)) {
    header("Location: ../profile.php?error=New+Email+and+Password+are+required");
    exit();
}

if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
     header("Location: ../profile.php?error=Invalid+email+format");
    exit();
}

// Optional: Check domain (copy from register.php if needed)

try {
    // 5. Check if New Email Already Exists (for another user)
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt_check->bind_param("si", $new_email, $logged_in_user_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($result_check->num_rows > 0) {
        $stmt_check->close();
        header("Location: ../profile.php?error=This+email+is+already+registered+to+another+account");
        exit();
    }
    $stmt_check->close();

    // 6. Verify Current Password
    $stmt_pass = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt_pass->bind_param("i", $logged_in_user_id);
    $stmt_pass->execute();
    $result_pass = $stmt_pass->get_result();

    if ($result_pass->num_rows !== 1) {
        $stmt_pass->close();
        header("Location: ../profile.php?error=User+not+found");
        exit();
    }

    $user = $result_pass->fetch_assoc();
    $hashed_password_from_db = $user['password'];
    $stmt_pass->close();

    if (password_verify($password, $hashed_password_from_db)) {
        // Password is correct

        // 7. Update Email in users table
        $stmt_update = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt_update->bind_param("si", $new_email, $logged_in_user_id);
        $stmt_update->execute();
        $stmt_update->close();

        // 8. Update Session
        $_SESSION['user_email'] = $new_email;

        // 9. Redirect with Success
        header("Location: ../profile.php?success=Email+address+updated+successfully");
        exit();

    } else {
        // Incorrect password
        header("Location: ../profile.php?error=Incorrect+password+provided");
        exit();
    }

} catch (Exception $e) {
    header("Location: ../profile.php?error=" . urlencode("Database error: " . $e->getMessage()));
    exit();
}

$conn->close();
?>