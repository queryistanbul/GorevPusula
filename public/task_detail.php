<?php
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

check_auth();
$db = Database::getInstance();
$user = Auth::user();

$id = $_GET['id'] ?? null;
if (!$id)
    redirect('index.php');

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $new_status_id = $_POST['status_id'];

        // Get old and new status info for logging
        $oldTask = $db->fetchOne("SELECT t.title, s.name as old_status FROM tasks t JOIN statuses s ON t.status_id = s.id WHERE t.id = ?", [$id]);
        $newStatus = $db->fetchOne("SELECT name, kanban_column FROM statuses WHERE id = ?", [$new_status_id]);

        $completedAt = ($newStatus['kanban_column'] === 'done') ? date('Y-m-d H:i:s') : null;

        $db->execute("UPDATE tasks SET status_id = ?, completed_at = ? WHERE id = ?", [$new_status_id, $completedAt, $id]);

        log_activity(
            'status_change',
            'task',
            $id,
            $user['full_name'] . " '{$oldTask['title']}' görevinin durumunu '{$oldTask['old_status']}' -> '{$newStatus['name']}' olarak değiştirdi",
            ['status' => $oldTask['old_status']],
            ['status' => $newStatus['name']]
        );
    }

    if (isset($_POST['delete_task'])) {
        // Check permission (Admin or Requester)
        $task = $db->fetchOne("SELECT title, requester_id FROM tasks WHERE id = ?", [$id]);
        if ($user['role'] === 'admin' || $user['id'] == $task['requester_id']) {
            $db->execute("DELETE FROM tasks WHERE id = ?", [$id]);

            log_activity('delete', 'task', $id, $user['full_name'] . " '{$task['title']}' görevini sildi");

            redirect('index.php');
        }
    }

    if (isset($_POST['add_comment'])) {
        $comment = $_POST['comment'];
        if (!empty($comment)) {
            $db->execute(
                "INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)",
                [$id, $user['id'], $comment]
            );
        }
    }

    if (isset($_POST['update_details'])) {
        $owner_id = $_POST['owner_id'];
        $priority_id = $_POST['priority_id'];
        $main_topic_id = !empty($_POST['main_topic_id']) ? $_POST['main_topic_id'] : null;
        $target_date = !empty($_POST['target_completion_date']) ? $_POST['target_completion_date'] : null;
        $hashtags = $_POST['hashtags'] ?? '';
        $project_id = !empty($_POST['project_id']) ? $_POST['project_id'] : null;
        $step_id = !empty($_POST['step_id']) ? $_POST['step_id'] : null;

        // Get department of the new owner
        $owner = $db->fetchOne("SELECT department_id FROM users WHERE id = ?", [$owner_id]);
        $responsible_dept_id = $owner['department_id'];

        $sql = "UPDATE tasks SET 
                owner_id = ?, 
                responsible_department_id = ?,
                priority_id = ?,
                main_topic_id = ?,
                target_completion_date = ?,
                hashtags = ?,
                project_id = ?,
                project_step_id = ?
                WHERE id = ?";

        try {
            // Get old data for logging
            $oldData = $db->fetchOne("SELECT owner_id, priority_id, main_topic_id, target_completion_date, hashtags, project_id, project_step_id FROM tasks WHERE id = ?", [$id]);

            $db->execute($sql, [$owner_id, $responsible_dept_id, $priority_id, $main_topic_id, $target_date, $hashtags, $project_id, $step_id, $id]);

            // Log activity
            $taskTitle = $db->fetchOne("SELECT title FROM tasks WHERE id = ?", [$id])['title'];
            log_activity(
                'update',
                'task',
                $id,
                $user['full_name'] . " '$taskTitle' görevinin detaylarını güncelledi",
                $oldData,
                [
                    'owner_id' => $owner_id,
                    'priority_id' => $priority_id,
                    'main_topic_id' => $main_topic_id,
                    'target_completion_date' => $target_date,
                    'hashtags' => $hashtags
                ]
            );

            // Handle Attachments
            if (!empty($_FILES['attachments']['name'][0])) {
                $uploadDir = __DIR__ . '/uploads/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                foreach ($_FILES['attachments']['name'] as $key => $name) {
                    if ($_FILES['attachments']['error'][$key] === 0) {
                        $tmpName = $_FILES['attachments']['tmp_name'][$key];
                        $size = $_FILES['attachments']['size'][$key];
                        $fileName = time() . '_' . $name;
                        $uploadPath = $uploadDir . $fileName;

                        if (move_uploaded_file($tmpName, $uploadPath)) {
                            $db->execute(
                                "INSERT INTO task_attachments (task_id, file_name, file_path, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?)",
                                [$id, $name, 'uploads/' . $fileName, $size, $user['id']]
                            );
                        }
                    }
                }
            }

            // Handle Add Checklist Item
            if (isset($_POST['add_checklist_item']) && !empty($_POST['new_checklist_item'])) {
                $newItemText = trim($_POST['new_checklist_item']);
                if ($newItemText) {
                    $maxOrder = $db->fetchOne("SELECT MAX(order_index) as m FROM task_checklist_items WHERE task_id = ?", [$id])['m'];
                    $newOrder = ((int) $maxOrder) + 1;
                    $db->execute("INSERT INTO task_checklist_items (task_id, item_text, order_index, is_completed) VALUES (?, ?, ?, 0)", [$id, $newItemText, $newOrder]);
                }
            }

            // Handle Delete Checklist Item
            if (isset($_POST['delete_checklist_item'])) {
                $delId = $_POST['delete_checklist_item'];
                $db->execute("DELETE FROM task_checklist_items WHERE id = ? AND task_id = ?", [$delId, $id]);
            }

            // Handle Checklist Updates
            // We need to fetch all items for this task to know which ones were unchecked
            $allChecklistItems = $db->query("SELECT id FROM task_checklist_items WHERE task_id = ?", [$id]);
            $postedItems = $_POST['checklist_items'] ?? [];

            foreach ($allChecklistItems as $item) {
                $itemId = $item['id'];

                // Skip if this item was just deleted
                if (isset($_POST['delete_checklist_item']) && $_POST['delete_checklist_item'] == $itemId) {
                    continue;
                }

                $isCompleted = isset($postedItems[$itemId]) ? 1 : 0;

                // You might want to track completed_at only when it changes to 1?
                // For simplicity, update:
                $completedAt = $isCompleted ? date('Y-m-d H:i:s') : null;

                $db->execute("UPDATE task_checklist_items SET is_completed = ?, completed_at = ? WHERE id = ?", [$isCompleted, $completedAt, $itemId]);
            }

        } catch (Exception $e) {
            die("Hata oluştu: " . $e->getMessage());
        }
    }

    // Refresh to show updates
    // Redirect to index
    redirect("index.php");
}

