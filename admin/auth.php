<?php
/**
 * GB Launcher - Autenticação do Painel Admin
 */
session_start();

require_once __DIR__ . '/db.php';

define('ADMIN_SESSION_KEY', 'gb_admin_logged_in');
define('ADMIN_USER_KEY',    'gb_admin_user');

function isLoggedIn(): bool {
    return !empty($_SESSION[ADMIN_SESSION_KEY]);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function attemptLogin(string $username, string $password): bool {
    $db   = getDB();
    $stmt = $db->prepare("SELECT id, username, password FROM admin_users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION[ADMIN_SESSION_KEY] = true;
        $_SESSION[ADMIN_USER_KEY]    = $user['username'];
        session_regenerate_id(true);
        return true;
    }
    return false;
}

function logout(): void {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}
