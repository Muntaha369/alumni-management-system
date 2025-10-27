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
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');

// 4. Validation (Basic: ensure names are not empty)
if (empty($first_name) || empty($last_name)) {
    header("Location: ../profile.php?error=First+and+Last+Name+cannot+be+empty");
    exit();
}

try {
    // 5. Update or Insert into alumni_profiles
    $sql = "INSERT INTO alumni_profiles (user_id, first_name, last_name)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
            first_name = VALUES(first_name),
            last_name = VALUES(last_name)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $logged_in_user_id, $first_name, $last_name);
    $stmt->execute();
    $stmt->close();

    // 6. Update Session
    $_SESSION['first_name'] = $first_name;
    $_SESSION['last_name'] = $last_name;

    // 7. Redirect with Success
    header("Location: ../profile.php?success=Display+Name+updated+successfully");
    exit();

} catch (Exception $e) {
    header("Location: ../profile.php?error=" . urlencode("Database error: " . $e->getMessage()));
    exit();
}

$conn->close();
?>