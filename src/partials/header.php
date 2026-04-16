<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
</head>

<body>
    <script>
        // Check for saved theme preference or system preference
        const savedTheme = localStorage.getItem('theme');
        const systemTheme = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';

        if (savedTheme === 'light' || (!savedTheme && systemTheme === 'light')) {
            document.body.setAttribute('data-theme', 'light');
        }
    </script>

    <?php if (isset($_SESSION['user'])): ?>
        <div class="app-layout">
            <aside class="sidebar glass" id="sidebar">
                <button id="sidebar-toggle" class="sidebar-toggle-btn" title="Menüyü Gizle/Göster">
                    ☰
                </button>
                <a href="index.php" class="sidebar-logo">
                    <img src="img/logo.png" alt="TaskFlow" style="height: 50px;">
                </a>

                <nav class="sidebar-menu">
                    <?php
                    $tasksPages = ['index.php', 'kanban.php', 'business_plans.php'];
                    $projectPages = ['projects.php', 'project_detail.php', 'project_dashboard.php'];
                    $reportPages = ['dashboard.php', 'reports.php'];
                    $isTasksActive = in_array(basename($_SERVER['PHP_SELF']), array_merge($tasksPages, $reportPages, $projectPages));
                    $isProjectActive = in_array(basename($_SERVER['PHP_SELF']), $projectPages);
                    $isReportActive = in_array(basename($_SERVER['PHP_SELF']), $reportPages);
                    ?>
                    <div class="nav-group" style="margin-top: 5px;">
                        <div class="nav-link <?= $isTasksActive ? 'active' : '' ?>" id="tasksMenuBtn"
                            style="cursor: pointer; display: flex; justify-content: space-between; align-items: center;"
                            onclick="toggleTasksMenu()">
                            <span>📊 &nbsp; Görevler</span>
                            <span id="tasksMenuIcon"
                                style="font-size: 0.7em; transition: transform 0.3s; transform: rotate(<?= $isTasksActive ? '180deg' : '0' ?>);">▼</span>
                        </div>
                        <div id="tasksMenuContent"
                            style="display: <?= $isTasksActive ? 'flex' : 'none' ?>; flex-direction: column; gap: 5px; padding-left: 10px; margin-top: 5px; border-left: 2px solid var(--glass-border); margin-left: 15px;">
                            
                            <a href="index.php"
                                class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' || basename($_SERVER['PHP_SELF']) == 'kanban.php' ? 'active' : '' ?>"
                                style="padding: 8px 12px; font-size: 0.95rem;">📋 &nbsp; Tüm Görevler</a>
                                
                            <div class="nav-group" style="margin-top: 2px;">
                                <div class="nav-link <?= $isProjectActive ? 'active' : '' ?>" id="projectSubMenuBtn"
                                    style="cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; font-size: 0.95rem;"
                                    onclick="toggleProjectSubMenu(event)">
                                    <span>🚀 &nbsp; Proje Menüsü</span>
                                    <span id="projectSubMenuIcon"
                                        style="font-size: 0.7em; transition: transform 0.3s; transform: rotate(<?= $isProjectActive ? '180deg' : '0' ?>);">▼</span>
                                </div>
                                <div id="projectSubMenuContent"
                                    style="display: <?= $isProjectActive ? 'flex' : 'none' ?>; flex-direction: column; gap: 5px; padding-left: 10px; margin-top: 5px; border-left: 2px solid var(--glass-border); margin-left: 15px;">
                                    <a href="projects.php"
                                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'projects.php' || basename($_SERVER['PHP_SELF']) == 'project_detail.php' ? 'active' : '' ?>"
                                        style="padding: 6px 12px; font-size: 0.9rem;">🚀 &nbsp; Projeler</a>
                                    <a href="project_dashboard.php"
                                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'project_dashboard.php' ? 'active' : '' ?>"
                                        style="padding: 6px 12px; font-size: 0.9rem;">📊 &nbsp; Proje Analizi</a>
                                </div>
                            </div>
                            
                            <a href="business_plans.php"
                                class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'business_plans.php' ? 'active' : '' ?>"
                                style="padding: 8px 12px; font-size: 0.95rem;">📋 &nbsp; İş Planları</a>
                                
                            <div class="nav-group" style="margin-top: 2px;">
                                <div class="nav-link <?= $isReportActive ? 'active' : '' ?>" id="reportSubMenuBtn"
                                    style="cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; font-size: 0.95rem;"
                                    onclick="toggleReportSubMenu(event)">
                                    <span>📄 &nbsp; Rapor Menüsü</span>
                                    <span id="reportSubMenuIcon"
                                        style="font-size: 0.7em; transition: transform 0.3s; transform: rotate(<?= $isReportActive ? '180deg' : '0' ?>);">▼</span>
                                </div>
                                <div id="reportSubMenuContent"
                                    style="display: <?= $isReportActive ? 'flex' : 'none' ?>; flex-direction: column; gap: 5px; padding-left: 10px; margin-top: 5px; border-left: 2px solid var(--glass-border); margin-left: 15px;">
                                    <a href="dashboard.php"
                                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>"
                                        style="padding: 6px 12px; font-size: 0.9rem;">🏠 &nbsp; Kontrol Paneli</a>
                                    <a href="reports.php"
                                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>"
                                        style="padding: 6px 12px; font-size: 0.9rem;">📈 &nbsp; Raporlar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                        function toggleTasksMenu() {
                            var content = document.getElementById('tasksMenuContent');
                            var icon = document.getElementById('tasksMenuIcon');
                            if (content.style.display === 'none' || content.style.display === '') {
                                content.style.display = 'flex';
                                icon.style.transform = 'rotate(180deg)';
                            } else {
                                content.style.display = 'none';
                                icon.style.transform = 'rotate(0deg)';
                            }
                        }
                        
                        function toggleProjectSubMenu(event) {
                            if(event) event.stopPropagation();
                            var content = document.getElementById('projectSubMenuContent');
                            var icon = document.getElementById('projectSubMenuIcon');
                            if (content.style.display === 'none' || content.style.display === '') {
                                content.style.display = 'flex';
                                icon.style.transform = 'rotate(180deg)';
                            } else {
                                content.style.display = 'none';
                                icon.style.transform = 'rotate(0deg)';
                            }
                        }
                        
                        function toggleReportSubMenu(event) {
                            if(event) event.stopPropagation();
                            var content = document.getElementById('reportSubMenuContent');
                            var icon = document.getElementById('reportSubMenuIcon');
                            if (content.style.display === 'none' || content.style.display === '') {
                                content.style.display = 'flex';
                                icon.style.transform = 'rotate(180deg)';
                            } else {
                                content.style.display = 'none';
                                icon.style.transform = 'rotate(0deg)';
                            }
                        }
                    </script>

                    <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                        <?php
                        $adminPages = ['users.php', 'departments.php', 'checklists.php', 'topics.php', 'statuses.php', 'audit_log.php'];
                        $isAdminActive = in_array(basename($_SERVER['PHP_SELF']), $adminPages);
                        ?>
                        <div class="nav-group" style="margin-top: 5px;">
                            <div class="nav-link <?= $isAdminActive ? 'active' : '' ?>" id="adminMenuBtn"
                                style="cursor: pointer; display: flex; justify-content: space-between; align-items: center;"
                                onclick="toggleAdminMenu()">
                                <span>⚙️ &nbsp; Yönetim</span>
                                <span id="adminMenuIcon"
                                    style="font-size: 0.7em; transition: transform 0.3s; transform: rotate(<?= $isAdminActive ? '180deg' : '0' ?>);">▼</span>
                            </div>
                            <div id="adminMenuContent"
                                style="display: <?= $isAdminActive ? 'flex' : 'none' ?>; flex-direction: column; gap: 5px; padding-left: 10px; margin-top: 5px; border-left: 2px solid var(--glass-border); margin-left: 15px;">
                                <a href="users.php"
                                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>"
                                    style="padding: 8px 12px; font-size: 0.95rem;">👥 &nbsp; Kullanıcılar</a>
                                <a href="departments.php"
                                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'departments.php' ? 'active' : '' ?>"
                                    style="padding: 8px 12px; font-size: 0.95rem;">🏢 &nbsp; Bölümler</a>
                                <a href="checklists.php"
                                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'checklists.php' ? 'active' : '' ?>"
                                    style="padding: 8px 12px; font-size: 0.95rem;">✅ &nbsp; Listeler</a>
                                <a href="topics.php"
                                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'topics.php' ? 'active' : '' ?>"
                                    style="padding: 8px 12px; font-size: 0.95rem;">📑 &nbsp; Konular</a>
                                <a href="statuses.php"
                                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'statuses.php' ? 'active' : '' ?>"
                                    style="padding: 8px 12px; font-size: 0.95rem;">🔄 &nbsp; Durumlar</a>
                                <a href="audit_log.php"
                                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'audit_log.php' ? 'active' : '' ?>"
                                    style="padding: 8px 12px; font-size: 0.95rem;">📋 &nbsp; Günlük</a>
                            </div>
                        </div>
                        <script>
                            function toggleAdminMenu() {
                                var content = document.getElementById('adminMenuContent');
                                var icon = document.getElementById('adminMenuIcon');
                                if (content.style.display === 'none' || content.style.display === '') {
                                    content.style.display = 'flex';
                                    icon.style.transform = 'rotate(180deg)';
                                } else {
                                    content.style.display = 'none';
                                    icon.style.transform = 'rotate(0deg)';
                                }
                            }
                        </script>
                    <?php endif; ?>
                </nav>

                <div class="sidebar-footer">
                    <div style="flex: 1;">
                        <div style="padding: 0 10px; margin-bottom: 15px;">
                            <div style="font-size: 0.9rem; font-weight: bold;"><?= escape($_SESSION['user']['full_name']) ?>
                            </div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">
                                <?= isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin' ? 'Yönetici' : 'Kullanıcı' ?>
                            </div>
                        </div>

                        <button id="theme-toggle" class="nav-link"
                            style="background: transparent; border: none; cursor: pointer; width: 100%; text-align: left;">
                            🌙 &nbsp; Tema Değiştir
                        </button>
                    </div>

                    <a href="logout.php" class="nav-link text-danger"
                        style="margin-top: 20px; border-top: 1px solid var(--glass-border); padding-top: 15px;">
                        🚪 &nbsp; Çıkış Yap
                    </a>
                </div>
            </aside>

            <!-- Task Edit Side Panel -->
            <div class="task-panel-overlay" id="taskPanelOverlay" onclick="closeTaskPanel()"></div>
            <div class="task-side-panel" id="taskSidePanel">
                <div class="panel-header">
                    <h3>Görev Düzenle <span class="panel-task-id" id="panelTaskId"></span></h3>
                    <button class="panel-close-btn" onclick="closeTaskPanel()">&times;</button>
                </div>
                <div class="panel-content" id="panelContent">
                    <div class="panel-loading">Yükleniyor...</div>
                </div>
                <div class="panel-footer">
                    <button class="btn btn-outline" onclick="closeTaskPanel()">İptal</button>
                    <button class="btn btn-primary" onclick="saveTaskPanel()">Kaydet</button>
                </div>
            </div>

            <main class="main-content">
                <div class="<?= isset($layout) && $layout === 'fluid' ? 'container-fluid' : 'container' ?>"
                    style="padding: 0;">
                <?php else: ?>
                    <div class="<?= isset($layout) && $layout === 'fluid' ? 'container-fluid' : 'container' ?>">
                    <?php endif; ?>

                    <script>
                        document.addEventListener('DOMContentLoaded', () => {
                            const toggleBtn = document.getElementById('theme-toggle');
                            if (toggleBtn) {
                                const isLight = document.body.getAttribute('data-theme') === 'light';
                                toggleBtn.innerHTML = isLight ? '☀️ &nbsp; Tema Değiştir' : '🌙 &nbsp; Tema Değiştir';

                                toggleBtn.addEventListener('click', () => {
                                    const currentTheme = document.body.getAttribute('data-theme');
                                    const newTheme = currentTheme === 'light' ? 'dark' : 'light';

                                    if (newTheme === 'dark') {
                                        document.body.removeAttribute('data-theme');
                                        toggleBtn.innerHTML = '🌙 &nbsp; Tema Değiştir';
                                    } else {
                                        document.body.setAttribute('data-theme', 'light');
                                        toggleBtn.innerHTML = '☀️ &nbsp; Tema Değiştir';
                                    }

                                    localStorage.setItem('theme', newTheme);
                                });
                            }

                            // Sidebar Toggle
                            const sidebarToggle = document.getElementById('sidebar-toggle');
                            const sidebar = document.getElementById('sidebar');
                            const mainContent = document.querySelector('.main-content');

                            // Load saved state
                            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                            if (sidebarCollapsed) {
                                sidebar.classList.add('collapsed');
                                mainContent.classList.add('expanded');
                            }

                            if (sidebarToggle) {
                                sidebarToggle.addEventListener('click', () => {
                                    sidebar.classList.toggle('collapsed');
                                    mainContent.classList.toggle('expanded');

                                    // Save state
                                    const isCollapsed = sidebar.classList.contains('collapsed');
                                    localStorage.setItem('sidebarCollapsed', isCollapsed);
                                });
                            }
                        });
                    </script>

                    <!-- Task Side Panel Script -->
                    <script>
                        let currentTaskId = null;
                        let taskData = null;

                        function openTaskPanel(taskId, event) {
                            if (event) {
                                event.preventDefault();
                                event.stopPropagation();
                            }

                            currentTaskId = taskId;
                            const panel = document.getElementById('taskSidePanel');
                            const overlay = document.getElementById('taskPanelOverlay');
                            const content = document.getElementById('panelContent');
                            const taskIdSpan = document.getElementById('panelTaskId');

                            // Show panel
                            panel.classList.add('active');
                            overlay.classList.add('active');
                            document.body.style.overflow = 'hidden';

                            // Show loading
                            content.innerHTML = '<div class="panel-loading">Yükleniyor...</div>';
                            taskIdSpan.textContent = '#' + taskId;

                            // Fetch task data
                            fetch('api_get_task.php?id=' + taskId)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        taskData = data;
                                        renderPanelContent(data);
                                    } else {
                                        content.innerHTML = '<div class="panel-loading" style="color: #ef4444;">Hata: ' + (data.error || 'Bilinmeyen hata') + '</div>';
                                    }
                                })
                                .catch(err => {
                                    content.innerHTML = '<div class="panel-loading" style="color: #ef4444;">Bağlantı hatası</div>';
                                });
                        }

                        function renderPanelContent(data) {
                            const task = data.task;
                            const options = data.options;
                            const comments = data.comments || [];
                            const attachments = data.attachments || [];

                            // Format date helper
                            const formatDate = (dateStr) => {
                                if (!dateStr) return '';
                                const d = new Date(dateStr);
                                return d.toLocaleDateString('tr-TR') + ' ' + d.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
                            };

                            let html = `
                                <div class="form-group">
                                    <label>Durum</label>
                                    <select id="panelStatus">
                                        ${options.statuses.map(s => `<option value="${s.id}" ${s.id == task.status_id ? 'selected' : ''}>${s.name}</option>`).join('')}
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Öncelik</label>
                                    <select id="panelPriority">
                                        ${options.priorities.map(p => `<option value="${p.id}" ${p.id == task.priority_id ? 'selected' : ''}>${p.name}</option>`).join('')}
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Sorumlu Kişi</label>
                                    <select id="panelOwner">
                                        ${options.users.map(u => `<option value="${u.id}" ${u.id == task.owner_id ? 'selected' : ''}>${u.full_name}</option>`).join('')}
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Hedef Tarih</label>
                                    <input type="date" id="panelTargetDate" value="${task.target_completion_date || ''}">
                                </div>
                                
                                <div class="form-group">
                                    <label>Konu</label>
                                    <select id="panelTopic">
                                        <option value="">Seçiniz...</option>
                                        ${options.mainTopics.map(t => `<option value="${t.id}" ${t.id == task.main_topic_id ? 'selected' : ''}>${t.name}</option>`).join('')}
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Proje</label>
                                    <select id="panelProject" onchange="updatePanelSteps()">
                                        <option value="">Proje Seçiniz...</option>
                                        ${options.projects.map(p => `<option value="${p.id}" ${p.id == task.project_id ? 'selected' : ''}>${p.project_code} - ${p.name}</option>`).join('')}
                                    </select>
                                </div>
                                
                                <div class="form-group" id="panelStepContainer" style="${task.project_id ? '' : 'display: none;'}">
                                    <label>Proje Adımı</label>
                                    <select id="panelStep">
                                        <option value="">Adım Seçiniz...</option>
                                        ${options.projectSteps.filter(s => s.project_id == task.project_id).map(s => `<option value="${s.id}" ${s.id == task.project_step_id ? 'selected' : ''}>${s.name}</option>`).join('')}
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Etiketler</label>
                                    <input type="text" id="panelHashtags" value="${task.hashtags || ''}" placeholder="#acil">
                                </div>

                                <hr style="border: none; border-top: 1px solid var(--glass-border); margin: 20px 0;">

                                <!-- File Upload Section -->
                                <div class="form-group">
                                    <label>📎 Dosyalar (${attachments.length})</label>
                                    <div id="panelAttachments" style="margin-bottom: 10px;">
                                        ${attachments.length > 0 ? attachments.map(a => `
                                            <div style="display: flex; align-items: center; gap: 8px; padding: 6px 0; font-size: 0.85rem;">
                                                <a href="${a.file_path}" target="_blank" style="color: var(--primary); text-decoration: none;">
                                                    📄 ${a.file_name}
                                                </a>
                                                <span style="color: var(--text-muted); font-size: 0.75rem;">(${(a.file_size / 1024).toFixed(1)} KB)</span>
                                            </div>
                                        `).join('') : '<div style="color: var(--text-muted); font-size: 0.85rem; font-style: italic;">Dosya yok</div>'}
                                    </div>
                                    <div style="display: flex; gap: 8px;">
                                        <input type="file" id="panelFileInput" style="flex: 1; font-size: 0.85rem;">
                                        <button type="button" class="btn btn-sm btn-glass" onclick="uploadPanelFile()">Yükle</button>
                                    </div>
                                </div>

                                <hr style="border: none; border-top: 1px solid var(--glass-border); margin: 20px 0;">

                                <!-- Comments Section -->
                                <div class="form-group">
                                    <label>💬 Yorumlar (${comments.length})</label>
                                    <div id="panelComments" style="max-height: 200px; overflow-y: auto; margin-bottom: 10px;">
                                        ${comments.length > 0 ? comments.map(c => `
                                            <div style="background: rgba(255,255,255,0.05); padding: 10px; border-radius: 6px; margin-bottom: 8px; font-size: 0.85rem;">
                                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px; color: var(--text-muted); font-size: 0.75rem;">
                                                    <strong style="color: var(--primary);">${c.full_name}</strong>
                                                    <span>${formatDate(c.created_at)}</span>
                                                </div>
                                                <div style="color: var(--text-main); line-height: 1.4;">${c.comment.replace(/\n/g, '<br>')}</div>
                                            </div>
                                        `).join('') : '<div style="color: var(--text-muted); font-size: 0.85rem; font-style: italic;">Henüz yorum yok</div>'}
                                    </div>
                                    <div style="display: flex; gap: 8px;">
                                        <input type="text" id="panelCommentInput" placeholder="Yorum yazın..." style="flex: 1; font-size: 0.85rem;" onkeypress="if(event.key==='Enter') addPanelComment()">
                                        <button type="button" class="btn btn-sm btn-glass" onclick="addPanelComment()">Gönder</button>
                                    </div>
                                </div>
                                
                                <a href="task_detail.php?id=${task.id}" class="panel-link">📄 Tam Sayfada Aç →</a>
                            `;

                            document.getElementById('panelContent').innerHTML = html;
                        }

                        function updatePanelSteps() {
                            const projectId = document.getElementById('panelProject').value;
                            const stepContainer = document.getElementById('panelStepContainer');
                            const stepSelect = document.getElementById('panelStep');

                            if (!projectId) {
                                stepContainer.style.display = 'none';
                                return;
                            }

                            const steps = taskData.options.projectSteps.filter(s => s.project_id == projectId);

                            stepSelect.innerHTML = '<option value="">Adım Seçiniz...</option>' +
                                steps.map(s => `<option value="${s.id}">${s.name}</option>`).join('');

                            stepContainer.style.display = steps.length > 0 ? 'block' : 'none';
                        }

                        function closeTaskPanel() {
                            const panel = document.getElementById('taskSidePanel');
                            const overlay = document.getElementById('taskPanelOverlay');

                            panel.classList.remove('active');
                            overlay.classList.remove('active');
                            document.body.style.overflow = '';
                            currentTaskId = null;
                            taskData = null;
                        }

                        function saveTaskPanel() {
                            if (!currentTaskId) return;

                            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                            const payload = {
                                task_id: currentTaskId,
                                status_id: document.getElementById('panelStatus').value,
                                priority_id: document.getElementById('panelPriority').value,
                                owner_id: document.getElementById('panelOwner').value,
                                target_completion_date: document.getElementById('panelTargetDate').value || null,
                                main_topic_id: document.getElementById('panelTopic').value || null,
                                project_id: document.getElementById('panelProject').value || null,
                                project_step_id: document.getElementById('panelStep').value || null,
                                hashtags: document.getElementById('panelHashtags').value
                            };

                            fetch('api_update_task.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-Token': csrfToken
                                },
                                body: JSON.stringify(payload)
                            })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        closeTaskPanel();
                                        window.location.reload();
                                    } else {
                                        alert('Hata: ' + (data.error || 'Bilinmeyen hata'));
                                    }
                                })
                                .catch(err => {
                                    alert('Bağlantı hatası: ' + err.message);
                                });
                        }

                        // Add comment function
                        function addPanelComment() {
                            const input = document.getElementById('panelCommentInput');
                            const comment = input.value.trim();
                            if (!comment || !currentTaskId) return;

                            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                            fetch('api_add_comment.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-Token': csrfToken
                                },
                                body: JSON.stringify({
                                    task_id: currentTaskId,
                                    comment: comment
                                })
                            })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        input.value = '';
                                        // Refresh panel to show new comment
                                        openTaskPanel(currentTaskId);
                                    } else {
                                        alert('Hata: ' + (data.error || 'Bilinmeyen hata'));
                                    }
                                })
                                .catch(err => {
                                    alert('Bağlantı hatası: ' + err.message);
                                });
                        }

                        // Upload file function
                        function uploadPanelFile() {
                            const fileInput = document.getElementById('panelFileInput');
                            if (!fileInput.files.length || !currentTaskId) return;

                            const formData = new FormData();
                            formData.append('task_id', currentTaskId);
                            formData.append('file', fileInput.files[0]);

                            fetch('api_upload_file.php', {
                                method: 'POST',
                                body: formData
                            })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        fileInput.value = '';
                                        // Refresh panel to show new file
                                        openTaskPanel(currentTaskId);
                                    } else {
                                        alert('Hata: ' + (data.error || 'Bilinmeyen hata'));
                                    }
                                })
                                .catch(err => {
                                    alert('Yükleme hatası: ' + err.message);
                                });
                        }

                        // ESC key to close
                        document.addEventListener('keydown', function (e) {
                            if (e.key === 'Escape') {
                                closeTaskPanel();
                            }
                        });
                    </script>