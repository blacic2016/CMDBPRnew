<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/zabbix_api.php';

// Filtros opcionales (por implementar en el frontend)
$severity_filter = isset($_GET['severity']) ? $_GET['severity'] : null;
$tag_filter = isset($_GET['tag']) ? $_GET['tag'] : null;

$params = [
    'output' => ['eventid', 'name', 'severity', 'clock', 'r_eventid', 'acknowledged', 'suppressed', 'objectid'],
    'selectTags' => 'extend',
    'selectAcknowledges' => 'extend',
    'recent' => true, 
    'sortfield' => 'eventid',
    'sortorder' => 'DESC'
];

if ($severity_filter !== null && $severity_filter !== 'all') {
    $params['severities'] = [$severity_filter];
}

$response = call_zabbix_api('problem.get', $params);

if (isset($response['error'])) {
    echo json_encode(['success' => false, 'error' => $response['error']]);
    exit;
}

$problems = $response['result'];

// Obtener hostnames para los triggers (objectid)
$triggerids = array_unique(array_column($problems, 'objectid'));
$trigger_hosts = [];

if (!empty($triggerids)) {
    $t_res = call_zabbix_api('trigger.get', [
        'triggerids' => $triggerids,
        'selectHosts' => ['hostid', 'name'],
        'output' => ['triggerid']
    ]);
    if (!isset($t_res['error'])) {
        foreach ($t_res['result'] as $t) {
            $trigger_hosts[$t['triggerid']] = !empty($t['hosts']) ? $t['hosts'][0] : ['hostid' => null, 'name' => 'N/A'];
        }
    }
}

$columns = [
    'open' => [],
    'in_progress' => [],
    'resolved' => [],
    'maintenance' => []
];

$analytics = [
    'severity' => [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
    'status' => ['Open' => 0, 'In Progress' => 0, 'Resolved' => 0, 'Maintenance' => 0]
];

foreach ($problems as $p) {
    // Analytics: Severity
    $sev = (int)$p['severity'];
    if (isset($analytics['severity'][$sev])) {
        $analytics['severity'][$sev]++;
    }

    $host_info = $trigger_hosts[$p['objectid']] ?? ['hostid' => null, 'name' => 'N/A'];
    $host_name = $host_info['name'];
    $host_id = $host_info['hostid'];
    
    $card = [
        'eventid' => $p['eventid'],
        'name' => $p['name'],
        'severity' => $sev,
        'host' => $host_name,
        'hostid' => $host_id,
        'clock' => date('Y-m-d H:i:s', $p['clock']),
        'r_clock' => $p['r_eventid'] != 0 ? 'Resolved' : 'Active',
        'acknowledged' => $p['acknowledged'],
        'suppressed' => $p['suppressed']
    ];

    // Lógica de columnas
    if ($p['suppressed'] == 1) {
        $columns['maintenance'][] = $card;
        $analytics['status']['Maintenance']++;
    } elseif ($p['r_eventid'] != 0) {
        $columns['resolved'][] = $card;
        $analytics['status']['Resolved']++;
    } elseif ($p['acknowledged'] == 1) {
        $columns['in_progress'][] = $card;
        $analytics['status']['In Progress']++;
    } else {
        $columns['open'][] = $card;
        $analytics['status']['Open']++;
    }
}

echo json_encode([
    'success' => true,
    'columns' => $columns,
    'analytics' => $analytics
]);
