<?php
/**
 * Authentication & Session Management
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/services.php';

function is_logged_in(): bool {
    return isset($_SESSION['user']) && !empty($_SESSION['user']);
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function user_role(): ?string {
    return $_SESSION['user']['role'] ?? null;
}

function require_auth(array|string $roles = []): void {
    if (!is_logged_in()) {
        header("Location: " . BASE_URL . "/index.php");
        exit;
    }

    if (!empty($roles)) {
        if (is_string($roles)) {
            $roles = [$roles];
        }
        if (!in_array(user_role(), $roles, true)) {
            http_response_code(403);
            die("Access Denied: You do not have permission to view this page.");
        }
    }
}

function login_admin(string $password): bool {
    if ($password === ADMIN_PASSWORD) {
        $_SESSION['user'] = [
            'id'    => 0,
            'role'  => 'admin',
            'name'  => 'Administrator',
            'email' => 'admin@vignan.ac.in',
            'phone' => '',
            'department' => 'Examination Cell'
        ];
        return true;
    }
    return false;
}

function login_person(string $role, string $email, string $access_code): bool {
    $person = services_authenticate($role, $email, $access_code);
    if ($person) {
        $_SESSION['user'] = $person;
        return true;
    }
    return false;
}

function logout(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
