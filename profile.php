<?php
session_start();

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

// 2. Database & Session Variables for Logged-in User
include_once 'db_connection.php';
$logged_in_user_id = $_SESSION['user_id'];
$logged_in_user_role = $_SESSION['user_role'] ?? 'guest';
$logged_in_first_name = $_SESSION['first_name'] ?? '';
$logged_in_last_name = $_SESSION['last_name'] ?? '';
$logged_in_display_name = trim(($logged_in_first_name ?: '') . ' ' . ($logged_in_last_name ?: ''));
if (empty($logged_in_display_name)) {
    $logged_in_display_name = $_SESSION['user_email'];
}
$logged_in_avatar_image = $_SESSION['profile_image_url'] ?? 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#ccc"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>');
$current_page = basename($_SERVER['PHP_SELF']);


// 3. Determine Whose Profile to Display/Edit
// -----------------------------------------------------------------
$user_to_display_id = $logged_in_user_id; // Default to self
$role_of_user_being_displayed = $logged_in_user_role;
$is_editing_other_user = false;

// Check if an admin is trying to edit a specific user via URL
if ($logged_in_user_role === 'admin' && isset($_GET['user_id'])) {
    $target_user_id_from_url = filter_var($_GET['user_id'], FILTER_SANITIZE_NUMBER_INT);
    if ($target_user_id_from_url && $target_user_id_from_url != $logged_in_user_id) {
        // Verify the target user exists and get their role
        try {
            $stmt_target = $conn->prepare("SELECT role, email FROM users WHERE id = ?");
            $stmt_target->bind_param("i", $target_user_id_from_url);
            $stmt_target->execute();
            $target_result = $stmt_target->get_result();
            if ($target_result->num_rows === 1) {
                $target_user_data = $target_result->fetch_assoc();
                $user_to_display_id = $target_user_id_from_url;
                $role_of_user_being_displayed = $target_user_data['role'];
                $is_editing_other_user = true;
                // Fetch target user's profile details for display name if needed later
                // (This part might need refinement depending on what you want to show)
            } else {
                // User ID from URL not found, redirect or show error
                header("Location: directory.php?error=User+not+found");
                exit();
            }
            $stmt_target->close();
        } catch (Exception $e) {
            die("Error fetching target user details: " . $e->getMessage());
        }
    }
}


// 4. LOGIC FORK: Load data and set page title based on the ROLE of the user being displayed
// ----------------------------------------------------------------------------------------

