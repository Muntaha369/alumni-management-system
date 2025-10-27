<?php
// Must start the session to access session data
session_start();

// Check if the user is logged in. If not, redirect them to the login page.
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

// Get user data from the session
$user_email = $_SESSION['user_email'];
$user_role = $_SESSION['user_role'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; flex-direction: column; }
        .container { padding: 2rem; border: 1px solid #ccc; border-radius: 8px; text-align: center; }
        a { color: #005A9C; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome to the Alumni Platform!</h1>
        <p>You are logged in as: <strong><?php echo htmlspecialchars($user_email); ?></strong></p>
        <p>Your role is: <strong><?php echo htmlspecialchars($user_role); ?></strong></p>
        <br>
        <a href="api/logout.php">Logout</a>
    </div>
</body>
</html>