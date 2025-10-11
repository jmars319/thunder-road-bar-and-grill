<?php
/**
 * admin/upload-audit.php
 * Simple viewer and exporter for `data/upload-audit.log` which is an
 * append-only JSON-lines file created by the image upload flow. This
 * page is read-only but requires admin auth to access audit data.
 *
 * Outputs HTML (or CSV/JSON when download query param is present).
 */

require_once __DIR__ . '/config.php';
require_admin();
ensure_csrf_token();

$logFile = __DIR__ . '/../data/upload-audit.log';
$entries = [];
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines) {
        foreach ($lines as $ln) {
            $j = json_decode($ln, true);
            if (is_array($j)) $entries[] = $j;
        }
    }
}

// newest first
$entries = array_reverse($entries);

$search = trim((string)($_GET['q'] ?? ''));
$filtered = [];
if ($search === '') {
    $filtered = $entries;
} else {
    $s = mb_strtolower($search);
    foreach ($entries as $e) {
        $hay = '';
        $hay .= ($e['admin'] ?? '') . ' ' . ($e['original_name'] ?? '') . ' ' . ($e['stored_name'] ?? '') . ' ' . ($e['type'] ?? '') . ' ' . ($e['mime'] ?? '') . ' ' . ($e['ip'] ?? '') . ' ' . ($e['user_agent'] ?? '');
        if (mb_strpos(mb_strtolower($hay), $s) !== false) $filtered[] = $e;
    }
}

$per_page = isset($_GET['per_page']) ? max(10, min(500, (int)$_GET['per_page'])) : 50;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$total = count($filtered);
$total_pages = $total ? (int)ceil($total / $per_page) : 1;
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;
$paged = array_slice($filtered, $offset, $per_page);

// downloads
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="upload-audit.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['timestamp','admin','original_name','stored_name','type','mime','size','ip','user_agent']);
    foreach ($filtered as $e) {
        fputcsv($out, [ $e['timestamp'] ?? '', $e['admin'] ?? '', $e['original_name'] ?? '', $e['stored_name'] ?? '', $e['type'] ?? '', $e['mime'] ?? '', $e['size'] ?? '', $e['ip'] ?? '', $e['user_agent'] ?? '' ]);
    }
    fclose($out);
    exit;
}
if (isset($_GET['download']) && $_GET['download'] === 'json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="upload-audit.json"');
    echo json_encode($filtered, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Admin - Upload Audit</title>
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
    <h1>Upload Audit Log</h1>
    <p class="small">This shows append-only entries written to <code>data/upload-audit.log</code>. Newest first.</p>

    <div class="top-actions">
      <form method="get" style="display:flex;gap:.5rem;align-items:center">
        <input type="text" name="q" placeholder="Search admin, filename, type, ip..." value="<?php echo htmlspecialchars($search); ?>">
        <label class="small">Per page:
          <select name="per_page">
            <option value="20" <?php if ($per_page==20) echo 'selected'; ?>>20</option>
            <option value="50" <?php if ($per_page==50) echo 'selected'; ?>>50</option>
            <option value="100" <?php if ($per_page==100) echo 'selected'; ?>>100</option>
          </select>
        </label>
        <button type="submit" class="btn">Filter</button>
      </form>

      <div style="margin-left:auto">
        <a class="btn btn-ghost" href="upload-audit.php?download=csv">Download CSV</a>
        <a class="btn btn-ghost" href="upload-audit.php?download=json">Download JSON</a>
      </div>
    </div>

    <?php if (empty($filtered)): ?>
      <p>No entries found.</p>
    <?php else: ?>
  <p class="small">Showing <?php echo (int)count($paged); ?> of <?php echo (int)$total; ?> entries (page <?php echo (int)$page; ?> of <?php echo (int)$total_pages; ?>)</p>
      <table>
        <thead>
          <tr>
            <th>Time</th>
            <th>Admin</th>
            <th>Original</th>
            <th>Stored</th>
            <th>Type</th>
            <th>MIME</th>
            <th>Size</th>
            <th>IP</th>
            <th>User Agent</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($paged as $row): ?>
          <tr>
            <td class="mono"><?php echo htmlspecialchars($row['timestamp'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($row['admin'] ?? ''); ?></td>
            <td title="<?php echo htmlspecialchars($row['original_name'] ?? ''); ?>"><?php echo htmlspecialchars(mb_strimwidth($row['original_name'] ?? '', 0, 40, '...')); ?></td>
            <td title="<?php echo htmlspecialchars($row['stored_name'] ?? ''); ?>"><?php echo htmlspecialchars(mb_strimwidth($row['stored_name'] ?? '', 0, 36, '...')); ?></td>
            <td><?php echo htmlspecialchars($row['type'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($row['mime'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars(isset($row['size']) ? $row['size'] : ''); ?></td>
            <td><?php echo htmlspecialchars($row['ip'] ?? ''); ?></td>
            <td title="<?php echo htmlspecialchars($row['user_agent'] ?? ''); ?>"><?php echo htmlspecialchars(mb_strimwidth($row['user_agent'] ?? '', 0, 60, '...')); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($total_pages > 1): ?>
        <div style="margin-top:.75rem">
          <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>">&laquo; Prev</a>
          <?php endif; ?>
          <?php for ($p=1;$p<=$total_pages;$p++): ?>
            <?php if ($p==$page): ?><strong><?php echo (int)$p; ?></strong><?php else: ?><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => (int)$p])); ?>"><?php echo (int)$p; ?></a><?php endif; ?>
          <?php endfor; ?>
          <?php if ($page < $total_pages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>">Next &raquo;</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  </body>
</html>
