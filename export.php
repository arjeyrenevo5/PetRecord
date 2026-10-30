<?php
require_once 'db.php';
require_once 'assets/pet_schema.php';
require_once 'assets/health_schema.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

ensurePetOwnershipSchema($conn);
ensurePetProfileSchema($conn);

$where = [];
if ($_SESSION['role'] !== 'admin') {
    $where[] = "owner_user_id = " . (int)$_SESSION['user_id'];
}
$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="pet_records_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['ID', 'Pet Name', 'Species', 'Breed', 'Age', 'Gender', 'Color', 'Weight', 'Birthdate', 'Microchip', 'Owner', 'Contact', 'Notes', 'Created']);

$res = mysqli_query($conn, "SELECT * FROM pets $whereClause ORDER BY id DESC");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        fputcsv($out, [
            $row['id'],
            $row['pet_name'],
            $row['species'],
            $row['breed'],
            $row['age'],
            $row['gender'],
            $row['color'] ?? '',
            $row['weight'] ?? '',
            $row['birthdate'] ?? '',
            $row['microchip_id'] ?? '',
            $row['owner_name'],
            $row['contact_number'],
            $row['notes'] ?? '',
            $row['created_at'],
        ]);
    }
}
fclose($out);
exit;
