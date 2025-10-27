<?php
session_start();

// Security check: If user is not logged in, redirect
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

// --- Database Connection ---
include_once 'db_connection.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Search Logic --- (Unchanged)
$search_term = $_GET['search'] ?? '';
$sql = "SELECT u.email, p.user_id, p.first_name, p.last_name, p.profile_image, p.company
        FROM users u
        INNER JOIN alumni_profiles p ON u.id = p.user_id
        WHERE u.role = 'alumni'";
if (!empty($search_term)) {
    $sql .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.company LIKE ? OR p.batch_years LIKE ? OR p.skills LIKE ?)";
    $stmt = $conn->prepare($sql);
    $like_term = "%{$search_term}%";
    $stmt->bind_param("sssss", $like_term, $like_term, $like_term, $like_term, $like_term);
} else {
    $stmt = $conn->prepare($sql);
}
$stmt->execute();
$result = $stmt->get_result();

// --- Session and Navbar variables --- (Unchanged)
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
    <title>Alumni Directory</title>
    <style>
        /* --- All existing CSS is unchanged --- */
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
        body { font-family: 'Inter', sans-serif; background-color: var(--background-color); color: var(--text-color); line-height: 1.6; }
        .navbar { background-color: var(--card-bg-color); border-bottom: 1px solid var(--border-color); padding: 0.8rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .navbar-left { display: flex; align-items: center; gap: 2rem; }
        .navbar-logo { font-size: 1.5rem; font-weight: 700; color: var(--primary-color); text-decoration: none; }
        .nav-links { list-style: none; display: flex; gap: 1.5rem; }
        .nav-links li { position: relative; } /* Needed for dot */
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
        .search-bar { margin-bottom: 2rem; display: flex; gap: 1rem; }
        .search-bar input { flex-grow: 1; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 1rem; font-family: 'Inter', sans-serif; }
        .search-bar button { background-color: var(--primary-color); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-size: 1rem; cursor: pointer; font-weight: 500; transition: background-color 0.2s; }
        .search-bar button:hover { background-color: #0b5ed7; }
        .directory-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; }
        .profile-card-link { text-decoration: none; color: inherit; display: block; } /* Make the link block level */
        .profile-card { background-color: var(--card-bg-color); border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem; display: flex; align-items: center; gap: 1rem; transition: transform 0.2s ease, box-shadow 0.2s ease; position: relative; /* Needed for edit button */ }
        .profile-card:hover { transform: translateY(-5px); box-shadow: 0 8px 16px rgba(0,0,0,0.08); }
        .profile-card img { width: 70px; height: 70px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
        .profile-info { text-align: left; flex-grow: 1; }
        .profile-info h3 { margin-bottom: 0.25rem; }
        .profile-info p { color: #6c757d; font-size: 0.9rem; margin: 0; }
        .profile-info .company { font-weight: 500; color: var(--text-color); }

        /* --- NEW: Edit Button Style --- */
        .edit-profile-btn {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            background-color: var(--primary-color);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            opacity: 0.8;
            transition: opacity 0.2s ease;
        }
        .profile-card:hover .edit-profile-btn {
            opacity: 1;
        }
        .edit-profile-btn:hover {
            background-color: #0b5ed7;
        }
        /* --- Notification Dot --- */
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
                <?php if ($user_role === 'alumni'): ?>
                    <li id="nav-chat-li"><a href="chat.php" class="<?php if($current_page == 'chat.php') echo 'active'; ?>">Chat Room</a><span class="notification-dot" id="chat-dot"></span></li>
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
        <h1>Alumni Directory</h1>
        <p style="margin-bottom: 2rem;">Connect with fellow graduates from our community.</p>

        <form action="directory.php" method="GET" class="search-bar">
            <input type="text" name="search" placeholder="Search by name, company, batch, or skill..." value="<?php echo htmlspecialchars($search_term); ?>">
            <button type="submit">Search</button>
        </form>

        <div class="directory-grid">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <?php
                        // --- NEW: Determine the correct link based on role ---
                        $profile_link = '';
                        if ($user_role === 'admin') {
                            // Admins link to the editable profile page
                            $profile_link = "profile.php?user_id=" . $row['user_id'];
                        } else {
                            // Non-admins link to a view-only page (adjust if needed)
                            $profile_link = "view_profile.php?id=" . $row['user_id'];
                        }
                    ?>
                    <div class="profile-card">
                        <img src="<?php echo htmlspecialchars($row['profile_image'] ?? 'data:image/svg+xml;base64,...'); ?>" alt="Profile Picture">
                        <div class="profile-info">
                             <a href="<?php echo $profile_link; ?>" style="text-decoration: none; color: inherit;">
                                <h3><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></h3>
                             </a>
                            <?php if (!empty($row['company'])): ?>
                                <p class="company"><?php echo htmlspecialchars($row['company']); ?></p>
                            <?php else: ?>
                                <p><?php echo htmlspecialchars($row['email']); ?></p>
                            <?php endif; ?>
                        </div>
                         <?php if ($user_role === 'admin'): ?>
                            <a href="profile.php?user_id=<?php echo $row['user_id']; ?>" class="edit-profile-btn">Edit</a>
                         <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <?php if (!empty($search_term)): ?>
                    <p>No alumni profiles found matching "<?php echo htmlspecialchars($search_term); ?>".</p>
                <?php else: ?>
                    <p>No alumni profiles found.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // --- Notification Polling code --- (Unchanged)
        const chatDot = document.getElementById('chat-dot');
        const eventsDot = document.getElementById('events-dot');
        const hasChatLink = <?php echo ($user_role === 'alumni') ? 'true' : 'false'; ?>;
        async function checkNotifications() {
            try {
                const response = await fetch('api/check_notifications.php');
                const data = await response.json();
                if (eventsDot) { eventsDot.style.display = data.new_events ? 'block' : 'none'; }
                if (hasChatLink && chatDot) { chatDot.style.display = data.new_messages ? 'block' : 'none'; }
            } catch (error) { console.error("Error checking notifications:", error); }
        }
        checkNotifications();
        setInterval(checkNotifications, 15000);
    </script>

</body>
</html>
<?php
$stmt->close();
$conn->close();
?>