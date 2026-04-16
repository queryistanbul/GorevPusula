<?php
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

check_auth();
$db = Database::getInstance();
$user = Auth::user();

// Check for "my tasks" filter
$myTasksOnly = isset($_GET['my']) && $_GET['my'] == '1';
// Check for "details" filter
$showDetails = isset($_GET['details']) && $_GET['details'] == '1';
// Check for "overdue" filter
$overdueOnly = isset($_GET['overdue']) && $_GET['overdue'] == '1';
// Check for "show completed" filter
$showCompleted = isset($_GET['completed']) && $_GET['completed'] == '1';
// Check for "owner" filter
$filterOwnerId = !empty($_GET['owner']) ? $_GET['owner'] : null;
// Check for "project" filter
$filterProjectId = !empty($_GET['project']) ? $_GET['project'] : null;

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

// Fetch User Department Name for Header
$userDeptName = '';
if ($user) {
    $dept = $db->fetchOne("SELECT name FROM departments WHERE id = ?", [$user['department_id']]);
    $userDeptName = $dept['name'] ?? '';
}

// Fetch users for the owner filter
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

// Fetch Tasks, ordered by target date
$tasks = get_tasks($db, $user, $myTasksOnly, $overdueOnly, $showCompleted, "t.target_completion_date IS NULL ASC, t.target_completion_date ASC, s.order_index ASC, t.created_at DESC", $filterOwnerId, $filterProjectId);

// Always fetch comments and checklists for PDF/Print access
$taskComments = [];
$taskChecklists = [];
if (!empty($tasks)) {
    $taskIds = array_column($tasks, 'id');
    $placeholders = implode(',', array_fill(0, count($taskIds), '?'));

    // Fetch Comments
    $sqlComments = "SELECT tc.*, u.full_name 
            FROM task_comments tc 
            LEFT JOIN users u ON tc.user_id = u.id 
            WHERE tc.task_id IN ($placeholders) 
            ORDER BY tc.created_at ASC";

    $comments = $db->query($sqlComments, $taskIds);

    foreach ($comments as $c) {
        $taskComments[$c['task_id']][] = $c;
    }

    // Fetch Checklists
    $sqlChecklists = "SELECT * FROM task_checklist_items 
                      WHERE task_id IN ($placeholders) 
                      ORDER BY order_index ASC";

    $checklists = $db->query($sqlChecklists, $taskIds);

    foreach ($checklists as $cl) {
        $taskChecklists[$cl['task_id']][] = $cl;
    }
}

$layout = 'fluid';
require SRC_DIR . '/partials/header.php';
?>

<div class="page-header">
    <div style="display: flex; align-items: center; gap: 15px;">
        <h1 class="page-title">Görevlerim (Liste)</h1>
        <a href="#" onclick="window.print(); return false;" class="btn btn-sm btn-glass">🖨️ PDF / Yazdır</a>
    </div>

    <?php
    // Calculate active filters count for the button badge
    $activeFilterCount = 0;
    if ($showDetails)
        $activeFilterCount++;
    if ($showCompleted)
        $activeFilterCount++;
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
                class="btn btn-sm btn-primary">Liste</a>
            <a href="gantt.php?<?= http_build_query(array_merge($_GET, ['view' => 'gantt'])) ?>"
                class="btn btn-sm btn-glass">Gantt</a>
        </div>

        <a href="create_task.php" class="btn btn-primary">+ Yeni Görev</a>
    </div>
</div>

