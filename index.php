<?php
require_once 'db.php';
require_once 'assets/paw.php';
require_once 'assets/pet_schema.php';
require_once 'assets/health_schema.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

ensurePetOwnershipSchema($conn);
ensurePetProfileSchema($conn);
ensurePetHealthRecordsSchema($conn);

$message = "";
$messageType = "";
$greeting = 'Good day';

if (isset($_POST['delete_pet_id'])) {
    $deleteAllowed = $_SESSION['role'] === 'admin';
    if (!$deleteAllowed) {
        $deleteIdInputTmp = is_string($_POST['delete_pet_id']) ? $_POST['delete_pet_id'] : '';
        $deleteIdTmp = filter_var($deleteIdInputTmp, FILTER_VALIDATE_INT);
        if ($deleteIdTmp && $deleteIdTmp > 0) {
            $ownStmt = $conn->prepare('SELECT owner_user_id FROM pets WHERE id = ?');
            if ($ownStmt) {
                $ownStmt->bind_param('i', $deleteIdTmp);
                $ownStmt->execute();
                $ownRes = $ownStmt->get_result();
                $ownRow = $ownRes ? $ownRes->fetch_assoc() : null;
                $ownStmt->close();
                if ($ownRow && (int)$ownRow['owner_user_id'] === (int)$_SESSION['user_id']) {
                    $deleteAllowed = true;
                }
            }
        }
    }

    if (!$deleteAllowed) {
        $message = "You are not allowed to delete pet records.";
        $messageType = "danger";
    } elseif (!hash_equals($_SESSION['csrf_token'], is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '')) {
        $message = "Your session expired. Refresh the page and try again.";
        $messageType = "danger";
    } else {
        $deleteIdInput = is_string($_POST['delete_pet_id']) ? $_POST['delete_pet_id'] : '';
        $delete_id = filter_var($deleteIdInput, FILTER_VALIDATE_INT);
        if (!$delete_id || $delete_id <= 0) {
            $message = "Invalid pet record.";
            $messageType = "danger";
        } else {
            $healthTable = $conn->query(
                "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'pet_health_records'"
            );
            if (!$healthTable) {
                $message = "Unable to check pet health history: " . $conn->error;
                $messageType = "danger";
            } else {
                $hasHealthTable = $healthTable->num_rows > 0;
                $healthTable->free();

                if (!$conn->begin_transaction()) {
                    $message = "Unable to start pet record deletion: " . $conn->error;
                    $messageType = "danger";
                } else {
                    $deletedHistory = true;
                    if ($hasHealthTable) {
                        $historyStmt = $conn->prepare('DELETE FROM pet_health_records WHERE pet_id = ?');
                        if ($historyStmt) {
                            $historyStmt->bind_param('i', $delete_id);
                            $deletedHistory = $historyStmt->execute();
                            if (!$deletedHistory) {
                                $message = "Unable to delete pet health history: " . $historyStmt->error;
                            }
                            $historyStmt->close();
                        } else {
                            $deletedHistory = false;
                            $message = "Unable to prepare pet health history deletion: " . $conn->error;
                        }
                    }

                    $deleteStmt = $deletedHistory ? $conn->prepare('DELETE FROM pets WHERE id = ?') : false;
                    if ($deleteStmt) {
                        $deleteStmt->bind_param('i', $delete_id);
                        $deletedPet = $deleteStmt->execute() && $deleteStmt->affected_rows > 0;
                        if ($deletedPet) {
                            $deleteStmt->close();
                            if ($conn->commit()) {
                                header("Location: index.php?msg=deleted");
                                exit;
                            }
                            $message = "Unable to complete pet record deletion: " . $conn->error;
                        } else {
                            $message = $deleteStmt->error ?: "Pet record not found.";
                            $deleteStmt->close();
                        }
                    } elseif ($deletedHistory) {
                        $message = "Unable to prepare pet record deletion: " . $conn->error;
                    }

                    $conn->rollback();
                    $messageType = "danger";
                }
            }
        }
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
    } elseif ($_GET['msg'] === 'pet-not-found') {
        $message = "That pet record could not be found.";
        $messageType = "danger";
    }
}

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$species_filter = isset($_GET['species']) ? mysqli_real_escape_string($conn, trim($_GET['species'])) : '';

$where = [];
if ($_SESSION['role'] !== 'admin') {
    $where[] = "owner_user_id = " . (int)$_SESSION['user_id'];
}
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

