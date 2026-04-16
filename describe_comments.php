<?php
require_once __DIR__ . '/src/db.php';
$db = Database::getInstance();
$columns = $db->query("DESCRIBE task_comments");
print_r($columns);