<div class="glass glass-card">
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Başlık</th>
                <th>Durum</th>
                <th>Öncelik</th>
                <th>Sorumlu</th>
                <th>Sorumlu Dept.</th>
                <th>Talep Eden</th>
                <th>Talep Eden Dept.</th>
                <th>Hedef Tarih</th>
                <th class="text-right">Oluşturulma</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tasks as $i => $task): ?>
                <tr style="<?= $showDetails ? 'border-bottom: none;' : '' ?>">
                    <td class="text-muted">#<?= $task['id'] ?></td>
                    <td>
                        <a href="#" onclick="openTaskPanel(<?= $task['id'] ?>, event)" class="link-white">
                            <?= escape($task['title']) ?>
                        </a>
                        <?php if (!empty($task['project_code'])): ?>
                            <div style="font-size: 0.75rem; color: var(--primary); margin-top: 4px; font-weight: 500;">
                                🚀 <?= escape($task['project_code']) ?> - <?= escape($task['project_name']) ?>
                                <?php if (!empty($task['step_name'])): ?>
                                    <span style="color: var(--text-muted);">/ <?= escape($task['step_name']) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge"
                            style="font-size: 0.8rem; padding: 4px 8px; background: <?= $task['status_color'] ?>20; color: <?= $task['status_color'] ?>; border: 1px solid <?= $task['status_color'] ?>40;">
                            <?= escape($task['status_name']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge"
                            style="font-size: 0.8rem; padding: 4px 8px; background: <?= $task['priority_color'] ?>20; color: <?= $task['priority_color'] ?>; border: 1px solid <?= $task['priority_color'] ?>40;">
                            <?= escape($task['priority_name'] ?? 'Normal') ?>
                        </span>
                    </td>
                    <td><?= escape($task['owner_name']) ?></td>
                    <td class="text-muted"><?= escape($task['owner_department']) ?></td>
                    <td><?= escape($task['requester_name']) ?></td>
                    <td class="text-muted"><?= escape($task['requester_department']) ?></td>
                    <td>
                        <?php
                        $isOverdue = $task['target_completion_date'] &&
                            $task['target_completion_date'] < date('Y-m-d') &&
                            !in_array($task['status_id'], [5, 6]);
                        ?>
                        <?php if ($isOverdue): ?>
                            <span style="color: #ef4444; font-weight: bold;" title="Gecikmiş!">
                                ⚠️ <?= date('d.m.Y', strtotime($task['target_completion_date'])) ?>
                            </span>
                        <?php else: ?>
                            <?= $task['target_completion_date'] ? date('d.m.Y', strtotime($task['target_completion_date'])) : '-' ?>
                        <?php endif; ?>
                    </td>
                    <td class="text-right text-muted"
                        style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
                        <span><?= date('d.m.Y', strtotime($task['created_at'])) ?></span>
                        <input type="checkbox" onchange="toggleTaskStatus(this, <?= $task['id'] ?>)" title="Tamamlandı"
                            style="width: 18px; height: 18px;" <?= $task['status_id'] == 5 ? 'checked' : '' ?>>
                    </td>
                </tr>
                <?php if ($showDetails): ?>
                    <tr style="background: rgba(255, 255, 255, 0.03);">
                        <td colspan="10"
                            style="padding: 15px 20px; border-top: 1px dashed var(--glass-border); text-align: left;">
                            <div style="margin-bottom: 10px;">
                                <strong>Açıklama:</strong><br>
                                <div style="white-space: pre-wrap; color: var(--text-muted); margin-top: 5px;">
                                    <?= escape($task['description']) ?>
                                </div>
                            </div>
                            <?php if (isset($taskChecklists[$task['id']])): ?>
                                <div style="margin-top: 15px; margin-left: 0; padding-left: 0;">
                                    <strong>Kontrol Listesi:</strong>
                                    <div style="margin: 8px 0 0 0; padding: 0;">
                                        <?php foreach ($taskChecklists[$task['id']] as $item): ?>
                                            <div
                                                style="display: flex; align-items: flex-start; gap: 10px; font-size: 0.9rem; margin: 0 0 8px 0; padding: 0;">
                                                <input type="checkbox" disabled <?= $item['is_completed'] ? 'checked' : '' ?>
                                                    style="cursor: default; margin: 0; padding: 0; flex-shrink: 0; width: auto;">
                                                <span class="<?= $item['is_completed'] ? 'text-muted' : '' ?>"
                                                    style="<?= $item['is_completed'] ? 'text-decoration: line-through;' : '' ?>">
                                                    <?= escape($item['item_text']) ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($taskComments[$task['id']])): ?>
                                <div style="margin-top: 15px;">
                                    <strong>Yorumlar:</strong>
                                    <ul style="list-style: none; padding: 0; margin-top: 5px;">
                                        <?php foreach ($taskComments[$task['id']] as $comment): ?>
                                            <li style="margin-bottom: 8px; font-size: 0.9rem;">
                                                <span
                                                    style="color: var(--primary); font-weight: 500;"><?= escape($comment['full_name']) ?></span>
                                                <span style="color: var(--text-muted); font-size: 0.75rem; margin-left: 5px;">
                                                    (<?= date('d.m.Y H:i', strtotime($comment['created_at'])) ?>)
                                                </span>:
                                                <?= escape($comment['comment']) ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if (empty($tasks)): ?>
                <tr>
                    <td colspan="10" style="padding: 20px; text-align: center; color: var(--text-muted);">
                        Görev bulunamadı.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>




