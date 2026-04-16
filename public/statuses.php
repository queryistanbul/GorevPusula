<?php
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

check_auth();
$user = Auth::user();

// Only admin
if ($user['role'] !== 'admin') {
    redirect('index.php');
}

$db = Database::getInstance();
$error = null;
$success = null;

// Handle Post
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = $_POST['status_id'];
        try {
            $db->execute("DELETE FROM statuses WHERE id = ?", [$id]);
            $success = "Durum başarıyla silindi.";
        } catch (Exception $e) {
            $error = "Bu durum silinemez. İlişkili görevler olabilir.";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = $_POST['status_id'];
        $name = $_POST['name'];
        $color = $_POST['color'];
        $order_index = $_POST['order_index'];
        $kanban_column = $_POST['kanban_column'];

        if (empty($name)) {
            $error = "Durum adı zorunludur.";
        } else {
            try {
                $db->execute(
                    "UPDATE statuses SET name = ?, color = ?, order_index = ?, kanban_column = ? WHERE id = ?",
                    [$name, $color, $order_index, $kanban_column, $id]
                );
                $success = "Durum başarıyla güncellendi.";
                // Clear edit mode by redirecting or just clearing logic
                redirect('statuses.php');
            } catch (Exception $e) {
                $error = "Hata: " . $e->getMessage();
            }
        }
    } else {
        $name = $_POST['name'];
        $color = $_POST['color'];
        $order_index = $_POST['order_index'];
        $kanban_column = $_POST['kanban_column'];

        if (empty($name)) {
            $error = "Durum adı zorunludur.";
        } else {
            try {
                $db->execute(
                    "INSERT INTO statuses (name, color, order_index, kanban_column) VALUES (?, ?, ?, ?)",
                    [$name, $color, $order_index, $kanban_column]
                );
                $success = "Durum başarıyla eklendi.";
            } catch (Exception $e) {
                $error = "Hata: " . $e->getMessage();
            }
        }
    }
}

// Check for Edit Mode
$editStatus = null;
if (isset($_GET['edit'])) {
    $editStatus = $db->fetchOne("SELECT * FROM statuses WHERE id = ?", [$_GET['edit']]);
}

$statuses = $db->query("SELECT * FROM statuses ORDER BY order_index");

require SRC_DIR . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Durum Yönetimi</h1>
        <p class="page-subtitle">Görev durumlarını yönetin.</p>
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

    <!-- Add/Edit Form -->
    <div class="glass glass-card">
        <h3 class="card-title"><?= $editStatus ? 'Durum Düzenle' : 'Yeni Durum Ekle' ?></h3>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editStatus ? 'update' : 'add' ?>">
            <?php if ($editStatus): ?>
                <input type="hidden" name="status_id" value="<?= $editStatus['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Durum Adı</label>
                <input type="text" name="name" required placeholder="Örn: İncelemede" value="<?= $editStatus ? escape($editStatus['name']) : '' ?>">
            </div>

            <div class="form-group">
                <label>Renk</label>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="color" name="color" value="<?= $editStatus ? escape($editStatus['color']) : '#3B82F6' ?>"
                        style="width: 50px; height: 40px; padding: 0; border: none; cursor: pointer; background: transparent;">
                    <span class="text-muted" style="font-size: 0.9rem;">Listelerde görünecek renk</span>
                </div>
            </div>

            <div class="form-group">
                <label>Sıralama (Sayı)</label>
                <input type="number" name="order_index" value="<?= $editStatus ? escape($editStatus['order_index']) : '0' ?>" required>
            </div>

            <div class="form-group">
                <label>Kanban Grubu</label>
                <select name="kanban_column">
                    <option value="todo" <?= ($editStatus && $editStatus['kanban_column'] == 'todo') ? 'selected' : '' ?>>Yapılacak</option>
                    <option value="in_progress" <?= ($editStatus && $editStatus['kanban_column'] == 'in_progress') ? 'selected' : '' ?>>Devam Ediyor</option>
                    <option value="review" <?= ($editStatus && $editStatus['kanban_column'] == 'review') ? 'selected' : '' ?>>Kontrol/Test</option>
                    <option value="done" <?= ($editStatus && $editStatus['kanban_column'] == 'done') ? 'selected' : '' ?>>Tamamlandı</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary btn-block"><?= $editStatus ? 'Güncelle' : 'Durum Oluştur' ?></button>
            <?php if ($editStatus): ?>
                <a href="statuses.php" class="btn btn-secondary btn-block" style="text-align:center; margin-top:10px; display:block;">İptal</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- List -->
    <div class="glass glass-card">
        <h3 class="card-title">Durum Listesi (
            <?= count($statuses) ?>)
        </h3>

        <table class="table">
            <thead>
                <tr>
                    <th>Adı</th>
                    <th>Renk</th>
                    <th>Sıra</th>
                    <th>Grup</th>
                    <th class="text-right">İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($statuses as $s): ?>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <td style="padding: 10px; font-weight: 500;">
                            <?= escape($s['name']) ?>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <div
                                    style="width: 20px; height: 20px; border-radius: 4px; background-color: <?= $s['color'] ?>;">
                                </div>
                                <span class="text-muted" style="font-size: 0.9rem;">
                                    <?= $s['color'] ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <?= $s['order_index'] ?>
                        </td>
                        <td>
                            <?= $s['kanban_column'] ?>
                        </td>
                        <td class="text-right">
                            <a href="?edit=<?= $s['id'] ?>" class="btn-text" style="margin-right: 10px;">Düzenle</a>
                            <form method="POST" onsubmit="return confirm('Silmek istediğinize emin misiniz?');"
                                style="display: inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="status_id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn-text-danger">Sil</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require SRC_DIR . '/partials/footer.php'; ?>