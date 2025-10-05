<?php
require_once __DIR__ . '/config.php';
require_admin();
ensure_csrf_token();

header('Content-Type: text/html; charset=utf-8');
// load site content for header
$siteContent = [];
if (file_exists(CONTENT_FILE)) {
  $c = @file_get_contents(CONTENT_FILE);
  $siteContent = $c ? json_decode($c, true) : [];
  if (!is_array($siteContent)) $siteContent = [];
}
?>
<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Admin - SMTP Settings</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
      body{font-family:Arial,Helvetica,sans-serif;padding:20px}
  .small{font-size:0.9rem;color:var(--text-secondary)}
  .card{background:var(--card-bg);border:1px solid var(--border-color);padding:1rem;border-radius:8px}
    </style>
  </head>
  <body class="admin">
    <div class="page-wrap">
      <div class="admin-card">
        <div class="admin-card-header">
          <h1 class="admin-card-title">SMTP Settings</h1>
          <div><a href="index.php" class="btn btn-ghost">Back to dashboard</a></div>
        </div>

        <p class="small">Configure SMTP credentials used to send notification emails. Passwords are stored in <code>admin/auth.json</code> (untracked).</p>
        <?php if (!file_exists(__DIR__ . '/../vendor/autoload.php')): ?>
          <div class="small" style="color:var(--error-color);margin-top:.5rem">Note: PHPMailer (Composer dependencies) not detected. The SMTP test button will be disabled until you install dependencies (see README).</div>
        <?php endif; ?>

        <section class="card card-spaced">
          <form id="smtp-form" method="post" action="save-smtp.php" class="smtp-form">
            <?php echo csrf_input_field(); ?>
            <label class="smtp-label">Username <input type="text" name="smtp_username" value="<?php echo htmlspecialchars($GLOBALS['SMTP_USERNAME_OVERRIDE'] ?? (defined('SMTP_USERNAME') ? SMTP_USERNAME : '')); ?>" class="form-input"></label>
            <label class="smtp-label">Password <input type="password" name="smtp_password" value="" placeholder="(leave blank to keep existing)" class="form-input"></label>
            <button type="submit" class="btn btn-primary">Save</button>
            <label class="smtp-test">Test recipient <input type="email" id="smtp-test-recipient" placeholder="you@yourdomain.com" class="form-input smtp-test-input"></label>
            <button type="button" id="smtp-test" class="btn btn-ghost">Send Test Email</button>
          </form>

          <div id="smtp-result" class="small smtp-result"></div>
        </section>

      </div>
    </div>

    <div id="toast-container"></div>

        <script>
      // show a toast if redirected after save
      (function(){
        var params = new URLSearchParams(window.location.search);
        var msg = params.get('msg');
        if (!msg) return;
        var c = document.getElementById('toast-container');
        if (!c){ c = document.createElement('div'); c.id='toast-container'; document.body.appendChild(c); }
        if (msg === 'save_ok') {
          var el = document.createElement('div'); el.className = 'toast success'; el.textContent = 'SMTP settings saved'; c.appendChild(el);
          setTimeout(function(){ el.style.transition='opacity .3s'; el.style.opacity='0'; setTimeout(function(){ el.remove(); }, 350); }, 3000);
        } else if (msg === 'save_failed') {
          var el = document.createElement('div'); el.className = 'toast error'; el.textContent = 'Failed to save SMTP settings'; c.appendChild(el);
          setTimeout(function(){ el.style.transition='opacity .3s'; el.style.opacity='0'; setTimeout(function(){ el.remove(); }, 350); }, 6000);
        }
      })();

    </script>

    <script>
      (function(){
        var btn = document.getElementById('smtp-test');
        if (!btn) return;
        // disable test if PHPMailer vendor autoload isn't present
        <?php if (!file_exists(__DIR__ . '/../vendor/autoload.php')): ?>
          btn.disabled = true; btn.title = 'Install Composer dependencies to enable SMTP test';
        <?php endif; ?>
        btn.addEventListener('click', function(){
          var recipient = document.getElementById('smtp-test-recipient').value || '';
          var payload = { csrf_token: '<?php echo htmlspecialchars(generate_csrf_token()); ?>' };
          if (recipient) payload.recipient = recipient;
          btn.textContent = 'Sending...'; btn.disabled = true;
          fetch('send-test-email.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type':'application/json' }, body: JSON.stringify(payload) }).then(function(r){ return r.json(); }).then(function(j){
            var out = document.getElementById('smtp-result');
            if (j && j.success) { out.innerHTML = '<div class="toast success">Test email sent successfully.</div>'; }
            else { out.innerHTML = '<div class="toast error">Test failed: ' + (j && j.message ? j.message : 'unknown') + '</div>'; }
          }).catch(function(err){ document.getElementById('smtp-result').innerHTML = '<div class="toast error">Error: ' + err.message + '</div>'; }).finally(function(){ btn.textContent = 'Send Test Email'; btn.disabled = false; setTimeout(function(){ var el = document.getElementById('smtp-result'); if (el) el.innerHTML = ''; }, 5000); });
        });
      })();
    </script>
  </body>
</html>
