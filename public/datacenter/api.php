<?php
/**
 * Datacenter API (AJAX endpoints for Rack Builder)
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$pdo = getPDO();
$action = $_REQUEST['action'] ?? '';

try {
    if ($action === 'get_devices') {
        $rack_id = $_GET['rack_id'] ?? 0;
        $stmt = $pdo->prepare("SELECT * FROM dc_rack_devices WHERE rack_id = ? ORDER BY start_u ASC");
        $stmt->execute([$rack_id]);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decodificar JSON
        foreach ($devices as &$dev) {
            $dev['details'] = json_decode($dev['details_json'], true) ?: [];
            unset($dev['details_json']);
        }
        
        echo json_encode(['success' => true, 'data' => $devices]);
        
    } elseif ($action === 'save_device') {
        $id = $_POST['id'] ?? 0;
        $rack_id = $_POST['rack_id'] ?? 0;
        $name = $_POST['name'] ?? 'Dispositivo Desconocido';
        $start_u = (int)($_POST['start_u'] ?? 1);
        $height_u = (int)($_POST['height_u'] ?? 1);
        $orientation = $_POST['orientation'] ?? 'front';
        $cmdb_ref = $_POST['cmdb_reference'] ?? '';
        
        // Campos dinámicos del JSON
        $details = [
            'make' => $_POST['make'] ?? '',
            'model' => $_POST['model'] ?? '',
            'serial_number' => $_POST['serial_number'] ?? '',
            'asset_tag' => $_POST['asset_tag'] ?? '',
            'server_function' => $_POST['server_function'] ?? '',
            'owner' => $_POST['owner'] ?? '',
            'ip_address' => $_POST['ip_address'] ?? '',
            'mac_address' => $_POST['mac_address'] ?? '',
            'warranty' => $_POST['warranty'] ?? '',
            'weight' => $_POST['weight'] ?? '',
            'watts' => $_POST['watts'] ?? '',
            'amps' => $_POST['amps'] ?? '',
            'voltage' => $_POST['voltage'] ?? '',
            'color' => $_POST['color'] ?? '#2a2a2a' // Color de fondo visual en el rack
        ];
        
        $details_json = json_encode($details);
        
        if ($id > 0) {
            // Actualizar
            $stmt = $pdo->prepare("UPDATE dc_rack_devices SET name=?, start_u=?, height_u=?, orientation=?, cmdb_reference=?, details_json=? WHERE id=? AND rack_id=?");
            $stmt->execute([$name, $start_u, $height_u, $orientation, $cmdb_ref, $details_json, $id, $rack_id]);
            echo json_encode(['success' => true, 'message' => 'Dispositivo actualizado']);
        } else {
            // Insertar
            $stmt = $pdo->prepare("INSERT INTO dc_rack_devices (rack_id, name, start_u, height_u, orientation, cmdb_reference, details_json) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$rack_id, $name, $start_u, $height_u, $orientation, $cmdb_ref, $details_json]);
            echo json_encode(['success' => true, 'message' => 'Dispositivo agregado', 'id' => $pdo->lastInsertId()]);
        }
        
    } elseif ($action === 'delete_device') {
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM dc_rack_devices WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Dispositivo eliminado']);
        
    } elseif ($action === 'update_rack_position') {
        $rack_id = (int)($_POST['rack_id'] ?? 0);
        $grid_x = (int)($_POST['grid_x'] ?? 0);
        $grid_y = (int)($_POST['grid_y'] ?? 0);
        
        if ($rack_id > 0) {
            $stmt = $pdo->prepare("UPDATE dc_racks SET grid_x = ?, grid_y = ? WHERE id = ?");
            $stmt->execute([$grid_x, $grid_y, $rack_id]);
            echo json_encode(['success' => true, 'message' => 'Posición actualizada']);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de rack inválido']);
        }
    } elseif ($action === 'update_rack_rotation') {
        $rack_id = (int)($_POST['rack_id'] ?? 0);
        $rotation = (int)($_POST['rotation'] ?? 0);
        
        if ($rack_id > 0) {
            $stmt = $pdo->prepare("UPDATE dc_racks SET rotation = ? WHERE id = ?");
            $stmt->execute([$rotation, $rack_id]);
            echo json_encode(['success' => true, 'message' => 'Rotación actualizada']);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de rack inválido']);
        }
        
    } elseif ($action === 'save_rack_drag') {
        $room_id = (int)($_POST['room_id'] ?? 0);
        $name = $_POST['name'] ?? 'Nuevo Rack';
        $grid_x = (int)($_POST['grid_x'] ?? 0);
        $grid_y = (int)($_POST['grid_y'] ?? 0);
        $width_tiles = (int)($_POST['width_tiles'] ?? 1);
        $depth_tiles = (int)($_POST['depth_tiles'] ?? 2);
        $total_u = (int)($_POST['total_u'] ?? 42);
        $rotation = (int)($_POST['rotation'] ?? 0);
        
        if ($room_id > 0) {
            $stmt = $pdo->prepare("INSERT INTO dc_racks (room_id, name, grid_x, grid_y, width_tiles, depth_tiles, total_u, rotation) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$room_id, $name, $grid_x, $grid_y, $width_tiles, $depth_tiles, $total_u, $rotation]);
            echo json_encode(['success' => true, 'message' => 'Rack creado', 'id' => $pdo->lastInsertId()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de cuarto inválido']);
        }

    } elseif ($action === 'get_floor_items') {
        $room_id = (int)($_GET['room_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT i.*, l.name as layer_name, l.z_index 
                               FROM dc_floor_items i 
                               LEFT JOIN dc_floor_layers l ON i.layer_id = l.id 
                               WHERE i.room_id = ?");
        $stmt->execute([$room_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $items]);
        
    } elseif ($action === 'save_floor_item') {
        $id = (int)($_POST['id'] ?? 0);
        $room_id = (int)($_POST['room_id'] ?? 0);
        $name = $_POST['name'] ?? 'Nuevo Ítem';
        $type = $_POST['type'] ?? 'unknown';
        $layer_id = !empty($_POST['layer_id']) ? (int)$_POST['layer_id'] : null;
        $width_tiles = (float)($_POST['width_tiles'] ?? 1);
        $depth_tiles = (float)($_POST['depth_tiles'] ?? 1);
        $height_meters = (float)($_POST['height_meters'] ?? 0);
        $grid_x = (float)($_POST['grid_x'] ?? 0);
        $grid_y = (float)($_POST['grid_y'] ?? 0);
        $rotation = (int)($_POST['rotation'] ?? 0);
        
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE dc_floor_items SET name=?, type=?, layer_id=?, width_tiles=?, depth_tiles=?, height_meters=?, rotation=? WHERE id=?");
            $stmt->execute([$name, $type, $layer_id, $width_tiles, $depth_tiles, $height_meters, $rotation, $id]);
            echo json_encode(['success' => true, 'message' => 'Ítem actualizado']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO dc_floor_items (room_id, name, type, layer_id, grid_x, grid_y, width_tiles, depth_tiles, height_meters, rotation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$room_id, $name, $type, $layer_id, $grid_x, $grid_y, $width_tiles, $depth_tiles, $height_meters, $rotation]);
            echo json_encode(['success' => true, 'message' => 'Ítem creado', 'id' => $pdo->lastInsertId()]);
        }
        
    } elseif ($action === 'delete_floor_item') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM dc_floor_items WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Ítem eliminado']);
        
    } elseif ($action === 'update_floor_item_position') {
        $id = (int)($_POST['id'] ?? 0);
        $grid_x = (float)($_POST['grid_x'] ?? 0);
        $grid_y = (float)($_POST['grid_y'] ?? 0);
        
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE dc_floor_items SET grid_x = ?, grid_y = ? WHERE id = ?");
            $stmt->execute([$grid_x, $grid_y, $id]);
            echo json_encode(['success' => true, 'message' => 'Posición de ítem actualizada']);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
        }
        
    } elseif ($action === 'update_floor_item_rotation') {
        $id = (int)($_POST['id'] ?? 0);
        $rotation = (int)($_POST['rotation'] ?? 0);
        
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE dc_floor_items SET rotation = ? WHERE id = ?");
            $stmt->execute([$rotation, $id]);
            echo json_encode(['success' => true, 'message' => 'Rotación de ítem actualizada']);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
        }
        
    } elseif ($action === 'update_item_z_index') {
        $id = (int)($_POST['id'] ?? 0);
        $type = $_POST['type'] ?? '';
        $z_index = (int)($_POST['z_index'] ?? 0);
        
        if ($id > 0 && ($type === 'rack' || $type === 'item')) {
            if ($type === 'rack') {
                $stmt = $pdo->prepare("UPDATE dc_racks SET z_index = ? WHERE id = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE dc_floor_items SET z_index = ? WHERE id = ?");
            }
            $stmt->execute([$z_index, $id]);
            echo json_encode(['success' => true, 'message' => 'Orden de capa (z-index) actualizado']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
        }
        
    } elseif ($action === 'get_layers') {
        $stmt = $pdo->query("SELECT * FROM dc_floor_layers ORDER BY z_index ASC");
        $layers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $layers]);
        
    } elseif ($action === 'save_layer') {
        $id = (int)($_POST['id'] ?? 0);
        $name = $_POST['name'] ?? 'Nueva Capa';
        $z_index = (int)($_POST['z_index'] ?? 10);
        
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE dc_floor_layers SET name=?, z_index=? WHERE id=?");
            $stmt->execute([$name, $z_index, $id]);
            echo json_encode(['success' => true, 'message' => 'Capa actualizada']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO dc_floor_layers (name, z_index) VALUES (?, ?)");
            $stmt->execute([$name, $z_index]);
            echo json_encode(['success' => true, 'message' => 'Capa creada', 'id' => $pdo->lastInsertId()]);
        }
        
    } elseif ($action === 'delete_layer') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM dc_floor_layers WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Capa eliminada']);
        
    } elseif ($action === 'get_zabbix_hostgroups') {
        require_once __DIR__ . '/../../src/zabbix_api.php';
        $response = call_zabbix_api('hostgroup.get', [
            'output' => ['groupid', 'name'],
            'sortfield' => 'name'
        ]);
        if (isset($response['error'])) {
            echo json_encode(['success' => false, 'message' => $response['error']]);
        } else {
            echo json_encode(['success' => true, 'data' => $response['result']]);
        }
        
    } elseif ($action === 'get_zabbix_hosts') {
        require_once __DIR__ . '/../../src/zabbix_api.php';
        $groupid = $_GET['groupid'] ?? null;
        $params = [
            'output' => ['hostid', 'host', 'name'],
            'selectInterfaces' => ['ip'],
            'sortfield' => 'name'
        ];
        if ($groupid) {
            $params['groupids'] = $groupid;
        }
        $response = call_zabbix_api('host.get', $params);
        if (isset($response['error'])) {
            echo json_encode(['success' => false, 'message' => $response['error']]);
        } else {
            $hosts = array_map(function($h) {
                $ip = '';
                if (!empty($h['interfaces'])) {
                    $ip = $h['interfaces'][0]['ip'];
                }
                return [
                    'hostid' => $h['hostid'],
                    'name' => $h['name'],
                    'ip' => $ip
                ];
            }, $response['result']);
            echo json_encode(['success' => true, 'data' => $hosts]);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
