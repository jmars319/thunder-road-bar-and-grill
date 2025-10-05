<?php
/**
 * admin/index.php
 * Main administration UI. This file provides:
 *  - Listing and export of job/contact submissions.
 *  - Management actions (download CSV/JSON, purge logs, manage reservations).
 *
 * Security and assumptions:
 *  - Session-based admin auth is required (handled in `config.php`).
 *  - CSRF tokens are enforced for state-changing POST actions.
 *  - Downloads and archives are read from `data/` and `data/archives`.
 *
 * Developer notes:
 *  - Large exports are streamed; be mindful of memory if the logs
 *    grow significantly. Consider paginating or using gzipped archives
 *    when exporting very large datasets.
 */

require_once __DIR__ . '/config.php';
require_admin();
ensure_csrf_token();

// Compatibility filenames — prefer new name but fall back to legacy during transition
$APPLICATIONS_FILE = __DIR__ . '/../data/applications.json';
$LEGACY_MESSAGES_FILE = __DIR__ . '/../data/messages.json';

// Load entries from either the new applications file or the legacy messages file.
function load_entries($newPath, $legacyPath) {
  $entries = [];
  if (file_exists($newPath)) {
    $c = @file_get_contents($newPath);
    $entries = $c ? json_decode($c, true) : [];
  } elseif (file_exists($legacyPath)) {
    $c = @file_get_contents($legacyPath);
    $entries = $c ? json_decode($c, true) : [];
  }
  if (!is_array($entries)) $entries = [];
  return $entries;
}

// Load archived entries from both new and legacy archive filename patterns.
function load_archived_entries($archiveDir) {
  $entries = [];
  if (!is_dir($archiveDir)) return $entries;
  $patterns = ['/applications-*.json.gz', '/messages-*.json.gz'];
  foreach ($patterns as $p) {
    $files = glob($archiveDir . $p);
    if ($files) {
      foreach ($files as $f) {
        $gz = @file_get_contents($f);
        if ($gz === false) continue;
        $json = @gzdecode($gz);
        if ($json === false) continue;
        $arr = json_decode($json, true);
        if (is_array($arr)) $entries = array_merge($entries, $arr);
      }
    }
  }
  return $entries;
}

