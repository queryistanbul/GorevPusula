<?php
require_once __DIR__ . '/../src/db.php';

try {
    $db = Database::getInstance();

    // Check if column exists
    $columns = $db->fetchOne("SHOW COLUMNS FROM projects LIKE 'department_id'");

    if (!$columns) {
        // Add department_id column
        $sql = "ALTER TABLE projects 
                ADD COLUMN department_id INT NULL AFTER name,
                ADD CONSTRAINT fk_project_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL";

        $db->execute($sql);
        echo "SUCCESS: Column 'department_id' added to 'projects' table.<br>";

        // Optional: Update existing projects to a default department if needed?
        // For now, they will be NULL.
    } else {
        echo "INFO: Column 'department_id' already exists in 'projects' table.<br>";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>