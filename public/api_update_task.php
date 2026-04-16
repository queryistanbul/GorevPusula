<?php
/**
 * API: Update Task Details
 * Updates task fields via AJAX
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

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$db = Database::getInstance();
$user = Auth::user();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['task_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Task ID required']);
    exit;
}

$taskId = $input['task_id'];

// Check task exists
$existingTask = $db->fetchOne("SELECT id, title FROM tasks WHERE id = ?", [$taskId]);
if (!$existingTask) {
    http_response_code(404);
    echo json_encode(['error' => 'Task not found']);
    exit;
}

try {
    // Build update fields
    $updates = [];
    $params = [];

    // Status update
    if (isset($input['status_id'])) {
        $newStatus = $db->fetchOne("SELECT name, kanban_column FROM statuses WHERE id = ?", [$input['status_id']]);
        $completedAt = ($newStatus['kanban_column'] === 'done') ? date('Y-m-d H:i:s') : null;

        $updates[] = "status_id = ?";
        $updates[] = "completed_at = ?";
        $params[] = $input['status_id'];
        $params[] = $completedAt;
    }

    // Owner update
    if (isset($input['owner_id'])) {
        $owner = $db->fetchOne("SELECT department_id FROM users WHERE id = ?", [$input['owner_id']]);
        $updates[] = "owner_id = ?";
        $updates[] = "responsible_department_id = ?";
        $params[] = $input['owner_id'];
        $params[] = $owner['department_id'];
    }

    // Priority update
    if (isset($input['priority_id'])) {
        $updates[] = "priority_id = ?";
        $params[] = $input['priority_id'];
    }

    // Main topic update
    if (array_key_exists('main_topic_id', $input)) {
        $updates[] = "main_topic_id = ?";
        $params[] = $input['main_topic_id'] ?: null;
    }

    // Target date update
    if (array_key_exists('target_completion_date', $input)) {
        $updates[] = "target_completion_date = ?";
        $params[] = $input['target_completion_date'] ?: null;
    }

    // Hashtags update
    if (isset($input['hashtags'])) {
        $updates[] = "hashtags = ?";
        $params[] = $input['hashtags'];
    }

    // Project update
    if (array_key_exists('project_id', $input)) {
        $updates[] = "project_id = ?";
        $params[] = $input['project_id'] ?: null;
    }

    // Project step update
    if (array_key_exists('project_step_id', $input)) {
        $updates[] = "project_step_id = ?";
        $params[] = $input['project_step_id'] ?: null;
    }

    if (empty($updates)) {
        echo json_encode(['success' => true, 'message' => 'No changes']);
        exit;
    }

    // Add task ID to params
    $params[] = $taskId;

    $sql = "UPDATE tasks SET " . implode(", ", $updates) . " WHERE id = ?";
    $db->execute($sql, $params);

    // Log activity
    log_activity(
        'update',
        'task',
        $taskId,
        $user['full_name'] . " '{$existingTask['title']}' görevini güncelledi (panel)"
    );

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
