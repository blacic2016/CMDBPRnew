<?php
/**
 * CMDB VILASECA - Herramienta de Diagnóstico
 * Úsala para identificar por qué el sistema da Error 500.
 */
header('Content-Type: text/html; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Diagnóstico del Sistema CMDB</h1>";

// 1. Verificar PHP
echo "<h3>1. Entorno PHP</h3>";
echo "Versión PHP: " . PHP_VERSION . "<br>";
$exts = ['pdo', 'pdo_mysql', 'curl', 'mbstring', 'gd', 'zip', 'json'];
foreach ($exts as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ Extensión '$ext' cargada.<br>";
    } else {
        echo "❌ ERROR: Extensión '$ext' NO cargada.<br>";
    }
}

// 2. Verificar Configuración
echo "<h3>2. Archivo de Configuración</h3>";
if (file_exists(__DIR__ . '/config.php')) {
    echo "✅ Archivo config.php encontrado.<br>";
    require_once __DIR__ . '/config.php';
    echo "✅ config.php cargado correctamente.<br>";
} else {
    echo "❌ ERROR: Archivo config.php NO encontrado en " . __DIR__ . "<br>";
    exit();
}

// 3. Verificar Base de Datos
echo "<h3>3. Conexión a Base de Datos</h3>";
require_once __DIR__ . '/src/db.php';
$pdo = getPDO();
if ($pdo) {
    echo "✅ Conexión a la base de datos establecida correctamente.<br>";
    
    // Verificar tablas críticas
    $tables = ['users', 'zabbix_cmdb_config', 'zabbix_api_config', 'snmp_communities', 'snmp_scan_results'];
    foreach ($tables as $t) {
        $check = $pdo->query("SHOW TABLES LIKE '$t'")->fetch();
        if ($check) {
            echo "✅ Tabla '$t' existe.<br>";
        } else {
            echo "❌ ERROR: Tabla '$t' NO existe.<br>";
        }
    }
} else {
    echo "❌ ERROR: No se pudo conectar a la base de datos. Revisa DB_CONFIG en config.php.<br>";
}

// 4. Verificar Permisos
echo "<h3>4. Sistema de Archivos</h3>";
$dirs = [
    'storage' => __DIR__ . '/storage',
    'storage/logs' => __DIR__ . '/storage/logs',
    'public/uploads' => __DIR__ . '/public/uploads'
];

foreach ($dirs as $name => $path) {
    if (is_dir($path)) {
        if (is_writable($path)) {
            echo "✅ Directorio '$name' es escribible.<br>";
        } else {
            echo "❌ ERROR: Directorio '$name' NO es escribible (Permisos).<br>";
        }
    } else {
        echo "❌ ERROR: Directorio '$name' NO existe en $path<br>";
    }
}

// 5. Verificar Sesiones
echo "<h3>5. Sesiones PHP</h3>";
$session_path = ini_get('session.save_path');
if (empty($session_path)) $session_path = "/tmp";
echo "Ruta de sesiones: $session_path<br>";
if (is_writable($session_path)) {
    echo "✅ La ruta de sesiones es escribible.<br>";
} else {
    echo "⚠️ ADVERTENCIA: La ruta de sesiones NO parece escribible. Esto puede causar problemas de login.<br>";
}

echo "<br><hr><b>Diagnóstico completado. Si todo aparece en verde y sigues con Error 500, revisa los logs del servidor Apache/Nginx.</b>";
