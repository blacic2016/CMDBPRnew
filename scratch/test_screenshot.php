<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/password_vault_helper.php';

echo "Testing capture_screenshot...\n";
$url = "https://www.google.com";
$entry_id = 999;

$path = capture_screenshot($url, $entry_id);
echo "Result path: " . ($path ? $path : "FAILED") . "\n";
if ($path && file_exists(__DIR__ . '/../public/' . $path)) {
    echo "✅ File exists at: public/" . $path . "\n";
} else {
    echo "❌ File does NOT exist at: public/" . $path . "\n";
}