// Handle POST actions: logout, download csv/json
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $token = $_POST['csrf_token'] ?? '';
  if (!verify_csrf($token)) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Invalid CSRF token';
    exit;
  }

  if ($action === 'logout') {
    do_logout();
    header('Location: login.php');
    exit;
  }

  if ($action === 'download_csv') {
    $entries = load_entries($APPLICATIONS_FILE, $LEGACY_MESSAGES_FILE);
    header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="applications.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['timestamp','first_name','last_name','email','phone','address','age','eligible_to_work','position_desired','employment_type','desired_salary','start_date','availability','shift_preference','hours_per_week','restaurant_experience','other_experience','why_work_here','references','mail_sent','ip']);
    foreach ($entries as $e) {
      fputcsv($out, [
        $e['timestamp'] ?? '',
        $e['first_name'] ?? '',
        $e['last_name'] ?? '',
        $e['email'] ?? '',
        $e['phone'] ?? '',
        $e['address'] ?? '',
        $e['age'] ?? '',
        $e['eligible_to_work'] ?? '',
        $e['position_desired'] ?? '',
        $e['employment_type'] ?? '',
        $e['desired_salary'] ?? '',
        $e['start_date'] ?? '',
        is_array($e['availability']) ? implode('|', $e['availability']) : $e['availability'] ?? '',
        $e['shift_preference'] ?? '',
        $e['hours_per_week'] ?? '',
        $e['restaurant_experience'] ?? '',
        $e['other_experience'] ?? '',
        $e['why_work_here'] ?? '',
        $e['references'] ?? '',
        !empty($e['mail_sent']) ? '1' : '0',
        $e['ip'] ?? ''
      ]);
    }
    fclose($out);
    exit;
  }

  if ($action === 'download_json') {
    $entries = load_entries($APPLICATIONS_FILE, $LEGACY_MESSAGES_FILE);
    header('Content-Type: application/json');
  header('Content-Disposition: attachment; filename="applications.json"');
    echo json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
  }
  // export all (live + archives)
  if ($action === 'download_all_csv' || $action === 'download_all_json') {
    $entries = load_entries($APPLICATIONS_FILE, $LEGACY_MESSAGES_FILE);
    // include archives (both new and legacy patterns)
    $archiveDir = __DIR__ . '/../data/archives';
    $archived = load_archived_entries($archiveDir);
    if (!empty($archived)) $entries = array_merge($entries, $archived);

    if ($action === 'download_all_json') {
      header('Content-Type: application/json');
      header('Content-Disposition: attachment; filename="all-applications.json"');
      echo json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
      exit;
    }

    // CSV
    header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="all-applications.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['timestamp','first_name','last_name','email','phone','address','age','eligible_to_work','position_desired','employment_type','desired_salary','start_date','availability','shift_preference','hours_per_week','restaurant_experience','other_experience','why_work_here','references','mail_sent','ip']);
    foreach ($entries as $e) {
      fputcsv($out, [
        $e['timestamp'] ?? '',
        $e['first_name'] ?? '',
        $e['last_name'] ?? '',
        $e['email'] ?? '',
        $e['phone'] ?? '',
        $e['address'] ?? '',
        $e['age'] ?? '',
        $e['eligible_to_work'] ?? '',
        $e['position_desired'] ?? '',
        $e['employment_type'] ?? '',
        $e['desired_salary'] ?? '',
        $e['start_date'] ?? '',
        is_array($e['availability']) ? implode('|', $e['availability']) : $e['availability'] ?? '',
        $e['shift_preference'] ?? '',
        $e['hours_per_week'] ?? '',
        $e['restaurant_experience'] ?? '',
        $e['other_experience'] ?? '',
        $e['why_work_here'] ?? '',
        $e['references'] ?? '',
        !empty($e['mail_sent']) ? '1' : '0',
        $e['ip'] ?? ''
      ]);
    }
    fclose($out);
    exit;
  }
  
  // reservations export
  if ($action === 'download_reservations') {
    $resFile = __DIR__ . '/../data/reservations.json';
    $rows = [];
    if (file_exists($resFile)) {
      $j = @file_get_contents($resFile);
      $rows = $j ? json_decode($j, true) : [];
      if (!is_array($rows)) $rows = [];
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reservations.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['timestamp','name','phone','date','time','guests','event','ip']);
    foreach ($rows as $r) {
      fputcsv($out, [ $r['timestamp'] ?? '', $r['name'] ?? '', $r['phone'] ?? '', $r['date'] ?? '', $r['time'] ?? '', $r['guests'] ?? '', $r['event_type'] ?? '', $r['ip'] ?? '' ]);
    }
    fclose($out);
    exit;
  }

  // download reservation audit as CSV
  if ($action === 'download_reservation_audit') {
    $auditFile = __DIR__ . '/../data/reservation-audit.json';
    $rows = [];
    if (file_exists($auditFile)) {
      $j = @file_get_contents($auditFile);
      $rows = $j ? json_decode($j, true) : [];
      if (!is_array($rows)) $rows = [];
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reservation-audit.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['timestamp','name','phone','date','time','guests','ip']);
    foreach ($rows as $r) {
      fputcsv($out, [ $r['timestamp'] ?? '', $r['name'] ?? '', $r['phone'] ?? '', $r['date'] ?? '', $r['time'] ?? '', $r['guests'] ?? '', $r['ip'] ?? '' ]);
    }
    fclose($out);
    exit;
  }

  // clear reservation audit
  if ($action === 'clear_reservation_audit') {
    $auditFile = __DIR__ . '/../data/reservation-audit.json';
  $json = json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  if ($json !== false) { file_put_contents($auditFile . '.tmp', $json, LOCK_EX); @rename($auditFile . '.tmp', $auditFile); }
    header('Location: index.php'); exit;
  }

  // delete reservation by index
  if ($action === 'delete_reservation') {
    $idx = isset($_POST['idx']) ? (int)$_POST['idx'] : -1;
    $resFile = __DIR__ . '/../data/reservations.json';
    $rows = [];
    if (file_exists($resFile)) {
      $j = @file_get_contents($resFile);
      $rows = $j ? json_decode($j, true) : [];
      if (!is_array($rows)) $rows = [];
    }
    if ($idx >= 0 && isset($rows[$idx])) {
      array_splice($rows, $idx, 1);
  $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  if ($json !== false) { file_put_contents($resFile . '.tmp', $json, LOCK_EX); @rename($resFile . '.tmp', $resFile); }
    }
    header('Location: index.php'); exit;
  }

  // purge logs: create backup archive of combined entries, then remove archives and empty live log
  if ($action === 'purge_logs') {
    $archiveDir = __DIR__ . '/../data/archives';
    $entries = load_entries($APPLICATIONS_FILE, $LEGACY_MESSAGES_FILE);
    $archived = load_archived_entries($archiveDir);
    if (!empty($archived)) $entries = array_merge($entries, $archived);

    // backup combined into purge-backup-<ts>.json.gz
  if (!is_dir($archiveDir)) @mkdir($archiveDir, 0755, true);
  $ts = (function_exists('eastern_now') ? eastern_now('Ymd_His') : date('Ymd_His'));
    $backupName = $archiveDir . "/purge-backup-{$ts}.json.gz";
    $gz = gzencode(json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 9);
  if ($gz !== false) { if (file_put_contents($backupName . '.tmp', $gz, LOCK_EX) !== false) @rename($backupName . '.tmp', $backupName); }

    // remove archives
    if (is_dir($archiveDir)) {
      $files = glob($archiveDir . '/*.json.gz');
      if ($files) foreach ($files as $f) @unlink($f);
    }
    // empty live log
  $json = json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  if ($json !== false) { file_put_contents($logFile . '.tmp', $json, LOCK_EX); @rename($logFile . '.tmp', $logFile); }

    // redirect back
    header('Location: index.php');
    exit;
  }
}

