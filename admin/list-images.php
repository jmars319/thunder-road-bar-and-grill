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
// only include common image extensions
$allowed = ['jpg','jpeg','png','gif','webp','svg','ico'];
if (is_dir($dir)) {
    $all = scandir($dir);
    foreach ($all as $f) {
        // skip dotfiles and directories
        if ($f === '.' || $f === '..') continue;
        if ($f[0] === '.') continue;
        $full = $dir . $f;
        if (!is_file($full)) continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) continue;
        $files[] = $f;
    }
}
echo json_encode(['files' => $files]);
