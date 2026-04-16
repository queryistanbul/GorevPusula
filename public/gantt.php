<?php
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

check_auth();
$db = Database::getInstance();
$user = Auth::user();

// Filters
$myTasksOnly = isset($_GET['my']) && $_GET['my'] == '1';
$overdueOnly = isset($_GET['overdue']) && $_GET['overdue'] == '1';
$filterOwnerId = isset($_GET['owner']) ? (int) $_GET['owner'] : null;
$filterProjectId = isset($_GET['project']) ? (int) $_GET['project'] : null;

// Fetch projects for the project filter
if ($user['role'] === 'admin') {
    $filterProjects = $db->query("SELECT id, name, project_code FROM projects ORDER BY project_code");
} else {
    $deptIds = [$user['department_id']];
    if (!empty($user['managed_department_ids'])) {
        $deptIds = array_merge($deptIds, $user['managed_department_ids']);
    }
    $placeholders = implode(',', array_fill(0, count($deptIds), '?'));
    
    $sqlProj = "SELECT DISTINCT p.id, p.name, p.project_code 
                FROM projects p 
                LEFT JOIN tasks t ON p.id = t.project_id 
                WHERE p.department_id IN ($placeholders) OR p.department_id IS NULL 
                   OR t.owner_id = ? OR t.requester_id = ? 
                ORDER BY p.project_code";
    
    $projParams = array_merge($deptIds, [$user['id'], $user['id']]);
    $filterProjects = $db->query($sqlProj, $projParams);
}

// Fetch users for Owner Filter
$filterUsersSql = "SELECT id, full_name FROM users WHERE is_active = 1";
$filterUsersParams = [];

if ($user['role'] !== 'admin') {
    $deptIds = [$user['department_id']];
    if (!empty($user['managed_department_ids'])) {
        $deptIds = array_merge($deptIds, $user['managed_department_ids']);
    }
    $placeholders = implode(',', array_fill(0, count($deptIds), '?'));
    $filterUsersSql .= " AND department_id IN ($placeholders)";
    $filterUsersParams = $deptIds;
}
$filterUsersSql .= " ORDER BY full_name";
$filterUsers = $db->query($filterUsersSql, $filterUsersParams);

// Fetch Tasks
// Order by target_completion_date ASC for chronological Gantt
$orderBy = "t.target_completion_date IS NULL ASC, t.target_completion_date ASC, s.order_index ASC, t.created_at DESC";
$tasks = get_tasks($db, $user, $myTasksOnly, $overdueOnly, false, $orderBy, $filterOwnerId, $filterProjectId);

// Prepare Gantt Data
$ganttTasks = [];
$minDate = time();
$maxDate = time();

foreach ($tasks as $task) {
    if (!$task['target_completion_date'])
        continue;

    $endTs = strtotime($task['target_completion_date']);
    // Logic: Start date is 3 weeks (21 days) before target
    $startTs = strtotime('-3 weeks', $endTs);

    if ($startTs < $minDate)
        $minDate = $startTs;
    if ($endTs > $maxDate)
        $maxDate = $endTs;

    $ganttTasks[] = [
        'id' => $task['id'],
        'title' => $task['title'],
        'owner_name' => $task['owner_name'],
        'status_color' => $task['status_color'],
        'status_name' => $task['status_name'],
        'start_ts' => $startTs,
        'end_ts' => $endTs,
        'days_duration' => 21,
        'project_code' => $task['project_code'] ?? '',
        'project_name' => $task['project_name'] ?? ''
    ];
}

// Sort chronologically by End Date (target_completion_date)
usort($ganttTasks, function ($a, $b) {
    if ($a['end_ts'] === $b['end_ts']) {
        return strcasecmp($a['title'], $b['title']);
    }
    return ($a['end_ts'] < $b['end_ts']) ? -1 : 1;
});

// Add padding to date range (1 month before and after)
$minDate = strtotime('-1 month', $minDate);
$maxDate = strtotime('+1 month', $maxDate);

