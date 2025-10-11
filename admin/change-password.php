<?php
/**
 * admin/change-password.php
 * Simple form to change the admin password. This script performs the
 * following steps:
 *  - verifies the current password using the stored hash
 *  - enforces a minimum length for new passwords
 *  - writes the new bcrypt hash into `admin/config.php` (attempts an
 *    atomic write using a tmp file)
 *
 * Notes for developers:
 *  - Writing PHP files to update credentials is a quick solution for
 *    small self-hosted projects; in larger systems prefer a secure
 *    storage mechanism outside of code files.
 */

require_once __DIR__ . '/config.php';

// ensure user is authenticated
checkAuth();

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verify_csrf_token($token)) {
        $error = 'Invalid CSRF token';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        // verify current against ADMIN_PASSWORD_HASH
        $ok = false;
        if (!empty(ADMIN_PASSWORD_HASH) && password_verify($current, ADMIN_PASSWORD_HASH)) {
            $ok = true;
        }

        if (!$ok) {
            $error = 'Current password incorrect';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match';
        } else {
            // generate new hash and prefer to write into admin/auth.json (untracked) so
            // the runtime config and versioning work correctly. If that fails, fall
            // back to replacing the constant inside config.php.
            $hash = password_hash($new, PASSWORD_DEFAULT);

            // try to write to auth.json and bump version (preferred)
            $wroteAuth = false;
            if (function_exists('set_admin_hash_and_bump_version')) {
                try {
                    $wroteAuth = set_admin_hash_and_bump_version($hash);
                } catch (Exception $e) {
                    $wroteAuth = false;
                }
            }

            if ($wroteAuth) {
                // update session pw version so the current session remains valid
                if (session_status() !== PHP_SESSION_ACTIVE) session_start();
                $_SESSION['admin_pw_version'] = get_admin_password_version();
                $success = 'Password updated successfully';
            } else {
                // fallback: update config.php by replacing the constant definition
                $cfgFile = __DIR__ . '/config.php';
                $orig = @file_get_contents($cfgFile);
                if ($orig === false) {
                    $error = 'Failed to read config file. Check permissions.';
                } else {
                    // use addslashes to safely insert the hash into single quotes
                    $replacement = "define('ADMIN_PASSWORD_HASH', '" . addslashes($hash) . "');";
                    $newContent = preg_replace("/define\(\s*'ADMIN_PASSWORD_HASH'\s*,\s*'[^']*'\s*\);/", $replacement, $orig, 1, $count);
                    if ($newContent === null) {
                        $error = 'Failed to update config content';
                    } elseif ($count === 0) {
                        // no match - append replacement after ADMIN_USERNAME define
                        $newContent = preg_replace("/(define\(\s*'ADMIN_USERNAME'[^;]+;)/", "$1\n" . $replacement, $orig, 1, $c2);
                    }

                    if (empty($error)) {
                        // write atomically
                        $tmp = $cfgFile . '.tmp';
                        if (file_put_contents($tmp, $newContent, LOCK_EX) !== false && @rename($tmp, $cfgFile)) {
                            $success = 'Password updated successfully';
                            // Best-effort: update session pw version to avoid immediate forced logout
                            if (session_status() !== PHP_SESSION_ACTIVE) session_start();
                            $_SESSION['admin_pw_version'] = get_admin_password_version();
                        } else {
                            @unlink($tmp);
                            error_log('change-password.php: failed to write config file ' . $cfgFile);
                            $error = 'Failed to write config file. Check permissions.';
                        }
                    }
                }
            }
        }
    }
}

// ensure a CSRF token exists for the form
generate_csrf_token();
?>
<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Change Admin Password</title>
        <link rel="stylesheet" href="/assets/css/admin.css">
    </head>
    <body class="admin">
        <div class="page-wrap">
            <div class="admin-card" style="max-width:720px;margin:2.5rem auto;padding:1.25rem">
                <div class="header-row">
                    <div class="header-left">
                        <h1 style="margin:0">Change Password</h1>
                    </div>
                    <div class="header-actions">
                        <a href="index.php" class="btn btn-ghost">Back to Dashboard</a>
                    </div>
                </div>

                <?php if ($error): ?><div class="success-alert" style="margin-top:.6rem">❌ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="success-alert" style="margin-top:.6rem">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>

                <div class="content-editor" style="margin-top:1rem">
                    <div class="left">
                        <form method="post">
                            <?php echo csrf_input_field(); ?>
                            <label class="form-label">Current password
                                <input name="current_password" type="password" required class="form-input">
                            </label>
                            <label class="form-label">New password
                                <input name="new_password" type="password" required class="form-input">
                            </label>
                            <label class="form-label">Confirm new password
                                <input name="confirm_password" type="password" required class="form-input">
                            </label>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Change Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
