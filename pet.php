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

ensurePetOwnershipSchema($conn);
ensurePetProfileSchema($conn);

$petId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$petId || $petId < 1) {
    http_response_code(404);
    exit('Pet not found.');
}

if ($_SESSION['role'] === 'admin') {
    $petStmt = $conn->prepare('SELECT * FROM pets WHERE id = ?');
    if (!$petStmt) {
        http_response_code(500);
        exit('Unable to prepare pet profile: ' . htmlspecialchars($conn->error));
    }
    $petStmt->bind_param('i', $petId);
} else {
    $userId = (int)$_SESSION['user_id'];
    $petStmt = $conn->prepare('SELECT * FROM pets WHERE id = ? AND owner_user_id = ?');
    if (!$petStmt) {
        http_response_code(500);
        exit('Unable to prepare pet profile: ' . htmlspecialchars($conn->error));
    }
    $petStmt->bind_param('ii', $petId, $userId);
}

if (!$petStmt->execute()) {
    http_response_code(500);
    exit('Unable to load pet profile: ' . htmlspecialchars($petStmt->error));
}
$petResult = $petStmt->get_result();
$pet = $petResult->fetch_assoc();
$petStmt->close();

if (!$pet) {
    http_response_code(404);
    exit('Pet not found.');
}

$canManage = $_SESSION['role'] === 'admin' || (int)($pet['owner_user_id'] ?? 0) === (int)$_SESSION['user_id'];

ensurePetHealthRecordsSchema($conn);
$historyStmt = $conn->prepare(
    'SELECT id, record_type, title, details, record_date, next_due_date FROM pet_health_records WHERE pet_id = ? ORDER BY record_date DESC, id DESC'
);
if (!$historyStmt) {
    http_response_code(500);
    exit('Unable to prepare pet health history: ' . htmlspecialchars($conn->error));
}
$historyStmt->bind_param('i', $petId);
if (!$historyStmt->execute()) {
    http_response_code(500);
    exit('Unable to load pet health history: ' . htmlspecialchars($historyStmt->error));
}
$historyResult = $historyStmt->get_result();
$healthSections = [
    'appointments' => [
        'title' => 'Appointments',
        'description' => 'Scheduled visits and follow-up appointments',
        'icon' => 'bi-calendar2-week',
        'records' => [],
    ],
    'medical_history' => [
        'title' => 'Medical History',
        'description' => 'Checkups, vaccinations, treatments, and care notes',
        'icon' => 'bi-journal-medical',
        'records' => [],
    ],
    'medications' => [
        'title' => 'Medication',
        'description' => 'Prescriptions and medication records',
        'icon' => 'bi-capsule',
        'records' => [],
    ],
];

while ($record = $historyResult->fetch_assoc()) {
    if ($record['record_type'] === 'Appointment') {
        $healthSections['appointments']['records'][] = $record;
    } elseif ($record['record_type'] === 'Medication') {
        $healthSections['medications']['records'][] = $record;
    } else {
        $healthSections['medical_history']['records'][] = $record;
    }
}
$historyStmt->close();

