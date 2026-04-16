<?php
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

check_auth();
$db = Database::getInstance();
$user = Auth::user();

// Fetch Projects for Dropdown (with permission check)
$projectsSql = "SELECT id, project_code, name FROM projects";
$params = [];

if ($user['role'] !== 'admin') {
    $deptIds = [$user['department_id']];
    if (!empty($user['managed_department_ids'])) {
        $deptIds = array_merge($deptIds, $user['managed_department_ids']);
    }
    $placeholders = implode(',', array_fill(0, count($deptIds), '?'));
    $projectsSql .= " WHERE department_id IN ($placeholders) OR department_id IS NULL";
    $params = $deptIds;
}
$projectsSql .= " ORDER BY created_at DESC";
$projects = $db->query($projectsSql, $params);

// Selected Project
$selectedProjectId = $_GET['id'] ?? ($projects[0]['id'] ?? null);
$project = null;

if ($selectedProjectId) {
    // Verify access again for the selected IP
    $accessCheckSql = "SELECT * FROM projects WHERE id = ?";
    $accessCheckParams = [$selectedProjectId];

    if ($user['role'] !== 'admin') {
        // Re-use logic or just check if it was in the allowed list above. 
        // For stricter check:
        $deptIds = [$user['department_id']];
        if (!empty($user['managed_department_ids'])) {
            $deptIds = array_merge($deptIds, $user['managed_department_ids']);
        }
        $placeholders = implode(',', array_fill(0, count($deptIds), '?'));
        $accessCheckSql .= " AND (department_id IN ($placeholders) OR department_id IS NULL)";
        $accessCheckParams = array_merge([$selectedProjectId], $deptIds);
    }

    $project = $db->fetchOne($accessCheckSql, $accessCheckParams);
}

// Calculate Stats if project exists
$stats = [
    'total' => 0,
    'completed' => 0,
    'in_progress' => 0,
    'overdue' => 0,
    'completion_rate' => 0
];

$tasks = [];
$tasks = [];
$statusData = [];
$assigneeData = [];
$tasksByStep = [];
$steps = [];

if ($project) {
    // Fetch all tasks for this project
    $tasks = $db->query("
        SELECT t.*, s.name as status_name, s.color as status_color, s.kanban_column,
               u.full_name as owner_name
        FROM tasks t
        JOIN statuses s ON t.status_id = s.id
        LEFT JOIN users u ON t.owner_id = u.id
        WHERE t.project_id = ?
    ", [$project['id']]);

    foreach ($tasks as $t) {
        $stats['total']++;

        if ($t['kanban_column'] === 'done') {
            $stats['completed']++;
        } else {
            $stats['in_progress']++;
            if ($t['target_completion_date'] && strtotime($t['target_completion_date']) < time()) {
                $stats['overdue']++;
            }
        }

        // Data for Status Chart
        if (!isset($statusData[$t['status_name']])) {
            $statusData[$t['status_name']] = ['count' => 0, 'color' => $t['status_color']];
        }
        $statusData[$t['status_name']]['count']++;

        // Data for Assignee Chart
        $owner = $t['owner_name'] ?: 'Atanmamış';
        if (!isset($assigneeData[$owner])) {
            $assigneeData[$owner] = 0;
        }
        $assigneeData[$owner]++;

        // Group by Step
        $stepId = $t['project_step_id'] ?: 'uncategorized';
        $tasksByStep[$stepId][] = $t;
    }

    // Fetch Steps
    $steps = $db->query("SELECT * FROM project_steps WHERE project_id = ? ORDER BY order_index ASC", [$project['id']]);

    if ($stats['total'] > 0) {
        $stats['completion_rate'] = round(($stats['completed'] / $stats['total']) * 100);
    }

    // Fetch All Comments for Project Tasks
    $commentsRaw = $db->query("
        SELECT c.*, u.full_name, u.username 
        FROM task_comments c 
        JOIN tasks t ON c.task_id = t.id 
        JOIN users u ON c.user_id = u.id 
        WHERE t.project_id = ? 
        ORDER BY c.created_at ASC
    ", [$project['id']]);

    $commentsByTask = [];
    foreach ($commentsRaw as $c) {
        $commentsByTask[$c['task_id']][] = $c;
    }
}


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
        'project_code' => $project['project_code'] ?? '',
        'project_name' => $project['name'] ?? ''
    ];
}

