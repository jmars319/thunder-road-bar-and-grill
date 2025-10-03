<?php
require_once __DIR__ . '/config.php';
require_admin();
// simple CSRF check
$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) { header('HTTP/1.1 400 Bad Request'); echo 'Invalid CSRF token'; exit; }
$username = trim($_POST['smtp_username'] ?? '');
$password = trim($_POST['smtp_password'] ?? '');
$path = __DIR__ . '/auth.json';
$existing = [];
if (file_exists($path)) {
    $raw = @file_get_contents($path);
    $existing = $raw ? json_decode($raw, true) : [];
    if (!is_array($existing)) $existing = [];
}
if ($username !== '') $existing['smtp_username'] = $username;
if ($password !== '') $existing['smtp_password'] = $password;
// atomic write: write to tmp then rename
$tmp = $path . '.tmp';
$json = json_encode($existing, JSON_PRETTY_PRINT);
if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false) {
    header('Location: smtp-settings.php?msg=save_failed'); exit;
}
if (!@rename($tmp, $path)) { @unlink($tmp); header('Location: smtp-settings.php?msg=save_failed'); exit; }
@chmod($path, 0640);
header('Location: smtp-settings.php?msg=save_ok');
exit;
