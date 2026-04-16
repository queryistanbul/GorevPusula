<?php
require_once __DIR__ . '/../src/db.php';

try {
    $db = Database::getInstance();

    // Create user_departments table
    $sql = "CREATE TABLE IF NOT EXISTS user_departments (
        user_id INT NOT NULL,
        department_id INT NOT NULL,
        PRIMARY KEY (user_id, department_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $db->execute($sql);
    echo "SUCCESS: Table 'user_departments' created successfully.";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>