<?php
session_start();
require_once 'config.php';
require_admin();

// Simple endpoint to append a blank item to a named menu section.
// This is a fallback when the JS admin UI cannot create new fields.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo 'Method not allowed';
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verify_csrf($csrf)) {
    header('Location: index.php?error=csrf');
    exit;
}

$sectionId = trim((string)($_POST['section_id'] ?? ''));
if ($sectionId === '') {
    header('Location: index.php?error=missing_section');
    exit;
}

$contentFile = CONTENT_FILE;
$content = [];
if (file_exists($contentFile)) {
    $c = @file_get_contents($contentFile);
    $content = $c ? json_decode($c, true) : [];
}
if (!is_array($content)) $content = [];

// Ensure menu exists and is an array of sections
if (!isset($content['menu']) || !is_array($content['menu'])) {
    $content['menu'] = [];
}

$found = false;
for ($i = 0; $i < count($content['menu']); $i++) {
    if (isset($content['menu'][$i]['id']) && $content['menu'][$i]['id'] === $sectionId) {
        if (!isset($content['menu'][$i]['items']) || !is_array($content['menu'][$i]['items'])) $content['menu'][$i]['items'] = [];
        $content['menu'][$i]['items'][] = [
            'title' => '', 'short' => '', 'description' => '', 'image' => '', 'price' => '', 'quantities' => []
        ];
        $found = true;
        break;
    }
}

if (!$found) {
    // If section not found, create a new one with this id and append blank item
    $content['menu'][] = [ 'title' => $sectionId, 'id' => $sectionId, 'items' => [ [ 'title'=>'', 'short'=>'', 'description'=>'', 'image'=>'', 'price'=>'', 'quantities'=>[] ] ] ];
}

$content['last_updated'] = date('Y-m-d H:i:s');
$json = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    header('Location: index.php?error=json_encode'); exit;
}
$tmp = $contentFile . '.tmp';
if (file_put_contents($tmp, $json, LOCK_EX) !== false && @rename($tmp, $contentFile)) {
    @chmod($contentFile, 0640);
    header('Location: index.php?msg=added_item');
    exit;
} else {
    @unlink($tmp);
    header('Location: index.php?error=save_failed');
    exit;
}

?>