// Sort by Task Title
usort($ganttTasks, function ($a, $b) {
    return strcasecmp($a['title'], $b['title']);
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
        'label' => date('F Y', $curr),
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

$layout = 'fluid';

require SRC_DIR . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">📊 Proje Analiz Panosu</h1>
    </div>

    <!-- Project Selector -->
    <div style="min-width: 300px;">
        <form method="GET">
            <select name="id" class="form-select" onchange="this.form.submit()" style="padding: 10px; font-size: 1rem;">
                <?php if (empty($projects)): ?>
                    <option value="">Erişilebilir proje bulunmuyor</option>
                <?php else: ?>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $p['id'] == $selectedProjectId ? 'selected' : '' ?>>
                            <?= escape($p['project_code']) ?> -
                            <?= escape($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </form>
    </div>
</div>

<?php if (!$project): ?>
    <div class="glass glass-card" style="text-align: center; padding: 40px; color: var(--text-muted);">
        Lütfen analiz etmek için bir proje seçin.
    </div>
<?php else: ?>

    <!-- KPI Cards -->
    <div class="dashboard-grid"
        style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
        <div class="glass glass-card">
            <div class="stat-title">Toplam Görev</div>
            <div class="stat-value">
                <?= $stats['total'] ?>
            </div>
        </div>

        <div class="glass glass-card">
            <div class="stat-title">Tamamlanan</div>
            <div class="stat-value" style="color: #10b981;">
                <?= $stats['completed'] ?>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= $stats['completion_rate'] ?>%; background: #10b981;"></div>
            </div>
            <div style="font-size: 0.8rem; text-align: right; margin-top: 5px;">%
                <?= $stats['completion_rate'] ?>
            </div>
        </div>

        <div class="glass glass-card">
            <div class="stat-title">Devam Eden</div>
            <div class="stat-value" style="color: #3b82f6;">
                <?= $stats['in_progress'] ?>
            </div>
        </div>

        <div class="glass glass-card">
            <div class="stat-title">Geciken</div>
            <div class="stat-value" style="color: #ef4444;">
                <?= $stats['overdue'] ?>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
        <!-- Status Chart -->
        <div class="glass glass-card">
            <h3 style="margin-top: 0; margin-bottom: 20px;">Görev Durum Dağılımı</h3>
            <div style="height: 300px; position: relative;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <!-- Assignee Chart -->
        <div class="glass glass-card">
            <h3 style="margin-top: 0; margin-bottom: 20px;">Kişi Bazlı Görev Dağılımı</h3>
            <div style="height: 300px; position: relative;">
                <canvas id="assigneeChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Gantt Chart Row -->
    <div class="glass glass-card"
        style="padding: 0; overflow: hidden; display: flex; flex-direction: column; margin-bottom: 30px;">
        <h3 style="margin: 20px 20px 10px 20px;">Proje Zaman Çizelgesi (Gantt)</h3>

        <!-- Gantt Header (Months) -->
        <div class="gantt-header"
            style="display: flex; border-bottom: 1px solid var(--glass-border); background: rgba(255,255,255,0.05);">

            <!-- Sidebar HeaderSplit -->
            <div style="display: flex; min-width: 350px; border-right: 1px solid var(--glass-border);">
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
        <div class="gantt-body" style="overflow-y: auto; max-height: 500px;">
            <?php foreach ($ganttTasks as $task): ?>
                <div class="gantt-row" style="display: flex; border-bottom: 1px solid var(--glass-border);">
                    <!-- Task Info Sidebar -->
                    <div class="gantt-sidebar-item"
                        style="display: flex; min-width: 350px; border-right: 1px solid var(--glass-border); background: rgba(255,255,255,0.02);">

                        <!-- Task Column -->
                        <div style="flex: 1; padding: 15px; overflow: hidden;">
                            <div
                                style="font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 320px;">
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
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($ganttTasks)): ?>
                <div style="padding: 20px; text-align: center; color: var(--text-muted);">
                    Görüntülenecek zaman çizelgesi verisi yok.
                </div>
            <?php endif; ?>
        </div>


    </div>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Status Chart
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_keys($statusData)) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($statusData, 'count')) ?>,
                    backgroundColor: <?= json_encode(array_column($statusData, 'color')) ?>,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#94a3b8' } }
                }
            }
        });

        // Assignee Chart
        new Chart(document.getElementById('assigneeChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($assigneeData)) ?>,
                datasets: [{
                    label: 'Görev Sayısı',
                    data: <?= json_encode(array_values($assigneeData)) ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderColor: '#3b82f6',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.1)' }, ticks: { color: '#94a3b8' } },
                    x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    </script>

    <!-- Project Steps List -->
    <div class="glass glass-card" style="padding: 0; overflow: hidden;">
        <div
            style="padding: 20px; border-bottom: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;">Proje Adımları ve Görevler</h3>
            <label
                style="font-size: 0.9rem; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" id="showDetails" onchange="toggleTaskDetails()">
                Detayları Göster
            </label>
        </div>

        <div style="padding: 20px;">
            <?php if (empty($steps) && empty($tasksByStep['uncategorized'])): ?>
                <div class="text-muted text-center">Bu projede henüz adım veya görev bulunmuyor.</div>
            <?php endif; ?>

            <!-- Defined Steps -->
            <?php foreach ($steps as $step): ?>
                <div style="margin-bottom: 30px;">
                    <h4 style="color: var(--primary); margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                        <span
                            style="background: rgba(79, 70, 229, 0.1); padding: 4px 8px; border-radius: 4px; font-size: 0.8rem;">#<?= $step['order_index'] ?></span>
                        <?= escape($step['name']) ?>
                    </h4>

                    <?php if (isset($tasksByStep[$step['id']])): ?>
                        <div class="glass glass-card" style="padding: 15px;">
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <?php foreach ($tasksByStep[$step['id']] as $task): ?>
                                    <div class="task-card-mini" style="
                                            width: 100%;
                                            background: rgba(255,255,255,0.05);
                                            border: 1px solid var(--glass-border);
                                            border-radius: 8px;
                                            padding: 12px 15px;
                                            transition: transform 0.2s;
                                            cursor: pointer;
                                            " onclick="window.location='task_detail.php?id=<?= $task['id'] ?>'"
                                        onmouseover="this.style.background='rgba(255,255,255,0.08)'"
                                        onmouseout="this.style.background='rgba(255,255,255,0.05)'">

                                        <!-- Main Row -->
                                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 15px;">

                                            <!-- Left: ID & Title -->
                                            <div style="display: flex; align-items: center; gap: 15px; flex: 1; overflow: hidden;">
                                                <span class="text-muted"
                                                    style="font-size: 0.85rem; min-width: 40px;">#<?= $task['id'] ?></span>

                                                <div style="font-weight: 500; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 300px;"
                                                    title="<?= escape($task['title']) ?>">
                                                    <?= escape($task['title']) ?>
                                                </div>

                                                <!-- Middle: Hashtag & Date -->
                                                <div style="display: flex; align-items: center; gap: 15px; margin-left: 20px;">
                                                    <?php if (!empty($task['hashtag'])): ?>
                                                        <span
                                                            style="font-size: 0.8rem; background: rgba(255,255,255,0.1); padding: 2px 6px; border-radius: 4px; color: var(--text-muted);">
                                                            #<?= escape($task['hashtag']) ?>
                                                        </span>
                                                    <?php endif; ?>

                                                    <?php if (!empty($task['target_completion_date'])): ?>
                                                        <span
                                                            style="font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; gap: 4px;"
                                                            title="Hedef Tarih">
                                                            📅 <?= date('d.m.Y', strtotime($task['target_completion_date'])) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Right: Owner & Status -->
                                            <div style="display: flex; align-items: center; gap: 20px; flex-shrink: 0;">
                                                <div
                                                    style="display: flex; align-items: center; gap: 5px; font-size: 0.85rem; color: var(--text-muted);">
                                                    <span>👤 <?= explode(' ', $task['owner_name'])[0] ?></span>
                                                </div>

                                                <span class="status-badge"
                                                    style="font-size: 0.75rem; padding: 4px 10px; border-radius: 4px; background: <?= $task['status_color'] ?>20; color: <?= $task['status_color'] ?>; min-width: 80px; text-align: center;">
                                                    <?= escape($task['status_name']) ?>
                                                </span>
                                            </div>
                                        </div>

                                        <?php if (isset($commentsByTask[$task['id']])): ?>
                                            <div class="task-comments"
                                                style="display: none; margin-top: 10px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 10px; padding-left: 55px;">
                                                <?php foreach ($commentsByTask[$task['id']] as $comment): ?>
                                                    <div
                                                        style="font-size: 0.85rem; margin-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 5px;">
                                                        <div style="display: flex; gap: 10px; align-items: baseline;">
                                                            <span
                                                                style="color: var(--primary); font-weight: 500;"><?= escape($comment['full_name']) ?></span>
                                                            <span
                                                                style="color: var(--text-muted); font-size: 0.75rem;"><?= date('d.m.Y H:i', strtotime($comment['created_at'])) ?></span>
                                                        </div>
                                                        <div style="color: var(--text-main); margin-top: 2px; padding-left: 0;">
                                                            <?= nl2br(escape($comment['comment'])) ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-muted" style="font-size: 0.9rem; padding-left: 10px; font-style: italic;">Bu adımda
                            görev
                            yok.</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <!-- Uncategorized Tasks -->
            <?php if (isset($tasksByStep['uncategorized']) && !empty($tasksByStep['uncategorized'])): ?>
                <div style="margin-bottom: 20px;">
                    <h4 style="color: var(--text-muted); margin-bottom: 15px;">Diğer Görevler (Adımsız)</h4>
                    <div class="glass glass-card" style="padding: 15px;">
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <?php foreach ($tasksByStep['uncategorized'] as $task): ?>
                                <div class="task-card-mini" style="
                                        width: 100%;
                                        background: rgba(255,255,255,0.05);
                                        border: 1px solid var(--glass-border);
                                        border-radius: 8px;
                                        padding: 12px 15px;
                                        transition: transform 0.2s;
                                        cursor: pointer;
                                        " onclick="window.location='task_detail.php?id=<?= $task['id'] ?>'"
                                    onmouseover="this.style.background='rgba(255,255,255,0.08)'"
                                    onmouseout="this.style.background='rgba(255,255,255,0.05)'">

                                    <!-- Main Row -->
                                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 15px;">

                                        <!-- Left: ID & Title -->
                                        <div style="display: flex; align-items: center; gap: 15px; flex: 1; overflow: hidden;">
                                            <span class="text-muted"
                                                style="font-size: 0.85rem; min-width: 40px;">#<?= $task['id'] ?></span>

                                            <div style="font-weight: 500; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 300px;"
                                                title="<?= escape($task['title']) ?>">
                                                <?= escape($task['title']) ?>
                                            </div>

                                            <!-- Middle: Hashtag & Date -->
                                            <div style="display: flex; align-items: center; gap: 15px; margin-left: 20px;">
                                                <?php if (!empty($task['hashtag'])): ?>
                                                    <span
                                                        style="font-size: 0.8rem; background: rgba(255,255,255,0.1); padding: 2px 6px; border-radius: 4px; color: var(--text-muted);">
                                                        #<?= escape($task['hashtag']) ?>
                                                    </span>
                                                <?php endif; ?>

                                                <?php if (!empty($task['target_completion_date'])): ?>
                                                    <span
                                                        style="font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; gap: 4px;"
                                                        title="Hedef Tarih">
                                                        📅 <?= date('d.m.Y', strtotime($task['target_completion_date'])) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Right: Owner & Status -->
                                        <div style="display: flex; align-items: center; gap: 20px; flex-shrink: 0;">
                                            <div
                                                style="display: flex; align-items: center; gap: 5px; font-size: 0.85rem; color: var(--text-muted);">
                                                <span>👤 <?= explode(' ', $task['owner_name'])[0] ?></span>
                                            </div>

                                            <span class="status-badge"
                                                style="font-size: 0.75rem; padding: 4px 10px; border-radius: 4px; background: <?= $task['status_color'] ?>20; color: <?= $task['status_color'] ?>; min-width: 80px; text-align: center;">
                                                <?= escape($task['status_name']) ?>
                                            </span>
                                        </div>
                                    </div>

                                    <?php if (isset($commentsByTask[$task['id']])): ?>
                                        <div class="task-comments"
                                            style="display: none; margin-top: 10px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 10px; padding-left: 55px;">
                                            <?php foreach ($commentsByTask[$task['id']] as $comment): ?>
                                                <div
                                                    style="font-size: 0.85rem; margin-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 5px;">
                                                    <div style="display: flex; gap: 10px; align-items: baseline;">
                                                        <span
                                                            style="color: var(--primary); font-weight: 500;"><?= escape($comment['full_name']) ?></span>
                                                        <span
                                                            style="color: var(--text-muted); font-size: 0.75rem;"><?= date('d.m.Y H:i', strtotime($comment['created_at'])) ?></span>
                                                    </div>
                                                    <div style="color: var(--text-main); margin-top: 2px; padding-left: 0;">
                                                        <?= nl2br(escape($comment['comment'])) ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>

    <?php require SRC_DIR . '/partials/footer.php'; ?>
    <script>
        function toggleTaskDetails() {
            const checkbox = document.getElementById('showDetails');
            const comments = document.querySelectorAll('.task-comments');

            comments.forEach(el => {
                el.style.display = checkbox.checked ? 'block' : 'none';
            });
        }
    </script>