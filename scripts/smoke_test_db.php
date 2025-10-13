<?php
// Quick smoke test: load bootstrap, get PDO, select the most recent menu item named 'Smoke Test Item'
// Load project bootstrap which exposes db() and session/cfg
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

try {
    $pdo = db();
    // Use a seeded menu item name that exists in seed_menu.sql
    $seedName = 'Fried Pickles';
    $stmt = $pdo->prepare('SELECT id, name, price, created_at FROM menu_items WHERE name = :name ORDER BY id DESC LIMIT 1');
    $stmt->execute(['name' => $seedName]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo "SMOKE OK: found menu item: ";
        echo json_encode($row) . PHP_EOL;
        exit(0);
    }
    echo "SMOKE FAIL: menu item not found" . PHP_EOL;
    exit(2);
} catch (Exception $e) {
    echo 'SMOKE ERROR: ' . $e->getMessage() . PHP_EOL;
    exit(3);
}
