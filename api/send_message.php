<?php
session_start();
include_once '../db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit();
}

// Check if the request method is POST and message is set
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $user_id = $_SESSION['user_id'];
    $message = trim($_POST['message']);

    if (!empty($message)) {
        try {
            $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, message_text) VALUES (?, ?)");
            $stmt->bind_param("is", $user_id, $message);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            http_response_code(500); // Internal Server Error
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
    } else {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty']);
    }
} else {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}

$conn->close();
?>