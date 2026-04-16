<?php
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

check_auth();
$db = Database::getInstance();
$user = Auth::user();

// Handle New Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_project') {
    verify_csrf();
    $code = $_POST['project_code'];
    $name = $_POST['name'];
    $type = $_POST['type'];
    $department_id = $_POST['department_id'] ?: null;

    // Authorization Check: Can user create for this department?
    // Admin can create for any. Managers can create for their managed/own depts. Users... maybe only their own?
    if ($user['role'] !== 'admin') {
        $allowed = [$user['department_id']];
        if (!empty($user['managed_department_ids'])) {
            $allowed = array_merge($allowed, $user['managed_department_ids']);
        }
        if (!in_array($department_id, $allowed)) {
            // Fallback to own department if they try to hack it
            $department_id = $user['department_id'];
        }
    }

    if ($code && $name) {
        $db->execute("INSERT INTO projects (project_code, name, type, department_id) VALUES (?, ?, ?, ?)", [$code, $name, $type, $department_id]);
        $projectId = $db->lastInsertId();

        log_activity('create', 'project', $projectId, $user['full_name'] . " '$name' projesini oluşturdu ($code)");

        redirect('projects.php');
    }
}

// Handle Copy Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'copy_project') {
    verify_csrf();
    $sourceProjectId = $_POST['source_project_id'];
    $newCode = $_POST['new_project_code'];
    $newName = $_POST['new_project_name'];

    // Fetch source project
    $sourceProject = $db->fetchOne("SELECT * FROM projects WHERE id = ?", [$sourceProjectId]);

    if ($sourceProject && $newCode && $newName) {
        // Authorization check for department
        $department_id = $sourceProject['department_id'];
        if ($user['role'] !== 'admin' && $department_id) {
            $allowed = [$user['department_id']];
            if (!empty($user['managed_department_ids'])) {
                $allowed = array_merge($allowed, $user['managed_department_ids']);
            }
            if (!in_array($department_id, $allowed)) {
                $department_id = $user['department_id'];
            }
        }

        // 1. Create new project
        $db->execute(
            "INSERT INTO projects (project_code, name, type, department_id) VALUES (?, ?, ?, ?)",
            [$newCode, $newName, $sourceProject['type'], $department_id]
        );
        $newProjectId = $db->lastInsertId();

        // 2. Copy project steps (keep mapping for task assignment)
        $stepMapping = []; // old_step_id => new_step_id
        $sourceSteps = $db->query("SELECT * FROM project_steps WHERE project_id = ? ORDER BY order_index", [$sourceProjectId]);
        foreach ($sourceSteps as $step) {
            $db->execute(
                "INSERT INTO project_steps (project_id, name, order_index) VALUES (?, ?, ?)",
                [$newProjectId, $step['name'], $step['order_index']]
            );
            $stepMapping[$step['id']] = $db->lastInsertId();
        }

        // 3. Copy tasks with all fields
        $sourceTasks = $db->query("SELECT * FROM tasks WHERE project_id = ?", [$sourceProjectId]);
        foreach ($sourceTasks as $task) {
            // Map to new step if exists
            $newStepId = null;
            if ($task['project_step_id'] && isset($stepMapping[$task['project_step_id']])) {
                $newStepId = $stepMapping[$task['project_step_id']];
            }

            // Insert new task (reset status to 'Yeni' = 1, clear completion dates)
            $db->execute(
                "INSERT INTO tasks (title, description, priority_id, status_id, owner_id, requester_id, 
                 requesting_department_id, responsible_department_id, main_topic_id, sub_topic_id, 
                 target_completion_date, hashtags, project_id, project_step_id, order_index, created_at) 
                 VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $task['title'],
                    $task['description'],
                    $task['priority_id'],
                    $task['owner_id'],
                    $user['id'], // New requester is current user
                    $user['department_id'],
                    $task['responsible_department_id'],
                    $task['main_topic_id'],
                    $task['sub_topic_id'],
                    $task['target_completion_date'],
                    $task['hashtags'],
                    $newProjectId,
                    $newStepId,
                    $task['order_index']
                ]
            );
            $newTaskId = $db->lastInsertId();

            // 4. Copy checklists for this task
            $sourceChecklists = $db->query("SELECT * FROM task_checklist_items WHERE task_id = ? ORDER BY order_index", [$task['id']]);
            foreach ($sourceChecklists as $item) {
                $db->execute(
                    "INSERT INTO task_checklist_items (task_id, item_text, order_index, is_completed) VALUES (?, ?, ?, 0)",
                    [$newTaskId, $item['item_text'], $item['order_index']]
                );
            }
        }

        log_activity('copy', 'project', $newProjectId, $user['full_name'] . " '{$sourceProject['name']}' projesini '$newName' olarak kopyaladı");

        $_SESSION['success_message'] = "Proje başarıyla kopyalandı! " . count($sourceSteps) . " adım ve " . count($sourceTasks) . " görev kopyalandı.";
        redirect('projects.php');
    }
}

