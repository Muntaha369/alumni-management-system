<?php
session_start();

// 1. SECURITY: Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.html"); // Redirect to login if not admin
    exit();
}

// --- Database Connection ---
include_once '../db_connection.php';
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// 2. BACKEND LOGIC: Handle form submissions (Create, Update, Delete)
$message = ''; // To show success/error messages

// --- Handle DELETE ---
if (isset($_GET['delete'])) {
    $id_to_delete = (int)$_GET['delete'];
    
    // Prevent admin from deleting themselves
    if ($id_to_delete === $_SESSION['user_id']) {
        $message = '<div class="message error">You cannot delete your own account.</div>';
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id_to_delete);
        if ($stmt->execute()) {
            $message = '<div class="message success">User deleted successfully.</div>';
            // Also delete their profile
            $conn->query("DELETE FROM alumni_profiles WHERE user_id = $id_to_delete");
        } else {
            $message = '<div class="message error">Error deleting user.</div>';
        }
        $stmt->close();
    }
}

// --- Handle CREATE or UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $role = $_POST['role'];
    $edit_id = $_POST['edit_id'] ?? null;

    if ($edit_id) {
        // --- UPDATE existing user ---
        $stmt = $conn->prepare("UPDATE users SET email = ?, role = ? WHERE id = ?");
        $stmt->bind_param("ssi", $email, $role, $edit_id);
        $action = 'updated';
        
        // Optionally update password if provided
        if (!empty($_POST['password'])) {
            $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt_pass = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt_pass->bind_param("si", $hashed_password, $edit_id);
            $stmt_pass->execute();
            $stmt_pass->close();
        }
        
    } else {
        // --- CREATE new user ---
        $password = $_POST['password'];
        if (empty($email) || empty($password) || empty($role)) {
            $message = '<div class="message error">All fields are required to create a user.</div>';
            $stmt = false; // Prevent execution
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $email, $hashed_password, $role);
            $action = 'created';
        }
    }

    if ($stmt && $stmt->execute()) {
        $message = "<div class='message success'>User {$action} successfully.</div>";
    } elseif ($stmt) {
        $message = "<div class='message error'>Error: That email may already be in use.</div>";
    }
    if ($stmt) $stmt->close();
}

// 3. DATA FETCHING: Get data for the page
// --- THIS LINE FIXES THE WARNING ---
$edit_user = null; 
// ---------------------------------
if (isset($_GET['edit'])) {
    $id_to_edit = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT id, email, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $id_to_edit);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_user = $result->fetch_assoc();
    $stmt->close();
}
// Fetch all users to display in the table
$all_users_result = $conn->query("SELECT id, email, role, created_at FROM users WHERE role != 'admin' ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
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
        .input-group input, .input-group select { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 1rem; }
        .submit-btn { background-color: var(--primary-color); color: white; border: none; padding: 0.8rem 2rem; border-radius: 6px; font-size: 1rem; cursor: pointer; }
        .cancel-btn { background-color: #6c757d; color: white; text-decoration: none; padding: 0.8rem 2rem; border-radius: 6px; font-size: 1rem; margin-left: 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 0.8rem; border-bottom: 1px solid var(--border-color); }
        th { font-weight: 600; }
        .actions a { text-decoration: none; margin-right: 1rem; font-weight: 500; }
        .edit-link { color: var(--primary-color); }
        .delete-link { color: var(--error-color); }
        .role-badge { text-transform: capitalize; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <a href="../admin.php">&larr; Back to Admin Dashboard</a>
        <h1>Manage Users</h1>

        <?php echo $message; ?>

        <div class="form-container">
            <?php if ($edit_user): ?>
                <h2>Edit User (<?php echo htmlspecialchars($edit_user['email']); ?>)</h2>
                <form action="manage_users.php" method="POST">
                    <input type="hidden" name="edit_id" value="<?php echo $edit_user['id']; ?>">
                    <div class="input-group">
                        <label for="email">Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($edit_user['email']); ?>" required>
                    </div>
                    <div class="input-group">
                        <label for="password">New Password (Leave blank to keep current)</label>
                        <input type="password" name="password">
                    </div>
                    <div class="input-group">
                        <label for="role">Role</label>
                        <select name="role">
                            <option value="student" <?php if ($edit_user['role'] == 'student') echo 'selected'; ?>>Student</option>
                            <option value="alumni" <?php if ($edit_user['role'] == 'alumni') echo 'selected'; ?>>Alumni</option>
                            <option value="admin" <?php if ($edit_user['role'] == 'admin') echo 'selected'; ?>>Admin</option>
                        </select>
                    </div>
                    <button type="submit" name="update_user" class="submit-btn">Update User</button>
                    <a href="manage_users.php" class="cancel-btn">Cancel</a>
                </form>
            <?php else: ?>
                <h2>Create New User</h2>
                <form action="manage_users.php" method="POST">
                    <div class="input-group">
                        <label for="email">Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="input-group">
                        <label for="password">Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="input-group">
                        <label for="role">Role</label>
                        <select name="role">
                            <option value="student">Student</option>
                            <option value="alumni">Alumni</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <button type="submit" name="create_user" class="submit-btn">Create User</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="table-container">
            <h2>All Users</h2>
            <table>
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $all_users_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><span class="role-badge"><?php echo htmlspecialchars($row['role']); ?></span></td>
                        <td><?php echo date("M j, Y", strtotime($row['created_at'])); ?></td>
                        <td class="actions">
                            <a href="manage_users.php?edit=<?php echo $row['id']; ?>" class="edit-link">Edit</a>
                            <?php if ($row['id'] !== $_SESSION['user_id']): // Don't show delete for self ?>
                                <a href="manage_users.php?delete=<?php echo $row['id']; ?>" class="delete-link" onclick="return confirm('Are you sure you want to delete this user? This will also delete their alumni profile.');">Delete</a>
                            <?php endif; ?>
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