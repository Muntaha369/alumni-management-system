<?php
session_start();

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

// 2. Session and Navbar Variables
$logged_in_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'guest';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings</title>
    <style>
        /* Reusing styles from profile.php and others */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        :root { --primary-color: #0d6efd; --background-color: #f8f9fa; --card-bg-color: #ffffff; --text-color: #212529; --nav-link-color: #495057; --border-color: #dee2e6; --success-color: #198754; --danger-color: #dc3545; --danger-bg-color: #f8d7da; }
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
        .container { max-width: 700px; margin: 2rem auto; padding: 0 2rem; }

        /* Message Styles */
        .message { padding: 1rem; margin-bottom: 2rem; border-radius: 6px; }
        .success-message { background-color: #d1e7dd; color: var(--success-color); border: 1px solid #badbcc; }
        .error-message { background-color: var(--danger-bg-color); color: var(--danger-color); border: 1px solid #f5c6cb; }

        /* Card Styles (like account-info from profile.php) */
        .setting-card {
            background-color: var(--card-bg-color);
            padding: 2rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }
        .setting-card h2 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1rem;
            color: var(--primary-color);
        }
        .info-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 1rem;
        }
        .info-row .info-label {
            font-weight: 600;
            color: #495057;
        }
        .info-row .info-value {
            font-weight: 500;
        }
        .info-row .info-value .role-badge {
            background-color: var(--primary-color);
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.9rem;
            text-transform: capitalize;
        }
        .input-group { margin-bottom: 1rem; }
        .input-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .input-group input { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 1rem; font-family: 'Inter', sans-serif; }
        .submit-btn { background-color: var(--primary-color); color: white; border: none; padding: 0.8rem 2rem; border-radius: 6px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background-color 0.2s; }
        .submit-btn:hover { background-color: #0b5ed7; }

        /* Delete Account Section Styles */
        .delete-section h2 {
            color: var(--danger-color);
        }
        .delete-section p {
            margin-bottom: 1.5rem;
            color: #444;
        }
        .delete-btn {
            background-color: var(--danger-color);
            color: white;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .delete-btn:hover {
            background-color: #bb2d3b;
        }
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
        <h1>Account Settings</h1>

        <?php if (isset($_GET['success'])): ?>
            <div class="message success-message"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="message error-message">
                <strong>Error:</strong> <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <div class="setting-card">
            <h2>Account Information</h2>
            <div class="info-row">
                <span class="info-label">Display Name</span>
                <span class="info-value"><?php echo htmlspecialchars($display_name); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value"><?php echo htmlspecialchars($_SESSION['user_email']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Role</span>
                <span class="info-value"><span class="role-badge"><?php echo htmlspecialchars($user_role); ?></span></span>
            </div>
        </div>

        <div class="setting-card">
            <h2>Change Password</h2>
            <form action="api/change_password.php" method="POST">
                <div class="input-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="input-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" minlength="6" required>
                </div>
                <div class="input-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" minlength="6" required>
                </div>
                <button type="submit" class="submit-btn">Update Password</button>
            </form>
        </div>

        <div class="setting-card delete-section">
            <h2>Delete Account</h2>
            <p>Warning: Deleting your account is permanent. All your data, including your profile information and chat messages, will be lost. This action cannot be undone.</p>
            <form action="api/delete_account.php" method="POST" onsubmit="return confirm('Are you absolutely sure you want to delete your account? This is irreversible.');">
                <div class="input-group">
                    <label for="delete_confirm_password">Enter Your Password to Confirm</label>
                    <input type="password" id="delete_confirm_password" name="password" required>
                </div>
                <button type="submit" class="delete-btn">Delete My Account Permanently</button>
            </form>
        </div>

    </main>
</body>
</html>