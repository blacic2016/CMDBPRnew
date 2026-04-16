<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/zabbix_api.php';

require_login();

header('Content-Type: application/json');

// 1. Obtener Macros Globales (Opcional, no debe bloquear el script)
$global_macros = [];
try {
    $global_macros_resp = call_zabbix_api('usermacro.get', ['globalmacro' => true, 'output' => 'extend']);
    if (isset($global_macros_resp['result'])) {
        foreach ($global_macros_resp['result'] as $gm) {
            if (isset($gm['value'])) {
                $global_macros[$gm['macro']] = $gm['value'];
            }
        }
    }
} catch (Exception $e) {}

// 2. Obtener Hosts
$params = [
    'output' => ['hostid', 'host', 'name'],
    'selectInterfaces' => ['interfaceid', 'ip', 'type', 'details'],
    'selectMacros' => 'extend',
    'filter' => ['status' => 0],
];

$response = call_zabbix_api('host.get', $params);

if (isset($response['error'])) {
    echo json_encode(['error' => $response['error']]);
    exit;
}

$hosts_output = [];
foreach ($response['result'] as $host) {
    // Combinar macros del host
    $host_macros = [];
    if (isset($host['macros'])) {
        foreach ($host['macros'] as $m) {
            if (isset($m['value'])) {
                $host_macros[$m['macro']] = $m['value'];
            }
        }
    }

    foreach ($host['interfaces'] as $iface) {
        // Incluimos tanto interfaces SNMP (2) como Agente (1) por si acaso el usuario quiere probar,
        // pero priorizamos mostrar las SNMP.
        if ($iface['type'] == 2 || $iface['type'] == 1) { 
            $snmp_details = $iface['details'] ?? [];
            $community = $snmp_details['community'] ?? 'public';
            
            // Si es un macro, buscar en host -> luego global
            if (preg_match('/^{\$.+}$/', $community)) {
                if (isset($host_macros[$community])) {
                    $community = $host_macros[$community];
                } elseif (isset($global_macros[$community])) {
                    $community = $global_macros[$community];
                }
            }

            $hosts_output[] = [
                'name' => $host['name'],
                'ip' => $iface['ip'],
                'version' => $snmp_details['version'] ?? '2',
                'community' => $community,
            ];
        }
    }
}

// Eliminar duplicados si un host tiene varias interfaces del mismo tipo/IP
$unique_hosts = [];
foreach ($hosts_output as $h) {
    $key = $h['ip'] . $h['community'];
    if (!isset($unique_hosts[$key])) {
        $unique_hosts[$key] = $h;
    }
}

echo json_encode(['result' => array_values($unique_hosts)]);