<style>
    /* Print Area - Hidden on screen */
    #print-area {
        display: none;
    }

    @media print {

        /* Whitelist approach: hide UI, show content */
        .sidebar,
        .top-header,
        .filter-bar,
        footer,
        .btn,
        .no-print,
        header,
        .app-layout {
            display: none !important;
        }

        /* Essential resets for the print area at root level */
        #print-area {
            display: block !important;
            width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
            background: white !important;
            color: black !important;
        }

        html,
        body {
            height: auto !important;
            overflow: visible !important;
            background: white !important;
        }

        #print-area {
            display: block !important;
            width: 100%;
            background: white;
            color: black;
            padding: 0;
            margin: 0;
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        @page {
            size: A4 portrait;
            margin: 10mm 10mm 15mm 10mm;
            /* Bottom margin for the raw number */
        }

        /* Header Table */
        .print-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            border: 1px solid #000;
        }

        .print-header td {
            border: 1px solid #000;
            padding: 10px;
            vertical-align: middle;
        }

        .ph-center {
            text-align: center;
            background-color: #f8f9fa;
            padding: 15px !important;
        }

        .ph-dept {
            font-size: 24px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 8px;
            letter-spacing: 2px;
        }

        .ph-title {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            letter-spacing: 2px;
        }

        /* Content Table */
        .print-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }

        .print-table th,
        .print-table td {
            border: 1px solid #000;
            padding: 8px;
            vertical-align: top;
        }

        .print-table th {
            background-color: #d9d9d9;
            font-weight: bold;
            text-align: center;
            padding: 10px 5px;
            font-size: 12px;
        }

        .col-no {
            width: 30px;
            text-align: center;
        }

        .col-desc {}

        .col-status {
            width: 70px;
            text-align: center;
            font-size: 11px;
        }

        .col-requester {
            width: 100px;
            text-align: center;
            font-size: 10px;
        }

        .col-owner {
            width: 90px;
            text-align: center;
            font-size: 11px;
        }

        .col-date {
            width: 75px;
            text-align: center;
        }
    }
</style>

<!-- Filter Modal -->
<div id="filterModal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1050; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
    <div class="glass glass-card" style="width: 100%; max-width: 400px; padding: 25px; position: relative;">
        <button type="button" onclick="document.getElementById('filterModal').style.display='none'"
            style="position: absolute; top: 15px; right: 15px; background: none; border: none; color: var(--text-muted); font-size: 1.2rem; cursor: pointer;">✕</button>

        <h3 style="margin-top: 0; margin-bottom: 20px; color: var(--text-main);">Filtreler</h3>

        <div style="display: flex; flex-direction: column; gap: 15px;">

            <label class="filter-checkbox"
                style="width: 100%; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                <input type="checkbox" id="detailsFilter" <?= $showDetails ? 'checked' : '' ?>>
                📝 Detaylı Görünüm
            </label>

            <label class="filter-checkbox"
                style="color: #10b981; width: 100%; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                <input type="checkbox" id="completedFilter" <?= $showCompleted ? 'checked' : '' ?>>
                ✅ Tamamlananlar
            </label>

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
        const details = document.getElementById('detailsFilter').checked ? '1' : '0';
        const completed = document.getElementById('completedFilter').checked ? '1' : '0';
        const owner = document.getElementById('ownerFilter').value;
        const project = document.getElementById('projectFilter').value;

        const params = new URLSearchParams(window.location.search);
        if (myTasks === '1') params.set('my', '1'); else params.delete('my');
        if (overdue === '1') params.set('overdue', '1'); else params.delete('overdue');
        if (details === '1') params.set('details', '1'); else params.delete('details');
        if (completed === '1') params.set('completed', '1'); else params.delete('completed');
        if (owner) params.set('owner', owner); else params.delete('owner');
        if (project) params.set('project', project); else params.delete('project');

        window.location.href = 'list.php?' + params.toString();
    }

    function clearFilters() {
        document.getElementById('myTasksFilter').checked = false;
        document.getElementById('overdueFilter').checked = false;
        document.getElementById('detailsFilter').checked = false;
        document.getElementById('completedFilter').checked = false;
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

    function toggleTaskStatus(checkbox, taskId) {
        const isChecked = checkbox.checked;
        const newStatusId = isChecked ? 5 : 2; // 5: Tamamlandı, 2: İşlemde (Uncheck edince işlemdeye alalım)

        // Optimistic UI update (optional, but good for feedback)
        const row = checkbox.closest('tr');
        if (isChecked) {
            row.style.opacity = '0.7';
        } else {
            row.style.opacity = '1';
        }

        // Get CSRF token from meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        fetch('api_update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                task_id: taskId,
                status_id: newStatusId
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload to reflect all changes (like completed_at date, status badge color etc)
                    // Or just update UI dynamically. Reload is safer for consistency.
                    window.location.reload();
                } else {
                    alert('Hata oluştu: ' + (data.error || 'Bilinmeyen hata'));
                    checkbox.checked = !isChecked; // Revert
                    row.style.opacity = '1';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Bir bağlantı hatası oluştu.');
                checkbox.checked = !isChecked; // Revert
                row.style.opacity = '1';
            });
    }