// Generate Months Headers
$months = [];
$curr = strtotime(date('Y-m-01', $minDate));
$end = strtotime(date('Y-m-t', $maxDate));

while ($curr <= $end) {
    $months[] = [
        'label' => date('F Y', $curr), // e.g. January 2026
        'ts' => $curr,
        'days' => date('t', $curr)
    ];
    $curr = strtotime('+1 month', $curr);
}

// Turkish Month Names
$trMonths = [
    'January' => 'Ocak',
    'February' => 'Şubat',
    'March' => 'Mart',
    'April' => 'Nisan',
    'May' => 'Mayıs',
    'June' => 'Haziran',
    'July' => 'Temmuz',
    'August' => 'Ağustos',
    'September' => 'Eylül',
    'October' => 'Ekim',
    'November' => 'Kasım',
    'December' => 'Aralık'
];

$totalDays = ($end - strtotime(date('Y-m-01', $minDate))) / 86400;

$layout = 'fluid';
require SRC_DIR . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Görevlerim (Gantt)</h1>
    </div>
    <?php
    // Calculate active filters count for the button badge
    $activeFilterCount = 0;
    if ($overdueOnly)
        $activeFilterCount++;
    if ($filterOwnerId)
        $activeFilterCount++;
    if ($filterProjectId)
        $activeFilterCount++;
    if ($myTasksOnly)
        $activeFilterCount++;
    ?>

    <div class="filter-bar">
        <button type="button" class="btn btn-sm btn-glass"
            onclick="document.getElementById('filterModal').style.display='flex'" style="margin-right: 15px;">
            ⚙️ Filtreler <?= $activeFilterCount > 0 ? "($activeFilterCount)" : "" ?>
        </button>

        <div class="view-switcher">
            <a href="index.php?<?= http_build_query(array_merge($_GET, ['view' => 'kanban'])) ?>"
                class="btn btn-sm btn-glass">Kanban</a>
            <a href="list.php?<?= http_build_query(array_merge($_GET, ['view' => 'list'])) ?>"
                class="btn btn-sm btn-glass">Liste</a>
            <a href="gantt.php?<?= http_build_query(array_merge($_GET, ['view' => 'gantt'])) ?>"
                class="btn btn-sm btn-primary">Gantt</a>
        </div>
        <a href="create_task.php" class="btn btn-primary">+ Yeni Görev</a>
    </div>
</div>

