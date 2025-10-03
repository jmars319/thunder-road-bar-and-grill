<?php
/**
 * admin/make_password.php
 * CLI helper to generate a bcrypt password hash for the admin user.
 * Usage: php make_password.php <password>
 * Intended for one-off use when setting or rotating the admin
 * password. The script exits when invoked via a web server.
 */

if (php_sapi_name() !== 'cli') {
    echo "This script is for CLI use only.\n";
    exit(1);
}
$pw = $argv[1] ?? null;
if (empty($pw)) {
    fwrite(STDOUT, "Usage: php make_password.php <password>\n");
    exit(1);
}
echo password_hash($pw, PASSWORD_DEFAULT) . PHP_EOL;
