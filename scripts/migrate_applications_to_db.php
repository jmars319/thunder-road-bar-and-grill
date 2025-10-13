<?php
// Migrate entries from data/applications.json into job_applications table (idempotent)
require_once __DIR__ . '/../bootstrap.php';

function load_json($path) {
    if (!file_exists($path)) return [];
    $c = @file_get_contents($path);
    $a = $c ? json_decode($c, true) : [];
    return is_array($a) ? $a : [];
}

$dataFile = __DIR__ . '/../data/applications.json';
$entries = load_json($dataFile);
if (empty($entries)) {
    echo "No legacy entries found in {$dataFile}\n";
    exit(0);
}

$pdo = db();
$inserted = 0;
foreach ($entries as $e) {
    // dedupe by email+timestamp+position (best-effort)
    $ts = $e['timestamp'] ?? null;
    $email = $e['email'] ?? null;
    $position = $e['position_desired'] ?? null;
    $checkSql = 'SELECT COUNT(*) FROM job_applications WHERE email = ? AND position_desired = ? AND created_at = ?';
    $chk = $pdo->prepare($checkSql);
    $chk->execute([$email, $position, $ts]);
    if ($chk->fetchColumn() > 0) continue;

    $stmt = $pdo->prepare('INSERT INTO job_applications (first_name,last_name,email,phone,address,age,eligible_to_work,position_desired,employment_type,why_work_here,availability,resume_storage_name,resume_original_name,ip_address,user_agent,sent,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $e['first_name'] ?? null,
        $e['last_name'] ?? null,
        $e['email'] ?? null,
        $e['phone'] ?? null,
        $e['address'] ?? null,
        isset($e['age']) ? (int)$e['age'] : null,
        $e['eligible_to_work'] ?? null,
        $e['position_desired'] ?? null,
        $e['employment_type'] ?? null,
        $e['why_work_here'] ?? null,
        is_array($e['availability']) ? json_encode($e['availability']) : ($e['availability'] ?? null),
        $e['resume_storage_name'] ?? null,
        $e['resume_original_name'] ?? null,
        $e['ip'] ?? null,
        $e['user_agent'] ?? null,
        !empty($e['sent']) ? 1 : 0,
        $e['timestamp'] ?? null,
    ]);
    $inserted++;
}
echo "Migration complete. Inserted: {$inserted}\n";
