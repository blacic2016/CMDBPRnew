<?php

namespace Modules\InterfaceStatus\Actions;

use CController,
    CControllerResponseData,
    CControllerResponseFatal,
    API,
    CRoleHelper,
    Exception;

class InterfaceStatus extends CController {
    protected function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        $fields = [
            'hostid' => 'string',
            'filter_set' => 'in 1',
            'filter_rst' => 'in 1',
            'page' => 'int32'
        ];

        $ret = $this->validateInput($fields);

        if (!$ret) {
            $this->setResponse(new CControllerResponseFatal());
        }

        return $ret;
    }

    protected function checkPermissions(): bool {
        return $this->checkAccess(CRoleHelper::UI_MONITORING_HOSTS);
    }

    protected function doAction(): void {
        try {
            $hostid = $this->getInput('hostid', '');
            
            // Inicializa as variáveis
            $hosts = [];
            $host = null;
            $interfaces = [];
            $active_alerts = 0;
            
            if ($hostid) {
                // Busca dados do host
                $hostData = $this->getHostInfo($hostid);
                $host = $hostData['host'] ?? null;
                
                // Busca interfaces
                $interfaces = $this->getInterfacesInfo($hostid);
                
                // Busca alertas ativos
                $active_alerts = $this->getActiveAlerts($hostid);
                
                // Prepara os dados para o multiselect
                if ($host) {
                    $hosts[$host['hostid']] = $host;
                }
            }
            
            // Prepara os dados para o multiselect
            $ms_hosts = [];
            foreach ($hosts as $h) {
                $ms_hosts[] = [
                    'id' => $h['hostid'],
                    'name' => $h['name']
                ];
            }
            
            $data = [
                'host' => $host,
                'interfaces' => $interfaces,
                'ms_hosts' => $ms_hosts,
                'active_alerts' => $active_alerts,
                'filter' => [
                    'hostid' => $hostid
                ]
            ];

            $response = new CControllerResponseData($data);
            $this->setResponse($response);
        }
        catch (Exception $e) {
            error_log('Error in doAction: ' . $e->getMessage());
            $response = new CControllerResponseFatal();
            $response->setTitle(_('Error'));
            $response->setMessage($e->getMessage());
            $this->setResponse($response);
        }
    }

    protected function getHostInfo($hostid) {
        if (empty($hostid)) {
            return [];
        }

        try {
            // Busca informações do host incluindo uptime
            $result = API::Host()->get([
                'output' => ['hostid', 'host', 'name', 'description'],
                'selectInterfaces' => ['type', 'ip', 'dns', 'useip', 'port', 'main'],
                'selectItems' => ['itemid', 'name', 'key_', 'lastvalue', 'lastclock'],
                'filter' => [
                    'hostid' => $hostid
                ],
                'preservekeys' => true
            ]);

            if (empty($result[$hostid])) {
                return [];
            }

            $host = $result[$hostid];
            
            // Encontrar a interface principal
            $main_interface = null;
            foreach ($host['interfaces'] as $interface) {
                if ($interface['main'] == 1) {
                    $main_interface = $interface;
                    break;
                }
            }

            // Encontrar o uptime
            $uptime = null;
            foreach ($host['items'] as $item) {
                if ($item['key_'] === 'uptime') {
                    $uptime = $this->formatUptime($item['lastvalue']);
                    break;
                }
            }

            return [
                'host' => [
                    'hostid' => $host['hostid'],
                    'host' => $host['host'],
                    'name' => $host['name'],
                    'description' => $host['description'],
                    'ip' => $main_interface ? $main_interface['ip'] : '',
                    'uptime' => $uptime,
                    'interfaces' => $host['interfaces']
                ]
            ];
        } catch (Exception $e) {
            error_log('Error in getHostInfo: ' . $e->getMessage());
            return [];
        }
    }

    protected function getInterfacesInfo($hostid) {
        if (empty($hostid)) {
            return [];
        }

        try {
            // Busca todos os itens relacionados a interfaces
            $items = API::Item()->get([
                'output' => ['itemid', 'name', 'key_', 'lastvalue', 'units'],
                'hostids' => [$hostid],
                'search' => [
                    'key_' => [
                        'net.if.*'
                    ]
                ],
                'searchWildcardsEnabled' => true,
                'sortfield' => 'name'
            ]);

            $interfaces = [];
            
            // Processa os itens e organiza por interface
            foreach ($items as $item) {
                // Extrai o índice da interface do nome do item
                if (preg_match('/Interface XGigabitEthernet\d+\/\d+\/\d+(?:\([\w_]+\))?/', $item['name'], $nameMatches)) {
                    $interfaceName = $nameMatches[0];
                } else {
                    continue;
                }

                // Extrai o índice do key_
                if (preg_match('/\[.*?\.(\d+)\]/', $item['key_'], $matches)) {
                    $index = $matches[1];
                    
                    if (!isset($interfaces[$index])) {
                        $interfaces[$index] = [
                            'index' => $index,
                            'name' => $interfaceName,
                            'status' => 'down',
                            'used' => 'free',
                            'in' => '0 B/s',
                            'out' => '0 B/s',
                            'itemid_in' => null,
                            'itemid_out' => null
                        ];
                    }

                    // Processa o valor baseado no tipo de item
                    if (strpos($item['key_'], 'ifHCInOctets') !== false) {
                        $interfaces[$index]['in'] = $this->formatTraffic($item['lastvalue']);
                        $interfaces[$index]['itemid_in'] = $item['itemid'];
                        if ($item['lastvalue'] > 0) {
                            $interfaces[$index]['used'] = 'used';
                        }
                    }
                    elseif (strpos($item['key_'], 'ifHCOutOctets') !== false) {
                        $interfaces[$index]['out'] = $this->formatTraffic($item['lastvalue']);
                        $interfaces[$index]['itemid_out'] = $item['itemid'];
                        if ($item['lastvalue'] > 0) {
                            $interfaces[$index]['used'] = 'used';
                        }
                    }
                    elseif (strpos($item['key_'], 'ifOperStatus') !== false) {
                        $interfaces[$index]['status'] = $item['lastvalue'] == 1 ? 'up' : 'down';
                    }
                }
            }

            // Ordena por índice
            ksort($interfaces);
            
            return array_values($interfaces);
        } catch (Exception $e) {
            error_log('Error in getInterfacesInfo: ' . $e->getMessage());
            return [];
        }
    }

    protected function formatTraffic($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return number_format($bytes, 2) . ' ' . $units[$pow] . '/s';
    }

    protected function getActiveAlerts($hostid) {
        if (empty($hostid)) {
            return [];
        }

        try {
            $triggers = API::Trigger()->get([
                'output' => ['triggerid', 'description', 'priority'],
                'hostids' => [$hostid],
                'filter' => [
                    'value' => TRIGGER_VALUE_TRUE,
                    'status' => TRIGGER_STATUS_ENABLED
                ],
                'sortfield' => 'priority',
                'sortorder' => 'DESC'
            ]);

            // Agrupar por severidade usando as constantes do Zabbix
            $alerts_by_severity = [
                TRIGGER_SEVERITY_NOT_CLASSIFIED => 0,
                TRIGGER_SEVERITY_INFORMATION => 0,
                TRIGGER_SEVERITY_WARNING => 0,
                TRIGGER_SEVERITY_AVERAGE => 0,
                TRIGGER_SEVERITY_HIGH => 0,
                TRIGGER_SEVERITY_DISASTER => 0
            ];

            foreach ($triggers as $trigger) {
                $alerts_by_severity[$trigger['priority']]++;
            }

            return [
                'total' => count($triggers),
                'by_severity' => $alerts_by_severity
            ];
        } catch (Exception $e) {
            error_log('Error in getActiveAlerts: ' . $e->getMessage());
            return [
                'total' => 0,
                'by_severity' => []
            ];
        }
    }

    protected function formatUptime($seconds) {
        if (!$seconds) return 'N/A';

        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $parts = [];
        if ($days > 0) $parts[] = $days . 'd';
        if ($hours > 0) $parts[] = $hours . 'h';
        if ($minutes > 0) $parts[] = $minutes . 'm';

        return implode(' ', $parts);
    }
} 