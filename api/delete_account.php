<?php
session_start();
include_once '../db_connection.php';

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // If not logged in, just redirect to login page
    header("Location: ../index.html");
    exit();
}

// 2. Check if this is a POST request and password is provided
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['password'])) {
    header("Location: ../settings.php?error=Invalid+request");
    exit();
}

$logged_in_user_id = $_SESSION['user_id'];
$password_entered = $_POST['password'];

try {
    // 3. Verify Password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $logged_in_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        // Should not happen if session is valid, but good to check
        header("Location: ../settings.php?error=User+not+found");
        exit();
    }

    $user = $result->fetch_assoc();
    $hashed_password_from_db = $user['password'];
    $stmt->close();

    if (password_verify($password_entered, $hashed_password_from_db)) {
        // Password is correct, proceed with deletion

        // 4. Delete User Data (add more tables if needed)
        // Order matters due to foreign key constraints (delete children first)
        $conn->begin_transaction();

        // Delete chat messages
        $stmt_chat = $conn->prepare("DELETE FROM chat_messages WHERE user_id = ?");
        $stmt_chat->bind_param("i", $logged_in_user_id);
        $stmt_chat->execute();
        $stmt_chat->close();

        // Delete alumni profile
        $stmt_profile = $conn->prepare("DELETE FROM alumni_profiles WHERE user_id = ?");
        $stmt_profile->bind_param("i", $logged_in_user_id);
        $stmt_profile->execute();
        $stmt_profile->close();

        // Delete user record itself
        $stmt_user = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt_user->bind_param("i", $logged_in_user_id);
        $stmt_user->execute();
        $stmt_user->close();

        // Commit transaction
        $conn->commit();

        // 5. Log the user out completely
        session_unset();
        session_destroy();

        // 6. Redirect to login page with success message
        header("Location: ../index.html?success=Account+deleted+successfully");
        exit();

    } else {
        // Incorrect password
        header("Location: ../settings.php?error=Incorrect+password+for+account+deletion");
        exit();
    }

} catch (Exception $e) {
    $conn->rollback(); // Rollback changes if any error occurred
    header("Location: ../settings.php?error=An+error+occurred+during+deletion:+" . urlencode($e->getMessage()));
    exit();
}

$conn->close();
?>