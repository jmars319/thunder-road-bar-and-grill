<?php
/**
 * admin/restore-image.php
 * Restores a file from `uploads/trash/` back into `uploads/images/`.
 * The endpoint attempts to avoid filename collisions and will append
 * "-restored-N" if necessary. Admin auth and CSRF are required.
 *
 * Outputs JSON { success, message, filename } and updates
 * `data/content.json` where empty references are found.
 */

session_start();
require_once 'config.php';
checkAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
}

$csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verify_csrf_token($csrf)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF']); exit; }

$trashName = basename($_POST['trash_name'] ?? '');
if ($trashName === '') { http_response_code(400); echo json_encode(['success'=>false,'message'=>'No file specified']); exit; }

$trashDir = dirname(UPLOAD_DIR) . '/uploads/trash/';
$src = $trashDir . $trashName;
$metaFile = $src . '.json';
$originalName = $trashName;
if ($metaFile && file_exists($metaFile)) {
    $m = json_decode(file_get_contents($metaFile), true);
    if (is_array($m) && !empty($m['original'])) $originalName = basename($m['original']);
}
$dest = UPLOAD_DIR . $originalName;

if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);
if (file_exists($src)) {
    // avoid overwriting existing files: if $dest exists, append a numeric suffix
    $base = pathinfo($originalName, PATHINFO_FILENAME);
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    $attempt = 0;
    $candidate = $dest;
    while (file_exists($candidate)) {
        $attempt++;
        $newName = $base . "-restored-" . $attempt . ($ext ? "." . $ext : '');
        $candidate = UPLOAD_DIR . $newName;
        // safety cap to avoid infinite loop
        if ($attempt > 100) break;
    }
    if ($candidate !== $dest) $dest = $candidate;

    if (!@rename($src, $dest)) {
        http_response_code(500); echo json_encode(['success'=>false,'message'=>'Restore failed']); exit;
    }
    // remove metadata file
    if (file_exists($metaFile)) @unlink($metaFile);
    // update content.json: attempt to replace empty values with restored filename where appropriate
    $contentFile = CONTENT_FILE;
    if (file_exists($contentFile)) {
        $content = json_decode(file_get_contents($contentFile), true);
        if (is_array($content)) {
            $changed = false;
            array_walk_recursive($content, function(&$v, $k) use (&$changed, $dest, $trashName) {
                if ($v === '') { $v = basename($dest); $changed = true; }
            });
            if ($changed) {
                $json = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                if ($json !== false) { file_put_contents($contentFile . '.tmp', $json, LOCK_EX); @rename($contentFile . '.tmp', $contentFile); }
            }
        }
    }

    echo json_encode(['success'=>true,'message'=>'Restored','filename'=>basename($dest)]);
    exit;
}

http_response_code(404); echo json_encode(['success'=>false,'message'=>'Not found']);