// Fetch Task
$sql = "SELECT t.*,
            p.name as priority_name, p.color as priority_color,
            s.name as status_name, s.color as status_color,
            mt.name as main_topic_name,
            o.full_name as owner_name,
            r.full_name as requester_name,
            d.name as department_name
        FROM tasks t
        LEFT JOIN priorities p ON t.priority_id = p.id
        LEFT JOIN statuses s ON t.status_id = s.id
        LEFT JOIN main_topics mt ON t.main_topic_id = mt.id
        LEFT JOIN users o ON t.owner_id = o.id
        LEFT JOIN users r ON t.requester_id = r.id
        LEFT JOIN departments d ON t.responsible_department_id = d.id
        WHERE t.id = ?";

$task = $db->fetchOne($sql, [$id]);

if (!$task) {
    die("Görev bulunamadı.");
}

// Fetch necessary data for dropdowns
$statuses = $db->query("SELECT * FROM statuses ORDER BY order_index");
$priorities = $db->query("SELECT * FROM priorities ORDER BY id");
$users = $db->query("SELECT id, full_name, department_id FROM users WHERE is_active = 1");
$mainTopics = $db->query("SELECT * FROM main_topics");
$attachments = $db->query("SELECT * FROM task_attachments WHERE task_id = ?", [$id]);
$comments = $db->query("
    SELECT c.*, u.full_name, u.username 
    FROM task_comments c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.task_id = ? 
    ORDER BY c.created_at ASC
", [$id]);

// Fetch Checklist Items
$checklistItems = $db->query("SELECT * FROM task_checklist_items WHERE task_id = ? ORDER BY order_index", [$id]);

// Fetch Projects (filtered by user's department permissions)
$projectParams = [];
$projectSql = "SELECT id, project_code, name FROM projects";
if ($user['role'] !== 'admin') {
    $deptIds = [$user['department_id']];
    if (!empty($user['managed_department_ids'])) {
        $deptIds = array_merge($deptIds, $user['managed_department_ids']);
    }
    $placeholders = implode(',', array_fill(0, count($deptIds), '?'));
    $projectSql .= " WHERE department_id IN ($placeholders) OR department_id IS NULL";
    $projectParams = $deptIds;
}
$projectSql .= " ORDER BY project_code DESC";
$projects = $db->query($projectSql, $projectParams);
$allSteps = $db->query("SELECT id, project_id, name FROM project_steps ORDER BY order_index");

require SRC_DIR . '/partials/header.php';
?>

<div style="margin-bottom: 20px;">
    <a href="index.php" style="color: var(--text-muted); text-decoration: none;">&larr; Listeye Dön</a>
</div>

<div style="max-width: 1200px; margin: 0 auto;">

    <!-- Header & Actions -->
    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
        <div style="flex: 1;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                <span style="color: var(--text-muted); font-size: 0.9rem;">#<?= $task['id'] ?></span>
                <span class="status-badge"
                    style="background: <?= $task['status_color'] ?>20; color: <?= $task['status_color'] ?>; border: 1px solid <?= $task['status_color'] ?>40;">
                    <?= escape($task['status_name']) ?>
                </span>
            </div>
            <h1 style="margin: 0; font-size: 1.8rem;"><?= escape($task['title']) ?></h1>
        </div>

        <?php if ($user['role'] === 'admin' || $user['id'] == $task['requester_id']): ?>
            <form method="POST" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                <input type="hidden" name="delete_task" value="1">
                <button type="submit" class="btn"
                    style="background: rgba(239, 68, 68, 0.2); color: #ef4444; padding: 8px 16px; font-size: 0.9rem;">
                    Görevi Sil
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Main Detail Form Declaration (Inputs will link to this) -->
    <form id="detail-form" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="update_details" value="1">
    </form>

    <div class="create-task-grid">
        <!-- Left Column: Primary Content -->
        <div style="display: flex; flex-direction: column; gap: 20px;">

            <!-- Description Card -->
            <div class="glass glass-card">
                <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 1.1rem; color: var(--text-muted);">Açıklama
                </h3>
                <div style="line-height: 1.6; color: var(--text-main);">
                    <?= nl2br(escape($task['description'])) ?>
                </div>

                <?php if ($task['completed_at']): ?>
                    <div
                        style="margin-top: 20px; padding: 10px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; color: var(--text-main); display: inline-block;">
                        ✅ Kapanma Tarihi: <strong><?= date('d.m.Y H:i', strtotime($task['completed_at'])) ?></strong>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Attachments & Upload -->
            <div class="glass glass-card">
                <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 1.1rem; color: var(--text-muted);">Dosyalar
                </h3>

                <?php if (!empty($attachments)): ?>
                    <div style="display: grid; gap: 10px; margin-bottom: 20px;">
                        <?php foreach ($attachments as $att): ?>
                            <div
                                style="display: flex; align-items: center; justify-content: space-between; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span>📎</span>
                                    <a href="<?= escape($att['file_path']) ?>" download target="_blank"
                                        style="color: var(--primary); text-decoration: none;">
                                        <?= escape($att['file_name']) ?>
                                    </a>
                                    <span style="font-size: 0.8rem; color: var(--text-muted);">
                                        (<?= number_format($att['file_size'] / 1024, 1) ?> KB)
                                    </span>
                                </div>
                                <span style="font-size: 0.8rem; color: var(--text-muted);">
                                    <?= date('d.m.Y H:i', strtotime($att['uploaded_at'])) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Compact Upload Zone -->
                <input type="file" id="file-input" name="attachments[]" multiple style="display: none;"
                    form="detail-form" onchange="updateFileList(this.files)">
                <div id="drop-zone" onclick="document.getElementById('file-input').click()" style="
                    border: 2px dashed var(--glass-border);
                    border-radius: 8px;
                    padding: 15px;
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    cursor: pointer;
                    transition: all 0.2s;
                    background: rgba(0,0,0,0.1);
                    position: relative;
                " onmouseover="this.style.borderColor='var(--primary)'; this.style.background='rgba(79, 70, 229, 0.05)'"
                    onmouseout="this.style.borderColor='var(--glass-border)'; this.style.background='rgba(0,0,0,0.1)'">

                    <!-- Drag Overlay -->
                    <div id="drag-overlay" style="
                        display: none;
                        position: absolute;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(79, 70, 229, 0.1);
                        border: 2px dashed var(--primary);
                        border-radius: 8px;
                        z-index: 10;
                        backdrop-filter: blur(2px);
                        justify-content: center;
                        align-items: center;
                        pointer-events: none;
                    ">
                        <div style="font-weight: 500; color: var(--primary);">Bırakın</div>
                    </div>

                    <div style="font-size: 1.5rem;">📎</div>
                    <div style="flex: 1;">
                        <div style="color: var(--text-main); font-weight: 500;">Dosya Yükle</div>
                        <div style="color: var(--text-muted); font-size: 0.85rem;" id="file-list-placeholder">Sürükleyip
                            bırakın veya seçin</div>
                        <div id="file-list" style="font-size: 0.85rem; color: var(--primary); margin-top: 2px;"></div>
                    </div>
                </div>
            </div>

            <!-- Checklist -->
            <div class="glass glass-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-muted);">Kontrol Listesi</h3>
                </div>

                <div style="display: flex; flex-direction: column; gap: 10px; margin: 0 0 15px 0;">
                    <?php if (empty($checklistItems)): ?>
                        <div style="color: var(--text-muted); font-style: italic; font-size: 0.9rem;">Henüz madde
                            eklenmemiş.</div>
                    <?php endif; ?>

                    <?php foreach ($checklistItems as $item): ?>
                        <div
                            style="display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                            <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                                <input type="checkbox" name="checklist_items[<?= $item['id'] ?>]" value="1"
                                    <?= $item['is_completed'] ? 'checked' : '' ?> form="detail-form"
                                    style="cursor: pointer; transform: scale(1.3); flex-shrink: 0; width: auto;">
                                <span class="<?= $item['is_completed'] ? 'text-muted' : '' ?>"
                                    style="color: var(--text-main); line-height: 1.5; <?= $item['is_completed'] ? 'text-decoration: line-through;' : '' ?>">
                                    <?= escape($item['item_text']) ?>
                                </span>
                            </div>
                            <button type="submit" name="delete_checklist_item" value="<?= $item['id'] ?>" form="detail-form"
                                style="background: none; border: none; color: #ef4444; font-size: 1.1rem; cursor: pointer; padding: 0 5px; opacity: 0.7;"
                                title="Maddeyi Sil"
                                onclick="return confirm('Bu maddeyi silmek istediğinize emin misiniz?');">
                                🗑️
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Add New Item -->
                <div style="display: flex; gap: 10px; margin: 0; padding: 0;">
                    <input type="text" name="new_checklist_item" form="detail-form"
                        placeholder="Yeni kontrol maddesi ekle..." style="flex: 1; min-width: 0;">
                    <button type="submit" name="add_checklist_item" value="1" form="detail-form"
                        class="btn btn-sm btn-glass">
                        + Ekle
                    </button>
                </div>
            </div>

        </div>

        <!-- Right Column: Settings & Metadata -->
        <div style="display: flex; flex-direction: column; gap: 20px;">

            <!-- Status Card -->
            <div class="glass glass-card" style="padding: 20px;">
                <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 1rem; color: var(--text-muted);">Durum</h3>
                <form method="POST" style="display: flex; gap: 10px;">
                    <input type="hidden" name="update_status" value="1">
                    <select name="status_id" style="width: 100%;" onchange="this.form.submit()">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $s['id'] == $task['status_id'] ? 'selected' : '' ?>>
                                <?= $s['name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <!-- <button type="submit" class="btn btn-primary btn-sm">Güncelle</button> (Auto-submit on change is cleaner) -->
                    <noscript><button type="submit" class="btn btn-primary btn-sm">Ok</button></noscript>
                </form>
            </div>

            <!-- Details Card -->
            <div class="glass glass-card" style="padding: 20px;">
                <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 1rem; color: var(--text-muted);">Görev
                    Detayları</h3>

                <div class="form-group">
                    <label style="font-size: 0.85rem;">Öncelik</label>
                    <select name="priority_id" form="detail-form" style="width: 100%;">
                        <?php foreach ($priorities as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $p['id'] == $task['priority_id'] ? 'selected' : '' ?>>
                                <?= escape($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label style="font-size: 0.85rem;">Hedef Tarih</label>
                    <input type="date" name="target_completion_date" value="<?= $task['target_completion_date'] ?>"
                        form="detail-form" style="width: 100%;">
                </div>

                <div class="form-group">
                    <label style="font-size: 0.85rem;">Sorumlu Kişi</label>
                    <select name="owner_id" form="detail-form" style="width: 100%;">
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $u['id'] == $task['owner_id'] ? 'selected' : '' ?>>
                                <?= escape($u['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label style="font-size: 0.85rem;">Konu</label>
                    <select name="main_topic_id" form="detail-form" style="width: 100%;">
                        <option value="">Seçiniz...</option>
                        <?php foreach ($mainTopics as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $t['id'] == $task['main_topic_id'] ? 'selected' : '' ?>>
                                <?= escape($t['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label style="font-size: 0.85rem;">Etiketler</label>
                    <input type="text" name="hashtags" value="<?= escape($task['hashtags']) ?>" form="detail-form"
                        placeholder="#acil" style="width: 100%;">
                </div>

                <div class="form-group">
                    <label style="font-size: 0.85rem;">Proje (Opsiyonel)</label>
                    <select name="project_id" id="projectSelect" form="detail-form" style="width: 100%;" onchange="loadProjectSteps()">
                        <option value="">Proje Seçiniz...</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?= $proj['id'] ?>" <?= $proj['id'] == $task['project_id'] ? 'selected' : '' ?>>
                                <?= escape($proj['project_code']) ?> - <?= escape($proj['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="stepSelectContainer" style="<?= $task['project_id'] ? '' : 'display: none;' ?>">
                    <label style="font-size: 0.85rem;">Proje Adımı</label>
                    <select name="step_id" id="stepSelect" form="detail-form" style="width: 100%;">
                        <option value="">Adım Seçiniz...</option>
                        <?php 
                        $currentProjectSteps = array_filter($allSteps, function($s) use ($task) {
                            return $s['project_id'] == $task['project_id'];
                        });
                        foreach ($currentProjectSteps as $step): ?>
                            <option value="<?= $step['id'] ?>" <?= $step['id'] == $task['project_step_id'] ? 'selected' : '' ?>>
                                <?= escape($step['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" form="detail-form" class="btn btn-primary" style="width: 100%;">Değişiklikleri
                        Kaydet</button>
                </div>
            </div>

            <!-- Meta Info (Requester etc) -->
            <div style="padding: 0 10px; font-size: 0.85rem; color: var(--text-muted);">
                <div><strong>Talep Eden:</strong> <?= escape($task['requester_name']) ?></div>
                <div style="margin-top: 5px;"><strong>Sorumlu Bölüm:</strong> <?= escape($task['department_name']) ?>
                </div>
            </div>

        </div>
    </div>

    <script>
        const dropZone = document.getElementById('drop-zone');
        const dragOverlay = document.getElementById('drag-overlay');
        const fileInput = document.getElementById('file-input');
        const fileList = document.getElementById('file-list');
        const fileListPlaceholder = document.getElementById('file-list-placeholder');

        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Highlight drop zone when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            dragOverlay.style.display = 'flex';
        }

        function unhighlight(e) {
            dragOverlay.style.display = 'none';
        }

        // Handle dropped files
        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            updateFileList(files);
        }

        function handleFiles(files) {
            fileInput.files = files;
            updateFileList(files);
        }

        fileInput.addEventListener('change', function () {
            updateFileList(this.files);
        });

        function updateFileList(files) {
            if (files.length > 0) {
                const names = Array.from(files).map(f => f.name).join(', ');
                fileList.innerHTML = `<strong>${files.length} dosya seçildi:</strong> ${names}`;
                fileListPlaceholder.style.display = 'none';
            } else {
                fileList.innerHTML = '';
                fileListPlaceholder.style.display = 'block';
            }
        }

        // Project Steps Logic
        const allSteps = <?= json_encode($allSteps) ?>;
        const projectSelect = document.getElementById('projectSelect');
        const stepSelect = document.getElementById('stepSelect');
        const stepContainer = document.getElementById('stepSelectContainer');

        function loadProjectSteps() {
            if (!projectSelect) return;
            const projectId = projectSelect.value;
            const currentStepId = <?= $task['project_step_id'] ?: 'null' ?>;
            
            // Clear existing options
            stepSelect.innerHTML = '<option value="">Adım Seçiniz...</option>';
            
            if (!projectId) {
                stepContainer.style.display = 'none';
                return;
            }
            
            // Filter steps for this project
            const projectSteps = allSteps.filter(s => s.project_id == projectId);
            
            if (projectSteps.length > 0) {
                projectSteps.forEach(step => {
                    const option = document.createElement('option');
                    option.value = step.id;
                    option.textContent = step.name;
                    if (step.id == currentStepId) option.selected = true;
                    stepSelect.appendChild(option);
                });
                stepContainer.style.display = 'block';
            } else {
                stepContainer.style.display = 'none';
            }
        }
    </script>

    <!-- Comments Section -->
    <div class="glass glass-card" style="margin-top: 30px;">
        <h3>Yorumlar</h3>

        <div style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 20px;">
            <?php foreach ($comments as $comment): ?>
                <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px;">
                    <div
                        style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.9rem; color: var(--text-muted);">
                        <strong><?= escape($comment['full_name']) ?></strong>
                        <span><?= date('d.m.Y H:i', strtotime($comment['created_at'])) ?></span>
                    </div>
                    <div style="line-height: 1.5;">
                        <?= nl2br(escape($comment['comment'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($comments)): ?>
                <div style="color: var(--text-muted); font-style: italic;">Henüz yorum yok.</div>
            <?php endif; ?>
        </div>

        <form method="POST">
            <input type="hidden" name="add_comment" value="1">
            <div class="form-group">
                <textarea name="comment" rows="3" placeholder="Yorum yazın..." required
                    style="width: 100%; padding: 10px; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); border-radius: 8px; color: white;"></textarea>
            </div>
            <div style="text-align: right; margin-top: 10px;">
                <button type="submit" class="btn btn-primary">Yorum Ekle</button>
            </div>
        </form>
    </div>

</div>

<?php require SRC_DIR . '/partials/footer.php'; ?>