<?php
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

check_auth();
$db = Database::getInstance();
$user = Auth::user();

// Filter Logic
$myTasksOnly = isset($_GET['my']) && $_GET['my'] == '1';

$wheres = [];
$params = [];

if ($myTasksOnly) {
    // strict "My Work" filter
    $wheres[] = "t.owner_id = ?";
    $params[] = $user['id'];
} elseif ($user['role'] !== 'admin') {
    // Standard User Default View: Assigned + Requested + Department
    $deptId = $user['department_id'];
    // Assuming 'responsible_department_id' exists in tasks table as per previous context
    $wheres[] = "(t.owner_id = ? OR t.requester_id = ? OR t.responsible_department_id = ?)";
    $params[] = $user['id'];
    $params[] = $user['id'];
    $params[] = $deptId;
}

// Base Where Clause String
$baseWhere = "";
if (!empty($wheres)) {
    $baseWhere = " WHERE " . implode(" AND ", $wheres);
}

// Helper to append WHERE to existing conditions
function appendWhere($sql, $baseWhere, $andCondition = "")
{
    if (empty($baseWhere) && empty($andCondition))
        return $sql;

    // Check if SQL already has a WHERE clause (simple check)
    if (stripos($sql, 'WHERE') !== false) {
        $sql .= " AND " . ltrim($baseWhere, " WHERE");
    } else {
        $sql .= $baseWhere;
    }

    if (!empty($andCondition)) {
        $prefix = (stripos($sql, 'WHERE') !== false) ? " AND " : " WHERE ";
        $sql .= $prefix . $andCondition;
    }

    return $sql;
}

// 1. Key Metrics
// Total
$sql = "SELECT COUNT(*) as count FROM tasks t" . $baseWhere;
$totalTasks = $db->query($sql, $params)[0]['count'];

// Completed
$sql = "SELECT COUNT(*) as count FROM tasks t";
$sql = appendWhere($sql, $baseWhere, "t.status_id = 5");
$completedTasks = $db->query($sql, $params)[0]['count'];

// Pending
$pendingTasks = $totalTasks - $completedTasks;

// Overdue
$sql = "SELECT COUNT(*) as count FROM tasks t";
$sql = appendWhere($sql, $baseWhere, "t.target_completion_date < CURDATE() AND t.status_id != 5");
$overdueTasks = $db->query($sql, $params)[0]['count'];

// Custom Personal Stats (independent of global filters usually, but let's keep them handy)
// 1. Assigned to Me (Active)
$myRespCount = $db->query("SELECT COUNT(*) as count FROM tasks WHERE owner_id = ? AND status_id != 5", [$user['id']])[0]['count'];

// 2. Requested by Me, Assigned to Others (Active)
$myReqCount = $db->query("SELECT COUNT(*) as count FROM tasks WHERE requester_id = ? AND owner_id != ? AND status_id != 5", [$user['id'], $user['id']])[0]['count'];

// 2. Status Distribution (Pie Chart)
$sql = "SELECT s.name, s.color, COUNT(t.id) as count 
    FROM tasks t 
    JOIN statuses s ON t.status_id = s.id";
// Exclude Completed (5) and Cancelled (6)
$sql = appendWhere($sql, $baseWhere, "t.status_id NOT IN (5, 6)");
$sql .= " GROUP BY s.id";
$statusStats = $db->query($sql, $params);

// 3. Department Workload (Grouped by Status)
$sql = "SELECT d.name as dept_name, s.name as status_name, s.color as status_color, COUNT(t.id) as count
    FROM tasks t
    JOIN users u ON t.owner_id = u.id
    JOIN departments d ON u.department_id = d.id
    JOIN statuses s ON t.status_id = s.id";
$sql = appendWhere($sql, $baseWhere, "t.status_id != 5");
$sql .= " GROUP BY d.id, s.id ORDER BY d.name";
$rawDeptStats = $db->query($sql, $params);

