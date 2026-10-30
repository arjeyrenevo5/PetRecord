<?php
function ensurePetHealthRecordsSchema($conn)
{
    $schemaSql = "CREATE TABLE IF NOT EXISTS `pet_health_records` (
        `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
        `pet_id` INT(11) NOT NULL,
        `record_type` VARCHAR(30) NOT NULL,
        `title` VARCHAR(120) NOT NULL,
        `details` TEXT,
        `record_date` DATE NOT NULL,
        `next_due_date` DATE DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_pet_health_records_pet_date` (`pet_id`, `record_date`)
    )";

    if (!$conn->query($schemaSql)) {
        http_response_code(500);
        exit('Unable to prepare pet health records: ' . htmlspecialchars($conn->error));
    }
}
