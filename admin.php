<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$message = '';
$messageType = '';

// Handle user deletion
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    if ($delete_id != $_SESSION['user_id']) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param('i', $delete_id);
        if ($stmt->execute()) {
            $message = 'User deleted successfully';
            $messageType = 'success';
        } else {
            $message = 'Failed to delete user';
            $messageType = 'danger';
        }
        $stmt->close();
    } else {
        $message = 'You cannot delete your own account';
        $messageType = 'danger';
    }
    header("Location: admin.php?msg=" . urlencode($message) . "&type=" . $messageType);
    exit;
}

// Handle role update
if (isset($_POST['update_role'])) {
    $user_id = intval($_POST['user_id']);
    $role = $_POST['role'];
    if ($user_id == $_SESSION['user_id'] && $role === 'user') {
        $message = 'You cannot demote yourself';
        $messageType = 'danger';
    } else {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param('si', $role, $user_id);
        if ($stmt->execute()) {
            $message = 'User role updated successfully';
            $messageType = 'success';
        } else {
            $message = 'Failed to update role';
            $messageType = 'danger';
        }
        $stmt->close();
    }
}

if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = $_GET['msg'];
    $messageType = $_GET['type'];
}

$result = $conn->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - PetRecord</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border: #e2e8f0;
            --success: #22c55e;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text-primary);
        }
        nav {
            background: #0f172a;
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .brand {
            font-weight: 700;
            font-size: 1.5rem;
            text-decoration: none;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .brand span {
            color: var(--warning);
        }
        .nav-links {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        .user-info {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.95rem;
        }
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-outline {
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
        }
        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        .header {
            margin-bottom: 2rem;
        }
        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .header p {
            color: var(--text-secondary);
        }
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            border-radius: 12px;
        }
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
            border: 1px solid rgba(34, 197, 94, 0.2);
        }
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 1rem 1.5rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        th {
            background: #f8fafc;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .badge-admin {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary);
        }
        .badge-user {
            background: rgba(100, 116, 139, 0.1);
            color: var(--text-secondary);
        }
        .actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        select {
            padding: 0.5rem;
            border-radius: 6px;
            border: 1px solid var(--border);
            font-family: inherit;
        }
    </style>
</head>
<body>
    <nav>
        <div class="nav-container">
            <a href="index.php" class="brand">
                <i class="bi bi-shield-shaded"></i>
                Pet<span>Record</span> <small style="font-size: 0.875rem; opacity: 0.8;">Admin</small>
            </a>
            <div class="nav-links">
                <span class="user-info">Welcome, <?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="index.php" class="btn btn-outline">View Pets</a>
                <a href="logout.php" class="btn btn-outline">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    <div class="container">
        <div class="header">
            <h1>Admin Dashboard</h1>
            <p>Manage user accounts and permissions</p>
        </div>
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <span class="badge badge-<?= $user['role'] ?>"><?= htmlspecialchars(ucfirst($user['role'])) ?></span>
                            </td>
                            <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                            <td>
                                <div class="actions">
                                    <form method="POST" action="admin.php" style="display: flex; gap: 0.5rem; align-items: center;">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <select name="role">
                                            <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        </select>
                                        <button type="submit" name="update_role" class="btn btn-primary">Update</button>
                                    </form>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <a href="admin.php?delete_id=<?= $user['id'] ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this user?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