// Process for Chart.js
$departments = [];
$statuses = [];
$matrix = [];

foreach ($rawDeptStats as $row) {
    $departments[$row['dept_name']] = $row['dept_name'];
    // Store status color
    $statuses[$row['status_name']] = $row['status_color'];
    // Store count
    $matrix[$row['dept_name']][$row['status_name']] = $row['count'];
}

$deptLabels = array_values($departments);
$deptDatasets = [];

foreach ($statuses as $sName => $sColor) {
    $data = [];
    foreach ($deptLabels as $dept) {
        $data[] = $matrix[$dept][$sName] ?? 0;
    }
    $deptDatasets[] = [
        'label' => $sName,
        'data' => $data,
        'backgroundColor' => $sColor,
    ];
}

// 4. User Workload (Grouped by Status)
$sql = "SELECT u.full_name as user_name, s.name as status_name, s.color as status_color, COUNT(t.id) as count
    FROM tasks t
    JOIN users u ON t.owner_id = u.id
    JOIN statuses s ON t.status_id = s.id";
$sql = appendWhere($sql, $baseWhere, "t.status_id != 5");
$sql .= " GROUP BY u.id, s.id ORDER BY u.full_name";
$rawUserStats = $db->query($sql, $params);

// Process for Chart.js
$users = [];
$matrix = [];
// Reuse statuses from Dept chart to ensure consistent coloring
foreach ($rawUserStats as $row) {
    $users[$row['user_name']] = $row['user_name'];
    $statuses[$row['status_name']] = $row['status_color'];
    $matrix[$row['user_name']][$row['status_name']] = $row['count'];
}

$userLabels = array_values($users);
$userDatasets = [];

foreach ($statuses as $sName => $sColor) {
    $data = [];
    foreach ($userLabels as $userName) {
        $data[] = $matrix[$userName][$sName] ?? 0;
    }
    $userDatasets[] = [
        'label' => $sName,
        'data' => $data,
        'backgroundColor' => $sColor,
    ];
}

// 5. Upcoming Deadlines
$sql = "SELECT t.*, u.full_name as owner_name, p.name as priority_name, p.color as priority_color
    FROM tasks t 
    LEFT JOIN users u ON t.owner_id = u.id
    LEFT JOIN priorities p ON t.priority_id = p.id";
$sql = appendWhere($sql, $baseWhere, "t.status_id != 5 AND t.target_completion_date >= CURDATE()");
$sql .= " ORDER BY t.target_completion_date ASC LIMIT 5";
$upcomingTasks = $db->query($sql, $params);

// 6. Pending Business Plans (Current Year, not completed/cancelled)
$currentYear = date('Y');
$deptId = is_array($user) ? ($user['department_id'] ?? 0) : 0;
$isAdmin = is_array($user) && ($user['role'] ?? '') === 'admin';

$bpSql = "SELECT bp.*, d.name as dept_name 
          FROM business_plans bp
          LEFT JOIN departments d ON bp.department_id = d.id
          WHERE bp.year = ? 
          AND bp.status NOT IN ('completed', 'cancelled')";

$bpParams = [$currentYear];

if (!$isAdmin) {
    // Normal users see only their department
    $bpSql .= " AND bp.department_id = ?";
    $bpParams[] = $deptId;
}

$bpSql .= " ORDER BY bp.month ASC, bp.id ASC";
$pendingPlans = $db->query($bpSql, $bpParams);

// DEBUG: (disabled)
// echo "<pre>Year: $currentYear | DeptId: $deptId | isAdmin: " . ($isAdmin ? 'true' : 'false') . "</pre>";
// echo "<pre>SQL: $bpSql</pre>";
// echo "<pre>Params: " . print_r($bpParams, true) . "</pre>";
// echo "<pre>Results: " . count($pendingPlans) . " plans found</pre>";
// echo "<pre>" . print_r($pendingPlans, true) . "</pre>";

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

