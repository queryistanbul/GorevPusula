<?php
require_once __DIR__ . '/src/db.php';

try {
    $db = Database::getInstance();

    $sql = "CREATE TABLE IF NOT EXISTS task_comments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        task_id INT NOT NULL,
        user_id INT NOT NULL,
        comment TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $db->execute($sql);
    echo "task_comments table created successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