<div class="glass glass-card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column;">
    <!-- Gantt Header (Months) -->
    <div class="gantt-header"
        style="display: flex; border-bottom: 1px solid var(--glass-border); background: rgba(255,255,255,0.05);">

        <!-- Sidebar HeaderSplit -->
        <div style="display: flex; min-width: 350px; border-right: 1px solid var(--glass-border);">
            <div
                style="flex: 0 0 100px; padding: 15px; font-weight: bold; border-right: 1px solid var(--glass-border);">
                Proje
            </div>
            <div style="flex: 1; padding: 15px; font-weight: bold;">
                Görev
            </div>
        </div>

        <div class="gantt-timeline-header" style="flex: 1; display: flex; overflow-x: auto;">
            <?php foreach ($months as $m): ?>
                <?php
                $mName = str_replace(array_keys($trMonths), array_values($trMonths), $m['label']);
                ?>
                <div
                    style="flex: 1; min-width: 100px; text-align: center; padding: 15px; border-right: 1px solid var(--glass-border);">
                    <?= $mName ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Gantt Body -->
    <div class="gantt-body" style="overflow-y: auto; max-height: calc(100vh - 250px); position: relative;">
        <?php foreach ($ganttTasks as $task): ?>
            <div class="gantt-row" style="display: flex; border-bottom: 1px solid var(--glass-border);">
                <!-- Task Info Sidebar -->
                <div class="gantt-sidebar-item"
                    style="display: flex; min-width: 350px; border-right: 1px solid var(--glass-border); background: rgba(255,255,255,0.02);">

                    <!-- Project Column -->
                    <div
                        style="flex: 0 0 100px; padding: 15px; border-right: 1px solid var(--glass-border); display: flex; align-items: center;">
                        <span style="font-size: 0.85rem; color: var(--primary); font-weight: bold;">
                            <?= escape($task['project_code'] ?: '-') ?>
                        </span>
                    </div>

                    <!-- Task Column -->
                    <div style="flex: 1; padding: 15px; overflow: hidden;">
                        <div
                            style="font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px;">
                            <a href="task_detail.php?id=<?= $task['id'] ?>" class="link-white">
                                <?= escape($task['title']) ?>
                            </a>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">
                            <?= escape($task['owner_name']) ?>
                        </div>
                    </div>
                </div>

                <!-- Timeline Bar area -->
                <div class="gantt-timeline-row"
                    style="flex: 1; display: flex; position: relative; min-height: 60px; align-items: center;">

                    <!-- Month Grid Lines -->
                    <?php foreach ($months as $i => $m): ?>
                        <div style="flex: 1; min-width: 100px; height: 100%; border-right: 1px solid rgba(255,255,255,0.05);">
                        </div>
                    <?php endforeach; ?>

                    <!-- Task Bar -->
                    <?php
                    // Calculate position
                    // Total timeline range
                    $timelineStart = strtotime(date('Y-m-01', $minDate));
                    $timelineEnd = strtotime(date('Y-m-t', $maxDate));
                    $totalSeconds = $timelineEnd - $timelineStart;

                    // Task position relative to start
                    $taskStartRelative = $task['start_ts'] - $timelineStart;
                    $taskDuration = $task['end_ts'] - $task['start_ts'];

                    if ($taskStartRelative < 0)
                        $taskStartRelative = 0; // Clip if out of bounds (shouldn't happen with padding)
                
                    $leftPercent = ($taskStartRelative / $totalSeconds) * 100;
                    $widthPercent = ($taskDuration / $totalSeconds) * 100;
                    ?>
                    <div class="gantt-bar"
                        title="<?= date('d.m.Y', $task['start_ts']) ?> - <?= date('d.m.Y', $task['end_ts']) ?>" style="
                            position: absolute; 
                            left: <?= $leftPercent ?>%; 
                            width: <?= $widthPercent ?>%; 
                            height: 24px; 
                            background: <?= $task['status_color'] ?>; 
                            border-radius: 12px; 
                            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                            cursor: pointer;
                         " onclick="window.location='task_detail.php?id=<?= $task['id'] ?>'">

                        <!-- Tooltip or label inside bar optional -->
                    </div>

                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($ganttTasks)): ?>
            <div style="padding: 20px; text-align: center; color: var(--text-muted);">
                Görüntülenecek görev verisi yok.
            </div>
        <?php endif; ?>

        <!-- Today Marker Line -->
        <?php
        // Calculate today's position
        $today = strtotime('today');

        // Find which month today falls in
        $todayMonthIndex = -1;
        foreach ($months as $idx => $month) {
            $monthStart = $month['ts'];
            $monthEnd = strtotime('+1 month', $monthStart) - 1;
            if ($today >= $monthStart && $today <= $monthEnd) {
                $todayMonthIndex = $idx;
                break;
            }
        }

        if ($todayMonthIndex >= 0) {
            $monthStart = $months[$todayMonthIndex]['ts'];
            $daysInMonth = $months[$todayMonthIndex]['days'];
            $dayOfMonth = date('j', $today); // 1-31
        
            // Each month column has flex: 1, so they're equal width in the flex container
            // Calculate position as: (months completed + day position within month) / total months
            $totalMonths = count($months);
            $monthWidthPercent = 100 / $totalMonths;

            // We need to convert this to CSS. The timeline width is (100% of container - 350px sidebar).
            // So left = 350px + (Percentage * TimelineWidth)
        
            // Re-calculate the percentage first (was missing)
            // Position within the current month (0 to 1)
            $dayPositionInMonth = ($dayOfMonth - 1) / $daysInMonth;

            // Total position percentage
            $todayPercent = ($todayMonthIndex * $monthWidthPercent) + ($dayPositionInMonth * $monthWidthPercent);

            // IMPORTANT: Use number_format to ensure dot separator for CSS, regardless of server locale
            $pctVal = number_format($todayPercent / 100, 6, '.', '');
            ?>
            <div id="today-line" style="
                position: absolute;
                left: calc(350px + ((100% - 350px) * <?= $pctVal ?>));
                top: 0;
                bottom: 0;
                width: 2px;
                background: #ef4444;
                z-index: 10;
                pointer-events: none;
            "></div>
        <?php } ?>
    </div>