$layout = 'fluid';
require SRC_DIR . '/partials/header.php';
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="page-header">
    <div>
        <h1 class="page-title">Kontrol Paneli</h1>
        <span style="color: var(--text-muted);"><?= date('d F Y, l') ?></span>
    </div>

    <div class="filter-bar">
        <label class="filter-checkbox">
            <input type="checkbox" id="myTasksFilter" <?= $myTasksOnly ? 'checked' : '' ?> onchange="toggleMyTasks()">
            Benim İşlerim
        </label>
    </div>
</div>

<script>
    function toggleMyTasks() {
        const chk = document.getElementById('myTasksFilter');
        const url = new URL(window.location.href);
        if (chk.checked) {
            url.searchParams.set('my', '1');
        } else {
            url.searchParams.delete('my');
        }
        window.location.href = url.toString();
    }
</script>

<!-- Summary Cards -->
<div class="stats-grid"
    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">

    <div class="glass glass-card"
        style="padding: 20px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <div style="color: var(--text-muted); font-size: 0.9rem;">Toplam Görev</div>
            <div style="font-size: 1.8rem; font-weight: bold; margin-top: 5px;">
                <?= $totalTasks ?>
            </div>
        </div>
        <div style="font-size: 2.5rem; opacity: 0.2;">📊</div>
    </div>

    <div class="glass glass-card"
        style="padding: 20px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <div style="color: var(--text-muted); font-size: 0.9rem;">Bekleyen</div>
            <div style="font-size: 1.8rem; font-weight: bold; margin-top: 5px; color: #fbbf24;">
                <?= $pendingTasks ?>
            </div>
        </div>
        <div style="font-size: 2.5rem; opacity: 0.2;">⏳</div>
    </div>

    <!-- My Responsibilities -->
    <div class="glass glass-card"
        style="padding: 20px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <div style="color: var(--text-muted); font-size: 0.9rem;">Üzerimdeki İşler</div>
            <div style="font-size: 1.8rem; font-weight: bold; margin-top: 5px; color: #3b82f6;"><?= $myRespCount ?>
            </div>
        </div>
        <div style="font-size: 2.5rem; opacity: 0.2;">👤</div>
    </div>

    <!-- My Requests (Assigned to others) -->
    <div class="glass glass-card"
        style="padding: 20px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <div style="color: var(--text-muted); font-size: 0.9rem;">Taleplerim</div>
            <div style="font-size: 1.8rem; font-weight: bold; margin-top: 5px; color: #8b5cf6;"><?= $myReqCount ?></div>
        </div>
        <div style="font-size: 2.5rem; opacity: 0.2;">📢</div>
    </div>

    <div class="glass glass-card"
        style="padding: 20px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <div style="color: var(--text-muted); font-size: 0.9rem;">Tamamlanan</div>
            <div style="font-size: 1.8rem; font-weight: bold; margin-top: 5px; color: #34d399;">
                <?= $completedTasks ?>
            </div>
        </div>
        <div style="font-size: 2.5rem; opacity: 0.2;">✅</div>
    </div>

    <div class="glass glass-card"
        style="padding: 20px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <div style="color: var(--text-muted); font-size: 0.9rem;">Geciken</div>
            <div style="font-size: 1.8rem; font-weight: bold; margin-top: 5px; color: #ef4444;">
                <?= $overdueTasks ?>
            </div>
        </div>
        <div style="font-size: 2.5rem; opacity: 0.2;">⚠️</div>
    </div>

</div>

