<?php
require_once __DIR__ . '/src/db.php';
$db = Database::getInstance();
$columns = $db->query("SHOW COLUMNS FROM tasks");
foreach ($columns as $col) {
    echo $col['Field'] . "\n";
}
