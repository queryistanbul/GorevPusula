<?php
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

check_auth();
$db = Database::getInstance();
$user = Auth::user();

// Check for "my tasks" filter
$myTasksOnly = isset($_GET['my']) && $_GET['my'] == '1';
// Check for "overdue" filter
$overdueOnly = isset($_GET['overdue']) && $_GET['overdue'] == '1';

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

// Fetch Tasks
$tasks = get_tasks($db, $user, $myTasksOnly, $overdueOnly, false, "s.order_index ASC, t.completed_at DESC, t.created_at DESC", $filterOwnerId, $filterProjectId);

// Fetch Statuses for columns
$statuses = $db->query("SELECT * FROM statuses ORDER BY order_index");

// Group tasks by status
$groupedTasks = [];
foreach ($statuses as $status) {
    $groupedTasks[$status['id']] = [];
}
foreach ($tasks as $task) {
    if (isset($groupedTasks[$task['status_id']])) {
        $groupedTasks[$task['status_id']][] = $task;
    }
}

$layout = 'fluid';
require SRC_DIR . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Görevlerim</h1>

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
                class="btn btn-sm btn-primary">Kanban</a>
            <a href="list.php?<?= http_build_query(array_merge($_GET, ['view' => 'list'])) ?>"
                class="btn btn-sm btn-glass">Liste</a>
            <a href="gantt.php?<?= http_build_query(array_merge($_GET, ['view' => 'gantt'])) ?>"
                class="btn btn-sm btn-glass">Gantt</a>
        </div>
        <a href="create_task.php" class="btn btn-primary">+ Yeni Görev</a>
    </div>
</div>

<div class="kanban-board">
    <?php foreach ($statuses as $status): ?>
        <div class="kanban-column">
            <div class="kanban-column-header">
                <h3 class="kanban-column-title" style="color: <?= $status['color'] ?>;">
                    <span class="kanban-dot" style="background: <?= $status['color'] ?>"></span>
                    <?= escape($status['name']) ?>
                </h3>
                <span class="kanban-count">
                    <?= count($groupedTasks[$status['id']]) ?>
                </span>
            </div>

            <div class="kanban-tasks" data-status-id="<?= $status['id'] ?>"
                ondragover="event.preventDefault(); this.style.background='rgba(255,255,255,0.05)';"
                ondragleave="this.style.background='transparent';"
                ondrop="handleDrop(event, <?= $status['id'] ?>); this.style.background='transparent';">
                <?php foreach ($groupedTasks[$status['id']] as $task): ?>
                    <div class="kanban-card glass glass-card" draggable="true" data-task-id="<?= $task['id'] ?>"
                        ondragstart="handleDragStart(event, <?= $task['id'] ?>)"
                        onclick="openTaskPanel(<?= $task['id'] ?>, event)">
                        <div class="kanban-card-header">
                            <span class="status-badge"
                                style="font-size: 0.7rem; padding: 2px 6px; background: <?= $task['priority_color'] ?>20; color: <?= $task['priority_color'] ?>; border: 1px solid <?= $task['priority_color'] ?>40;">
                                <?= escape($task['priority_name'] ?? 'Norm') ?>
                            </span>
                        </div>
                        <h4 class="kanban-card-title"
                            style="color: <?= ($task['owner_name'] === $user['full_name']) ? '#10b981' : 'var(--text-main)' ?>;">
                            <?= escape($task['title']) ?>
                        </h4>

                        <?php if (!empty($task['hashtags'])): ?>
                            <div style="margin-bottom: 10px;">
                                <?php
                                $tags = array_filter(preg_split('/[\s,]+/', $task['hashtags']));
                                foreach ($tags as $tag):
                                    ?>
                                    <span class="hashtag"><?= escape($tag) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="kanban-card-meta">
                            <div class="user-avatar-sm">
                                👤
                                <?= explode(' ', $task['owner_name'])[0] ?>
                            </div>
                            <div>
                                <?= $task['target_completion_date'] ? date('d.m', strtotime($task['target_completion_date'])) : '-' ?>
                            </div>
                        </div>
                        <?php if (!empty($task['project_name'])): ?>
                            <div style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed var(--glass-border);">
                                <span class="project-info-badge"
                                    title="<?= escape($task['project_code'] . ' - ' . $task['project_name']) ?>">
                                    📁 <?= escape($task['project_code']) ?> - <?= escape($task['project_name']) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($groupedTasks[$status['id']])): ?>
                    <div class="empty-placeholder">
                        Görev yok
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
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

        window.location.href = 'index.php?' + params.toString();
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

<script>
    let draggedTaskId = null;
    let draggedElement = null;

    function handleDragStart(event, taskId) {
        draggedTaskId = taskId;
        draggedElement = event.target;
        event.target.style.opacity = '0.5';
        event.dataTransfer.effectAllowed = 'move';
    }

    function handleDrop(event, statusId) {
        event.preventDefault();
        if (!draggedTaskId) return;

        const dropZone = event.currentTarget;
        const cards = Array.from(dropZone.querySelectorAll('.kanban-card'));

        // Find where we dropped (which card are we near?)
        let dropIndex = cards.length; // Default to end
        const dropY = event.clientY;

        for (let i = 0; i < cards.length; i++) {
            const card = cards[i];
            const rect = card.getBoundingClientRect();
            const cardMiddle = rect.top + rect.height / 2;

            if (dropY < cardMiddle) {
                dropIndex = i;
                break;
            }
        }

        // Calculate order_index (use index * 10 for spacing)
        const orderIndex = dropIndex * 10;

        // Make AJAX call to update status and order
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        fetch('api_update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                task_id: draggedTaskId,
                status_id: statusId,
                order_index: orderIndex
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Hata: ' + (data.error || 'Bilinmeyen hata'));
                }
            })
            .catch(err => {
                alert('Bağlantı hatası: ' + err.message);
            });
    }

    // Reset opacity on drag end
    document.addEventListener('dragend', function (e) {
        if (e.target.classList.contains('kanban-card')) {
            e.target.style.opacity = '1';
        }
        draggedTaskId = null;
        draggedElement = null;
    });

    // Add visual indicator for drop position
    document.querySelectorAll('.kanban-tasks').forEach(zone => {
        zone.addEventListener('dragover', function (e) {
            e.preventDefault();
            const cards = Array.from(this.querySelectorAll('.kanban-card'));

            // Remove existing indicators
            this.querySelectorAll('.drop-indicator').forEach(el => el.remove());

            // Find insertion point
            const dropY = e.clientY;
            let insertBefore = null;

            for (const card of cards) {
                const rect = card.getBoundingClientRect();
                if (dropY < rect.top + rect.height / 2) {
                    insertBefore = card;
                    break;
                }
            }

            // Create and insert indicator
            const indicator = document.createElement('div');
            indicator.className = 'drop-indicator';
            indicator.style.cssText = 'height: 4px; background: var(--primary); border-radius: 2px; margin: 5px 0;';

            if (insertBefore) {
                this.insertBefore(indicator, insertBefore);
            } else {
                this.appendChild(indicator);
            }
        });

        zone.addEventListener('dragleave', function (e) {
            // Only remove if leaving the zone entirely
            if (!this.contains(e.relatedTarget)) {
                this.querySelectorAll('.drop-indicator').forEach(el => el.remove());
            }
        });

        zone.addEventListener('drop', function () {
            this.querySelectorAll('.drop-indicator').forEach(el => el.remove());
        });
    });
</script>

<?php require SRC_DIR . '/partials/footer.php'; ?>