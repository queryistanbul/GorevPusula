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
        $id = $_POST['topic_id'];
        try {
            $db->execute("DELETE FROM main_topics WHERE id = ?", [$id]);
            $success = "Konu başarıyla silindi.";
        } catch (Exception $e) {
            $error = "Bu konu silinemez. İlişkili görevler olabilir.";
        }
    } else {
        $name = $_POST['name'];
        $description = $_POST['description'];

        if (empty($name)) {
            $error = "Konu adı zorunludur.";
        } else {
            try {
                $db->execute("INSERT INTO main_topics (name, description) VALUES (?, ?)", [$name, $description]);
                $success = "Konu başarıyla eklendi.";
            } catch (Exception $e) {
                $error = "Hata: " . $e->getMessage();
            }
        }
    }
}

$topics = $db->query("SELECT * FROM main_topics ORDER BY name");

require SRC_DIR . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Konu Yönetimi</h1>
        <p class="page-subtitle">Görev konularını yönetin.</p>
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

    <!-- Add Form -->
    <div class="glass glass-card">
        <h3 class="card-title">Yeni Konu Ekle</h3>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">

            <div class="form-group">
                <label>Konu Adı</label>
                <input type="text" name="name" required placeholder="Örn: Teknik Destek">
            </div>

            <div class="form-group">
                <label>Açıklama</label>
                <textarea name="description" rows="3" placeholder="Konu açıklaması..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Konu Başlığı Oluştur</button>
        </form>
    </div>

    <!-- List -->
    <div class="glass glass-card">
        <h3 class="card-title">Konu Listesi (
            <?= count($topics) ?>)
        </h3>

        <table class="table">
            <thead>
                <tr>
                    <th>Konu Adı</th>
                    <th>Açıklama</th>
                    <th class="text-right">İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topics as $t): ?>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <td style="padding: 10px; font-weight: 500;">
                            <?= escape($t['name']) ?>
                        </td>
                        <td style="padding: 10px; color: var(--text-muted);">
                            <?= escape($t['description']) ?>
                        </td>
                        <td style="padding: 10px; text-align: right;">
                            <form method="POST" onsubmit="return confirm('Silmek istediğinize emin misiniz?');"
                                style="display: inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="topic_id" value="<?= $t['id'] ?>">
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