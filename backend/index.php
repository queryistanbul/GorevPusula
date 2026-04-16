<?php
/**
 * Task Management System - Main Entry Point
 * 
 * All API requests are routed through this file
 */

// Load configuration
require_once __DIR__ . '/config.php';

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: ' . CORS_ALLOW_ORIGIN);
    header('Access-Control-Allow-Methods: ' . CORS_ALLOW_METHODS);
    header('Access-Control-Allow-Headers: ' . CORS_ALLOW_HEADERS);
    header('Access-Control-Max-Age: 86400'); // 24 hours
    http_response_code(200);
    exit;
}

// Set CORS headers for all requests
header('Access-Control-Allow-Origin: ' . CORS_ALLOW_ORIGIN);
header('Access-Control-Allow-Methods: ' . CORS_ALLOW_METHODS);
header('Access-Control-Allow-Headers: ' . CORS_ALLOW_HEADERS);
header('Content-Type: application/json; charset=utf-8');

// Log request
$logMessage = date('Y-m-d H:i:s') . " - " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI'] . "\n";
error_log($logMessage, 3, __DIR__ . '/logs/access.log');

// Global error handler
set_exception_handler(function ($e) {
    error_log("Uncaught Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    exit;
});

// Health check endpoint
if ($_SERVER['REQUEST_URI'] === '/api/health' || $_SERVER['REQUEST_URI'] === API_PREFIX . '/health') {
    echo json_encode([
        'status' => 'OK',
        'message' => 'Task Management API is running',
        'timestamp' => date('c')
    ]);
    exit;
}

// Load router
$router = require_once __DIR__ . '/routes.php';

// Dispatch request
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

$router->dispatch($method, $uri);
