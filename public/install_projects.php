<?php
require_once __DIR__ . '/../src/db.php';

try {
    $db = Database::getInstance();

    // 1. Create projects table
    $sqlProjects = "CREATE TABLE IF NOT EXISTS projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_code VARCHAR(50) NOT NULL,
        name VARCHAR(255) NOT NULL,
        type VARCHAR(100),
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $db->execute($sqlProjects);
    echo "SUCCESS: Table 'projects' created/verified.<br>";

    // 2. Create project_steps table
    $sqlSteps = "CREATE TABLE IF NOT EXISTS project_steps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        order_index INT DEFAULT 0,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $db->execute($sqlSteps);
    echo "SUCCESS: Table 'project_steps' created/verified.<br>";

    // 3. Update tasks table (Add columns if not exist)
    // We'll check if column exists first to avoid errors on re-run
    $columns = $db->fetchOne("SHOW COLUMNS FROM tasks LIKE 'project_id'");
    if (!$columns) {
        $sqlAlter = "ALTER TABLE tasks 
                     ADD COLUMN project_id INT NULL AFTER id,
                     ADD COLUMN project_step_id INT NULL AFTER project_id,
                     ADD CONSTRAINT fk_task_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
                     ADD CONSTRAINT fk_task_project_step FOREIGN KEY (project_step_id) REFERENCES project_steps(id) ON DELETE SET NULL";
        $db->execute($sqlAlter);
        echo "SUCCESS: Table 'tasks' altered with project columns.<br>";
    } else {
        echo "INFO: Table 'tasks' already has project columns.<br>";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>