<?php
/**
 * Install audit_logs table
 * Run once: http://localhost/a_task/public/install_audit_logs.php
 */

require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';

$db = Database::getInstance();

try {
    // Create audit_logs table
    $db->execute("
        CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            action_type VARCHAR(50) NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id INT NULL,
            description TEXT NOT NULL,
            old_values JSON NULL,
            new_values JSON NULL,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_created_at (created_at),
            INDEX idx_entity (entity_type, entity_id),
            INDEX idx_user (user_id),
            INDEX idx_action (action_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "<h2 style='color: green;'>✅ audit_logs tablosu başarıyla oluşturuldu!</h2>";
    echo "<p><a href='audit_log.php'>Aktivite Günlüğüne Git</a></p>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Hata:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
