<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/db.php';
$pdo = getPDO();
$tables = ['dc_rooms', 'dc_racks', 'dc_rack_devices'];
foreach ($tables as $t) {
    echo "TABLE: $t\n";
    $stmt = $pdo->query("SHOW CREATE TABLE $t");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $row['Create Table'] . "\n\n";
}
