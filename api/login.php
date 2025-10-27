<?php
// This MUST be at the very top of the file
session_start();

// --- 1. Database Connection ---
// Use the connection file we already created
include_once '../db_connection.php'; 

// --- 2. Get Data from Form ---
// Make sure your HTML form has name="login-email" and name="login-password"
$email = $_POST['login-email'] ?? '';
$password = $_POST['login-password'] ?? '';

if (empty($email) || empty($password)) {
    header("Location: ../index.html?error=Email+and+password+are+required");
    exit();
}

// --- 3. Fetch User and Profile Data ---
try {
    // UPDATED THE SQL QUERY:
    // Changed 'p.profile_image_url' to 'p.profile_image'
    $sql = "
        SELECT 
            u.id, u.email, u.password, u.role,
            p.first_name, p.last_name, p.profile_image 
        FROM users u
        LEFT JOIN alumni_profiles p ON u.id = p.user_id
        WHERE u.email = ?
    ";

    $stmt = $conn->prepare($sql);
    
    // Check if prepare failed (this is what causes the error)
    if ($stmt === false) {
        die("SQL Prepare Failed: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify the hashed password
        if (password_verify($password, $user['password'])) {
            // Password is correct! Store all needed data in the session.
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            
            // UPDATED THE SESSION KEY:
            // This makes it consistent with profile.php
            $_SESSION['profile_image_url'] = $user['profile_image']; 

            // --- FIXED REDIRECT LOGIC ---
            if ($user['role'] === 'admin') { // <-- FIX 1: 'Admin' changed to 'admin'
                header("Location: ../admin.php"); // <-- FIX 2: Redirected to admin.php
            } elseif ($user['role'] === 'alumni') { // <-- FIX 1: 'Alumni' changed to 'alumni'
                header("Location: ../profile.php"); // Go to their profile
            } elseif ($user['role'] === 'student') { // <-- FIX 1: 'Student' changed to 'student'
                header("Location: ../directory.php"); // Go to the directory
            } else {
                header("Location: ../index.html?error=Unknown+role");
            }
            exit();
        }
    }

    // If login fails for any reason, redirect back with an error
    header("Location: ../index.html?error=Invalid_email_or_password");
    exit();

} catch (Exception $e) {
    header("Location: ../index.html?error=" . urlencode($e->getMessage()));
    exit();
}

$conn->close();
?>