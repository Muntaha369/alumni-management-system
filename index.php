<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

$first_name = $_SESSION['first_name'] ?? '';
$user_role = $_SESSION['user_role'] ?? 'guest';
$display_name = trim(($first_name ?: '') . ' ' . ($_SESSION['last_name'] ?? ''));
if (empty($display_name)) {
    $display_name = $_SESSION['user_email'];
}
$avatar_image = $_SESSION['profile_image_url'] ?? 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24" 24" fill="#ccc"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>');
$current_page = basename($_SERVER['PHP_SELF']);

$welcome_name = $first_name;
if (empty($welcome_name)) {
    if ($user_role === 'admin') {
        $welcome_name = 'Admin';
    } elseif ($user_role === 'student') {
        $welcome_name = 'Student';
    } else {
        $welcome_name = 'Alumnus';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Platform Home</title>
    <style>
        /* ... All your existing CSS ... */
        
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        :root {
            --primary-color: #0d6efd;
            --background-color: #f8f9fa;
            --card-bg-color: #ffffff;
            --text-color: #212529;
            --nav-link-color: #495057;
            --border-color: #dee2e6;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
        }
        .navbar {
            background-color: var(--card-bg-color);
            border-bottom: 1px solid var(--border-color);
            padding: 0.8rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar-left { display: flex; align-items: center; gap: 2rem; }
        .navbar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
        }
        .nav-links { list-style: none; display: flex; gap: 1.5rem; }
        .nav-links a {
            text-decoration: none;
            color: var(--nav-link-color);
            font-weight: 500;
            padding: 0.5rem 0;
            border-bottom: 2px solid transparent;
            transition: color 0.2s ease, border-color 0.2s ease;
        }
        .nav-links a:hover { color: var(--primary-color); }
        .nav-links a.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        .profile-dropdown { position: relative; display: flex; align-items: center; cursor: pointer; }
        .profile-dropdown img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .profile-dropdown span { margin-left: 0.75rem; font-weight: 600; font-size: 0.9rem; }
        .dropdown-menu {
            position: absolute; top: 140%; right: 0;
            background-color: var(--card-bg-color);
            border: 1px solid var(--border-color);
            border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            width: 180px; z-index: 100;
            opacity: 0; visibility: hidden;
            transform: translateY(10px);
            transition: all 0.2s ease;
        }
        .profile-dropdown:hover .dropdown-menu { opacity: 1; visibility: visible; transform: translateY(0); }
        .dropdown-menu a { display: block; padding: 0.75rem 1rem; text-decoration: none; color: var(--text-color); }
        .dropdown-menu a:hover { background-color: #f8f9fa; }
        .dropdown-menu a.logout { color: #dc3545; }
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        .welcome-hero {
            background: linear-gradient(135deg, #0d6efd, #0d64d5);
            color: white;
            padding: 3rem 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        .action-card {
            background-color: var(--card-bg-color);
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            color: var(--text-color);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.08);
        }
        .action-card h3 { color: var(--primary-color); }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="navbar-left">
            <a href="index.php" class="navbar-logo">AlumniHub</a>
            <ul class="nav-links">
                <li><a href="index.php" class="<?php if($current_page == 'index.php') echo 'active'; ?>">Home</a></li>
                <li><a href="directory.php" class="<?php if($current_page == 'directory.php') echo 'active'; ?>">Directory</a></li>
                <li><a href="events.php" class="<?php if($current_page == 'events.php') echo 'active'; ?>">Events</a></li>
                <li><a href="donate.php" class="<?php if($current_page == 'donate.php') echo 'active'; ?>">Donate</a></li>
                <?php if ($user_role === 'alumni'): ?>
                    <li><a href="chat.php" class="<?php if($current_page == 'chat.php') echo 'active'; ?>">Chat Room</a></li>
                <?php endif; ?>
                <?php if ($user_role === 'admin'): ?>
                    <li><a href="admin.php" class="<?php if($current_page == 'admin.php') echo 'active'; ?>">Admin</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="profile-dropdown">
            <img src="<?php echo htmlspecialchars($avatar_image); ?>" alt="Profile Picture">
            <span><?php echo htmlspecialchars($display_name); ?></span>
            <div class="dropdown-menu">
                <a href="profile.php">My Profile</a>
                <a href="settings.php">Settings</a>
                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 0.2rem 0;">
                <a href="api/logout.php" class="logout">Logout</a>
            </div>
        </div>
    </nav>

    <main class="container">
        <section class="welcome-hero">
          <h1>Welcome back, <?php echo htmlspecialchars($welcome_name); ?>!</h1>
        </section>

        <section class="actions-grid">
            <a href="directory.php" class="action-card">
                <h3>Browse Directory</h3>
                <p>Find and connect with fellow alumni and students.</p>
            </a>
            <a href="events.php" class="action-card">
                <h3>View Events</h3>
                <p>Stay updated on upcoming reunions and gatherings.</p>
            </a>
            <?php if ($user_role === 'alumni'): ?>
                <a href="profile.php" class="action-card">
                    <h3>Edit Your Profile</h3>
                    <p>Keep your professional information up-to-date.</p>
                </a>
            <?php endif; ?>
        </section>
    </main>

</body>
</html>