<!-- Charts Row -->
<div class="charts-grid"
    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-bottom: 30px;">

    <!-- Status Distribution -->
    <div class="glass glass-card">
        <h3 class="card-title">Görev Durum Dağılımı</h3>
        <div style="height: 300px; position: relative;">
            <canvas id="statusChart"></canvas>
        </div>
    </div>

    <!-- Department Workload -->
    <div class="glass glass-card">
        <h3 class="card-title">Departman İş Yükü (Aktif)</h3>
        <div style="height: 300px; position: relative;">
            <canvas id="deptChart"></canvas>
        </div>
    </div>

    <!-- User Workload -->
    <div class="glass glass-card">
        <h3 class="card-title">Çalışan İş Yükü (Aktif)</h3>
        <div style="height: 300px; position: relative;">
            <canvas id="userChart"></canvas>
        </div>
    </div>

</div>

<!-- Bottom Grid: Upcoming Deadlines + Business Plans -->
<div class="bottom-grid"
    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 30px;">

    <!-- Upcoming Deadlines -->
    <div class="glass glass-card">
        <div class="card-header">
            <h3 class="card-title">Yaklaşan Teslim Tarihleri</h3>
            <a href="list.php" class="btn btn-sm btn-glass">Tümünü Gör</a>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Görev</th>
                    <th>Sorumlu</th>
                    <th>Tarih</th>
                    <th>Öncelik</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($upcomingTasks as $task): ?>
                    <tr>
                        <td>
                            <a href="task_detail.php?id=<?= $task['id'] ?>" class="link-white">
                                <?= escape($task['title']) ?>
                            </a>
                        </td>
                        <td>
                            <?= escape($task['owner_name']) ?>
                        </td>
                        <td>
                            <?php
                            $daysLeft = (strtotime($task['target_completion_date']) - time()) / 86400;
                            $color = $daysLeft < 2 ? '#ef4444' : ($daysLeft < 5 ? '#fbbf24' : 'var(--text-muted)');
                            ?>
                            <span style="color: <?= $color ?>; font-weight: 500;">
                                <?= date('d.m.Y', strtotime($task['target_completion_date'])) ?>
                            </span>
                            <small style="color: var(--text-muted);">
                                (<?= ceil($daysLeft) ?> gün)
                            </small>
                        </td>
                        <td>
                            <span class="status-badge"
                                style="background: <?= $task['priority_color'] ?>20; color: <?= $task['priority_color'] ?>;">
                                <?= escape($task['priority_name']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($upcomingTasks)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 20px;">
                            Yaklaşan görev bulunmamaktadır.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pending Business Plans -->
    <div class="glass glass-card">
        <div class="card-header">
            <h3 class="card-title">Departman İş Planı (Bekleyen)</h3>
            <a href="business_plans.php" class="btn btn-sm btn-glass">Planı Yönet</a>
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px; max-height: 400px; overflow-y: auto;">
            <?php foreach ($pendingPlans as $plan): ?>
                <div
                    style="background: rgba(255,255,255,0.05); border-radius: 8px; padding: 12px; border-left: 3px solid #f59e0b;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
                        <div>
                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 2px;">
                                <?= $monthNames[$plan['month']] ?>     <?= $plan['year'] ?>
                                <?php if ($isAdmin): ?>
                                    <span style="opacity: 0.7; margin-left: 5px;">• <?= escape($plan['dept_name']) ?></span>
                                <?php endif; ?>
                            </div>
                            <strong><?= escape($plan['title']) ?></strong>
                            <?php if ($plan['description']): ?>
                                <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: var(--text-muted);">
                                    <?= nl2br(escape($plan['description'])) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <span class="status-badge" style="background: #f59e0b20; color: #f59e0b; font-size: 0.7rem;">
                            <?= $plan['status'] == 'in_progress' ? 'Devam Ediyor' : 'Planlandı' ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($pendingPlans)): ?>
                <div style="text-align: center; color: var(--text-muted); padding: 20px;">
                    Bekleyen iş planı bulunmuyor. 🎉
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Weekly Performance Pivot Table -->
<?php
// 7. Weekly Task Completion Pivot Table (Employees x Weeks)
$currentYear = date('Y');
$currentWeek = (int) date('W');

