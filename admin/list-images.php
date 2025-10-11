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

// Optional: support a `type` query parameter (e.g. ?type=gallery) so the
// admin UI can request a filtered subset server-side. We apply simple
// heuristics consistent with the client-side classifier.
$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
if ($type) {
    $filtered = [];
    foreach ($files as $f) {
        $lower = strtolower($f);
        $parts = explode('-', $f);
        $t = 'general';
        if (count($parts) && in_array(strtolower($parts[0]), ['logo','hero','gallery','general'], true)) $t = strtolower($parts[0]);
        else if (strpos($lower, 'logo') !== false || strpos($lower, 'trbg') !== false) $t = 'logo';
        else if (strpos($lower, 'hero') !== false) $t = 'hero';
        else if (strpos($lower, 'gallery') !== false || strpos($lower, 'img') !== false || strpos($lower, 'photo') !== false) $t = 'gallery';

        if ($type === 'gallery') {
            if ($t === 'gallery' || $t === 'general') $filtered[] = $f;
        } else if ($type === 'logo') {
            if ($t === 'logo') $filtered[] = $f;
        } else if ($type === 'hero') {
            if ($t === 'hero') $filtered[] = $f;
        } else {
            $filtered[] = $f;
        }
    }
    $files = $filtered;
}

echo json_encode(['files' => $files]);
