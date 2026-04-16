<?php
require_once __DIR__ . '/src/db.php';
$db = Database::getInstance();
$statuses = $db->query("SELECT * FROM statuses");
print_r($statuses);