</script>

<?php require SRC_DIR . '/partials/footer.php'; ?>

<!-- PRINT ONLY SECTION - Moved to root level -->
<div id="print-area">
    <table class="print-header">
        <tr>
            <td class="ph-center">
                <div class="ph-dept"><?= escape($userDeptName ?? '') ?></div>
                <div class="ph-title">İŞ LİSTESİ</div>
            </td>
        </tr>
    </table>

    <table class="print-table">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th class="col-desc">Devam Eden Kararlar/ Aksiyonlar</th>
                <th class="col-status">Durum</th>
                <th class="col-requester">Talep Eden / Tarih</th>
                <th class="col-owner">Sorumlu</th>
                <th class="col-date">Hedef Tarih</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tasks as $index => $task): ?>
                <tr>
                    <td class="col-no"><?= $index + 1 ?></td>
                    <td class="col-desc">
                        <strong><?= escape($task['title']) ?></strong>
                        <?php if (!empty($task['description'])): ?>
                            <div style="margin-top: 5px;"><?= nl2br(escape($task['description'])) ?></div>
                        <?php endif; ?>

                        <?php if (isset($taskChecklists[$task['id']])): ?>
                            <div style="margin-top: 8px;">
                                <strong style="font-size: 0.9em;">Kontrol Listesi:</strong>
                                <ul style="margin: 5px 0 0 0; padding-left: 20px; list-style: none;">
                                    <?php foreach ($taskChecklists[$task['id']] as $item): ?>
                                        <li style="margin-bottom: 3px; font-size: 0.9em;">
                                            <?= $item['is_completed'] ? '☑' : '☐' ?>
                                            <span style="<?= $item['is_completed'] ? 'text-decoration: line-through;' : '' ?>">
                                                <?= escape($item['item_text']) ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($taskComments[$task['id']])): ?>
                            <div style="margin-top: 8px;">
                                <strong style="font-size: 0.9em;">Yorumlar:</strong>
                                <ul style="margin: 5px 0 0 0; padding-left: 20px; list-style: none;">
                                    <?php foreach ($taskComments[$task['id']] as $comment): ?>
                                        <li style="margin-bottom: 3px; font-size: 0.85em; color: #444;">
                                            • <strong><?= escape($comment['full_name']) ?></strong>
                                            <span
                                                style="font-size: 0.8em; color: #777;">(<?= date('d.m.Y H:i', strtotime($comment['created_at'])) ?>)</span>:
                                            <?= escape($comment['comment']) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="col-status">
                        <?= escape($task['status_name']) ?>
                    </td>
                    <td class="col-requester">
                        <?= escape($task['requester_name']) ?><br>
                        <em><?= escape($task['requester_department']) ?></em><br>
                        <span
                            style="font-size: 0.9em; color: #555;"><?= date('d.m.Y', strtotime($task['created_at'])) ?></span>
                    </td>
                    <td class="col-owner">
                        <?= escape($task['owner_name']) ?><br>
                        <em><?= escape($task['owner_department']) ?></em>
                    </td>
                    <td class="col-date">
                        <?= $task['target_completion_date'] ? date('d.m.Y', strtotime($task['target_completion_date'])) : 'Devamlı' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>