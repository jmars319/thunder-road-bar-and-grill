<?php
// admin/preview-diff.php
// Accepts POST JSON { menu: [...] } and returns a simple diff between the
// provided menu and the current stored menu in data/content.json.

session_start();
require_once 'config.php';
checkAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = $raw ? json_decode($raw, true) : null;
if (!is_array($payload) || !isset($payload['menu']) || !is_array($payload['menu'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payload; expected { menu: [...] }']);
    exit;
}

$incoming = $payload['menu'];

$contentFile = realpath(__DIR__ . '/../data/content.json');
$stored = [];
if ($contentFile && file_exists($contentFile)) {
    $stored = json_decode(file_get_contents($contentFile), true);
    if (!is_array($stored)) $stored = [];
}
$storedMenu = $stored['menu'] ?? [];

// normalize helper: map sections by id (fallback to title)
function section_key($s) {
    if (!is_array($s)) return null;
    return isset($s['id']) && $s['id'] !== '' ? (string)$s['id'] : (isset($s['title']) ? (string)$s['title'] : null);
}

function items_map_by_title($items) {
    $map = [];
    if (!is_array($items)) return $map;
    foreach ($items as $it) {
        $k = isset($it['title']) ? trim((string)$it['title']) : '';
        if ($k === '') continue;
        $map[$k] = $it;
    }
    return $map;
}

$diff = [];

// create maps for quick lookup
$storedSections = [];
foreach ($storedMenu as $s) {
    $k = section_key($s);
    if ($k === null) continue;
    $storedSections[$k] = $s;
}

foreach ($incoming as $s) {
    $k = section_key($s);
    if ($k === null) continue;
    $inItems = is_array($s['items']) ? $s['items'] : [];

    $storedSec = $storedSections[$k] ?? ['items' => []];
    $storedItems = is_array($storedSec['items']) ? $storedSec['items'] : [];

    $inMap = items_map_by_title($inItems);
    $stMap = items_map_by_title($storedItems);

    $added = [];
    $removed = [];
    $changed = [];

    // added: in incoming but not in stored
    foreach ($inMap as $title => $it) {
        if (!isset($stMap[$title])) {
            $added[] = $title;
        } else {
            // simple change detection: compare price, description, quantities
            $sIt = $stMap[$title];
            $changes = [];
            $inPrice = isset($it['price']) ? (string)$it['price'] : '';
            $stPrice = isset($sIt['price']) ? (string)$sIt['price'] : '';
            if ($inPrice !== $stPrice) $changes[] = 'price';
            $inDesc = isset($it['description']) ? (string)$it['description'] : '';
            $stDesc = isset($sIt['description']) ? (string)$sIt['description'] : '';
            if ($inDesc !== $stDesc) $changes[] = 'description';
            // quantities compare: normalized count or values
            $inQty = isset($it['quantities']) && is_array($it['quantities']) ? json_encode($it['quantities']) : (isset($it['quantity']) ? json_encode($it['quantity']) : '');
            $stQty = isset($sIt['quantities']) && is_array($sIt['quantities']) ? json_encode($sIt['quantities']) : (isset($sIt['quantity']) ? json_encode($sIt['quantity']) : '');
            if ($inQty !== $stQty) $changes[] = 'quantities';
            if (count($changes)) $changed[] = ['title' => $title, 'changes' => $changes];
        }
    }

    // removed: in stored but not in incoming
    foreach ($stMap as $title => $it) {
        if (!isset($inMap[$title])) $removed[] = $title;
    }

    $diff[$k] = ['added' => $added, 'removed' => $removed, 'changed' => $changed];
}

echo json_encode(['success' => true, 'diff' => $diff], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

?>