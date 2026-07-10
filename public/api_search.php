<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/helpers.php';

header('Content-Type: application/json');

if (!current_user_id()) {
    echo json_encode(['success' => false, 'error' => 'No session']);
    exit;
}

$q = $_GET['q'] ?? '';
$q = trim($q);

if (strlen($q) < 2) {
    echo json_encode(['success' => true, 'results' => [
        'ci_instances' => [],
        'precmdb_equipos' => [],
        'port_mappings' => []
    ]]);
    exit;
}

$pdo = getPDO();
$likeQuery = '%' . $q . '%';

$results = [
    'ci_instances' => [],
    'precmdb_equipos' => [],
    'port_mappings' => []
];

// 1. Search in ci_instances (Graph CMDB)
try {
    $stmt = $pdo->prepare("
        SELECT i.id, i.hostname, i.ip_address, i.ci_unique, i.sigla, i.category_id, i.attributes_json,
               c.name as category_name, c.icon as category_icon,
               r.name as rack_name, rm.name as room_name
        FROM ci_instances i
        JOIN ci_categories c ON i.category_id = c.id
        LEFT JOIN dc_rack_devices rd ON rd.cmdb_reference = i.id
        LEFT JOIN dc_racks r ON rd.rack_id = r.id
        LEFT JOIN dc_rooms rm ON r.room_id = rm.id
        WHERE i.hostname LIKE :q 
           OR i.ip_address LIKE :q 
           OR i.ci_unique LIKE :q 
           OR i.sigla LIKE :q
           OR i.description LIKE :q
        ORDER BY i.hostname ASC
        LIMIT 15
    ");
    $stmt->execute([':q' => $likeQuery]);
    $db_instances = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($db_instances as $item) {
        $locParts = [];
        if (!empty($item['room_name'])) {
            $locParts[] = "Sala: " . $item['room_name'];
        }
        if (!empty($item['rack_name'])) {
            $locParts[] = "Rack: " . $item['rack_name'];
        }
        
        if (empty($locParts)) {
            $attrs = json_decode($item['attributes_json'], true) ?: [];
            foreach (['ubicacion', 'ubicación', 'datacenter', 'sala', 'room', 'cuarto', 'rack', 'site', 'lugar', 'ciudad', 'pais', 'país'] as $locKey) {
                if (!empty($attrs[$locKey])) {
                    $locParts[] = ucfirst($locKey) . ": " . $attrs[$locKey];
                    break;
                }
            }
        }
        $item['location'] = !empty($locParts) ? implode(', ', $locParts) : 'Sin ubicación';
        unset($item['attributes_json']);
        $results['ci_instances'][] = $item;
    }
} catch (Exception $e) {
    // Ignore or log
}

// 2. Search in port_mappings
try {
    $stmt = $pdo->prepare("
        SELECT pm.id, pm.cable_type, pm.color_code, pm.notes, COALESCE(pm.connection_type, 'network') as connection_type,
               c1.name as source_port, i1.hostname as source_device, i1.id as source_device_id,
               c2.name as target_port, i2.hostname as target_device, i2.id as target_device_id,
               r1.name as s_rack, rm1.name as s_room,
               r2.name as t_rack, rm2.name as t_room
        FROM port_mappings pm
        JOIN ci_components c1 ON pm.source_component_id = c1.id
        JOIN ci_instances i1 ON c1.parent_ci_id = i1.id
        LEFT JOIN dc_rack_devices rd1 ON rd1.cmdb_reference = i1.id
        LEFT JOIN dc_racks r1 ON rd1.rack_id = r1.id
        LEFT JOIN dc_rooms rm1 ON r1.room_id = rm1.id
        JOIN ci_components c2 ON pm.target_component_id = c2.id
        JOIN ci_instances i2 ON c2.parent_ci_id = i2.id
        LEFT JOIN dc_rack_devices rd2 ON rd2.cmdb_reference = i2.id
        LEFT JOIN dc_racks r2 ON rd2.rack_id = r2.id
        LEFT JOIN dc_rooms rm2 ON r2.room_id = rm2.id
        WHERE i1.hostname LIKE :q 
           OR i2.hostname LIKE :q 
           OR c1.name LIKE :q 
           OR c2.name LIKE :q 
           OR pm.notes LIKE :q 
           OR pm.cable_type LIKE :q
        ORDER BY pm.created_at DESC
        LIMIT 15
    ");
    $stmt->execute([':q' => $likeQuery]);
    $db_pm = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($db_pm as $item) {
        $locPartsS = [];
        if (!empty($item['s_room'])) $locPartsS[] = $item['s_room'];
        if (!empty($item['s_rack'])) $locPartsS[] = $item['s_rack'];
        $locS = !empty($locPartsS) ? ' (' . implode(', ', $locPartsS) . ')' : '';

        $locPartsT = [];
        if (!empty($item['t_room'])) $locPartsT[] = $item['t_room'];
        if (!empty($item['t_rack'])) $locPartsT[] = $item['t_rack'];
        $locT = !empty($locPartsT) ? ' (' . implode(', ', $locPartsT) . ')' : '';

        $item['location'] = "Origen" . $locS . " ➔ Destino" . $locT;
        $results['port_mappings'][] = $item;
    }
} catch (Exception $e) {
    // Ignore or log
}

// 3. Search in spreadsheet tables (PRECMDB)
try {
    $tables = listSheetTables();
    foreach ($tables as $table) {
        // Skip relational or metadata tables if needed
        if ($table === 'sheet_configs' || $table === 'sheet_relaciones' || $table === 'sheet_history' || $table === 'sheet_distrib_rack') {
            continue;
        }

        // Get columns to build query
        $stmtCols = $pdo->prepare("SHOW COLUMNS FROM `" . $table . "`");
        $stmtCols->execute();
        $cols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);

        $nameField = null;
        $ipField = null;
        $codeField = null;
        $locField = null;

        // Detect name field
        foreach (['nombre', 'hostname', 'visiblename', 'device_name', 'servidor', 'nombre_del_servicio'] as $f) {
            if (in_array($f, $cols)) {
                $nameField = $f;
                break;
            }
        }
        if (!$nameField) {
            foreach ($cols as $c) {
                if ($c !== 'id' && $c !== '_row_hash' && $c !== 'created_at' && $c !== 'updated_at' && $c !== 'estado_actual') {
                    $nameField = $c;
                    break;
                }
            }
            if (!$nameField) $nameField = 'id';
        }

        // Detect IP field
        foreach (['ip', 'direcci_n_ip', 'monitoring_access_ip', 'ip_address', 'ilo'] as $f) {
            if (in_array($f, $cols)) {
                $ipField = $f;
                break;
            }
        }

        // Detect asset code or serial field
        foreach (['asset_code', 'serial', 'serial_number', 'serie', 'excel_id'] as $f) {
            if (in_array($f, $cols)) {
                $codeField = $f;
                break;
            }
        }

        // Detect location field
        foreach (['ubicacion', 'ubicación', 'datacenter', 'sala', 'room', 'cuarto', 'rack', 'site', 'lugar', 'ciudad', 'pais', 'país'] as $f) {
            if (in_array($f, $cols)) {
                $locField = $f;
                break;
            }
        }

        // Build dynamic WHERE clause checking all VARCHAR/TEXT fields for the query
        $whereParts = [];
        foreach ($cols as $col) {
            // Ignore technical/hash fields
            if ($col === 'id' || $col === '_row_hash' || $col === 'zabbix_host_id') continue;
            $whereParts[] = "`$col` LIKE :q";
        }

        if (!empty($whereParts)) {
            $selectFields = ["id", "`$nameField` as display_name"];
            if ($ipField) {
                $selectFields[] = "`$ipField` as ip_address";
            } else {
                $selectFields[] = "NULL as ip_address";
            }
            if ($codeField) {
                $selectFields[] = "`$codeField` as code";
            } else {
                $selectFields[] = "NULL as code";
            }
            if ($locField) {
                $selectFields[] = "`$locField` as location";
            } else {
                $selectFields[] = "NULL as location";
            }

            $sql = "SELECT " . implode(', ', $selectFields) . ", '$table' as table_name 
                    FROM `$table` 
                    WHERE " . implode(' OR ', $whereParts) . " 
                    LIMIT 6";
            
            $stmtSearch = $pdo->prepare($sql);
            $stmtSearch->execute([':q' => $likeQuery]);
            $rows = $stmtSearch->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                // Ensure display_name is not empty
                if (empty(trim($row['display_name'] ?? ''))) {
                    $row['display_name'] = "Registro #" . $row['id'];
                }
                $results['precmdb_equipos'][] = $row;
            }
        }
    }
} catch (Exception $e) {
    // Ignore
}

// Format counts
echo json_encode([
    'success' => true,
    'results' => $results
]);
