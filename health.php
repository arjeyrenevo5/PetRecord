<?php
require_once 'db.php';
require_once 'assets/paw.php';
require_once 'assets/health_schema.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

ensurePetHealthRecordsSchema($conn);

$petId = filter_input(INPUT_GET, 'pet_id', FILTER_VALIDATE_INT) ?: 0;
$petStmt = $conn->prepare('SELECT id, pet_name, species, breed FROM pets WHERE id = ?');
if (!$petStmt) {
    http_response_code(500);
    exit('Unable to load pet profile: ' . htmlspecialchars($conn->error));
}
$petStmt->bind_param('i', $petId);
$petLoaded = $petStmt->execute();
if (!$petLoaded) {
    http_response_code(500);
    exit('Unable to load pet profile: ' . htmlspecialchars($petStmt->error));
}
$petResult = $petStmt->get_result();
$pet = $petResult->fetch_assoc();
$petStmt->close();

if (!$pet) {
    header('Location: index.php?msg=pet-not-found');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $ownCheck = $conn->prepare('SELECT owner_user_id FROM pets WHERE id = ?');
    $ownCheck->bind_param('i', $petId);
    $ownCheck->execute();
    $ownRes = $ownCheck->get_result();
    $ownRow = $ownRes ? $ownRes->fetch_assoc() : null;
    $ownCheck->close();
    if (!$ownRow || (int)$ownRow['owner_user_id'] !== (int)$_SESSION['user_id']) {
        header('Location: index.php');
        exit;
    }
}

$message = isset($_GET['saved']) ? 'Health record saved successfully.' : '';
$messageType = 'success';
$error = '';
$recordTypes = ['Appointment', 'Checkup', 'Vaccination', 'Medication', 'Treatment', 'Other'];

