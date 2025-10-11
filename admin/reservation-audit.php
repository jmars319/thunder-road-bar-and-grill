<?php
/**
 * admin/reservation-audit.php
 * Viewer/exporter for the reservation audit file.
 */
require_once __DIR__ . '/config.php';
require_admin();
ensure_csrf_token();

$auditFile = __DIR__ . '/../data/reservation-audit.json';
$entries = [];
if (file_exists($auditFile)) {
    $j = @file_get_contents($auditFile);
    $entries = $j ? json_decode($j, true) : [];
    if (!is_array($entries)) $entries = [];
}

// downloads
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reservation-audit.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['timestamp','name','phone','date','time','guests','ip']);
    foreach ($entries as $r) {
        fputcsv($out, [ $r['timestamp'] ?? '', $r['name'] ?? '', $r['phone'] ?? '', $r['date'] ?? '', $r['time'] ?? '', $r['guests'] ?? '', $r['ip'] ?? '' ]);
    }
    fclose($out);
    exit;
}

if (isset($_GET['download']) && $_GET['download'] === 'json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="reservation-audit.json"');
    echo json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Clear audit (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_reservation_audit') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) { header('HTTP/1.1 400 Bad Request'); echo 'Invalid CSRF token'; exit; }
  $json = json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  if ($json !== false) { file_put_contents($auditFile . '.tmp', $json, LOCK_EX); @rename($auditFile . '.tmp', $auditFile); }
    header('Location: reservation-audit.php'); exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Admin - Reservation Audit</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
      body{font-family:Arial,Helvetica,sans-serif;padding:20px}
      table{width:100%;border-collapse:collapse}
  th,td{padding:6px;border:1px solid var(--border-color);text-align:left;font-size:0.9rem}
  th{background:var(--divider-color)}
  .small{font-size:0.85rem;color:var(--text-secondary)}
      .top-actions{margin-bottom:1rem;display:flex;gap:.5rem;align-items:center}
      .mono{font-family:monospace;font-size:0.85rem}
    </style>
  </head>
  <body>
  <a href="index.php" class="btn btn-ghost">◀ Back to Dashboard</a>
    <h1>Reservation Audit</h1>
    <p class="small">Append-only audit entries written to <code>data/reservation-audit.json</code>. Newest first.</p>

    <div class="top-actions">
      <div style="margin-left:auto">
        <a class="btn btn-ghost" href="reservation-audit.php?download=csv">Download CSV</a>
        <a class="btn btn-ghost" href="reservation-audit.php?download=json">Download JSON</a>
      </div>
    </div>

    <?php if (empty($entries)): ?>
      <p>No audit entries.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr><th>Time</th><th>Name</th><th>Phone</th><th>Date</th><th>Time</th><th>Guests</th><th>IP</th></tr>
        </thead>
        <tbody>
        <?php foreach (array_reverse($entries) as $r): ?>
          <tr>
            <td class="mono"><?php echo htmlspecialchars($r['timestamp'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['name'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['phone'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['date'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['time'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['guests'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['ip'] ?? ''); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <form method="post" style="margin-top:1rem" data-confirm="Clear reservation audit? This will remove all entries. Continue?">
      <?php echo csrf_input_field(); ?>
      <input type="hidden" name="action" value="clear_reservation_audit">
      <button type="submit" class="btn btn-ghost">Clear audit</button>
    </form>
  </body>
</html>
