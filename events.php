<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

include_once 'db_connection.php';
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$upcoming_sql = "SELECT * FROM events WHERE status = 'upcoming' ORDER BY event_date ASC";
$upcoming_result = $conn->query($upcoming_sql);

$past_sql = "SELECT * FROM events WHERE status = 'past' ORDER BY event_date DESC";
$past_result = $conn->query($past_sql);

$user_role = $_SESSION['user_role'] ?? 'guest';
$display_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if (empty($display_name)) { $display_name = $_SESSION['user_email']; }
$avatar_image = $_SESSION['profile_image_url'] ?? 'data:image/svg+xml;base64,...'; // Fallback SVG
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events</title>
    <style>
        /* ... All your existing CSS ... */
        /* --- NEW: Notification Dot --- */
        .nav-links li {
            position: relative; /* Needed for absolute positioning of the dot */
        }
        .notification-dot {
            position: absolute;
            top: 5px; /* Adjust as needed */
            right: -8px; /* Adjust as needed */
            width: 8px;
            height: 8px;
            background-color: var(--danger-color); /* Use your theme's danger color */
            border-radius: 50%;
            display: none; /* Hidden by default */
        }
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        :root { --primary-color: #0d6efd; --background-color: #f8f9fa; --card-bg-color: #ffffff; --text-color: #212529; --nav-link-color: #495057; --border-color: #dee2e6; --success-color: #198754; }
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
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .admin-btn {
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        .admin-btn:hover { background-color: #0b5ed7; }
        .event-section { margin-bottom: 3rem; }
        .event-card {
            background-color: var(--card-bg-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .event-card h3 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        .event-date {
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 1rem;
        }
        .event-card p {
            line-height: 1.7;
            color: #495057;
            margin-bottom: 1.5rem;
        }
        .past-event {
            opacity: 0.7;
        }
        .reminder-link {
            display: inline-block;
            background-color: var(--success-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: background-color 0.2s;
        }
        .reminder-link:hover {
            background-color: #157347;
        }
        .past-event .reminder-link {
            background-color: var(--nav-link-color);
            cursor: not-allowed;
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
        <div class="page-header">
            <h1>Events</h1>
            <?php if ($user_role === 'admin'): ?>
                <a href="admin/manage_events.php" class="admin-btn">Manage Events</a>
            <?php endif; ?>
        </div>
        <section class="event-section">
            <h2>Upcoming Events</h2>
            <hr style="margin: 1rem 0 1.5rem 0; border: 0; border-top: 1px solid var(--border-color);">
            <?php if ($upcoming_result && $upcoming_result->num_rows > 0): ?>
                <?php while($row = $upcoming_result->fetch_assoc()): ?>
                    <?php
                        $base_url = "https://www.google.com/calendar/render?action=TEMPLATE";
                        $title = urlencode($row['event_name']);
                        $description = urlencode($row['description']);
                        $start_date = date('Ymd', strtotime($row['event_date']));
                        $end_date = date('Ymd', strtotime($row['event_date'] . ' +1 day'));
                        $dates = $start_date . '/' . $end_date;
                        $google_calendar_url = "{$base_url}&text={$title}&dates={$dates}&details={$description}";
                    ?>
                    <div class="event-card">
                        <h3><?php echo htmlspecialchars($row['event_name']); ?></h3>
                        <div class="event-date"><?php echo date("F j, Y", strtotime($row['event_date'])); ?></div>
                        <p><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
                        <a href="<?php echo $google_calendar_url; ?>" target="_blank" class="reminder-link">
                            📅 Set Reminder
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No upcoming events scheduled at this time.</p>
            <?php endif; ?>
        </section>
        <section class="event-section">
            <h2>Past Events</h2>
            <hr style="margin: 1rem 0 1.5rem 0; border: 0; border-top: 1px solid var(--border-color);">
            <?php if ($past_result && $past_result->num_rows > 0): ?>
                <?php while($row = $past_result->fetch_assoc()): ?>
                    <div class="event-card past-event">
                        <h3><?php echo htmlspecialchars($row['event_name']); ?></h3>
                        <div class="event-date"><?php echo date("F j, Y", strtotime($row['event_date'])); ?></div>
                        <p><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
                        <a href="#" class="reminder-link" onclick="return false;">
                            📅 Event Ended
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No past event information available.</p>
            <?php endif; ?>
        </section>
    </main>

</body>
</html>
<?php
$conn->close();
?>