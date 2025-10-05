<?php
/**
 * admin/login.php
 * Login form for the administration panel.
 */

require_once 'config.php';

// If already logged in, redirect to admin panel
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        header('Location: index.php');
        exit;
}

// Prepare content.json logo path safely
$cfile = __DIR__ . '/../data/content.json';
$c = file_exists($cfile) ? json_decode(file_get_contents($cfile), true) : [];
$logo = $c['images']['logo'] ?? '';
$logoUrl = '';
if ($logo) {
        $logoUrl = preg_match('#^https?://#i', $logo) ? $logo : '../uploads/images/'.ltrim($logo, '/');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <title>Admin Login</title>
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <link rel="stylesheet" href="/assets/css/admin.css">
        <style>
            /* Minimal page-level fallback while admin.css loads */
            html,body{height:100%;}
            body{margin:0;}
        </style>
    </head>
    <body class="admin">
        <div class="page-wrap">
            <div class="admin-card" style="max-width:520px;margin:2.5rem auto;padding:2rem">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:1rem">
                    <a href="../" class="logo" style="display:inline-block">
                        <?php if ($logoUrl): ?>
                            <span class="logo-badge" aria-hidden="true"><img class="site-logo-img" src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Admin" style="height:40px; width:auto; max-width:100%; display:inline-block; vertical-align:middle;"></span>
                        <?php else: ?>
                            <strong><?php echo htmlspecialchars($c['business_info']['name'] ?? 'Admin'); ?></strong>
                        <?php endif; ?>
                    </a>
                    <div>
                        <h1 style="margin:0">Admin Login</h1>
                        <div class="topbar"><a href="../" class="btn btn-ghost" target="_blank">View site</a></div>
                    </div>
                </div>

                <div class="login-container">
                    <div class="login-header">
                        <?php if (empty($logoUrl)): ?><h1>🔐 Admin Login</h1><?php endif; ?>
                        <p>Website Management Panel</p>
                    </div>

                    <?php if (isset($_GET['error'])): ?>
                    <div class="alert">❌ Invalid username or password</div>
                    <?php endif; ?>

                    <?php if (isset($_GET['logout'])): ?>
                    <div class="alert success-alert">✅ You have been logged out successfully</div>
                    <?php endif; ?>

                    <form action="auth.php" method="POST">
                        <div class="form-group">
                            <label class="form-label" for="username">Username</label>
                            <input id="username" type="text" name="username" class="form-input" required autofocus>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="password">Password</label>
                            <input id="password" type="password" name="password" class="form-input" required>
                        </div>
                        <?php echo csrf_input_field(); ?>
                        <button type="submit" class="btn btn-primary" style="width:100%">Login</button>
                    </form>

                    <p style="text-align:center;margin-top:1rem;color:var(--text-secondary);font-size:0.9rem">Forgot password? Contact your administrator</p>
                </div>

            </div>
        </div>
    </body>
</html>
