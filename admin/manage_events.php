<?php
session_start();

// 1. SECURITY: Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.html"); // Redirect to login if not admin
    exit();
}

// --- Database Connection ---
$conn = new mysqli("localhost", "root", "", "alumni_db");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// 2. BACKEND LOGIC: Handle form submissions (Create, Update, Delete)
$message = ''; // To show success/error messages

// --- Handle DELETE ---
if (isset($_GET['delete'])) {
    $id_to_delete = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
    $stmt->bind_param("i", $id_to_delete);
    if ($stmt->execute()) {
        $message = '<div class="message success">Event deleted successfully.</div>';
    } else {
        $message = '<div class="message error">Error deleting event.</div>';
    }
    $stmt->close();
}

// --- Handle CREATE or UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_name = $_POST['event_name'];
    $event_date = $_POST['event_date'];
    $description = $_POST['description'];
    $status = $_POST['status'];
    $edit_id = $_POST['edit_id'] ?? null;

    if ($edit_id) {
        // UPDATE existing event
        $stmt = $conn->prepare("UPDATE events SET event_name=?, event_date=?, description=?, status=? WHERE id=?");
        $stmt->bind_param("ssssi", $event_name, $event_date, $description, $status, $edit_id);
        $action = 'updated';
    } else {
        // CREATE new event
        $stmt = $conn->prepare("INSERT INTO events (event_name, event_date, description, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $event_name, $event_date, $description, $status);
        $action = 'created';
    }

    if ($stmt->execute()) {
        $message = "<div class='message success'>Event {$action} successfully.</div>";
    } else {
        $message = "<div class='message error'>Error: Could not save the event.</div>";
    }
    $stmt->close();
}

// 3. DATA FETCHING: Get data for the page
$edit_event = null;
if (isset($_GET['edit'])) {
    $id_to_edit = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->bind_param("i", $id_to_edit);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_event = $result->fetch_assoc();
    $stmt->close();
}

// Fetch all events to display in the table
$all_events_result = $conn->query("SELECT * FROM events ORDER BY event_date DESC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Events</title>
    <style>
        /* Reusing the same theme and basic styles */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        :root { --primary-color: #0d6efd; --background-color: #f8f9fa; --card-bg-color: #ffffff; --text-color: #212529; --border-color: #dee2e6; --error-color: #dc3545; --success-color: #198754; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: var(--background-color); color: var(--text-color); }
        .container { max-width: 1000px; margin: 2rem auto; padding: 0 2rem; }
        .message { padding: 1rem; margin-bottom: 1rem; border-radius: 6px; }
        .success { background-color: #d1e7dd; color: var(--success-color); border: 1px solid #badbcc; }
        .error { background-color: #f8d7da; color: var(--error-color); border: 1px solid #f5c2c7; }
        .form-container, .table-container { background-color: var(--card-bg-color); border: 1px solid var(--border-color); border-radius: 8px; padding: 2rem; margin-bottom: 2rem; }
        h1, h2 { margin-bottom: 1.5rem; }
        .input-group { margin-bottom: 1rem; }
        .input-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .input-group input, .input-group select, .input-group textarea { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 1rem; }
        .submit-btn { background-color: var(--primary-color); color: white; border: none; padding: 0.8rem 2rem; border-radius: 6px; font-size: 1rem; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 0.8rem; border-bottom: 1px solid var(--border-color); }
        th { font-weight: 600; }
        .actions a { text-decoration: none; margin-right: 1rem; font-weight: 500; }
        .edit-link { color: var(--primary-color); }
        .delete-link { color: var(--error-color); }
    </style>
</head>
<body>
    <div class="container">
        <a href="../events.php">&larr; Back to Events Page</a>
        <h1>Manage Events</h1>

        <?php echo $message; ?>

        <div class="form-container">
            <h2><?php echo $edit_event ? 'Edit Event' : 'Create New Event'; ?></h2>
            <form action="manage_events.php" method="POST">
                <?php if ($edit_event): ?>
                    <input type="hidden" name="edit_id" value="<?php echo $edit_event['id']; ?>">
                <?php endif; ?>

                <div class="input-group">
                    <label for="event_name">Event Name</label>
                    <input type="text" name="event_name" value="<?php echo htmlspecialchars($edit_event['event_name'] ?? ''); ?>" required>
                </div>
                <div class="input-group">
                    <label for="event_date">Event Date</label>
                    <input type="date" name="event_date" value="<?php echo htmlspecialchars($edit_event['event_date'] ?? ''); ?>" required>
                </div>
                <div class="input-group">
                    <label for="status">Status</label>
                    <select name="status">
                        <option value="upcoming" <?php if (isset($edit_event['status']) && $edit_event['status'] == 'upcoming') echo 'selected'; ?>>Upcoming</option>
                        <option value="past" <?php if (isset($edit_event['status']) && $edit_event['status'] == 'past') echo 'selected'; ?>>Past</option>
                    </select>
                </div>
                <div class="input-group">
                    <label for="description">Description</label>
                    <textarea name="description" rows="5" required><?php echo htmlspecialchars($edit_event['description'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="submit-btn"><?php echo $edit_event ? 'Update Event' : 'Create Event'; ?></button>
            </form>
        </div>

        <div class="table-container">
            <h2>All Events</h2>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $all_events_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['event_name']); ?></td>
                        <td><?php echo date("M j, Y", strtotime($row['event_date'])); ?></td>
                        <td><?php echo htmlspecialchars($row['status']); ?></td>
                        <td class="actions">
                            <a href="manage_events.php?edit=<?php echo $row['id']; ?>" class="edit-link">Edit</a>
                            <a href="manage_events.php?delete=<?php echo $row['id']; ?>" class="delete-link" onclick="return confirm('Are you sure you want to delete this event?');">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>