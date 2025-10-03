<?php
/**
 * admin/list-trash.php
 * Returns JSON list of trashed uploads. Each entry includes the
 * trash filename and optional metadata stored alongside the trashed
 * file. Admin auth required.
 */

session_start();
require_once 'config.php';
checkAuth();

header('Content-Type: application/json');

$trashDir = dirname(UPLOAD_DIR) . '/uploads/trash/';
$items = [];
if (is_dir($trashDir)) {
    $all = scandir($trashDir);
    foreach ($all as $f) {
        if ($f === '.' || $f === '..') continue;
        if (preg_match('/\.json$/', $f)) continue; // skip metadata files
        $metaFile = $trashDir . $f . '.json';
        $meta = null;
        if (file_exists($metaFile)) {
            $j = @file_get_contents($metaFile);
            $meta = $j ? json_decode($j, true) : null;
        }
        $items[] = [ 'trash_name' => $f, 'meta' => $meta ];
    }
}

echo json_encode(['items' => $items]);
