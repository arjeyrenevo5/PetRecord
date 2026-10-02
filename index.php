<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$createTableQuery = "CREATE TABLE IF NOT EXISTS `pets` (
    `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
    `pet_name` VARCHAR(100) NOT NULL,
    `species` VARCHAR(50) NOT NULL,
    `breed` VARCHAR(100) DEFAULT NULL,
    `age` INT(11) DEFAULT NULL,
    `gender` ENUM('Male', 'Female', 'Unknown') DEFAULT 'Unknown',
    `owner_name` VARCHAR(100) DEFAULT NULL,
    `contact_number` VARCHAR(50) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($conn, $createTableQuery);

$message = "";
$messageType = "";

if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delSql = "DELETE FROM pets WHERE id = $delete_id";
    if (mysqli_query($conn, $delSql)) {
        header("Location: index.php?msg=deleted");
        exit;
    } else {
        $message = "Error deleting pet: " . mysqli_error($conn);
        $messageType = "danger";
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') {
        $message = "Pet record added successfully!";
        $messageType = "success";
    } elseif ($_GET['msg'] === 'updated') {
        $message = "Pet record updated successfully!";
        $messageType = "success";
    } elseif ($_GET['msg'] === 'deleted') {
        $message = "Pet record deleted successfully!";
        $messageType = "warning";
    }
}

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$species_filter = isset($_GET['species']) ? mysqli_real_escape_string($conn, trim($_GET['species'])) : '';

$where = [];
if (!empty($search)) {
    $where[] = "(pet_name LIKE '%$search%' OR owner_name LIKE '%$search%' OR breed LIKE '%$search%')";
}
if (!empty($species_filter)) {
    $where[] = "species = '$species_filter'";
}

$whereClause = "";
if (count($where) > 0) {
    $whereClause = "WHERE " . implode(" AND ", $where);
}

$query = "SELECT * FROM pets $whereClause ORDER BY id DESC";
$result = mysqli_query($conn, $query);

$totalPets = 0;
$totalDogs = 0;
$totalCats = 0;
$totalOthers = 0;

$countRes = mysqli_query($conn, "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN LOWER(species) = 'dog' THEN 1 ELSE 0 END) as dogs,
    SUM(CASE WHEN LOWER(species) = 'cat' THEN 1 ELSE 0 END) as cats
    FROM pets");

if ($countRes && $row = mysqli_fetch_assoc($countRes)) {
    $totalPets = (int)($row['total'] ?? 0);
    $totalDogs = (int)($row['dogs'] ?? 0);
    $totalCats = (int)($row['cats'] ?? 0);
    $totalOthers = max(0, $totalPets - ($totalDogs + $totalCats));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Record Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
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
            flex-wrap: wrap;
            gap: 1rem;
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
            flex-wrap: wrap;
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
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }
        .stat-info h3 {
            font-size: 2.5rem;
            margin-bottom: 0.25rem;
        }
        .stat-info p {
            color: var(--text-secondary);
            font-weight: 500;
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .icon-primary {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary);
        }
        .icon-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        .icon-success {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
        }
        .icon-secondary {
            background: rgba(100, 116, 139, 0.1);
            color: var(--text-secondary);
        }
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
        }
        .filters {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .search-box {
            display: flex;
            gap: 0.5rem;
        }
        .form-control {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-family: inherit;
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
        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.2);
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
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary);
        }
        .badge-dog { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .badge-cat { background: rgba(34, 197, 94, 0.1); color: var(--success); }
        .badge-other { background: rgba(100, 116, 139, 0.1); color: var(--text-secondary); }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        @media (max-width: 768px) {
            table {
                display: block;
                overflow-x: auto;
            }
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
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin.php" class="btn btn-outline">Admin Panel</a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-outline">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <div class="stats">
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= $totalPets ?></h3>
                    <p>Total Pets</p>
                </div>
                <div class="stat-icon icon-primary">
                    <i class="bi bi-heart-fill"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= $totalDogs ?></h3>
                    <p>Dogs</p>
                </div>
                <div class="stat-icon icon-warning">
                    <i class="bi bi-bounding-box"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= $totalCats ?></h3>
                    <p>Cats</p>
                </div>
                <div class="stat-icon icon-success">
                    <i class="bi bi-github"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= $totalOthers ?></h3>
                    <p>Others</p>
                </div>
                <div class="stat-icon icon-secondary">
                    <i class="bi bi-diagram-3"></i>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Pet Records</h2>
                <div class="filters">
                    <form method="GET" class="search-box">
                        <input type="text" name="search" class="form-control" placeholder="Search by name, owner, breed..." value="<?= htmlspecialchars($search) ?>">
                        <select name="species" class="form-control" onchange="this.form.submit()">
                            <option value="">All Species</option>
                            <option value="Dog" <?= $species_filter === 'Dog' ? 'selected' : '' ?>>Dog</option>
                            <option value="Cat" <?= $species_filter === 'Cat' ? 'selected' : '' ?>>Cat</option>
                            <option value="Bird" <?= $species_filter === 'Bird' ? 'selected' : '' ?>>Bird</option>
                            <option value="Fish" <?= $species_filter === 'Fish' ? 'selected' : '' ?>>Fish</option>
                            <option value="Other" <?= $species_filter === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                        <button type="submit" class="btn btn-outline" style="color: var(--text-primary); border-color: var(--border);">Search</button>
                        <?php if (!empty($search) || !empty($species_filter)): ?>
                            <a href="index.php" class="btn btn-outline" style="color: var(--text-primary); border-color: var(--border);">Clear</a>
                        <?php endif; ?>
                    </form>
                    <a href="add.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Add New Pet
                    </a>
                </div>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Pet Name</th>
                            <th>Species</th>
                            <th>Breed</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Owner</th>
                            <th>Contact</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <?php
                                $spec = strtolower($row['species']);
                                $badgeClass = 'badge-other';
                                if ($spec === 'dog') $badgeClass = 'badge-dog';
                                if ($spec === 'cat') $badgeClass = 'badge-cat';
                                ?>
                                <tr>
                                    <td>#<?= $row['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($row['pet_name']) ?></strong></td>
                                    <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($row['species']) ?></span></td>
                                    <td><?= htmlspecialchars($row['breed'] ?? '-') ?></td>
                                    <td><?= $row['age'] !== null ? $row['age'] . ' yrs' : '-' ?></td>
                                    <td><?= htmlspecialchars($row['gender']) ?></td>
                                    <td><?= htmlspecialchars($row['owner_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['contact_number'] ?? '-') ?></td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-outline btn-sm" style="color: var(--text-primary); border-color: var(--border);">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="index.php?delete_id=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this record?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="empty-state">
                                    <i class="bi bi-inbox"></i>
                                    <h3>No pet records found</h3>
                                    <p>Start by adding your first pet record</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
