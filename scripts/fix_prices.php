<?php
// scripts/fix_prices.php
// Normalize prices in data/content.json to fixed 2-decimal strings (e.g. 0.50, 12.00)

chdir(__DIR__ . '/..');
$file = __DIR__ . '/../data/content.json';
if (!file_exists($file)) { echo "content.json not found\n"; exit(2); }

$backup = $file . '.prepricefix.' . date('Ymd-His') . '.bak';
if (!copy($file, $backup)) { echo "Failed to create backup $backup\n"; exit(1); }
echo "Backup created: $backup\n";

$content = json_decode(file_get_contents($file), true);
if (!is_array($content)) { echo "Invalid JSON\n"; exit(2); }

$changed = false;
$menu = $content['menu'] ?? null;
if (!is_array($menu)) { echo "No menu present; nothing to do.\n"; exit(0); }

foreach ($menu as $si => $sdata) {
    if (!isset($sdata['items']) || !is_array($sdata['items'])) continue;
    foreach ($sdata['items'] as $ii => $item) {
        // normalize item price
        if (isset($item['price'])) {
            $raw = (string)$item['price'];
            // strip whitespace
            $raw = trim($raw);
            // if empty skip
            if ($raw !== '') {
                // remove currency symbols and spaces
                $clean = preg_replace('/[^0-9\.\-]/', '', $raw);
                if ($clean !== '' && is_numeric($clean)) {
                    $num = (float)$clean;
                    $fmt = number_format($num, 2, '.', '');
                    if ($fmt !== $item['price']) { $menu[$si]['items'][$ii]['price'] = $fmt; $changed = true; }
                }
            }
        }
        // normalize quantities prices
        if (isset($item['quantities']) && is_array($item['quantities'])) {
            foreach ($item['quantities'] as $qi => $qop) {
                if (isset($qop['price'])) {
                    $raw = (string)$qop['price']; $raw = trim($raw);
                    if ($raw !== '') {
                        $clean = preg_replace('/[^0-9\.\-]/', '', $raw);
                        if ($clean !== '' && is_numeric($clean)) {
                            $num = (float)$clean; $fmt = number_format($num, 2, '.', '');
                            if ($fmt !== $menu[$si]['items'][$ii]['quantities'][$qi]['price']) { $menu[$si]['items'][$ii]['quantities'][$qi]['price'] = $fmt; $changed = true; }
                        }
                    }
                }
            }
        }
    }
}

if ($changed) {
    $content['menu'] = $menu;
    $content['last_updated'] = date('Y-m-d H:i:s');
    $tmp = $file . '.tmp';
    $json = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json && file_put_contents($tmp, $json, LOCK_EX) !== false && @rename($tmp, $file)) {
        @chmod($file, 0640);
        echo "Prices normalized and content.json updated. Backup: $backup\n";
        exit(0);
    } else {
        @unlink($tmp);
        echo "Failed to write updated content.json\n";
        exit(1);
    }
} else {
    echo "No price changes necessary.\n";
    // remove backup since no changes
    @unlink($backup);
    exit(0);
}