$species = strtolower($pet['species']);
$avatarClass = $species === 'dog' ? 'dog' : ($species === 'cat' ? 'cat' : 'other');
$initial = strtoupper(substr(trim($pet['pet_name']), 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pet['pet_name']) ?>'s Profile - PetRecord</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="pet-profile-page">
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
                <a href="index.php#my-pets" class="btn btn-outline"><i class="bi bi-heart-pulse"></i> My Pets</a>
                <a href="profile.php" class="nav-user-card" aria-label="User profile">
                    <span class="profile-avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($_SESSION['username'], 0, 1))) ?></span>
                    <span class="profile-details">
                        <span class="profile-name"><?= htmlspecialchars($_SESSION['username']) ?></span>
                        <span class="profile-role"><?= $_SESSION['role'] === 'admin' ? 'Administrator' : 'Pet Owner' ?></span>
                    </span>
                </a>
                <a href="logout.php" class="btn btn-outline"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>
    </nav>
    <main class="container">
        <section class="pet-profile-hero">
            <?php if (!empty($pet['photo_path'])): ?>
                <img class="pet-profile-avatar <?= $avatarClass ?>" src="<?= htmlspecialchars($pet['photo_path']) ?>" alt="<?= htmlspecialchars($pet['pet_name']) ?>" style="object-fit:cover;">
            <?php else: ?>
                <span class="pet-profile-avatar <?= $avatarClass ?>" aria-hidden="true"><?= htmlspecialchars($initial) ?></span>
            <?php endif; ?>
            <div class="pet-profile-heading">
                <span class="eyebrow"><i class="bi bi-heart-fill"></i> Pet Profile</span>
                <h1><?= htmlspecialchars($pet['pet_name']) ?></h1>
                <p><?= htmlspecialchars($pet['species']) ?><?= $pet['breed'] ? ' · ' . htmlspecialchars($pet['breed']) : '' ?></p>
            </div>
            <div class="pet-profile-actions">
                <?php if ($canManage): ?>
                    <a href="health.php?pet_id=<?= (int)$pet['id'] ?>" class="btn btn-primary"><i class="bi bi-heart-pulse"></i> Manage Health Records</a>
                <?php endif; ?>
                <?php if ($canManage): ?>
                    <a href="edit.php?id=<?= (int)$pet['id'] ?>" class="btn btn-light"><i class="bi bi-pencil"></i> Edit Pet</a>
                <?php endif; ?>
                <a href="index.php#my-pets" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Back to My Pets</a>
            </div>
        </section>

        <section class="card" style="margin-bottom:24px;">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-info-circle"></i>
                    <div>Pet Details<small>Profile information for <?= htmlspecialchars($pet['pet_name']) ?></small></div>
                </div>
            </div>
            <div style="padding:16px;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
                <?php
                $details = [
                    'Species' => $pet['species'] ?? '',
                    'Breed' => $pet['breed'] ?? '',
                    'Age' => isset($pet['age']) && $pet['age'] !== null && $pet['age'] !== '' ? $pet['age'] . ' yrs' : '—',
                    'Gender' => $pet['gender'] ?? '—',
                    'Birthdate' => !empty($pet['birthdate']) ? date('M j, Y', strtotime($pet['birthdate'])) : '—',
                    'Weight' => isset($pet['weight']) && $pet['weight'] !== null && $pet['weight'] !== '' ? $pet['weight'] . ' kg' : '—',
                    'Color' => $pet['color'] ?? '—',
                    'Microchip' => $pet['microchip_id'] ?? '—',
                    'Owner' => $pet['owner_name'] ?: '—',
                    'Contact' => $pet['contact_number'] ?: '—',
                ];
                foreach ($details as $label => $value):
                ?>
                    <div>
                        <strong style="display:block;font-size:12px;text-transform:uppercase;opacity:.7;"><?= htmlspecialchars($label) ?></strong>
                        <span><?= htmlspecialchars($value ?: '—') ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (!empty($pet['notes'])): ?>
                    <div style="grid-column:1/-1;">
                        <strong style="display:block;font-size:12px;text-transform:uppercase;opacity:.7;">Notes</strong>
                        <span><?= nl2br(htmlspecialchars($pet['notes'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <div class="pet-health-sections">
            <?php foreach ($healthSections as $section): ?>
                <section class="card pet-health-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="bi <?= $section['icon'] ?>"></i>
                            <div>
                                <?= htmlspecialchars($section['title']) ?>
                                <small><?= count($section['records']) ?> record<?= count($section['records']) === 1 ? '' : 's' ?> · <?= htmlspecialchars($section['description']) ?></small>
                            </div>
                        </div>
                    </div>
                    <?php if ($section['records']): ?>
                        <div class="health-timeline">
                            <?php foreach ($section['records'] as $record): ?>
                                <?php $isDue = !empty($record['next_due_date']) && $record['next_due_date'] <= date('Y-m-d'); ?>
                                <article class="health-entry">
                                    <span class="health-entry-icon health-type-<?= strtolower(preg_replace('/[^a-z]/i', '', $record['record_type'])) ?>" aria-hidden="true">
                                        <i class="bi <?= $record['record_type'] === 'Appointment' ? 'bi-calendar-event' : ($record['record_type'] === 'Vaccination' ? 'bi-shield-plus' : ($record['record_type'] === 'Medication' ? 'bi-capsule' : ($record['record_type'] === 'Checkup' ? 'bi-clipboard2-pulse' : 'bi-heart-pulse'))) ?>"></i>
                                    </span>
                                    <div class="health-entry-content">
                                        <div class="health-entry-topline">
                                            <span class="badge health-type-badge"><?= htmlspecialchars($record['record_type']) ?></span>
                                            <time datetime="<?= htmlspecialchars($record['record_date']) ?>"><?= date('M j, Y', strtotime($record['record_date'])) ?></time>
                                        </div>
                                        <h3><?= htmlspecialchars($record['title']) ?></h3>
                                        <?php if (!empty($record['details'])): ?>
                                            <p><?= nl2br(htmlspecialchars($record['details'])) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($record['next_due_date'])): ?>
                                            <span class="health-due <?= $isDue ? 'is-due' : '' ?>">
                                                <i class="bi bi-bell"></i>
                                                <?= $isDue ? 'Due' : 'Follow-up' ?> <?= date('M j, Y', strtotime($record['next_due_date'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state pet-health-empty">
                            <span class="empty-state-icon"><i class="bi <?= $section['icon'] ?>"></i></span>
                            <h3>No <?= strtolower(htmlspecialchars($section['title'])) ?> yet</h3>
                            <p><?= $canManage ? 'Add a ' . strtolower(rtrim($section['title'], 's')) . ' record using Manage Health Records.' : 'There are no records in this section yet.' ?></p>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
    </main>
    <script src="assets/js/sidebar.js" defer></script>
</body>
</html>
