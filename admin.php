<?php
require_once 'db.php';
require_once 'assets/paw.php';
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
    $messageType = in_array($_GET['type'], ['success', 'danger', 'warning'], true) ? $_GET['type'] : 'success';
}

$result = $conn->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
$users = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$totalUsers = count($users);
$totalAdmins = 0;
foreach ($users as $u) {
    if ($u['role'] === 'admin') {
        $totalAdmins++;
    }
}
$totalMembers = $totalUsers - $totalAdmins;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - PetRecord</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="admin-page">
    <nav id="sidebar-nav">
        <button class="sidebar-toggle" id="sidebar-toggle" type="button" aria-controls="sidebar-links" aria-expanded="true" aria-label="Hide navigation">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
        <div class="nav-container">
            <a href="index.php" class="brand">
                <i class="brand-mark"><?= paw_svg() ?></i>
                Pet<span>Record</span> <small>Admin</small>
            </a>
            <div class="nav-links" id="sidebar-links">
                <a href="index.php#dashboard" class="btn btn-outline"><i class="bi bi-house-heart"></i> Dashboard</a>
                <a href="profile.php" class="nav-user-card" aria-label="User profile">
                    <span class="profile-avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($_SESSION['username'], 0, 1))) ?></span>
                    <span class="profile-details">
                        <span class="profile-name"><?= htmlspecialchars($_SESSION['username']) ?></span>
                        <span class="profile-role"><?= $_SESSION['role'] === 'admin' ? 'Administrator' : 'Pet Owner' ?></span>
                    </span>
                </a>
                <a href="logout.php" class="btn btn-outline">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> toast-notification" role="<?= $messageType === 'danger' ? 'alert' : 'status' ?>" aria-live="<?= $messageType === 'danger' ? 'assertive' : 'polite' ?>">
                <i class="bi <?= $messageType === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?>"></i>
                <span><?= htmlspecialchars($message) ?></span>
                <button class="toast-close" type="button" aria-label="Dismiss notification"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            </div>
        <?php endif; ?>

        <section class="admin-hero" id="admin-overview">
            <div class="admin-hero-copy">
                <span class="admin-kicker"><i class="bi bi-shield-lock-fill"></i> PetRecord Control Center</span>
                <h1>Administration</h1>
                <p>Manage accounts, assign access levels, and keep your community organized.</p>
            </div>
            <div class="admin-hero-mark" aria-hidden="true"><i class="bi bi-shield-check"></i></div>
            <a class="admin-hero-link" href="index.php#dashboard"><i class="bi bi-arrow-up-right"></i> Open pet dashboard</a>
        </section>

        <div class="admin-stats">
            <div class="mini-card admin-stat-card">
                <span class="mini-icon"><i class="bi bi-people-fill"></i></span>
                <div>
                    <strong><?= $totalUsers ?></strong>
                    <span>Registered accounts</span>
                </div>
            </div>
            <div class="mini-card admin-stat-card">
                <span class="mini-icon"><i class="bi bi-shield-lock-fill"></i></span>
                <div>
                    <strong><?= $totalAdmins ?></strong>
                    <span>Admin accounts</span>
                </div>
            </div>
            <div class="mini-card admin-stat-card">
                <span class="mini-icon"><i class="bi bi-person-heart"></i></span>
                <div>
                    <strong><?= $totalMembers ?></strong>
                    <span>Pet owner accounts</span>
                </div>
            </div>
        </div>

        <section class="card admin-users-card" id="user-management">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-person-gear"></i>
                    <div>
                        User Management
                        <small>Review accounts and control their access</small>
                    </div>
                </div>
                <span class="admin-user-count"><i class="bi bi-person-check-fill"></i> <?= $totalUsers ?> total</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($totalUsers > 0): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <span class="user-avatar"><?= htmlspecialchars(substr($user['username'], 0, 1)) ?></span>
                                            <div>
                                                <strong><?= htmlspecialchars($user['username']) ?></strong>
                                                <small><?= htmlspecialchars($user['email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $user['role'] === 'admin' ? 'admin' : 'user' ?>">
                                            <i class="bi <?= $user['role'] === 'admin' ? 'bi-shield-fill-check' : 'bi-person-fill' ?>"></i>
                                            <?= htmlspecialchars(ucfirst($user['role'])) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <div class="actions" style="justify-content: flex-end;">
                                            <form method="POST" action="admin.php" class="role-form">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <select name="role" aria-label="Role for <?= htmlspecialchars($user['username']) ?>">
                                                    <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                </select>
                                                <button type="submit" name="update_role" class="btn btn-primary">
                                                    <i class="bi bi-arrow-repeat"></i> Update
                                                </button>
                                            </form>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <a href="admin.php?delete_id=<?= $user['id'] ?>" class="icon-btn danger" title="Delete user" aria-label="Delete <?= htmlspecialchars($user['username']) ?>" onclick="return confirm('Are you sure you want to delete this user?')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="empty-state">
                                    <i class="bi bi-people"></i>
                                    <h3>No users yet</h3>
                                    <p>Registered accounts will appear here.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <script src="assets/js/sidebar.js" defer></script>
</body>
</html>
