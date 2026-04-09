<?php
require_once __DIR__ . '/db.php';

/**
 * Calls the Zabbix API with a given method and parameters.
 *
 * @param string $method The Zabbix API method to call (e.g., 'host.get').
 * @param array $params The parameters for the API method.
 * @return array The decoded JSON response from the API. Returns ['error' => message] on failure.
 */
function call_zabbix_api($method, $params) {
    $ch = curl_init(ZABBIX_API_URL);
    if ($ch === false) {
        return ['error' => 'Failed to initialize cURL.'];
    }

    // Prepare payload. For Zabbix 5.4+ (incl. 7.0), 'auth' should be omitted if using Bearer token.
    $payload = json_encode([
        'jsonrpc' => '2.0',
        'method' => $method,
        'params' => $params,
        'id' => time(),
    ]);

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . ZABBIX_API_TOKEN,
    ];

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // increased timeout to 30s
    
    // Support self-signed certificates common in monitoring setups
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) {
        return ['error' => "cURL Error (Connection Issue): " . $error];
    }
    
    if ($http_code >= 400) {
        return ['error' => "HTTP Error: Received status code " . $http_code . " from " . ZABBIX_API_URL];
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Failed to decode JSON response from Zabbix API. Response was: ' . substr($response, 0, 100)];
    }

    if (isset($decoded['error'])) {
        $msg = $decoded['error']['message'] ?? 'Unknown Error';
        $data = $decoded['error']['data'] ?? '';
        return ['error' => "Zabbix API Error: $msg" . ($data ? " - $data" : "")];
    }
    return $decoded;
}
