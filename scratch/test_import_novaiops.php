<?php
/**
 * Test Import Script for NovaIOPS Excel Ingestion
 */
session_start();
$_SESSION['user_id'] = 1; // Mock admin user session

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../public/api_novaiops.php';

$pdo = getPDO();
if (!$pdo) {
    die("Database connection failed.\n");
}

echo "Initializing system tables...\n";
init_novaiops_system_tables($pdo);

// Find default file
$dir = __DIR__ . '/../cotizador/novaiops';
$files = glob($dir . '/reporte_tareas*.xlsx');
if (empty($files)) {
    die("No file matching reporte_tareas*.xlsx found in $dir\n");
}

$filePath = $files[0];
$filename = basename($filePath);

echo "Found file: $filePath\n";
echo "Starting import process...\n";

$userId = 1; 
$result = process_excel_file($pdo, $filePath, $filename, $userId);

echo "Result:\n";
print_r($result);

if ($result['success']) {
    echo "\nVerification:\n";
    $countTareas = $pdo->query("SELECT COUNT(*) FROM novaiops_reporte_tareas")->fetchColumn();
    $countSeguimientos = $pdo->query("SELECT COUNT(*) FROM novaiops_seguimientos")->fetchColumn();
    $countInformacion = $pdo->query("SELECT COUNT(*) FROM novaiops_informacion_reportes")->fetchColumn();
    $historyCount = $pdo->query("SELECT COUNT(*) FROM novaiops_upload_history")->fetchColumn();
    $metaCount = $pdo->query("SELECT COUNT(*) FROM novaiops_meta_columns")->fetchColumn();

    echo "Total Tasks in DB: $countTareas\n";
    echo "Total Seguimientos in DB: $countSeguimientos\n";
    echo "Total Información Reportes in DB: $countInformacion\n";
    echo "Total Upload History Logs: $historyCount\n";
    echo "Total Meta Column Mappings: $metaCount\n";
}
