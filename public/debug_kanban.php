<?php
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

check_auth();
$db = Database::getInstance();
$user = Auth::user();

echo "<h3>Session Debug</h3>";
echo "Current User ID: " . $user['id'] . " (" . $user['full_name'] . ")<br>";

echo "<h3>Task Ownership Sample</h3>";
$tasks = $db->query("SELECT id, title, owner_id FROM tasks LIMIT 10");
foreach ($tasks as $t) {
    $match = ($t['owner_id'] == $user['id']) ? "YES" : "NO";
    echo "Task ID {$t['id']}: Owner ID {$t['owner_id']} | User ID {$user['id']} | Match: $match | Title: {$t['title']}<br>";
}