// Get completed tasks grouped by user and week for current year
$pivotSql = "SELECT 
        u.id as user_id,
        u.full_name as user_name, 
        d.name as dept_name,
        WEEK(t.completed_at, 1) as week_num,
        COUNT(t.id) as completed_count
    FROM tasks t
    JOIN users u ON t.owner_id = u.id
    JOIN departments d ON u.department_id = d.id
    WHERE t.status_id = 5 
    AND YEAR(t.completed_at) = ?";

$pivotParams = [$currentYear];

// Apply department filter for non-admin users
if ($user['role'] !== 'admin') {
    $pivotSql .= " AND u.department_id = ?";
    $pivotParams[] = $user['department_id'];
}

$pivotSql .= " GROUP BY u.id, WEEK(t.completed_at, 1)
    ORDER BY d.name, u.full_name, week_num";
$pivotData = $db->query($pivotSql, $pivotParams);

// Build matrix: users -> weeks -> count
$employees = [];
$weekMatrix = [];

foreach ($pivotData as $row) {
    $userId = $row['user_id'];
    if (!isset($employees[$userId])) {
        $employees[$userId] = [
            'name' => $row['user_name'],
            'dept' => $row['dept_name']
        ];
    }
    $weekMatrix[$userId][$row['week_num']] = $row['completed_count'];
}

// Sort employees by department
uasort($employees, function ($a, $b) {
    return strcmp($a['dept'], $b['dept']);
});

// Determine weeks to show (1 to current week)
$weeksToShow = range(1, $currentWeek);
?>

<div class="glass glass-card" style="margin-top: 20px;">
    <div class="card-header">
        <h3 class="card-title">📈 <?= $currentYear ?> Yılı Haftalık Performans</h3>
    </div>

    <?php if (empty($employees)): ?>
        <div style="text-align: center; color: var(--text-muted); padding: 30px;">
            Bu yıl tamamlanan görev bulunmuyor.
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="table" style="min-width: 100%;">
                <thead>
                    <tr>
                        <th style="position: sticky; left: 0; background: var(--glass-bg); z-index: 1;">Çalışan</th>
                        <th style="position: sticky; left: 0; background: var(--glass-bg); z-index: 1;">Departman</th>
                        <?php foreach ($weeksToShow as $week): ?>
                            <th
                                style="text-align: center; min-width: 50px; <?= $week == $currentWeek ? 'background: rgba(99, 102, 241, 0.2);' : '' ?>">
                                H<?= $week ?>
                            </th>
                        <?php endforeach; ?>
                        <th style="text-align: center; background: rgba(16, 185, 129, 0.1);">Toplam</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $currentDept = '';
                    foreach ($employees as $userId => $emp):
                        $rowTotal = 0;
                        ?>
                        <tr>
                            <td style="font-weight: 500;"><?= escape($emp['name']) ?></td>
                            <td style="color: var(--text-muted); font-size: 0.85rem;"><?= escape($emp['dept']) ?></td>
                            <?php foreach ($weeksToShow as $week):
                                $count = $weekMatrix[$userId][$week] ?? 0;
                                $rowTotal += $count;
                                $bgColor = $count > 0 ? 'rgba(16, 185, 129, ' . min(0.1 + ($count * 0.1), 0.5) . ')' : 'transparent';
                                ?>
                                <td
                                    style="text-align: center; background: <?= $bgColor ?>; <?= $week == $currentWeek ? 'border: 1px solid rgba(99, 102, 241, 0.3);' : '' ?>">
                                    <?= $count > 0 ? $count : '-' ?>
                                </td>
                            <?php endforeach; ?>
                            <td
                                style="text-align: center; font-weight: bold; background: rgba(16, 185, 129, 0.1); color: #10b981;">
                                <?= $rowTotal ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Recently Completed Tasks (Last 15 Days) -->
