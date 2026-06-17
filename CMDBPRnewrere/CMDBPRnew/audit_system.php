<?php
/**
 * CMDB VILASECA - Auditoría de Requerimientos (CLI/Public)
 * Redireccionado a la nueva lógica optimizada.
 */
require_once __DIR__ . '/src/system_audit_helper.php';
require_once __DIR__ . '/config.php';

if (php_sapi_name() === 'cli') {
    $audit = runSystemAudit();
    echo "📊 Auditoría de Infraestructura - CMDB VILASECA\n";
    echo "============================================\n";
    echo "PHP Version: " . $audit['php']['message'] . " [" . strtoupper($audit['php']['status']) . "]\n";
    echo "\nExtensiones:\n";
    foreach($audit['extensions'] as $ext => $info) {
        echo ($info['loaded'] ? " [OK] " : " [X]  ") . str_pad($ext, 12) . " (" . $info['description'] . ")\n";
    }
    echo "\nConectividad:\n";
    echo " BD: " . $audit['database']['message'] . "\n";
    echo " Zabbix: " . $audit['zabbix']['url'] . "\n";
    exit();
}

// Si se accede vía web, redirigir a la versión bonita
header("Location: public/test.php");
exit();
