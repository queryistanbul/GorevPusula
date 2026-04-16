<?php
/**
 * Task Management System - Configuration
 * 
 * Central configuration file for database, JWT, and system settings
 */

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 for development
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php-error.log');

// Timezone
date_default_timezone_set('Europe/Istanbul');

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'task_management');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_CHARSET', 'utf8mb4');

// JWT Configuration
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'your-secret-key-change-this-in-production');
define('JWT_EXPIRE', getenv('JWT_EXPIRE') ?: '24h'); // 24 hours
define('JWT_ALGORITHM', 'HS256');

// File Upload Configuration
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 10485760); // 10MB in bytes
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'zip', 'rar']);

// CORS Configuration
define('CORS_ALLOW_ORIGIN', getenv('CORS_ALLOW_ORIGIN') ?: 'http://localhost:3000');
define('CORS_ALLOW_METHODS', 'GET, POST, PUT, DELETE, OPTIONS');
define('CORS_ALLOW_HEADERS', 'Content-Type, Authorization');

// Application Configuration
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('API_PREFIX', '/api');

// Convert JWT_EXPIRE to seconds
function getJwtExpireSeconds() {
    $expire = JWT_EXPIRE;
    if (is_numeric($expire)) {
        return (int)$expire;
    }
    
    // Parse time strings like '24h', '7d', '30m'
    if (preg_match('/^(\d+)([smhd])$/', $expire, $matches)) {
        $value = (int)$matches[1];
        $unit = $matches[2];
        
        switch ($unit) {
            case 's': return $value;
            case 'm': return $value * 60;
            case 'h': return $value * 3600;
            case 'd': return $value * 86400;
        }
    }
    
    return 86400; // Default: 24 hours
}

// Create necessary directories
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}
