<?php
require_once 'db.php';

// Auto-create table if it doesn't exist yet
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

// Handle delete action
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

// Search and filter logic
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

// Statistics counts
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
    
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
            color: #334155;
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .stat-card {
            border: none;
            border-radius: 16px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
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
        .main-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }
        .table th {
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            border-top: none;
            background-color: #f8fafc;
        }
        .badge-species {
            padding: 6px 12px;
            font-weight: 500;
            border-radius: 8px;
        }
        .btn-add-pet {
            border-radius: 10px;
            font-weight: 600;
            padding: 10px 20px;
        }
    </style>
</head>
<body>

    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top py-3 shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <i class="bi bi-shield-shaded fs-3 text-warning"></i>
                <span>Pet<span class="text-warning">Record</span></span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <a href="add.php" class="btn btn-warning btn-add-pet d-flex align-items-center gap-2">
                    <i class="bi bi-plus-circle-fill"></i>
                    <span>Add Pet</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container py-4">

        <!-- Notification Alert -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi <?= $messageType === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Dashboard Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h2 class="fw-bold mb-1">🐾 Pet Management Dashboard</h2>
                <p class="text-muted mb-0">Manage, view, and track all registered pets and their owners.</p>
            </div>
            <div>
                <!-- Add Pet Button -->
                <a href="add.php" class="btn btn-primary btn-add-pet shadow-sm d-inline-flex align-items-center gap-2">
                    <i class="bi bi-plus-lg fs-6"></i>
                    <span>Add New Pet</span>
                </a>
            </div>
        </div>

        <!-- Metric / Overview Cards -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card stat-card shadow-sm p-3 bg-white">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Pets</span>
                            <h3 class="fw-bold text-dark mt-1 mb-0"><?= $totalPets ?></h3>
                        </div>
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-folder2-open"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card stat-card shadow-sm p-3 bg-white">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Dogs</span>
                            <h3 class="fw-bold text-dark mt-1 mb-0"><?= $totalDogs ?></h3>
                        </div>
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-heart-pulse-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card stat-card shadow-sm p-3 bg-white">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Cats</span>
                            <h3 class="fw-bold text-dark mt-1 mb-0"><?= $totalCats ?></h3>
                        </div>
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-stars"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card stat-card shadow-sm p-3 bg-white">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Others</span>
                            <h3 class="fw-bold text-dark mt-1 mb-0"><?= $totalOthers ?></h3>
                        </div>
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-grid-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Pet Table Card -->
        <div class="card main-card bg-white overflow-hidden shadow-sm">
            <div class="card-body p-4">
                
                <!-- Filter and Search Toolbar -->
                <form method="GET" action="index.php" class="row g-2 align-items-center mb-4">
                    <div class="col-12 col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control border-start-0" placeholder="Search by pet name, owner, breed..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <select name="species" class="form-select" onchange="this.form.submit()">
                            <option value="">All Species</option>
                            <option value="Dog" <?= $species_filter === 'Dog' ? 'selected' : '' ?>>Dog</option>
                            <option value="Cat" <?= $species_filter === 'Cat' ? 'selected' : '' ?>>Cat</option>
                            <option value="Bird" <?= $species_filter === 'Bird' ? 'selected' : '' ?>>Bird</option>
                            <option value="Fish" <?= $species_filter === 'Fish' ? 'selected' : '' ?>>Fish</option>
                            <option value="Other" <?= $species_filter === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-secondary flex-grow-1">
                            <i class="bi bi-funnel me-1"></i> Filter
                        </button>
                        <?php if (!empty($search) || !empty($species_filter)): ?>
                            <a href="index.php" class="btn btn-outline-secondary" title="Reset Filters">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>

                <!-- Pet Records Table -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Pet Name</th>
                                <th>Species</th>
                                <th>Breed</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Owner Name</th>
                                <th>Contact</th>
                                <th>Registered</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php $count = 1; while ($pet = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td class="text-muted fw-semibold"><?= $count++ ?></td>
                                        <td>
                                            <span class="fw-bold text-dark"><?= htmlspecialchars($pet['pet_name']) ?></span>
                                        </td>
                                        <td>
                                            <?php
                                                $sp = strtolower($pet['species']);
                                                $badgeClass = 'bg-secondary';
                                                if ($sp === 'dog') $badgeClass = 'bg-success bg-opacity-10 text-success border border-success border-opacity-25';
                                                elseif ($sp === 'cat') $badgeClass = 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25';
                                                elseif ($sp === 'bird') $badgeClass = 'bg-info bg-opacity-10 text-info border border-info border-opacity-25';
                                                elseif ($sp === 'fish') $badgeClass = 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25';
                                            ?>
                                            <span class="badge <?= $badgeClass ?> badge-species">
                                                <?= htmlspecialchars($pet['species']) ?>
                                            </span>
                                        </td>
                                        <td><?= !empty($pet['breed']) ? htmlspecialchars($pet['breed']) : '<span class="text-muted fst-italic">N/A</span>' ?></td>
                                        <td>
                                            <?= !empty($pet['age']) ? htmlspecialchars($pet['age']) . ' yr' . ($pet['age'] > 1 ? 's' : '') : '<span class="text-muted fst-italic">N/A</span>' ?>
                                        </td>
                                        <td>
                                            <?php if ($pet['gender'] === 'Male'): ?>
                                                <span class="text-primary"><i class="bi bi-gender-male"></i> Male</span>
                                            <?php elseif ($pet['gender'] === 'Female'): ?>
                                                <span class="text-danger"><i class="bi bi-gender-female"></i> Female</span>
                                            <?php else: ?>
                                                <span class="text-muted">Unknown</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-semibold text-secondary">
                                            <?= !empty($pet['owner_name']) ? htmlspecialchars($pet['owner_name']) : '<span class="text-muted fst-italic">N/A</span>' ?>
                                        </td>
                                        <td>
                                            <?= !empty($pet['contact_number']) ? htmlspecialchars($pet['contact_number']) : '<span class="text-muted fst-italic">N/A</span>' ?>
                                        </td>
                                        <td class="text-muted small">
                                            <?= isset($pet['created_at']) ? date('M d, Y', strtotime($pet['created_at'])) : '-' ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="index.php?delete_id=<?= $pet['id'] ?>" 
                                                   class="btn btn-outline-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this record (<?= htmlspecialchars(addslashes($pet['pet_name'])) ?>)?');"
                                                   title="Delete Pet Record">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center py-5">
                                        <div class="d-flex flex-column align-items-center">
                                            <i class="bi bi-folder-x fs-1 text-muted mb-2"></i>
                                            <h5 class="text-muted fw-semibold">No Pet Records Found</h5>
                                            <p class="text-muted small mb-3">
                                                <?= (!empty($search) || !empty($species_filter)) ? 'Try adjusting your search filters.' : 'Get started by adding your first pet record.' ?>
                                            </p>
                                            <a href="add.php" class="btn btn-primary btn-sm rounded-pill px-3">
                                                <i class="bi bi-plus-circle me-1"></i> Add Pet Now
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>