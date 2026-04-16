<?php
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

check_auth();
$user = Auth::user();

// Only admin can access this page
if ($user['role'] !== 'admin') {
    redirect('index.php');
}

$db = Database::getInstance();
$error = null;
$success = null;

// Handle Form Submission (Add/Edit/Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        // Delete User
        $id = $_POST['user_id'];
        if ($id != $user['id']) { // Check: User cannot delete themselves
            $targetUser = $db->fetchOne("SELECT full_name FROM users WHERE id = ?", [$id]);
            $db->execute("DELETE FROM users WHERE id = ?", [$id]);

            log_activity('delete', 'user', $id, $user['full_name'] . " '{$targetUser['full_name']}' kullanıcısını sildi");

            $success = "Kullanıcı başarıyla silindi.";
        } else {
            $error = "Kendinizi silemezsiniz.";
        }
    } elseif (isset($_POST['action']) && ($_POST['action'] === 'add' || $_POST['action'] === 'update')) {
        // Add or Update User
        $full_name = $_POST['full_name'];
        $email = $_POST['email'];
        $username = $_POST['username'] ?? explode('@', $email)[0]; // Default username
        $password = $_POST['password'];
        $department_id = $_POST['department_id'];
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        $is_update = $_POST['action'] === 'update';
        $user_id = $_POST['user_id'] ?? null;

        // Validations
        if (empty($full_name) || empty($email) || empty($department_id) || (!$is_update && empty($password))) {
            $error = "Lütfen zorunlu alanları doldurunuz.";
        } else {
            // Check if email exists (excluding current user if update)
            $sqlCheck = "SELECT id FROM users WHERE email = ?";
            $paramsCheck = [$email];

            if ($is_update) {
                $sqlCheck .= " AND id != ?";
                $paramsCheck[] = $user_id;
            }

            $exists = $db->fetchOne($sqlCheck, $paramsCheck);

            if ($exists) {
                $error = "Bu e-posta adresi zaten kullanılıyor.";
            } else {
                try {
                    $db->getConnection()->beginTransaction();

                    if ($is_update) {
                        // Update
                        $sql = "UPDATE users SET full_name = ?, email = ?, department_id = ?, is_admin = ?";
                        $params = [$full_name, $email, $department_id, $is_admin];

                        if (!empty($password)) {
                            $sql .= ", password_hash = ?";
                            $params[] = password_hash($password, PASSWORD_DEFAULT);
                        }

                        $sql .= " WHERE id = ?";
                        $params[] = $user_id;

                        $db->execute($sql, $params);
                        $target_user_id = $user_id;

                        log_activity('update', 'user', $target_user_id, $user['full_name'] . " '$full_name' kullanıcısını güncelledi");

                        $success = "Kullanıcı başarıyla güncellendi.";
                    } else {
                        // Add
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $sql = "INSERT INTO users (full_name, email, username, password_hash, department_id, is_admin) VALUES (?, ?, ?, ?, ?, ?)";
                        $db->execute($sql, [$full_name, $email, $username, $password_hash, $department_id, $is_admin]);
                        $target_user_id = $db->lastInsertId();

                        log_activity('create', 'user', $target_user_id, $user['full_name'] . " '$full_name' kullanıcısını oluşturdu");

                        $success = "Kullanıcı başarıyla oluşturuldu.";
                    }

                    // Handle Managed Departments
                    $db->execute("DELETE FROM user_departments WHERE user_id = ?", [$target_user_id]);
                    if (isset($_POST['managed_departments']) && is_array($_POST['managed_departments'])) {
                        $stmt = $db->getConnection()->prepare("INSERT INTO user_departments (user_id, department_id) VALUES (?, ?)");
                        foreach ($_POST['managed_departments'] as $dept_id) {
                            $stmt->execute([$target_user_id, $dept_id]);
                        }
                    }

                    $db->getConnection()->commit();
                } catch (Exception $e) {
                    $db->getConnection()->rollBack();
                    $error = "Hata: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch Users
$users = $db->query("SELECT u.*, d.name as department_name, GROUP_CONCAT(ud.department_id) as managed_dept_ids 
                     FROM users u 
                     LEFT JOIN departments d ON u.department_id = d.id 
                     LEFT JOIN user_departments ud ON u.id = ud.user_id
                     GROUP BY u.id
                     ORDER BY u.created_at DESC");

// Fetch Departments for Dropdown
$departments = $db->query("SELECT * FROM departments ORDER BY name");

require SRC_DIR . '/partials/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <div>
        <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">Kullanıcı Yönetimi</h1>
        <p style="color: var(--text-muted); margin-top: 5px;">Sistemdeki kullanıcıları yönetin.</p>
    </div>
</div>

<?php if ($error): ?>
    <div
        style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.5); padding: 15px; border-radius: 8px; margin-bottom: 20px; color: #fca5a5;">
        <?= $error ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div
        style="background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.5); padding: 15px; border-radius: 8px; margin-bottom: 20px; color: #6ee7b7;">
        <?= $success ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px; align-items: start;">

    <!-- Add User Form -->
    <div class="glass glass-card">
        <h3 style="margin-bottom: 20px;" id="formTitle">Yeni Kullanıcı Ekle</h3>
        <form method="POST" id="userForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="user_id" id="userId">

            <div class="form-group">
                <label>Ad Soyad</label>
                <input type="text" name="full_name" required placeholder="Ad Soyad">
            </div>

            <div class="form-group">
                <label>E-Posta</label>
                <input type="email" name="email" required placeholder="ornek@sirket.com">
            </div>

            <div class="form-group">
                <label>Şifre <small class="text-muted" id="passwordHelp"></small></label>
                <input type="password" name="password" id="passwordInput" required placeholder="******">
            </div>

            <div class="form-group">
                <label>Bölüm</label>
                <select name="department_id" required>
                    <option value="">Seçiniz...</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>">
                            <?= escape($dept['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="display: flex; gap: 10px; align-items: center;">
                <input type="checkbox" name="is_admin" id="is_admin" style="width: auto;">
                <label for="is_admin" style="margin: 0; cursor: pointer;">Yönetici Yetkisi Ver</label>
            </div>

            <div class="form-group">
                <label>Ekstra Sorumlu Olduğu Bölümler (Çoklu seçim için Ctrl ile tıklayın)</label>
                <select name="managed_departments[]" id="managed_departments" multiple style="height: 100px;">
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>">
                            <?= escape($dept['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Bu kullanıcının kendi bölümü haricinde görevlerini görebileceği bölümleri
                    seçin.</small>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;" id="submitBtn">Kullanıcı Oluştur</button>
                <button type="button" class="btn btn-glass" id="cancelBtn" style="display: none;"
                    onclick="cancelEdit()">İptal</button>
            </div>
        </form>
    </div>

    <!-- User List -->
    <div class="glass glass-card">
        <h3 style="margin-bottom: 20px;">Kullanıcı Listesi (
            <?= count($users) ?>)
        </h3>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 10px;">Ad Soyad</th>
                        <th style="padding: 10px;">E-Posta</th>
                        <th style="padding: 10px;">Bölüm</th>
                        <th style="padding: 10px;">Rol</th>
                        <th style="padding: 10px; text-align: right;">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr style="border-bottom: 1px solid var(--glass-border);">
                            <td style="padding: 10px; font-weight: 500;">
                                <?= escape($u['full_name']) ?>
                            </td>
                            <td style="padding: 10px; color: var(--text-muted);">
                                <?= escape($u['email']) ?>
                            </td>
                            <td style="padding: 10px;">
                                <?= escape($u['department_name']) ?>
                            </td>
                            <td style="padding: 10px;">
                                <?php if ($u['is_admin']): ?>
                                    <span
                                        style="background: rgba(245, 158, 11, 0.2); color: #fbbf24; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem;">Yönetici</span>
                                <?php else: ?>
                                    <span
                                        style="background: rgba(59, 130, 246, 0.2); color: #60a5fa; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem;">Kullanıcı</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 10px; text-align: right;">
                                <?php if ($u['id'] != $user['id']): ?>
                                    <button type="button" onclick='editUser(<?= json_encode($u) ?>)'
                                        style="background: none; border: none; color: var(--primary); cursor: pointer; padding: 5px; margin-right: 5px;">Düzenle</button>

                                    <form method="POST"
                                        onsubmit="return confirm('Bu kullanıcıyı silmek istediğinize emin misiniz?');"
                                        style="display: inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit"
                                            style="background: none; border: none; color: #ef4444; cursor: pointer; padding: 5px;">Sil</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.8rem;">(Siz)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>

<script>
    function editUser(user) {
        // Switch to Update Mode
        document.getElementById('formTitle').textContent = 'Kullanıcı Düzenle';
        document.getElementById('formAction').value = 'update';
        document.getElementById('userId').value = user.id;
        document.getElementById('submitBtn').textContent = 'Güncelle';
        document.getElementById('cancelBtn').style.display = 'block';

        // Populate Fields
        document.querySelector('input[name="full_name"]').value = user.full_name;
        document.querySelector('input[name="email"]').value = user.email;
        document.querySelector('select[name="department_id"]').value = user.department_id;
        document.querySelector('input[name="is_admin"]').checked = user.is_admin == 1;

        // Select Managed Departments in Multi-Select
        const managedDeptSelect = document.getElementById('managed_departments');
        Array.from(managedDeptSelect.options).forEach(option => option.selected = false); // Clear first

        if (user.managed_dept_ids) {
            const deptIds = user.managed_dept_ids.split(',');
            Array.from(managedDeptSelect.options).forEach(option => {
                if (deptIds.includes(option.value)) {
                    option.selected = true;
                }
            });
        }

        // Password handling
        const passwordInput = document.getElementById('passwordInput');
        passwordInput.required = false;
        passwordInput.placeholder = '(Değiştirmek istemiyorsanız boş bırakın)';
        document.getElementById('passwordHelp').textContent = '(Opsiyonel)';
    }

    function cancelEdit() {
        // Reset to Add Mode
        document.getElementById('userForm').reset();
        document.getElementById('formTitle').textContent = 'Yeni Kullanıcı Ekle';
        document.getElementById('formAction').value = 'add';
        document.getElementById('userId').value = '';
        document.getElementById('submitBtn').textContent = 'Kullanıcı Oluştur';
        document.getElementById('cancelBtn').style.display = 'none';

        // Reset Managed Departments
        const managedDeptSelect = document.getElementById('managed_departments');
        Array.from(managedDeptSelect.options).forEach(option => option.selected = false);

        // Reset Password Field
        const passwordInput = document.getElementById('passwordInput');
        passwordInput.required = true;
        passwordInput.placeholder = '******';
        document.getElementById('passwordHelp').textContent = '';
    }
</script>

<?php require SRC_DIR . '/partials/footer.php'; ?>