<?php
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

check_auth();
$db = Database::getInstance();
$user = Auth::user();

$id = $_GET['id'] ?? null;
if (!$id)
    redirect('projects.php');

$project = $db->fetchOne("SELECT * FROM projects WHERE id = ?", [$id]);
if (!$project)
    redirect('projects.php');

// Access Control
if ($user['role'] !== 'admin') {
    $canView = false;
    // Allow if project is global (no department)
    if (empty($project['department_id'])) {
        $canView = true;
    } else {
        // Allow if project belongs to user's department
        if ($project['department_id'] == $user['department_id']) {
            $canView = true;
        }
        // Allow if user manages the project's department
        elseif (!empty($user['managed_department_ids']) && in_array($project['department_id'], $user['managed_department_ids'])) {
            $canView = true;
        }
    }

    if (!$canView) {
        // Redirect or show error
        redirect('projects.php');
    }
}

// Handle Update Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_project') {
    verify_csrf();
    $code = $_POST['project_code'];
    $name = $_POST['name'];
    $type = $_POST['type'];
    $dept_id = $_POST['department_id'] ?: null; // Handle empty string as NULL

    // Authorization for Update: Admin or User with access to the target department
    // Ideally, we should check if they have rights to the NEW department too.
    // For simplicity, strict check:
    if ($user['role'] !== 'admin') {
        // Ensure user belongs to or manages the NEW department (if setting one)
        if ($dept_id) {
            $allowed = [$user['department_id']];
            if (!empty($user['managed_department_ids']))
                $allowed = array_merge($allowed, $user['managed_department_ids']);

            if (!in_array($dept_id, $allowed)) {
                die("Yetkisiz işlem: Bu bölüme proje atayamazsınız.");
            }
        }
    }

    if ($code && $name) {
        $db->execute(
            "UPDATE projects SET project_code = ?, name = ?, type = ?, department_id = ? WHERE id = ?",
            [$code, $name, $type, $dept_id, $id]
        );

        log_activity('update', 'project', $id, $user['full_name'] . " '$name' projesini güncelledi");

        header("Location: project_detail.php?id=$id");
        exit;
    }
}

// Handle Add Step
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_step') {
    verify_csrf();
    $stepName = $_POST['step_name'];
    $order = $_POST['order_index'] ?? 0;

    if ($stepName) {
        $db->execute("INSERT INTO project_steps (project_id, name, order_index) VALUES (?, ?, ?)", [$id, $stepName, $order]);
        $stepId = $db->lastInsertId();

        log_activity('create', 'project_step', $stepId, $user['full_name'] . " '{$project['name']}' projesine '$stepName' adımını ekledi");

        // Redirect to avoid resubmit
        header("Location: project_detail.php?id=$id");
        exit;
    }
}

// Fetch Steps
$steps = $db->query("SELECT * FROM project_steps WHERE project_id = ? ORDER BY order_index ASC, id ASC", [$id]);

// Fetch Departments for Edit Modal
$departments = $db->query("SELECT * FROM departments ORDER BY name");

