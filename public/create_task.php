<?php
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

check_auth();
$db = Database::getInstance();
$user = Auth::user();

// Fetch Options
$priorities = $db->query("SELECT * FROM priorities ORDER BY order_index");
$users = $db->query("SELECT id, full_name, department_id FROM users WHERE is_active = 1");
$departments = $db->query("SELECT * FROM departments");
$mainTopics = $db->query("SELECT * FROM main_topics");
$checklistTemplates = $db->query("SELECT * FROM checklist_templates ORDER BY name");

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

// Check for Project Context
$projectId = $_GET['project_id'] ?? null;
$stepId = $_GET['step_id'] ?? null;
$projectContext = null;

if ($projectId && $stepId) {
    $project = $db->fetchOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
    $step = $db->fetchOne("SELECT * FROM project_steps WHERE id = ?", [$stepId]);
    if ($project && $step) {
        $projectContext = $project['project_code'] . ' - ' . $step['name'];
    }
}

// Helper: Generate recurring dates
function generateRecurringDates($frequency, $startDate, $endDate, $weekDays = [], $monthDay = 1)
{
    $dates = [];
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);

    switch ($frequency) {
        case 'daily':
            $current = clone $start;
            while ($current <= $end) {
                $dow = (int) $current->format('N'); // 1=Mon, 7=Sun
                if ($dow >= 1 && $dow <= 5) { // Weekdays only
                    $dates[] = $current->format('Y-m-d');
                }
                $current->modify('+1 day');
            }
            break;

        case 'weekly':
            if (empty($weekDays))
                $weekDays = [(int) $start->format('N')];
            $current = clone $start;
            while ($current <= $end) {
                $dow = (int) $current->format('N');
                if (in_array($dow, $weekDays)) {
                    $dates[] = $current->format('Y-m-d');
                }
                $current->modify('+1 day');
            }
            break;

        case 'biweekly':
            if (empty($weekDays))
                $weekDays = [(int) $start->format('N')];
            $weekStart = clone $start;
            $weekStart->modify('monday this week');
            $weekNum = 0;
            $current = clone $start;
            while ($current <= $end) {
                $currentWeekStart = clone $current;
                $currentWeekStart->modify('monday this week');
                $weekDiff = (int) (($currentWeekStart->getTimestamp() - $weekStart->getTimestamp()) / (7 * 86400));
                if ($weekDiff % 2 === 0) {
                    $dow = (int) $current->format('N');
                    if (in_array($dow, $weekDays)) {
                        $dates[] = $current->format('Y-m-d');
                    }
                }
                $current->modify('+1 day');
            }
            break;

        case 'monthly':
            $current = clone $start;
            $current->setDate((int) $current->format('Y'), (int) $current->format('m'), min($monthDay, (int) $current->format('t')));
            if ($current < $start) {
                $current->modify('+1 month');
                $current->setDate((int) $current->format('Y'), (int) $current->format('m'), min($monthDay, (int) $current->format('t')));
            }
            while ($current <= $end) {
                $dates[] = $current->format('Y-m-d');
                $current->modify('+1 month');
                $current->setDate((int) $current->format('Y'), (int) $current->format('m'), min($monthDay, (int) $current->format('t')));
            }
            break;

        case 'quarterly':
            $current = clone $start;
            $current->setDate((int) $current->format('Y'), (int) $current->format('m'), min($monthDay, (int) $current->format('t')));
            if ($current < $start) {
                $current->modify('+3 months');
                $current->setDate((int) $current->format('Y'), (int) $current->format('m'), min($monthDay, (int) $current->format('t')));
            }
            while ($current <= $end) {
                $dates[] = $current->format('Y-m-d');
                $current->modify('+3 months');
                $current->setDate((int) $current->format('Y'), (int) $current->format('m'), min($monthDay, (int) $current->format('t')));
            }
            break;

        case 'semiannual':
            $current = clone $start;
            $current->setDate((int) $current->format('Y'), (int) $current->format('m'), min($monthDay, (int) $current->format('t')));
            if ($current < $start) {
                $current->modify('+6 months');
                $current->setDate((int) $current->format('Y'), (int) $current->format('m'), min($monthDay, (int) $current->format('t')));
            }
            while ($current <= $end) {
                $dates[] = $current->format('Y-m-d');
                $current->modify('+6 months');
                $current->setDate((int) $current->format('Y'), (int) $current->format('m'), min($monthDay, (int) $current->format('t')));
            }
            break;
    }

    return $dates;
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $title = $_POST['title'];
    $description = $_POST['description'];
    $priority_id = $_POST['priority_id'];
    $owner_id = $_POST['owner_id'];
    $target_date = $_POST['target_date'] ?: null;
    $main_topic_id = $_POST['main_topic_id'] ?: null;
    $hashtags = $_POST['hashtags'] ?? '';
    $initial_comment = $_POST['initial_comment'] ?? '';

    // Recurring fields
    $is_recurring = ($_POST['is_recurring'] ?? '0') === '1';
    $recurring_frequency = $_POST['recurring_frequency'] ?? 'weekly';
    $recurring_start = $_POST['recurring_start'] ?? date('Y-m-d');
    $recurring_end = $_POST['recurring_end'] ?? date('Y') . '-12-31';
    $recurring_weekdays = $_POST['recurring_weekdays'] ?? [];
    $recurring_monthday = (int) ($_POST['recurring_monthday'] ?? 1);

    // Project context from POST (to persist through submission)
    $p_id = $_POST['project_id'] ?: null;
    $s_id = $_POST['step_id'] ?: null;

    // Validations (Basic)
    if (empty($title) || empty($description) || empty($priority_id) || empty($owner_id) || empty($main_topic_id)) {
        $error = "Lütfen tüm alanları doldurun.";
    } else {
        // Find owner's department
        $owner = $db->fetchOne("SELECT department_id FROM users WHERE id = ?", [$owner_id]);
        $responsible_dept_id = $owner['department_id'];

        try {
            if ($is_recurring) {
                // === RECURRING TASK CREATION ===
                $weekDaysInt = array_map('intval', $recurring_weekdays);
                $dates = generateRecurringDates($recurring_frequency, $recurring_start, $recurring_end, $weekDaysInt, $recurring_monthday);

                if (empty($dates)) {
                    $error = "Seçilen kurallara göre oluşturulacak görev bulunamadı. Lütfen tarih aralığını ve kuralları kontrol edin.";
                } else {
                    $group_id = bin2hex(random_bytes(16)); // unique group id
                    $created_count = 0;
                    $checklist_id = $_POST['checklist_id'] ?: null;
                    $checklist_items = [];
                    if ($checklist_id) {
                        $checklist_items = $db->query("SELECT * FROM checklist_template_items WHERE template_id = ? ORDER BY order_index", [$checklist_id]);
                    }

                    // Handle Attachments (upload once, link to first task only)
                    $first_task_id = null;

                    foreach ($dates as $date) {
                        $dateFormatted = date('d.m.Y', strtotime($date));
                        $taskTitle = $title . ' - ' . $dateFormatted;

                        $sql = "INSERT INTO tasks (title, description, priority_id, status_id, owner_id, requester_id, requesting_department_id, responsible_department_id, main_topic_id, target_completion_date, hashtags, project_id, project_step_id, recurring_group_id, created_at) 
                                VALUES (:title, :description, :priority_id, 1, :owner_id, :requester_id, :requesting_department_id, :responsible_department_id, :main_topic_id, :target_date, :hashtags, :project_id, :project_step_id, :recurring_group_id, NOW())";

                        $db->execute($sql, [
                            ':title' => $taskTitle,
                            ':description' => $description,
                            ':priority_id' => $priority_id,
                            ':owner_id' => $owner_id,
                            ':requester_id' => $user['id'],
                            ':requesting_department_id' => $user['department_id'],
                            ':responsible_department_id' => $responsible_dept_id,
                            ':main_topic_id' => $main_topic_id,
                            ':target_date' => $date,
                            ':hashtags' => $hashtags,
                            ':project_id' => $p_id,
                            ':project_step_id' => $s_id,
                            ':recurring_group_id' => $group_id
                        ]);

                        $task_id = $db->lastInsertId();
                        if ($first_task_id === null)
                            $first_task_id = $task_id;

                        // Copy checklist to each task
                        foreach ($checklist_items as $item) {
                            $db->execute(
                                "INSERT INTO task_checklist_items (task_id, item_text, order_index) VALUES (?, ?, ?)",
                                [$task_id, $item['item_text'], $item['order_index']]
                            );
                        }

                        // Add initial comment to each task
                        if (!empty($initial_comment)) {
                            $db->execute(
                                "INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)",
                                [$task_id, $user['id'], $initial_comment]
                            );
                        }

                        $created_count++;
                    }

                    // Upload attachments to first task only
                    if ($first_task_id && !empty($_FILES['attachments']['name'][0])) {
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
                                        [$first_task_id, $name, 'uploads/' . $fileName, $size, $user['id']]
                                    );
                                }
                            }
                        }
                    }

                    log_activity('create', 'task', $first_task_id, $user['full_name'] . " '$title' tekrarlayan görevini oluşturdu ($created_count adet)");

                    // Store success info in session for display
                    $_SESSION['recurring_success'] = [
                        'count' => $created_count,
                        'title' => $title,
                        'group_id' => $group_id,
                        'first_date' => $dates[0],
                        'last_date' => end($dates)
                    ];

                    redirect('create_task.php?recurring_success=1');
                }

            } else {
                // === SINGLE TASK CREATION (original logic) ===
                $sql = "INSERT INTO tasks (title, description, priority_id, status_id, owner_id, requester_id, requesting_department_id, responsible_department_id, main_topic_id, target_completion_date, hashtags, project_id, project_step_id, created_at) 
                        VALUES (:title, :description, :priority_id, 1, :owner_id, :requester_id, :requesting_department_id, :responsible_department_id, :main_topic_id, :target_date, :hashtags, :project_id, :project_step_id, NOW())";

                $db->execute($sql, [
                    ':title' => $title,
                    ':description' => $description,
                    ':priority_id' => $priority_id,
                    ':owner_id' => $owner_id,
                    ':requester_id' => $user['id'],
                    ':requesting_department_id' => $user['department_id'],
                    ':responsible_department_id' => $responsible_dept_id,
                    ':main_topic_id' => $main_topic_id,
                    ':target_date' => $target_date,
                    ':hashtags' => $hashtags,
                    ':project_id' => $p_id,
                    ':project_step_id' => $s_id
                ]);

                $task_id = $db->lastInsertId();

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
                                    [$task_id, $name, 'uploads/' . $fileName, $size, $user['id']]
                                );
                            }
                        }
                    }
                }

                // Handle Initial Comment
                if (!empty($initial_comment)) {
                    $db->execute(
                        "INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)",
                        [$task_id, $user['id'], $initial_comment]
                    );
                }

                // Handle Checklist
                $checklist_id = $_POST['checklist_id'] ?: null;
                if ($checklist_id) {
                    $items = $db->query("SELECT * FROM checklist_template_items WHERE template_id = ? ORDER BY order_index", [$checklist_id]);
                    foreach ($items as $item) {
                        $db->execute(
                            "INSERT INTO task_checklist_items (task_id, item_text, order_index) VALUES (?, ?, ?)",
                            [$task_id, $item['item_text'], $item['order_index']]
                        );
                    }
                }

                // Log activity
                log_activity('create', 'task', $task_id, $user['full_name'] . " '$title' görevini oluşturdu");

                // Redirect based on context
                if ($p_id) {
                    redirect("project_detail.php?id=$p_id");
                } else {
                    redirect('index.php');
                }
            }

        } catch (Exception $e) {
            $error = "Hata oluştu: " . $e->getMessage();
        }
    }
}

