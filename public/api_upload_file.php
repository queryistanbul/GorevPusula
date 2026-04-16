<?php
/**
 * API: Upload File to Task
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

$taskId = $_POST['task_id'] ?? null;

if (!$taskId) {
    http_response_code(400);
    echo json_encode(['error' => 'Task ID required']);
    exit;
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== 0) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit;
}

try {
    $uploadDir = __DIR__ . '/uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $file = $_FILES['file'];
    $originalName = $file['name'];
    $tmpName = $file['tmp_name'];
    $size = $file['size'];
    $fileName = time() . '_' . $originalName;
    $uploadPath = $uploadDir . $fileName;

    if (move_uploaded_file($tmpName, $uploadPath)) {
        $db->execute(
            "INSERT INTO task_attachments (task_id, file_name, file_path, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?)",
            [$taskId, $originalName, 'uploads/' . $fileName, $size, $user['id']]
        );

        $newId = $db->lastInsertId();
        $attachment = $db->fetchOne("SELECT * FROM task_attachments WHERE id = ?", [$newId]);

        echo json_encode([
            'success' => true,
            'attachment' => $attachment
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save file']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
