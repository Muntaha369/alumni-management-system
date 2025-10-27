<?php
session_start();

// Security: Redirect if not logged in or not an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.html");
    exit();
}

$message = ''; // For success/error messages

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Database Connection ---
    $conn = new mysqli("localhost", "root", "", "alumni_db");
    if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

    $subject = $_POST['subject'] ?? 'A Message from AlumniHub';
    $body = $_POST['body'] ?? '';

    if (empty($subject) || empty($body)) {
        $message = '<div class="message error">Subject and message cannot be empty.</div>';
    } else {
        // Fetch all alumni emails
        $result = $conn->query("SELECT email FROM users WHERE role = 'alumni'");
        
        if ($result->num_rows > 0) {
            $headers = "From: no-reply@alumnihub.com" . "\r\n" .
                       "Reply-To: no-reply@alumnihub.com" . "\r\n" .
                       "Content-Type: text/html; charset=UTF-8" . "\r\n" .
                       "X-Mailer: PHP/" . phpversion();
            
            $email_body = "<html><body>";
            $email_body .= nl2br(htmlspecialchars($body)); // Convert newlines to <br> and sanitize
            $email_body .= "</body></html>";

            $sent_count = 0;
            $failed_count = 0;

            while ($row = $result->fetch_assoc()) {
                $to = $row['email'];
                if (mail($to, $subject, $email_body, $headers)) {
                    $sent_count++;
                } else {
                    $failed_count++;
                }
            }
            
            $message = "<div class='message success'>Broadcast sent! {$sent_count} emails sent successfully. {$failed_count} failed.</div>";
        } else {
            $message = '<div class="message error">No alumni found to send emails to.</div>';
        }
        $conn->close();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Broadcast</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        :root { --primary-color: #0d6efd; --background-color: #f8f9fa; --card-bg-color: #ffffff; --text-color: #212529; --border-color: #dee2e6; --error-color: #dc3545; --success-color: #198754; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: var(--background-color); color: var(--text-color); }
        .container { max-width: 800px; margin: 2rem auto; padding: 0 2rem; }
        .message { padding: 1rem; margin-bottom: 1rem; border-radius: 6px; }
        .success { background-color: #d1e7dd; color: var(--success-color); border: 1px solid #badbcc; }
        .error { background-color: #f8d7da; color: var(--error-color); border: 1px solid #f5c2c7; }
        .form-container { background-color: var(--card-bg-color); border: 1px solid var(--border-color); border-radius: 8px; padding: 2rem; }
        h1 { margin-bottom: 1.5rem; }
        .input-group { margin-bottom: 1.5rem; }
        .input-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .input-group input, .input-group textarea { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 1rem; }
        .input-group textarea { min-height: 200px; resize: vertical; }
        .submit-btn { background-color: var(--primary-color); color: white; border: none; padding: 0.8rem 2rem; border-radius: 6px; font-size: 1rem; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <a href="../admin.php">&larr; Back to Admin Dashboard</a>
        <h1>Email Broadcast to Alumni</h1>

        <?php echo $message; ?>

        <div class="form-container">
            <form action="broadcast.php" method="POST">
                <div class="input-group">
                    <label for="subject">Subject</label>
                    <input type="text" name="subject" required>
                </div>
                <div class="input-group">
                    <label for="body">Message</label>
                    <textarea name="body" required></textarea>
                </div>
                <button type="submit" class="submit-btn">Send Broadcast</button>
            </form>
        </div>
    </div>
</body>
</html>