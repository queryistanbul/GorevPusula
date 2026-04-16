<?php
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

header('Content-Type: application/json');

// Check auth
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

verify_csrf();

$data = json_decode(file_get_contents('php://input'), true);

$task_id = $data['task_id'] ?? null;
$status_id = $data['status_id'] ?? null;
$order_index = $data['order_index'] ?? null;

if (!$task_id || !$status_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing task_id or status_id']);
    exit;
}

try {
    // Get old task info for logging
    $oldTask = $db->fetchOne("SELECT t.title, s.name as old_status FROM tasks t JOIN statuses s ON t.status_id = s.id WHERE t.id = ?", [$task_id]);

    // Check if new status is a 'done' status
    $status = $db->fetchOne("SELECT name, kanban_column FROM statuses WHERE id = ?", [$status_id]);
    $completedAt = ($status['kanban_column'] === 'done') ? date('Y-m-d H:i:s') : null;

    if ($order_index !== null) {
        $db->execute("UPDATE tasks SET status_id = ?, order_index = ?, completed_at = ? WHERE id = ?", [$status_id, $order_index, $completedAt, $task_id]);
    } else {
        $db->execute("UPDATE tasks SET status_id = ?, completed_at = ? WHERE id = ?", [$status_id, $completedAt, $task_id]);
    }

    // Log activity
    $userName = $_SESSION['user']['full_name'];
    log_activity(
        'status_change',
        'task',
        $task_id,
        "$userName '{$oldTask['title']}' görevinin durumunu '{$oldTask['old_status']}' → '{$status['name']}' olarak değiştirdi",
        ['status' => $oldTask['old_status']],
        ['status' => $status['name']]
    );

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
