<?php
// Ensure a session is active for auth helpers
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin credentials
define('ADMIN_USERNAME', 'admin');

// Generate password hash: password_hash('your-password', PASSWORD_DEFAULT)
// Default password is 'admin123' - CHANGE THIS!
define('ADMIN_PASSWORD_HASH', '$2y$12$hKkiRiF9ZZs82lxU.xYNGOFVKsB188cgTttg7NCLxJEb4Hi2qPT2S');

// Content file path
define('CONTENT_FILE', '../data/content.json');

// Upload directory
define('UPLOAD_DIR', '../uploads/images/');

// Notification email for reservation submissions (change as needed)
define('RESERVATION_NOTIFICATION_EMAIL', 'thundergrillmidway@gmail.com');

// --- SMTP / mail configuration (for PHPMailer)
// If you install dependencies via Composer and want to send via SMTP
// configure these values. Keep credentials out of git; consider
// placing them in a separate `admin/auth.json` file or environment vars.
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'thundergrillmidway@gmail.com');
// Default empty password; prefer loading from admin/auth.json (untracked)
define('SMTP_PASSWORD', ''); // <-- do NOT commit a real password here
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'
define('SMTP_FROM_ADDRESS', 'thundergrillmidway@gmail.com');
define('SMTP_FROM_NAME', 'Thunder Road Reservations');

// If an untracked `admin/auth.json` file exists, load SMTP credentials
// from it so you don't commit secrets into the repo. Create or edit
// `admin/auth.json` and add the fields shown in the example below.
// Example `admin/auth.json`:
// {
//   "smtp_username": "thundergrillmidway@gmail.com",
//   "smtp_password": "<YOUR-APP-PASSWORD-HERE>"
// }
// NOTE: The password should be a Gmail App Password (if using Google),
// not your main Google account password. This file is ignored by Git.
$authPath = __DIR__ . '/auth.json';
if (file_exists($authPath)) {
    $raw = @file_get_contents($authPath);
    $j = $raw ? json_decode($raw, true) : null;
    if (is_array($j)) {
        // Prefer explicit override values from the auth file. Because PHP
        // constants cannot be redefined at runtime, we store overrides in
        // the $GLOBALS array and other code will prefer those if present.
        if (!empty($j['smtp_password'])) {
            $GLOBALS['SMTP_PASSWORD_OVERRIDE'] = $j['smtp_password'];
        }
        if (!empty($j['smtp_username'])) {
            $GLOBALS['SMTP_USERNAME_OVERRIDE'] = $j['smtp_username'];
        }
    }
}

// Session timeout (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Check if session is valid
function checkAuth() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }
    
    // Check session timeout
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > SESSION_TIMEOUT)) {
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }
    
    // Update last activity time
    $_SESSION['login_time'] = time();
}

/**
 * CSRF helpers
 */
function generate_csrf_token() {
    // token valid for 1 hour
    $ttl = 3600;
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || (time() - $_SESSION['csrf_token_time']) > $ttl) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (empty($token)) {
        return false;
    }
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    $valid = hash_equals($_SESSION['csrf_token'], $token);
    return $valid;
}

// Compatibility helper wrappers used by admin pages
function require_admin() {
    // check basic session and timeout
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }
    // session timeout handled by checkAuth (if available)
    if (function_exists('checkAuth')) checkAuth();

    // check password version to force logout if password changed
    $currentVersion = get_admin_password_version();
    $sessVersion = $_SESSION['admin_pw_version'] ?? 0;
    if ($sessVersion !== $currentVersion) {
        do_logout();
        header('Location: login.php?timeout=1');
        exit;
    }
}

function do_logout() {
    // clear session
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function ensure_csrf_token() {
    return generate_csrf_token();
}

function verify_csrf($token) {
    return verify_csrf_token($token);
}

function csrf_input_field() {
    $t = htmlspecialchars(generate_csrf_token(), ENT_QUOTES);
    return "<input type=\"hidden\" name=\"csrf_token\" value=\"{$t}\">";
}

/**
 * Return a formatted timestamp in US/Eastern timezone.
 * Usage: eastern_now('c') or eastern_now('Y-m-d H:i:s')
 */
function eastern_now($format = 'c') {
    try {
        $dt = new DateTime('now', new DateTimeZone('America/New_York'));
        return $dt->format($format);
    } catch (Exception $e) {
        // fallback to server default timezone format
        return date($format);
    }
}

// -- Admin auth storage helpers -------------------------------------------------
// Path to optional JSON-backed auth file (untracked in reposafe setups)
function admin_auth_file_path() {
    return __DIR__ . '/auth.json';
}

// Return ['hash'=>..., 'version'=>int]
function get_admin_auth() {
    $path = admin_auth_file_path();
    if (file_exists($path)) {
        $j = @file_get_contents($path);
        if ($j !== false) {
            $data = json_decode($j, true);
            if (is_array($data) && isset($data['hash'])) {
                return [
                    'hash' => $data['hash'],
                    'version' => isset($data['version']) ? (int)$data['version'] : 0,
                ];
            }
        }
    }
    // fallback to constant defined in this file
    return [
        'hash' => defined('ADMIN_PASSWORD_HASH') ? ADMIN_PASSWORD_HASH : '',
        'version' => 0,
    ];
}

function get_admin_hash() {
    $a = get_admin_auth();
    return $a['hash'];
}

function get_admin_password_version() {
    $a = get_admin_auth();
    return $a['version'];
}

// write auth.json safely (backup original). $hash should be a bcrypt hash string.
function set_admin_hash_and_bump_version($hash) {
    $path = admin_auth_file_path();
    // read existing
    $existing = null;
    if (file_exists($path)) {
        $existing = @file_get_contents($path);
    }
    // backup existing auth file if present
    if ($existing !== null && $existing !== false) {
        $bak = $path . '.bak-' . date('Ymd-His');
    // atomic backup write
    $tmpBak = $bak . '.tmp';
    if (file_put_contents($tmpBak, $existing, LOCK_EX) !== false) {@rename($tmpBak, $bak);} else { error_log('config.php: failed to write backup ' . $bak); }
    }
    $data = ['hash' => $hash, 'version' => time()];
    $tmp = $path . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT);
    if ($json === false) return false;
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
    return true;
}

?>
