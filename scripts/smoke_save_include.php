<?php
// scripts/smoke_save_include.php
// CLI smoke test that includes admin/save-content.php with stubs to allow save.

chdir(__DIR__ . '/..');

$contentFile = __DIR__ . '/../data/content.json';
if (!file_exists($contentFile)) {
    echo "No data/content.json found to test against.\n";
    exit(1);
}

$bak = $contentFile . '.bak-' . date('Ymd-His');
if (!copy($contentFile, $bak)) {
    echo "Failed to create backup of content.json\n";
    exit(1);
}
echo "Created backup: $bak\n";

// Prepare a minimal POST payload via php://input
$payload = [
    'section' => 'menu',
    'content' => [
        [ 'title' => 'CLI Smoke Section', 'id' => 'cli-smoke-section', 'items' => [ [ 'title' => 'CLI Smoke Item', 'description' => 'Smoke test', 'quantities' => [ ['label'=>'3pc','value'=>3,'price'=>'6.00'] ] ] ] ]
    ],
    'csrf_token' => 'cli-test'
];
$json = json_encode($payload);

// Simulate POST input by writing to a temp php://input replacement
// We'll set $HTTP_RAW_POST_DATA equivalent by creating a temporary stream wrapper
// Simpler: create a temporary file and override php://input using a stream wrapper is complex; instead set $GLOBALS['__SMOKE_RAW'] and modify save-content.php to look for it when running in CLI.

// Define stubs used by admin/save-content.php to bypass auth and CSRF checks when run from CLI
function checkAuth() { return true; }
function verify_csrf_token($t) { return true; }

// Populate superglobals as the endpoint expects
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';
// emulate php://input by setting a global and polyfill file_get_contents('php://input') in a small wrapper
$GLOBALS['__SMOKE_RAW'] = $json;

// Create a small polyfill for file_get_contents('php://input') by defining a function if not already used
if (!function_exists('__smoke_file_get_contents')) {
    function __smoke_file_get_contents($arg) {
        if ($arg === 'php://input' && isset($GLOBALS['__SMOKE_RAW'])) return $GLOBALS['__SMOKE_RAW'];
        return file_get_contents($arg);
    }
}

// Temporarily override file_get_contents via runkit or stream wrappers isn't available; so we copy admin/save-content.php and replace file_get_contents('php://input') with __smoke_file_get_contents('php://input') in a temp file and include that.
$orig = __DIR__ . '/../admin/save-content.php';
$tmp = sys_get_temp_dir() . '/save-content-smoke-' . uniqid() . '.php';
$code = file_get_contents($orig);
// Replace the first occurrence of file_get_contents('php://input') with __smoke_file_get_contents('php://input')
$code = preg_replace("/file_get_contents\(\s*'php:\/\/input'\s*\)/", "__smoke_file_get_contents('php://input')", $code, 1);
file_put_contents($tmp, $code);

ob_start();
include $tmp;
$out = ob_get_clean();
echo "Endpoint output:\n";
echo $out . "\n";

$j = json_decode($out, true);
if (!$j || !isset($j['success']) || $j['success'] !== true) {
    echo "Save endpoint reported failure; restoring backup.\n";
    copy($bak, $contentFile);
    echo "Backup restored.\n";
    unlink($tmp);
    exit(1);
}

echo "Save endpoint returned success.\n";
unlink($tmp);
exit(0);