// Handle deletion of a health record
if (isset($_POST['delete_record_id'])) {
    if (!hash_equals($_SESSION['csrf_token'], is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '')) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        $delId = filter_var($_POST['delete_record_id'], FILTER_VALIDATE_INT);
        if ($delId && $delId > 0) {
            $delStmt = $conn->prepare('DELETE FROM pet_health_records WHERE id = ? AND pet_id = ?');
            if ($delStmt) {
                $delStmt->bind_param('ii', $delId, $petId);
                if ($delStmt->execute()) {
                    header('Location: health.php?pet_id=' . $petId . '&saved=1');
                    exit;
                }
                $error = 'Unable to delete record: ' . $delStmt->error;
                $delStmt->close();
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$postedValue = static function ($key, $default = '') {
    return is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : $default;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $postedValue('csrf_token');
    $type = $postedValue('record_type');
    $title = $postedValue('title');
    $details = $postedValue('details');
    $recordDate = $postedValue('record_date');
    $nextDueDate = $postedValue('next_due_date');
    $validDate = static function ($value) {
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    };

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (!in_array($type, $recordTypes, true)) {
        $error = 'Choose a valid health record type.';
    } elseif ($title === '' || strlen($title) > 120) {
        $error = 'Enter a record title of up to 120 characters.';
    } elseif (!$validDate($recordDate)) {
        $error = 'Enter a valid record date.';
    } elseif ($nextDueDate !== '' && (!$validDate($nextDueDate) || $nextDueDate < $recordDate)) {
        $error = 'The follow-up date must be valid and on or after the record date.';
    } elseif (strlen($details) > 10000) {
        $error = 'Details must be 10,000 characters or fewer.';
    } else {
        $dueDate = $nextDueDate !== '' ? $nextDueDate : null;
        $detailsValue = $details !== '' ? $details : null;
        $insertStmt = $conn->prepare(
            'INSERT INTO pet_health_records (pet_id, record_type, title, details, record_date, next_due_date) VALUES (?, ?, ?, ?, ?, ?)'
        );
        if (!$insertStmt) {
            $error = 'Unable to prepare health record: ' . $conn->error;
        } else {
            $insertStmt->bind_param('isssss', $petId, $type, $title, $detailsValue, $recordDate, $dueDate);

            if ($insertStmt->execute()) {
                $insertStmt->close();
                header('Location: health.php?pet_id=' . $petId . '&saved=1');
                exit;
            }

            $error = 'Unable to save health record: ' . $insertStmt->error;
            $insertStmt->close();
        }
    }
}

$historyStmt = $conn->prepare(
    'SELECT id, record_type, title, details, record_date, next_due_date FROM pet_health_records WHERE pet_id = ? ORDER BY record_date DESC, id DESC'
);
if (!$historyStmt) {
    http_response_code(500);
    exit('Unable to load pet health history: ' . htmlspecialchars($conn->error));
}
$historyStmt->bind_param('i', $petId);
$historyLoaded = $historyStmt->execute();
if (!$historyLoaded) {
    http_response_code(500);
    exit('Unable to load pet health history: ' . htmlspecialchars($historyStmt->error));
}
$historyResult = $historyStmt->get_result();
$history = $historyResult->fetch_all(MYSQLI_ASSOC);
$historyStmt->close();
$petLabel = $pet['pet_name'] . ' · ' . $pet['species'];
if (!empty($pet['breed'])) {
    $petLabel .= ' · ' . $pet['breed'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health History - <?= htmlspecialchars($pet['pet_name']) ?> | PetRecord</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="health-page">
    <button class="sidebar-toggle" id="sidebar-toggle" type="button" aria-controls="sidebar-nav" aria-expanded="true" aria-label="Hide navigation">
        <i class="bi bi-x-lg" aria-hidden="true"></i>
    </button>
    <nav id="sidebar-nav">
        <div class="nav-container">
            <a href="index.php" class="brand">
                <i class="brand-mark"><?= paw_svg() ?></i>
                Pet<span>Record</span> <small>Admin</small>
            </a>
            <div class="nav-links">
                <a href="index.php#my-pets" class="btn btn-outline"><i class="bi bi-heart-pulse"></i> Pet Records</a>
                <a href="admin.php" class="btn btn-outline"><i class="bi bi-gear"></i> Admin Panel</a>
                <a href="profile.php" class="btn btn-outline"><i class="bi bi-person"></i> Profile</a>
                <a href="logout.php" class="btn btn-outline"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>
    </nav>
    <div class="profile-card" aria-label="User profile">
        <span class="profile-avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($_SESSION['username'], 0, 1))) ?></span>
        <span class="profile-details">
            <span class="profile-name"><?= htmlspecialchars($_SESSION['username']) ?></span>
            <span class="profile-role">Administrator</span>
        </span>
    </div>

    <main class="container">
        <section class="page-head health-page-head">
            <div>
                <span class="eyebrow"><i class="bi bi-heart-pulse"></i> Health &amp; Care</span>
                <h1><?= htmlspecialchars($pet['pet_name']) ?>'s Health History</h1>
                <p class="page-sub"><?= htmlspecialchars($petLabel) ?> · Keep checkups, vaccinations, treatments, and follow-ups together.</p>
            </div>
            <div class="page-actions">
                <a href="index.php#my-pets" class="btn btn-light"><i class="bi bi-arrow-left"></i> Back to Pet Records</a>
            </div>
        </section>

        <?php if ($message): ?>
            <div class="alert alert-success toast-notification" role="status" aria-live="polite">
                <i class="bi bi-check-circle-fill"></i>
                <span><?= htmlspecialchars($message) ?></span>
                <button class="toast-close" type="button" aria-label="Dismiss notification"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <div class="health-layout">
            <section class="card health-form-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="bi bi-plus-circle"></i>
                        <div>New Health Record<small>Add an appointment, vaccine, treatment, or medication</small></div>
                    </div>
                </div>
                <div class="health-form-body">
                    <form method="POST" action="health.php?pet_id=<?= $petId ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="field">
                            <label for="record_type">Record type</label>
                            <select class="form-control" id="record_type" name="record_type" required>
                                <?php foreach ($recordTypes as $typeOption): ?>
                                    <option value="<?= htmlspecialchars($typeOption) ?>" <?= $postedValue('record_type', 'Checkup') === $typeOption ? 'selected' : '' ?>><?= htmlspecialchars($typeOption) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="title">Title</label>
                            <input class="form-control" id="title" name="title" type="text" maxlength="120" placeholder="e.g. Annual wellness check" value="<?= htmlspecialchars($postedValue('title')) ?>" required>
                        </div>
                        <div class="health-date-grid">
                            <div class="field">
                                <label for="record_date">Appointment / record date</label>
                                <input class="form-control" id="record_date" name="record_date" type="date" value="<?= htmlspecialchars($postedValue('record_date', date('Y-m-d'))) ?>" required>
                            </div>
                            <div class="field">
                                <label for="next_due_date">Follow-up date <span class="field-optional">Optional</span></label>
                                <input class="form-control" id="next_due_date" name="next_due_date" type="date" value="<?= htmlspecialchars($postedValue('next_due_date')) ?>">
                            </div>
                        </div>
                        <div class="field">
                            <label for="details">Notes <span class="field-optional">Optional</span></label>
                            <textarea class="form-control health-notes" id="details" name="details" maxlength="10000" placeholder="Add vet details, dosage, observations, or care instructions..."><?= htmlspecialchars($postedValue('details')) ?></textarea>
                        </div>
                        <button class="btn btn-primary health-submit" type="submit"><i class="bi bi-check-lg"></i> Save Health Record</button>
                    </form>
                </div>
            </section>

            <section class="card health-history-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="bi bi-journal-medical"></i>
                        <div>Health Timeline<small><?= count($history) ?> record<?= count($history) === 1 ? '' : 's' ?> for <?= htmlspecialchars($pet['pet_name']) ?></small></div>
                    </div>
                </div>
                <?php if ($history): ?>
                    <div class="health-timeline">
                        <?php foreach ($history as $record): ?>
                            <?php $isDue = !empty($record['next_due_date']) && $record['next_due_date'] <= date('Y-m-d'); ?>
                            <article class="health-entry">
                                <span class="health-entry-icon health-type-<?= strtolower($record['record_type']) ?>" aria-hidden="true">
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
                                    <form method="POST" action="health.php?pet_id=<?= $petId ?>" style="margin-top:8px;" onsubmit="return confirm('Delete this health record?')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="delete_record_id" value="<?= (int)$record['id'] ?>">
                                        <button type="submit" class="icon-btn danger" title="Delete record"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state health-empty-state">
                        <span class="empty-state-icon"><i class="bi bi-clipboard2-heart"></i></span>
                        <h3>No health records yet</h3>
                        <p>Add the first visit or care note to start <?= htmlspecialchars($pet['pet_name']) ?>'s health timeline.</p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
    <script src="assets/js/sidebar.js" defer></script>
</body>
</html>
