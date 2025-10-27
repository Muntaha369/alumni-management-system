<?php
// --- 1. Database Connection ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "alumni_db";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- 2. Get Data from Form ---
$email = $_POST['signup-email'] ?? '';
$password = $_POST['signup-password'] ?? '';

// --- 3. Validate Data ---
$adminDomain = '@admin.college.edu';
$alumniDomain = '@alumni.college.edu';
$studentDomain = '@student.college.edu';
$role = '';

if (str_ends_with($email, $adminDomain)) {
    $role = 'admin';
} elseif (str_ends_with($email, $alumniDomain)) {
    $role = 'alumni';
} elseif (str_ends_with($email, $studentDomain)) {
    $role = 'student';
} else {
    // Redirect back to the form with an error message
    header("Location: ../index.html?error=Please_use_a_valid_college_email");
    exit();
}

if (strlen($password) < 6) {
    header("Location: ../index.html?error=Password_must_be_at_least_6_characters");
    exit();
}

// --- 4. Prepare and Execute SQL Query ---

// Check if email already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    header("Location: ../index.html?error=This_email_is_already_registered");
} else {
    // Hash the password for security
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $hashed_password, $role);

    if ($stmt->execute()) {
        // Redirect back with a success message
        header("Location: ../index.html?success=Registration_successful!_Please_log_in.");
    } else {
        header("Location: ../index.html?error=An_error_occurred._Please_try_again.");
    }
}

// --- 5. Close Connections ---
$stmt->close();
$conn->close();
exit();
?>