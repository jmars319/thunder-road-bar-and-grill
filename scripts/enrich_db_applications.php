<?php
// Enrich job_applications rows with extra fields from data/applications.json when available
require_once __DIR__ . '/../bootstrap.php';

function load_json($path) {
    if (!file_exists($path)) return [];
    $c = @file_get_contents($path);
    $a = $c ? json_decode($c, true) : [];
    return is_array($a) ? $a : [];
}

$dataFile = __DIR__ . '/../data/applications.json';
$legacy = load_json($dataFile);
if (empty($legacy)) { echo "No legacy JSON found to enrich from.\n"; exit(0); }

$pdo = db();
$updated = 0;
foreach ($legacy as $e) {
    $ts = $e['timestamp'] ?? null;
    $email = $e['email'] ?? null;
    $position = $e['position_desired'] ?? null;
    if (!$ts || !$email) continue;
    // find matching row
    $stmt = $pdo->prepare('SELECT id FROM job_applications WHERE email = ? AND position_desired = ? AND created_at = ? LIMIT 1');
    $stmt->execute([$email, $position, $ts]);
    $id = $stmt->fetchColumn();
    if (!$id) continue;
    $upd = $pdo->prepare('UPDATE job_applications SET desired_salary = ?, start_date = ?, shift_preference = ?, hours_per_week = ?, restaurant_experience = ?, other_experience = ?, references_text = ?, raw_message = ? WHERE id = ?');
    $upd->execute([
        $e['desired_salary'] ?? null,
        $e['start_date'] ?? null,
        $e['shift_preference'] ?? null,
        $e['hours_per_week'] ?? null,
        $e['restaurant_experience'] ?? null,
        $e['other_experience'] ?? null,
        $e['references'] ?? null,
        $e['raw_message'] ?? null,
        $id
    ]);
    $updated++;
}
echo "Enrichment complete. Updated: {$updated}\n";