// Check for recurring success message
$recurringSuccess = null;
if (isset($_GET['recurring_success']) && isset($_SESSION['recurring_success'])) {
    $recurringSuccess = $_SESSION['recurring_success'];
    unset($_SESSION['recurring_success']);
}

require SRC_DIR . '/partials/header.php';
?>

<div class="glass glass-card" style="max-width: 1200px; margin: 0 auto;">
    <h2 class="form-title">Yeni Görev Oluştur</h2>

    <?php if ($recurringSuccess): ?>
        <div style="
            background: linear-gradient(135deg, rgba(16,185,129,0.15), rgba(59,130,246,0.10));
            border: 1px solid rgba(16,185,129,0.4);
            padding: 20px 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            animation: slideDown 0.4s ease;
        ">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                <span style="font-size: 2rem;">🎉</span>
                <div>
                    <div style="font-size: 1.15rem; font-weight: 700; color: #10b981;">Tekrarlayan Görevler Oluşturuldu!
                    </div>
                    <div style="color: var(--text-muted); font-size: 0.9rem; margin-top: 2px;">
                        <strong style="color: var(--text-main);"><?= escape($recurringSuccess['title']) ?></strong>
                        başlığıyla
                        <strong style="color: #10b981;"><?= $recurringSuccess['count'] ?></strong> adet görev oluşturuldu.
                    </div>
                </div>
            </div>
            <div style="display: flex; gap: 16px; font-size: 0.85rem; color: var(--text-muted); padding-left: 44px;">
                <span>📅 İlk: <?= date('d.m.Y', strtotime($recurringSuccess['first_date'])) ?></span>
                <span>📅 Son: <?= date('d.m.Y', strtotime($recurringSuccess['last_date'])) ?></span>
            </div>
            <div style="padding-left: 44px; margin-top: 12px;">
                <a href="index.php" class="btn btn-primary" style="font-size: 0.85rem; padding: 8px 16px;">📊 Görevleri
                    Görüntüle</a>
            </div>
        </div>
        <style>
            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        </style>
    <?php endif; ?>

    <?php if ($projectContext): ?>
        <div
            style="background: rgba(var(--primary-rgb), 0.1); border: 1px solid var(--primary); padding: 10px 15px; border-radius: 8px; margin-bottom: 20px; color: var(--primary); font-weight: 500;">
            🚀 Bu görev <strong><?= escape($projectContext) ?></strong> adımı için oluşturuluyor.
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <p class="text-danger" style="margin-bottom: 20px;">
            <?= $error ?>
        </p>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="create-task-grid">
            <!-- Left Column: Primary Content -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Başlık</label>
                    <input type="text" name="title" required placeholder="Görevin başlığı ne olsun?"
                        style="font-size: 1.1rem; padding: 15px;">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label>Açıklama</label>
                    <textarea name="description" rows="12" placeholder="Detaylı açıklama..."
                        style="resize: vertical; min-height: 200px;"></textarea>
                </div>

                <!-- Compact File Upload -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Dosya Ekle</label>
                    <input type="file" id="file-input-create" name="attachments[]" multiple style="display: none;">

                    <div id="drop-zone-create" onclick="document.getElementById('file-input-create').click()" style="
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
                        min-height: 80px;
                    " onmouseover="this.style.borderColor='var(--primary)'; this.style.background='rgba(79, 70, 229, 0.05)'"
                        onmouseout="this.style.borderColor='var(--glass-border)'; this.style.background='rgba(0,0,0,0.1)'">

                        <!-- Drag Overlay -->
                        <div id="drag-overlay-create" style="
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
                            <div style="color: var(--text-muted); font-size: 0.85rem;" id="file-list-placeholder">
                                Sürükleyip bırakın veya seçin</div>
                            <div id="file-list-create"
                                style="font-size: 0.85rem; color: var(--primary); margin-top: 2px;"></div>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label>Başlangıç Yorumu (Opsiyonel)</label>
                    <textarea name="initial_comment" rows="2" placeholder="Ekstra not ekleyin..."
                        style="min-height: 60px;"></textarea>
                </div>
            </div>

            <!-- Right Column: Metadata & Settings -->
            <div
                style="background: rgba(255,255,255,0.03); border-radius: 12px; padding: 20px; border: 1px solid var(--glass-border); height: fit-content;">
                <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 1.1rem; color: var(--text-muted);">Görev
                    Detayları</h3>

                <div class="form-group">
                    <label style="font-size: 0.85rem;">Öncelik</label>
                    <div style="position: relative;">
                        <select name="priority_id" style="width: 100%;">
                            <?php foreach ($priorities as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $p['name'] == 'Normal' ? 'selected' : '' ?>>
                                    <?= $p['name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label style="font-size: 0.85rem;">Hedef Tarih</label>
                    <input type="date" name="target_date" style="width: 100%;">
                </div>

                <div class="form-group">
                    <label style="font-size: 0.85rem;">Sorumlu Kişi</label>
                    <select name="owner_id" required style="width: 100%;">
                        <option value="">Seçiniz...</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= (isset($_POST['owner_id']) && $_POST['owner_id'] == $u['id']) || (!isset($_POST['owner_id']) && $u['id'] == $user['id']) ? 'selected' : '' ?>>
                                <?= $u['full_name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label style="font-size: 0.85rem;">Konu / Kategori</label>
                    <select name="main_topic_id" style="width: 100%;">
                        <option value="">Seçiniz...</option>
                        <?php foreach ($mainTopics as $t): ?>
                            <option value="<?= $t['id'] ?>">
                                <?= $t['name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label style="font-size: 0.85rem;">Etiketler</label>
                    <input type="text" name="hashtags" placeholder="#acil #analiz" style="width: 100%;">
                </div>

                <div class="form-group">
                    <label style="font-size: 0.85rem;">Şablon Seç (Opsiyonel)</label>
                    <select name="checklist_id" id="checklistSelect" style="width: 100%;">
                        <option value="">Yok</option>
                        <?php foreach ($checklistTemplates as $ct): ?>
                            <option value="<?= $ct['id'] ?>" data-dept="<?= $ct['department_id'] ?>">
                                <?= escape($ct['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!$projectContext): ?>
                    <div class="form-group">
                        <label style="font-size: 0.85rem;">Proje (Opsiyonel)</label>
                        <select name="project_id" id="projectSelect" style="width: 100%;" onchange="loadProjectSteps()">
                            <option value="">Proje Seçiniz...</option>
                            <?php foreach ($projects as $proj): ?>
                                <option value="<?= $proj['id'] ?>">
                                    <?= escape($proj['project_code']) ?> - <?= escape($proj['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="stepSelectContainer" style="display: none;">
                        <label style="font-size: 0.85rem;">Proje Adımı</label>
                        <select name="step_id" id="stepSelect" style="width: 100%;">
                            <option value="">Adım Seçiniz...</option>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="project_id" value="<?= $projectId ?>">
                    <input type="hidden" name="step_id" value="<?= $stepId ?>">
                <?php endif; ?>

                <!-- Recurring Task Section -->
                <div style="border-top: 1px solid var(--glass-border); margin-top: 8px; padding-top: 16px;">
                    <div class="form-group">
                        <label style="font-size: 0.85rem;">🔄 Tekrarlayan Görev</label>
                        <select name="is_recurring" id="isRecurring" style="width: 100%;" onchange="toggleRecurring()">
                            <option value="0">Hayır</option>
                            <option value="1">Evet</option>
                        </select>
                    </div>

                    <div id="recurringOptions" style="display: none; animation: slideDown 0.3s ease;">
                        <div class="form-group">
                            <label style="font-size: 0.85rem;">Tekrar Sıklığı</label>
                            <select name="recurring_frequency" id="recurringFrequency" style="width: 100%;"
                                onchange="onFrequencyChange()">
                                <option value="daily">📅 Günlük (İş Günleri)</option>
                                <option value="weekly" selected>📆 Haftalık</option>
                                <option value="biweekly">📆 2 Haftada Bir</option>
                                <option value="monthly">🗓️ Aylık</option>
                                <option value="quarterly">📊 3 Aylık</option>
                                <option value="semiannual">📅 6 Aylık</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="font-size: 0.85rem;">Başlangıç Tarihi</label>
                            <input type="date" name="recurring_start" id="recurringStart" style="width: 100%;"
                                value="<?= date('Y-m-d') ?>" onchange="updatePreview()">
                        </div>

                        <div class="form-group">
                            <label style="font-size: 0.85rem;">Bitiş Tarihi</label>
                            <input type="date" name="recurring_end" id="recurringEnd" style="width: 100%;"
                                value="<?= date('Y') ?>-12-31" onchange="updatePreview()">
                        </div>

                        <!-- Weekday picker (for weekly / biweekly) -->
                        <div class="form-group" id="weekdayPicker" style="display: none;">
                            <label style="font-size: 0.85rem;">Hangi Günler?</label>
                            <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px;">
                                <label class="weekday-chip"><input type="checkbox" name="recurring_weekdays[]" value="1"
                                        onchange="updatePreview()"> Pzt</label>
                                <label class="weekday-chip"><input type="checkbox" name="recurring_weekdays[]" value="2"
                                        onchange="updatePreview()"> Sal</label>
                                <label class="weekday-chip"><input type="checkbox" name="recurring_weekdays[]" value="3"
                                        onchange="updatePreview()"> Çar</label>
                                <label class="weekday-chip"><input type="checkbox" name="recurring_weekdays[]" value="4"
                                        onchange="updatePreview()"> Per</label>
                                <label class="weekday-chip"><input type="checkbox" name="recurring_weekdays[]" value="5"
                                        onchange="updatePreview()"> Cum</label>
                            </div>
                        </div>

                        <!-- Month day picker (for monthly / quarterly) -->
                        <div class="form-group" id="monthdayPicker" style="display: none;">
                            <label style="font-size: 0.85rem;">Ayın Kaçıncı Günü?</label>
                            <input type="number" name="recurring_monthday" id="recurringMonthday" min="1" max="31"
                                value="1" style="width: 100%;" onchange="updatePreview()">
                        </div>

                        <!-- Preview -->
                        <div id="recurringPreview" style="
                            background: linear-gradient(135deg, rgba(79,70,229,0.08), rgba(59,130,246,0.08));
                            border: 1px solid rgba(79,70,229,0.2);
                            border-radius: 10px;
                            padding: 14px 16px;
                            margin-top: 8px;
                            transition: all 0.3s ease;
                        ">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                <span style="font-size: 1.3rem;">📋</span>
                                <span
                                    style="font-weight: 600; font-size: 0.95rem; color: var(--primary);">Önizleme</span>
                            </div>
                            <div id="previewText"
                                style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.6;">Ayarları yapınca
                                burada kaç görev oluşturulacağını göreceksiniz.</div>
                        </div>
                    </div>
                </div>

                <style>
                    .weekday-chip {
                        display: inline-flex;
                        align-items: center;
                        gap: 4px;
                        padding: 6px 10px;
                        border-radius: 20px;
                        font-size: 0.8rem;
                        font-weight: 500;
                        cursor: pointer;
                        background: rgba(255, 255, 255, 0.05);
                        border: 1px solid var(--glass-border);
                        transition: all 0.2s ease;
                        user-select: none;
                    }

                    .weekday-chip:hover {
                        border-color: var(--primary);
                        background: rgba(79, 70, 229, 0.1);
                    }

                    .weekday-chip input[type="checkbox"] {
                        width: 14px;
                        height: 14px;
                        accent-color: var(--primary);
                    }

                    .weekday-chip:has(input:checked) {
                        background: rgba(79, 70, 229, 0.15);
                        border-color: var(--primary);
                        color: var(--primary);
                    }

                    @keyframes slideDown {
                        from {
                            opacity: 0;
                            transform: translateY(-8px);
                        }

                        to {
                            opacity: 1;
                            transform: translateY(0);
                        }
                    }
                </style>
            </div>
        </div>

        <script>
            // File Upload Logic
            const dropZoneCreate = document.getElementById('drop-zone-create');
            const dragOverlayCreate = document.getElementById('drag-overlay-create');
            const fileInputCreate = document.getElementById('file-input-create');
            const fileListCreate = document.getElementById('file-list-create');
            const fileListPlaceholder = document.getElementById('file-list-placeholder');

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZoneCreate.addEventListener(eventName, preventDefaultsCreate, false);
                document.body.addEventListener(eventName, preventDefaultsCreate, false);
            });

            function preventDefaultsCreate(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            ['dragenter', 'dragover'].forEach(eventName => {
                dropZoneCreate.addEventListener(eventName, highlightCreate, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZoneCreate.addEventListener(eventName, unhighlightCreate, false);
            });

            function highlightCreate(e) {
                dragOverlayCreate.style.display = 'flex';
            }

            function unhighlightCreate(e) {
                dragOverlayCreate.style.display = 'none';
            }

            dropZoneCreate.addEventListener('drop', handleDropCreate, false);

            function handleDropCreate(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                fileInputCreate.files = files;
                updateFileListCreate(files);
            }

            fileInputCreate.addEventListener('change', function () {
                updateFileListCreate(this.files);
            });

            function updateFileListCreate(files) {
                if (files.length > 0) {
                    const names = Array.from(files).map(f => f.name).join(', ');
                    fileListCreate.innerHTML = `<strong>${files.length} dosya seçildi:</strong> ${names}`;
                    fileListPlaceholder.style.display = 'none';
                } else {
                    fileListCreate.innerHTML = '';
                    fileListPlaceholder.style.display = 'block';
                }
            }

            // Checklist Filtering Logic
            const users = <?= json_encode($users) ?>;
            const userSelect = document.querySelector('select[name="owner_id"]');
            const checklistSelect = document.getElementById('checklistSelect');
            const checklistOptions = Array.from(checklistSelect.options);

            function filterChecklists() {
                const selectedUserId = userSelect.value;
                if (!selectedUserId) return;

                const user = users.find(u => u.id == selectedUserId);
                if (!user) return;

                const deptId = user.department_id;

                checklistSelect.value = "";
                checklistOptions.forEach(opt => {
                    if (opt.value === "") return;
                    const optDept = opt.getAttribute('data-dept');
                    if (!optDept || optDept == deptId) {
                        opt.style.display = '';
                    } else {
                        opt.style.display = 'none';
                    }
                });
            }

            userSelect.addEventListener('change', filterChecklists);
            if (userSelect.value) filterChecklists();

            // Project Steps Logic
            const allSteps = <?= json_encode($allSteps) ?>;
            const projectSelect = document.getElementById('projectSelect');
            const stepSelect = document.getElementById('stepSelect');
            const stepContainer = document.getElementById('stepSelectContainer');

            function loadProjectSteps() {
                if (!projectSelect) return;
                const projectId = projectSelect.value;

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
                        stepSelect.appendChild(option);
                    });
                    stepContainer.style.display = 'block';
                } else {
                    stepContainer.style.display = 'none';
                }
            }

            // === RECURRING TASK LOGIC ===
            function toggleRecurring() {
                const isRecurring = document.getElementById('isRecurring').value === '1';
                const options = document.getElementById('recurringOptions');
                const targetDateInput = document.querySelector('input[name="target_date"]');

                if (isRecurring) {
                    options.style.display = 'block';
                    options.style.animation = 'slideDown 0.3s ease';
                    // Hide individual target date when recurring
                    targetDateInput.closest('.form-group').style.opacity = '0.4';
                    targetDateInput.closest('.form-group').style.pointerEvents = 'none';
                    onFrequencyChange();
                    updatePreview();
                } else {
                    options.style.display = 'none';
                    targetDateInput.closest('.form-group').style.opacity = '1';
                    targetDateInput.closest('.form-group').style.pointerEvents = 'auto';
                }
            }

            function onFrequencyChange() {
                const freq = document.getElementById('recurringFrequency').value;
                const weekdayPicker = document.getElementById('weekdayPicker');
                const monthdayPicker = document.getElementById('monthdayPicker');

                weekdayPicker.style.display = (freq === 'weekly' || freq === 'biweekly') ? 'block' : 'none';
                monthdayPicker.style.display = (freq === 'monthly' || freq === 'quarterly' || freq === 'semiannual') ? 'block' : 'none';

                updatePreview();
            }

            function updatePreview() {
                const freq = document.getElementById('recurringFrequency').value;
                const startStr = document.getElementById('recurringStart').value;
                const endStr = document.getElementById('recurringEnd').value;
                const previewText = document.getElementById('previewText');

                if (!startStr || !endStr) {
                    previewText.innerHTML = 'Tarih aralığı seçiniz.';
                    return;
                }

                const start = new Date(startStr);
                const end = new Date(endStr);

                if (start > end) {
                    previewText.innerHTML = '<span style="color: #ef4444;">⚠️ Bitiş tarihi başlangıçtan önce olamaz.</span>';
                    return;
                }

                // Get selected weekdays
                const weekdayCheckboxes = document.querySelectorAll('input[name="recurring_weekdays[]"]');
                const selectedDays = Array.from(weekdayCheckboxes).filter(cb => cb.checked).map(cb => parseInt(cb.value));

                const monthDay = parseInt(document.getElementById('recurringMonthday')?.value) || 1;

                // Calculate dates client-side (mirror of PHP logic)
                let dates = [];
                let current = new Date(start);

                switch (freq) {
                    case 'daily':
                        while (current <= end) {
                            const dow = current.getDay(); // 0=Sun, 6=Sat
                            if (dow >= 1 && dow <= 5) dates.push(new Date(current));
                            current.setDate(current.getDate() + 1);
                        }
                        break;

                    case 'weekly':
                    case 'biweekly': {
                        const activeDays = selectedDays.length > 0 ? selectedDays : [getISODay(start)];
                        const weekStart = new Date(start);
                        weekStart.setDate(weekStart.getDate() - (weekStart.getDay() === 0 ? 6 : weekStart.getDay() - 1));

                        current = new Date(start);
                        while (current <= end) {
                            const isoDay = getISODay(current);
                            if (activeDays.includes(isoDay)) {
                                if (freq === 'weekly') {
                                    dates.push(new Date(current));
                                } else {
                                    // biweekly: check week parity
                                    const curWeekStart = new Date(current);
                                    curWeekStart.setDate(curWeekStart.getDate() - (curWeekStart.getDay() === 0 ? 6 : curWeekStart.getDay() - 1));
                                    const weekDiff = Math.round((curWeekStart - weekStart) / (7 * 86400000));
                                    if (weekDiff % 2 === 0) dates.push(new Date(current));
                                }
                            }
                            current.setDate(current.getDate() + 1);
                        }
                        break;
                    }

                    case 'monthly': {
                        current = new Date(start.getFullYear(), start.getMonth(), Math.min(monthDay, daysInMonth(start.getFullYear(), start.getMonth())));
                        if (current < start) {
                            current.setMonth(current.getMonth() + 1);
                            current.setDate(Math.min(monthDay, daysInMonth(current.getFullYear(), current.getMonth())));
                        }
                        while (current <= end) {
                            dates.push(new Date(current));
                            current.setMonth(current.getMonth() + 1);
                            current.setDate(Math.min(monthDay, daysInMonth(current.getFullYear(), current.getMonth())));
                        }
                        break;
                    }

                    case 'quarterly': {
                        current = new Date(start.getFullYear(), start.getMonth(), Math.min(monthDay, daysInMonth(start.getFullYear(), start.getMonth())));
                        if (current < start) {
                            current.setMonth(current.getMonth() + 3);
                            current.setDate(Math.min(monthDay, daysInMonth(current.getFullYear(), current.getMonth())));
                        }
                        while (current <= end) {
                            dates.push(new Date(current));
                            current.setMonth(current.getMonth() + 3);
                            current.setDate(Math.min(monthDay, daysInMonth(current.getFullYear(), current.getMonth())));
                        }
                        break;
                    }

                    case 'semiannual': {
                        current = new Date(start.getFullYear(), start.getMonth(), Math.min(monthDay, daysInMonth(start.getFullYear(), start.getMonth())));
                        if (current < start) {
                            current.setMonth(current.getMonth() + 6);
                            current.setDate(Math.min(monthDay, daysInMonth(current.getFullYear(), current.getMonth())));
                        }
                        while (current <= end) {
                            dates.push(new Date(current));
                            current.setMonth(current.getMonth() + 6);
                            current.setDate(Math.min(monthDay, daysInMonth(current.getFullYear(), current.getMonth())));
                        }
                        break;
                    }
                }

                // Render preview
                if (dates.length === 0) {
                    previewText.innerHTML = '<span style="color: #f59e0b;">⚠️ Bu kurallara göre oluşturulacak görev yok.</span>';
                } else {
                    const freqLabels = {
                        'daily': 'Günlük (İş Günleri)',
                        'weekly': 'Haftalık',
                        'biweekly': '2 Haftada Bir',
                        'monthly': 'Aylık',
                        'quarterly': '3 Aylık',
                        'semiannual': '6 Aylık'
                    };
                    const firstDate = formatDateTR(dates[0]);
                    const lastDate = formatDateTR(dates[dates.length - 1]);

                    previewText.innerHTML = `
                        <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary); margin-bottom: 4px;">${dates.length} görev</div>
                        <div>📅 ${freqLabels[freq]} olarak oluşturulacak</div>
                        <div style="margin-top: 4px;">İlk: <strong>${firstDate}</strong> → Son: <strong>${lastDate}</strong></div>
                    `;
                }
            }

            function getISODay(date) {
                const d = date.getDay();
                return d === 0 ? 7 : d; // Convert to ISO: 1=Mon, 7=Sun
            }

            function daysInMonth(year, month) {
                return new Date(year, month + 1, 0).getDate();
            }

            function formatDateTR(date) {
                const d = date.getDate().toString().padStart(2, '0');
                const m = (date.getMonth() + 1).toString().padStart(2, '0');
                return `${d}.${m}.${date.getFullYear()}`;
            }
        </script>



        <div class="form-actions">
            <a href="index.php" class="btn btn-outline">İptal</a>
            <button type="submit" class="btn btn-primary">Oluştur</button>
        </div>
    </form>
</div>

<?php require SRC_DIR . '/partials/footer.php'; ?>