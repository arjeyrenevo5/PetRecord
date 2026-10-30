<?php
require_once 'db.php';
require_once 'assets/paw.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username)) {
        $errors[] = 'Username is required';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Username can only contain letters, numbers and underscores';
    }

    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }

    if (empty($errors)) {
        $check_user = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check_user->bind_param('ss', $username, $email);
        $check_user->execute();
        $check_user->store_result();
        
        if ($check_user->num_rows > 0) {
            $errors[] = 'Username or email already exists';
            $check_user->close();
        } else {
            $check_user->close();
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')");
            $stmt->bind_param('sss', $username, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $success = 'Registration successful! You can now login.';
            } else {
                $errors[] = 'Registration failed. Please try again.';
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - PetRecord</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/auth.css" rel="stylesheet">
</head>
<body>
    <div class="auth-page">
        <aside class="brand-panel">
            <div class="animal-art" aria-hidden="true">
                <span class="animal-face"><?= paw_svg() ?></span>
                <span class="animal-face"><i class="bi bi-heart-fill"></i></span>
                <i class="animal-paw animal-paw-one"><?= paw_svg() ?></i>
                <i class="animal-paw animal-paw-two"><?= paw_svg() ?></i>
            </div>
            <div class="brand-badge"><?= paw_svg() ?> PetRecord</div>
            <h2>Start your pet care journey today.</h2>
            <p>Create your account to organize vaccinations, feeding routines, weight tracking, and every happy memory in one place.</p>
            <ul class="feature-list">
                <li><span class="feature-badge"><i class="bi bi-shield-check"></i></span> Secure personal profile</li>
                <li><span class="feature-badge"><i class="bi bi-clipboard2-pulse"></i></span> Complete pet health records</li>
                <li><span class="feature-badge"><i class="bi bi-bell"></i></span> Care reminders and notes</li>
            </ul>
            <div class="mini-stats">
                <div class="mini-stat">
                    <strong>4.8/5</strong>
                    <span>owner rating</span>
                </div>
                <div class="mini-stat">
                    <strong>120+</strong>
                    <span>pet records</span>
                </div>
                <div class="mini-stat">
                    <strong>Secure</strong>
                    <span>private data</span>
                </div>
            </div>
        </aside>

        <main class="auth-card">
            <div class="auth-icon">
                <i class="bi bi-person-plus"></i>
            </div>
            <div class="auth-header">
                <h1>Create an Account</h1>
                <p>Register to start managing pet records</p>
            </div>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <div><?= htmlspecialchars($error) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <form method="POST" action="register.php">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <div class="input-wrap">
                            <i class="bi bi-person field-icon"></i>
                            <input type="text" id="username" name="username" class="form-control" placeholder="Enter username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="username" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <div class="input-wrap">
                            <i class="bi bi-envelope field-icon"></i>
                            <input type="email" id="email" name="email" class="form-control" placeholder="Enter email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email" required>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-wrap">
                            <i class="bi bi-lock field-icon"></i>
                            <input type="password" id="password" name="password" class="form-control has-toggle" placeholder="Enter password" autocomplete="new-password" required>
                            <button type="button" class="password-toggle" data-toggle-password="#password" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Password</label>
                        <div class="input-wrap">
                            <i class="bi bi-shield-lock field-icon"></i>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control has-toggle" placeholder="Confirm" autocomplete="new-password" required>
                            <button type="button" class="password-toggle" data-toggle-password="#confirm_password" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-person-plus-fill"></i> Create Account
                </button>
            </form>
            <div class="auth-footer">
                Already have an account? <a href="login.php">Login here</a>
            </div>
            <p class="auth-note"><i class="bi bi-shield-lock-fill"></i> We only use your details to keep your pet records safe.</p>
        </main>
    </div>

    <script>
        document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.querySelector(button.getAttribute('data-toggle-password'));
                if (!input) return;
                var icon = button.querySelector('i');
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
                button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });
        });
    </script>
</body>
</html>
