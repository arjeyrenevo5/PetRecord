<?php
require_once 'db.php';
require_once 'assets/paw.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '')) {
        $message = 'Your session expired. Refresh the page and try again.';
        $messageType = 'danger';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($current === '' || $new === '' || $confirm === '') {
            $message = 'All fields are required.';
            $messageType = 'danger';
        } elseif (strlen($new) < 6) {
            $message = 'New password must be at least 6 characters.';
            $messageType = 'danger';
        } elseif ($new !== $confirm) {
            $message = 'Passwords do not match.';
            $messageType = 'danger';
        } else {
            $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
            $stmt->bind_param('i', $_SESSION['user_id']);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if (!$row || !password_verify($current, $row['password'])) {
                $message = 'Current password is incorrect.';
                $messageType = 'danger';
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $up = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                $up->bind_param('si', $hash, $_SESSION['user_id']);
                if ($up->execute()) {
                    $message = 'Password updated successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update password.';
                    $messageType = 'danger';
                }
                $up->close();
            }
        }
    }
}

$userStmt = $conn->prepare('SELECT username, email, role, created_at FROM users WHERE id = ?');
$userStmt->bind_param('i', $_SESSION['user_id']);
$userStmt->execute();
$userRes = $userStmt->get_result();
$user = $userRes ? $userRes->fetch_assoc() : null;
$userStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - PetRecord</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <button class="sidebar-toggle" id="sidebar-toggle" type="button" aria-controls="sidebar-nav" aria-expanded="true" aria-label="Hide navigation">
        <i class="bi bi-x-lg" aria-hidden="true"></i>
    </button>
    <nav id="sidebar-nav">
        <div class="nav-container">
            <a href="index.php" class="brand">
                <i class="brand-mark"><?= paw_svg() ?></i>
                Pet<span>Record</span>
            </a>
            <div class="nav-links">
                <a href="index.php#dashboard" class="btn btn-outline"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin.php" class="btn btn-outline"><i class="bi bi-gear"></i> Admin</a>
                <?php endif; ?>
                <a href="profile.php" class="btn btn-outline" aria-current="page"><i class="bi bi-person"></i> Profile</a>
                <a href="logout.php" class="btn btn-outline">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    <div class="profile-card" aria-label="User profile">
        <span class="profile-avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($_SESSION['username'], 0, 1))) ?></span>
        <span class="profile-details">
            <span class="profile-name"><?= htmlspecialchars($_SESSION['username']) ?></span>
            <span class="profile-role"><?= $_SESSION['role'] === 'admin' ? 'Administrator' : 'Pet Owner' ?></span>
        </span>
    </div>

    <div class="container">
        <section class="card" style="max-width:720px;margin:0 auto;">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-person-circle"></i>
                    <div>My Profile<small>Account information</small></div>
                </div>
            </div>
            <div style="padding:16px;">
                <?php if ($user): ?>
                    <p><strong>Username:</strong> <?= htmlspecialchars($user['username']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                    <p><strong>Role:</strong> <?= htmlspecialchars(ucfirst($user['role'])) ?></p>
                    <p><strong>Joined:</strong> <?= date('M d, Y', strtotime($user['created_at'])) ?></p>
                <?php endif; ?>
            </div>
        </section>

        <section class="card" style="max-width:720px;margin:24px auto 0;">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-key"></i>
                    <div>Change Password</div>
                </div>
            </div>
            <div style="padding:16px;">
                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?>">
                        <span><?= htmlspecialchars($message) ?></span>
                    </div>
                <?php endif; ?>
                <form method="POST" action="profile.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="field" style="margin-bottom:12px;">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    <div class="field" style="margin-bottom:12px;">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required>
                    </div>
                    <div class="field" style="margin-bottom:12px;">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Password</button>
                </form>
            </div>
        </section>
    </div>
    <script src="assets/js/sidebar.js" defer></script>
</body>
</html>
PHP
echo done