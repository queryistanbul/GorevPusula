<?php
/**
 * Permission Middleware
 * 
 * Check department-based permissions for task access
 */

require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/Auth.php';

class Permissions
{
    private static $viewableDepartments = null;
    private static $canViewAll = false;

    /**
     * Load department permissions for current user
     */
    public static function loadPermissions()
    {
        $user = Auth::user();

        if (!$user) {
            return;
        }

        // Admin can view all departments
        if ($user['is_admin']) {
            self::$canViewAll = true;
            return;
        }

        $db = Database::getInstance();

        // Get departments this user's department can view
        $permissions = $db->query(
            "SELECT can_view_department_id 
             FROM department_permissions 
             WHERE department_id = ?",
            [$user['department_id']]
        );

        // Always include own department
        self::$viewableDepartments = [$user['department_id']];

        foreach ($permissions as $perm) {
            self::$viewableDepartments[] = (int) $perm['can_view_department_id'];
        }

        self::$viewableDepartments = array_unique(self::$viewableDepartments);
    }

    /**
     * Check if user can view a specific department
     */
    public static function canViewDepartment($departmentId)
    {
        if (self::$canViewAll) {
            return true;
        }

        return in_array($departmentId, self::$viewableDepartments ?? []);
    }

    /**
     * Get list of viewable department IDs
     */
    public static function getViewableDepartments()
    {
        return self::$viewableDepartments ?? [];
    }

    /**
     * Check if user can view all departments
     */
    public static function canViewAllDepartments()
    {
        return self::$canViewAll;
    }

    /**
     * Build SQL WHERE clause for department filtering
     */
    public static function getDepartmentFilterSQL($requestingDeptColumn = 'requesting_department_id', $responsibleDeptColumn = 'responsible_department_id')
    {
        if (self::$canViewAll) {
            return ['', []];
        }

        $deptIds = self::$viewableDepartments ?? [];

        if (empty($deptIds)) {
            return ['AND 1=0', []]; // No access
        }

        $placeholders = implode(',', array_fill(0, count($deptIds), '?'));
        $sql = "AND ($responsibleDeptColumn IN ($placeholders) OR $requestingDeptColumn IN ($placeholders))";
        $params = array_merge($deptIds, $deptIds);

        return [$sql, $params];
    }
}