</div>

<!-- Filter Modal -->
<div id="filterModal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1050; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
    <div class="glass glass-card" style="width: 100%; max-width: 400px; padding: 25px; position: relative;">
        <button type="button" onclick="document.getElementById('filterModal').style.display='none'"
            style="position: absolute; top: 15px; right: 15px; background: none; border: none; color: var(--text-muted); font-size: 1.2rem; cursor: pointer;">✕</button>

        <h3 style="margin-top: 0; margin-bottom: 20px; color: var(--text-main);">Filtreler</h3>

        <div style="display: flex; flex-direction: column; gap: 15px;">

            <label class="filter-checkbox"
                style="color: #ef4444; width: 100%; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                <input type="checkbox" id="overdueFilter" <?= $overdueOnly ? 'checked' : '' ?>>
                ⚠️ Gecikenler
            </label>

            <label class="filter-checkbox"
                style="width: 100%; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                <input type="checkbox" id="myTasksFilter" <?= $myTasksOnly ? 'checked' : '' ?>>
                👤 Benim Görevlerim
            </label>

            <div style="padding: 10px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                <label
                    style="display: block; margin-bottom: 5px; font-size: 0.9rem; color: var(--text-muted);">Sorumlu</label>
                <select id="ownerFilter" class="form-control form-control-sm"
                    style="width: 100%; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 4px; padding: 6px 8px;">
                    <option value="">Tüm Sorumlular</option>
                    <?php foreach ($filterUsers as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($filterOwnerId == $u['id']) ? 'selected' : '' ?>>
                            <?= escape($u['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="padding: 10px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                <label
                    style="display: block; margin-bottom: 5px; font-size: 0.9rem; color: var(--text-muted);">Proje</label>
                <select id="projectFilter" class="form-control form-control-sm"
                    style="width: 100%; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 4px; padding: 6px 8px;">
                    <option value="">Tüm Projeler</option>
                    <?php foreach ($filterProjects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($filterProjectId == $p['id']) ? 'selected' : '' ?>>
                            <?= escape($p['project_code'] . ' - ' . $p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="margin-top: 25px; display: flex; gap: 10px; justify-content: flex-end;">
            <button type="button" class="btn btn-sm btn-glass" onclick="clearFilters()">Temizle</button>
            <button type="button" class="btn btn-sm btn-primary" onclick="updateFilters()">Uygula</button>
        </div>
    </div>
</div>

<script>
    function updateFilters() {
        const myTasks = document.getElementById('myTasksFilter').checked ? '1' : '0';
        const overdue = document.getElementById('overdueFilter').checked ? '1' : '0';
        const owner = document.getElementById('ownerFilter').value;
        const project = document.getElementById('projectFilter').value;

        const params = new URLSearchParams(window.location.search);
        if (myTasks === '1') params.set('my', '1'); else params.delete('my');
        if (overdue === '1') params.set('overdue', '1'); else params.delete('overdue');
        if (owner) params.set('owner', owner); else params.delete('owner');
        if (project) params.set('project', project); else params.delete('project');

        params.set('view', 'gantt');

        window.location.href = 'gantt.php?' + params.toString();
    }

    function clearFilters() {
        document.getElementById('myTasksFilter').checked = false;
        document.getElementById('overdueFilter').checked = false;
        document.getElementById('ownerFilter').value = '';
        document.getElementById('projectFilter').value = '';
        updateFilters();
    }

    // Close modal when clicking outside
    document.getElementById('filterModal').addEventListener('click', function (e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
</script>

<?php require SRC_DIR . '/partials/footer.php'; ?>