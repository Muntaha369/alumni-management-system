<?php
session_start();
include_once '../db_connection.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    try {
        $stmt = $conn->prepare("UPDATE users SET last_event_view = NOW() WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    $conn->close();
} else {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
}
?>