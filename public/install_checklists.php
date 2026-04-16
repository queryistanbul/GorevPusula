<?php
require_once __DIR__ . '/../src/db.php';

$db = Database::getInstance();

try {
    // 1. Checklist Templates (Definitions)
    $db->execute("
        CREATE TABLE IF NOT EXISTS checklist_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            department_id INT,
            name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'checklist_templates' created or exists.<br>";

    // 2. Checklist Template Items
    $db->execute("
        CREATE TABLE IF NOT EXISTS checklist_template_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            template_id INT NOT NULL,
            item_text TEXT NOT NULL,
            order_index INT DEFAULT 0,
            FOREIGN KEY (template_id) REFERENCES checklist_templates(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'checklist_template_items' created or exists.<br>";

    // 3. Task Checklist Items (Instances attached to tasks)
    $db->execute("
        CREATE TABLE IF NOT EXISTS task_checklist_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            task_id INT NOT NULL,
            item_text TEXT NOT NULL,
            is_completed TINYINT(1) DEFAULT 0,
            order_index INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'task_checklist_items' created or exists.<br>";

    echo "Migration completed successfully.";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
