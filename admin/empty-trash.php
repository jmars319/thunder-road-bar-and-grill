<?php
/**
 * admin/empty-trash.php
 * Permanently deletes all files in the uploads trash directory.
 * Requires admin auth and POST with a valid CSRF token.
 *
 * Warning: this is irreversible. The endpoint counts deletions and
 * redirects back to the admin panel with a summary.
 */

session_start();
require_once 'config.php';
checkAuth();

// Accept POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); exit;
}

$csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verify_csrf_token($csrf)) {
    header('Location: index.php?error=csrf'); exit;
}

$trashDir = dirname(UPLOAD_DIR) . '/uploads/trash/';
if (!is_dir($trashDir)) {
    header('Location: index.php?msg=notrash'); exit;
}

// remove files and count deletions
$files = glob($trashDir . '*');
$deleted = 0;
foreach ($files as $f) {
    // skip directories
    if (is_dir($f)) continue;
    if (@unlink($f)) $deleted++;
}

// Redirect with count so the admin can show a toast with how many files were removed
header('Location: index.php?msg=trash_emptied&count=' . (int)$deleted);
exit;
?>
