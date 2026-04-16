<?php
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

check_auth();
$db = Database::getInstance();
$user = Auth::user();

// Check for "my tasks" filter
$myTasksOnly = isset($_GET['my']) && $_GET['my'] == '1';

// Fetch Tasks
$tasks = get_tasks($db, $user, $myTasksOnly);

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
        <h1 class="page-title">Görevlerim (Kanban)</h1>

    </div>
    <div class="filter-bar">
        <label class="filter-checkbox">
            <input type="checkbox" id="myTasksFilter" <?= $myTasksOnly ? 'checked' : '' ?>
                onchange="window.location.href = this.checked ? '?my=1' : 'kanban.php'">
            Benim Görevlerim
        </label>
        <div class="view-switcher">
            <a href="index.php<?= $myTasksOnly ? '?my=1' : '' ?>" class="btn btn-sm btn-glass">Grid</a>
            <a href="kanban.php<?= $myTasksOnly ? '?my=1' : '' ?>" class="btn btn-sm btn-primary">Kanban</a>
            <a href="list.php<?= $myTasksOnly ? '?my=1' : '' ?>" class="btn btn-sm btn-glass">Liste</a>
            <a href="gantt.php<?= $myTasksOnly ? '?my=1' : '' ?>" class="btn btn-sm btn-glass">Gantt</a>
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
                        onclick="window.location='task_detail.php?id=<?= $task['id'] ?>'">
                        <div class="kanban-card-header">
                            <span class="status-badge"
                                style="font-size: 0.7rem; padding: 2px 6px; background: <?= $task['priority_color'] ?>20; color: <?= $task['priority_color'] ?>; border: 1px solid <?= $task['priority_color'] ?>40;">
                                <?= escape($task['priority_name'] ?? 'Norm') ?>
                            </span>
                            <?php if (!empty($task['project_name'])): ?>
                                <span class="project-hint"
                                    style="font-size: 0.7rem; color: #4f46e5; font-weight: bold; margin-left: auto; display: inline-block; background: rgba(79, 70, 229, 0.1); padding: 2px 6px; border-radius: 4px;"
                                    title="<?= escape($task['project_code'] . ' - ' . $task['project_name']) ?>">
                                    <?= escape($task['project_code']) ?> - <?= escape($task['project_name']) ?>
                                </span>
                            <?php else: ?>
                                <!-- Debug: No Project Name found for Task ID <?= $task['id'] ?> -->
                            <?php endif; ?>
                        </div>
                        <h4
                            class="kanban-card-title <?= ((int) $task['owner_id'] === (int) $user['id']) ? 'my-task-title' : '' ?>">
                            <?= escape($task['title']) ?>
                            <span style="font-size: 0.6rem; color: #94a3b8;">(M: <?= $user['id'] ?>, O:
                                <?= $task['owner_id'] ?>)</span>
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