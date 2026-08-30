<?php
require_once 'db.php';

$error = "";

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
        $sql = "INSERT INTO `pets` (`pet_name`, `species`, `breed`, `age`, `gender`, `owner_name`, `contact_number`) 
                VALUES ('$pet_name', '$species', '$breed', $age, '$gender', '$owner_name', '$contact_number')";

        if (mysqli_query($conn, $sql)) {
            header("Location: index.php?msg=added");
            exit;
        } else {
            $error = "Error saving pet record: " . mysqli_error($conn);
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
        .form-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>

    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top py-3 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
                <i class="bi bi-shield-shaded fs-3 text-warning"></i>
                <span>Pet<span class="text-warning">Record</span></span>
            </a>
            <div>
                <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                    <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card form-card bg-white p-4 p-md-5">
                    <div class="d-flex align-items-center gap-3 mb-4 border-bottom pb-3">
                        <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle fs-4">
                            <i class="bi bi-plus-circle-fill"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-1">Add New Pet</h3>
                            <p class="text-muted small mb-0">Fill in the details below to register a new pet.</p>
                        </div>
                    </div>

                    <form method="POST" action="add.php">
                        
                        <!-- Pet Name -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Pet Name <span class="text-danger">*</span></label>
                            <input type="text" name="pet_name" class="form-control form-control-lg" placeholder="e.g. Milo, Bella" required>
                        </div>

                        <!-- Species & Breed -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Species <span class="text-danger">*</span></label>
                                <select name="species" class="form-select form-select-lg" required>
                                    <option value="" disabled selected>Select species</option>
                                    <option value="Dog">Dog</option>
                                    <option value="Cat">Cat</option>
                                    <option value="Bird">Bird</option>
                                    <option value="Fish">Fish</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Breed</label>
                                <input type="text" name="breed" class="form-control form-control-lg" placeholder="e.g. Golden Retriever">
                            </div>
                        </div>

                        <!-- Age & Gender -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Age (in years)</label>
                                <input type="number" name="age" class="form-control form-control-lg" min="0" max="100" placeholder="e.g. 2">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Gender</label>
                                <select name="gender" class="form-select form-select-lg">
                                    <option value="Unknown">Unknown</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                        </div>

                        <!-- Owner Information -->
                        <div class="border-top pt-3 mt-4 mb-3">
                            <h6 class="text-uppercase text-muted fw-bold small mb-3">Owner Information</h6>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Owner Name</label>
                                <input type="text" name="owner_name" class="form-control form-control-lg" placeholder="e.g. Juan Dela Cruz">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Contact Number</label>
                                <input type="text" name="contact_number" class="form-control form-control-lg" placeholder="e.g. 09123456789">
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="d-flex gap-2 pt-3">
                            <button type="submit" class="btn btn-primary btn-lg flex-grow-1 fw-semibold">
                                <i class="bi bi-check-lg me-1"></i> Save Pet Record
                            </button>
                            <a href="index.php" class="btn btn-light btn-lg border fw-semibold">
                                Cancel
                            </a>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>