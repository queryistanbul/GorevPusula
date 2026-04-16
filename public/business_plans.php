<?php
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

check_auth();
$db = Database::getInstance();
$user = Auth::user();

// Year selection (default: current year)
$selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');

// Department selection (for admins)
$selectedDeptId = null;
if ($user['role'] === 'admin' && isset($_GET['dept'])) {
    $selectedDeptId = (int) $_GET['dept'];
} else {
    $selectedDeptId = $user['department_id'];
}

// Handle Add Plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_plan') {
    verify_csrf();
    $month = (int) $_POST['month'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $dept_id = $selectedDeptId;

    // Authorization
    if ($user['role'] !== 'admin') {
        $allowed = [$user['department_id']];
        if (!empty($user['managed_department_ids'])) {
            $allowed = array_merge($allowed, $user['managed_department_ids']);
        }
        if (!in_array($dept_id, $allowed)) {
            die("Yetkisiz işlem.");
        }
    }

    if ($title && $month >= 1 && $month <= 12) {
        $db->execute(
            "INSERT INTO business_plans (department_id, year, month, title, description, created_by) VALUES (?, ?, ?, ?, ?, ?)",
            [$dept_id, $selectedYear, $month, $title, $description, $user['id']]
        );
        $planId = $db->lastInsertId();

        log_activity('create', 'business_plan', $planId, $user['full_name'] . " '$title' iş planını oluşturdu ($selectedYear)");

        header("Location: business_plans.php?year=$selectedYear&dept=$dept_id");
        exit;
    }
}

// Handle Update Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    verify_csrf();
    $plan_id = (int) $_POST['plan_id'];
    $new_status = $_POST['status'];

    // Verify ownership
    $plan = $db->fetchOne("SELECT * FROM business_plans WHERE id = ?", [$plan_id]);
    if ($plan) {
        $allowed = [$user['department_id']];
        if (!empty($user['managed_department_ids'])) {
            $allowed = array_merge($allowed, $user['managed_department_ids']);
        }

        if ($user['role'] === 'admin' || in_array($plan['department_id'], $allowed)) {
            $db->execute("UPDATE business_plans SET status = ? WHERE id = ?", [$new_status, $plan_id]);

            log_activity('update', 'business_plan', $plan_id, $user['full_name'] . " '{$plan['title']}' iş planının durumunu '{$new_status}' olarak güncelledi");
        }
    }
    header("Location: business_plans.php?year=$selectedYear&dept=$selectedDeptId");
    exit;
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_plan') {
    verify_csrf();
    $plan_id = (int) $_POST['plan_id'];

    $plan = $db->fetchOne("SELECT * FROM business_plans WHERE id = ?", [$plan_id]);
    if ($plan) {
        $allowed = [$user['department_id']];
        if (!empty($user['managed_department_ids'])) {
            $allowed = array_merge($allowed, $user['managed_department_ids']);
        }

        if ($user['role'] === 'admin' || in_array($plan['department_id'], $allowed)) {
            $db->execute("DELETE FROM business_plans WHERE id = ?", [$plan_id]);

            log_activity('delete', 'business_plan', $plan_id, $user['full_name'] . " '{$plan['title']}' iş planını sildi");
        }
    }
    header("Location: business_plans.php?year=$selectedYear&dept=$selectedDeptId");
    exit;
}

// Fetch Plans for selected department and year
$params = [$selectedDeptId, $selectedYear];
$plans = $db->query(
    "SELECT bp.*, u.full_name as creator_name 
     FROM business_plans bp 
     LEFT JOIN users u ON bp.created_by = u.id 
     WHERE bp.department_id = ? AND bp.year = ? 
     ORDER BY bp.month ASC, bp.created_at ASC",
    $params
);

// Group by month
$plansByMonth = [];
for ($m = 1; $m <= 12; $m++) {
    $plansByMonth[$m] = [];
}
foreach ($plans as $p) {
    $plansByMonth[$p['month']][] = $p;
}

// Fetch departments for admin filter
$departments = $db->query("SELECT * FROM departments ORDER BY name");

// Get current department name
$currentDeptName = '';
foreach ($departments as $d) {
    if ($d['id'] == $selectedDeptId) {
        $currentDeptName = $d['name'];
        break;
    }
}

// Months in Turkish
$monthNames = [
    1 => 'Ocak',
    2 => 'Şubat',
    3 => 'Mart',
    4 => 'Nisan',
    5 => 'Mayıs',
    6 => 'Haziran',
    7 => 'Temmuz',
    8 => 'Ağustos',
    9 => 'Eylül',
    10 => 'Ekim',
    11 => 'Kasım',
    12 => 'Aralık'
];

// Status labels and colors
$statusLabels = [
    'planned' => ['label' => 'Planlandı', 'color' => '#6366f1'],
    'in_progress' => ['label' => 'Devam Ediyor', 'color' => '#f59e0b'],
    'completed' => ['label' => 'Tamamlandı', 'color' => '#10b981'],
    'cancelled' => ['label' => 'İptal', 'color' => '#ef4444']
];

