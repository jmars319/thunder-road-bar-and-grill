<?php
// scripts/smoke_save_menu.php
// Minimal smoke test: POST a small menu payload to admin/save-content.php and print response.

chdir(__DIR__ . '/..');

$url = 'http://127.0.0.1:8000/admin/save-content.php'; // assumes local dev server if running

$payload = [
    'section' => 'menu',
    'content' => [
        [
            'title' => 'Smoke Test Section',
            'id' => 'smoke-test-section',
            'items' => [
                [ 'title' => 'Smoke Item', 'description' => 'Auto test item', 'quantities' => [ ['label'=>'3pc','value'=>3,'price'=>'6.00'], ['label'=>'5pc','value'=>5,'price'=>'9.00'] ] ]
            ]
        ]
    ],
    'csrf_token' => ''
];

$json = json_encode($payload);

$opts = [
  'http' => [
    'method' => 'POST',
    'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($json) . "\r\n",
    'content' => $json,
    'timeout' => 10
  ]
];

$context = stream_context_create($opts);

echo "Posting to $url ...\n";
// Attempt to call save-content.php on localhost. If you do not have a local PHP server running,
// this will fail; you can still run this script via the web server.
try {
    $res = @file_get_contents($url, false, $context);
    if ($res === false) {
        $err = error_get_last();
        echo "Request failed: " . ($err['message'] ?? 'unknown') . "\n";
        exit(1);
    }
    echo "Response:\n";
    echo $res . "\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    exit(1);
}

?>