// Fetch Projects
$params = [];
$sql = "SELECT p.*, d.name as department_name,
        (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) as total_tasks,
        (SELECT COUNT(*) FROM tasks t LEFT JOIN statuses s ON t.status_id = s.id WHERE t.project_id = p.id AND s.kanban_column = 'done') as completed_tasks,
        (SELECT COUNT(*) FROM tasks t LEFT JOIN statuses s ON t.status_id = s.id WHERE t.project_id = p.id AND s.kanban_column != 'done') as open_tasks
        FROM projects p 
        LEFT JOIN departments d ON p.department_id = d.id";

// Filter by Department if not Admin
if ($user['role'] !== 'admin') {
    $deptIds = [$user['department_id']];
    if (!empty($user['managed_department_ids'])) {
        $deptIds = array_merge($deptIds, $user['managed_department_ids']);
    }

    // Allow seeing projects with NO department? (Maybe "General" projects?) 
    // Or strictly filter. Let's filter strictly + NULLs (global projects).
    $placeholders = implode(',', array_fill(0, count($deptIds), '?'));

    $sql .= " WHERE p.department_id IN ($placeholders) OR p.department_id IS NULL";
    $params = $deptIds;
}

$sql .= " ORDER BY p.created_at DESC";
$projects = $db->query($sql, $params);

// Fetch Departments for Form
$departments = $db->query("SELECT * FROM departments ORDER BY name");

$layout = 'fluid';
require SRC_DIR . '/partials/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Projeler</h1>
    <button onclick="toggleProjectForm()" class="btn btn-primary">+ Yeni Proje</button>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="glass glass-card"
        style="background: rgba(52, 211, 153, 0.1); border: 1px solid #34d399; margin-bottom: 20px; padding: 15px;">
        ✅ <?= escape($_SESSION['success_message']) ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<!-- New Project Form (Hidden by default) -->
