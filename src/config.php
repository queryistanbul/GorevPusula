<?php
/**
 * Core Configuration
 */

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1); // Enable for development
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../writable/logs/php-error.log');

// Timezone
date_default_timezone_set('Europe/Istanbul');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'a_task_dev');
define('DB_USER', 'root');
define('DB_PASSWORD', ''); // Only for local dev
define('DB_CHARSET', 'utf8mb4');

// Paths
define('ROOT_DIR', dirname(__DIR__));
define('SRC_DIR', __DIR__);
define('PUBLIC_DIR', ROOT_DIR . '/public');
define('UPLOAD_DIR', ROOT_DIR . '/writable/uploads');

// Create writable directories if not exist
if (!file_exists(dirname(ini_get('error_log')))) {
    mkdir(dirname(ini_get('error_log')), 0755, true);
}
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Session Configuration
session_start();