$perPage = 12;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$countQuery = "SELECT COUNT(*) AS c FROM pets $whereClause";
$countResult = mysqli_query($conn, $countQuery);
$totalFiltered = 0;
if ($countResult && $cr = mysqli_fetch_assoc($countResult)) {
    $totalFiltered = (int)$cr['c'];
}
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));

$query = "SELECT * FROM pets $whereClause ORDER BY id DESC LIMIT $offset, $perPage";
$result = mysqli_query($conn, $query);

$totalPets = 0;
$totalDogs = 0;
$totalCats = 0;
$totalOthers = 0;

$countWhere = "";
if ($_SESSION['role'] !== 'admin') {
    $countWhere = "WHERE owner_user_id = " . (int)$_SESSION['user_id'];
}
$countRes = mysqli_query($conn, "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN LOWER(species) = 'dog' THEN 1 ELSE 0 END) as dogs,
    SUM(CASE WHEN LOWER(species) = 'cat' THEN 1 ELSE 0 END) as cats
    FROM pets $countWhere");

if ($countRes && $row = mysqli_fetch_assoc($countRes)) {
    $totalPets = (int)($row['total'] ?? 0);
    $totalDogs = (int)($row['dogs'] ?? 0);
    $totalCats = (int)($row['cats'] ?? 0);
    $totalOthers = max(0, $totalPets - ($totalDogs + $totalCats));
}

$pct = function ($count) use ($totalPets) {
    return $totalPets > 0 ? (int)round(($count / $totalPets) * 100) : 0;
};

$filterActive = !empty($search) || !empty($species_filter);
$showPets = true;

// Upcoming care reminders
$reminderWhere = [];
if ($_SESSION['role'] !== 'admin') {
    $reminderWhere[] = "p.owner_user_id = " . (int)$_SESSION['user_id'];
}
$reminderWhere[] = "h.next_due_date IS NOT NULL";
$reminderWhere[] = "h.next_due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
$reminderClause = $reminderWhere ? "WHERE " . implode(" AND ", $reminderWhere) : "";
$reminderQuery = "SELECT h.id, h.record_type, h.title, h.next_due_date, p.id AS pet_id, p.pet_name, p.species
    FROM pet_health_records h
    JOIN pets p ON p.id = h.pet_id
    $reminderClause
    ORDER BY h.next_due_date ASC
    LIMIT 10";
$reminderResult = mysqli_query($conn, $reminderQuery);
$reminders = $reminderResult ? mysqli_fetch_all($reminderResult, MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PetRecord</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="dashboard-page">
    <nav id="sidebar-nav">
        <button class="sidebar-toggle" id="sidebar-toggle" type="button" aria-controls="sidebar-links" aria-expanded="true" aria-label="Hide navigation">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
        <div class="nav-container">
            <a href="index.php" class="brand">
                <i class="brand-mark"><?= paw_svg() ?></i>
                Pet<span>Record</span>
            </a>
            <div class="nav-links" id="sidebar-links">
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin.php" class="btn btn-outline"><i class="bi bi-gear"></i> Admin Panel</a>
                <?php endif; ?>
                <a href="index.php#my-pets" class="btn btn-outline" aria-current="page"><i class="bi bi-heart-pulse"></i> My Pets</a>
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
                <i class="bi <?= $messageType === 'success' ? 'bi-check-circle-fill' : ($messageType === 'warning' ? 'bi-info-circle-fill' : 'bi-exclamation-triangle-fill') ?>"></i>
                <span><?= htmlspecialchars($message) ?></span>
                <button class="toast-close" type="button" aria-label="Dismiss notification"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            </div>
        <?php endif; ?>

        <section class="page-head" id="dashboard">
            <div>
                <span class="eyebrow"><i class="bi bi-house-heart"></i> Pet Dashboard</span>
                <h1><?= $greeting ?>, <?= htmlspecialchars($_SESSION['username']) ?></h1>
                <p class="page-sub">A happy little home for every pet profile. Keep their details organized and easy to find.</p>
            </div>
            <div class="page-actions">
                <?php if ($_SESSION['role'] === 'user'): ?>
                    <a href="add.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-plus-lg"></i> Add New Pet
                    </a>
                <?php endif; ?>
            </div>
        </section>

        <?php if (true): ?>
            <div class="stats">
                <article class="stat-card stat-total">
                    <div class="stat-top">
                        <span class="stat-icon"><i class="bi bi-heart-fill"></i></span>
                        <span class="stat-tag"><?php echo $_SESSION['role'] === 'admin' ? 'All pets' : 'My pets'; ?></span>
                    </div>
                    <h3><?= $totalPets ?></h3>
                    <p>Total registered pets</p>
                    <div class="stat-bar"><span style="width: 100%;"></span></div>
                </article>
                <article class="stat-card stat-dogs">
                    <div class="stat-top">
                        <span class="stat-icon"><?= paw_svg() ?></span>
                        <span class="stat-tag">Dogs</span>
                    </div>
                    <h3><?= $totalDogs ?></h3>
                    <p><?= $pct($totalDogs) ?>% of all pets</p>
                    <div class="stat-bar"><span style="width: <?= $pct($totalDogs) ?>%;"></span></div>
                </article>
                <article class="stat-card stat-cats">
                    <div class="stat-top">
                        <span class="stat-icon"><i class="bi bi-stars"></i></span>
                        <span class="stat-tag">Cats</span>
                    </div>
                    <h3><?= $totalCats ?></h3>
                    <p><?= $pct($totalCats) ?>% of all pets</p>
                    <div class="stat-bar"><span style="width: <?= $pct($totalCats) ?>%;"></span></div>
                </article>
                <article class="stat-card stat-others">
                    <div class="stat-top">
                        <span class="stat-icon"><i class="bi bi-collection-fill"></i></span>
                        <span class="stat-tag">Others</span>
                    </div>
                    <h3><?= $totalOthers ?></h3>
                    <p><?= $pct($totalOthers) ?>% of all pets</p>
                    <div class="stat-bar"><span style="width: <?= $pct($totalOthers) ?>%;"></span></div>
                </article>
            </div>
        <?php endif; ?>

        <?php if (!empty($reminders)): ?>
        <section class="card" id="care-reminders">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-bell"></i>
                    <div>
                        Upcoming Care
                        <small>Appointments and follow-ups in the next 30 days</small>
                    </div>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Pet</th>
                            <th>Type</th>
                            <th>Record</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reminders as $reminder): ?>
                            <?php $overdue = $reminder['next_due_date'] < date('Y-m-d'); ?>
                            <tr>
                                <td><a href="pet.php?id=<?= (int)$reminder['pet_id'] ?>"><?= htmlspecialchars($reminder['pet_name']) ?></a></td>
                                <td><?= htmlspecialchars($reminder['record_type']) ?></td>
                                <td><?= htmlspecialchars($reminder['title']) ?></td>
                                <td><?= date('M j, Y', strtotime($reminder['next_due_date'])) ?></td>
                                <td><span class="badge <?= $overdue ? 'badge-other' : 'badge-dog' ?>"><?= $overdue ? 'Overdue' : 'Upcoming' ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($showPets): ?>
        <section class="card" id="my-pets">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-journal-medical"></i>
                    <div>
                        My Pets
                        <small><?= $totalFiltered ?> pet record<?= $totalFiltered === 1 ? '' : 's' ?><?= $filterActive ? ' match your filters' : ' in your collection' ?></small>
                    </div>
                </div>
                <div class="filters">
                    <form method="GET" class="search-box">
                        <div class="input-icon">
                            <i class="bi bi-search"></i>
                            <input type="search" name="search" class="form-control" aria-label="Search pet name, owner, or breed" placeholder="Search pets..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <select name="species" class="form-control" aria-label="Filter by species">
                            <option value="">All Species</option>
                            <option value="Dog" <?= $species_filter === 'Dog' ? 'selected' : '' ?>>Dogs</option>
                            <option value="Cat" <?= $species_filter === 'Cat' ? 'selected' : '' ?>>Cats</option>
                            <option value="Bird" <?= $species_filter === 'Bird' ? 'selected' : '' ?>>Birds</option>
                            <option value="Fish" <?= $species_filter === 'Fish' ? 'selected' : '' ?>>Fish</option>
                            <option value="Other" <?= $species_filter === 'Other' ? 'selected' : '' ?>>Others</option>
                        </select>
                        <button type="submit" class="btn btn-light"><i class="bi bi-funnel"></i> Filter</button>
                        <?php if ($filterActive): ?>
                            <a href="index.php#my-pets" class="btn btn-ghost"><i class="bi bi-x-lg"></i> Clear</a>
                        <?php endif; ?>
                        <a href="export.php" class="btn btn-light"><i class="bi bi-download"></i> Export CSV</a>
                    </form>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Pet</th>
                            <th>Species</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Owner</th>
                            <th>Contact</th>
                            <?php if (true): ?>
                                <th style="text-align: right;">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <?php
                                $spec = strtolower($row['species']);
                                $badgeClass = 'badge-other';
                                $avatarClass = 'other';
                                if ($spec === 'dog') {
                                    $badgeClass = 'badge-dog';
                                    $avatarClass = 'dog';
                                } elseif ($spec === 'cat') {
                                    $badgeClass = 'badge-cat';
                                    $avatarClass = 'cat';
                                }
                                $initial = strtoupper(substr(trim($row['pet_name']), 0, 1));
                                $canManage = $_SESSION['role'] === 'admin' || (int)($row['owner_user_id'] ?? 0) === (int)$_SESSION['user_id'];
                                ?>
                                <tr>
                                    <td>
                                        <a class="pet-cell pet-profile-link" href="pet.php?id=<?= (int)$row['id'] ?>" aria-label="View <?= htmlspecialchars($row['pet_name']) ?>'s profile">
                                            <span class="pet-avatar <?= $avatarClass ?>"><?= htmlspecialchars($initial) ?></span>
                                            <div class="pet-meta">
                                                <strong><?= htmlspecialchars($row['pet_name']) ?></strong>
                                                <span>#<?= str_pad($row['id'], 3, '0', STR_PAD_LEFT) ?><?= !empty($row['breed']) ? ' &middot; ' . htmlspecialchars($row['breed']) : '' ?></span>
                                            </div>
                                        </a>
                                    </td>
                                    <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($row['species']) ?></span></td>
                                    <td><?= $row['age'] !== null && $row['age'] !== '' ? htmlspecialchars($row['age']) . ' yrs' : '&mdash;' ?></td>
                                    <td><?= htmlspecialchars($row['gender']) ?></td>
                                    <td><?= htmlspecialchars($row['owner_name'] ?: '—') ?></td>
                                    <td><?= htmlspecialchars($row['contact_number'] ?: '—') ?></td>
                                    <?php if (true): ?>
                                        <td>
                                            <div class="actions" style="justify-content: flex-end;">
                                                <?php if ($canManage): ?>
                                                <a href="edit.php?id=<?= $row['id'] ?>" class="icon-btn" title="Edit pet" aria-label="Edit <?= htmlspecialchars($row['pet_name']) ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php endif; ?>
                                                <?php if ($canManage): ?>
                                                <a href="health.php?pet_id=<?= (int)$row['id'] ?>" class="icon-btn health-action" title="Health history" aria-label="Health history for <?= htmlspecialchars($row['pet_name']) ?>">
                                                    <i class="bi bi-heart-pulse"></i>
                                                </a>
                                                <?php endif; ?>
                                                <?php if ($canManage): ?>
                                                <form method="POST" action="index.php" class="inline-action-form" onsubmit="return confirm('Are you sure you want to delete this pet record?')">
                                                    <input type="hidden" name="delete_pet_id" value="<?= (int)$row['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                    <button type="submit" class="icon-btn danger" title="Delete pet" aria-label="Delete <?= htmlspecialchars($row['pet_name']) ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <span class="empty-state-icon"><i class="bi <?= $filterActive ? 'bi-search' : 'bi-heart' ?>"></i></span>
                                        <h3><?= $filterActive ? 'No pets match those filters' : 'Your pet family starts here' ?></h3>
                                        <p><?= $filterActive ? 'Try another search or clear the filters to see all records.' : 'Your pet records will be easy to find and manage here.' ?></p>
                                        <?php if ($filterActive): ?>
                                            <a href="index.php#my-pets" class="btn btn-light"><i class="bi bi-arrow-counterclockwise"></i> Clear filters</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
            <div class="pagination" style="display:flex;gap:8px;justify-content:center;padding:16px;flex-wrap:wrap;">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="index.php?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $species_filter ? '&species=' . urlencode($species_filter) : '' ?>#my-pets" class="btn <?= $i === $page ? 'btn-primary' : 'btn-light' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>
    <script src="assets/js/sidebar.js" defer></script>
</body>
</html>
