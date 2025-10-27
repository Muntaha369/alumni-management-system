<?php
session_start();
include_once '../db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode([]);
    exit();
}

try {
    // Select the last 100 messages to avoid overloading the chat
    $sql = "
        SELECT 
            m.id, 
            m.user_id, 
            m.message_text, 
            m.timestamp, 
            p.first_name, 
            p.last_name, 
            u.email 
        FROM 
            (SELECT * FROM chat_messages ORDER BY timestamp DESC LIMIT 100) AS m
        JOIN 
            users u ON m.user_id = u.id
        LEFT JOIN 
            alumni_profiles p ON m.user_id = p.user_id
        ORDER BY 
            m.timestamp ASC
    ";
    
    $result = $conn->query($sql);
    
    $messages = [];
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
    }

    header('Content-Type: application/json');
    echo json_encode($messages);

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Could not fetch messages']);
}

$conn->close();
?>