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

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($orig) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
