<?php
/**
 * Department Controller
 * 
 * Department management and permissions
 */

require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/Auth.php';

class DepartmentController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * GET /api/departments
     * Get all departments
     */
    public function index()
    {
        Auth::authenticate();

        $departments = $this->db->query(
            "SELECT d.*, 
                    (SELECT COUNT(*) FROM department_permissions WHERE department_id = d.id) as permission_count
             FROM departments d
             ORDER BY d.name"
        );

        foreach ($departments as &$dept) {
            $dept['id'] = (int) $dept['id'];
            $dept['permission_count'] = (int) $dept['permission_count'];
        }

        Response::success($departments);
    }

    /**
     * POST /api/departments
     * Create new department (Admin only)
     */
    public function create()
    {
        Auth::authenticate();
        Auth::requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true);

        $validator = new Validator();
        $validator->required('name', $data['name'] ?? null);

        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }

        try {
            $this->db->execute(
                "INSERT INTO departments (name, description) VALUES (?, ?)",
                [$data['name'], $data['description'] ?? null]
            );

            $deptId = $this->db->lastInsertId();

            $departments = $this->db->query(
                "SELECT * FROM departments WHERE id = ?",
                [$deptId]
            );

            $dept = $departments[0];
            $dept['id'] = (int) $dept['id'];

            Response::created($dept);
        } catch (Exception $e) {
            error_log("Create department error: " . $e->getMessage());
            Response::serverError('Failed to create department');
        }
    }

    /**
     * PUT /api/departments/:id
     * Update department (Admin only)
     */
    public function update($id)
    {
        Auth::authenticate();
        Auth::requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true);

        $updates = [];
        $params = [];

        if (isset($data['name'])) {
            $updates[] = 'name = ?';
            $params[] = $data['name'];
        }

        if (isset($data['description'])) {
            $updates[] = 'description = ?';
            $params[] = $data['description'];
        }

        if (empty($updates)) {
            Response::error('No fields to update', 400);
        }

        $params[] = $id;

        try {
            $this->db->execute(
                "UPDATE departments SET " . implode(', ', $updates) . " WHERE id = ?",
                $params
            );

            $departments = $this->db->query(
                "SELECT * FROM departments WHERE id = ?",
                [$id]
            );

            if (empty($departments)) {
                Response::notFound('Department not found');
            }

            $dept = $departments[0];
            $dept['id'] = (int) $dept['id'];

            Response::success($dept);
        } catch (Exception $e) {
            error_log("Update department error: " . $e->getMessage());
            Response::serverError('Failed to update department');
        }
    }

    /**
     * POST /api/departments/:id/permissions
     * Add department viewing permission (Admin only)
     */
    public function addPermission($id)
    {
        Auth::authenticate();
        Auth::requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true);

        $validator = new Validator();
        $validator
            ->required('can_view_department_id', $data['can_view_department_id'] ?? null)
            ->isInt('can_view_department_id', $data['can_view_department_id'] ?? null);

        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }

        try {
            $this->db->execute(
                "INSERT IGNORE INTO department_permissions (department_id, can_view_department_id)
                 VALUES (?, ?)",
                [$id, $data['can_view_department_id']]
            );

            Response::success(['message' => 'Permission added successfully']);
        } catch (Exception $e) {
            error_log("Add permission error: " . $e->getMessage());
            Response::serverError('Failed to add permission');
        }
    }

    /**
     * GET /api/departments/:id/permissions
     * Get department permissions
     */
    public function getPermissions($id)
    {
        Auth::authenticate();

        $permissions = $this->db->query(
            "SELECT dp.*, d.name as department_name
             FROM department_permissions dp
             JOIN departments d ON dp.can_view_department_id = d.id
             WHERE dp.department_id = ?",
            [$id]
        );

        foreach ($permissions as &$perm) {
            $perm['id'] = (int) $perm['id'];
            $perm['department_id'] = (int) $perm['department_id'];
            $perm['can_view_department_id'] = (int) $perm['can_view_department_id'];
        }

        Response::success($permissions);
    }
}
