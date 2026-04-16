<?php
require_once __DIR__ . '/src/db.php';

$db = Database::getInstance();
$sql = "CREATE TABLE IF NOT EXISTS user_departments (
    user_id INT NOT NULL,
    department_id INT NOT NULL,
    PRIMARY KEY (user_id, department_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

try {
    $db->execute($sql);
    echo "Migration successful.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
