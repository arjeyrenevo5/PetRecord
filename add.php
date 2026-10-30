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
ensurePetHealthRecordsSchema($conn);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '')) {
        $error = "Your session expired. Refresh the page and try again.";
    } else {
        $pet_name = trim(is_string($_POST['pet_name'] ?? null) ? $_POST['pet_name'] : '');
        $species = trim(is_string($_POST['species'] ?? null) ? $_POST['species'] : '');
        $breed = trim(is_string($_POST['breed'] ?? null) ? $_POST['breed'] : '');
        $ageInput = trim(is_string($_POST['age'] ?? null) ? $_POST['age'] : '');
        $age = $ageInput === '' ? null : filter_var($ageInput, FILTER_VALIDATE_INT);
        $gender = trim(is_string($_POST['gender'] ?? null) ? $_POST['gender'] : 'Unknown');
        $owner_name = trim(is_string($_POST['owner_name'] ?? null) ? $_POST['owner_name'] : '');
        $contact_number = trim(is_string($_POST['contact_number'] ?? null) ? $_POST['contact_number'] : '');
        $birthdate = trim(is_string($_POST['birthdate'] ?? null) ? $_POST['birthdate'] : '');
        $weightInput = trim(is_string($_POST['weight'] ?? null) ? $_POST['weight'] : '');
        $weight = $weightInput === '' ? null : filter_var($weightInput, FILTER_VALIDATE_FLOAT);
        $color = trim(is_string($_POST['color'] ?? null) ? $_POST['color'] : '');
        $microchip_id = trim(is_string($_POST['microchip_id'] ?? null) ? $_POST['microchip_id'] : '');
        $notes = trim(is_string($_POST['notes'] ?? null) ? $_POST['notes'] : '');

        $validBirthdate = true;
        if ($birthdate !== '') {
            $d = DateTime::createFromFormat('!Y-m-d', $birthdate);
            $validBirthdate = $d && $d->format('Y-m-d') === $birthdate;
        }

        if ($pet_name === '' || $species === '' || ($ageInput !== '' && ($age === false || $age < 0 || $age > 100)) || !in_array($gender, ['Unknown', 'Male', 'Female'], true) || !$validBirthdate || ($weightInput !== '' && ($weight === false || $weight < 0))) {
            $error = "Please fill in all required fields (Pet Name and Species).";
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO `pets` (`pet_name`, `species`, `breed`, `age`, `gender`, `owner_name`, `contact_number`, `owner_user_id`, `birthdate`, `weight`, `color`, `microchip_id`, `notes`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $owner_user_id = (int)$_SESSION['user_id'];
                $bd = $birthdate !== '' ? $birthdate : null;
                mysqli_stmt_bind_param($stmt, 'sssisssisdsss', $pet_name, $species, $breed, $age, $gender, $owner_name, $contact_number, $owner_user_id, $bd, $weight, $color, $microchip_id, $notes);
            }

            if ($stmt && mysqli_stmt_execute($stmt)) {
                $newId = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

                // photo upload
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $file = $_FILES['photo'];
                    if ($file['error'] === UPLOAD_ERR_OK) {
                        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = finfo_file($finfo, $file['tmp_name']);
                        finfo_close($finfo);
                        if (in_array($mime, $allowed, true) && $file['size'] <= 2*1024*1024) {
                            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                            if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) { $ext = 'jpg'; }
                            $newName = 'pet_' . $newId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                            $dest = __DIR__ . '/assets/uploads/pets/' . $newName;
                            if (move_uploaded_file($file['tmp_name'], $dest)) {
                                $relPath = 'assets/uploads/pets/' . $newName;
                                $up = $conn->prepare("UPDATE pets SET photo_path=? WHERE id=?");
                                $up->bind_param('si', $relPath, $newId);
                                $up->execute();
                                $up->close();
                            }
                        }
                    }
                }

                header("Location: index.php?msg=added");
                exit;
            } else {
                $error = "Error saving pet record: " . htmlspecialchars($stmt ? mysqli_stmt_error($stmt) : mysqli_error($conn));
                if ($stmt) {
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Pet - PetRecord</title>
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
                <a href="profile.php" class="btn btn-outline"><i class="bi bi-person"></i> Profile</a>
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
        <div class="form-shell">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <div class="form-card">
                <div class="form-banner">
                    <div class="icon-badge">
                        <i class="bi bi-plus-circle-fill"></i>
                    </div>
                    <div>
                        <h1>Add New Pet</h1>
                        <p>Fill in the details below to welcome a new member to your pet family.</p>
                    </div>
                </div>

                <div class="form-body">
                    <form method="POST" action="add.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="form-grid">
                            <div class="field full">
                                <label for="pet_name">Pet Name <span class="req">*</span></label>
                                <input type="text" id="pet_name" name="pet_name" class="form-control" placeholder="e.g. Milo, Bella" value="<?= htmlspecialchars($_POST['pet_name'] ?? '') ?>" required>
                            </div>

                            <div class="field">
                                <label for="species">Species <span class="req">*</span></label>
                                <select id="species" name="species" class="form-control" required>
                                    <option value="" disabled <?= empty($_POST['species']) ? 'selected' : '' ?>>Select species</option>
                                    <?php foreach (['Dog', 'Cat', 'Bird', 'Fish', 'Other'] as $option): ?>
                                        <option value="<?= $option ?>" <?= ($_POST['species'] ?? '') === $option ? 'selected' : '' ?>><?= $option ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field">
                                <label for="breed">Breed</label>
                                <input type="text" id="breed" name="breed" class="form-control" placeholder="e.g. Golden Retriever" value="<?= htmlspecialchars($_POST['breed'] ?? '') ?>">
                            </div>

                            <div class="field">
                                <label for="age">Age (in years)</label>
                                <input type="number" id="age" name="age" class="form-control" min="0" max="100" placeholder="e.g. 2" value="<?= htmlspecialchars($_POST['age'] ?? '') ?>">
                            </div>

                            <div class="field">
                                <label for="gender">Gender</label>
                                <select id="gender" name="gender" class="form-control">
                                    <?php foreach (['Unknown', 'Male', 'Female'] as $option): ?>
                                        <option value="<?= $option ?>" <?= ($_POST['gender'] ?? 'Unknown') === $option ? 'selected' : '' ?>><?= $option ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field">
                                <label for="birthdate">Birthdate</label>
                                <input type="date" id="birthdate" name="birthdate" class="form-control" value="<?= htmlspecialchars($_POST['birthdate'] ?? '') ?>">
                            </div>

                            <div class="field">
                                <label for="weight">Weight (kg)</label>
                                <input type="number" step="0.01" id="weight" name="weight" class="form-control" min="0" value="<?= htmlspecialchars($_POST['weight'] ?? '') ?>">
                            </div>

                            <div class="field">
                                <label for="color">Color / Markings</label>
                                <input type="text" id="color" name="color" class="form-control" value="<?= htmlspecialchars($_POST['color'] ?? '') ?>">
                            </div>

                            <div class="field">
                                <label for="microchip_id">Microchip ID</label>
                                <input type="text" id="microchip_id" name="microchip_id" class="form-control" value="<?= htmlspecialchars($_POST['microchip_id'] ?? '') ?>">
                            </div>

                            <div class="field full">
                                <label for="photo">Pet Photo</label>
                                <input type="file" id="photo" name="photo" class="form-control" accept="image/*">
                            </div>

                            <div class="field full">
                                <label for="notes">Notes</label>
                                <textarea id="notes" name="notes" class="form-control" rows="3"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="section-heading">
                            <i class="bi bi-person-vcard"></i> Owner Information
                        </div>

                        <div class="form-grid">
                            <div class="field">
                                <label for="owner_name">Owner Name</label>
                                <input type="text" id="owner_name" name="owner_name" class="form-control" placeholder="e.g. Juan Dela Cruz" value="<?= htmlspecialchars($_POST['owner_name'] ?? '') ?>">
                            </div>

                            <div class="field">
                                <label for="contact_number">Contact Number</label>
                                <input type="text" id="contact_number" name="contact_number" class="form-control" placeholder="e.g. 09123456789" value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-lg"></i> Save Pet Record
                            </button>
                            <a href="index.php" class="btn btn-light btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/sidebar.js" defer></script>
</body>
</html>
PHP
echo done