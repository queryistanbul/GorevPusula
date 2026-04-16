<?php
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

check_auth();
$user = Auth::user();

// Admin only
if ($user['role'] !== 'admin') {
    redirect('dashboard.php');
}

$db = Database::getInstance();

// Filters
$filterUser = $_GET['user_id'] ?? '';
$filterAction = $_GET['action'] ?? '';
$filterEntity = $_GET['entity'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$filterDateTo = $_GET['date_to'] ?? date('Y-m-d');

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Build query
$sql = "SELECT al.*, u.full_name as user_name 
        FROM audit_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        WHERE 1=1";
$countSql = "SELECT COUNT(*) as total FROM audit_logs al WHERE 1=1";
$params = [];

if ($filterUser) {
    $sql .= " AND al.user_id = ?";
    $countSql .= " AND al.user_id = ?";
    $params[] = $filterUser;
}

if ($filterAction) {
    $sql .= " AND al.action_type = ?";
    $countSql .= " AND al.action_type = ?";
    $params[] = $filterAction;
}

if ($filterEntity) {
    $sql .= " AND al.entity_type = ?";
    $countSql .= " AND al.entity_type = ?";
    $params[] = $filterEntity;
}

if ($filterDateFrom) {
    $sql .= " AND DATE(al.created_at) >= ?";
    $countSql .= " AND DATE(al.created_at) >= ?";
    $params[] = $filterDateFrom;
}

if ($filterDateTo) {
    $sql .= " AND DATE(al.created_at) <= ?";
    $countSql .= " AND DATE(al.created_at) <= ?";
    $params[] = $filterDateTo;
}

// Get total count
$total = $db->query($countSql, $params)[0]['total'];
$totalPages = ceil($total / $perPage);

// Get logs with pagination
$sql .= " ORDER BY al.created_at DESC LIMIT $perPage OFFSET $offset";
$logs = $db->query($sql, $params);

// Get filter options
$users = $db->query("SELECT id, full_name FROM users ORDER BY full_name");
$actionTypes = $db->query("SELECT DISTINCT action_type FROM audit_logs ORDER BY action_type");
$entityTypes = $db->query("SELECT DISTINCT entity_type FROM audit_logs ORDER BY entity_type");

// Action type labels
$actionLabels = [
    'create' => ['🆕', 'Oluşturma', '#10b981'],
    'update' => ['✏️', 'Güncelleme', '#3b82f6'],
    'delete' => ['🗑️', 'Silme', '#ef4444'],
    'login' => ['🔐', 'Giriş', '#8b5cf6'],
    'logout' => ['🚪', 'Çıkış', '#6b7280'],
    'status_change' => ['🔄', 'Durum Değişikliği', '#f59e0b'],
];

$layout = 'fluid';
require SRC_DIR . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">📋 Aktivite Günlüğü</h1>
        <span style="color: var(--text-muted);">Sistemdeki tüm hareketlerin kaydı</span>
    </div>
</div>

<!-- Filters -->
<div class="glass glass-card" style="margin-bottom: 20px; padding: 15px;">
    <form method="GET" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">

        <div style="flex: 1; min-width: 150px;">
            <label
                style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px;">Kullanıcı</label>
            <select name="user_id" class="form-control form-control-sm">
                <option value="">Tümü</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $filterUser == $u['id'] ? 'selected' : '' ?>>
                        <?= escape($u['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 1; min-width: 150px;">
            <label
                style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px;">Aksiyon</label>
            <select name="action" class="form-control form-control-sm">
                <option value="">Tümü</option>
                <?php foreach ($actionTypes as $a): ?>
                    <option value="<?= $a['action_type'] ?>" <?= $filterAction == $a['action_type'] ? 'selected' : '' ?>>
                        <?= $actionLabels[$a['action_type']][1] ?? $a['action_type'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 1; min-width: 150px;">
            <label
                style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px;">Entity</label>
            <select name="entity" class="form-control form-control-sm">
                <option value="">Tümü</option>
                <?php foreach ($entityTypes as $e): ?>
                    <option value="<?= $e['entity_type'] ?>" <?= $filterEntity == $e['entity_type'] ? 'selected' : '' ?>>
                        <?= ucfirst($e['entity_type']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 1; min-width: 130px;">
            <label
                style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px;">Başlangıç</label>
            <input type="date" name="date_from" value="<?= $filterDateFrom ?>" class="form-control form-control-sm">
        </div>

        <div style="flex: 1; min-width: 130px;">
            <label
                style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px;">Bitiş</label>
            <input type="date" name="date_to" value="<?= $filterDateTo ?>" class="form-control form-control-sm">
        </div>

        <div>
            <button type="submit" class="btn btn-primary btn-sm">Filtrele</button>
            <a href="audit_log.php" class="btn btn-glass btn-sm">Sıfırla</a>
        </div>
    </form>
</div>

<!-- Results -->
<div class="glass glass-card">
    <div class="card-header" style="margin-bottom: 15px;">
        <span style="color: var(--text-muted);">Toplam
            <?= number_format($total) ?> kayıt
        </span>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 150px;">Tarih</th>
                <th style="width: 150px;">Kullanıcı</th>
                <th style="width: 140px;">Aksiyon</th>
                <th>Açıklama</th>
                <th style="width: 100px;">IP</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log):
                $actionInfo = $actionLabels[$log['action_type']] ?? ['📌', $log['action_type'], '#6b7280'];
                ?>
                <tr>
                    <td style="font-size: 0.85rem; color: var(--text-muted);">
                        <?= date('d.m.Y H:i', strtotime($log['created_at'])) ?>
                    </td>
                    <td>
                        <?= escape($log['user_name'] ?? 'Sistem') ?>
                    </td>
                    <td>
                        <span class="status-badge"
                            style="background: <?= $actionInfo[2] ?>20; color: <?= $actionInfo[2] ?>;">
                            <?= $actionInfo[0] ?>
                            <?= $actionInfo[1] ?>
                        </span>
                    </td>
                    <td>
                        <?= escape($log['description']) ?>
                        <?php if ($log['old_values'] || $log['new_values']): ?>
                            <button type="button" class="btn btn-glass btn-sm"
                                style="margin-left: 10px; padding: 2px 8px; font-size: 0.7rem;"
                                onclick="toggleDetails(<?= $log['id'] ?>)">Detay</button>
                            <div id="details-<?= $log['id'] ?>"
                                style="display: none; margin-top: 10px; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 5px; font-size: 0.8rem;">
                                <?php if ($log['old_values']): ?>
                                    <div style="margin-bottom: 5px;"><strong>Eski:</strong>
                                        <code><?= escape($log['old_values']) ?></code></div>
                                <?php endif; ?>
                                <?php if ($log['new_values']): ?>
                                    <div><strong>Yeni:</strong> <code><?= escape($log['new_values']) ?></code></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="font-size: 0.8rem; color: var(--text-muted);">
                        <?= escape($log['ip_address']) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">
                        Bu kriterlere uygun kayıt bulunamadı.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div style="display: flex; justify-content: center; gap: 5px; margin-top: 20px;">
            <?php
            $queryParams = $_GET;
            for ($i = max(1, $page - 3); $i <= min($totalPages, $page + 3); $i++):
                $queryParams['page'] = $i;
                ?>
                <a href="?<?= http_build_query($queryParams) ?>"
                    class="btn <?= $i == $page ? 'btn-primary' : 'btn-glass' ?> btn-sm">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    function toggleDetails(id) {
        const el = document.getElementById('details-' + id);
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
    }
</script>

<?php require SRC_DIR . '/partials/footer.php'; ?>