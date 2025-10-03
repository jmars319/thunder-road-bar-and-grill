<?php
/**
 * admin/list-images.php
 * JSON helper used by the admin UI to list available image filenames
 * in the uploads directory. Requires admin auth and returns a simple
 * JSON array { files: [...] }.
 */

session_start();
require_once 'config.php';
checkAuth();

header('Content-Type: application/json');

$dir = UPLOAD_DIR;
$files = [];
if (is_dir($dir)) {
    $all = scandir($dir);
    foreach ($all as $f) {
        if ($f === '.' || $f === '..') continue;
        if (is_file($dir . $f)) $files[] = $f;
    }
}
echo json_encode(['files' => $files]);
