<?php
/**
 * User Controller
 * 
 * User management CRUD operations (Admin only)
 */

require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/Auth.php';

class UserController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * GET /api/users
     * Get all users (Admin only)
     */
    public function index()
    {
        Auth::authenticate();
        Auth::requireAdmin();

        $users = $this->db->query(
            "SELECT u.id, u.username, u.full_name, u.email, u.department_id, u.is_admin, u.is_active, u.created_at,
                    d.name as department_name
             FROM users u
             JOIN departments d ON u.department_id = d.id
             ORDER BY u.created_at DESC"
        );

        // Convert types
        foreach ($users as &$user) {
            $user['id'] = (int) $user['id'];
            $user['department_id'] = (int) $user['department_id'];
            $user['is_admin'] = (bool) $user['is_admin'];
            $user['is_active'] = (bool) $user['is_active'];
            unset($user['password_hash']); // Safety
        }

        Response::success($users);
    }

    /**
     * POST /api/users
     * Create new user (Admin only)
     */
    public function create()
    {
        Auth::authenticate();
        Auth::requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true);

        // Validate input
        $validator = new Validator();
        $validator
            ->required('username', $data['username'] ?? null)
            ->required('password', $data['password'] ?? null)
            ->minLength('password', $data['password'] ?? null, 6)
            ->required('full_name', $data['full_name'] ?? null)
            ->required('email', $data['email'] ?? null)
            ->isEmail('email', $data['email'] ?? null)
            ->required('department_id', $data['department_id'] ?? null)
            ->isInt('department_id', $data['department_id'] ?? null);

        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }

        // Check for duplicate username or email
        $existing = $this->db->query(
            "SELECT id FROM users WHERE username = ? OR email = ?",
            [$data['username'], $data['email']]
        );

        if (!empty($existing)) {
            Response::error('Username or email already exists', 400);
        }

        // Hash password
        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);

        // Insert user
        try {
            $this->db->execute(
                "INSERT INTO users (username, password_hash, full_name, email, department_id, is_admin, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $data['username'],
                    $passwordHash,
                    $data['full_name'],
                    $data['email'],
                    $data['department_id'],
                    isset($data['is_admin']) ? (int) $data['is_admin'] : 0,
                    isset($data['is_active']) ? (int) $data['is_active'] : 1
                ]
            );

            $userId = $this->db->lastInsertId();

            // Get created user
            $users = $this->db->query(
                "SELECT u.*, d.name as department_name
                 FROM users u
                 JOIN departments d ON u.department_id = d.id
                 WHERE u.id = ?",
                [$userId]
            );

            $user = $users[0];
            $user['id'] = (int) $user['id'];
            $user['department_id'] = (int) $user['department_id'];
            $user['is_admin'] = (bool) $user['is_admin'];
            $user['is_active'] = (bool) $user['is_active'];
            unset($user['password_hash']);

            Response::created($user);
        } catch (Exception $e) {
            error_log("Create user error: " . $e->getMessage());
            Response::serverError('Failed to create user');
        }
    }

    /**
     * PUT /api/users/:id
     * Update user (Admin only)
     */
    public function update($id)
    {
        Auth::authenticate();
        Auth::requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true);

        // Check if user exists
        $existingUsers = $this->db->query("SELECT id FROM users WHERE id = ?", [$id]);
        if (empty($existingUsers)) {
            Response::notFound('User not found');
        }

        $updates = [];
        $params = [];

        if (isset($data['username'])) {
            $updates[] = 'username = ?';
            $params[] = $data['username'];
        }

        if (isset($data['password']) && !empty($data['password'])) {
            $updates[] = 'password_hash = ?';
            $params[] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        if (isset($data['full_name'])) {
            $updates[] = 'full_name = ?';
            $params[] = $data['full_name'];
        }

        if (isset($data['email'])) {
            $updates[] = 'email = ?';
            $params[] = $data['email'];
        }

        if (isset($data['department_id'])) {
            $updates[] = 'department_id = ?';
            $params[] = $data['department_id'];
        }

        if (isset($data['is_admin'])) {
            $updates[] = 'is_admin = ?';
            $params[] = (int) $data['is_admin'];
        }

        if (isset($data['is_active'])) {
            $updates[] = 'is_active = ?';
            $params[] = (int) $data['is_active'];
        }

        if (empty($updates)) {
            Response::error('No fields to update', 400);
        }

        $params[] = $id;

        try {
            $this->db->execute(
                "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?",
                $params
            );

            // Get updated user
            $users = $this->db->query(
                "SELECT u.*, d.name as department_name
                 FROM users u
                 JOIN departments d ON u.department_id = d.id
                 WHERE u.id = ?",
                [$id]
            );

            $user = $users[0];
            $user['id'] = (int) $user['id'];
            $user['department_id'] = (int) $user['department_id'];
            $user['is_admin'] = (bool) $user['is_admin'];
            $user['is_active'] = (bool) $user['is_active'];
            unset($user['password_hash']);

            Response::success($user);
        } catch (Exception $e) {
            error_log("Update user error: " . $e->getMessage());
            Response::serverError('Failed to update user');
        }
    }

    /**
     * DELETE /api/users/:id
     * Delete user (Admin only)
     */
    public function delete($id)
    {
        Auth::authenticate();
        Auth::requireAdmin();

        // Check if user has tasks
        $tasks = $this->db->query(
            "SELECT COUNT(*) as count FROM tasks WHERE owner_id = ? OR requester_id = ?",
            [$id, $id]
        );

        if ($tasks[0]['count'] > 0) {
            Response::error('Cannot delete user with associated tasks', 400);
        }

        try {
            $this->db->execute("DELETE FROM users WHERE id = ?", [$id]);
            Response::success(['message' => 'User deleted successfully']);
        } catch (Exception $e) {
            error_log("Delete user error: " . $e->getMessage());
            Response::serverError('Failed to delete user');
        }
    }
}
