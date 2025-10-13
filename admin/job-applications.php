<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: login.php'); exit; }

require_once __DIR__ . '/config.php';
require_admin();
ensure_csrf_token();

$db = db();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $token = $_POST['csrf_token'] ?? '';
  if (!verify_csrf($token)) { header('HTTP/1.1 400 Bad Request'); echo 'Invalid CSRF'; exit; }
  $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  if ($action === 'mark_reviewed' && $id) {
    $db->prepare('UPDATE job_applications SET status = ? WHERE id = ?')->execute(['reviewed', $id]);
  }
  if ($action === 'archive' && $id) {
    $db->prepare('UPDATE job_applications SET status = ? WHERE id = ?')->execute(['archived', $id]);
  }
  if ($action === 'delete' && $id) {
    // optionally remove resume file
    $r = $db->prepare('SELECT resume_storage_name FROM job_applications WHERE id = ?');
    $r->execute([$id]);
    $row = $r->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['resume_storage_name'])) {
      $p = __DIR__ . '/../data/resumes/' . $row['resume_storage_name'];
      if (file_exists($p)) @unlink($p);
    }
    $db->prepare('DELETE FROM job_applications WHERE id = ?')->execute([$id]);
  }
  header('Location: job-applications.php'); exit;
}

// Server-side search & pagination
$search = trim((string)($_GET['search'] ?? ''));
$per_page = isset($_GET['per_page']) ? max(5, min(100, (int)$_GET['per_page'])) : 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$where = [];
$params = [];
if ($search !== '') {
  $where[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? OR position_desired LIKE ? OR why_work_here LIKE ? OR raw_message LIKE ? )";
  $s = '%' . $search . '%';
  for ($i=0;$i<7;$i++) $params[] = $s;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$countStmt = $db->prepare("SELECT COUNT(*) FROM job_applications {$whereSql}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$total_pages = $total ? (int)ceil($total / $per_page) : 1;
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

$sql = "SELECT id, first_name, last_name, email, phone, address, age, eligible_to_work, position_desired, employment_type, desired_salary, start_date, availability, shift_preference, hours_per_week, restaurant_experience, other_experience, references_text, why_work_here, resume_storage_name, resume_original_name, status, created_at, ip_address, user_agent, sent, raw_message FROM job_applications {$whereSql} ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page; $params[] = $offset;
$stmt = $db->prepare($sql);
$stmt->execute($params);
$apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Job Applications</title><link rel="stylesheet" href="/assets/css/admin.css"></head>
<body>
<main class="admin-card">
  <h1>Job Applications</h1>
  <form method="get" style="display:flex;gap:.5rem;align-items:center;margin-bottom:1rem">
    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, email, phone, position, message..." class="search-input">
    <label>Per page:
      <select name="per_page" onchange="this.form.submit()">
        <option value="10" <?php if ($per_page==10) echo 'selected'; ?>>10</option>
        <option value="20" <?php if ($per_page==20) echo 'selected'; ?>>20</option>
        <option value="50" <?php if ($per_page==50) echo 'selected'; ?>>50</option>
      </select>
    </label>
    <button type="submit" class="btn btn-primary">Search</button>
    <div style="margin-left:auto;display:flex;gap:.5rem">
      <form method="post" style="margin:0">
        <?php echo csrf_input_field(); ?>
        <input type="hidden" name="action" value="download_csv">
        <button type="submit" class="btn">Export CSV</button>
      </form>
      <form method="post" style="margin:0">
        <?php echo csrf_input_field(); ?>
        <input type="hidden" name="action" value="download_json">
        <button type="submit" class="btn">Export JSON</button>
      </form>
    </div>
  </form>

  <table class="admin-table">
    <thead>
      <tr>
        <th>Time</th><th>Name</th><th>Email</th><th>Phone</th><th>Position</th><th>Availability</th><th>Experience</th><th>Resume</th><th>Status</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($apps as $a): ?>
      <tr>
        <td class="small"><?php echo htmlspecialchars($a['created_at']); ?><br><?php echo htmlspecialchars($a['ip_address'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')); ?><br><small><?php echo htmlspecialchars($a['address'] ?? ''); ?></small></td>
        <td><?php echo htmlspecialchars($a['email'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($a['phone'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($a['position_desired'] ?? ''); ?><br><small><?php echo htmlspecialchars($a['employment_type'] ?? '') . ' ' . htmlspecialchars($a['desired_salary'] ?? ''); ?></small></td>
        <td><?php echo is_array($a['availability']) ? htmlspecialchars(implode(', ', json_decode($a['availability'], true) ?: [])) : htmlspecialchars($a['availability'] ?? ''); ?></td>
        <td>
          <strong>Restaurant:</strong> <?php echo nl2br(htmlspecialchars($a['restaurant_experience'] ?? '')); ?><br>
          <strong>Other:</strong> <?php echo nl2br(htmlspecialchars($a['other_experience'] ?? '')); ?><br>
          <strong>Why:</strong> <?php echo nl2br(htmlspecialchars($a['why_work_here'] ?? '')); ?>
        </td>
        <td><?php if (!empty($a['resume_storage_name'])): ?><a href="download-application.php?id=<?php echo urlencode($a['id']); ?>"><?php echo htmlspecialchars($a['resume_original_name'] ?: 'resume'); ?></a><?php else: ?>—<?php endif; ?></td>
        <td><?php echo htmlspecialchars($a['status'] ?? ''); ?></td>
        <td>
          <form method="post" style="display:inline;margin:0">
            <?php echo csrf_input_field(); ?>
            <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
            <button type="submit" name="action" value="mark_reviewed" class="btn btn-sm">Mark Reviewed</button>
          </form>
          <form method="post" style="display:inline;margin:0">
            <?php echo csrf_input_field(); ?>
            <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
            <button type="submit" name="action" value="archive" class="btn btn-sm">Archive</button>
          </form>
          <form method="post" style="display:inline;margin:0" onsubmit="return confirm('Delete this application?');">
            <?php echo csrf_input_field(); ?>
            <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
            <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div style="margin-top:.75rem;display:flex;justify-content:space-between;align-items:center">
    <div class="small">Total: <?php echo (int)$total; ?> results</div>
    <div>
      <?php for ($p=1;$p<=$total_pages;$p++): ?>
        <a href="?page=<?php echo $p; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-ghost <?php if ($p==$page) echo 'active'; ?>"><?php echo $p; ?></a>
      <?php endfor; ?>
    </div>
  </div>
</main>
</body>
</html>