$entries = load_entries($APPLICATIONS_FILE, $LEGACY_MESSAGES_FILE);

// --- Search and Pagination (server-side) ---
$search = trim((string)($_GET['search'] ?? ''));
$per_page = isset($_GET['per_page']) ? max(5, min(100, (int)$_GET['per_page'])) : 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// newest-first
$entries = array_reverse($entries);

// Load current site content for editor
$siteContent = [];
if (file_exists(CONTENT_FILE)) {
  $c = @file_get_contents(CONTENT_FILE);
  $siteContent = $c ? json_decode($c, true) : [];
  if (!is_array($siteContent)) $siteContent = [];
}

// filter
$filtered = [];
if ($search === '') {
  $filtered = $entries;
} else {
  $s = mb_strtolower($search);
  foreach ($entries as $e) {
    $hay = '';
    $hay .= ($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '') . ' ';
    $hay .= ($e['email'] ?? '') . ' ';
    $hay .= ($e['phone'] ?? '') . ' ';
    $hay .= ($e['position_desired'] ?? '') . ' ';
    $hay .= ($e['raw_message'] ?? '') . ' ';
    $hay .= ($e['why_work_here'] ?? '') . ' ';
    $hay = mb_strtolower($hay);
    if (mb_strpos($hay, $s) !== false) {
      $filtered[] = $e;
    }
  }
}

$total = count($filtered);
$total_pages = $total ? (int)ceil($total / $per_page) : 1;
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;
$paged_entries = array_slice($filtered, $offset, $per_page);

