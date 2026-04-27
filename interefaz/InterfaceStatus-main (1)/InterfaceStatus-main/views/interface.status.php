<?php
$page_title = _('Interface Status');

// Configuração do filtro
$filter_column = new CFormList();
$filter_column->addRow(_('Host'),
    (new CMultiSelect([
        'name' => 'hostid',
        'object_name' => 'hosts',
        'data' => isset($data['ms_hosts']) ? $data['ms_hosts'] : [],
        'popup' => [
            'parameters' => [
                'srctbl' => 'hosts',
                'srcfld1' => 'hostid',
                'dstfrm' => 'zbx_filter',
                'dstfld1' => 'hostid_',
            ]
        ],
        'multiple' => false
    ]))
);

$filter = (new CFilter())
    ->setResetUrl(new CUrl('zabbix.php?action=interfacestatus.view'))
    ->addVar('action', 'interfacestatus.view')
    ->addFilterTab(_('Filter'), [$filter_column]);

// Calcula o total de interfaces UP e DOWN
$interface_stats = [
    'up' => 0,
    'down' => 0
];

if (isset($data['interfaces'])) {
    foreach ($data['interfaces'] as $interface) {
        if ($interface['status'] === 'up') {
            $interface_stats['up']++;
        } else {
            $interface_stats['down']++;
        }
    }
}

// Container principal usando CHtmlPage
(new CHtmlPage())
    ->setTitle($page_title)
    ->addItem([
        // Filtro em uma div separada
        (new CDiv())
            ->addClass('interface-status-filter')
            ->addItem($filter),
            
        // Container principal
        (new CDiv())
            ->addClass('interface-status-container')
            ->addItem([
                // Informações do host e alertas unidos
                isset($data['host']) && !empty($data['host']) ? 
                    (new CDiv())
                        ->addClass('host-info-container')
                        ->addItem([
                            (new CDiv())
                                ->addClass('host-info-header')
                                ->addItem([
                                    (new CDiv())
                                        ->addClass('host-info-left')
                                        ->addItem([
                                            (new CSpan($data['host']['name']))
                                                ->addClass('host-name'),
                                            (new CSpan(' (' . $data['host']['ip'] . ')'))
                                                ->addClass('host-ip'),
                                            (new CSpan(_('Uptime') . ': ' . ($data['host']['uptime'] ?? 'N/A')))
                                                ->addClass('host-uptime')
                                        ]),
                                    (new CDiv())
                                        ->addClass('status-summary')
                                        ->addItem([
                                            (new CDiv())
                                                ->addClass('interface-stats')
                                                ->addItem([
                                                    (new CSpan())
                                                        ->addClass('interface-stat-label')
                                                        ->addItem(_('Interfaces:')),
                                                    (new CSpan())
                                                        ->addClass('interface-stat up')
                                                        ->addItem([
                                                            new CSpan($interface_stats['up']),
                                                            new CSpan(_('UP'))
                                                        ]),
                                                    (new CSpan())
                                                        ->addClass('interface-stat down')
                                                        ->addItem([
                                                            new CSpan($interface_stats['down']),
                                                            new CSpan(_('DOWN'))
                                                        ])
                                                ]),
                                            (new CDiv())
                                                ->addClass('alerts-severity-list')
                                                ->addItem([
                                                    (new CSpan())
                                                        ->addClass('alert-stat-label')
                                                        ->addItem(_('Alerts:')),
                                                    ...array_map(function($severity, $count) {
                                                        if ($count === 0) return null;
                                                        return (new CSpan($count))
                                                            ->addClass('severity-count')
                                                            ->addClass('severity-' . $severity);
                                                    }, array_keys($data['active_alerts']['by_severity']), $data['active_alerts']['by_severity'])
                                                ])
                                        ])
                                ])
                        ]) : null,
                // Tabela de interfaces
                isset($data['host']) && !empty($data['host']) && isset($data['interfaces']) ? 
                    (new CDiv())
                        ->addClass('interface-table-container')
                        ->addItem([
                            (new CTable())
                                ->addClass('interface-table')
                                ->setHeader([
                                    (new CCol(_('Description')))->addClass('text-left'),
                                    (new CCol(_('Status')))->addClass('text-center'),
                                    (new CCol(_('Used')))->addClass('text-center'),
                                    (new CCol(_('Traffic In')))
                                        ->addClass('text-right sortable')
                                        ->setAttribute('data-sort', 'traffic_in'),
                                    (new CCol(_('Traffic Out')))
                                        ->addClass('text-right sortable')
                                        ->setAttribute('data-sort', 'traffic_out'),
                                    (new CCol(_('Graph')))->addClass('text-center')
                                ])
                                ->addItem(
                                    array_map(function($interface) use ($data) {
                                        // Extrai apenas a parte da descrição até os dois pontos
                                        $description = '';
                                        if (isset($interface['name']) && preg_match('/^(Interface [^:]+):/', $interface['name'], $matches)) {
                                            $description = $matches[1] . ':';
                                        } else {
                                            $description = $interface['name'] ?? '';
                                        }

                                        $status = isset($interface['status']) ? $interface['status'] : 'unknown';
                                        $used = isset($interface['used']) ? $interface['used'] : 'unknown';

                                        return (new CRow())
                                            ->addClass($status === 'down' ? 'interface-down' : '')
                                            ->setAttribute('data-interface-index', $interface['index'] ?? '')
                                            ->setAttribute('data-interface-name', $interface['name'] ?? '')
                                            ->setAttribute('data-hostid', isset($data['host']['hostid']) ? $data['host']['hostid'] : '')
                                            ->setAttribute('data-traffic-in', $interface['in'] ?? '0')
                                            ->setAttribute('data-traffic-out', $interface['out'] ?? '0')
                                            ->addClass('interface-row')
                                            ->addItem([
                                                (new CCol($description))
                                                    ->addClass('text-left'),
                                                (new CCol())
                                                    ->addClass('status-cell text-center')
                                                    ->addClass($status)
                                                    ->addItem($status),
                                                (new CCol())
                                                    ->addClass('status-cell text-center')
                                                    ->addClass($used)
                                                    ->addItem($used),
                                                (new CCol($interface['in'] ?? '0 B/s'))
                                                    ->addClass('text-right'),
                                                (new CCol($interface['out'] ?? '0 B/s'))
                                                    ->addClass('text-right'),
                                                (new CCol())
                                                    ->addClass('text-center')
                                                    ->addItem(
                                                        (new CLink(''))
                                                            ->addClass('btn-graph')
                                                            ->setAttribute('title', _('View traffic graph'))
                                                            ->setAttribute('data-interface-index', $interface['index'] ?? '')
                                                            ->setAttribute('data-interface-name', $interface['name'] ?? '')
                                                            ->setAttribute('data-hostid', isset($data['host']['hostid']) ? $data['host']['hostid'] : '')
                                                            ->setAttribute('data-itemid-in', $interface['itemid_in'] ?? '')
                                                            ->setAttribute('data-itemid-out', $interface['itemid_out'] ?? '')
                                                    )
                                            ]);
                                    }, $data['interfaces'])
                                )
                        ]) : 
                    (new CDiv())
                        ->addClass('no-host-selected')
                        ->addItem(_('Please select a host to view interface details'))
            ])
    ])
    ->show(); 