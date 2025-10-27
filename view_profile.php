<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

// Get the ID of the profile to view from the URL (e.g., view_profile.php?id=1)
$profile_user_id = $_GET['id'] ?? 0;
if ($profile_user_id === 0) {
    // If no ID, and user is an Alumnus, redirect to their own profile.php
    if ($_SESSION['user_role'] === 'Alumni') {
        header("Location: profile.php");
        exit();
    }
    die("No profile specified.");
}

include_once 'db_connection.php'; // Use your connection file

// Fetch the profile data using a JOIN
// We get the email from 'users' and everything else from 'alumni_profiles'
$sql = "SELECT p.*, u.email 
        FROM alumni_profiles p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $profile_user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();
$stmt->close();

if (!$profile) {
    die("Profile not found or is incomplete.");
}

// --- Data for Navbar ---
$logged_in_user_role = $_SESSION['user_role'] ?? 'guest';
$logged_in_user_id = $_SESSION['user_id'] ?? 0;
$display_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if (empty($display_name)) {
    $display_name = $_SESSION['user_email'];
}
$avatar_image = $_SESSION['profile_image_url'] ?? 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#ccc"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>');
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?>'s Profile</title>
    <style>
        /* Include the same navbar and container CSS as your other pages */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        :root { --primary-color: #0d6efd; --background-color: #f8f9fa; --card-bg-color: #ffffff; --text-color: #212529; --nav-link-color: #495057; --border-color: #dee2e6; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: var(--background-color); color: var(--text-color); }
        .navbar { background-color: var(--card-bg-color); border-bottom: 1px solid var(--border-color); padding: 0.8rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .navbar-left { display: flex; align-items: center; gap: 2rem; }
        .navbar-logo { font-size: 1.5rem; font-weight: 700; color: var(--primary-color); text-decoration: none; }
        .nav-links { list-style: none; display: flex; gap: 1.5rem; }
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
        .container { max-width: 900px; margin: 2rem auto; padding: 0 2rem; }
        
        /* --- Profile View Styles --- */
        .profile-header {
            background-color: var(--card-bg-color);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            margin-bottom: 2rem;
            overflow: hidden; /* Added to contain banner */
        }
        .profile-banner {
            height: 220px;
            background-size: cover;
            background-position: center;
            background-color: #e9ecef; /* Fallback color */
        }
        .profile-details {
            padding: 1.5rem;
            position: relative;
        }
        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 5px solid white;
            position: absolute;
            top: -75px; /* Pulls it halfway over the banner */
            left: 1.5rem;
            object-fit: cover;
            background-color: #fff;
        }
        .profile-actions {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
        }
        .action-btn {
            background-color: var(--primary-color); color: white;
            text-decoration: none; padding: 0.5rem 1rem;
            border-radius: 6px; font-weight: 500;
        }
        .action-btn.admin {
             background-color: #ffc107; color: #000;
        }
        .profile-info {
            margin-top: 80px; /* Space for the profile picture */
        }
        .profile-info h1 { font-size: 2rem; margin-bottom: 0.25rem; }
        .profile-info p { color: #6c757d; font-size: 1.1rem; }
        .profile-info .job-title {
            color: var(--text-color);
            font-size: 1.25rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .profile-content {
            background-color: var(--card-bg-color);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 2rem;
        }
        .profile-section { margin-bottom: 2rem; }
        .profile-section:last-child { margin-bottom: 0; }
        .profile-section h3 { margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; }
        .skills-list { display: flex; flex-wrap: wrap; gap: 0.5rem; list-style: none; padding-left: 0; }
        .skill-badge { background-color: #e9ecef; color: #495057; padding: 0.3rem 0.8rem; border-radius: 16px; font-size: 0.9rem; }
        
        .contact-links { display: flex; flex-wrap: wrap; gap: 1rem; }
        .contact-links a {
            display: inline-flex; align-items: center; gap: 0.5rem;
            text-decoration: none; color: var(--primary-color); font-weight: 500;
        }
        .contact-links a svg { width: 20px; height: 20px; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-left">
            <a href="index.html" class="navbar-logo">AlumniHub</a>
            <ul class="nav-links">
                <?php if ($logged_in_user_role === 'Admin'): ?>
                    <li><a href="admin_dashboard.php">Dashboard</a></li>
                    <li><a href="admin_users.php">User Mgmt</a></li>
                    <li><a href="admin_events.php">Event Mgmt</a></li>
                    <li><a href="admin_broadcast.php">Broadcast</a></li>
                <?php else: ?>
                    <li><a href="directory.php" class="<?php echo ($current_page == 'directory.php') ? 'active' : ''; ?>">Directory</a></li>
                    <li><a href="events.php" class="<?php echo ($current_page == 'events.php') ? 'active' : ''; ?>">Events</a></li>
                    <?php if ($logged_in_user_role === 'Alumni'): ?>
                        <li><a href="chat.php" class="<?php echo ($current_page == 'chat.php') ? 'active' : ''; ?>">Chat</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
        </div>
        <div class="profile-dropdown">
            <img src="<?php echo htmlspecialchars($avatar_image); ?>" alt="Profile">
            <span><?php echo htmlspecialchars($display_name); ?></span>
            <div class="dropdown-menu">
                <a href="profile.php">My Profile</a>
                <a href="logout.php" class="logout">Logout</a>
            </div>
        </div>
    </nav>

    <main class="container">
        <div class="profile-header">
            <div class="profile-banner" style="background-image: url('<?php echo htmlspecialchars($profile['banner_image'] ?? ''); ?>');"></div>
            <div class="profile-details">
                <img class="profile-picture" src="<?php echo htmlspecialchars($profile['profile_image'] ?? 'default-avatar.svg'); ?>" alt="Profile Picture">
                
                <div class="profile-actions">
                    <?php 
                    // If the logged-in user is an Admin, show an "Edit" button
                    if ($logged_in_user_role === 'Admin'): ?>
                        <a href="profile.php?user_id=<?php echo $profile_user_id; ?>" class="action-btn admin">Admin Edit</a>
                    
                    <?php 
                    // Else if the logged-in user is viewing THEIR OWN profile
                    elseif ($logged_in_user_id == $profile_user_id): ?>
                        <a href="profile.php" class="action-btn">Edit Your Profile</a>
                    <?php endif; ?>
                </div>

                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?></h1>
                    
                    <p class="job-title">
                        <?php echo htmlspecialchars($profile['job_title'] ?? 'Job Title Not Set'); ?> 
                        at 
                        <?php echo htmlspecialchars($profile['company'] ?? 'Company Not Set'); ?>
                    </p>
                    
                    <p>
                   Class of <?php echo htmlspecialchars($profile['graduation_year'] ?? 'N/A'); ?> 
                  (Batch: <?php echo htmlspecialchars($profile['batch_years'] ?? 'N/A'); ?>)
                   </p>
                </div>
            </div>
        </div>

        <div class="profile-content">
            <div class="profile-section">
                <h3>Professional Info</h3>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($profile['email']); ?></p>
                <p><strong>Company:</strong> <?php echo htmlspecialchars($profile['company']); ?></p>
                <p><strong>Job Title:</strong> <?php echo htmlspecialchars($profile['job_title']); ?></p>
            </div>
            
            <div class="profile-section">
                <h3>Skills</h3>
                <ul class="skills-list">
                    <?php 
                        // --- FIXED: Replaced json_decode with explode ---
                        // Get the skills string from the database
                        $skills_string = $profile['skills'] ?? ''; 
                        
                        if (!empty($skills_string)) {
                            // Turn the string into an array, splitting it at the comma
                            $skills_array = explode(',', $skills_string); 

                            // Now loop over the array
                            foreach ($skills_array as $skill): 
                    ?>
                                <li class="skill-badge"><?php echo htmlspecialchars(trim($skill)); ?></li>
                    <?php 
                            endforeach; 
                        } else {
                            echo "<li>No skills listed.</li>";
                        }
                    ?>
                </ul>
            </div>
            
            <div class="profile-section">
                <h3>Contact & Links</h3>
                <div class="contact-links">
                    <a href="mailto:<?php echo htmlspecialchars($profile['email']); ?>">
                        <svg fill="currentColor" viewBox="0 0 24 24"><path d="M22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6zm-2 0l-8 5-8-5h16zm0 12H4V8l8 5 8-5v10z"/></svg>
                        Email
                    </a>
                    
                    <?php if (!empty($profile['linkedin_url'])): ?>
                        <a href="<?php echo htmlspecialchars($profile['linkedin_url']); ?>" target="_blank">
                           <svg fill="currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                           LinkedIn
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($profile['github_url'])): ?>
                        <a href="<?php echo htmlspecialchars($profile['github_url']); ?>" target="_blank">
                           <svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.034c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.109-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.91 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                           GitHub
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($profile['website_url'])): ?>
                        <a href="<?php echo htmlspecialchars($profile['website_url']); ?>" target="_blank">
                            <svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm-3.045 18.113c-.312 0-.442-.238-.442-.489v-2.689c0-.251.13-.489.442-.489.313 0 .443.238.443.489v2.689c0 .251-.13.489-.443.489zm6.09 0c-.312 0-.442-.238-.442-.489v-2.689c0-.251.13-.489.442-.489.313 0 .443.238.443.489v2.689c0 .251-.13.489-.443.489zm.413-11.231c-.139-.234-.41-.374-.716-.374h-5.297c-.305 0-.577.14-.716.374l-2.697 4.547c-.126.212-.195.451-.195.702 0 .68.551 1.231 1.23 1.231h1.228v1.734c0 .251.13.489.442.489.313 0 .443-.238.443-.489v-1.734h2.51v1.734c0 .251.13.489.442.489.313 0 .443-.238.443-.489v-1.734h1.228c.679 0 1.23-.551 1.23-1.231 0-.251-.069-.49-.195-.702l-2.697-4.547zm-3.398 3.844c-.752 0-1.362-.61-1.362-1.362s.61-1.362 1.362-1.362 1.362.61 1.362 1.362-.61 1.362-1.362 1.362z"/></svg>
                            Website
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>