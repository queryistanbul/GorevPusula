<?php
/**
 * API: Add Comment to Task
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$db = Database::getInstance();
$user = Auth::user();

$input = json_decode(file_get_contents('php://input'), true);

$taskId = $input['task_id'] ?? null;
$comment = trim($input['comment'] ?? '');

if (!$taskId || empty($comment)) {
    http_response_code(400);
    echo json_encode(['error' => 'Task ID and comment required']);
    exit;
}

try {
    $db->execute(
        "INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)",
        [$taskId, $user['id'], $comment]
    );

    $newId = $db->lastInsertId();

    // Get the new comment with user info
    $newComment = $db->fetchOne("
        SELECT c.*, u.full_name 
        FROM task_comments c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id = ?
    ", [$newId]);

    echo json_encode([
        'success' => true,
        'comment' => $newComment
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
