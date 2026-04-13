<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/db.php';

$pdo = getPDO();
$stmt = $pdo->query("SHOW TABLES LIKE 'sheet_%'");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "Aplicando parche de flexibilidad para 'estado_actual'...\n";

foreach ($tables as $table) {
    if ($table === 'sheet_configs' || $table === 'sheet_history') continue;
    
    try {
        $pdo->exec("ALTER TABLE `$table` MODIFY COLUMN estado_actual VARCHAR(50) DEFAULT 'USADO'");
        echo "[OK] Tabla $table actualizada.\n";
    } catch (Exception $e) {
        echo "[ERROR] Tabla $table: " . $e->getMessage() . "\n";
    }
}

echo "\nProceso finalizado. El error de 'Data truncated' no debería volver a ocurrir.\n";