<?php
$recentlyCompletedSql = "SELECT t.*, u.full_name as owner_name, p.name as priority_name, p.color as priority_color
    FROM tasks t 
    LEFT JOIN users u ON t.owner_id = u.id
    LEFT JOIN priorities p ON t.priority_id = p.id
    WHERE t.status_id = 5 
    AND t.completed_at >= DATE_SUB(NOW(), INTERVAL 15 DAY)";

$rcParams = [];

// Apply department filter for non-admin users
if ($user['role'] !== 'admin') {
    $recentlyCompletedSql .= " AND u.department_id = ?";
    $rcParams[] = $user['department_id'];
}

$recentlyCompletedSql .= " ORDER BY t.completed_at DESC LIMIT 15";
$recentlyCompleted = $db->query($recentlyCompletedSql, $rcParams);
?>

<div class="glass glass-card" style="margin-top: 20px;">
    <div class="card-header">
        <h3 class="card-title">✅ Son 15 Günde Tamamlanan Görevler</h3>
        <a href="list.php?completed=1" class="btn btn-sm btn-glass">Tümünü Gör</a>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Görev</th>
                <th>Sorumlu</th>
                <th>Tamamlanma Tarihi</th>
                <th>Öncelik</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentlyCompleted as $task): ?>
                <tr>
                    <td>
                        <a href="task_detail.php?id=<?= $task['id'] ?>" class="link-white">
                            <?= escape($task['title']) ?>
                        </a>
                    </td>
                    <td>
                        <?= escape($task['owner_name']) ?>
                    </td>
                    <td>
                        <?php
                        $daysAgo = floor((time() - strtotime($task['completed_at'])) / 86400);
                        ?>
                        <span style="color: #10b981; font-weight: 500;">
                            <?= date('d.m.Y', strtotime($task['completed_at'])) ?>
                        </span>
                        <small style="color: var(--text-muted);">
                            (<?= $daysAgo == 0 ? 'bugün' : $daysAgo . ' gün önce' ?>)
                        </small>
                    </td>
                    <td>
                        <span class="status-badge"
                            style="background: <?= $task['priority_color'] ?>20; color: <?= $task['priority_color'] ?>;">
                            <?= escape($task['priority_name']) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($recentlyCompleted)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 20px;">
                        Son 15 günde tamamlanan görev bulunmamaktadır.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    // Theme colors
    const isDark = document.body.getAttribute('data-theme') !== 'light';
    const textColor = isDark ? '#94a3b8' : '#64748b';
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';

    // Status Chart
    const ctxStatus = document.getElementById('statusChart').getContext('2d');
    new Chart(ctxStatus, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($statusStats, 'name')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($statusStats, 'count')) ?>,
                backgroundColor: <?= json_encode(array_column($statusStats, 'color')) ?>,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: { color: textColor }
                }
            }
        }
    });

    // Dept Chart
    const ctxDept = document.getElementById('deptChart').getContext('2d');
    new Chart(ctxDept, {
        type: 'bar',
        data: {
            labels: <?= json_encode($deptLabels) ?>,
            datasets: <?= json_encode($deptDatasets) ?>
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    stacked: true,
                    beginAtZero: true,
                    grid: { color: gridColor },
                    ticks: { color: textColor }
                },
                x: {
                    stacked: true,
                    grid: { display: false },
                    ticks: { color: textColor }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    labels: { color: textColor }
                }
            }
        }
    });

    // User Chart
    const ctxUser = document.getElementById('userChart').getContext('2d');
    new Chart(ctxUser, {
        type: 'bar',
        data: {
            labels: <?= json_encode($userLabels) ?>,
            datasets: <?= json_encode($userDatasets) ?>
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    stacked: true,
                    beginAtZero: true,
                    grid: { color: gridColor },
                    ticks: { color: textColor }
                },
                x: {
                    stacked: true,
                    grid: { display: false },
                    ticks: { color: textColor }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    labels: { color: textColor }
                }
            }
        }
    });
</script>

<?php require SRC_DIR . '/partials/footer.php'; ?>