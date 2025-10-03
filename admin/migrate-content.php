<?php
// admin/migrate-content.php
// One-time migration helper to normalize menu items in data/content.json
// - Ensures items have either `quantities` array or `quantity` integer
// - Normalizes per-item price to 2-decimal string
// - Converts legacy `quantity` into a single-entry `quantities` array when appropriate

session_start();
require_once 'config.php';
checkAuth();

$contentFile = realpath(__DIR__ . '/../data/content.json');
if (!$contentFile || !file_exists($contentFile)) {
    echo "No content.json found\n";
    exit;
}

$content = json_decode(file_get_contents($contentFile), true);
if (!is_array($content)) {
    echo "Invalid content.json\n";
    exit;
}

$menu = $content['menu'] ?? null;
if (!is_array($menu)) {
    echo "No menu present in content.json\n";
    exit;
}

$changed = false;
foreach ($menu as $si => $sdata) {
    $secId = isset($sdata['id']) ? $sdata['id'] : '';
    if (!isset($sdata['items']) || !is_array($sdata['items'])) continue;
    foreach ($sdata['items'] as $ii => $item) {
        // normalize price
        if (isset($item['price'])) {
            $clean = preg_replace('/[^0-9\.\-]/', '', (string)$item['price']);
            if ($clean === '' || !is_numeric($clean)) {
                // leave as-is but note
            } else {
                $num = (float)$clean; $menu[$si]['items'][$ii]['price'] = number_format($num, 2, '.', ''); $changed = true;
            }
        }
        // convert legacy quantity into quantities array
        if (!isset($item['quantities']) && isset($item['quantity'])) {
            $qclean = preg_replace('/[^0-9\-]/', '', (string)$item['quantity']);
            if ($qclean !== '' && is_numeric($qclean)) {
                $qint = intval($qclean);
                $menu[$si]['items'][$ii]['quantities'] = [ ['label'=>'', 'value'=>$qint, 'price'=> isset($item['price']) ? $menu[$si]['items'][$ii]['price'] ?? $item['price'] : '' ] ];
                unset($menu[$si]['items'][$ii]['quantity']);
                $changed = true;
            }
        }
        // migrate legacy `short` into `description` when description is empty
        if (( !isset($menu[$si]['items'][$ii]['description']) || trim((string)$menu[$si]['items'][$ii]['description']) === '' ) && isset($item['short']) && trim((string)$item['short']) !== '') {
            $menu[$si]['items'][$ii]['description'] = trim((string)$item['short']);
            // keep original short for now if desired, but mark changed so file is rewritten
            $changed = true;
        }
        // ensure there's either quantities or quantity
        if (!isset($menu[$si]['items'][$ii]['quantities']) && !isset($menu[$si]['items'][$ii]['quantity'])) {
            $menu[$si]['items'][$ii]['quantity'] = ($secId === 'wings-tenders') ? 1 : 0; $changed = true;
        }
    }
}

if ($changed) {
    $content['menu'] = $menu;
    $content['last_updated'] = date('Y-m-d H:i:s');
    $tmp = $contentFile . '.tmp';
    $json = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json && file_put_contents($tmp, $json, LOCK_EX) !== false && @rename($tmp, $contentFile)) {
        @chmod($contentFile, 0640);
        echo "Migration applied and content.json updated.\n";
    } else {
        @unlink($tmp);
        echo "Failed to write updated content.json (check permissions).\n";
    }
} else {
    echo "No changes necessary; content.json already normalized.\n";
}

?>