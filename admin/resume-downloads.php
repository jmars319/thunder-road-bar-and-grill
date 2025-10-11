<?php
require_once __DIR__ . '/config.php';
require_admin();
ensure_csrf_token();

$logFile = __DIR__ . '/../logs/resume_downloads.log';
$lines = [];
if (file_exists($logFile)) {
    $raw = @file_get_contents($logFile);
    $l = explode("\n", trim($raw));
    foreach ($l as $row) {
        if (!trim($row)) continue;
        $j = json_decode($row, true);
        if (is_array($j)) $lines[] = $j;
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Resume Download Audit</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>table{width:100%;border-collapse:collapse}th,td{padding:.5rem;border:1px solid #ddd}</style>
  </head>
  <body class="admin">
    <div class="page-wrap">
      <div class="admin-card">
        <h1>Resume Download Audit</h1>
        <p class="small">Recent resume download events. This log is append-only.</p>
        <table>
          <thead><tr><th>Time</th><th>Admin</th><th>IP</th><th>File</th><th>Original</th><th>Application ID</th></tr></thead>
          <tbody>
          <?php foreach ($lines as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['timestamp'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($r['admin'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($r['ip'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($r['file'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($r['original'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($r['application_id'] ?? ''); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div style="margin-top:1rem"><a href="index.php" class="btn btn-ghost">Back to Dashboard</a></div>
      </div>
    </div>
  </body>
</html>
