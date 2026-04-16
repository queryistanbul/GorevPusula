<?php
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

check_auth();
$user = Auth::user();

// only admin
if ($user['role'] !== 'admin') {
    redirect('index.php');
}

$db = Database::getInstance();
$error = null;
$success = null;

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add_template') {
                $dept_id = $_POST['department_id'] ?: null; // Handle empty as null (general) - though requirements say per department
                if (empty($dept_id))
                    throw new Exception("Bölüm seçimi zorunludur.");

                $name = $_POST['name'];
                if (empty($name))
                    throw new Exception("Liste adı zorunludur.");

                $db->execute("INSERT INTO checklist_templates (department_id, name) VALUES (?, ?)", [$dept_id, $name]);
                $success = "Kontrol listesi şablonu eklendi.";

            } elseif ($_POST['action'] === 'delete_template') {
                $id = $_POST['template_id'];
                $db->execute("DELETE FROM checklist_templates WHERE id = ?", [$id]);
                $success = "Şablon silindi.";

            } elseif ($_POST['action'] === 'add_item') {
                $template_id = $_POST['template_id'];
                $text = $_POST['item_text'];

                // Get current max order
                $max = $db->fetchOne("SELECT MAX(order_index) as m FROM checklist_template_items WHERE template_id = ?", [$template_id]);
                $order = ($max['m'] ?? 0) + 1;

                $db->execute("INSERT INTO checklist_template_items (template_id, item_text, order_index) VALUES (?, ?, ?)", [$template_id, $text, $order]);
                $success = "Madde eklendi. (Popup kapanırsa sayfayı yenileyin)";

            } elseif ($_POST['action'] === 'delete_item') {
                $id = $_POST['item_id'];
                $db->execute("DELETE FROM checklist_template_items WHERE id = ?", [$id]);
                $success = "Madde silindi.";
            }

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Fetch Departments
$departments = $db->query("SELECT * FROM departments ORDER BY name");

// Fetch Checklists with Department Name
$sql = "SELECT c.*, d.name as department_name 
        FROM checklist_templates c 
        LEFT JOIN departments d ON c.department_id = d.id 
        ORDER BY d.name, c.name";
$templates = $db->query($sql);

// If viewing details of a template
$viewTemplate = null;
if (isset($_GET['view_id'])) {
    $viewTemplate = $db->fetchOne("SELECT * FROM checklist_templates WHERE id = ?", [$_GET['view_id']]);
    if ($viewTemplate) {
        $viewTemplate['items'] = $db->query("SELECT * FROM checklist_template_items WHERE template_id = ? ORDER BY order_index", [$viewTemplate['id']]);
    }
}

require SRC_DIR . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Kontrol Listeleri</h1>
        <p class="page-subtitle">Bölüm bazlı kontrol listesi şablonlarını yönetin.</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert-error">
        <?= $error ?>
    </div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert-success">
        <?= $success ?>
    </div>
<?php endif; ?>

<div class="form-grid-2">
    <!-- Left: Create & List -->
    <div>
        <!-- Create Form -->
        <div class="glass glass-card" style="margin-bottom: 20px;">
            <h3 class="card-title">Yeni Şablon Ekle</h3>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_template">

                <div class="form-group">
                    <label>Bölüm</label>
                    <select name="department_id" class="form-select" required>
                        <option value="">Seçiniz...</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>">
                                <?= escape($d['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Liste Adı</label>
                    <input type="text" name="name" class="form-input" required
                        placeholder="Örn: İşe Başlama Kontrol Listesi">
                </div>

                <button type="submit" class="btn btn-primary btn-block">Şablon Oluştur</button>
            </form>
        </div>

        <!-- List -->
        <div class="glass glass-card">
            <h3 class="card-title">Mevcut Şablonlar</h3>
            <?php if (empty($templates)): ?>
                <p class="text-muted">Henüz şablon eklenmemiş.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php foreach ($templates as $t): ?>
                        <div style="
                            padding: 10px; 
                            background: rgba(255,255,255,0.05); 
                            border-radius: 8px; 
                            border: 1px solid <?= ($viewTemplate && $viewTemplate['id'] == $t['id']) ? 'var(--primary)' : 'transparent' ?>;
                            display: flex; 
                            justify-content: space-between; 
                            align-items: center;
                            cursor: pointer;
                        " onclick="window.location='?view_id=<?= $t['id'] ?>'">
                            <div>
                                <div style="font-weight: 500;">
                                    <?= escape($t['name']) ?>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">
                                    <?= escape($t['department_name'] ?? 'Genel') ?>
                                </div>
                            </div>
                            <div style="font-size: 1.2rem;">&rsaquo;</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: Detail & Items -->
    <div>
        <?php if ($viewTemplate): ?>
            <div class="glass glass-card">
                <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <h3 class="card-title" style="margin: 0;">
                        <?= escape($viewTemplate['name']) ?> - Maddeler
                    </h3>
                    <form method="POST" onsubmit="return confirm('Bu şablonu silmek istediğinize emin misiniz?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_template">
                        <input type="hidden" name="template_id" value="<?= $viewTemplate['id'] ?>">
                        <button type="submit" class="btn-text-danger" style="font-size: 0.9rem;">Şablonu Sil</button>
                    </form>
                </div>

                <!-- Add Item Form -->
                <form method="POST" style="margin-bottom: 20px; display: flex; gap: 10px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_item">
                    <input type="hidden" name="template_id" value="<?= $viewTemplate['id'] ?>">
                    <input type="text" name="item_text" class="form-input" required placeholder="Yeni madde ekle..."
                        style="flex: 1;">
                    <button type="submit" class="btn btn-sm btn-primary">Ekle</button>
                </form>

                <!-- Items List -->
                <?php if (empty($viewTemplate['items'])): ?>
                    <p class="text-muted">Bu listede henüz madde yok.</p>
                <?php else: ?>
                    <div style="display: grid; gap: 8px;">
                        <?php foreach ($viewTemplate['items'] as $item): ?>
                            <div style="
                                display: flex; 
                                justify-content: space-between; 
                                align-items: flex-start; 
                                padding: 8px 12px; 
                                background: rgba(0,0,0,0.2); 
                                border-radius: 6px;
                            ">
                                <span style="margin-top: 2px;">•
                                    <?= escape($item['item_text']) ?>
                                </span>
                                <form method="POST" style="margin-left: 10px;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_item">
                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                    <input type="hidden" name="template_id" value="<?= $viewTemplate['id'] ?>">
                                    <!-- Keep context? No redirect logic here but useful -->
                                    <button type="submit"
                                        style="background: none; border: none; cursor: pointer; color: #ef4444; font-size: 1.1rem; line-height: 1;">&times;</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        <?php else: ?>
            <div class="glass glass-card" style="text-align: center; color: var(--text-muted); padding: 50px;">
                <p>Maddeleri düzenlemek için soldan bir liste seçin.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require SRC_DIR . '/partials/footer.php'; ?>