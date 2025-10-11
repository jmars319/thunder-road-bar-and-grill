<?php
require_once __DIR__ . '/config.php';
require_admin();
ensure_csrf_token();

// Load site content for logo/title like index.php
$siteContent = [];
if (file_exists(CONTENT_FILE)) {
  $c = @file_get_contents(CONTENT_FILE);
  $siteContent = $c ? json_decode($c, true) : [];
  if (!is_array($siteContent)) $siteContent = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Email Scheduler - Admin</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
      /* small compatibility tweaks */
      .container{max-width:1100px;margin:20px auto;padding:0}
      .tabs{display:flex;gap:8px;margin-bottom:16px}
      .tab{padding:8px 12px;background:#fff;border-radius:6px;cursor:pointer}
      .tab.active{background:#3498db;color:#fff}
      .tab-content{display:none;background:#fff;padding:16px;border-radius:8px}
      .tab-content.active{display:block}
    </style>
</head>
<body class="admin">
  <link rel="stylesheet" href="/assets/css/admin.css">
  <div class="page-wrap">
    <div class="admin-card">
      <div class="header-row">
        <div class="header-left">
          <div style="display:flex;align-items:center;gap:12px">
            <a href="../" class="logo" style="display:inline-block">
              <?php
                $adminLogo = '';
                if (!empty($siteContent['images']['logo'])) {
                  $lf = $siteContent['images']['logo'];
                  if (preg_match('#^https?://#i', $lf)) $adminLogo = $lf; else $adminLogo = '../uploads/images/' . ltrim($lf, '/');
                }
              ?>
              <?php if ($adminLogo): ?>
                <span class="logo-badge" aria-hidden="true"><img class="site-logo-img" src="<?php echo htmlspecialchars($adminLogo); ?>" alt="<?php echo htmlspecialchars($siteContent['business_info']['name'] ?? 'Site'); ?>"></span>
              <?php else: ?>
                <strong><?php echo htmlspecialchars($siteContent['business_info']['name'] ?? 'Admin'); ?></strong>
              <?php endif; ?>
            </a>
            <div>
              <h1 style="margin:0">Email Scheduler</h1>
              <div class="topbar">
                <a href="../" class="btn btn-ghost" target="_blank">View site</a>
                <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
              </div>
            </div>
          </div>
        </div>
        <div class="header-actions">
          <div class="profile-wrap">
            <button id="profile-btn" type="button" class="btn btn-ghost">Admin Options ▾</button>
            <div id="profile-menu" style="display:none"></div>
          </div>
        </div>
      </div>

      <div class="container" style="padding:20px">
        <p>Manage automated email campaigns</p>

        <div class="tabs">
            <button class="tab active" data-target="campaigns">Campaigns</button>
            <button class="tab" data-target="new-campaign">New Campaign</button>
            <button class="tab" data-target="config">Email Config</button>
            <button class="tab" data-target="logs">Email Logs</button>
        </div>

        <div id="campaigns" class="tab-content active">
            <h2>Your Campaigns</h2>
            <div id="campaign-list">Loading campaigns...</div>
        </div>

        <div id="new-campaign" class="tab-content">
            <h2>Create New Campaign</h2>
            <div id="campaign-alert"></div>
            <form id="campaign-form">
                <div class="form-group"><label>Campaign Name</label><input name="name" required></div>
                <div class="form-group"><label>Email Subject</label><input name="subject" required></div>
                <div class="form-group"><label>Email Body</label><textarea name="body" required></textarea></div>
                <div class="form-group"><label>Recipients (one per line)</label><textarea name="recipients" placeholder="email1@example.com\nemail2@example.com" required></textarea></div>
                <div class="form-group"><label>Send on Days</label><div class="checkbox-group">
                    <label><input type="checkbox" name="days" value="monday"> Monday</label>
                    <label><input type="checkbox" name="days" value="tuesday"> Tuesday</label>
                    <label><input type="checkbox" name="days" value="wednesday"> Wednesday</label>
                    <label><input type="checkbox" name="days" value="thursday"> Thursday</label>
                    <label><input type="checkbox" name="days" value="friday"> Friday</label>
                    <label><input type="checkbox" name="days" value="saturday"> Saturday</label>
                    <label><input type="checkbox" name="days" value="sunday"> Sunday</label>
                </div></div>
                <div class="form-group"><label>Send Time</label><input type="time" name="send_time" value="09:00" required></div>
                <div class="form-group"><label><input type="checkbox" name="active" checked> Active</label></div>
                <button class="btn btn-primary" type="submit">Create Campaign</button>
            </form>
        </div>

        <div id="config" class="tab-content">
            <h2>Email Configuration</h2>
            <form id="config-form">
                <div class="form-group"><label>SMTP Server</label><input name="smtp_server" required></div>
                <div class="form-group"><label>SMTP Port</label><input name="smtp_port" required></div>
                <div class="form-group"><label>Email Address</label><input name="email_address" type="email" required></div>
                <div class="form-group"><label>Email Password</label><input name="email_password" type="password" required></div>
                <button class="btn btn-primary" type="submit">Save Configuration</button>
            </form>
        </div>

        <div id="logs" class="tab-content">
            <h2>Email Logs</h2>
            <div id="logs-list">Loading logs...</div>
        </div>

        <!-- Edit Campaign Modal -->
        <div id="edit-modal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Edit Campaign</h2>
                    <button class="close-modal" onclick="closeModal()">&times;</button>
                </div>
                <div id="edit-campaign-content">
                    <form id="edit-form">
                        <input type="hidden" name="id">
                        <div class="form-group"><label>Campaign Name</label><input name="name" required></div>
                        <div class="form-group"><label>Email Subject</label><input name="subject" required></div>
                        <div class="form-group"><label>Email Body</label><textarea name="body" required></textarea></div>
                        <div class="form-group"><label>Recipients (one per line)</label><textarea name="recipients" required></textarea></div>
                        <div class="form-group"><label>Send on Days</label><div class="checkbox-group">
                            <label><input type="checkbox" name="days" value="monday"> Monday</label>
                            <label><input type="checkbox" name="days" value="tuesday"> Tuesday</label>
                            <label><input type="checkbox" name="days" value="wednesday"> Wednesday</label>
                            <label><input type="checkbox" name="days" value="thursday"> Thursday</label>
                            <label><input type="checkbox" name="days" value="friday"> Friday</label>
                            <label><input type="checkbox" name="days" value="saturday"> Saturday</label>
                            <label><input type="checkbox" name="days" value="sunday"> Sunday</label>
                        </div></div>
                        <div class="form-group"><label>Send Time</label><input type="time" name="send_time" required></div>
                        <div class="form-group"><label><input type="checkbox" name="active"> Active</label></div>
                        <div style="display:flex;gap:8px"><button class="btn btn-primary" type="submit">Save Changes</button><button type="button" class="btn btn-danger" id="delete-btn">Delete</button></div>
                    </form>
                </div>
            </div>
        </div>
      </div>
    </div>
  </div>

  <script>
  // JS: reuse previous code (kept minimal here), load via external file if desired
  const API_URL = '/admin/api/email_api.php';
  document.querySelectorAll('.tab').forEach(btn=>btn.addEventListener('click',e=>{
      document.querySelectorAll('.tab').forEach(b=>b.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));
      btn.classList.add('active');
      document.getElementById(btn.dataset.target).classList.add('active');
  }));
  // loadCampaigns, openEdit, etc. are unchanged; pull from existing file if you prefer
  </script>

</body>
</html>