if ($role_of_user_being_displayed === 'alumni') {
    // --- SHOW ALUMNI PROFILE EDITOR VIEW ---
    $page_title = $is_editing_other_user ? "Edit Alumni Profile" : "Profile Dashboard";

    // Fetch the alumni profile data for the user being displayed
    try {
        $stmt = $conn->prepare("SELECT * FROM alumni_profiles WHERE user_id = ?");
        $stmt->bind_param("i", $user_to_display_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $profile = $result->fetch_assoc(); // This holds the data for the form
        $stmt->close();
    } catch (Exception $e) { die("Error fetching profile: " . $e->getMessage()); }
    if (empty($profile)) { $profile = []; } // Ensure $profile is an array even if empty

    // Profile Completion Logic (only makes sense when viewing own profile)
    $percentage = 0;
    if (!$is_editing_other_user) {
        $total_fields = 10; $completed_fields = 0;
        if (!empty($profile['first_name'])) $completed_fields++;
        if (!empty($profile['last_name'])) $completed_fields++;
        if (!empty($profile['company'])) $completed_fields++;
        if (!empty($profile['job_title'])) $completed_fields++;
        if (!empty($profile['batch_years'])) $completed_fields++;
        if (!empty($profile['graduation_year'])) $completed_fields++;
        if (!empty($profile['skills'])) $completed_fields++;
        if (!empty($profile['profile_image']) && !str_contains($profile['profile_image'], 'placeholder')) $completed_fields++;
        if (!empty($profile['banner_image']) && !str_contains($profile['banner_image'], 'placeholder')) $completed_fields++;
        if (!empty($profile['linkedin_url']) || !empty($profile['github_url']) || !empty($profile['instagram_url']) || !empty($profile['website_url'])) { $completed_fields++; }
        $percentage = ($total_fields > 0) ? round(($completed_fields / $total_fields) * 100) : 0;
    }

    // Preview Card Variables (using the fetched $profile data)
    $preview_avatar = $profile['profile_image'] ?? $logged_in_avatar_image; // Fallback carefully
    $preview_banner = $profile['banner_image'] ?? 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mN8/x8AAuMB8DtXNJsAAAAASUVORK5CYII=';
    $preview_name = htmlspecialchars(trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')));
    if (empty($preview_name)) $preview_name = "Alumnus Name"; // Default for editing others
    $preview_job = htmlspecialchars($profile['job_title'] ?? 'Job Title');
    $preview_company = htmlspecialchars($profile['company'] ?? 'Company');
    $preview_skills = !empty($profile['skills']) ? explode(',', $profile['skills']) : ['Skills not set'];

} else {
    // --- SHOW ADMIN / STUDENT ACCOUNT VIEW ---
    // This view should ONLY be for the logged-in user editing themselves.
    // If an admin tries to view another admin/student profile via URL, redirect them.
    if ($is_editing_other_user) {
         header("Location: directory.php?error=Cannot+edit+non-alumni+profiles+here");
         exit();
    }

    $page_title = "My Account";
    // Need current names for the update forms
    $current_first_name = $logged_in_first_name;
    $current_last_name = $logged_in_last_name;

    if ($logged_in_user_role === 'admin') {
        // Fetch stats for the admin quick-links
        $total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
        $total_alumni = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'alumni'")->fetch_assoc()['count'];
        $total_events = $conn->query("SELECT COUNT(*) as count FROM events")->fetch_assoc()['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        /* --- All existing CSS is unchanged --- */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        :root { --primary-color: #0d6efd; --background-color: #f8f9fa; --card-bg-color: #ffffff; --text-color: #212529; --nav-link-color: #495057; --border-color: #dee2e6; --success-color: #198754; --danger-color: #dc3545; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: var(--background-color); color: var(--text-color); }
        .navbar { background-color: var(--card-bg-color); border-bottom: 1px solid var(--border-color); padding: 0.8rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .navbar-left { display: flex; align-items: center; gap: 2rem; }
        .navbar-logo { font-size: 1.5rem; font-weight: 700; color: var(--primary-color); text-decoration: none; }
        .nav-links { list-style: none; display: flex; gap: 1.5rem; }
        .nav-links li { position: relative; } /* Dot */
        .nav-links a { text-decoration: none; color: var(--nav-link-color); font-weight: 500; padding: 0.5rem 0; border-bottom: 2px solid transparent; transition: color 0.2s ease, border-color 0.2s ease; }
        .nav-links a:hover { color: var(--primary-color); }
        .nav-links a.active { color: var(--primary-color); border-bottom-color: var(--primary-color); }
        .profile-dropdown { position: relative; display: flex; align-items: center; cursor: pointer; }
        .profile-dropdown img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .profile-dropdown span { margin-left: 0.75rem; font-weight: 600; font-size: 0.9rem; }
        .dropdown-menu { position: absolute; top: 140%; right: 0; background-color: var(--card-bg-color); border: 1px solid var(--border-color); border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 180px; z-index: 100; opacity: 0; visibility: hidden; transform: translateY(10px); transition: all 0.2s ease; }
        .profile-dropdown:hover .dropdown-menu { opacity: 1; visibility: visible; transform: translateY(0); }
        .dropdown-menu a { display: block; padding: 0.75rem 1rem; text-decoration: none; color: var(--text-color); }
        .dropdown-menu a:hover { background-color: #f8f9fa; }
        .dropdown-menu a.logout { color: #dc3545; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }
        .success-message { padding: 1rem; background-color: #d1e7dd; color: var(--success-color); border: 1px solid #badbcc; border-radius: 6px; margin-bottom: 2rem; }
        .error-message { padding: 1rem; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 6px; margin-bottom: 2rem; }
        .dashboard-widget { background-color: var(--card-bg-color); padding: 2rem; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 2rem; }
        .progress-widget h2 { font-size: 1.25rem; margin-bottom: 1rem; }
        .progress-bar-container { width: 100%; background-color: #e9ecef; border-radius: 8px; height: 20px; overflow: hidden; }
        .progress-bar-inner { height: 100%; background-color: var(--success-color); border-radius: 8px; text-align: center; color: white; font-weight: 600; font-size: 0.8rem; line-height: 20px; transition: width 0.4s ease; }
        .dashboard-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; }
        @media (max-width: 992px) { .dashboard-grid { grid-template-columns: 1fr; } }
        .profile-preview-card { background-color: var(--card-bg-color); border-radius: 8px; border: 1px solid var(--border-color); overflow: hidden; height: fit-content; position: sticky; top: 2rem; }
        .preview-banner { width: 100%; height: 120px; background-color: #eee; background-image: url('<?php echo htmlspecialchars($preview_banner); ?>'); background-size: cover; background-position: center; }
        .preview-content { padding: 1.5rem; position: relative; text-align: center; }
        .preview-avatar { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid var(--card-bg-color); margin-top: -60px; background-color: #fff; }
        .preview-name { font-size: 1.5rem; font-weight: 700; margin-top: 0.5rem; }
        .preview-job { font-size: 1rem; font-weight: 500; color: var(--primary-color); margin-top: 0.25rem; }
        .preview-skills { margin-top: 1.5rem; display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: center; }
        .preview-skill-tag { background-color: #e9ecef; color: #495057; padding: 0.25rem 0.75rem; border-radius: 16px; font-size: 0.8rem; font-weight: 500; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .input-group { margin-bottom: 1.5rem; }
        .input-group.full-width { grid-column: 1 / -1; }
        .input-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .input-group input, .input-group select, .input-group textarea { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 1rem; font-family: 'Inter', sans-serif; }
        .input-group textarea { resize: vertical; min-height: 100px; }
        .input-group input[type="file"] { padding: 0.5rem; }
        .form-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; }
        .submit-btn { background-color: var(--primary-color); color: white; border: none; padding: 0.8rem 2rem; border-radius: 6px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background-color 0.2s; }
        .submit-btn:hover { background-color: #0b5ed7; }
        .delete-btn { background-color: transparent; color: var(--danger-color); border: 1px solid var(--danger-color); padding: 0.8rem 1.5rem; border-radius: 6px; font-size: 1rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .delete-btn:hover { background-color: var(--danger-color); color: white; }
        .account-info { background-color: var(--card-bg-color); padding: 2rem; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 2rem; max-width: 600px; }
        .account-info h2 { font-size: 1.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; color: var(--primary-color); }
        .info-row { display: grid; grid-template-columns: 150px 1fr; gap: 1rem; margin-bottom: 1rem; font-size: 1rem; }
        .info-row .info-label { font-weight: 600; color: #495057; }
        .info-row .info-value { font-weight: 500; }
        .info-row .info-value .role-badge { background-color: var(--primary-color); color: white; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.9rem; text-transform: capitalize; }
        .account-info .input-group { margin-bottom: 1rem; }
        .actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; }
        .action-card { background-color: var(--card-bg-color); border: 1px solid var(--border-color); padding: 1.5rem; border-radius: 12px; text-decoration: none; color: var(--text-color); transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .action-card:hover { transform: translateY(-5px); box-shadow: 0 8px 16px rgba(0,0,0,0.08); }
        .action-card h3 { color: var(--primary-color); font-size: 1.25rem; }
        .action-card h3 span { margin-right: 0.5rem; }
        .notification-dot { position: absolute; top: 5px; right: -8px; width: 8px; height: 8px; background-color: var(--danger-color); border-radius: 50%; display: none; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-left">
            <a href="index.php" class="navbar-logo">AlumniHub</a>
            <ul class="nav-links">
                <li><a href="index.php" class="<?php if($current_page == 'index.php') echo 'active'; ?>">Home</a></li>
                <li><a href="directory.php" class="<?php if($current_page == 'directory.php') echo 'active'; ?>">Directory</a></li>
                <li id="nav-events-li"><a href="events.php" class="<?php if($current_page == 'events.php') echo 'active'; ?>">Events</a><span class="notification-dot" id="events-dot"></span></li>
                <li><a href="donate.php" class="<?php if($current_page == 'donate.php') echo 'active'; ?>">Donate</a></li>
                <?php if ($logged_in_user_role === 'alumni'): ?>
                    <li id="nav-chat-li"><a href="chat.php" class="<?php if($current_page == 'chat.php') echo 'active'; ?>">Chat Room</a><span class="notification-dot" id="chat-dot"></span></li>
                <?php endif; ?>
                <?php if ($logged_in_user_role === 'admin'): ?>
                    <li><a href="admin.php" class="<?php if($current_page == 'admin.php') echo 'active'; ?>">Admin</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="profile-dropdown">
            <img src="<?php echo htmlspecialchars($logged_in_avatar_image); ?>" alt="Profile Picture">
            <span><?php echo htmlspecialchars($logged_in_display_name); ?></span>
            <div class="dropdown-menu">
                <a href="profile.php">My Profile</a>
                <a href="settings.php">Settings</a>
                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 0.2rem 0;">
                <a href="api/logout.php" class="logout">Logout</a>
            </div>
        </div>
    </nav>

    <main class="container">

        <?php if (isset($_GET['success'])): ?>
            <div class="success-message"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="error-message">
                <strong>Error:</strong> <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <?php if ($role_of_user_being_displayed === 'alumni'): ?>

            <?php if (!$is_editing_other_user) : ?>
                <h1>Your Profile Dashboard</h1>
                <p style="margin-bottom: 2rem;">Use this page to manage your public profile.</p>
                <div class="dashboard-widget progress-widget">
                    <h2>Your profile is <?php echo $percentage; ?>% complete.</h2>
                    <div class="progress-bar-container"><div class="progress-bar-inner" style="width: <?php echo $percentage; ?>%;"><?php echo $percentage; ?>%</div></div>
                </div>
            <?php else: ?>
                <h1>Edit Alumni Profile</h1>
                <p style="margin-bottom: 2rem;">You are editing the profile for <?php echo htmlspecialchars($profile['first_name'] ?? 'Alumnus'); ?>.</p>
            <?php endif; ?>

            <div class="dashboard-grid">
                <div class="profile-preview-card">
                    <div class="preview-banner" style="background-image: url('<?php echo htmlspecialchars($preview_banner); ?>');"></div>
                    <div class="preview-content">
                        <img src="<?php echo htmlspecialchars($preview_avatar); ?>" alt="Profile Avatar" class="preview-avatar">
                        <h3 class="preview-name"><?php echo $preview_name; ?></h3>
                        <p class="preview-job"><?php echo $preview_job; ?> at <?php echo $preview_company; ?></p>
                        <div class="preview-skills"><?php foreach($preview_skills as $skill): ?><span class="preview-skill-tag"><?php echo htmlspecialchars(trim($skill)); ?></span><?php endforeach; ?></div>
                        </div>
                </div>

                <div class="profile-form-container">
                    <form action="api/update_profile.php" method="POST" class="dashboard-widget" enctype="multipart/form-data">
                        <input type="hidden" name="user_id_to_update" value="<?php echo htmlspecialchars($user_to_display_id); ?>">
                        <input type="hidden" name="current_profile_image" value="<?php echo htmlspecialchars($profile['profile_image'] ?? ''); ?>">
                        <input type="hidden" name="current_banner_image" value="<?php echo htmlspecialchars($profile['banner_image'] ?? ''); ?>">

                        <h3 style="margin-bottom: 1.5rem;">Basic Information</h3>
                        <div class="form-grid">
                            <div class="input-group"><label for="first_name">First Name</label><input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" required></div>
                            <div class="input-group"><label for="last_name">Last Name</label><input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" required></div>
                        </div>
                        <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 1rem 0 2rem 0;">
                        <h3 style="margin-bottom: 1.5rem;">Academic & Professional</h3>
                        <div class="form-grid">
                            <div class="input-group"><label for="company">Company</label><input type="text" id="company" name="company" value="<?php echo htmlspecialchars($profile['company'] ?? ''); ?>"></div>
                            <div class="input-group"><label for="job_title">Job Title</label><input type="text" id="job_title" name="job_title" value="<?php echo htmlspecialchars($profile['job_title'] ?? ''); ?>"></div>
                        </div>
                        <div class="form-grid">
                             <div class="input-group"><label for="batch_years">Batch Years</label><input type="text" id="batch_years" name="batch_years" value="<?php echo htmlspecialchars($profile['batch_years'] ?? ''); ?>"></div>
                            <div class="input-group"><label for="graduation_year">Graduation Year</label><select id="graduation__year" name="graduation_year"><option value="">Select Year</option><?php $current_year = date('Y'); $selected_year = $profile['graduation_year'] ?? ''; for ($year = $current_year; $year >= 1980; $year--) { $selected = ($year == $selected_year) ? 'selected' : ''; echo "<option value=\"$year\" $selected>$year</option>"; } ?></select></div>
                        </div>
                        <div class="input-group full-width"><label for="skills">Skills</label><textarea id="skills" name="skills"><?php echo htmlspecialchars($profile['skills'] ?? ''); ?></textarea></div>
                        <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 1rem 0 2rem 0;">
                        <h3 style="margin-bottom: 1.5rem;">Images & Social Links</h3>
                        <div class="input-group full-width"><label for="profile_image">Profile Image</label><input type="file" id="profile_image" name="profile_image" accept="image/png, image/jpeg"></div>
                        <div class="input-group full-width"><label for="banner_image">Banner Image</label><input type="file" id="banner_image" name="banner_image" accept="image/png, image/jpeg"></div>
                        <div class="form-grid">
                            <div class="input-group"><label for="linkedin_url">LinkedIn Profile URL</label><input type="url" id="linkedin_url" name="linkedin_url" value="<?php echo htmlspecialchars($profile['linkedin_url'] ?? ''); ?>"></div>
                            <div class="input-group"><label for="github_url">GitHub Profile URL</label><input type="url" id="github_url" name="github_url" value="<?php echo htmlspecialchars($profile['github_url'] ?? ''); ?>"></div>
                        </div>
                         <div class="form-grid">
                            <div class="input-group"><label for="instagram_url">Instagram URL</label><input type="url" id="instagram_url" name="instagram_url" value="<?php echo htmlspecialchars($profile['instagram_url'] ?? ''); ?>"></div>
                            <div class="input-group"><label for="website_url">Personal Website URL</label><input type="url" id="website_url" name="website_url" value="<?php echo htmlspecialchars($profile['website_url'] ?? ''); ?>"></div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="submit-btn">Save Changes</button>
                            <?php if ($logged_in_user_role === 'admin' || !$is_editing_other_user): ?>
                            <a href="api/delete_profile.php?user_id=<?php echo htmlspecialchars($user_to_display_id); ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this profile card? This does not delete the user account.');">Delete Profile Card</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            <?php else: ?>

            <h1>My Account</h1>

            <div class="account-info">
                <h2>Account Information</h2>
                <div class="info-row"><span class="info-label">Display Name</span><span class="info-value"><?php echo htmlspecialchars($logged_in_display_name); ?></span></div>
                <div class="info-row"><span class="info-label">Email</span><span class="info-value"><?php echo htmlspecialchars($_SESSION['user_email']); ?></span></div>
                <div class="info-row"><span class="info-label">Role</span><span class="info-value"><span class="role-badge"><?php echo htmlspecialchars($logged_in_user_role); ?></span></span></div>
            </div>

            <div class="account-info">
                <h2>Update Display Name</h2>
                <form action="api/update_account_details.php" method="POST">
                     <div class="input-group"><label for="first_name">First Name</label><input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($current_first_name); ?>" required></div>
                    <div class="input-group"><label for="last_name">Last Name</label><input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($current_last_name); ?>" required></div>
                    <button type="submit" class="submit-btn">Update Name</button>
                </form>
            </div>

             <div class="account-info">
                <h2>Update Email Address</h2>
                <form action="api/update_email.php" method="POST">
                     <div class="input-group"><label for="new_email">New Email Address</label><input type="email" id="new_email" name="new_email" value="<?php echo htmlspecialchars($_SESSION['user_email']); ?>" required></div>
                    <div class="input-group"><label for="confirm_password_email">Enter Your Current Password to Confirm</label><input type="password" id="confirm_password_email" name="password" required></div>
                    <button type="submit" class="submit-btn">Update Email</button>
                </form>
            </div>

            <div class="account-info">
                <h2>Change Password</h2>
                <form action="api/change_password.php" method="POST">
                    <div class="input-group"><label for="current_password">Current Password</label><input type="password" id="current_password" name="current_password" required></div>
                    <div class="input-group"><label for="new_password">New Password</label><input type="password" id="new_password" name="new_password" minlength="6" required></div>
                    <div class="input-group"><label for="confirm_password">Confirm New Password</label><input type="password" id="confirm_password" name="confirm_password" minlength="6" required></div>
                    <button type="submit" class="submit-btn">Update Password</button>
                </form>
            </div>

            <?php if ($logged_in_user_role === 'admin'): ?>
                <h2>Admin Quick Actions</h2>
                <hr style="margin: 1rem 0 1.5rem 0; border: 0; border-top: 1px solid var(--border-color);">
                <section class="actions-grid">
                    <a href="admin/manage_users.php" class="action-card"><h3><span>👥</span>Manage Users (<?php echo $total_users; ?>)</h3><p>View, edit, and delete all user accounts.</p></a>
                    <a href="admin/manage_events.php" class="action-card"><h3><span>📅</span>Manage Events (<?php echo $total_events; ?>)</h3><p>Create, update, and delete upcoming and past events.</p></a>
                    <a href="admin/broadcast.php" class="action-card"><h3><span>📧</span>Email Broadcast</h3><p>Send announcements to all (<?php echo $total_alumni; ?>) alumni.</p></a>
                </section>
            <?php endif; ?>
            <?php endif; ?>

    </main>

    <script>
        // --- Notification Polling code --- (Unchanged)
        const chatDot = document.getElementById('chat-dot');
        const eventsDot = document.getElementById('events-dot');
        const hasChatLink = <?php echo ($logged_in_user_role === 'alumni') ? 'true' : 'false'; ?>;
        async function checkNotifications() { try { const response = await fetch('api/check_notifications.php'); const data = await response.json(); if (eventsDot) { eventsDot.style.display = data.new_events ? 'block' : 'none'; } if (hasChatLink && chatDot) { chatDot.style.display = data.new_messages ? 'block' : 'none'; } } catch (error) { console.error("Error checking notifications:", error); } } checkNotifications(); setInterval(checkNotifications, 15000);
    </script>
</body>
</html>