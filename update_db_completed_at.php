<?php
require_once __DIR__ . '/src/db.php';

try {
    $db = Database::getInstance();

    // Add completed_at column if it doesn't exist
    // MySQL doesn't have IF NOT EXISTS for columns in ALTER TABLE directly in all versions, 
    // so we can use a try-catch or check existence first.
    // For simplicity in this environment, I'll try to add it.

    $sql = "ALTER TABLE tasks ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL";

    try {
        $db->execute($sql);
        echo "completed_at column added successfully.";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "Column completed_at already exists.";
        } else {
            throw $e;
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
