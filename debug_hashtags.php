<?php
require_once __DIR__ . '/src/db.php';

$db = Database::getInstance();

echo "Checking columns in 'tasks' table...\n";
try {
    $columns = $db->query("SHOW COLUMNS FROM tasks");
    $found = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'hashtags') {
            echo " - Found column 'hashtags' (Type: " . $col['Type'] . ")\n";
            $found = true;
        }
    }
    if (!$found) {
        echo " - ERROR: Column 'hashtags' NOT FOUND!\n";
    }
} catch (Exception $e) {
    echo "Error showing columns: " . $e->getMessage() . "\n";
}

echo "\nTesting get_tasks query...\n";
// Minimal reproduction of get_tasks query
$sql = "SELECT t.id, t.hashtags 
        FROM tasks t 
        LIMIT 1";

try {
    $rows = $db->query($sql);
    echo "Query successful. Row count: " . count($rows) . "\n";
    if (count($rows) > 0) {
        echo "First row hashtags: " . var_export($rows[0]['hashtags'], true) . "\n";
    }
} catch (Exception $e) {
    echo "Query Failed: " . $e->getMessage() . "\n";
}
