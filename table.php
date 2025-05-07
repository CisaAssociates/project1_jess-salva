<?php 

include 'db_config.php';

$conn = mysqli_connect($host,$user,$pass,$db);

$stmt = "CREATE TABLE IF NOT EXISTS `attendance_logs` (
    `attendance_log_id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL, -- Links to the users table (assuming a `users` table exists)
    `log_type` ENUM('in', 'out') NOT NULL COMMENT 'Type of attendance event (in or out)',
    `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- When the event occurred
    `device_id` VARCHAR(50) NOT NULL COMMENT 'ID of the device that recorded this log (from device config)',
    `location_context` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Location context from device config',
    `face_recognized` BOOLEAN NOT NULL COMMENT 'Was the face recognized for this entry?',
    `rfid_card_id_used` VARCHAR(50) NULL DEFAULT NULL COMMENT 'RFID card ID used for this entry, if any',
    `confidence_score` DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'Face recognition confidence score for the detected face',
    PRIMARY KEY (`attendance_log_id`),
    INDEX `user_id` (`user_id`), -- Indexing user_id for faster lookups by user
    INDEX `timestamp` (`timestamp`), -- Indexing timestamp for time-based queries
    INDEX `log_type` (`log_type`), -- Indexing log_type for filtering by in/out
    -- Foreign key constraint assumes you have a 'users' table with 'user_id' as its primary key.
    -- ON DELETE CASCADE: If a user is deleted, their attendance logs are also deleted.
    -- ON UPDATE CASCADE: If a user's user_id changes, the corresponding logs are updated.
    CONSTRAINT `fk_attendance_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
)";

if(mysqli_query($conn,$stmt)){
    echo "sucess";
};