<?php
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

check_auth();
$db = Database::getInstance();
$user = Auth::user();
$isAdmin = $user['role'] === 'admin';
$deptId = $user['department_id'];

$currentYear = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$yearsSql = "SELECT DISTINCT YEAR(created_at) as year FROM tasks ORDER BY year DESC";
$availableYears = $db->query($yearsSql);
if (empty($availableYears))
    $availableYears = [['year' => date('Y')]];

$params = [];

// 1. Monthly Average Completion Time (days)
$sqlAvgCompletion = "
    SELECT 
        MONTH(t.completed_at) as month,
        COUNT(t.id) as task_count,
        AVG(DATEDIFF(t.completed_at, t.created_at)) as avg_days
    FROM tasks t
    JOIN users u ON t.owner_id = u.id
    WHERE t.status_id = 5 
    AND YEAR(t.completed_at) = ?
";
$paramsAvgComp = [$currentYear];
if (!$isAdmin) {
    $sqlAvgCompletion .= " AND u.department_id = ?";
    $paramsAvgComp[] = $deptId;
}
$sqlAvgCompletion .= " GROUP BY MONTH(t.completed_at) ORDER BY month ASC";
$dataAvgComp = $db->query($sqlAvgCompletion, $paramsAvgComp);

$monthlyAvgCompletion = array_fill(1, 12, 0);
foreach ($dataAvgComp as $row) {
    $monthlyAvgCompletion[(int) $row['month']] = round((float) $row['avg_days'], 1);
}

// 1.5. Monthly Average Completion Time (days) - PER USER
$sqlUserCompletion = "
    SELECT 
        u.id as user_id,
        u.full_name as user_name,
        MONTH(t.completed_at) as month,
        AVG(DATEDIFF(t.completed_at, t.created_at)) as avg_days
    FROM tasks t
    JOIN users u ON t.owner_id = u.id
    WHERE t.status_id = 5 
    AND YEAR(t.completed_at) = ?
";
$paramsUserComp = [$currentYear];
if (!$isAdmin) {
    $sqlUserCompletion .= " AND u.department_id = ?";
    $paramsUserComp[] = $deptId;
}
$sqlUserCompletion .= " GROUP BY u.id, MONTH(t.completed_at) ORDER BY u.full_name ASC, month ASC";
$dataUserComp = $db->query($sqlUserCompletion, $paramsUserComp);

$userCompMatrix = [];
$activeUsersComp = [];
foreach ($dataUserComp as $row) {
    $uName = $row['user_name'];
    $month = (int) $row['month'];
    $val = round((float) $row['avg_days'], 1);

    if (!isset($userCompMatrix[$uName])) {
        $userCompMatrix[$uName] = array_fill(1, 12, null);
        $activeUsersComp[$uName] = true;
    }
    $userCompMatrix[$uName][$month] = $val;
}

// 2. Monthly Average Delay Time (days)
// Only counting tasks that WERE delayed (completed_at > target_completion_date)
$sqlAvgDelay = "
    SELECT 
        MONTH(t.completed_at) as month,
        COUNT(t.id) as task_count,
        AVG(DATEDIFF(t.completed_at, t.target_completion_date)) as avg_delay_days
    FROM tasks t
    JOIN users u ON t.owner_id = u.id
    WHERE t.status_id = 5 
    AND t.target_completion_date IS NOT NULL
    AND t.completed_at > t.target_completion_date
    AND YEAR(t.completed_at) = ?
";
$paramsAvgDelay = [$currentYear];
if (!$isAdmin) {
    $sqlAvgDelay .= " AND u.department_id = ?";
    $paramsAvgDelay[] = $deptId;
}
$sqlAvgDelay .= " GROUP BY MONTH(t.completed_at) ORDER BY month ASC";
$dataAvgDelay = $db->query($sqlAvgDelay, $paramsAvgDelay);

$monthlyAvgDelay = array_fill(1, 12, 0);
foreach ($dataAvgDelay as $row) {
    $monthlyAvgDelay[(int) $row['month']] = round((float) $row['avg_delay_days'], 1);
}

// 2.5. Monthly Average Delay Time (days) - PER USER
$sqlUserDelay = "
    SELECT 
        u.id as user_id,
        u.full_name as user_name,
        MONTH(t.completed_at) as month,
        AVG(DATEDIFF(t.completed_at, t.target_completion_date)) as avg_delay_days
    FROM tasks t
    JOIN users u ON t.owner_id = u.id
    WHERE t.status_id = 5 
    AND t.target_completion_date IS NOT NULL
    AND t.completed_at > t.target_completion_date
    AND YEAR(t.completed_at) = ?
