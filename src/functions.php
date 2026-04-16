<?php
date_default_timezone_set('Europe/Istanbul');

function dd($data)
{
    echo '<pre>';
    print_r($data);
    echo '</pre>';
    die();
}

function redirect($path)
{
    header("Location: $path");
    exit;
}

function view($path, $data = [])
{
    extract($data);
    require SRC_DIR . "/views/$path.php";
}

function escape($string)
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function user()
{
    return $_SESSION['user'] ?? null;
}

function check_auth()
{
    if (!isset($_SESSION['user'])) {
        redirect('login.php');
    }
}

function get_tasks($db, $user, $myTasksOnly = false, $overdueOnly = false, $showCompleted = false, $orderBy = "s.order_index ASC, t.completed_at DESC, t.created_at DESC", $filterOwnerId = null, $filterProjectId = null)
{
    // ... (Query construction remains the same up to filter logic) ...
    $sql = "SELECT t.*,
            p.name as priority_name, p.color as priority_color,
            s.name as status_name, s.color as status_color,
            mt.name as main_topic_name,
            st.name as sub_topic_name,
            o.full_name as owner_name, od.name as owner_department,
            r.full_name as requester_name, rd.name as requester_department,
            proj.project_code, proj.name as project_name, step.name as step_name
        FROM tasks t
        LEFT JOIN priorities p ON t.priority_id = p.id
        LEFT JOIN statuses s ON t.status_id = s.id
        LEFT JOIN main_topics mt ON t.main_topic_id = mt.id
        LEFT JOIN sub_topics st ON t.sub_topic_id = st.id
        LEFT JOIN users o ON t.owner_id = o.id
        LEFT JOIN departments od ON o.department_id = od.id
        LEFT JOIN users r ON t.requester_id = r.id
        LEFT JOIN departments rd ON r.department_id = rd.id
        LEFT JOIN projects proj ON t.project_id = proj.id
        LEFT JOIN project_steps step ON t.project_step_id = step.id";

    // Filter Logic
    $params = [];
    $wheres = [];

    if ($myTasksOnly) {
        $wheres[] = "(t.owner_id = ? OR t.requester_id = ?)";
        $params[] = $user['id'];
        $params[] = $user['id'];
    } elseif ($user['role'] !== 'admin') {
        // Collect all department IDs the user is responsible for
        $deptIds = [$user['department_id']];
        if (!empty($user['managed_department_ids'])) {
            $deptIds = array_merge($deptIds, $user['managed_department_ids']);
        }

        // Create placeholders for IN clause (?,?,?)
        $placeholders = implode(',', array_fill(0, count($deptIds), '?'));

        $wheres[] = "(t.responsible_department_id IN ($placeholders) OR t.requester_id = ?)";
        $params = array_merge($params, $deptIds); // Add all dept IDs to params
        $params[] = $user['id']; // Add user ID for requester check
    }

    if ($overdueOnly) {
        $wheres[] = "t.target_completion_date < CURDATE() AND t.completed_at IS NULL";
    }

    if ($filterOwnerId) {
        $wheres[] = "t.owner_id = ?";
        $params[] = $filterOwnerId;
    }

    if ($filterProjectId) {
        $wheres[] = "t.project_id = ?";
        $params[] = $filterProjectId;
    }

    if (!empty($wheres)) {
        $sql .= " WHERE " . implode(' AND ', $wheres);
    }

    // Default Filter: Hide DONE tasks older than 24 hours unless $showCompleted is true
    if (!$showCompleted) {
        $prefix = (strpos($sql, 'WHERE') !== false) ? " AND " : " WHERE ";
        $sql .= $prefix . " (s.kanban_column != 'done' OR t.completed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) OR t.completed_at IS NULL)";
    }

    $sql .= " ORDER BY " . $orderBy;

    return $db->query($sql, $params);
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!$token || $token !== $_SESSION['csrf_token']) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                http_response_code(403);
                echo json_encode(['error' => 'CSRF validation failed']);
                exit;
            }
            die('CSRF doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.');
        }
    }
}

/**
 * Log an activity to the audit_logs table
 * 
 * @param string $actionType - create, update, delete, login, logout, status_change, etc.
 * @param string $entityType - task, user, project, business_plan, status, priority, etc.
 * @param int|null $entityId - ID of the affected entity
 * @param string $description - Human readable description, e.g. "Ahmet 'Proje X' görevini sildi"
 * @param array|null $oldValues - Previous values (will be JSON encoded)
 * @param array|null $newValues - New values (will be JSON encoded)
 */
function log_activity($actionType, $entityType, $entityId, $description, $oldValues = null, $newValues = null)
{
    try {
        $db = Database::getInstance();
        $userId = $_SESSION['user']['id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        $db->execute(
            "INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, description, old_values, new_values, ip_address) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $userId,
                $actionType,
                $entityType,
                $entityId,
                $description,
                $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                $ip
            ]
        );
    } catch (Exception $e) {
        // Silently fail - audit logging should not break the app
        error_log("Audit log error: " . $e->getMessage());
    }
}
