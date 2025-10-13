<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: login.php'); exit; }

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); echo 'Invalid id'; exit; }

$db = db();
$stmt = $db->prepare('SELECT resume_storage_name, resume_original_name FROM job_applications WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(404); echo 'Not found'; exit; }

$storage = $row['resume_storage_name'];
$orig = $row['resume_original_name'] ?: 'resume';
$path = __DIR__ . '/../data/resumes/' . $storage;
if (!file_exists($path)) { http_response_code(404); echo 'File missing'; exit; }

// Determine mime type if possible (match legacy behavior)
$finfo = @finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? finfo_file($finfo, $path) : 'application/octet-stream';
@finfo_close($finfo);

// Log the download
$logDir = __DIR__ . '/../logs/'; @mkdir($logDir, 0755, true);
$logFile = $logDir . 'resume_downloads.log';
$entry = [
	'timestamp' => function_exists('eastern_now') ? eastern_now('c') : date('c'),
	'admin' => $_SESSION['admin_username'] ?? (defined('ADMIN_USERNAME') ? ADMIN_USERNAME : 'admin'),
	'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
	'file' => $storage,
	'original' => $orig,
	'application_id' => $id,
];
file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);

// Stream file with appropriate headers
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($orig) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