<div id="newProjectForm" class="glass glass-card" style="display: none; margin-bottom: 20px;">
    <h3 style="margin-top: 0; margin-bottom: 15px;">Yeni Proje Oluştur</h3>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_project">

        <div class="form-group">
            <label>Proje Numarası</label>
            <input type="text" name="project_code" class="form-input" required placeholder="Örn: PRJ-2024-001">
        </div>

        <div class="form-group">
            <label>Proje İsmi</label>
            <input type="text" name="name" class="form-input" required placeholder="Proje Adı">
        </div>

        <div class="form-group">
            <label>Proje Tipi</label>
            <input type="text" name="type" class="form-input" placeholder="Örn: Ar-Ge, Üretim, Satış...">
        </div>

        <div class="form-group">
            <label>Bölüm</label>
            <select name="department_id" class="form-select" required>
                <?php foreach ($departments as $dept): ?>
                    <?php
                    // Filter options in dropdown for non-admins
                    if ($user['role'] !== 'admin') {
                        $allowed = [$user['department_id']];
                        if (!empty($user['managed_department_ids']))
                            $allowed = array_merge($allowed, $user['managed_department_ids']);
                        if (!in_array($dept['id'], $allowed))
                            continue;
                    }
                    ?>
                    <option value="<?= $dept['id'] ?>" <?= $dept['id'] == $user['department_id'] ? 'selected' : '' ?>>
                        <?= escape($dept['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="text-align: right; margin-top: 15px;">
            <button type="button" onclick="toggleProjectForm()" class="btn btn-glass"
                style="margin-right: 10px;">İptal</button>
            <button type="submit" class="btn btn-primary">Kaydet</button>
        </div>
    </form>
</div>

<div class="glass glass-card">
    <table class="table">
        <thead>
            <tr>
                <th>Proje No</th>
                <th>Proje İsmi</th>
                <th>Tip</th>
                <th class="text-center">Görevler (T/A/K)</th>
                <th>Bölüm</th>
                <th>Oluşturulma</th>
                <th>İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($projects as $p): ?>
                <tr>
                    <td style="font-weight: bold; color: var(--primary);"><?= escape($p['project_code']) ?></td>
                    <td>
                        <a href="project_detail.php?id=<?= $p['id'] ?>" class="link-white" style="font-size: 1.1rem;">
                            <?= escape($p['name']) ?>
                        </a>
                    </td>

                    <td><span class="status-badge"
                            style="background: rgba(255,255,255,0.1);"><?= escape($p['type']) ?></span></td>
                    <td class="text-center">
                        <span title="Toplam Görev"
                            style="color: var(--text-main); font-weight: bold;"><?= $p['total_tasks'] ?></span>
                        <span style="color: var(--text-muted); margin: 0 4px;">/</span>
                        <span title="Açık Görev" style="color: #fbbf24;"><?= $p['open_tasks'] ?></span>
                        <!-- Yellow/Orange -->
                        <span style="color: var(--text-muted); margin: 0 4px;">/</span>
                        <span title="Tamamlanan Görev" style="color: #34d399;"><?= $p['completed_tasks'] ?></span>
                        <!-- Green -->
                    </td>
                    <td class="text-muted"><?= escape($p['department_name'] ?? 'Genel') ?></td>
                    <td class="text-muted"><?= date('d.m.Y', strtotime($p['created_at'])) ?></td>
                    <td>
                        <a href="project_detail.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-glass">Detay & Yönet</a>
                        <button
                            onclick="openCopyModal(<?= $p['id'] ?>, '<?= escape($p['project_code']) ?>', '<?= escape($p['name']) ?>')"
                            class="btn btn-sm btn-glass" style="margin-left: 5px;" title="Projeyi Kopyala">
                            📋 Kopyala
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($projects)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted" style="padding: 20px;">Henüz proje bulunmuyor veya
                        yetkiniz yok.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    function toggleProjectForm() {
        const form = document.getElementById('newProjectForm');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }

    function openCopyModal(projectId, projectCode, projectName) {
        document.getElementById('source_project_id').value = projectId;
        document.getElementById('source_project_info').textContent = projectCode + ' - ' + projectName;

        // Auto-generate new code suggestion
        const today = new Date();
        const year = today.getFullYear();
        const suggestedCode = projectCode + '-COPY';
        document.getElementById('new_project_code').value = suggestedCode;
        document.getElementById('new_project_name').value = projectName + ' (Kopya)';

        document.getElementById('copyProjectModal').style.display = 'flex';
    }

    function closeCopyModal() {
        document.getElementById('copyProjectModal').style.display = 'none';
    }

    // Close modal on outside click
    document.getElementById('copyProjectModal')?.addEventListener('click', function (e) {
        if (e.target === this) closeCopyModal();
    });
</script>

<!-- Copy Project Modal -->
<div id="copyProjectModal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; justify-content: center; align-items: center;">
    <div class="glass glass-card" style="width: 500px; max-width: 90%;">
        <h3 style="margin-top: 0; margin-bottom: 20px;">📋 Proje Kopyala</h3>
        <p style="color: var(--text-muted); margin-bottom: 20px;">
            Kaynak: <strong id="source_project_info" style="color: var(--primary);"></strong><br>
            <small>Tüm adımlar, görevler ve kontrol listeleri kopyalanacaktır.</small>
        </p>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="copy_project">
            <input type="hidden" name="source_project_id" id="source_project_id">

            <div class="form-group">
                <label>Yeni Proje Kodu</label>
                <input type="text" name="new_project_code" id="new_project_code" class="form-input" required
                    placeholder="Örn: PRJ-2026-002">
            </div>

            <div class="form-group">
                <label>Yeni Proje İsmi</label>
                <input type="text" name="new_project_name" id="new_project_name" class="form-input" required
                    placeholder="Yeni proje adı">
            </div>

            <div style="text-align: right; margin-top: 20px;">
                <button type="button" onclick="closeCopyModal()" class="btn btn-glass"
                    style="margin-right: 10px;">İptal</button>
                <button type="submit" class="btn btn-primary">📋 Kopyala</button>
            </div>
        </form>
    </div>
</div>

<?php require SRC_DIR . '/partials/footer.php'; ?>