// Fetch All Tasks for this Project
$tasksRaw = $db->query("
    SELECT t.*, s.name as status_name, s.color as status_color, 
           u.full_name as owner_name, d.name as owner_department
    FROM tasks t
    LEFT JOIN statuses s ON t.status_id = s.id
    LEFT JOIN users u ON t.owner_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE t.project_id = ?
    ORDER BY t.created_at DESC
", [$id]);

// Fetch All Comments for Project Tasks
$commentsRaw = $db->query("
    SELECT c.*, u.full_name, u.username 
    FROM task_comments c 
    JOIN tasks t ON c.task_id = t.id 
    JOIN users u ON c.user_id = u.id 
    WHERE t.project_id = ? 
    ORDER BY c.created_at ASC
", [$id]);

$commentsByTask = [];
foreach ($commentsRaw as $c) {
    $commentsByTask[$c['task_id']][] = $c;
}

// Group Tasks by Step ID
$tasksByStep = [];
foreach ($tasksRaw as $t) {
    if ($t['project_step_id']) {
        $tasksByStep[$t['project_step_id']][] = $t;
    } else {
        $tasksByStep['uncategorized'][] = $t; // Tasks in project but not in a step
    }
}

$layout = 'fluid';
require SRC_DIR . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <a href="projects.php" class="text-muted" style="font-size: 0.9rem;">&larr; Projeler</a>
        <h1 class="page-title" style="margin-top: 5px;">
            <span style="color: var(--primary);">
                <?= escape($project['project_code']) ?>
            </span> -
            <?= escape($project['name']) ?>
        </h1>
        <div class="text-muted">
            <span class="status-badge"
                style="background: rgba(255,255,255,0.1); margin-right: 10px;"><?= escape($project['type']) ?></span>

            <?php
            // Find department name
            $deptName = 'Genel (Tüm Bölümler)';
            foreach ($departments as $d) {
                if ($d['id'] == $project['department_id']) {
                    $deptName = $d['name'];
                    break;
                }
            }
            ?>
            <span style="color: var(--text-muted); font-size: 0.9rem;">📂 <?= escape($deptName) ?></span>
        </div>
    </div>
    <div>
        <label
            style="font-size: 0.9rem; color: var(--text-muted); cursor: pointer; margin-right: 15px; display: inline-flex; align-items: center;">
            <input type="checkbox" id="globalDetailToggle" onchange="toggleTaskDetails(this)"
                style="margin-right: 5px;"> Detayları Göster
        </label>
        <button onclick="toggleEditProjectForm()" class="btn btn-glass" style="margin-right: 10px;">✏️ Düzenle</button>
        <button onclick="toggleStepForm()" class="btn btn-glass">+ Yeni Adım Ekle</button>
    </div>
</div>

<!-- Edit Project Modal -->
<div id="editProjectForm" class="glass glass-card" style="display: none; margin-bottom: 20px;">
    <h3 style="margin-top: 0; margin-bottom: 15px;">Projeyi Düzenle</h3>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_project">

        <div class="form-group">
            <label>Proje Numarası</label>
            <input type="text" name="project_code" class="form-input" required
                value="<?= escape($project['project_code']) ?>">
        </div>

        <div class="form-group">
            <label>Proje İsmi</label>
            <input type="text" name="name" class="form-input" required value="<?= escape($project['name']) ?>">
        </div>

        <div class="form-group">
            <label>Proje Tipi</label>
            <input type="text" name="type" class="form-input" value="<?= escape($project['type']) ?>">
        </div>

        <div class="form-group">
            <label>Bölüm</label>
            <select name="department_id" class="form-select">
                <option value="">Genel (Tüm Bölümler)</option>
                <?php foreach ($departments as $dept): ?>
                    <?php
                    // Verify if user can assign to this department (similar logic to add)
                    if ($user['role'] !== 'admin') {
                        $allowed = [$user['department_id']];
                        if (!empty($user['managed_department_ids']))
                            $allowed = array_merge($allowed, $user['managed_department_ids']);
                        if (!in_array($dept['id'], $allowed))
                            continue;
                    }
                    ?>
                    <option value="<?= $dept['id'] ?>" <?= $dept['id'] == $project['department_id'] ? 'selected' : '' ?>>
                        <?= escape($dept['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small style="color: var(--text-muted);">NOT: Bölümü değiştirdiğinizde, yetkiniz olmayan bir bölüme
                atarsanız projeye erişiminizi kaybedebilirsiniz.</small>
        </div>

        <div style="text-align: right; margin-top: 15px;">
            <button type="button" onclick="toggleEditProjectForm()" class="btn btn-glass"
                style="margin-right: 10px;">İptal</button>
            <button type="submit" class="btn btn-primary">Güncelle</button>
        </div>
    </form>
</div>

<!-- Script to toggle forms -->
<script>
    function toggleEditProjectForm() {
        const form = document.getElementById('editProjectForm');
        const stepForm = document.getElementById('newStepForm');

        // Close other
        if (stepForm.style.display !== 'none') stepForm.style.display = 'none';

        form.style.display = form.style.display === 'none' ? 'block' : 'none';
        if (form.style.display === 'block') {
            form.scrollIntoView({ behavior: 'smooth' });
        }
    }

    function toggleStepForm() {
        const form = document.getElementById('newStepForm');
        const editForm = document.getElementById('editProjectForm');

        // Close other
        if (editForm.style.display !== 'none') editForm.style.display = 'none';

        form.style.display = form.style.display === 'none' ? 'flex' : 'none';
        if (form.style.display === 'flex') {
            form.scrollIntoView({ behavior: 'smooth' });
        }
    }
</script>

<!-- Add Step Form -->
<div id="newStepForm" class="glass glass-card" style="display: none; margin-bottom: 20px;">
    <form method="POST" style="display: flex; gap: 10px; align-items: flex-end;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_step">
        <div style="flex: 1;">
            <label style="font-size: 0.8rem;">Adım İsmi</label>
            <input type="text" name="step_name" class="form-input" required placeholder="Örn: Analiz, Geliştirme...">
        </div>
        <div style="width: 100px;">
            <label style="font-size: 0.8rem;">Sıra</label>
            <input type="number" name="order_index" class="form-input" value="<?= count($steps) + 1 ?>">
        </div>
        <button type="submit" class="btn btn-primary" style="height: 42px;">Ekle</button>
    </form>
</div>

<div class="project-board">
    <!-- Debug: Steps Count = <?= count($steps) ?> -->
    <?php if (empty($steps)): ?>
        <div class="glass glass-card" style="text-align: center; padding: 40px; color: var(--text-muted);">
            Henüz proje adımı tanımlanmamış. "Yeni Adım Ekle" butonu ile başlayın.
        </div>
    <?php endif; ?>

    <?php foreach ($steps as $step): ?>
        <div class="step-wrapper" style="margin-bottom: 40px; display: block; clear: both;">
            <div class="project-step" style="margin-bottom: 20px;">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--glass-border); padding-bottom: 10px; margin-bottom: 15px;">
                    <h3 style="margin: 0; color: var(--text-main);">
                        <span style="color: var(--text-muted); font-size: 0.9rem;">#
                            <?= $step['order_index'] ?>
                        </span>
                        <?= escape($step['name']) ?>
                    </h3>
                    <a href="create_task.php?project_id=<?= $project['id'] ?>&step_id=<?= $step['id'] ?>"
                        class="btn btn-sm btn-primary">
                        + Bu Adıma Görev Ekle
                    </a>
                </div>
            </div>

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
                <div
                    style="padding: 10px; border: 1px dashed var(--glass-border); border-radius: 8px; text-align: center; color: var(--text-muted); font-size: 0.9rem;">
                    Bu adımda henüz görev yok.
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <!-- Uncategorized Tasks -->
    <?php if (isset($tasksByStep['uncategorized'])): ?>
        <div class="project-step" style="margin-top: 40px;">
            <div style="border-bottom: 1px solid var(--glass-border); padding-bottom: 10px; margin-bottom: 15px;">
                <h3 style="margin: 0; color: var(--text-muted);">Diğer Görevler (Adımsız)</h3>
            </div>
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
        </div>
    <?php endif; ?>
</div>

<script>
    function toggleStepForm() {
        const form = document.getElementById('newStepForm');
        form.style.display = form.style.display === 'none' ? 'flex' : 'none';
    }

    function toggleTaskDetails(checkbox) {
        const comments = document.querySelectorAll('.task-comments');
        comments.forEach(el => {
            el.style.display = checkbox.checked ? 'block' : 'none';
        });
    }
</script>

<?php require SRC_DIR . '/partials/footer.php'; ?>