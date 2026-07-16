<?php
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '50M');
require_once 'zabbix_api.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'topology';
$zabbix = new ZabbixAPI();

try {
    switch ($action) {
        case 'topology':
            echo json_encode(get_topology($zabbix));
            break;
        case 'trajectory':
            $clientId = $_GET['clientId'] ?? '';
            echo json_encode(get_trajectory($zabbix, $clientId));
            break;
        case 'heatmap':
            echo json_encode(get_heatmap($zabbix));
            break;
        case 'get_floorplans':
            echo json_encode(['success' => true, 'floorplans' => get_floorplans()]);
            break;
        case 'save_floorplan':
            echo json_encode(save_floorplan());
            break;
        case 'delete_floorplan':
            $id = $_GET['id'] ?? null;
            echo json_encode(delete_floorplan($id));
            break;
        case 'get_ap_positions':
            echo json_encode(['success' => true, 'positions' => get_ap_positions()]);
            break;
        case 'save_all_ap_positions':
            echo json_encode(save_all_ap_positions());
            break;
        case 'upload_image':
            echo json_encode(upload_image());
            break;
        default:
            throw new Exception("Invalid action: $action");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function get_db() {
    $dbFile = __DIR__ . '/../data/wireless_config.db';
    $db = new PDO("sqlite:$dbFile");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA foreign_keys = ON;");
    
    // Create floorplans table with model_json
    $db->exec("CREATE TABLE IF NOT EXISTS floorplans (
        id TEXT PRIMARY KEY,
        name TEXT UNIQUE,
        scale REAL DEFAULT 1.0,
        image_url TEXT,
        grid_meters REAL DEFAULT 5,
        ap_size INTEGER DEFAULT 12,
        model_json TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Compatibility check for old DBs
    try { $db->exec("ALTER TABLE floorplans ADD COLUMN model_json TEXT"); } catch(Exception $e) {}

    // Create ap_positions table
    $db->exec("CREATE TABLE IF NOT EXISTS ap_positions (
        ap_ip TEXT,
        floor_id TEXT,
        x REAL,
        y REAL,
        PRIMARY KEY (ap_ip, floor_id),
        FOREIGN KEY (floor_id) REFERENCES floorplans(id) ON DELETE CASCADE
    )");

    return $db;
}

function get_floorplans() {
    $db = get_db();
    $stmt = $db->query("SELECT * FROM floorplans ORDER BY name ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function save_floorplan() {
    $db = get_db();
    $name = $_POST['name'] ?? 'Nuevo Piso';
    $scale = floatval($_POST['scale'] ?? 1.0);
    $id = $_POST['id'] ?? '';
    $grid_meters = floatval($_POST['grid_meters'] ?? 5);
    $ap_size = intval($_POST['ap_size'] ?? 12);
    $model_json = $_POST['model_json'] ?? '';

    if (empty($id)) {
        $findStmt = $db->prepare("SELECT id FROM floorplans WHERE name = ?");
        $findStmt->execute([$name]);
        $res = $findStmt->fetch();
        if ($res) {
            $id = $res['id'];
        } else {
            $id = uniqid();
        }
    }

    $imageUrl = '';
    $imageDebug = 'no_file_in_request';

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $filename = "floor_{$id}.{$ext}";
        $destDir = __DIR__ . '/../imagen';
        $path = "$destDir/$filename";
        
        // Ensure directory exists
        if (!is_dir($destDir)) {
            mkdir($destDir, 0777, true);
        }
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $path)) {
            chmod($path, 0666);
            $imageUrl = "imagen/$filename";
            $imageDebug = "saved_to_$imageUrl";
        } else {
            $imageDebug = 'move_failed:' . $_FILES['image']['tmp_name'] . '->' . $path;
        }
    } else {
        // Preserve existing image_url if no new file uploaded
        $imageUrl = $_POST['image_url'] ?? '';
        $imageDebug = 'no_upload_using_post:' . $imageUrl;
        
        // If still empty, try to get existing from DB
        if (empty($imageUrl)) {
            $existing = $db->prepare("SELECT image_url FROM floorplans WHERE id = ?");
            $existing->execute([$id]);
            $row = $existing->fetch();
            if ($row && !empty($row['image_url'])) {
                $imageUrl = $row['image_url'];
                $imageDebug = 'preserved_from_db:' . $imageUrl;
            }
        }
    }

    $stmt = $db->prepare("INSERT OR REPLACE INTO floorplans (id, name, scale, image_url, grid_meters, ap_size, model_json, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
    $stmt->execute([$id, $name, $scale, $imageUrl, $grid_meters, $ap_size, $model_json]);
    
    return ['success' => true, 'id' => $id, 'image_url' => $imageUrl, 'debug' => $imageDebug];
}

function save_all_ap_positions() {
    $db = get_db();
    $floorId = $_POST['floor_id'] ?? null;
    $positionsJson = $_POST['positions'] ?? '[]';
    $positions = json_decode($positionsJson, true);

    if (!$floorId) return ['success' => false, 'error' => 'Floor ID required'];

    try {
        $db->beginTransaction();
        $delStmt = $db->prepare("DELETE FROM ap_positions WHERE floor_id = ?");
        $delStmt->execute([$floorId]);
        
        if (!empty($positions)) {
            $insStmt = $db->prepare("INSERT INTO ap_positions (ap_ip, floor_id, x, y) VALUES (?, ?, ?, ?)");
            foreach ($positions as $pos) {
                $insStmt->execute([$pos['ap_ip'], $floorId, floatval($pos['x']), floatval($pos['y'])]);
            }
        }
        $db->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function get_ap_positions() {
    $db = get_db();
    $stmt = $db->query("SELECT * FROM ap_positions");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function delete_floorplan($id) {
    if (!$id) return ['success' => false, 'error' => 'ID required'];
    $db = get_db();
    $stmt = $db->prepare("DELETE FROM floorplans WHERE id = ?");
    $stmt->execute([$id]);
    return ['success' => true];
}

// Zabbix Data Functions
function get_topology($zabbix) {
    // Search for items across all hosts to get more APs/Clients
    $apItems = $zabbix->request('item.get', [
        'output' => ['itemid', 'name', 'lastvalue', 'lastclock', 'hostid'],
        'search' => ['key_' => 'aruba.ap.'],
        'searchByAny' => true
    ]);

    $clientItems = $zabbix->request('item.get', [
        'output' => ['itemid', 'name', 'lastvalue', 'lastclock', 'hostid'],
        'search' => ['key_' => 'aruba.client.'],
        'searchByAny' => true
    ]);

    $aps = process_ap_data($apItems);
    $clients = process_client_data($clientItems);

    $now = time();
    $onlineAps = array_filter($aps, function($ap) use ($now) {
        $maxClock = 0;
        if (isset($ap['metrics'])) {
            foreach ($ap['metrics'] as $m) $maxClock = max($maxClock, $m['lastclock'] ?? 0);
        }
        return ($now - $maxClock) < 3600; // Increased to 1h for testing
    });

    $onlineClients = array_filter($clients, function($c) use ($now) {
        return ($now - ($c['lastclock'] ?? 0)) < 3600;
    });

    return [
        'success' => true,
        'aps' => array_values($onlineAps),
        'clients' => array_values($onlineClients)
    ];
}


function get_trajectory($zabbix, $clientId) {
    if (!$clientId) throw new Exception("Client ID required");
    
    // Normalize MAC address format to match space-separated keys in Zabbix item names
    $searchKey = trim($clientId);
    if (preg_match('/^[0-9A-Fa-f]{2}([:\-][0-9A-Fa-f]{2}){5}$/', $searchKey)) {
        $searchKey = str_replace([':', '-'], ' ', $searchKey);
    }
    
    $startTime = isset($_GET['startTime']) ? intval($_GET['startTime']) : (time() - 28800);
    $duration = isset($_GET['duration']) ? intval($_GET['duration']) : 28800;
    $endTime = $startTime + $duration;

    // Search for all items related to this client
    $items = $zabbix->request('item.get', [
        'output' => ['itemid', 'name', 'value_type', 'lastvalue', 'lastclock'],
        'search' => ['name' => $searchKey],
        'searchByAny' => true,
        'sortfield' => 'name'
    ]);

    $snrItemId = null;
    $apItemId = null;
    
    foreach ($items as $item) {
        if (stripos($item['name'], 'ClientSNR') !== false) {
            $snrItemId = $item['itemid'];
        }
        if (stripos($item['name'], 'ClientAPIPAddress') !== false) {
            $apItemId = $item['itemid'];
        }
    }

    $apHistory = [];
    $snrHistory = [];

    // Fetch SNR history (numeric - value_type 0 or 3)
    if ($snrItemId) {
        $snrData = $zabbix->request('history.get', [
            'output' => ['clock', 'value'],
            'itemids' => [$snrItemId],
            'time_from' => $startTime,
            'time_till' => $endTime,
            'sortfield' => 'clock',
            'sortorder' => 'ASC',
            'history' => 3, // unsigned int
            'limit' => 500
        ]);
        // If empty, try float type
        if (empty($snrData)) {
            $snrData = $zabbix->request('history.get', [
                'output' => ['clock', 'value'],
                'itemids' => [$snrItemId],
                'time_from' => $startTime,
                'time_till' => $endTime,
                'sortfield' => 'clock',
                'sortorder' => 'ASC',
                'history' => 0,
                'limit' => 500
            ]);
        }
        $snrHistory = $snrData ?: [];
    }

    // Fetch AP IP history (text - value_type 1 or 4)
    if ($apItemId) {
        $apData = $zabbix->request('history.get', [
            'output' => ['clock', 'value'],
            'itemids' => [$apItemId],
            'time_from' => $startTime,
            'time_till' => $endTime,
            'sortfield' => 'clock',
            'sortorder' => 'ASC', 
            'history' => 1, // string
            'limit' => 500
        ]);
        if (empty($apData)) {
            $apData = $zabbix->request('history.get', [
                'output' => ['clock', 'value'],
                'itemids' => [$apItemId],
                'time_from' => $startTime,
                'time_till' => $endTime,
                'sortfield' => 'clock',
                'sortorder' => 'ASC',
                'history' => 4, // text
                'limit' => 500
            ]);
        }
        $apHistory = $apData ?: [];
    }

    return [
        'success' => true, 
        'clientId' => $clientId, 
        'apHistory' => $apHistory, 
        'snrHistory' => $snrHistory,
        'debug' => [
            'snrItemId' => $snrItemId,
            'apItemId' => $apItemId,
            'totalItems' => count($items),
            'snrPoints' => count($snrHistory),
            'apPoints' => count($apHistory)
        ]
    ];
}

function get_heatmap($zabbix) {
    $topology = get_topology($zabbix);
    $densityMap = []; // Placeholder
    return ['success' => true, 'density' => $densityMap];
}

function process_ap_data($apItems) {
    $processed = [];
    foreach ($apItems as $item) {
        if (preg_match('/^(.+?)\s*:\s*(.+)$/', $item['name'], $matches)) {
            $apInfo = trim($matches[1]);
            $metric = trim($matches[2]);
            $mac = '';
            if (preg_match('/([0-9A-Fa-f]{2}(?:\s+[0-9A-Fa-f]{2}){5})/', $apInfo, $macMatch)) {
                 $mac = strtoupper(str_replace([' ', '-'], ':', trim($macMatch[1])));
            }
            if (!$mac) continue;
            if (!isset($processed[$mac])) {
                $processed[$mac] = ['mac' => $mac, 'name' => 'AP-' . $mac, 'ip' => 'N/A', 'metrics' => []];
            }
            if (preg_match('/_([^_]+)_([0-9\.]+)/', $apInfo, $extraMatches)) {
                if ($processed[$mac]['name'] === 'AP-' . $mac) {
                    $processed[$mac]['name'] = trim($extraMatches[1]);
                }
                if ($processed[$mac]['ip'] === 'N/A') {
                    $processed[$mac]['ip'] = trim($extraMatches[2]);
                }
            }
            if (stripos($metric, 'AP Name') !== false) $processed[$mac]['name'] = $item['lastvalue'];
            elseif (stripos($metric, 'AP IP address') !== false) $processed[$mac]['ip'] = $item['lastvalue'];
            $processed[$mac]['metrics'][$metric] = ['value' => $item['lastvalue'], 'lastclock' => $item['lastclock']];
        }
    }
    return array_values($processed);
}

function process_client_data($clientItems) {
    $processed = [];
    foreach ($clientItems as $item) {
        if (preg_match('/^(.+?)\s*:\s*(.+)$/', $item['name'], $matches)) {
            $clientKey = trim($matches[1]);
            $metric = trim($matches[2]);
            if (!isset($processed[$clientKey])) {
                $processed[$clientKey] = [
                    'key' => $clientKey,
                    'lastclock' => $item['lastclock'],
                    'name' => 'Desconocido',
                    'ip' => 'N/A',
                    'apIP' => '',
                    'snr' => 0,
                    'mac' => 'N/A',
                    'txBytes' => 0,
                    'rxBytes' => 0,
                    'txThroughput' => 0,
                    'rxThroughput' => 0
                ];
            }
            switch (true) {
                case stripos($metric, 'ClientName') !== false: $processed[$clientKey]['name'] = $item['lastvalue']; break;
                case stripos($metric, 'ClientIPAddress') !== false: $processed[$clientKey]['ip'] = $item['lastvalue']; break;
                case stripos($metric, 'ClientAPIPAddress') !== false: $processed[$clientKey]['apIP'] = $item['lastvalue']; break;
                case stripos($metric, 'ClientSNR') !== false: $processed[$clientKey]['snr'] = intval($item['lastvalue']); break;
                case stripos($metric, 'ClientMACAddress') !== false:
                case stripos($metric, 'ClientIPMAddress') !== false:
                    $processed[$clientKey]['mac'] = strtoupper(str_replace([' ', '-'], ':', trim($item['lastvalue'])));
                    break;
                case stripos($metric, 'ClientTxDataBytes') !== false: $processed[$clientKey]['txBytes'] = floatval($item['lastvalue']); break;
                case stripos($metric, 'ClientRxDataBytes') !== false: $processed[$clientKey]['rxBytes'] = floatval($item['lastvalue']); break;
                case stripos($metric, 'ClientTxTroughput') !== false: $processed[$clientKey]['txThroughput'] = floatval($item['lastvalue']); break;
                case stripos($metric, 'ClientRxTroughput') !== false: $processed[$clientKey]['rxThroughput'] = floatval($item['lastvalue']); break;
            }
        }
    }
    return array_values($processed);
}

function upload_image() {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'No image file received. Error: ' . ($_FILES['image']['error'] ?? 'none')];
    }
    
    $destDir = __DIR__ . '/../imagen';
    if (!is_dir($destDir)) {
        mkdir($destDir, 0777, true);
    }
    
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp'];
    if (!in_array($ext, $allowed)) {
        return ['success' => false, 'error' => "Extension '$ext' not allowed"];
    }
    
    $filename = 'floor_' . uniqid() . '.' . $ext;
    $path = "$destDir/$filename";
    
    if (move_uploaded_file($_FILES['image']['tmp_name'], $path)) {
        chmod($path, 0666);
        $imageUrl = "imagen/$filename";
        return ['success' => true, 'image_url' => $imageUrl, 'filename' => $filename];
    }
    
    return ['success' => false, 'error' => 'Failed to move uploaded file'];
}
?>
