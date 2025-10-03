<?php
/**
 * admin/delete-image.php
 * Moves an uploaded image into the repo's trash directory and writes
 * a small metadata file describing the original name and deletion
 * time. Also sweeps references from the content store to avoid broken
 * references.
 *
 * Contract:
 *  - Method: POST
 *  - Inputs: POST { filename, csrf_token } - filename will be basename()'d
 *  - Outputs: JSON { success: bool, message: string, trash?: string }
 *
 * Security and notes:
 *  - Admin auth and CSRF are required. The endpoint does not delete
 *    files permanently; it moves them to `uploads/trash/` with a
 *    timestamped name and writes `<trashname>.json` metadata.
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

$filename = basename($_POST['filename'] ?? '');
if ($filename === '') { http_response_code(400); echo json_encode(['success'=>false,'message'=>'No filename']); exit; }

$src = UPLOAD_DIR . $filename;
$trashDir = dirname(UPLOAD_DIR) . '/uploads/trash/';
if (!is_dir($trashDir)) @mkdir($trashDir, 0755, true);

if (file_exists($src)) {
    $uniq = time() . '-' . bin2hex(random_bytes(4));
    $destName = $uniq . '-' . $filename;
    $dest = $trashDir . $destName;
    if (@rename($src, $dest)) {
        // write metadata
        $meta = [
            'original' => $filename,
            'trash_name' => $destName,
            'deleted_at' => (function_exists('eastern_now') ? eastern_now('c') : date('c')),
            'deleted_by' => $_SESSION['admin_logged_in'] ? ($_SESSION['admin_username'] ?? 'admin') : 'admin'
        ];
    $json = json_encode($meta, JSON_PRETTY_PRINT);
    if ($json !== false) { file_put_contents($dest . '.json.tmp', $json, LOCK_EX); @rename($dest . '.json.tmp', $dest . '.json'); }
    }
}

// remove references from content.json (simple sweep)
$contentFile = CONTENT_FILE;
if (file_exists($contentFile)) {
    $content = json_decode(file_get_contents($contentFile), true);
    if (is_array($content)) {
        $changed = false;
        array_walk_recursive($content, function(&$v, $k) use (&$changed, $filename) {
            if ($v === $filename) { $v = ''; $changed = true; }
        });
        if ($changed) {
            $content['last_updated'] = (function_exists('eastern_now') ? eastern_now('Y-m-d H:i:s') : date('Y-m-d H:i:s'));
            $json = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json !== false) { file_put_contents($contentFile . '.tmp', $json, LOCK_EX); @rename($contentFile . '.tmp', $contentFile); }
        }
    }
}

echo json_encode(['success'=>true,'message'=>'Moved to trash','trash'=>($destName ?? null)]);