$layout = 'fluid';
require SRC_DIR . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">📋 İş Planları</h1>
        <p class="text-muted" style="margin-top: 5px;">
            <?= escape($currentDeptName) ?> -
            <?= $selectedYear ?> Yılı
        </p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
        <!-- Year Selector -->
        <select onchange="changeYear(this.value)" class="form-select" style="width: auto;">
            <?php for ($y = date('Y') - 2; $y <= date('Y') + 2; $y++): ?>
                <option value="<?= $y ?>" <?= $y == $selectedYear ? 'selected' : '' ?>>
                    <?= $y ?>
                </option>
            <?php endfor; ?>
        </select>

        <?php if ($user['role'] === 'admin'): ?>
            <!-- Department Selector for Admin -->
            <select onchange="changeDept(this.value)" class="form-select" style="width: auto;">
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $d['id'] == $selectedDeptId ? 'selected' : '' ?>>
                        <?= escape($d['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <button onclick="toggleAddForm()" class="btn btn-primary">+ Yeni Plan Ekle</button>
    </div>
</div>

<!-- Add Plan Form (Hidden) -->
<div id="addPlanForm" class="glass glass-card" style="display: none; margin-bottom: 20px;">
    <h3 style="margin-top: 0; margin-bottom: 15px;">Yeni İş Planı Ekle</h3>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_plan">

        <div style="display: grid; grid-template-columns: 150px 1fr; gap: 15px;">
            <div class="form-group">
                <label>Ay</label>
                <select name="month" class="form-select" required>
                    <?php foreach ($monthNames as $num => $name): ?>
                        <option value="<?= $num ?>" <?= $num == date('n') ? 'selected' : '' ?>>
                            <?= $name ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Başlık</label>
                <input type="text" name="title" class="form-input" required placeholder="Yapılacak iş/hedef">
            </div>
        </div>

        <div class="form-group">
            <label>Açıklama (Opsiyonel)</label>
            <textarea name="description" class="form-input" rows="2" placeholder="Detaylı açıklama..."></textarea>
        </div>

        <div style="text-align: right; margin-top: 15px;">
            <button type="button" onclick="toggleAddForm()" class="btn btn-glass"
                style="margin-right: 10px;">İptal</button>
            <button type="submit" class="btn btn-primary">Kaydet</button>
        </div>
    </form>
</div>

<!-- Monthly Plans Grid -->
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
    <?php foreach ($monthNames as $monthNum => $monthName): ?>
        <div class="glass glass-card" style="padding: 15px;">
            <h4
                style="margin: 0 0 15px 0; color: var(--primary); border-bottom: 1px solid var(--glass-border); padding-bottom: 10px;">
                <?= $monthName ?>
                <span class="text-muted" style="font-size: 0.8rem; font-weight: normal;">
                    (
                    <?= count($plansByMonth[$monthNum]) ?> plan)
                </span>
            </h4>

            <?php if (empty($plansByMonth[$monthNum])): ?>
                <p class="text-muted" style="font-size: 0.9rem; text-align: center; padding: 20px 0;">
                    Bu ay için plan yok
                </p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php foreach ($plansByMonth[$monthNum] as $plan): ?>
                        <div
                            style="background: rgba(255,255,255,0.05); border-radius: 8px; padding: 12px; border-left: 3px solid <?= $statusLabels[$plan['status']]['color'] ?>;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
                                <div style="flex: 1;">
                                    <strong>
                                        <?= escape($plan['title']) ?>
                                    </strong>
                                    <?php if ($plan['description']): ?>
                                        <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: var(--text-muted);">
                                            <?= nl2br(escape($plan['description'])) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div style="display: flex; gap: 5px;">
                                    <!-- Status Dropdown -->
                                    <form method="POST" style="display: inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                                        <select name="status" onchange="this.form.submit()" class="form-select"
                                            style="font-size: 0.75rem; padding: 4px 8px; width: auto; background: <?= $statusLabels[$plan['status']]['color'] ?>20; border-color: <?= $statusLabels[$plan['status']]['color'] ?>;">
                                            <?php foreach ($statusLabels as $key => $s): ?>
                                                <option value="<?= $key ?>" <?= $plan['status'] == $key ? 'selected' : '' ?>>
                                                    <?= $s['label'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>

                                    <!-- Delete Button -->
                                    <form method="POST" style="display: inline;"
                                        onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_plan">
                                        <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                                        <button type="submit" class="btn btn-sm"
                                            style="background: rgba(239,68,68,0.2); color: #ef4444; padding: 4px 8px;">×</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<script>
    function toggleAddForm() {
        const form = document.getElementById('addPlanForm');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
        if (form.style.display === 'block') {
            form.scrollIntoView({ behavior: 'smooth' });
        }
    }

    function changeYear(year) {
        const params = new URLSearchParams(window.location.search);
        params.set('year', year);
        window.location.href = 'business_plans.php?' + params.toString();
    }

    function changeDept(deptId) {
        const params = new URLSearchParams(window.location.search);
        params.set('dept', deptId);
        window.location.href = 'business_plans.php?' + params.toString();
    }
</script>

<?php require SRC_DIR . '/partials/footer.php'; ?>