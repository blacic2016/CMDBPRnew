<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/db.php';

$pdo = getPDO();
try {
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `cotizador_cotizaciones_detalles` LIKE 'multiplier_type'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `cotizador_cotizaciones_detalles` ADD COLUMN `multiplier_type` VARCHAR(50) DEFAULT 'Ninguno'");
        echo "Column multiplier_type added successfully.\n";
    } else {
        echo "Column multiplier_type already exists.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
