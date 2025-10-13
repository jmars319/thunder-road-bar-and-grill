#!/usr/bin/env php
<?php
// scripts/print_job_applications.php
// Usage: php scripts/print_job_applications.php [--json] [--limit=N]

$opts = [];
foreach ($argv as $arg) {
    if (preg_match('#^--json$#', $arg)) $opts['json'] = true;
    if (preg_match('#^--limit=(\d+)$#', $arg, $m)) $opts['limit'] = (int)$m[1];
}

// Attempt to load project bootstrap to get db()
$boot = __DIR__ . '/../bootstrap.php';
if (!file_exists($boot)) {
    fwrite(STDERR, "Error: bootstrap.php not found at $boot\n");
    exit(2);
}
require_once $boot;

try {
    $pdo = db();
} catch (Exception $e) {
    fwrite(STDERR, "DB connection error: " . $e->getMessage() . "\n");
    exit(2);
}

$limit = isset($opts['limit']) ? (int)$opts['limit'] : 25;
$limit = $limit > 0 && $limit <= 1000 ? $limit : 25;

$stmt = $pdo->prepare('SELECT id, created_at, first_name, last_name, email, phone, position_desired, employment_type, status, resume_storage_name, resume_original_name, ip_address, why_work_here FROM job_applications ORDER BY id DESC LIMIT ?');
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($opts['json'])) {
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

// Pretty table output
function col($s, $width) {
    $s = (string)$s;
    if (mb_strlen($s) > $width) return mb_substr($s, 0, $width - 1) . '…';
    return str_pad($s, $width);
}

$cols = [
    'ID' => 4,
    'Created' => 19,
    'Name' => 22,
    'Email' => 26,
    'Phone' => 14,
    'Position' => 16,
    'Status' => 10,
    'Resume' => 8,
    'IP' => 16,
];

// header
$hdr = '';
foreach ($cols as $k => $w) $hdr .= col($k, $w) . ' ';
echo $hdr . PHP_EOL;
echo str_repeat('-', mb_strlen($hdr)) . PHP_EOL;

foreach ($rows as $r) {
    $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
    $resume = !empty($r['resume_storage_name']) ? 'yes' : 'no';
    $line = '';
    $line .= col($r['id'] ?? '', $cols['ID']) . ' ';
    $line .= col($r['created_at'] ?? '', $cols['Created']) . ' ';
    $line .= col($name, $cols['Name']) . ' ';
    $line .= col($r['email'] ?? '', $cols['Email']) . ' ';
    $line .= col($r['phone'] ?? '', $cols['Phone']) . ' ';
    $line .= col($r['position_desired'] ?? '', $cols['Position']) . ' ';
    $line .= col($r['status'] ?? '', $cols['Status']) . ' ';
    $line .= col($resume, $cols['Resume']) . ' ';
    $line .= col($r['ip_address'] ?? $r['ip'] ?? '', $cols['IP']) . ' ';
    echo $line . PHP_EOL;
    // print why_work_here underneath truncated
    if (!empty($r['why_work_here'])) {
        echo '    Why: ' . preg_replace('/\s+/', ' ', trim($r['why_work_here'])) . PHP_EOL;
    }
}

echo PHP_EOL . "Displayed: " . count($rows) . " rows (limit={$limit})\n";

exit(0);
