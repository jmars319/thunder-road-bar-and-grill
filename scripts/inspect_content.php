<?php
// scripts/inspect_content.php
// Simple validator that checks data/content.json for menu normalization

chdir(__DIR__ . '/..');
$file = __DIR__ . '/../data/content.json';
if (!file_exists($file)) { echo "content.json not found\n"; exit(2); }
$c = json_decode(file_get_contents($file), true);
if (!is_array($c)) { echo "content.json invalid JSON\n"; exit(2); }
$menu = $c['menu'] ?? null;
if (!is_array($menu)) { echo "No menu section present (OK if intentional)\n"; exit(0); }
$errs = [];
foreach ($menu as $si => $sdata) {
    $secId = $sdata['id'] ?? '';
    if (!isset($sdata['items']) || !is_array($sdata['items'])) continue;
    foreach ($sdata['items'] as $ii => $item) {
        if (!isset($item['quantities']) && !isset($item['quantity'])) {
            $errs[] = "Section {$secId} item #{$ii} missing quantities/quantity";
        }
        if (isset($item['price'])) {
            if (!preg_match('/^[0-9]+\.[0-9]{2}$/', (string)$item['price'])) {
                $errs[] = "Section {$secId} item #{$ii} price not normalized: ".(string)$item['price'];
            }
        }
        if (isset($item['quantities']) && is_array($item['quantities'])) {
            foreach ($item['quantities'] as $qi => $qop) {
                if (!isset($qop['value'])) $errs[] = "Section {$secId} item #{$ii} qty #{$qi} missing value";
                if (!isset($qop['price']) || $qop['price'] === '') $errs[] = "Section {$secId} item #{$ii} qty #{$qi} missing price";
                if (isset($qop['price']) && !preg_match('/^[0-9]+\.[0-9]{2}$/', (string)$qop['price'])) $errs[] = "Section {$secId} item #{$ii} qty #{$qi} price not normalized: ".(string)$qop['price'];
            }
        }
    }
}
if (count($errs)) {
    echo "Validation found issues:\n" . implode("\n", $errs) . "\n";
    exit(1);
}
echo "Content validation passed. All menu items normalized.\n";
exit(0);
