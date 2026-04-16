<?php
/**
 * Task Controller
 * 
 * Task management with filtering, attachments, and department permissions
 */

require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../middleware/Permissions.php';

class TaskController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * GET /api/tasks
     * Get all tasks with filtering
     */
    public function index()
    {
        Auth::authenticate();
        Permissions::loadPermissions();

        $myTasks = $_GET['myTasks'] ?? false;
        $myDepartment = $_GET['myDepartment'] ?? false;
        $status = $_GET['status'] ?? null;
        $priority = $_GET['priority'] ?? null;
        $search = $_GET['search'] ?? null;

        $sql = "SELECT t.*,
                    p.name as priority_name, p.color as priority_color,
                    s.name as status_name, s.color as status_color, s.kanban_column,
                    mt.name as main_topic_name,
                    st.name as sub_topic_name,
                    o.full_name as owner_name,
                    r.full_name as requester_name,
                    rd.name as requesting_department_name,
                    rsd.name as responsible_department_name,
                    (SELECT COUNT(*) FROM task_attachments WHERE task_id = t.id) as attachment_count
                FROM tasks t
                LEFT JOIN priorities p ON t.priority_id = p.id
                LEFT JOIN statuses s ON t.status_id = s.id
                LEFT JOIN main_topics mt ON t.main_topic_id = mt.id
                LEFT JOIN sub_topics st ON t.sub_topic_id = st.id
                LEFT JOIN users o ON t.owner_id = o.id
                LEFT JOIN users r ON t.requester_id = r.id
                LEFT JOIN departments rd ON t.requesting_department_id = rd.id
                LEFT JOIN departments rsd ON t.responsible_department_id = rsd.id
                WHERE 1=1";

        $params = [];

        // Apply department permission filter
        list($deptFilter, $deptParams) = Permissions::getDepartmentFilterSQL('t.requesting_department_id', 't.responsible_department_id');
        $sql .= " " . $deptFilter;
        $params = array_merge($params, $deptParams);

        // Filter by current user's tasks
        if ($myTasks === 'true') {
            $sql .= " AND t.owner_id = ?";
            $params[] = Auth::user()['id'];
        }

        // Filter by current user's department
        if ($myDepartment === 'true') {
            $sql .= " AND t.responsible_department_id = ?";
            $params[] = Auth::user()['department_id'];
        }

        // Filter by status (can be multiple)
        if ($status) {
            $statusIds = is_array($status) ? $status : [$status];
            $placeholders = implode(',', array_fill(0, count($statusIds), '?'));
            $sql .= " AND t.status_id IN ($placeholders)";
            $params = array_merge($params, $statusIds);
        }

        // Filter by priority
        if ($priority) {
            $sql .= " AND t.priority_id = ?";
            $params[] = $priority;
        }

        // Search in title and description
        if ($search) {
            $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql .= " ORDER BY t.created_at DESC";

        try {
            $tasks = $this->db->query($sql, $params);

            // Convert types
            foreach ($tasks as &$task) {
                $task['id'] = (int) $task['id'];
                $task['priority_id'] = $task['priority_id'] ? (int) $task['priority_id'] : null;
                $task['status_id'] = $task['status_id'] ? (int) $task['status_id'] : null;
                $task['main_topic_id'] = $task['main_topic_id'] ? (int) $task['main_topic_id'] : null;
                $task['sub_topic_id'] = $task['sub_topic_id'] ? (int) $task['sub_topic_id'] : null;
                $task['owner_id'] = (int) $task['owner_id'];
                $task['requester_id'] = (int) $task['requester_id'];
                $task['requesting_department_id'] = (int) $task['requesting_department_id'];
                $task['responsible_department_id'] = (int) $task['responsible_department_id'];
                $task['attachment_count'] = (int) $task['attachment_count'];
            }

            Response::success($tasks);
        } catch (Exception $e) {
            error_log("Get tasks error: " . $e->getMessage());
            Response::serverError('Failed to get tasks');
        }
    }

    /**
     * GET /api/tasks/:id
     * Get single task with all details
     */
    public function show($id)
    {
        Auth::authenticate();
        Permissions::loadPermissions();

        try {
            $tasks = $this->db->query(
                "SELECT t.*,
                    p.name as priority_name, p.color as priority_color,
                    s.name as status_name, s.color as status_color, s.kanban_column,
                    mt.name as main_topic_name,
                    st.name as sub_topic_name,
                    o.full_name as owner_name, o.email as owner_email,
                    r.full_name as requester_name, r.email as requester_email,
                    rd.name as requesting_department_name,
                    rsd.name as responsible_department_name
                 FROM tasks t
                 LEFT JOIN priorities p ON t.priority_id = p.id
                 LEFT JOIN statuses s ON t.status_id = s.id
                 LEFT JOIN main_topics mt ON t.main_topic_id = mt.id
                 LEFT JOIN sub_topics st ON t.sub_topic_id = st.id
                 LEFT JOIN users o ON t.owner_id = o.id
                 LEFT JOIN users r ON t.requester_id = r.id
                 LEFT JOIN departments rd ON t.requesting_department_id = rd.id
                 LEFT JOIN departments rsd ON t.responsible_department_id = rsd.id
                 WHERE t.id = ?",
                [$id]
            );

            if (empty($tasks)) {
                Response::notFound('Task not found');
            }

            $task = $tasks[0];

            // Check permission
            if (!Permissions::canViewAllDepartments()) {
                if (
                    !Permissions::canViewDepartment($task['responsible_department_id']) &&
                    !Permissions::canViewDepartment($task['requesting_department_id'])
                ) {
                    Response::forbidden('Access denied');
                }
            }

            // Get attachments
            $attachments = $this->db->query(
                "SELECT a.*, u.full_name as uploaded_by_name
                 FROM task_attachments a
                 JOIN users u ON a.uploaded_by = u.id
                 WHERE a.task_id = ?
                 ORDER BY a.uploaded_at DESC",
                [$id]
            );

            // Convert types
            $task['id'] = (int) $task['id'];
            $task['priority_id'] = $task['priority_id'] ? (int) $task['priority_id'] : null;
            $task['status_id'] = $task['status_id'] ? (int) $task['status_id'] : null;
            $task['main_topic_id'] = $task['main_topic_id'] ? (int) $task['main_topic_id'] : null;
            $task['sub_topic_id'] = $task['sub_topic_id'] ? (int) $task['sub_topic_id'] : null;
            $task['owner_id'] = (int) $task['owner_id'];
            $task['requester_id'] = (int) $task['requester_id'];
            $task['requesting_department_id'] = (int) $task['requesting_department_id'];
            $task['responsible_department_id'] = (int) $task['responsible_department_id'];

            foreach ($attachments as &$att) {
                $att['id'] = (int) $att['id'];
                $att['task_id'] = (int) $att['task_id'];
                $att['file_size'] = (int) $att['file_size'];
                $att['uploaded_by'] = (int) $att['uploaded_by'];
            }

            $task['attachments'] = $attachments;

            Response::success($task);
        } catch (Exception $e) {
            error_log("Get task error: " . $e->getMessage());
            Response::serverError('Failed to get task');
        }
    }

    /**
     * POST /api/tasks
     * Create new task
     */
    public function create()
    {
        Auth::authenticate();

        $data = json_decode(file_get_contents('php://input'), true);

        // Validate input
        $validator = new Validator();
        $validator
            ->required('title', $data['title'] ?? null)
            ->required('owner_id', $data['owner_id'] ?? null)
            ->isInt('owner_id', $data['owner_id'] ?? null)
            ->required('requesting_department_id', $data['requesting_department_id'] ?? null)
            ->isInt('requesting_department_id', $data['requesting_department_id'] ?? null)
            ->required('responsible_department_id', $data['responsible_department_id'] ?? null)
            ->isInt('responsible_department_id', $data['responsible_department_id'] ?? null);

        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }

        try {
            $this->db->execute(
                "INSERT INTO tasks (
                    title, description, priority_id, status_id, main_topic_id, sub_topic_id,
                    owner_id, requester_id, requesting_department_id, responsible_department_id,
                    target_completion_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $data['title'],
                    $data['description'] ?? null,
                    $data['priority_id'] ?? null,
                    $data['status_id'] ?? null,
                    $data['main_topic_id'] ?? null,
                    $data['sub_topic_id'] ?? null,
                    $data['owner_id'],
                    Auth::user()['id'], // requester is current user
                    $data['requesting_department_id'],
                    $data['responsible_department_id'],
                    $data['target_completion_date'] ?? null
                ]
            );

            $taskId = $this->db->lastInsertId();

            // Get created task
            $newTask = $this->db->query(
                "SELECT t.*,
                    p.name as priority_name, s.name as status_name,
                    o.full_name as owner_name, r.full_name as requester_name
                 FROM tasks t
                 LEFT JOIN priorities p ON t.priority_id = p.id
                 LEFT JOIN statuses s ON t.status_id = s.id
                 LEFT JOIN users o ON t.owner_id = o.id
                 LEFT JOIN users r ON t.requester_id = r.id
                 WHERE t.id = ?",
                [$taskId]
            );

            $task = $newTask[0];
            $task['id'] = (int) $task['id'];

            Response::created($task);
        } catch (Exception $e) {
            error_log("Create task error: " . $e->getMessage());
            Response::serverError('Failed to create task');
        }
    }

    /**
     * PUT /api/tasks/:id
     * Update task
     */
    public function update($id)
    {
        Auth::authenticate();

        $data = json_decode(file_get_contents('php://input'), true);

        $updates = [];
        $params = [];

        if (isset($data['title'])) {
            $updates[] = 'title = ?';
            $params[] = $data['title'];
        }

        if (isset($data['description'])) {
            $updates[] = 'description = ?';
            $params[] = $data['description'];
        }

        if (isset($data['priority_id'])) {
            $updates[] = 'priority_id = ?';
            $params[] = $data['priority_id'];
        }

        if (isset($data['status_id'])) {
            $updates[] = 'status_id = ?';
            $params[] = $data['status_id'];
        }

        if (isset($data['main_topic_id'])) {
            $updates[] = 'main_topic_id = ?';
            $params[] = $data['main_topic_id'];
        }

        if (isset($data['sub_topic_id'])) {
            $updates[] = 'sub_topic_id = ?';
            $params[] = $data['sub_topic_id'];
        }

        if (isset($data['owner_id'])) {
            $updates[] = 'owner_id = ?';
            $params[] = $data['owner_id'];
        }

        if (isset($data['target_completion_date'])) {
            $updates[] = 'target_completion_date = ?';
            $params[] = $data['target_completion_date'];
        }

        if (empty($updates)) {
            Response::error('No fields to update', 400);
        }

        $params[] = $id;

        try {
            $this->db->execute(
                "UPDATE tasks SET " . implode(', ', $updates) . " WHERE id = ?",
                $params
            );

            // Get updated task
            $updated = $this->db->query(
                "SELECT t.*,
                    p.name as priority_name, s.name as status_name, s.kanban_column,
                    o.full_name as owner_name
                 FROM tasks t
                 LEFT JOIN priorities p ON t.priority_id = p.id
                 LEFT JOIN statuses s ON t.status_id = s.id
                 LEFT JOIN users o ON t.owner_id = o.id
                 WHERE t.id = ?",
                [$id]
            );

            if (empty($updated)) {
                Response::notFound('Task not found');
            }

            $task = $updated[0];
            $task['id'] = (int) $task['id'];

            Response::success($task);
        } catch (Exception $e) {
            error_log("Update task error: " . $e->getMessage());
            Response::serverError('Failed to update task');
        }
    }

    /**
     * DELETE /api/tasks/:id
     * Delete task
     */
    public function delete($id)
    {
        Auth::authenticate();

        // Get task to check permissions
        $tasks = $this->db->query("SELECT * FROM tasks WHERE id = ?", [$id]);

        if (empty($tasks)) {
            Response::notFound('Task not found');
        }

        $task = $tasks[0];

        // Only admin or task requester can delete
        if (!Auth::isAdmin() && $task['requester_id'] != Auth::user()['id']) {
            Response::forbidden('Access denied');
        }

        try {
            // Get and delete associated files
            $attachments = $this->db->query(
                "SELECT file_path FROM task_attachments WHERE task_id = ?",
                [$id]
            );

            foreach ($attachments as $att) {
                if (file_exists($att['file_path'])) {
                    unlink($att['file_path']);
                }
            }

            // Delete task (attachments will be deleted by CASCADE)
            $this->db->execute("DELETE FROM tasks WHERE id = ?", [$id]);

            Response::success(['message' => 'Task deleted successfully']);
        } catch (Exception $e) {
            error_log("Delete task error: " . $e->getMessage());
            Response::serverError('Failed to delete task');
        }
    }

    /**
     * POST /api/tasks/:id/attachments
     * Upload file attachment
     */
    public function uploadAttachment($id)
    {
        Auth::authenticate();

        // Verify task exists
        $tasks = $this->db->query("SELECT id FROM tasks WHERE id = ?", [$id]);
        if (empty($tasks)) {
            Response::notFound('Task not found');
        }

        if (!isset($_FILES['file'])) {
            Response::error('No file uploaded', 400);
        }

        $file = $_FILES['file'];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::error('File upload error', 400);
        }

        // Check file size
        if ($file['size'] > MAX_FILE_SIZE) {
            Response::error('File too large. Maximum size: 10MB', 400);
        }

        // Check file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXTENSIONS)) {
            Response::error('Invalid file type', 400);
        }

        // Generate unique filename
        $filename = time() . '-' . mt_rand(100000, 999999) . '-' . basename($file['name']);
        $filepath = UPLOAD_DIR . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            Response::serverError('Failed to save file');
        }

        try {
            // Insert attachment record
            $this->db->execute(
                "INSERT INTO task_attachments (task_id, file_name, file_path, file_size, uploaded_by)
                 VALUES (?, ?, ?, ?, ?)",
                [
                    $id,
                    $file['name'],
                    $filepath,
                    $file['size'],
                    Auth::user()['id']
                ]
            );

            $attachmentId = $this->db->lastInsertId();

            // Get created attachment
            $attachment = $this->db->query(
                "SELECT a.*, u.full_name as uploaded_by_name
                 FROM task_attachments a
                 JOIN users u ON a.uploaded_by = u.id
                 WHERE a.id = ?",
                [$attachmentId]
            );

            $att = $attachment[0];
            $att['id'] = (int) $att['id'];
            $att['task_id'] = (int) $att['task_id'];
            $att['file_size'] = (int) $att['file_size'];
            $att['uploaded_by'] = (int) $att['uploaded_by'];

            Response::created($att);
        } catch (Exception $e) {
            // Clean up file if database insert fails
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            error_log("Upload attachment error: " . $e->getMessage());
            Response::serverError('Failed to save attachment');
        }
    }

    /**
     * DELETE /api/tasks/:id/attachments/:attachmentId
     * Delete file attachment
     */
    public function deleteAttachment($id, $attachmentId)
    {
        Auth::authenticate();

        // Get attachment info
        $attachments = $this->db->query(
            "SELECT * FROM task_attachments WHERE id = ?",
            [$attachmentId]
        );

        if (empty($attachments)) {
            Response::notFound('Attachment not found');
        }

        $attachment = $attachments[0];

        // Only admin or uploader can delete
        if (!Auth::isAdmin() && $attachment['uploaded_by'] != Auth::user()['id']) {
            Response::forbidden('Access denied');
        }

        try {
            // Delete file from disk
            if (file_exists($attachment['file_path'])) {
                unlink($attachment['file_path']);
            }

            // Delete from database
            $this->db->execute("DELETE FROM task_attachments WHERE id = ?", [$attachmentId]);

            Response::success(['message' => 'Attachment deleted successfully']);
        } catch (Exception $e) {
            error_log("Delete attachment error: " . $e->getMessage());
            Response::serverError('Failed to delete attachment');
        }
    }

    /**
     * GET /api/tasks/attachments/:id/download
     * Download file attachment
     */
    public function downloadAttachment($id)
    {
        Auth::authenticate();

        $attachments = $this->db->query(
            "SELECT * FROM task_attachments WHERE id = ?",
            [$id]
        );

        if (empty($attachments)) {
            Response::notFound('Attachment not found');
        }

        $attachment = $attachments[0];

        if (!file_exists($attachment['file_path'])) {
            Response::notFound('File not found on server');
        }

        // Send file for download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($attachment['file_name']) . '"');
        header('Content-Length: ' . filesize($attachment['file_path']));
        readfile($attachment['file_path']);
        exit;
    }
}
