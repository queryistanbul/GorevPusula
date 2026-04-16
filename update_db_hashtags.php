<?php
require_once __DIR__ . '/src/db.php';

$db = Database::getInstance();

try {
    $db->execute("ALTER TABLE tasks ADD COLUMN hashtags VARCHAR(255) DEFAULT NULL");
    echo "Successfully added 'hashtags' column to 'tasks' table.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "Column 'hashtags' already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