";
$paramsUserDelay = [$currentYear];
if (!$isAdmin) {
    $sqlUserDelay .= " AND u.department_id = ?";
    $paramsUserDelay[] = $deptId;
}
$sqlUserDelay .= " GROUP BY u.id, MONTH(t.completed_at) ORDER BY u.full_name ASC, month ASC";
$dataUserDelay = $db->query($sqlUserDelay, $paramsUserDelay);

$userDelayMatrix = [];
$activeUsersDelay = [];
foreach ($dataUserDelay as $row) {
    $uName = $row['user_name'];
    $month = (int) $row['month'];
    $val = round((float) $row['avg_delay_days'], 1);

    if (!isset($userDelayMatrix[$uName])) {
        $userDelayMatrix[$uName] = array_fill(1, 12, null);
        $activeUsersDelay[$uName] = true;
    }
    $userDelayMatrix[$uName][$month] = $val;
}

// Ensure consistent colors for users across both charts
$allUniqueUsers = array_unique(array_merge(array_keys($activeUsersComp ?? []), array_keys($activeUsersDelay)));
$userColorMap = [];
$palette = [
    '#3b82f6',
    '#ef4444',
    '#10b981',
    '#f59e0b',
    '#8b5cf6',
    '#ec4899',
    '#06b6d4',
    '#14b8a6',
    '#f43f5e',
    '#84cc16'
];
$colorIndex = 0;
foreach ($allUniqueUsers as $uName) {
    $userColorMap[$uName] = $palette[$colorIndex % count($palette)];
    $colorIndex++;
}

// Build Chart.js user datasets
$datasetUserComp = [];
if (isset($userCompMatrix)) {
    foreach ($userCompMatrix as $uName => $monthsRaw) {
        $color = $userColorMap[$uName];
        $datasetUserComp[] = [
            'label' => $uName,
            'data' => array_values($monthsRaw),
            'borderColor' => $color,
            'backgroundColor' => $color . '20', // 20% opacity hex
            'borderWidth' => 2,
            'tension' => 0.3,
            'spanGaps' => true
        ];
    }
}

$datasetUserDelay = [];
foreach ($userDelayMatrix as $uName => $monthsRaw) {
    $color = $userColorMap[$uName];
    $datasetUserDelay[] = [
        'label' => $uName,
        'data' => array_values($monthsRaw),
        'borderColor' => $color,
        'backgroundColor' => $color . '20',
        'borderWidth' => 2,
        'tension' => 0.3,
        'spanGaps' => true
    ];
}

// 3. Bottleneck Analysis (Active Tasks: Who is waiting on whom)
// We want to see: Requester -> Owner -> Count
$sqlBottleneck = "
    SELECT 
        req.full_name as requester_name,
        req_d.name as requester_dept,
        own.full_name as owner_name,
        own_d.name as owner_dept,
        COUNT(t.id) as pending_count,
        AVG(DATEDIFF(CURDATE(), t.created_at)) as avg_waiting_days
    FROM tasks t
    JOIN users req ON t.requester_id = req.id
    JOIN departments req_d ON req.department_id = req_d.id
    JOIN users own ON t.owner_id = own.id
    JOIN departments own_d ON own.department_id = own_d.id
    WHERE t.status_id NOT IN (5, 6)
";
$paramsBottleneck = [];
if (!$isAdmin) {
    // Show bottlenecks involving this department (either they are requesting or owning)
    $sqlBottleneck .= " AND (req.department_id = ? OR own.department_id = ?)";
    $paramsBottleneck[] = $deptId;
    $paramsBottleneck[] = $deptId;
}
$sqlBottleneck .= " GROUP BY t.requester_id, t.owner_id ORDER BY pending_count DESC, avg_waiting_days DESC";
$bottleneckData = $db->query($sqlBottleneck, $paramsBottleneck);

// 4. Overdue Tasks (Hedef Tarihi Geçmiş Açık İşler)
$sqlOverdue = "
    SELECT 
        t.title as task_title,
        req.full_name as requester_name,
        req_d.name as requester_dept,
        own.full_name as owner_name,
        own_d.name as owner_dept,
        t.target_completion_date,
        DATEDIFF(CURDATE(), t.target_completion_date) as days_overdue
    FROM tasks t
    JOIN users req ON t.requester_id = req.id
    JOIN departments req_d ON req.department_id = req_d.id
    JOIN users own ON t.owner_id = own.id
    JOIN departments own_d ON own.department_id = own_d.id
    WHERE t.status_id NOT IN (5, 6)
    AND t.target_completion_date IS NOT NULL
    AND t.target_completion_date < CURDATE()
