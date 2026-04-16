<?php
require_once __DIR__ . '/../src/db.php';
$db = Database::getInstance();
$users = $db->query("SELECT id, full_name, is_admin FROM users WHERE full_name LIKE '%Erhan%'");
echo json_encode($users);
