<?php
require_once 'config.php';

class ZabbixAPI {
    private $url;
    private $token;

    public function __construct($url = ZABBIX_API_URL, $token = ZABBIX_API_TOKEN) {
        $this->url = $url;
        $this->token = $token;
    }

    public function request($method, $params = []) {
        $payload = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => time()
        ];

        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("CURL Error: " . $error);
        }
        curl_close($ch);

        $result = json_decode($response, true);
        if (isset($result['error'])) {
            throw new Exception("Zabbix API Error: " . $result['error']['message'] . " - " . $result['error']['data']);
        }

        return $result['result'] ?? [];
    }

    public function getHostsByInventory($inventoryValue, $inventoryField = 'location') {
        return $this->request('host.get', [
            'output' => ['hostid', 'host', 'name'],
            'selectInventory' => ['location', 'location_lat', 'location_lon'],
            'searchInventory' => [$inventoryField => $inventoryValue],
            'selectItems' => ['itemid', 'name', 'key_', 'lastvalue', 'units']
        ]);
    }

    public function getItemsByHost($hostId, $searchKey = '') {
        $params = [
            'output' => ['itemid', 'name', 'key_', 'lastvalue', 'lastclock', 'units'],
            'hostids' => $hostId
        ];
        if ($searchKey) {
            $params['search'] = ['key_' => $searchKey];
        }
        return $this->request('item.get', $params);
    }
}
?>