";
$paramsOverdue = [];
if (!$isAdmin) {
    $sqlOverdue .= " AND (req.department_id = ? OR own.department_id = ?)";
    $paramsOverdue[] = $deptId;
    $paramsOverdue[] = $deptId;
}
$sqlOverdue .= " ORDER BY days_overdue DESC";
$overdueData = $db->query($sqlOverdue, $paramsOverdue);

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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="page-header">
    <div>
        <h1 class="page-title">Performans Raporları</h1>
        <span style="color: var(--text-muted);">
            <?= $isAdmin ? 'Tüm Bölümler (Genel Görünüm)' : escape($user['department_name'] ?? 'Bölümünüz') ?>
        </span>
    </div>

    <div class="filter-bar">
        <form method="GET" style="display: flex; gap: 10px; align-items: center;">
            <label for="year" style="color: var(--text-muted); font-size: 0.9rem;">Yıl:</label>
            <select name="year" id="year" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <?php foreach ($availableYears as $y): ?>
                    <option value="<?= $y['year'] ?>" <?= $y['year'] == $currentYear ? 'selected' : '' ?>>
                        <?= $y['year'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<!-- TOP Row: General / Department Averages -->
<div class="charts-grid"
    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 30px;">

    <!-- Average Completion Time Chart -->
    <div class="glass glass-card">
        <h3 class="card-title">Genel Ortalama Kapanış Süresi (Gün)</h3>
        <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: -10px; margin-bottom: 15px;">
            <?= $isAdmin ? 'Tüm şirketin' : 'Bölümünüzün' ?> tamamlanan işleri kapatma süresi ortalaması.
        </p>
        <div style="height: 300px; position: relative;">
            <canvas id="completionChart"></canvas>
        </div>
    </div>

    <!-- Average Delay Time Chart -->
    <div class="glass glass-card">
        <h3 class="card-title">Genel Ortalama Gecikme Süresi (Gün)</h3>
        <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: -10px; margin-bottom: 15px;">
            <?= $isAdmin ? 'Tüm şirketin' : 'Bölümünüzün' ?> hedeften saptığı ortalama gecikme süresi.
        </p>
        <div style="height: 300px; position: relative;">
            <canvas id="delayChart"></canvas>
        </div>
    </div>

</div>

<!-- BOTTOM Row: User Level Breakdowns -->
<div class="charts-grid"
    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 30px;">

    <!-- User Level Completion Chart -->
    <div class="glass glass-card">
        <h3 class="card-title">Kişi Bazlı Ortalama Kapanış (Gün)</h3>
        <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: -10px; margin-bottom: 15px;">
            Hangi personel işleri ortalama kaç günde kapatıyor?
        </p>
        <div style="height: 300px; position: relative;">
            <canvas id="userCompChart"></canvas>
        </div>
    </div>

    <!-- User Level Delay Chart -->
    <div class="glass glass-card">
        <h3 class="card-title">Kişi Bazlı Ortalama Gecikme (Gün)</h3>
        <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: -10px; margin-bottom: 15px;">
            Hangi personel işleri ortalama kaç gün geciktiriyor?
        </p>
        <div style="height: 300px; position: relative;">
            <canvas id="userDelayChart"></canvas>
        </div>
    </div>

</div>

<!-- Bottleneck Table -->
<div class="glass glass-card">
    <div class="card-header">
        <h3 class="card-title">Bekleyen İş Analizi (Kimde, Kimin İşi Bekliyor?)</h3>
    </div>
    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0 20px 15px 20px;">
        Hala açık durumda olan görevlerin talep eden ve sorumlu kişi bazında dağılımı.
    </p>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Talep Eden</th>
                    <th>Talep Eden Bölüm</th>
                    <th></th>
                    <th>Sorumlu (İş Kimde?)</th>
                    <th>Sorumlu Bölüm</th>
                    <th style="text-align: center;">Bekleyen İş Sayısı</th>
                    <th style="text-align: center;">Ort. Bekleme Süresi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bottleneckData as $row): ?>
                    <tr>
                        <td style="font-weight: 500; color: #3b82f6;">
                            <?= escape($row['requester_name']) ?>
                        </td>
                        <td style="font-size: 0.85rem; color: var(--text-muted);">
                            <?= escape($row['requester_dept']) ?>
                        </td>
                        <td style="color: var(--text-muted); text-align: center;">➡️</td>
                        <td style="font-weight: 500; color: #f59e0b;">
                            <?= escape($row['owner_name']) ?>
                        </td>
                        <td style="font-size: 0.85rem; color: var(--text-muted);">
                            <?= escape($row['owner_dept']) ?>
                        </td>
                        <td style="text-align: center;">
                            <span class="status-badge"
                                style="background: rgba(239, 68, 68, 0.1); color: #ef4444; font-size: 0.9rem;">
                                <?= $row['pending_count'] ?> adet
                            </span>
                        </td>
                        <td
                            style="text-align: center; color: <?= $row['avg_waiting_days'] > 7 ? '#ef4444' : 'var(--text-muted)' ?>;">
                            <?= round($row['avg_waiting_days'], 1) ?> gün
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($bottleneckData)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            Kayıtlı aktif bir iş tıkanıklığı bulunmuyor. 🎉
                        </td>
                        </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Overdue Tasks Table -->
