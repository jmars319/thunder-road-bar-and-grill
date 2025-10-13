<?php
declare(strict_types=1);

// Load Composer autoloader (and vlucas/phpdotenv if installed)
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment from project root if phpdotenv is available
if (class_exists(Dotenv::class)) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    // safeLoad won't throw if .env is missing
    $dotenv->safeLoad();
}

// Determine environment (default to development)
$appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'development');

// Disable display_errors in production, enable in non-production
if (strtolower($appEnv) === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

// Configure session cookie parameters with security in mind
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

$cookieLifetime = 0; // until browser close
$cookiePath = '/';
$cookieDomain = $_SERVER['HTTP_HOST'] ?? '';
$cookieSecure = $isSecure;
$cookieHttpOnly = true;
$cookieSameSite = 'Lax';

// session_set_cookie_params accepts an array from PHP 7.3+
// Only set cookie params when a session is not already active to avoid warnings
if (session_status() === PHP_SESSION_NONE) {
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => $cookieLifetime,
            'path' => $cookiePath,
            'domain' => $cookieDomain,
            'secure' => $cookieSecure,
            'httponly' => $cookieHttpOnly,
            'samesite' => $cookieSameSite,
        ]);
    } else {
        // Fallback for older PHP versions: samesite must be set via path hack
        $path = $cookiePath . '; samesite=' . $cookieSameSite;
        session_set_cookie_params($cookieLifetime, $path, $cookieDomain, $cookieSecure, $cookieHttpOnly);
    }
} else {
    // Session already active; cannot change cookie params now. Leave existing params.
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Expose a shared PDO instance via db()
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = getenv('DB_DSN') ?: ($_ENV['DB_DSN'] ?? null);
    $user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? null);
    $pass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? null);

    if (empty($dsn)) {
        throw new RuntimeException('Environment variable DB_DSN is not set');
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);

    return $pdo;
}
