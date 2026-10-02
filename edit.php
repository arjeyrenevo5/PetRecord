<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$error = "";
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit;
}

$fetch = mysqli_query($conn, "SELECT * FROM pets WHERE id = $id");
if (mysqli_num_rows($fetch) == 0) {
    header("Location: index.php");
    exit;
}
$pet = mysqli_fetch_assoc($fetch);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pet_name = mysqli_real_escape_string($conn, trim($_POST['pet_name'] ?? ''));
    $species = mysqli_real_escape_string($conn, trim($_POST['species'] ?? ''));
    $breed = mysqli_real_escape_string($conn, trim($_POST['breed'] ?? ''));
    $age = !empty($_POST['age']) ? intval($_POST['age']) : "NULL";
    $gender = mysqli_real_escape_string($conn, trim($_POST['gender'] ?? 'Unknown'));
    $owner_name = mysqli_real_escape_string($conn, trim($_POST['owner_name'] ?? ''));
    $contact_number = mysqli_real_escape_string($conn, trim($_POST['contact_number'] ?? ''));

    if (empty($pet_name) || empty($species)) {
        $error = "Please fill in all required fields (Pet Name and Species).";
    } else {
        $sql = "UPDATE pets SET 
                pet_name = '$pet_name',
                species = '$species',
                breed = '$breed',
                age = $age,
                gender = '$gender',
                owner_name = '$owner_name',
                contact_number = '$contact_number'
                WHERE id = $id";

        if (mysqli_query($conn, $sql)) {
            header("Location: index.php?msg=updated");
            exit;
        } else {
            $error = "Error updating pet record: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pet - PetRecord</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #818cf8;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border: #e2e8f0;
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
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-light);
        }
        .btn-light {
            background: #f1f5f9;
            color: var(--text-primary);
            border: 1px solid var(--border);
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 3rem 1rem;
        }
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 2.5rem;
        }
        .header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
        }
        .icon {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary);
            padding: 1rem;
            border-radius: 50%;
            font-size: 1.5rem;
        }
        .header h1 {
            font-size: 1.75rem;
            margin-bottom: 0.25rem;
        }
        .header p {
            color: var(--text-secondary);
        }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        .row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        .section {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }
        .section-title {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
        }
        .actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 2rem;
        }
        .btn-lg {
            padding: 0.875rem 1.5rem;
            font-size: 1rem;
        }
        .flex-grow {
            flex: 1;
        }
        .text-danger {
            color: var(--danger);
        }
    </style>
</head>
<body>
    <nav>
        <div class="nav-container">
            <a href="index.php" class="brand">
                <i class="bi bi-shield-shaded"></i>
                Pet<span>Record</span>
            </a>
            <div class="nav-links">
                <span class="user-info">Welcome, <?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="index.php" class="btn btn-outline">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin.php" class="btn btn-outline">Admin</a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-outline">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    <div class="container">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <div class="card">
            <div class="header">
                <div class="icon">
                    <i class="bi bi-pencil-square"></i>
                </div>
                <div>
                    <h1>Edit Pet Record</h1>
                    <p>Update the details for <?= htmlspecialchars($pet['pet_name']) ?></p>
                </div>
            </div>
            <form method="POST" action="edit.php?id=<?= $id ?>">
                <div class="form-group">
                    <label class="form-label">Pet Name <span class="text-danger">*</span></label>
                    <input type="text" name="pet_name" class="form-control" value="<?= htmlspecialchars($pet['pet_name']) ?>" required>
                </div>
                <div class="row">
                    <div class="form-group">
                        <label class="form-label">Species <span class="text-danger">*</span></label>
                        <select name="species" class="form-control" required>
                            <option value="Dog" <?= $pet['species'] === 'Dog' ? 'selected' : '' ?>>Dog</option>
                            <option value="Cat" <?= $pet['species'] === 'Cat' ? 'selected' : '' ?>>Cat</option>
                            <option value="Bird" <?= $pet['species'] === 'Bird' ? 'selected' : '' ?>>Bird</option>
                            <option value="Fish" <?= $pet['species'] === 'Fish' ? 'selected' : '' ?>>Fish</option>
                            <option value="Other" <?= $pet['species'] === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Breed</label>
                        <input type="text" name="breed" class="form-control" value="<?= htmlspecialchars($pet['breed'] ?? '') ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="form-group">
                        <label class="form-label">Age (in years)</label>
                        <input type="number" name="age" class="form-control" min="0" max="100" value="<?= $pet['age'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-control">
                            <option value="Unknown" <?= $pet['gender'] === 'Unknown' ? 'selected' : '' ?>>Unknown</option>
                            <option value="Male" <?= $pet['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= $pet['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                </div>
                <div class="section">
                    <h6 class="section-title">Owner Information</h6>
                    <div class="form-group">
                        <label class="form-label">Owner Name</label>
                        <input type="text" name="owner_name" class="form-control" value="<?= htmlspecialchars($pet['owner_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($pet['contact_number'] ?? '') ?>">
                    </div>
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary btn-lg flex-grow">
                        <i class="bi bi-check-lg"></i> Update Pet Record
                    </button>
                    <a href="index.php" class="btn btn-light btn-lg">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
