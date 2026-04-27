<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/zabbix_api.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['eventid']) || !isset($input['target_column'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$eventid = $input['eventid'];
$target = $input['target_column'];
$hostid = $input['hostid'] ?? null;

$response = ['success' => true];

if ($target === 'in_progress') {
    // Acknowledge event
    $params = [
        'eventids' => $eventid,
        'action' => 6, // Acknowledge + Message
        'message' => 'Moving to In Progress via KanbanBoard'
    ];
    $res = call_zabbix_api('event.acknowledge', $params);
    if (isset($res['error'])) {
        $response = ['success' => false, 'error' => $res['error']];
    }
} elseif ($target === 'maintenance') {
    if (!$hostid) {
        // Fetch hostid if not provided
        $res_prob = call_zabbix_api('problem.get', [
            'eventids' => $eventid,
            'selectHosts' => ['hostid']
        ]);
        if (!empty($res_prob['result']) && !empty($res_prob['result'][0]['hosts'])) {
            $hostid = $res_prob['result'][0]['hosts'][0]['hostid'];
        }
    }

    if ($hostid) {
        // Create a 1-hour maintenance
        $now = time();
        $params = [
            'name' => 'Kanban Maint ' . $eventid . '_' . $now,
            'active_since' => $now,
            'active_till' => $now + 3600,
            'hostids' => [$hostid],
            'timeperiods' => [
                [
                    'timeperiod_type' => 0, // One time only
                    'start_date' => $now,
                    'period' => 3600
                ]
            ]
        ];
        $res = call_zabbix_api('maintenance.create', $params);
        if (isset($res['error'])) {
            $response = ['success' => false, 'error' => $res['error']];
        }
    } else {
        $response = ['success' => false, 'error' => 'Could not determine host for maintenance'];
    }
}

echo json_encode($response);
