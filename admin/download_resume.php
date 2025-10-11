<?php
require_once __DIR__ . '/config.php';
require_admin();

$name = $_GET['file'] ?? '';
if (!$name || preg_match('/[^A-Za-z0-9._-]/', $name)) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Invalid file';
    exit;
}

$path = __DIR__ . '/../data/resumes/' . $name;
if (!file_exists($path) || !is_file($path)) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not found';
    exit;
}

$orig = $_GET['orig'] ?? basename($path);
// Stream file with appropriate headers
$finfo = @finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? finfo_file($finfo, $path) : 'application/octet-stream';
@finfo_close($finfo);

// Log the download (append JSON line)
$logDir = __DIR__ . '/../logs/'; @mkdir($logDir, 0755, true);
$logFile = $logDir . 'resume_downloads.log';
$entry = [
    'timestamp' => function_exists('eastern_now') ? eastern_now('c') : date('c'),
    'admin' => $_SESSION['admin_username'] ?? (defined('ADMIN_USERNAME') ? ADMIN_USERNAME : 'admin'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'file' => $name,
    'original' => $orig,
    'application_id' => $_GET['app_id'] ?? null,
];
file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($orig) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
