<?php
// scripts/run_migration.php
// Helper to run admin/migrate-content.php locally without web auth. Intended for local dev only.

chdir(__DIR__ . '/..');
$mig = __DIR__ . '/../admin/migrate-content.php';
if (!file_exists($mig)) {
    echo "Migration helper not found: $mig\n";
    exit(1);
}

// Execute the migration as a separate PHP process to avoid session/header collisions
$cmd = sprintf('php %s', escapeshellarg($mig));
echo "Running: $cmd\n";
exec($cmd . ' 2>&1', $out, $rc);
foreach ($out as $line) echo $line . "\n";
if ($rc !== 0) {
    echo "Migration process exited with code $rc\n";
    exit($rc);
}
echo "Migration completed (exit code 0).\n";

?>
