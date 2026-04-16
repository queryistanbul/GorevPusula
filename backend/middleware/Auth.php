<?php
/**
 * Authentication Middleware
 * 
 * Validates JWT token and loads user information
 */

require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../database/Database.php';

class Auth
{
    private static $currentUser = null;

    /**
     * Authenticate request
     * Returns user data or exits with 401
     */
    public static function authenticate()
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

        if (!$authHeader) {
            Response::unauthorized('No authorization token provided');
        }

        // Extract token from "Bearer <token>"
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            Response::unauthorized('Invalid authorization format');
        }

        $token = $matches[1];
        $payload = JWT::decode($token);

        if (!$payload) {
            Response::unauthorized('Invalid or expired token');
        }

        // Load user from database to ensure it's still active
        $db = Database::getInstance();
        $users = $db->query(
            "SELECT u.*, d.name as department_name 
             FROM users u 
             JOIN departments d ON u.department_id = d.id 
             WHERE u.id = ? AND u.is_active = TRUE",
            [$payload['id']]
        );

        if (empty($users)) {
            Response::unauthorized('User not found or inactive');
        }

        $user = $users[0];

        // Store user data
        self::$currentUser = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'department_id' => (int) $user['department_id'],
            'department_name' => $user['department_name'],
            'is_admin' => (bool) $user['is_admin']
        ];

        return self::$currentUser;
    }

    /**
     * Get current authenticated user
     */
    public static function user()
    {
        return self::$currentUser;
    }

    /**
     * Check if current user is admin
     */
    public static function isAdmin()
    {
        return self::$currentUser && self::$currentUser['is_admin'];
    }

    /**
     * Require admin access
     */
    public static function requireAdmin()
    {
        if (!self::isAdmin()) {
            Response::forbidden('Admin access required');
        }
    }
}
