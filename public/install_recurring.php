<?php
/**
 * Migration: Add recurring_group_id column to tasks table
 * Run once: http://localhost/a_task_dev/public/install_recurring.php
 */
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

check_auth();
$db = Database::getInstance();

$messages = [];

try {
    // Check if column already exists
    $columns = $db->query("SHOW COLUMNS FROM tasks LIKE 'recurring_group_id'");

    if (empty($columns)) {
        $db->execute("ALTER TABLE tasks ADD COLUMN recurring_group_id VARCHAR(36) DEFAULT NULL");
        $db->execute("ALTER TABLE tasks ADD INDEX idx_recurring_group (recurring_group_id)");
        $messages[] = ['type' => 'success', 'text' => '✅ recurring_group_id kolonu eklendi.'];
    } else {
        $messages[] = ['type' => 'info', 'text' => 'ℹ️ recurring_group_id kolonu zaten mevcut.'];
    }

} catch (Exception $e) {
    $messages[] = ['type' => 'error', 'text' => '❌ Hata: ' . $e->getMessage()];
}

require SRC_DIR . '/partials/header.php';
?>

<div class="glass glass-card" style="max-width: 600px; margin: 40px auto; padding: 30px;">
    <h2>🔄 Tekrarlayan Görevler - Migration</h2>

    <?php foreach ($messages as $msg): ?>
        <div style="
            padding: 12px 16px; 
            border-radius: 8px; 
            margin: 15px 0;
            background: <?= $msg['type'] === 'success' ? 'rgba(16,185,129,0.15)' : ($msg['type'] === 'error' ? 'rgba(239,68,68,0.15)' : 'rgba(59,130,246,0.15)') ?>;
            color: <?= $msg['type'] === 'success' ? '#10b981' : ($msg['type'] === 'error' ? '#ef4444' : '#3b82f6') ?>;
            font-weight: 500;
        ">
            <?= $msg['text'] ?>
        </div>
    <?php endforeach; ?>

    <a href="create_task.php" class="btn btn-primary" style="margin-top: 20px;">← Görev Oluştur Sayfasına Git</a>
</div>

<?php require SRC_DIR . '/partials/footer.php'; ?>