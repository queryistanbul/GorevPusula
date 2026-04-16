<?php
/**
 * API: Get Task Details
 * Returns task data with all dropdown options for editing
 */
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$user = Auth::user();

$taskId = $_GET['id'] ?? null;

if (!$taskId) {
    http_response_code(400);
    echo json_encode(['error' => 'Task ID required']);
    exit;
}

// Fetch Task
$sql = "SELECT t.*,
            p.name as priority_name, p.color as priority_color,
            s.name as status_name, s.color as status_color,
            mt.name as main_topic_name,
            o.full_name as owner_name,
            r.full_name as requester_name,
            d.name as department_name,
            proj.project_code, proj.name as project_name,
            ps.name as step_name
        FROM tasks t
        LEFT JOIN priorities p ON t.priority_id = p.id
        LEFT JOIN statuses s ON t.status_id = s.id
        LEFT JOIN main_topics mt ON t.main_topic_id = mt.id
        LEFT JOIN users o ON t.owner_id = o.id
        LEFT JOIN users r ON t.requester_id = r.id
        LEFT JOIN departments d ON t.responsible_department_id = d.id
        LEFT JOIN projects proj ON t.project_id = proj.id
        LEFT JOIN project_steps ps ON t.project_step_id = ps.id
        WHERE t.id = ?";

$task = $db->fetchOne($sql, [$taskId]);

if (!$task) {
    http_response_code(404);
    echo json_encode(['error' => 'Task not found']);
    exit;
}

// Fetch dropdown options
$statuses = $db->query("SELECT id, name, color FROM statuses ORDER BY order_index");
$priorities = $db->query("SELECT id, name, color FROM priorities ORDER BY id");
$users = $db->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name");
$mainTopics = $db->query("SELECT id, name FROM main_topics ORDER BY name");

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

// Fetch all project steps
$allSteps = $db->query("SELECT id, project_id, name FROM project_steps ORDER BY order_index");

// Fetch Comments
$comments = $db->query("
    SELECT c.*, u.full_name 
    FROM task_comments c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.task_id = ? 
    ORDER BY c.created_at ASC
", [$taskId]);

// Fetch Attachments
$attachments = $db->query("SELECT * FROM task_attachments WHERE task_id = ? ORDER BY uploaded_at DESC", [$taskId]);

echo json_encode([
    'success' => true,
    'task' => $task,
    'comments' => $comments,
    'attachments' => $attachments,
    'options' => [
        'statuses' => $statuses,
        'priorities' => $priorities,
        'users' => $users,
        'mainTopics' => $mainTopics,
        'projects' => $projects,
        'projectSteps' => $allSteps
    ]
]);
