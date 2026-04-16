<?php
/**
 * Authentication Controller
 * 
 * Handles login, logout, and user session management
 */

require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../middleware/Auth.php';

class AuthController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * POST /api/auth/login
     * Login user and return JWT token
     */
    public function login()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // Validate input
        $validator = new Validator();
        $validator
            ->required('username', $data['username'] ?? null)
            ->required('password', $data['password'] ?? null);

        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }

        $username = $data['username'];
        $password = $data['password'];

        // Find user
        $users = $this->db->query(
            "SELECT u.*, d.name as department_name 
             FROM users u 
             JOIN departments d ON u.department_id = d.id 
             WHERE u.username = ? AND u.is_active = TRUE",
            [$username]
        );

        if (empty($users)) {
            Response::unauthorized('Invalid credentials');
        }

        $user = $users[0];

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            Response::unauthorized('Invalid credentials');
        }

        // Create JWT token
        $token = JWT::encode([
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'department_id' => (int) $user['department_id'],
            'is_admin' => (bool) $user['is_admin']
        ]);

        // Return token and user data
        Response::success([
            'token' => $token,
            'user' => [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'department_id' => (int) $user['department_id'],
                'department_name' => $user['department_name'],
                'is_admin' => (bool) $user['is_admin']
            ]
        ]);
    }

    /**
     * GET /api/auth/me
     * Get current user information
     */
    public function me()
    {
        Auth::authenticate();
        $user = Auth::user();

        // Reload full user data
        $users = $this->db->query(
            "SELECT u.id, u.username, u.full_name, u.email, u.department_id, u.is_admin, d.name as department_name
             FROM users u 
             JOIN departments d ON u.department_id = d.id 
             WHERE u.id = ?",
            [$user['id']]
        );

        if (empty($users)) {
            Response::notFound('User not found');
        }

        $userData = $users[0];
        $userData['id'] = (int) $userData['id'];
        $userData['department_id'] = (int) $userData['department_id'];
        $userData['is_admin'] = (bool) $userData['is_admin'];

        Response::success($userData);
    }

    /**
     * POST /api/auth/logout
     * Logout (handled client-side by removing token)
     */
    public function logout()
    {
        Auth::authenticate();
        Response::success(['message' => 'Logged out successfully']);
    }
}
