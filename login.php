<?php
require_once 'db.php';
require_once 'assets/paw.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $errors[] = 'Username and password are required';
    } else {
        $stmt = $conn->prepare("SELECT id, username, email, password, role FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param('ss', $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                if ($user['role'] === 'admin') {
                    header('Location: admin.php');
                } else {
                    header('Location: index.php');
                }
                exit;
            } else {
                $errors[] = 'Invalid username or password';
            }
        } else {
            $errors[] = 'Invalid username or password';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PetRecord</title>
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
            <h2>Keep every pet story in one safe place.</h2>
            <p>Track health notes, feeding schedules, visit logs, and special moments for every furry friend you love.</p>
            <ul class="feature-list">
                <li><span class="feature-badge"><i class="bi bi-heart-pulse"></i></span> Health and care tracking</li>
                <li><span class="feature-badge"><i class="bi bi-journal-medical"></i></span> Vaccines and appointments</li>
                <li><span class="feature-badge"><i class="bi bi-stars"></i></span> Happy memories saved</li>
            </ul>
            <div class="mini-stats">
                <div class="mini-stat">
                    <strong>2.4k+</strong>
                    <span>happy pets</span>
                </div>
                <div class="mini-stat">
                    <strong>96%</strong>
                    <span>care retention</span>
                </div>
                <div class="mini-stat">
                    <strong>24/7</strong>
                    <span>pet support</span>
                </div>
            </div>
        </aside>

        <main class="auth-card">
            <div class="auth-icon">
                <i class="bi bi-person-lock"></i>
            </div>
            <div class="auth-header">
                <h1>Welcome, Pet Parent!</h1>
                <p>Login to your PetRecord account</p>
            </div>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <div><?= htmlspecialchars($error) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="login.php">
                <div class="form-group">
                    <label class="form-label" for="username">Username or Email</label>
                    <div class="input-wrap">
                        <i class="bi bi-person field-icon"></i>
                        <input type="text" id="username" name="username" class="form-control" placeholder="Enter username or email" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="username" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrap">
                        <i class="bi bi-lock field-icon"></i>
                        <input type="password" id="password" name="password" class="form-control has-toggle" placeholder="Enter password" autocomplete="current-password" required>
                        <button type="button" class="password-toggle" data-toggle-password="#password" aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </button>
            </form>
            <div class="auth-footer">
                Don't have an account? <a href="register.php">Register here</a>
            </div>
            <p class="auth-note"><i class="bi bi-shield-lock-fill"></i> Your pet records stay private and secure.</p>
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
