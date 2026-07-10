<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/zabbix_api.php';

header('Content-Type: application/json');

require_once __DIR__ . '/../src/permissions_helper.php';

if (!current_user_id() || !has_module_access('portmapping')) {
    echo json_encode(['success' => false, 'error' => 'No session or unauthorized']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$pdo = getPDO();

switch ($action) {
    case 'get_hierarchy':
        $category_name = $_GET['category'] ?? '';
        $parent_id = $_GET['parent_id'] ?? null;
        
        if (empty($category_name)) {
            echo json_encode(['success' => false, 'error' => 'Category is required']);
            break;
        }
        
        $sql = "SELECT i.id, i.hostname as name 
                FROM ci_instances i 
                JOIN ci_categories c ON i.category_id = c.id 
                WHERE c.name = :cat";
        $params = [':cat' => $category_name];

        if ($parent_id) {
            $sql .= " AND i.id IN (SELECT source_id FROM ci_relationships WHERE target_id = :parent_id AND source_type = 'ci_instances' AND target_type = 'ci_instances')";
            $params[':parent_id'] = $parent_id;
        }
        
        $sql .= " ORDER BY i.hostname ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $results]);
        break;

    case 'get_devices':
        $room_id = $_GET['room_id'] ?? null;
        $arch_type = $_GET['arch_type'] ?? '';
        
        // Simplified category filter based on arch_type
        // For 'pasivo_activo': We could return Patch Panels AND Switches and let the UI filter, or just return all devices in the room.
        $sql = "SELECT i.id, i.hostname as name, c.name as category_name
                FROM ci_instances i
                JOIN ci_categories c ON i.category_id = c.id
                WHERE 1=1";
                
        $params = [];
        if ($room_id) {
            $sql .= " AND i.id IN (SELECT source_id FROM ci_relationships WHERE target_id = :room_id AND relation_type='ubicado_en')";
            $params[':room_id'] = $room_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $devices]);
        break;

    case 'get_device_ports':
        $device_id = $_GET['device_id'] ?? null;
        if (!$device_id) {
            echo json_encode(['success' => false, 'error' => 'Device ID required']);
            break;
        }
        
        // 1. Get device details to see if monitored
        $stmt = $pdo->prepare("SELECT hostname, zabbix_host_id FROM ci_instances WHERE id = ?");
        $stmt->execute([$device_id]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$device) {
            echo json_encode(['success' => false, 'error' => 'Device not found']);
            break;
        }

        // 2. Load ports from ci_components
        $sql = "SELECT c.id, c.name, c.attributes_json,
                (SELECT COUNT(*) FROM port_mappings WHERE source_component_id = c.id OR target_component_id = c.id) as is_mapped
                FROM ci_components c 
                WHERE c.parent_ci_id = ? 
                ORDER BY c.name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$device_id]);
        $cmdb_ports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $ports_by_name = [];
        foreach ($cmdb_ports as $p) {
            $ports_by_name[$p['name']] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'is_mapped' => ($p['is_mapped'] > 0)
            ];
        }

        // 3. If monitored, load from host_interfaces cache
        $zabbix_ports = [];
        if (!empty($device['zabbix_host_id'])) {
            $stmt = $pdo->prepare("SELECT interface_name FROM host_interfaces WHERE hostid = ? ORDER BY interface_name ASC");
            $stmt->execute([$device['zabbix_host_id']]);
            $zabbix_ports = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        // Merge them
        $final_ports = [];
        // Add Zabbix ports first
        foreach ($zabbix_ports as $iname) {
            if (isset($ports_by_name[$iname])) {
                $final_ports[] = $ports_by_name[$iname];
                unset($ports_by_name[$iname]);
            } else {
                $final_ports[] = [
                    'id' => null,
                    'name' => $iname,
                    'is_mapped' => false
                ];
            }
        }
        // Add any remaining CMDB-only ports
        foreach ($ports_by_name as $p) {
            $final_ports[] = $p;
        }

        echo json_encode(['success' => true, 'data' => $final_ports]);
        break;
        
    case 'create_port':
        // Explicit CMDB port creation
        $device_id = $_POST['device_id'] ?? null;
        $port_name = $_POST['port_name'] ?? '';
        
        if (!$device_id || !$port_name) {
            echo json_encode(['success' => false, 'error' => 'Device ID and Port Name are required']);
            break;
        }
        
        // Check if port already exists
        $stmt = $pdo->prepare("SELECT id FROM ci_components WHERE parent_ci_id = ? AND name = ?");
        $stmt->execute([$device_id, $port_name]);
        if($stmt->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'El puerto ya existe en este equipo']);
            break;
        }
        
        $attr = json_encode(['created_via' => 'portmapping_wizard', 'created_by' => current_user_id()]);
        $stmt = $pdo->prepare("INSERT INTO ci_components (parent_ci_id, name, attributes_json) VALUES (?, ?, ?)");
        if($stmt->execute([$device_id, $port_name, $attr])) {
            echo json_encode(['success' => true, 'port_id' => $pdo->lastInsertId()]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error insertando en BD']);
        }
        break;

    case 'save_portmapping':
        $source_port_id = $_POST['source_port_id'] ?? null;
        $target_port_id = $_POST['target_port_id'] ?? null;
        $cable_type = $_POST['cable_type'] ?? 'UTP Cat6A';
        $color_code = $_POST['color_code'] ?? '#0000FF';
        
        if (!$source_port_id || !$target_port_id) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            break;
        }
        
        // Verify they aren't already mapped
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM port_mappings WHERE source_component_id IN (?,?) OR target_component_id IN (?,?)");
        $stmt->execute([$source_port_id, $target_port_id, $source_port_id, $target_port_id]);
        if($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'Uno de los puertos ya está en uso. Verifique la CMDB.']);
            break;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO port_mappings (source_component_id, target_component_id, cable_type, color_code) VALUES (?, ?, ?, ?)");
            $stmt->execute([$source_port_id, $target_port_id, $cable_type, $color_code]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_mappings':
        $sql = "SELECT pm.id, 
                       c1.name as source_port, i1.hostname as source_device,
                       c2.name as target_port, i2.hostname as target_device,
                       pm.cable_type, pm.color_code, pm.created_at,
                       c2.id as target_component_id,
                       pm.notes,
                       COALESCE(pm.connection_type, 'network') as connection_type
                FROM port_mappings pm
                JOIN ci_components c1 ON pm.source_component_id = c1.id
                JOIN ci_instances i1 ON c1.parent_ci_id = i1.id
                JOIN ci_components c2 ON pm.target_component_id = c2.id
                JOIN ci_instances i2 ON c2.parent_ci_id = i2.id
                ORDER BY pm.created_at DESC";
        $stmt = $pdo->query($sql);
        $mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $mappings]);
        break;

    case 'get_hybrid_port_data':
        $port_component_id = $_GET['port_id'] ?? null;
        if (!$port_component_id) {
            echo json_encode(['success' => false, 'error' => 'Missing port_id']);
            break;
        }
        
        $stmt = $pdo->prepare("SELECT c.name as port_name, i.zabbix_host_id, i.hostname as switch_name 
                               FROM ci_components c 
                               JOIN ci_instances i ON c.parent_ci_id = i.id 
                               WHERE c.id = ?");
        $stmt->execute([$port_component_id]);
        $cmdb_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cmdb_data) {
            echo json_encode(['success' => false, 'error' => 'Port not found in CMDB']);
            break;
        }

        $response = ['cmdb_inventory' => $cmdb_data, 'zabbix_telemetry' => null];

        if (!empty($cmdb_data['zabbix_host_id'])) {
            $zbx_items = call_zabbix_api('item.get', [
                'hostids' => $cmdb_data['zabbix_host_id'],
                'search' => ['key_' => '*[' . $cmdb_data['port_name'] . ']'],
                'searchWildcardsEnabled' => true,
                'output' => ['name', 'key_', 'lastvalue', 'units']
            ]);
            
            if (!isset($zbx_items['error']) && !empty($zbx_items['result'])) {
                $telemetry = ['status' => 'DOWN', 'traffic_in' => 0, 'traffic_out' => 0];
                foreach ($zbx_items['result'] as $item) {
                    if (stripos($item['key_'], 'OperStatus') !== false) {
                        $telemetry['status'] = ($item['lastvalue'] == 1) ? 'UP' : 'DOWN';
                    }
                    if (stripos($item['key_'], 'InOctets') !== false) {
                        $telemetry['traffic_in'] = $item['lastvalue'];
                    }
                    if (stripos($item['key_'], 'OutOctets') !== false) {
                        $telemetry['traffic_out'] = $item['lastvalue'];
                    }
                }
                $response['zabbix_telemetry'] = $telemetry;
            }
        }
        
        echo json_encode(['success' => true, 'data' => $response]);
        break;

    case 'get_all_devices':
        try {
            $sql = "SELECT i.id, i.hostname as name, i.zabbix_host_id, c.name as category_name 
                    FROM ci_instances i 
                    JOIN ci_categories c ON i.category_id = c.id 
                    ORDER BY i.hostname ASC";
            $stmt = $pdo->query($sql);
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $devices]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_device_ports_and_connections':
        $device_id = $_GET['device_id'] ?? null;
        if (!$device_id) {
            echo json_encode(['success' => false, 'error' => 'Missing device_id']);
            break;
        }

        try {
            // Get device details
            $stmt = $pdo->prepare("SELECT hostname, zabbix_host_id FROM ci_instances WHERE id = ?");
            $stmt->execute([$device_id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$device) {
                echo json_encode(['success' => false, 'error' => 'Device not found']);
                break;
            }

            // Fetch current CMDB components and their mappings
            $sql = "SELECT 
                        c.id as component_id,
                        c.name as port_name,
                        c.attributes_json,
                        pm.id as mapping_id,
                        pm.cable_type,
                        pm.color_code,
                        pm.notes,
                        COALESCE(pm.connection_type, 'network') as connection_type,
                        CASE WHEN pm.source_component_id = c.id THEN c_tgt.id ELSE c_src.id END as dest_port_id,
                        CASE WHEN pm.source_component_id = c.id THEN c_tgt.name ELSE c_src.name END as dest_port_name,
                        CASE WHEN pm.source_component_id = c.id THEN i_tgt.id ELSE i_src.id END as dest_device_id,
                        CASE WHEN pm.source_component_id = c.id THEN i_tgt.hostname ELSE i_src.hostname END as dest_device_name
                    FROM ci_components c
                    LEFT JOIN port_mappings pm ON (pm.source_component_id = c.id OR pm.target_component_id = c.id)
                    LEFT JOIN ci_components c_src ON pm.source_component_id = c_src.id
                    LEFT JOIN ci_instances i_src ON c_src.parent_ci_id = i_src.id
                    LEFT JOIN ci_components c_tgt ON pm.target_component_id = c_tgt.id
                    LEFT JOIN ci_instances i_tgt ON c_tgt.parent_ci_id = i_tgt.id
                    WHERE c.parent_ci_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$device_id]);
            $cmdb_ports = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Parse attributes_json
            $ports_by_name = [];
            foreach ($cmdb_ports as $p) {
                $attrs = json_decode($p['attributes_json'], true) ?: [];
                $p['connection_type'] = $attrs['connection_type'] ?? $p['connection_type'] ?? 'network';
                $ports_by_name[$p['port_name']] = $p;
            }

            $zabbix_interfaces = [];
            if (!empty($device['zabbix_host_id'])) {
                // Fetch interfaces from host_interfaces local cache table
                $stmt = $pdo->prepare("SELECT * FROM host_interfaces WHERE hostid = ? ORDER BY interface_type ASC, interface_name ASC");
                $stmt->execute([$device['zabbix_host_id']]);
                $zabbix_interfaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Merge Zabbix interfaces
            $final_network_ports = [];
            $final_power_ports = [];

            // Add Zabbix ports first to preserve Zabbix ordering
            $processed_names = [];
            foreach ($zabbix_interfaces as $zi) {
                $iname = $zi['interface_name'];
                $processed_names[$iname] = true;

                $port_data = [
                    'component_id' => null,
                    'port_name' => $iname,
                    'mapping_id' => null,
                    'cable_type' => 'UTP Cat6A',
                    'color_code' => '#0000FF',
                    'notes' => '',
                    'connection_type' => 'network',
                    'dest_port_id' => null,
                    'dest_port_name' => '',
                    'dest_device_id' => null,
                    'dest_device_name' => '',
                    'status' => $zi['status'] ?? 'Unknown',
                    'vlan' => $zi['vlan'] ?? '',
                    'alias' => $zi['alias'] ?? '',
                    'bits_received' => $zi['bits_received'] ?? 0,
                    'bits_sent' => $zi['bits_sent'] ?? 0,
                    'is_zabbix' => true
                ];

                if (isset($ports_by_name[$iname])) {
                    $cmdb_p = $ports_by_name[$iname];
                    $port_data['component_id'] = $cmdb_p['component_id'];
                    $port_data['mapping_id'] = $cmdb_p['mapping_id'];
                    $port_data['cable_type'] = $cmdb_p['cable_type'] ?: 'UTP Cat6A';
                    $port_data['color_code'] = $cmdb_p['color_code'] ?: '#0000FF';
                    $port_data['notes'] = $cmdb_p['notes'] ?: '';
                    $port_data['dest_port_id'] = $cmdb_p['dest_port_id'];
                    $port_data['dest_port_name'] = $cmdb_p['dest_port_name'] ?: '';
                    $port_data['dest_device_id'] = $cmdb_p['dest_device_id'];
                    $port_data['dest_device_name'] = $cmdb_p['dest_device_name'] ?: '';
                }

                $final_network_ports[] = $port_data;
            }

            // Add remaining CMDB components that are not in Zabbix
            foreach ($cmdb_ports as $p) {
                if (isset($processed_names[$p['port_name']])) {
                    continue;
                }

                $port_data = [
                    'component_id' => $p['component_id'],
                    'port_name' => $p['port_name'],
                    'mapping_id' => $p['mapping_id'],
                    'cable_type' => $p['cable_type'] ?: 'UTP Cat6A',
                    'color_code' => $p['color_code'] ?: '#0000FF',
                    'notes' => $p['notes'] ?: '',
                    'connection_type' => $p['connection_type'],
                    'dest_port_id' => $p['dest_port_id'],
                    'dest_port_name' => $p['dest_port_name'] ?: '',
                    'dest_device_id' => $p['dest_device_id'],
                    'dest_device_name' => $p['dest_device_name'] ?: '',
                    'status' => 'Active', // Default for manual
                    'vlan' => '',
                    'alias' => '',
                    'bits_received' => 0,
                    'bits_sent' => 0,
                    'is_zabbix' => false
                ];

                if ($p['connection_type'] === 'power') {
                    $final_power_ports[] = $port_data;
                } else {
                    $final_network_ports[] = $port_data;
                }
            }

            echo json_encode([
                'success' => true,
                'device' => $device,
                'network_ports' => $final_network_ports,
                'power_ports' => $final_power_ports
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'save_device_connection':
        $device_id = $_POST['device_id'] ?? null;
        $port_name = $_POST['port_name'] ?? null;
        $connection_type = $_POST['connection_type'] ?? 'network';
        $dest_device_id = $_POST['dest_device_id'] ?? null;
        $dest_port_name = $_POST['dest_port_name'] ?? null;
        $cable_type = $_POST['cable_type'] ?? 'UTP Cat6A';
        $color_code = $_POST['color_code'] ?? '#0000FF';
        $notes = $_POST['notes'] ?? '';

        if (!$device_id || !$port_name || !$dest_device_id || !$dest_port_name) {
            echo json_encode(['success' => false, 'error' => 'Parámetros incompletos. Todos los campos de los extremos son obligatorios.']);
            break;
        }

        try {
            $pdo->beginTransaction();

            // Check if dest_device_id is a manual device name
            if (!is_numeric($dest_device_id)) {
                $dest_device_name_str = trim($dest_device_id);
                if (empty($dest_device_name_str)) {
                    echo json_encode(['success' => false, 'error' => 'El nombre del equipo destino no puede estar vacío.']);
                    break;
                }
                
                // Find if a device with this hostname already exists
                $stmt = $pdo->prepare("SELECT id FROM ci_instances WHERE hostname = ?");
                $stmt->execute([$dest_device_name_str]);
                $found_id = $stmt->fetchColumn();
                
                if ($found_id) {
                    $dest_device_id = $found_id;
                } else {
                    // Create new manual device
                    $stmt = $pdo->prepare("SELECT category_id FROM ci_instances WHERE id = ?");
                    $stmt->execute([$device_id]);
                    $src_cat_id = $stmt->fetchColumn() ?: 39;
                    
                    $stmt = $pdo->prepare("INSERT INTO ci_instances (category_id, hostname, source, status) VALUES (?, ?, 'manual', 'Activo')");
                    $stmt->execute([$src_cat_id, $dest_device_name_str]);
                    $dest_device_id = $pdo->lastInsertId();
                }
            }

            // 1. Find or create source component
            $stmt = $pdo->prepare("SELECT id FROM ci_components WHERE parent_ci_id = ? AND name = ?");
            $stmt->execute([$device_id, $port_name]);
            $src_id = $stmt->fetchColumn();

            if (!$src_id) {
                $attr = json_encode(['connection_type' => $connection_type, 'created_via' => 'portmapping_manager']);
                $stmt = $pdo->prepare("INSERT INTO ci_components (parent_ci_id, name, attributes_json) VALUES (?, ?, ?)");
                $stmt->execute([$device_id, $port_name, $attr]);
                $src_id = $pdo->lastInsertId();
            } else {
                // Update component connection_type if needed
                $stmt = $pdo->prepare("SELECT attributes_json FROM ci_components WHERE id = ?");
                $stmt->execute([$src_id]);
                $old_attr = json_decode($stmt->fetchColumn(), true) ?: [];
                if (!isset($old_attr['connection_type']) || $old_attr['connection_type'] !== $connection_type) {
                    $old_attr['connection_type'] = $connection_type;
                    $stmt = $pdo->prepare("UPDATE ci_components SET attributes_json = ? WHERE id = ?");
                    $stmt->execute([json_encode($old_attr), $src_id]);
                }
            }

            // 2. Find or create destination component
            $stmt = $pdo->prepare("SELECT id FROM ci_components WHERE parent_ci_id = ? AND name = ?");
            $stmt->execute([$dest_device_id, $dest_port_name]);
            $dest_id = $stmt->fetchColumn();

            if (!$dest_id) {
                $attr = json_encode(['connection_type' => $connection_type, 'created_via' => 'portmapping_manager']);
                $stmt = $pdo->prepare("INSERT INTO ci_components (parent_ci_id, name, attributes_json) VALUES (?, ?, ?)");
                $stmt->execute([$dest_device_id, $dest_port_name, $attr]);
                $dest_id = $pdo->lastInsertId();
            } else {
                // Update component connection_type if needed
                $stmt = $pdo->prepare("SELECT attributes_json FROM ci_components WHERE id = ?");
                $stmt->execute([$dest_id]);
                $old_attr = json_decode($stmt->fetchColumn(), true) ?: [];
                if (!isset($old_attr['connection_type']) || $old_attr['connection_type'] !== $connection_type) {
                    $old_attr['connection_type'] = $connection_type;
                    $stmt = $pdo->prepare("UPDATE ci_components SET attributes_json = ? WHERE id = ?");
                    $stmt->execute([json_encode($old_attr), $dest_id]);
                }
            }

            // 3. Delete any existing mapping for these components (to prevent duplicates or loops)
            $stmt = $pdo->prepare("DELETE FROM port_mappings WHERE source_component_id IN (?, ?) OR target_component_id IN (?, ?)");
            $stmt->execute([$src_id, $dest_id, $src_id, $dest_id]);

            // 4. Insert new mapping
            $stmt = $pdo->prepare("INSERT INTO port_mappings (source_component_id, target_component_id, cable_type, color_code, notes, connection_type) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$src_id, $dest_id, $cable_type, $color_code, $notes, $connection_type]);

            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'delete_device_connection':
        $mapping_id = $_POST['mapping_id'] ?? null;
        if (!$mapping_id) {
            echo json_encode(['success' => false, 'error' => 'Missing mapping_id']);
            break;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM port_mappings WHERE id = ?");
            $success = $stmt->execute([$mapping_id]);
            echo json_encode(['success' => $success]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'create_manual_port':
        $device_id = $_POST['device_id'] ?? null;
        $port_name = $_POST['port_name'] ?? null;
        $connection_type = $_POST['connection_type'] ?? 'network';

        if (!$device_id || !$port_name) {
            echo json_encode(['success' => false, 'error' => 'Faltan parámetros']);
            break;
        }

        try {
            // Check if it already exists
            $stmt = $pdo->prepare("SELECT id FROM ci_components WHERE parent_ci_id = ? AND name = ?");
            $stmt->execute([$device_id, $port_name]);
            if ($stmt->fetchColumn()) {
                echo json_encode(['success' => false, 'error' => 'El puerto/conexión ya existe']);
                break;
            }

            $attr = json_encode(['connection_type' => $connection_type, 'created_via' => 'portmapping_manual']);
            $stmt = $pdo->prepare("INSERT INTO ci_components (parent_ci_id, name, attributes_json) VALUES (?, ?, ?)");
            $success = $stmt->execute([$device_id, $port_name, $attr]);
            echo json_encode(['success' => $success, 'port_id' => $pdo->lastInsertId()]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
