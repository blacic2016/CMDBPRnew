<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/helpers.php';

$table = 'sheet_equipos';
$id = 1; // Assuming ID 1 exists
$pdo = getPDO();

$stmt = $pdo->prepare("SELECT hostname FROM `$table` WHERE id = :id");
$stmt->execute([':id' => $id]);
$old = $stmt->fetchColumn();

echo "Old Hostname: $old\n";

$new = $old . "_TEST";
$stmt = $pdo->prepare("UPDATE `$table` SET hostname = :h WHERE id = :id");
$res = $stmt->execute([':h' => $new, ':id' => $id]);

if ($res) {
    echo "Update Success!\n";
    $stmt = $pdo->prepare("SELECT hostname FROM `$table` WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $updated = $stmt->fetchColumn();
    echo "New Hostname: $updated\n";
    
    // Revert
    $pdo->prepare("UPDATE `$table` SET hostname = :h WHERE id = :id")->execute([':h' => $old, ':id' => $id]);
    echo "Reverted.\n";
} else {
    print_r($pdo->errorInfo());
}
