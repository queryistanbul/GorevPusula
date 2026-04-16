<?php
require_once __DIR__ . '/../src/db.php';
$db = Database::getInstance();
$tasks = $db->query("SELECT id, title, owner_id, requester_id FROM tasks WHERE title LIKE 'MMMM%' OR title LIKE 'faafas%' OR title = 'deneme'");
echo json_encode($tasks);
