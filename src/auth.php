<?php

require_once __DIR__ . '/db.php';

class Auth
{
    public static function attempt($email, $password)
    {
        $db = Database::getInstance();
        $user = $db->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'department_id' => $user['department_id'],
                'role' => $user['is_admin'] ? 'admin' : 'user'
            ];

            // Fetch managed departments
            $managed_depts = $db->query("SELECT department_id FROM user_departments WHERE user_id = ?", [$user['id']]);
            $_SESSION['user']['managed_department_ids'] = array_column($managed_depts, 'department_id');

            // Load permissions if needed
            // $_SESSION['permissions'] = ...

            // Log login activity
            log_activity('login', 'user', $user['id'], $user['full_name'] . ' sisteme giriş yaptı');

            return true;
        }

        return false;
    }

    public static function logout()
    {
        session_destroy();
    }

    public static function check()
    {
        return isset($_SESSION['user']);
    }

    public static function user()
    {
        return $_SESSION['user'] ?? null;
    }
}
