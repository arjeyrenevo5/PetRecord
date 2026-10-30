<?php
function ensurePetOwnershipSchema($conn)
{
    $createTableQuery = "CREATE TABLE IF NOT EXISTS `pets` (
        `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
        `pet_name` VARCHAR(100) NOT NULL,
        `species` VARCHAR(50) NOT NULL,
        `breed` VARCHAR(100) DEFAULT NULL,
        `age` INT(11) DEFAULT NULL,
        `gender` ENUM('Male', 'Female', 'Unknown') DEFAULT 'Unknown',
        `owner_name` VARCHAR(100) DEFAULT NULL,
        `contact_number` VARCHAR(50) DEFAULT NULL,
        `owner_user_id` INT(11) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_pets_owner_user_id` (`owner_user_id`)
    )";

    if (!mysqli_query($conn, $createTableQuery)) {
        die("Unable to initialize pet records: " . htmlspecialchars(mysqli_error($conn)));
    }

    $columnResult = mysqli_query($conn, "SHOW COLUMNS FROM `pets` LIKE 'owner_user_id'");
    if (!$columnResult) {
        die("Unable to check pet ownership schema: " . htmlspecialchars(mysqli_error($conn)));
    }
    $hasOwnerColumn = mysqli_num_rows($columnResult) > 0;
    mysqli_free_result($columnResult);

    if (!$hasOwnerColumn && !mysqli_query($conn, "ALTER TABLE `pets` ADD COLUMN `owner_user_id` INT(11) DEFAULT NULL AFTER `contact_number`")) {
        die("Unable to add pet ownership support: " . htmlspecialchars(mysqli_error($conn)));
    }

    $indexResult = mysqli_query($conn, "SHOW INDEX FROM `pets` WHERE Key_name = 'idx_pets_owner_user_id'");
    if (!$indexResult) {
        die("Unable to check pet ownership index: " . htmlspecialchars(mysqli_error($conn)));
    }
    $hasOwnerIndex = mysqli_num_rows($indexResult) > 0;
    mysqli_free_result($indexResult);

    if (!$hasOwnerIndex && !mysqli_query($conn, "ALTER TABLE `pets` ADD INDEX `idx_pets_owner_user_id` (`owner_user_id`)")) {
        die("Unable to add pet ownership index: " . htmlspecialchars(mysqli_error($conn)));
    }
}

function ensurePetProfileSchema($conn)
{
    $columns = [
        'birthdate' => "ALTER TABLE `pets` ADD COLUMN `birthdate` DATE DEFAULT NULL AFTER `age`",
        'weight' => "ALTER TABLE `pets` ADD COLUMN `weight` DECIMAL(6,2) DEFAULT NULL AFTER `birthdate`",
        'color' => "ALTER TABLE `pets` ADD COLUMN `color` VARCHAR(50) DEFAULT NULL AFTER `weight`",
        'microchip_id' => "ALTER TABLE `pets` ADD COLUMN `microchip_id` VARCHAR(50) DEFAULT NULL AFTER `color`",
        'notes' => "ALTER TABLE `pets` ADD COLUMN `notes` TEXT DEFAULT NULL AFTER `microchip_id`",
        'photo_path' => "ALTER TABLE `pets` ADD COLUMN `photo_path` VARCHAR(255) DEFAULT NULL AFTER `notes`",
        'updated_at' => "ALTER TABLE `pets` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`",
    ];

    foreach ($columns as $column => $sql) {
        $result = mysqli_query($conn, "SHOW COLUMNS FROM `pets` LIKE '$column'");
        if (!$result) {
            die("Unable to check pet profile schema: " . htmlspecialchars(mysqli_error($conn)));
        }
        $exists = mysqli_num_rows($result) > 0;
        mysqli_free_result($result);
        if (!$exists && !mysqli_query($conn, $sql)) {
            die("Unable to add pet profile column `$column`: " . htmlspecialchars(mysqli_error($conn)));
        }
    }
}
