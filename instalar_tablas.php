<?php
/**
 * CMDB VILASECA - Instalador de Tablas
 * Ejecuta este archivo desde el navegador para crear las tablas necesarias.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/db.php';

echo "<h1>🛠️ Instalador de Tablas CMDB</h1>";

$pdo = getPDO();
if (!$pdo) {
    die("❌ Error: No se pudo conectar a la base de datos.");
}

$schema = file_get_contents(__DIR__ . '/database_schema.sql');
$queries = explode(';', $schema);

foreach ($queries as $q) {
    $q = trim($q);
    if (empty($q)) continue;
    
    try {
        $pdo->exec($q);
        // Extraer el nombre de la tabla para el mensaje
        if (preg_match('/CREATE TABLE IF NOT EXISTS `([^`]+)`/', $q, $matches)) {
            echo "✅ Tabla '{$matches[1]}' procesada.<br>";
        } else if (preg_match('/INSERT IGNORE INTO `([^`]+)`/', $q, $matches)) {
            echo "✅ Datos iniciales para '{$matches[1]}' procesados.<br>";
        }
    } catch (PDOException $e) {
        echo "❌ ERROR en consulta: " . htmlspecialchars($e->getMessage()) . "<br>";
    }
}

echo "<br><b>Proceso finalizado.</b> <a href='diagnostico.php'>Ir al Diagnóstico</a>";
