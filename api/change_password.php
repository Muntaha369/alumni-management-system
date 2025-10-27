<?php
session_start();
include_once '../db_connection.php';

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../profile.php?error=Not+logged+in");
    exit();
}

// 2. Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../profile.php");
    exit();
}

// 3. Get Form Data
$logged_in_user_id = $_SESSION['user_id'];
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// 4. Validation
if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    header("Location: ../profile.php?error=All+fields+are+required");
    exit();
}

if (strlen($new_password) < 6) {
    header("Location: ../profile.php?error=New+password+must+be+at+least+6+characters");
    exit();
}

if ($new_password !== $confirm_password) {
    header("Location: ../profile.php?error=New+passwords+do+not+match");
    exit();
}

try {
    // 5. Check Current Password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $logged_in_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows !== 1) {
        header("Location: ../profile.php?error=User+not+found");
        exit();
    }
    
    $user = $result->fetch_assoc();
    $hashed_password_from_db = $user['password'];

    if (password_verify($current_password, $hashed_password_from_db)) {
        // 6. Update to New Password
        $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update_stmt->bind_param("si", $new_hashed_password, $logged_in_user_id);
        $update_stmt->execute();
        $update_stmt->close();

        header("Location: ../profile.php?success=Password+changed+successfully");
        exit();
        
    } else {
        header("Location: ../profile.php?error=Incorrect+current+password");
        exit();
    }

} catch (Exception $e) {
    header("Location: ../profile.php?error=" . urlencode($e->getMessage()));
    exit();
}

$conn->close();
?>