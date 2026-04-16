<?php
require_once __DIR__ . '/../src/db.php';

try {
    $db = Database::getInstance();

    // Check if table exists
    $tableExists = $db->fetchOne("SHOW TABLES LIKE 'business_plans'");

    if (!$tableExists) {
        $sql = "CREATE TABLE business_plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            department_id INT NOT NULL,
            year INT NOT NULL,
            month TINYINT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            status ENUM('planned', 'in_progress', 'completed', 'cancelled') DEFAULT 'planned',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_dept_year_month (department_id, year, month)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $db->execute($sql);
        echo "SUCCESS: Table 'business_plans' created.<br>";
    } else {
        echo "INFO: Table 'business_plans' already exists.<br>";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>