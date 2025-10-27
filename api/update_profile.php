<?php
// api/update_profile.php

session_start();
include_once '../db_connection.php'; // Go up one level to find the connection file

// 1. CHECK PERMISSIONS
// -----------------------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    header("Location: ../profile.php?error=Not+logged+in");
    exit();
}

$logged_in_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'guest';
$user_to_update = $_POST['user_id_to_update'];

// Security Check: Only allow Admins or the user themselves to edit
if ($user_role !== 'admin' && $logged_in_user_id != $user_to_update) {
    header("Location: ../profile.php?error=Permission+denied");
    exit();
}

// 2. HANDLE FILE UPLOADS
// -----------------------------------------------------------------
$upload_dir = '../uploads/'; // The folder we created

// Start with the existing image paths (passed from hidden inputs)
$profile_image_path = $_POST['current_profile_image'] ?? '';
$banner_image_path = $_POST['current_banner_image'] ?? '';

// Helper function for uploading (silently fails)
function handleUpload($file_key, $upload_dir) {
    if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == 0) {
        $filename = uniqid() . '-' . basename($_FILES[$file_key]['name']);
        $target_path = $upload_dir . $filename;

        if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $target_path)) {
            return 'uploads/' . $filename; // Return the relative path to store in DB
        }
    }
    return null; // Return null if upload failed or no file
}

// Check for new profile image
$new_profile_img = handleUpload('profile_image', $upload_dir);
if ($new_profile_img) {
    $profile_image_path = $new_profile_img;
}

// Check for new banner image
$new_banner_img = handleUpload('banner_image', $upload_dir);
if ($new_banner_img) {
    $banner_image_path = $new_banner_img;
}


// 3. GET ALL FORM DATA
// -----------------------------------------------------------------
$first_name = $_POST['first_name'] ?? '';
$last_name = $_POST['last_name'] ?? '';
$company = $_POST['company'] ?? '';
$job_title = $_POST['job_title'] ?? '';
$batch_years = $_POST['batch_years'] ?? '';
$graduation_year = $_POST['graduation_year'] ? (int)$_POST['graduation_year'] : null; // Cast to integer or null
$skills = $_POST['skills'] ?? '';
$linkedin_url = $_POST['linkedin_url'] ?? '';
$github_url = $_POST['github_url'] ?? '';
$instagram_url = $_POST['instagram_url'] ?? '';
$website_url = $_POST['website_url'] ?? '';


// 4. EXECUTE DATABASE QUERY (INSERT or UPDATE)
// -----------------------------------------------------------------
try {
    // Check if a profile already exists
    $stmt = $conn->prepare("SELECT user_id FROM alumni_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_to_update);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        // UPDATE existing profile
        $sql = "UPDATE alumni_profiles SET 
                    first_name = ?, last_name = ?, company = ?, job_title = ?, 
                    batch_years = ?, graduation_year = ?, skills = ?, 
                    profile_image = ?, banner_image = ?, 
                    linkedin_url = ?, github_url = ?, 
                    instagram_url = ?, website_url = ?
                WHERE user_id = ?";
        
        $types = "sssssisssssssi";
        $params = [
            $first_name, $last_name, $company, $job_title,
            $batch_years, $graduation_year, $skills,
            $profile_image_path, $banner_image_path,
            $linkedin_url, $github_url, $instagram_url, $website_url,
            $user_to_update
        ];

    } else {
        // INSERT new profile
        $sql = "INSERT INTO alumni_profiles 
                    (first_name, last_name, company, job_title, batch_years, graduation_year, skills, 
                     profile_image, banner_image, linkedin_url, github_url, instagram_url, website_url, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $types = "sssssisssssssi";
        $params = [
            $first_name, $last_name, $company, $job_title,
            $batch_years, $graduation_year, $skills,
            $profile_image_path, $banner_image_path,
            $linkedin_url, $github_url, $instagram_url, $website_url,
            $user_to_update
        ];
    }

    // Execute the query
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params); // Use splat operator to pass params
    $stmt->execute();
    $stmt->close();

    // 5. UPDATE SESSION & REDIRECT
    // -----------------------------------------------------------------
    $_SESSION['first_name'] = $first_name;
    $_SESSION['last_name'] = $last_name;
    // Only update the session image if a new one was successfully uploaded
    if ($new_profile_img) {
        $_SESSION['profile_image_url'] = $profile_image_path;
    }
    
    header("Location: ../profile.php?success=1");
    exit();

} catch (Exception $e) {
    header("Location: ../profile.php?error=" . urlencode($e->getMessage()));
    exit();
}

$conn->close();
?>