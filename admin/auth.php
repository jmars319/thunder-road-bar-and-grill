<?php
/**
 * admin/auth.php
 * Login handler for the admin UI. Expects a POST with username,
 * password, and a valid CSRF token. On success it sets session
 * variables and redirects to `admin/index.php`.
 *
 * Important:
 *  - CSRF is required for the login POST to mitigate token-less
 *    login attempts coming from third-party sites.
 *  - Passwords are verified against the stored hash returned by
 *    `get_admin_hash()` which may source from `auth.json` or the
 *    fallback constant in `config.php`.
 */

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Load helpers and config
    require_once 'config.php';

    // Verify CSRF token
    $csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verify_csrf_token($csrf)) {
        header('Location: login.php?error=1');
        exit;
    }

    // get stored hash (from auth.json if present)
    $storedHash = get_admin_hash();

    if ($username === ADMIN_USERNAME && !empty($storedHash) && password_verify($password, $storedHash)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['login_time'] = time();
        // store current auth version so we can detect password changes
        $_SESSION['admin_pw_version'] = get_admin_password_version();

        header('Location: index.php');
        exit;
    } else {
        header('Location: login.php?error=1');
        exit;
    }
} else {
    header('Location: login.php');
    exit;
}
?>