<div class="glass glass-card" style="margin-top: 30px;">
    <div class="card-header">
        <h3 class="card-title" style="color: #ef4444;">Hedef Tarihi Geçmiş Acil İşler</h3>
    </div>
    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0 20px 15px 20px;">
        Henüz kapatılmamış ancak hedef bitiş tarihi bugünden daha eski olan, gecikmeye düşmüş açık işler.
    </p>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Görevin Adı</th>
                    <th>Talep Eden</th>
                    <th></th>
                    <th>Sorumlu (İş Kimde?)</th>
                    <th>Hedef Tarih</th>
                    <th style="text-align: center;">Gecikme (Gün)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($overdueData as $row): ?>
                        <tr>
                            <td style="font-weight: 500; color: var(--text-color);">
                                <?= escape($row['task_title']) ?>
                            </td>
                            <td style="font-size: 0.9rem; color: #3b82f6;">
                                <?= escape($row['requester_name']) ?><br>
                                <span style="font-size: 0.8rem; color: var(--text-muted);"><?= escape($row['requester_dept']) ?></span>
                            </td>
                            <td style="color: var(--text-muted); text-align: center;">➡️</td>
                            <td style="font-size: 0.9rem; color: #f59e0b;">
                                <?= escape($row['owner_name']) ?><br>
                                <span style="font-size: 0.8rem; color: var(--text-muted);"><?= escape($row['owner_dept']) ?></span>
                            </td>
                            <td style="font-size: 0.9rem; color: var(--text-muted);">
                                <?= date('d.m.Y', strtotime($row['target_completion_date'])) ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="status-badge" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; font-size: 0.9rem; font-weight: bold;">
                                    <?= $row['days_overdue'] ?> gün
                                </span>
                            </td>
                        </tr>
                <?php endforeach; ?>

                <?php if (empty($overdueData)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                Harika! Hedef tarihi geçmiş ve hala açık olan bir iş bulunmuyor. 🎉
                            </td>
                        </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Theme colors matching dashboard
    const isDark = document.body.getAttribute('data-theme') !== 'light';
    const textColor = isDark ? '#94a3b8' : '#64748b';
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';

    const monthLabels = <?= json_encode(array_values($monthNames)) ?>;

    // Completion Chart
    const ctxComp = document.getElementById('completionChart').getContext('2d');
    new Chart(ctxComp, {
        type: 'line',
        data: {
            labels: monthLabels,
            datasets: [{
                label: 'Ortalama Kapanış (Gün)',
                data: <?= json_encode(array_values($monthlyAvgCompletion)) ?>,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3,
                pointBackgroundColor: '#10b981'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: gridColor },
                    ticks: { color: textColor, precision: 0 }
                },
                x: {
                    grid: { color: gridColor },
                    ticks: { color: textColor }
                }
            },
            plugins: {
                legend: { labels: { color: textColor } }
            }
        }
    });

    // Delay Chart
    const ctxDelay = document.getElementById('delayChart').getContext('2d');
    new Chart(ctxDelay, {
        type: 'bar',
        data: {
            labels: monthLabels,
            datasets: [{
                label: 'Ortalama Gecikme (Gün)',
                data: <?= json_encode(array_values($monthlyAvgDelay)) ?>,
                backgroundColor: 'rgba(239, 68, 68, 0.7)',
                borderColor: '#ef4444',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: gridColor },
                    ticks: { color: textColor, precision: 0 }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: textColor }
                }
            },
            plugins: {
                legend: { labels: { color: textColor } }
            }
        }
    });

    // --- USER LEVEL CHARTS ---

    const userCompData = <?= json_encode($datasetUserComp) ?>;
    const userDelayData = <?= json_encode($datasetUserDelay) ?>;

    const commonLineOptions = {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: gridColor },
                ticks: { color: textColor, precision: 0 }
            },
            x: {
                grid: { color: gridColor },
                ticks: { color: textColor }
            }
        },
        plugins: {
            legend: {
                position: 'right',
                labels: { color: textColor, boxWidth: 12 }
            }
        }
    };

    // User Level Completion Time
    const ctxUserComp = document.getElementById('userCompChart').getContext('2d');
    new Chart(ctxUserComp, {
        type: 'line',
        data: {
            labels: monthLabels,
            datasets: userCompData
        },
        options: commonLineOptions
    });

    // User Level Delay Time
    const ctxUserDelay = document.getElementById('userDelayChart').getContext('2d');
    new Chart(ctxUserDelay, {
        type: 'line',
        data: {
            labels: monthLabels,
            datasets: userDelayData
        },
        options: commonLineOptions
    });
</script>

<?php require SRC_DIR . '/partials/footer.php'; ?>