// CSV download
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="submissions.csv"');

    $out = fopen('php://output', 'w');
    // header row
    fputcsv($out, ['timestamp','first_name','last_name','email','phone','address','age','eligible_to_work','position_desired','employment_type','desired_salary','start_date','availability','shift_preference','hours_per_week','restaurant_experience','other_experience','why_work_here','references','mail_sent','ip']);
    foreach ($entries as $e) {
        fputcsv($out, [
            $e['timestamp'] ?? '',
            $e['first_name'] ?? '',
            $e['last_name'] ?? '',
            $e['email'] ?? '',
            $e['phone'] ?? '',
            $e['address'] ?? '',
            $e['age'] ?? '',
            $e['eligible_to_work'] ?? '',
            $e['position_desired'] ?? '',
            $e['employment_type'] ?? '',
            $e['desired_salary'] ?? '',
            $e['start_date'] ?? '',
            is_array($e['availability']) ? implode('|', $e['availability']) : $e['availability'] ?? '',
            $e['shift_preference'] ?? '',
            $e['hours_per_week'] ?? '',
            $e['restaurant_experience'] ?? '',
            $e['other_experience'] ?? '',
            $e['why_work_here'] ?? '',
            $e['references'] ?? '',
            !empty($e['mail_sent']) ? '1' : '0',
            $e['ip'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
  <title>Admin - Job Applications</title>
    <style>
      body{font-family:Arial,Helvetica,sans-serif;padding:20px}
      table{width:100%;border-collapse:collapse}
  th,td{padding:8px;border:1px solid var(--border-color);text-align:left}
  th{background:var(--divider-color)}
  .small{font-size:0.9rem;color:var(--text-secondary)}
      .top-actions{margin-bottom:1rem}
    </style>
  </head>
  <body class="admin">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
    /* Toasts */
    #toast-container { position: fixed; right: 1rem; top: 1rem; z-index: 9999; display:flex; flex-direction:column; gap:.5rem; }
  .toast { background: rgba(0,0,0,0.85); color:var(--text-inverse); padding:.6rem .8rem; border-radius:6px; box-shadow:0 6px 18px rgba(0,0,0,.2); opacity:.95 }
  .toast.success { background: var(--success-color) }
  .toast.error { background: var(--error-color) }
  /* Modal */
  #modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.5); display:none; align-items:center; justify-content:center; z-index:10000 }
  #modal { background: var(--card-bg); padding:1rem 1.25rem; border-radius:8px; max-width:480px; width:90%; box-shadow:0 10px 30px rgba(0,0,0,.25); color: var(--text-primary) }
  #modal .actions { margin-top:1rem; display:flex; gap:.5rem; justify-content:flex-end }
    </style>

    <div id="toast-container"></div>
    <div id="modal-backdrop" class="modal-backdrop">
      <div id="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
  <div id="modal-header"><h2 id="modal-title">Confirm action</h2><button id="modal-close" class="btn btn-ghost" aria-label="Close">✕</button></div>
        <div id="modal-body" class="modal-body">Are you sure?</div>
        <div class="actions"><button id="modal-cancel" type="button" class="btn btn-ghost">Cancel</button><button id="modal-ok" type="button" class="btn btn-primary">Confirm</button></div>
      </div>
    </div>
    <div class="page-wrap">
      <div class="admin-card">
        <div class="header-row">
          <div class="header-left">
            <?php
              // render site logo in admin header if available
              $adminLogo = '';
              if (!empty($siteContent['images']['logo'])) {
                $lf = $siteContent['images']['logo'];
                if (preg_match('#^https?://#i', $lf)) $adminLogo = $lf; else $adminLogo = '../uploads/images/' . ltrim($lf, '/');
              }
            ?>
            <div style="display:flex;align-items:center;gap:12px">
              <a href="../" class="logo" style="display:inline-block">
                <?php if ($adminLogo): ?>
                  <span class="logo-badge" aria-hidden="true"><img class="site-logo-img" src="<?php echo htmlspecialchars($adminLogo); ?>" alt="<?php echo htmlspecialchars($siteContent['business_info']['name'] ?? 'Site'); ?>"></span>
                <?php else: ?>
                  <strong><?php echo htmlspecialchars($siteContent['business_info']['name'] ?? 'Admin'); ?></strong>
                <?php endif; ?>
              </a>
              <div>
                <h1 style="margin:0">Admin Dashboard</h1>
                <div class="topbar">
                  <a href="../" class="btn btn-ghost" target="_blank">View site</a>
                </div>
              </div>
            </div>
          </div>
          <div class="header-actions">
              <div class="profile-wrap">
              <button id="profile-btn" type="button" class="btn btn-ghost"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7zM19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06A2 2 0 1 1 2.34 16.4l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09c.67 0 1.27-.4 1.51-1a1.65 1.65 0 0 0-.33-1.82l-.06-.06A2 2 0 1 1 7.6 2.34l.06.06c.45.45 1.02.7 1.64.7.22 0 .44-.03.65-.09.56-.17 1.16-.17 1.72 0 .21.06.43.09.65.09.62 0 1.19-.25 1.64-.7l.06-.06A2 2 0 1 1 21.66 7.6l-.06.06c-.17.17-.3.36-.4.57-.2.46-.2.98 0 1.44.1.21.23.4.4.57l.06.06A2 2 0 0 1 19.4 15z" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Admin Options ▾</button>
              <div id="profile-menu">
                <div class="pm-item pm-sep">
                  <div class="pm-combo">
                    <button type="button" class="btn btn-ghost pm-combo-toggle" aria-expanded="false"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M3 7h18M3 12h18M3 17h18" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Applications ▾</button>
                    <div class="pm-combo-menu">
                      <div style="padding:.4rem .5rem;font-weight:700;color:var(--muted);">Download current applications</div>
                      <a href="?download=applications&amp;format=csv" class="pm-subitem" role="menuitem"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M4 7h16M4 12h10M4 17h16" stroke-linecap="round" stroke-linejoin="round"/></svg></span>CSV</a>
                      <a href="?download=applications&amp;format=json" class="pm-subitem" role="menuitem"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M7 7l10 5-10 5V7z" stroke-linecap="round" stroke-linejoin="round"/></svg></span>JSON</a>
                      <div style="padding:.4rem .5rem;font-weight:700;color:var(--muted);margin-top:.4rem">Download all (including archives)</div>
                      <a href="?download=applications_all&amp;format=csv" class="pm-subitem" role="menuitem" data-confirm="Downloading all applications may create a large file. Continue?"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M4 6h16v12H4z" stroke-linecap="round" stroke-linejoin="round"/></svg></span>CSV</a>
                      <a href="?download=applications_all&amp;format=json" class="pm-subitem" role="menuitem" data-confirm="Downloading all applications may create a large file. Continue?"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M8 5h8v14H8z" stroke-linecap="round" stroke-linejoin="round"/></svg></span>JSON</a>
                      <form method="post" class="pm-form" data-confirm="This will archive and remove all logs — are you sure?" style="margin:0;margin-top:.5rem;padding:.25rem">
                        <?php echo csrf_input_field(); ?>
                        <input type="hidden" name="action" value="purge_logs">
                        <button type="submit" class="pm-subitem pm-subitem-full btn btn-danger-soft btn-danger-filled" style="display:flex;align-items:center;gap:.5rem"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Archive & clear all applications</button>
                        <span class="tooltip" data-tooltip="This will create a backup and then permanently clear all current and archived submissions">?</span>
                      </form>
                    </div>
                  </div>
                </div>
                <!-- reservation audit submenu handled below -->
                <div class="pm-item">
                    <div class="pm-combo">
                    <button type="button" class="btn btn-ghost pm-combo-toggle" aria-expanded="false"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M3 7h18M3 12h18M3 17h18" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Reservation Audit ▾</button>
                    <div class="pm-combo-menu">
                      <a href="reservation-audit.php" class="pm-subitem"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M4 6h16v12H4zM4 10h16" stroke-linecap="round" stroke-linejoin="round"/></svg></span>View full reservation audit</a>
                      <a href="reservation-audit.php?download=csv" class="pm-subitem"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M4 6h16v12H4zM8 10v6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Download CSV</a>
                      <a href="reservation-audit.php?download=json" class="pm-subitem"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M7 7l10 5-10 5V7z" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Download JSON</a>
                      <form method="post" style="margin:0" data-confirm="Clear reservation audit? This will remove recent audit entries. Continue?">
                        <?php echo csrf_input_field(); ?>
                        <input type="hidden" name="action" value="clear_reservation_audit">
                        <button type="submit" class="pm-subitem pm-subitem-full btn btn-danger-soft btn-danger-filled"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M6 7h12M9 7v10M15 7v10" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Clear reservation audit</button>
                      </form>
                      <form method="post" style="margin:0">
                        <?php echo csrf_input_field(); ?>
                        <input type="hidden" name="action" value="download_reservations">
                        <button type="submit" class="pm-subitem pm-subitem-full"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v10M8 9l4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Download Reservations (CSV)</button>
                      </form>
                    </div>
                  </div>
                </div>
                  <div class="pm-item">
                    <form method="post" action="empty-trash.php" class="pm-form" data-confirm="Empty image trash? This will permanently delete trashed images. Continue?">
                      <?php echo csrf_input_field(); ?>
                      <button type="submit" class="btn btn-danger-soft btn-danger-filled"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Empty image trash</button>
                    </form>
                  </div>
                <div class="pm-item pm-sep">
                  <form method="get" action="change-password.php" class="pm-form" style="margin:0">
                    <button type="submit" class="btn btn-ghost"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM5 11v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Change admin password</button>
                  </form>
                </div>
                <div class="pm-item">
                  <a class="btn btn-ghost" href="smtp-settings.php"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M4 4h16v16H4zM4 8l8 5 8-5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>SMTP Settings</a>
                </div>
                <div class="pm-item pm-sep">
                  <form method="post" class="pm-form">
                    <?php echo csrf_input_field(); ?>
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="btn btn-danger btn-logout"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M16 17l5-5-5-5M21 12H9M13 19v2H5V3h8v2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Log out</button>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
    <!-- SMTP settings moved to smtp-settings.php -->

    <h1>Job Applications</h1>
    <form method="get" class="search-form">
      <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, email, phone, application details..." class="search-input">
      <label class="perpage-label">Per page:
        <select name="per_page" onchange="this.form.submit()">
          <option value="10" <?php if ($per_page==10) echo 'selected'; ?>>10</option>
          <option value="20" <?php if ($per_page==20) echo 'selected'; ?>>20</option>
          <option value="50" <?php if ($per_page==50) echo 'selected'; ?>>50</option>
          <option value="100" <?php if ($per_page==100) echo 'selected'; ?>>100</option>
        </select>
      </label>
  <button type="submit" class="btn btn-primary">Search</button>
    </form>
  <p class="small">Total results: <?php echo (int)$total; ?> (page <?php echo (int)$page; ?> of <?php echo (int)$total_pages; ?>)</p>
    <?php if (empty($entries)): ?>
      <p>No submissions yet.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Time</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Position</th>
            <th>Mail Sent</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($paged_entries as $e): ?>
          <tr>
            <td><?php echo htmlspecialchars($e['timestamp'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '')); ?></td>
            <td><?php echo htmlspecialchars($e['email'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($e['phone'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($e['position_desired'] ?? ''); ?></td>
            <td><?php echo !empty($e['mail_sent']) ? 'Yes' : 'No'; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <hr class="spaced-hr">
    <h2>Menu Management</h2>
    <p class="small">Manage menu items shown on the public site.</p>
    <div style="margin-bottom:.5rem">
      <form method="post" action="add-menu-item.php" style="display:inline-block;margin-right:1rem">
        <?php echo csrf_input_field(); ?>
        <label for="quick-section">Quick add blank item to:</label>
        <select name="section_id" id="quick-section">
          <?php
            $existingSections = [];
            if (!empty($siteContent['menu']) && is_array($siteContent['menu'])) {
              foreach ($siteContent['menu'] as $sec) {
                $sid = $sec['id'] ?? ($sec['title'] ?? '');
                if ($sid) { $existingSections[] = $sid; echo '<option value="' . htmlspecialchars($sid) . '">' . htmlspecialchars($sec['title'] ?? $sid) . '</option>'; }
              }
            }
            // if burgers-sandwiches not present, include it for convenience
            if (!in_array('burgers-sandwiches', $existingSections)) echo '<option value="burgers-sandwiches">Burgers & Sandwiches</option>';
          ?>
        </select>
        <button type="submit" class="btn btn-ghost">Quick add blank item</button>
      </form>
      <span class="small">If the JS editor doesn't show new fields, use this to append a blank item server-side and then reload the page.</span>
    </div>
  <div id="menu-admin-wrap" class="menu-admin-wrap" style="display:flex;gap:1rem;align-items:stretch;margin-top:.5rem">
  <div id="menu-admin" style="flex:1">
    <div style="margin-bottom:.5rem">
    <button id="add-menu-item" type="button" class="btn btn-primary">Add Section</button>
    <button id="expand-all-sections" type="button" class="btn btn-ghost" style="margin-left:.5rem">Expand all</button>
    <label style="margin-left:.6rem; font-weight:600; font-size:0.95rem; display:inline-block">Find item:</label>
  <input id="find-menu-input" type="text" placeholder="Item title (e.g. Club Sub)" style="margin-left:.4rem; padding:.35rem; border-radius:6px; border:1px solid var(--border-color)">
    <button id="find-menu-button" type="button" class="btn btn-ghost" style="margin-left:.25rem">Find</button>
  </div>
        <div id="menu-list"></div>
      </div>
      <script>
        (function(){
          var btn = document.getElementById('expand-all-sections');
          if (!btn) return;
          btn.addEventListener('click', function(){
            try { document.dispatchEvent(new Event('admin.expandAllMenuSections')); } catch(e) { }
          });
        })();
      </script>
      <script>
        (function(){
          var fb = document.getElementById('find-menu-button');
          var fin = document.getElementById('find-menu-input');
          if (!fb || !fin) return;
          fb.addEventListener('click', function(){
            var term = (fin.value || '').trim();
            if (!term) { alert('Please enter an item title to find'); return; }
            try { document.dispatchEvent(new CustomEvent('admin.findMenuItem', { detail: { term: term } })); } catch(e) { }
          });
          // allow enter key in input to trigger find
          fin.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); fb.click(); } });
        })();
      </script>
      <div id="menu-preview" style="flex:1;min-width:300px;max-width:480px;display:flex;flex-direction:column">
        <h3 style="margin-top:0">Live Preview</h3>
  <div id="preview-area" style="border:1px solid var(--border-color);border-radius:8px;padding:.6rem;background:var(--card-bg);min-height:240px;overflow:auto;flex:1;min-height:0;max-height:640px"></div>
      </div>
    </div>
    
    <hr class="spaced-hr">
    <?php
      // Compact recent reservation summary (last 5 audit entries)
      $auditFile = __DIR__ . '/../data/reservation-audit.json';
      $recentAudit = [];
      if (file_exists($auditFile)) {
        $aj = @file_get_contents($auditFile);
        $allAudit = $aj ? json_decode($aj, true) : [];
        if (is_array($allAudit) && count($allAudit)) {
          $recentAudit = array_slice(array_reverse($allAudit), 0, 5);
        }
      }
    ?>
    <?php if (!empty($recentAudit)): ?>
      <div class="card" style="margin-bottom:.75rem">
        <h3 style="margin-top:0">Recent reservation summary</h3>
        <ul class="small" style="margin:0;padding-left:1rem">
          <?php foreach ($recentAudit as $a): ?>
            <li><?php echo htmlspecialchars(($a['timestamp'] ?? '') . ' — ' . ($a['name'] ?? 'Unknown') . ' — ' . ($a['guests'] ?? '')); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php else: ?>
      <p class="small">No recent reservation activity.</p>
    <?php endif; ?>

    <h2>Reservations</h2>
    <p class="small">Review reservations submitted through the public site.</p>
    <?php
      $resFile = __DIR__ . '/../data/reservations.json';
      $reservations = [];
      if (file_exists($resFile)) {
        $j = @file_get_contents($resFile);
        $reservations = $j ? json_decode($j, true) : [];
        if (!is_array($reservations)) $reservations = [];
      }
    ?>
    <?php if (empty($reservations)): ?>
      <p>No reservations yet.</p>
    <?php else: ?>
      <table class="admin-table">
        <thead>
          <tr><th>Time</th><th>Name</th><th>Phone</th><th>Date</th><th>Time</th><th>Guests</th><th>Event</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($reservations as $i => $r): ?>
          <tr>
            <td><?php echo htmlspecialchars($r['timestamp'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['name'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['phone'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['date'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['time'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['guests'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['event_type'] ?? ''); ?></td>
            <td>
              <form method="post" style="display:inline" data-confirm="Delete this reservation?">
                <?php echo csrf_input_field(); ?>
                <input type="hidden" name="action" value="delete_reservation">
                <input type="hidden" name="idx" value="<?php echo (int)$i; ?>">
                <button type="submit" class="btn btn-danger-soft">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

  <hr class="spaced-hr">
    <h2>Site Content Editor</h2>
    <p class="small">Edit named content sections and save. Changes are stored in <code><?php echo htmlspecialchars(CONTENT_FILE); ?></code>.</p>

    <div id="content-editor" class="content-editor-wrap">
      <div class="left">
        <label for="section-select">Section</label>
        <select id="section-select" class="section-select"></select>

        <div id="schema-form-wrap" class="schema-wrap">
          <form id="schema-form">
            <?php echo csrf_input_field(); ?>
            <div id="schema-fields"></div>
            <div class="form-actions">
              <button id="save-section" type="submit" class="btn btn-primary">Save Section</button>
            </div>
              <div id="autosave-status" class="small muted-text" style="margin-top:.4rem"></div>
          </form>
        </div>
      </div>

      <!-- right column intentionally left blank: live preview removed -->
      <div class="right"></div>
    </div>

    <hr class="spaced-hr">
    <h2>Image Uploads</h2>
    <p class="small">Upload images (logo, hero, gallery). Uploaded files are placed in <code><?php echo htmlspecialchars(UPLOAD_DIR); ?></code>.</p>
    <div class="upload-wrap">
      <form id="upload-form" enctype="multipart/form-data">
        <?php echo csrf_input_field(); ?>
        <label>Type
          <select name="type">
            <option value="logo">Logo</option>
            <option value="hero">Hero</option>
            <option value="gallery">Gallery</option>
            <option value="general">General</option>
          </select>
        </label>
        <label>File
          <input type="file" name="image" accept="image/*" required>
        </label>
        <button type="submit" class="btn btn-primary">Upload Image</button>
        <div id="upload-result" class="small"></div>
      </form>
    <div id="image-list"></div>
    </div>


    <script>
      // bootstrap data for extracted admin JS
      window.__siteContent = <?php echo json_encode($siteContent, JSON_UNESCAPED_SLASHES); ?> || {};
      window.__csrfToken = (document.querySelector('input[name="csrf_token"]') || { value: '' }).value || '';
      window.__schemaUrl = 'content-schemas.json';
    </script>
    <script>
      // admin options submenu toggles for combined CSV/JSON actions with keyboard navigation
      (function(){
        function closeAll() {
          document.querySelectorAll('.pm-combo-menu').forEach(function(m){ m.style.display = 'none'; });
          document.querySelectorAll('.pm-combo-toggle').forEach(function(b){ b.setAttribute('aria-expanded','false'); });
        }

        function openMenu(toggle, menu) {
          closeAll();
          menu.style.display = 'block';
          toggle.setAttribute('aria-expanded','true');
          // focus first focusable item in menu
          var first = menu.querySelector('.pm-subitem');
          if (first) first.focus();
        }

        document.addEventListener('click', function(e){
          var t = e.target;
          var toggle = t.closest && t.closest('.pm-combo-toggle');
          if (toggle) {
            var wrap = toggle.parentNode;
            var menu = wrap.querySelector('.pm-combo-menu');
            var isOpen = menu.style.display !== 'none';
            if (isOpen) { closeAll(); }
            else { openMenu(toggle, menu); }
            return;
          }
          // click outside closes menus
          if (!t.closest || !t.closest('.pm-combo')) {
            closeAll();
          }
        });

        // keyboard navigation within menus
        document.addEventListener('keydown', function(e){
          if (e.key === 'Escape') { closeAll(); return; }
          var active = document.activeElement;
          var inMenu = active && active.closest && active.closest('.pm-combo-menu');
          if (!inMenu) return;
          var items = Array.prototype.slice.call(active.closest('.pm-combo-menu').querySelectorAll('.pm-subitem'));
          var idx = items.indexOf(active);
          if (e.key === 'ArrowDown') {
            e.preventDefault();
            var next = items[idx+1] || items[0]; next.focus();
          } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            var prev = items[idx-1] || items[items.length-1]; prev.focus();
          } else if (e.key === 'Enter') {
            // activate focused item
            if (active && active.click) { active.click(); }
          }
        });

        // add confirmation for large exports (download_all)
        document.addEventListener('click', function(e){
          var btn = e.target.closest && e.target.closest('.pm-subitem');
          if (!btn) return;
          var form = btn.tagName.toLowerCase() === 'button' ? btn.form : btn.closest('form');
          if (!form) return;
          var actionInput = form.querySelector('input[name="action"]');
          if (actionInput && actionInput.value && actionInput.value.indexOf('download_all') === 0) {
            if (!confirm('Downloading all applications may create a large file. Continue?')) {
              e.preventDefault();
              return false;
            }
          }
        }, true);
      })();
    </script>
  <!-- SortableJS for thumbnail reordering -->
  <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
  <script src="/assets/js/admin.js"></script>

    <script>
      // lightweight toast (works even if admin.js scope doesn't expose showToast)
      (function(){
        function toast(msg, type='success', timeout=3500){
          var c = document.getElementById('toast-container');
          if (!c){ c = document.createElement('div'); c.id='toast-container'; document.body.appendChild(c); }
          var el = document.createElement('div'); el.className = 'toast ' + (type==='success' ? 'success' : (type==='error' ? 'error' : ''));
          el.textContent = msg; c.appendChild(el);
          setTimeout(function(){ el.style.transition='opacity .3s'; el.style.opacity='0'; setTimeout(function(){ el.remove(); }, 350); }, timeout);
        }

        var params = new URLSearchParams(window.location.search);
        if (params.get('msg') === 'trash_emptied') {
          var c = parseInt(params.get('count') || '0', 10);
          if (c > 0) toast('Emptied ' + c + ' files from image trash', 'success');
          else toast('Image trash emptied (no files removed)', 'success');
        }
        if (params.get('msg') === 'notrash') { toast('No trash folder found', 'error'); }
        if (params.get('error') === 'csrf') { toast('Invalid CSRF token', 'error'); }
      })();
    </script>

    <script>
      // Replace native confirm() with a centralized helper provided in assets/js/admin.js
      (function(){
        // if the page's admin.js exposes showAdminConfirm, use it for any forms/buttons with data-confirm
        function delegateConfirmForForms() {
          document.addEventListener('submit', async function(e){
            var form = e.target;
            if (form && form.getAttribute && form.getAttribute('data-confirm')) {
              e.preventDefault();
              var msg = form.getAttribute('data-confirm');
              try {
                if (window.showAdminConfirm) {
                  var ok = await window.showAdminConfirm(msg);
                  if (ok) form.submit();
                } else {
                  if (confirm(msg)) form.submit();
                }
              } catch (err) { /* ignore */ }
            }
          }, true);
        }

        // handle elements that are toggle buttons with data-confirm (e.g., before opening a large submenu)
        function delegateConfirmForButtons() {
          document.addEventListener('click', async function(e){
            var btn = e.target.closest && e.target.closest('[data-confirm]');
            if (!btn) return;
            // If the element is a button that normally toggles a submenu, show confirm first
            var isToggle = btn.classList && btn.classList.contains('pm-combo-toggle');
            if (!isToggle) return;
            var msg = btn.getAttribute('data-confirm');
            if (!msg) return;
            e.preventDefault();
            try {
              if (window.showAdminConfirm) {
                var ok = await window.showAdminConfirm(msg);
                if (ok) {
                  // trigger original click action by toggling the menu programmatically
                  btn.click();
                }
              } else {
                if (confirm(msg)) btn.click();
              }
            } catch (err) { /* ignore */ }
          }, true);
        }

        delegateConfirmForForms();
        delegateConfirmForButtons();
      })();
    </script>

    <?php if ($total_pages > 1): ?>
      <div class="pagination-wrap">
        <?php if ($page > 1): ?>
          <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>">&laquo; Prev</a>
        <?php endif; ?>

        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
          <?php if ($p == $page): ?>
            <strong class="current-page"><?php echo (int)$p; ?></strong>
          <?php else: ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => (int)$p])); ?>"><?php echo (int)$p; ?></a>
          <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
          <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>">Next &raquo;</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['download']) && $_GET['download'] == '1'): ?>
    <?php
      header('Content-Type: application/json');
      header('Content-Disposition: attachment; filename="submissions.json"');
      echo json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
      exit;
    ?>
    <?php endif; ?>
  </body>
 </html>
