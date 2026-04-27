<?php
// --- Robust Error Handling & Logging ---
ini_set('display_errors', 0); // Disable public error reporting
ini_set('log_errors', 1);     // Enable error logging
$log_path =  '../storage/logs/api_errors.log';
ini_set('error_log', $log_path); // Set log file path

// Set a global error handler to catch fatal errors and return valid JSON
register_shutdown_function(function () use ($log_path) {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Log the detailed error
        error_log(sprintf("Fatal Error: %s in %s on line %d", $error['message'], $error['file'], $error['line']));
        
        // Ensure we don't send headers that are already sent
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500); // Internal Server Error
        }
        // Attempt to clean any buffered output that might have happened before the error
        ob_get_level() > 0 && ob_end_clean();

        echo json_encode([
            'success' => false,
            'error' => 'Error fatal del servidor: ' . $error['message'],
            'details' => "File: {$error['file']}, Line: {$error['line']}"
        ]);
        exit();
    }
});

// Set a global exception handler to log uncaught exceptions
set_exception_handler(function($exception) {
    error_log("Uncaught Exception: " . $exception->getMessage());
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    ob_get_level() > 0 && ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Excepción no controlada: ' . $exception->getMessage()
    ]);
    exit();
});

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/zabbix_api.php';
require_once __DIR__ . '/../src/importer.php';

// Iniciar almacenamiento en búfer para evitar que advertencias rompan el JSON
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

header('Content-Type: application/json');
$action = $_REQUEST['action'] ?? '';
$user = current_user();

// --- NUEVAS ACCIONES PARA IMPORTACIÓN GRANULAR ---

if ($action === 'get_excel_metadata') {
    if (!has_role(['ADMIN', 'SUPER_ADMIN'])) exit(json_encode(['success' => false, 'error' => 'Permiso denegado.']));
    try {
        if (!isset($_FILES['file'])) throw new Exception("No se subió ningún archivo.");
        $tmpPath = $_FILES['file']['tmp_name'];
        $metadata = Importer::getExcelMetadata($tmpPath);
        
        $sessionStorage = __DIR__ . '/../storage/temp_imports/';
        if (!is_dir($sessionStorage)) mkdir($sessionStorage, 0777, true);
        
        $newFileName = 'import_' . time() . '_' . session_id() . '.xlsx';
        move_uploaded_file($tmpPath, $sessionStorage . $newFileName);
        
        echo json_encode(['success' => true, 'metadata' => $metadata, 'tempFile' => $newFileName]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'execute_mapped_import') {
    if (!has_role(['SUPER_ADMIN'])) exit(json_encode(['success' => false, 'error' => 'Permiso denegado.']));
    try {
        $tempFile = $_POST['tempFile'] ?? '';
        $tableName = $_POST['tableName'] ?? '';
        $sheetName = $_POST['sheetName'] ?? '';
        $mapping = $_POST['mapping'] ?? []; 

        if (empty($tempFile) || empty($tableName) || empty($sheetName) || empty($mapping)) {
            throw new Exception("Faltan parámetros para la importación.");
        }

        $fullPath = __DIR__ . '/../storage/temp_imports/' . basename($tempFile);
        if (!is_file($fullPath)) throw new Exception("Archivo temporal no encontrado.");

        $result = Importer::executeMappedImport($tableName, $fullPath, $sheetName, $mapping);
        @unlink($fullPath);
        
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'truncate_cmdb_table') {
    if (!has_role(['SUPER_ADMIN'])) exit(json_encode(['success' => false, 'error' => 'Permiso denegado.']));
    try {
        $tableName = $_POST['tableName'] ?? '';
        if (!isValidTableName($tableName)) throw new Exception("Nombre de tabla inválido.");
        $pdo = getPDO();
        $pdo->exec("TRUNCATE TABLE `$tableName` ");
        echo json_encode(['success' => true, 'message' => "La tabla '$tableName' ha sido vaciada correctamente."]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'list_keywords') {
    if (!has_role(['ADMIN', 'SUPER_ADMIN'])) exit(json_encode(['success' => false, 'error' => 'Permiso denegado.']));
    try {
        $pdo = getPDO();
        $res = $pdo->query("SELECT * FROM zabbix_keywords ORDER BY keyword ASC");
        echo json_encode(['success' => true, 'data' => $res->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'add_keyword') {
    if (!has_role(['SUPER_ADMIN'])) exit(json_encode(['success' => false, 'error' => 'Permiso denegado.']));
    try {
        $keyword = strtoupper(trim($_POST['keyword'] ?? ''));
        if (empty($keyword)) throw new Exception("Palabra clave vacía.");
        $pdo = getPDO();
        $stmt = $pdo->prepare("INSERT IGNORE INTO zabbix_keywords (keyword) VALUES (?)");
        $stmt->execute([$keyword]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_keyword') {
    if (!has_role(['SUPER_ADMIN'])) exit(json_encode(['success' => false, 'error' => 'Permiso denegado.']));
    try {
        $id = (int)($_POST['id'] ?? 0);
        $pdo = getPDO();
        $stmt = $pdo->prepare("DELETE FROM zabbix_keywords WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'drop_cmdb_table') {
    if (!has_role(['SUPER_ADMIN'])) exit(json_encode(['success' => false, 'error' => 'Permiso denegado.']));
    try {
        $tableName = $_POST['tableName'] ?? '';
        if (!isValidTableName($tableName)) throw new Exception("Nombre de tabla inválido.");
        $pdo = getPDO();
        $pdo->exec("DROP TABLE `$tableName` ");
        // Eliminar configuraciones asociadas
        $pdo->prepare("DELETE FROM sheet_configs WHERE table_name = ?")->execute([$tableName]);
        $pdo->prepare("DELETE FROM zabbix_cmdb_config WHERE table_name = ?")->execute([$tableName]);
        $pdo->prepare("DELETE FROM zabbix_mappings WHERE cmdb_table_name = ?")->execute([$tableName]);
        
        echo json_encode(['success' => true, 'message' => "La tabla '$tableName' ha sido eliminada por completo."]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_table_stats') {
    if (!has_role(['ADMIN', 'SUPER_ADMIN'])) exit(json_encode(['success' => false, 'error' => 'Permiso denegado.']));
    try {
        $tableName = $_POST['tableName'] ?? '';
        if (!isValidTableName($tableName)) throw new Exception("Nombre de tabla inválido.");
        $pdo = getPDO();
        
        // Consultar estadísticas de la tabla en Information Schema
        $stmt = $pdo->prepare("
            SELECT 
                TABLE_ROWS as rows,
                DATA_LENGTH as data_size,
                INDEX_LENGTH as index_size,
                DATA_FREE as free_space
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl
        ");
        $stmt->execute([':db' => DB_CONFIG['database'], ':tbl' => $tableName]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stats) throw new Exception("No se pudieron obtener estadísticas para '$tableName'.");
        
        echo json_encode(['success' => true, 'stats' => $stats]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'save_zabbix_cmdb_config') {

    if (!has_role(['SUPER_ADMIN'])) {
        exit(json_encode(['success' => false, 'error' => 'Permiso denegado.']));
    }

    $selected_tables = $_POST['tables'] ?? [];
    $zabbix_ip = $_POST['zabbix_ip'] ?? '';
    $zabbix_api_key = $_POST['zabbix_api_key'] ?? '';

    if (!is_array($selected_tables)) {
        exit(json_encode(['success' => false, 'error' => 'Datos inválidos.']));
    }

    $pdo = getPDO();
    try {
        error_log("Iniciando guardado de configuración Zabbix...");
        $pdo->beginTransaction();

        // 1. Actualizar visibilidad de tablas
        $stmt_disable = $pdo->prepare("UPDATE zabbix_cmdb_config SET is_enabled = 0");
        $stmt_disable->execute();
        error_log("Tablas deshabilitadas temporalmente.");

        $stmt_upsert = $pdo->prepare(
            "INSERT INTO zabbix_cmdb_config (table_name, is_enabled) 
             VALUES (:table_name, 1) 
             ON DUPLICATE KEY UPDATE is_enabled = 1"
        );

        foreach ($selected_tables as $table) {
            if (isValidTableName($table)) {
                $stmt_upsert->execute([':table_name' => $table]);
                error_log("Tabla habilitada: $table");
            }
        }

        // 2. Actualizar configuración técnica en la base de datos (Zabbix API)
        if (!empty($zabbix_ip) && !empty($zabbix_api_key)) {
            $protocol = (strpos($zabbix_ip, 'https') === 0) ? '' : 'http://';
            if (strpos($zabbix_ip, '://') !== false) $protocol = '';
            $new_url = (strpos($zabbix_ip, '.php') === false) ? $protocol . trim($zabbix_ip) . "/zabbix/api_jsonrpc.php" : $protocol . trim($zabbix_ip);

            error_log("Actualizando Zabbix API Config: URL=$new_url");
            $stmt_cfg = $pdo->prepare("INSERT INTO zabbix_api_config (id, url, token) VALUES (1, :u, :t) ON DUPLICATE KEY UPDATE url = :u, token = :t");
            $stmt_cfg->execute([':u' => $new_url, ':t' => $zabbix_api_key]);
        }

        $pdo->commit();
        error_log("Transacción de configuración completada con éxito.");
        if (ob_get_level() > 0) ob_clean(); // Limpiar cualquier salida accidental de forma segura
        exit(json_encode(['success' => true]));

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        exit(json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]));
    }
}





if ($action === 'test_zabbix_connection') {
    header('Content-Type: application/json');
    try {
        $ip = $_POST['zabbix_ip'] ?? '';
        $token = $_POST['zabbix_api_key'] ?? '';

        if (empty($ip) || empty($token)) {
            throw new Exception("IP y Token son requeridos para la prueba.");
        }

        // Determinar protocolo y URL
        $protocol = (strpos($ip, 'https') === 0) ? '' : 'http://';
        if (strpos($ip, '://') !== false) $protocol = ''; // Ya tiene protocolo
        
        $clean_ip = trim($ip);
        // Si no termina en .php, asumimos la ruta estándar
        if (strpos($clean_ip, '.php') === false) {
            $url = $protocol . $clean_ip . "/zabbix/api_jsonrpc.php";
        } else {
            $url = $protocol . $clean_ip;
        }
        
        // --- PASO 1: OBTENER VERSIÓN (Prueba de conectividad básica) ---
        $ch = curl_init($url);
        if ($ch === false) throw new Exception("Error al inicializar cURL");

        $payload_version = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'apiinfo.version',
            'params' => [],
            'id' => 1
        ]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_version);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        // Ignorar SSL para pruebas si es necesario (comentado por seguridad, pero útil si falla)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response_v = curl_exec($ch);
        $error_v = curl_error($ch);
        $http_code_v = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($error_v) {
            curl_close($ch);
            throw new Exception("Error de conexión (Timeout o Red): " . $error_v);
        }

        if ($http_code_v >= 400) {
            curl_close($ch);
            throw new Exception("Error HTTP " . $http_code_v . ". Verifica que la URL sea correcta: $url");
        }

        $decoded_v = json_decode($response_v, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($decoded_v['result'])) {
            curl_close($ch);
            throw new Exception("Respuesta no válida de Zabbix en apiinfo.version. ¿Es correcta la URL?");
        }

        $zabbix_version = $decoded_v['result'];

        // --- PASO 2: VALIDAR TOKEN (Prueba de autenticación) ---
        // Zabbix 5.4+ prefiere Authorization: Bearer. 
        // Verificamos si podemos obtener info del usuario actual.
        
        $payload_auth = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'user.get',
            'params' => ['output' => ['username', 'name', 'surname']],
            'id' => 2
        ]);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_auth);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . trim($token)
        ]);

        $response_a = curl_exec($ch);
        $error_a = curl_error($ch);
        curl_close($ch);

        if ($error_a) {
            throw new Exception("Error al validar token: " . $error_a);
        }

        $decoded_a = json_decode($response_a, true);
        
        if (isset($decoded_a['error'])) {
            $err_detail = $decoded_a['error']['data'] ?? $decoded_a['error']['message'];
            throw new Exception("Error de Autenticación (Token): " . $err_detail);
        }

        if (!isset($decoded_a['result'])) {
            throw new Exception("Error inesperado al validar el token de Zabbix.");
        }

        echo json_encode([
            'success' => true, 
            'version' => $zabbix_version,
            'message' => 'Conexión exitosa y token validado.'
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'save_zabbix_mapping') {
    try {
        $pdo = getPDO();
        $table = $_POST['cmdb_table_name'] ?? '';
        
        if (empty($table)) {
            throw new Exception("Nombre de tabla no recibido.");
        }

        // 1. Procesar Inventario (inv_...)
        $inventory = [];
        foreach ($_POST as $key => $val) {
            if (strpos($key, 'inv_') === 0 && !empty($val)) {
                $field_zabbix = str_replace('inv_', '', $key);
                $inventory[$field_zabbix] = $val;
            }
        }

        // 2. Procesar Tags Dinámicos
        $tag_names = $_POST['tag_names'] ?? [];
        $tag_cols = $_POST['tag_cols'] ?? [];
        $tags_final = [];
        for ($i = 0; $i < count($tag_names); $i++) {
            if (!empty(trim($tag_names[$i]))) {
                $tags_final[trim($tag_names[$i])] = $tag_cols[$i];
            }
        }

        // 3. SQL con todos los campos requeridos
        $sql = "INSERT INTO zabbix_mappings 
                (cmdb_table_name, hostname_template, visible_name_template, hostgroup_template, ip_field, snmp_community_field, template_name, inventory_fields_json, tags_json) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                hostname_template=VALUES(hostname_template), 
                visible_name_template=VALUES(visible_name_template), 
                hostgroup_template=VALUES(hostgroup_template), 
                ip_field=VALUES(ip_field), 
                snmp_community_field=VALUES(snmp_community_field), 
                template_name=VALUES(template_name), 
                inventory_fields_json=VALUES(inventory_fields_json), 
                tags_json=VALUES(tags_json)";
        
        $stmt = $pdo->prepare($sql);
        $res = $stmt->execute([
            $table, 
            $_POST['hostname_template'], 
            $_POST['visible_name_template'], 
            $_POST['hostgroup_template'], 
            $_POST['ip_field'], 
            $_POST['snmp_community_field'] ?: 'public', 
            $_POST['template_name'], 
            json_encode($inventory), 
            json_encode($tags_final) // Guardamos los tags procesados
        ]);

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        // Si hay error, lo devolvemos como JSON para que el JS lo capture
        error_log("Error en save_zabbix_mapping: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
if ($action === 'create_zabbix_host') {
    header('Content-Type: application/json');
    try {
        $pdo = getPDO();
        $table_name = $_POST['table_name'] ?? '';
        $row_id = filter_input(INPUT_POST, 'row_id', FILTER_VALIDATE_INT);

        if (!$table_name || !$row_id) throw new Exception("Faltan parámetros básicos.");

        // 1. OBTENER MAPEO
        $stmt_map = $pdo->prepare("SELECT * FROM zabbix_mappings WHERE cmdb_table_name = :tbl LIMIT 1");
        $stmt_map->execute([':tbl' => $table_name]);
        $mapping = $stmt_map->fetch(PDO::FETCH_ASSOC);

        if (!is_array($mapping)) {
            throw new Exception("Configuración de mapeo no encontrada para '$table_name'.");
        }

        // 2. OBTENER FILA DEL EQUIPO + DATA DE LOCALIDAD (JOIN)
        // Unimos con la tabla localidades usando 'sucursal' -> 'localidad'
        $sql_row = "
            SELECT e.*, l.latitud, l.longitud 
            FROM `$table_name` AS e
            LEFT JOIN sheet_localidades AS l ON e.sucursal = l.localidades
            WHERE e.id = :id
        ";
        $stmt_row = $pdo->prepare($sql_row);
        $stmt_row->execute([':id' => $row_id]);
        $row = $stmt_row->fetch(PDO::FETCH_ASSOC);

        if (!$row) throw new Exception("Equipo ID $row_id no encontrado.");

        // --- HELPER: PARSER ---
        $parser = function($text, $data) {
            return preg_replace_callback('/\{([^}]+)\}/', function($m) use ($data) {
                return (isset($data[$m[1]])) ? (string)$data[$m[1]] : '';
            }, (string)$text);
        };

        // 3. PROCESAR NOMBRES Y LIMPIEZA
        $h_raw = $parser($mapping['hostname_template'], $row);
        $v_raw = $parser($mapping['visible_name_template'], $row) ?: $h_raw;
        $visible_name = trim(str_replace(["\xc2\xa0", "\xa0"], ' ', $v_raw));
        $hostname = preg_replace('/[\s\x{00a0}]+/u', '_', $h_raw);
        $hostname = preg_replace('/[^A-Za-z0-9\.\-_]/', '', $hostname);
        $hostname = trim($hostname, '_');

        if (empty($hostname)) throw new Exception("Nombre técnico vacío tras limpieza.");

        // 4. DATOS DE RED Y GRUPO
        $hostgroup_name = $parser($mapping['hostgroup_template'], $row);
        $ip_final = $row[$mapping['ip_field']] ?? '';
        $community = $row[$mapping['snmp_community_field']] ?? 'public';

        if (empty($ip_final)) throw new Exception("IP no encontrada.");

        // 5. GESTIÓN DE GRUPO
        $group_res = call_zabbix_api('hostgroup.get', ['filter' => ['name' => $hostgroup_name]]);
        if (empty($group_res['result'])) {
            $g_create = call_zabbix_api('hostgroup.create', ['name' => $hostgroup_name]);
            $group_id = $g_create['result']['groupids'][0] ?? null;
        } else {
            $group_id = $group_res['result'][0]['groupid'];
        }

        // 6. GESTIÓN DE TEMPLATES
        $template_names = array_map('trim', explode(',', $mapping['template_name']));
        $tpl_res = call_zabbix_api('template.get', ['output' => ['templateid'], 'filter' => ['host' => $template_names]]);
        if (empty($tpl_res['result'])) throw new Exception("Templates no encontrados en Zabbix.");
        
        $template_ids_payload = array_map(function($t) { return ['templateid' => $t['templateid']]; }, $tpl_res['result']);

        // 7. PREPARAR INVENTARIO (Incluyendo Coordenadas del JOIN)
        $inventory_data = [];
        $saved_inv_mapping = json_decode($mapping['inventory_fields_json'] ?? '[]', true);
        foreach ($saved_inv_mapping as $z_field => $cmdb_col) {
            if (!empty($cmdb_col) && !empty($row[$cmdb_col])) {
                $inventory_data[$z_field] = (string)$row[$cmdb_col];
            }
        }
        // Inyectar coordenadas para los Geomaps de Zabbix
        if (!empty($row['latitud']))  $inventory_data['location_lat'] = (string)$row['latitud'];
        if (!empty($row['longitud'])) $inventory_data['location_lon'] = (string)$row['longitud'];

        // Tags Dinámicos
        $tags_data = [];
        $saved_tags_mapping = json_decode($mapping['tags_json'] ?? '[]', true);
        foreach ($saved_tags_mapping as $tag_n => $cmdb_c) {
            if (!empty($row[$cmdb_c])) {
                $tags_data[] = ['tag' => (string)$tag_n, 'value' => (string)$row[$cmdb_c]];
            }
        }

        // 8. CONSTRUCCIÓN DEL PAYLOAD FINAL (Macro SNMP + Inventario)
        $params = [
            'host' => $hostname,
            'name' => $visible_name,
            'interfaces' => [[
                'type' => 2, 'main' => 1, 'useip' => 1, 'ip' => $ip_final, 'dns' => '', 'port' => '161',
                'details' => [
                    'version' => 2, 
                    'community' => '{$SNMP_COMMUNITY}' // Asignación de la macro
                ]
            ]],
            'groups' => [['groupid' => $group_id]],
            'templates' => $template_ids_payload,
            'macros' => [[
                'macro' => '{$SNMP_COMMUNITY}',
                'value' => $community // Valor real de la comunidad
            ]],
            'inventory_mode' => 1, // 1 = Manual
            'inventory' => $inventory_data,
            'tags' => $tags_data
        ];

        // 9. LLAMADA ÚNICA A LA API
        $response = call_zabbix_api('host.create', $params);

        if (isset($response['error'])) {
            $err_msg = $response['error']['data'] ?? $response['error']['message'];
            throw new Exception("API Zabbix: " . $err_msg);
        }

        // 10. ACTUALIZAR CMDB
        $new_hostid = $response['result']['hostids'][0];
        $pdo->prepare("UPDATE `$table_name` SET zabbix_host_id = ? WHERE id = ?")->execute([$new_hostid, $row_id]);

        echo json_encode(['success' => true, 'log' => "Éxito: Creado como '$hostname' (ID: $new_hostid)"]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'log' => "ERROR: " . $e->getMessage()]);
    }
    exit;
}











if ($action === 'get_zabbix_mapping') {
    if (!has_role(['SUPER_ADMIN'])) {
        exit(json_encode(['success' => false, 'error' => 'Permiso denegado.']));
    }
    
    try {
        $table_name = $_POST['table_name'] ?? '';
        if (!isValidTableName($table_name)) {
            exit(json_encode(['success' => false, 'error' => 'Nombre de tabla inválido.']));
        }

        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT * FROM zabbix_mappings WHERE cmdb_table_name = :table_name LIMIT 1");
        $stmt->execute([':table_name' => $table_name]);
        $mapping = $stmt->fetch(PDO::FETCH_ASSOC);

        // Also get the columns for this table to populate dropdowns
        $columns = getTableColumns($table_name);
        exit(json_encode(['success' => false, 'error' => 'Error de Base de Datos: ' . $e->getMessage()]));
    } catch (Exception $e) {
        exit(json_encode(['success' => false, 'error' => 'Error General del Servidor: ' . $e->getMessage()]));
    }
}


if ($action === 'get_cmdb_data_for_zabbix') {
    if (!has_role(['ADMIN', 'SUPER_ADMIN'])) {
        exit(json_encode(['success' => false, 'error' => 'Permiso denegado.']));
    }
    
    try {
        require_once  '../src/zabbix_api.php';

        $tables = $_POST['tables'] ?? [];
        if (empty($tables) || !is_array($tables)) {
            exit(json_encode(['success' => false, 'error' => 'No se seleccionaron tablas.']));
        }

        $pdo = getPDO();
        $results = [];
        
        // Fetch all hosts from Zabbix once to avoid multiple API calls
        $zabbix_response = call_zabbix_api('host.get', [
            'output' => ['host', 'name', 'hostid'],
            'selectInterfaces' => ['ip']
        ]);
        if (isset($zabbix_response['error'])) {
            exit(json_encode(['success' => false, 'error' => $zabbix_response['error']]));
        }
        $all_zabbix_hosts = $zabbix_response['result'];

        // Create lookup maps for quick access
        $zabbix_host_map_by_name = [];
        $zabbix_host_map_by_ip = [];
        foreach ($all_zabbix_hosts as $host) {
            $zabbix_host_map_by_name[strtolower($host['host'])] = $host; // Hostname
            $zabbix_host_map_by_name[strtolower($host['name'])] = $host; // Visible Name
            foreach ($host['interfaces'] as $interface) {
                $zabbix_host_map_by_ip[$interface['ip']] = $host;
            }
        }

        // --- LOGGING POINT ---
        error_log("Acción 'get_cmdb_data_for_zabbix' iniciada. Tablas solicitadas: " . implode(', ', $tables));

        foreach ($tables as $table) {
            if (!isValidTableName($table)) {
                error_log("ADVERTENCIA: Nombre de tabla inválido omitido: {$table}");
                continue;
            }

            try {
                $allCols = getTableColumns($table);
                if (in_array('sucursal', $allCols)) {
                    $sql = "SELECT e.*, l.latitud, l.longitud FROM `$table` AS e LEFT JOIN sheet_localidades AS l ON e.sucursal = l.localidades";
                } else {
                    $sql = "SELECT * FROM `$table` ";
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $rowCount = count($rows);
                // --- LOGGING POINT ---
                error_log("Consultando tabla '{$table}': Se encontraron {$rowCount} filas.");

                if ($rowCount === 0) {
                    $results[$table] = ['columns' => [], 'rows' => []]; // Send empty result
                    continue;
                }

                $columns = array_keys($rows[0]); // Get columns from the first row
                $processed_rows = [];

                // Define which CMDB columns to check for hostnames and IPs as fallback
                $name_candidates = ['nombre', 'hostname', 'device_name', 'name', 'ap'];
                $ip_candidates = ['ip', 'ip_address', 'direccion_ip'];

                // Fetch mapping for this table to use dynamic discovery
                $stmt_map = $pdo->prepare("SELECT hostname_template, visible_name_template, ip_field FROM zabbix_mappings WHERE cmdb_table_name = ?");
                $stmt_map->execute([$table]);
                $mapping = $stmt_map->fetch(PDO::FETCH_ASSOC);

                foreach ($rows as $row) {
                    $status = 'No Monitoreado';
                    $zabbix_host_id = $row['zabbix_host_id'] ?? null;
                    $found_host = null;

                    // 1. Check by stored Zabbix Host ID first
                    if ($zabbix_host_id) {
                        $status = 'Monitoreado (ID)';
                    } else {
                        // 2. If no ID, check by name/hostname and IP
                        $cmdb_names = [];
                        $cmdb_ip = '';

                        if ($mapping) {
                            $h_temp = $mapping['hostname_template'] ?? '';
                            $v_temp = $mapping['visible_name_template'] ?? '';
                            $ip_f = $mapping['ip_field'] ?? '';

                            // Identify columns from templates
                            if ($h_temp) {
                                $val = preg_replace_callback('/\{(\w+)\}/', function($m) use ($row) { return $row[$m[1]] ?? ''; }, $h_temp);
                                if (!empty($val)) $cmdb_names[] = strtolower($val);
                            }
                            if ($v_temp) {
                                $val = preg_replace_callback('/\{(\w+)\}/', function($m) use ($row) { return $row[$m[1]] ?? ''; }, $v_temp);
                                if (!empty($val)) $cmdb_names[] = strtolower($val);
                            }
                            if ($ip_f && !empty($row[$ip_f])) {
                                $cmdb_ip = $row[$ip_f];
                            }
                        } else {
                            // Fallback to old hardcoded candidates
                            foreach($name_candidates as $key) { if(!empty($row[$key])) { $cmdb_names[] = strtolower($row[$key]); break; } }
                            foreach($ip_candidates as $key) { if(!empty($row[$key])) { $cmdb_ip = $row[$key]; break; } }
                        }
                        
                        // Try matching by name
                        foreach ($cmdb_names as $n) {
                            if (isset($zabbix_host_map_by_name[$n])) {
                                $found_host = $zabbix_host_map_by_name[$n];
                                break;
                            }
                        }

                        // Try matching by IP if name failed
                        if (!$found_host && !empty($cmdb_ip) && isset($zabbix_host_map_by_ip[$cmdb_ip])) {
                            $found_host = $zabbix_host_map_by_ip[$cmdb_ip];
                        }

                        if ($found_host) {
                            $status = 'Monitoreado';
                            // Auto-update zabbix_host_id in CMDB for future lookups
                            $update_stmt = $pdo->prepare("UPDATE `$table` SET zabbix_host_id = :hostid WHERE id = :rowid");
                            $update_stmt->execute([':hostid' => $found_host['hostid'], ':rowid' => $row['id']]);
                        }
                    }
                    $row['_zabbix_status'] = $status;
                    $processed_rows[] = $row;
                }
                $results[$table] = [
                    'columns' => $columns,
                    'rows' => $processed_rows
                ];
            } catch (PDOException $e) {
                // Log the error and continue to the next table if possible
                error_log("ERROR de base de datos al consultar la tabla '{$table}': " . $e->getMessage());
                // We can return an error for this specific table in the response
                $results[$table] = ['error' => 'No se pudo consultar la tabla: ' . $e->getMessage()];
            }
        }
        exit(json_encode(['success' => true, 'data' => $results]));

    } catch (PDOException $e) {
        // Catch database errors (e.g., table not found)
        exit(json_encode(['success' => false, 'error' => 'Error de Base de Datos: ' . $e->getMessage()]));
    } catch (Exception $e) {
        // Catch any other general errors
        exit(json_encode(['success' => false, 'error' => 'Error General del Servidor: ' . $e->getMessage()]));
    }
}

if ($action === 'delete') {
    if (!has_role(['ADMIN','SUPER_ADMIN'])) exit(json_encode(['success'=>false,'error'=>'Permiso denegado']));
    $table = $_POST['table'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if (!isValidTableName($table) || !$id) exit(json_encode(['success'=>false,'error'=>'Parametros invalidos']));
    $pdo = getPDO();
    // fetch row before delete for history
    try {
        $st = $pdo->prepare("SELECT * FROM `$table` WHERE id = :id LIMIT 1");
        $st->execute([':id' => $id]);
        $old = $st->fetch(PDO::FETCH_ASSOC);
        // ensure history table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS sheet_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            table_name VARCHAR(255) NOT NULL,
            row_id INT NOT NULL,
            action VARCHAR(20) NOT NULL,
            changed_by VARCHAR(255) DEFAULT NULL,
            old_data JSON DEFAULT NULL,
            new_data JSON DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // insert history
        $h = $pdo->prepare('INSERT INTO sheet_history (table_name, row_id, action, changed_by, old_data) VALUES (:t,:rid,:a,:u,:o)');
        $h->execute([':t'=>$table, ':rid'=>$id, ':a'=>'delete', ':u'=>current_user()['username'] ?? null, ':o'=>json_encode($old)]);
    } catch (Exception $e) {
        // ignore history errors
    }
    $stmt = $pdo->prepare("DELETE FROM `$table` WHERE id = :id");
    $stmt->execute([':id'=>$id]);
    exit(json_encode(['success'=>true]));
}

if ($action === 'deactivate') {
    if (!has_role(['ADMIN','SUPER_ADMIN'])) exit(json_encode(['success'=>false,'error'=>'Permiso denegado']));
    $table = $_POST['table'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if (!isValidTableName($table) || !$id) exit(json_encode(['success'=>false,'error'=>'Parametros invalidos']));
    $pdo = getPDO();
    // Set estado_actual to 'NO_APARECE' or 'DANADO' as deactivation; we'll use 'NO_APARECE'
    $stmt = $pdo->prepare("UPDATE `$table` SET estado_actual = 'NO_APARECE' WHERE id = :id");
    $stmt->execute([':id'=>$id]);
    exit(json_encode(['success'=>true]));
}

if ($action === 'update') {
    if (!has_role(['ADMIN','SUPER_ADMIN'])) exit(json_encode(['success'=>false,'error'=>'Permiso denegado']));
    $table = $_POST['table'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if (!isValidTableName($table) || !$id) exit(json_encode(['success'=>false,'error'=>'Parametros invalidos']));
    
    $pdo = getPDO();
    $cols = getTableColumns($table);

    // Get current state of the row BEFORE update for history logging
    error_log("UPDATE REQUEST: Table=$table, ID=$id, Data=" . json_encode($_POST));
    $stmt_select = $pdo->prepare("SELECT * FROM `$table` WHERE id = :id");
    $stmt_select->execute([':id' => $id]);
    $old_row = $stmt_select->fetch(PDO::FETCH_ASSOC);
    if (!$old_row) exit(json_encode(['success'=>false,'error'=>'El registro no existe.']));

    // Determine what changed
    $old_values = [];
    $new_values = [];
    foreach ($cols as $c) {
        if (in_array($c, ['id', '_row_hash', 'created_at', 'updated_at'])) continue;
        // Check if field was submitted and is different from the old value
        if (isset($_POST[$c]) && array_key_exists($c, $old_row) && $_POST[$c] != $old_row[$c]) {
            $old_values[$c] = $old_row[$c];
            $new_values[$c] = $_POST[$c];
        }
    }

    // Prepare update query only with submitted fields
    $sets = []; $params = [':id'=>$id];
    $data_for_hash = $old_row; // Start with old data and overwrite with new

    foreach ($_POST as $key => $value) {
        if (in_array($key, $cols) && !in_array($key, ['id','_row_hash','created_at','updated_at','zabbix_host_id'])) {
            $sets[] = "`$key` = :$key";
            $params[":$key"] = $value;
            $data_for_hash[$key] = $value;
        }
    }

    if (empty($sets)) {
        exit(json_encode(['success'=>true, 'message'=>'No hay cambios que aplicar.']));
    }

    // Update _row_hash so future imports see this change
    unset($data_for_hash['id'], $data_for_hash['_row_hash'], $data_for_hash['created_at'], $data_for_hash['updated_at'], $data_for_hash['zabbix_host_id']);
    $newHash = hash('md5', json_encode(array_values($data_for_hash)));
    $sets[] = "`_row_hash` = :_new_hash";
    $params[':_new_hash'] = $newHash;

    // Log to history table ONLY if there were actual changes
    if (!empty($new_values)) {
        try {
            $stmt_history = $pdo->prepare(
                "INSERT INTO sheet_history (table_name, row_id, action, changed_by, old_data, new_data) 
                 VALUES (:table_name, :row_id, 'update', :user, :old, :new)"
            );
            $stmt_history->execute([
                ':table_name' => $table,
                ':row_id' => $id,
                ':user' => current_user()['username'] ?? 'unknown',
                ':old' => json_encode($old_values, JSON_UNESCAPED_UNICODE),
                ':new' => json_encode($new_values, JSON_UNESCAPED_UNICODE)
            ]);
        } catch (Exception $e) { /* ignore */ }
    }
    
    $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE id = :id";
    try {
        $stmt = $pdo->prepare($sql);
        $res = $stmt->execute($params);
        $count = $stmt->rowCount();
        
        if ($res) {
            // Verify if it actually changed in DB
            $vstmt = $pdo->prepare("SELECT * FROM `$table` WHERE id = :id");
            $vstmt->execute([':id' => $id]);
            $verified = $vstmt->fetch(PDO::FETCH_ASSOC);
            error_log("UPDATE SUCCESS: Table=$table, ID=$id, RowsAffected=$count, NewData=" . json_encode($verified));
            exit(json_encode(['success'=>true, 'rows_affected'=>$count]));
        } else {
             error_log("UPDATE FAILED for table $table, ID $id");
             exit(json_encode(['success'=>false, 'error'=>'Error al ejecutar actualización en DB']));
        }
    } catch (PDOException $e) {
        error_log("SQL UPDATE EXCEPTION: " . $e->getMessage() . " | SQL: $sql | Params: " . json_encode($params));
        exit(json_encode(['success'=>false, 'error'=>'Error de base de datos: ' . $e->getMessage()]));
    }
}

if ($action === 'create') {
    if (!has_role(['ADMIN','SUPER_ADMIN'])) exit(json_encode(['success'=>false,'error'=>'Permiso denegado']));
    $table = $_POST['table'] ?? '';
    if (!isValidTableName($table)) exit(json_encode(['success'=>false,'error'=>'Tabla invalida']));
    $cols = getTableColumns($table);
    $data = [];
    foreach ($cols as $c) {
        if (in_array($c, ['id','_row_hash','created_at','updated_at'])) continue;
        if (isset($_POST[$c]) && $_POST[$c] !== '') $data[$c] = $_POST[$c];
    }
    if (empty($data)) exit(json_encode(['success'=>false,'error'=>'Sin datos para insertar']));
    // compute _row_hash to match import logic
    $rowHash = hash('md5', json_encode(array_values($data)));
    $data['_row_hash'] = $rowHash;
    $colsInsert = array_keys($data);
    $placeholders = array_map(function($c){return ':'.$c;}, $colsInsert);
    $sql = "INSERT INTO `$table` (`" . implode('`,`', $colsInsert) . "`) VALUES (" . implode(', ', $placeholders) . ")";
    $pdo = getPDO();
    $stmt = $pdo->prepare($sql);
    $params = [];
    foreach ($data as $k=>$v) $params[':'.$k]=$v;
    try { $stmt->execute($params); } catch (Exception $e){ exit(json_encode(['success'=>false,'error'=>$e->getMessage()])); }
    exit(json_encode(['success'=>true]));
}

if ($action === 'list_images') {
    $table = $_GET['table'] ?? '';
    $id = (int)($_GET['id'] ?? 0);
    if (!isValidTableName($table) || !$id) exit(json_encode([]));
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT id, filepath, filename, uploaded_at FROM images WHERE entity_type = :t AND entity_id = :id ORDER BY uploaded_at DESC");
    $stmt->execute([':t'=>$table, ':id'=>$id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    exit(json_encode($rows));
}

if ($action === 'delete_image') {
    if (!has_role(['ADMIN','SUPER_ADMIN'])) exit(json_encode(['success'=>false,'error'=>'Permiso denegado']));
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) exit(json_encode(['success'=>false,'error'=>'Id invalido']));
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT filepath FROM images WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $fp = $stmt->fetchColumn();
    if (!$fp) exit(json_encode(['success'=>false,'error'=>'Imagen no encontrada']));
    // remove file if exists
    $full = '../' . ltrim($fp, '/');
    try {
        if (is_file($full)) @unlink($full);
    } catch (Exception $e) {
        // ignore
    }
    $d = $pdo->prepare("DELETE FROM images WHERE id = :id");
    $d->execute([':id' => $id]);
    exit(json_encode(['success'=>true]));
}


if ($action === 'get_mapping_form') {
    $table = $_GET['table'] ?? '';
    if (!isValidTableName($table)) {
        header('Content-Type: application/json');
        exit(json_encode(['success' => false, 'error' => 'Tabla no válida']));
    }

    $columns = getTableColumns($table); 
    if (empty($columns)) {
        header('Content-Type: application/json');
        exit(json_encode(['success' => false, 'error' => 'La tabla está vacía.']));
    }

    // Inyectar columnas lógicas de coordenadas si existe la columna sucursal
    if (in_array('sucursal', $columns)) {
        if (!in_array('latitud', $columns)) $columns[] = 'latitud';
        if (!in_array('longitud', $columns)) $columns[] = 'longitud';
    }

    header('Content-Type: text/html; charset=utf-8');

    // Recuperar mapeo previo
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM zabbix_mappings WHERE cmdb_table_name = ?");
    $stmt->execute([$table]);
    $saved = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $saved_inv = json_decode($saved['inventory_fields_json'] ?? '[]', true);
    $saved_tags = json_decode($saved['tags_json'] ?? '[]', true);

    $zabbix_inventory = [
        'type' => 'Tipo (Type)',
        'os' => 'S.O / Firmware',
        'hardware' => 'Hardware',
        'serialno_a' => 'S/N Principal',
        'location' => 'Ubicación',
        'location_lat' => 'Latitud (Latitude)',
        'location_lon' => 'Longitud (Longitude)',
        'vendor' => 'Fabricante',
        'model' => 'Modelo',
        'tag' => 'Asset Tag',
        'macaddress_a' => 'MAC Address A',
        'notes' => 'Notas (Notes)'
    ];
    ?>
    <form id="mapping-form">
        <input type="hidden" name="cmdb_table_name" value="<?= htmlspecialchars($table) ?>">
        
        <div class="card card-primary card-outline mb-3">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-id-card mr-2"></i>Identidad y Grupos</h3></div>
            <div class="card-body row">
                <div class="col-md-6 mb-3">
                    <label>Hostname (Zabbix Name)</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="hostname_template" id="h_temp" class="form-control" placeholder="{serial_number}" value="<?= htmlspecialchars($saved['hostname_template'] ?? '') ?>" required>
                        <select class="form-control col-4 btn-append-val" data-target="#h_temp">
                            <option value="">+ Columna</option>
                            <?php foreach($columns as $c) echo "<option value='{$c}'>$c</option>"; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label>Visible Name</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="visible_name_template" id="v_temp" class="form-control" placeholder="MON-{hostname}" value="<?= htmlspecialchars($saved['visible_name_template'] ?? '') ?>">
                        <select class="form-control col-4 btn-append-val" data-target="#v_temp">
                            <option value="">+ Columna</option>
                            <?php foreach($columns as $c) echo "<option value='{$c}'>$c</option>"; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-12">
                    <label>Host Group Template</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="hostgroup_template" id="g_temp" class="form-control" placeholder="Infra/{sucursal}" value="<?= htmlspecialchars($saved['hostgroup_template'] ?? '') ?>" required>
                        <select class="form-control col-2 btn-append-val" data-target="#g_temp">
                            <option value="">+ Columna</option>
                            <?php foreach($columns as $c) echo "<option value='{$c}'>$c</option>"; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-info card-outline mb-3">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-network-wired mr-2"></i>Conectividad y Template</h3></div>
            <div class="card-body row">
                <div class="col-md-4 mb-2">
                    <label>Columna IP Gestión</label>
                    <select name="ip_field" class="form-control form-control-sm select2-mapping">
                        <?php foreach($columns as $c) : ?>
                            <option value="<?= $c ?>" <?= ($saved['ip_field'] ?? '') == $c ? 'selected' : '' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label>Columna SNMP Community</label>
                    <select name="snmp_community_field" class="form-control form-control-sm select2-mapping">
                        <option value="">-- public --</option>
                        <?php foreach($columns as $c) : ?>
                            <option value="<?= $c ?>" <?= ($saved['snmp_community_field'] ?? '') == $c ? 'selected' : '' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-2">
    <label>Nombres de Templates (separados por coma)</label>
    <input type="text" name="template_name" class="form-control form-control-sm" 
           placeholder="Template 1, Template 2" 
           value="<?= htmlspecialchars($saved['template_name'] ?? '') ?>" required>
    <small class="text-muted">Ej: Template Net Cisco, ICMP Ping</small>
</div>
            </div>
        </div>

        <div class="card card-secondary card-outline mb-3">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-boxes mr-2"></i>Inventario Zabbix</h3></div>
            <div class="card-body row">
                <?php foreach ($zabbix_inventory as $field => $label): ?>
                <div class="col-md-4 mb-2">
                    <label class="small"><?= $label ?></label>
                    <select name="inv_<?= $field ?>" class="form-control form-control-sm select2-mapping">
                        <option value="">-- Ignorar --</option>
                        <?php foreach($columns as $c): ?>
                            <option value="<?= $c ?>" <?= ($saved_inv[$field] ?? '') == $c ? 'selected' : '' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card card-dark card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-tags mr-2"></i>Tags de Host</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" id="add-tag-row"><i class="fas fa-plus"></i> Añadir Tag</button>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0" id="tags-table">
                    <thead>
                        <tr>
                            <th>Nombre del Tag</th>
                            <th>Valor (Columna CMDB)</th>
                            <th style="width: 40px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($saved_tags)): foreach($saved_tags as $tag_name => $col_val): ?>
                        <tr>
                            <td><input type="text" name="tag_names[]" class="form-control form-control-sm" value="<?= htmlspecialchars($tag_name) ?>"></td>
                            <td>
                                <select name="tag_cols[]" class="form-control form-control-sm">
                                    <?php foreach($columns as $c) echo "<option value='$c' ".($col_val == $c ? 'selected' : '').">$c</option>"; ?>
                                </select>
                            </td>
                            <td><button type="button" class="btn btn-xs btn-danger remove-tag"><i class="fas fa-times"></i></button></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td><input type="text" name="tag_names[]" class="form-control form-control-sm" placeholder="Ej: Pais"></td>
                            <td>
                                <select name="tag_cols[]" class="form-control form-control-sm">
                                    <?php foreach($columns as $c) echo "<option value='$c' ".($c == 'pais' ? 'selected' : '').">$c</option>"; ?>
                                </select>
                            </td>
                            <td><button type="button" class="btn btn-xs btn-danger remove-tag"><i class="fas fa-times"></i></button></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <script>
        // Inyectar columnas en campos de texto
        $('.btn-append-val').on('change', function() {
            const col = $(this).val();
            const target = $(this).data('target');
            if(col) {
                $(target).val($(target).val() + '{' + col + '}');
                $(this).val('');
            }
        });

        // Agregar fila de Tag
        $('#add-tag-row').on('click', function() {
            const row = `<tr>
                <td><input type="text" name="tag_names[]" class="form-control form-control-sm"></td>
                <td>
                    <select name="tag_cols[]" class="form-control form-control-sm">
                        <?php foreach($columns as $c) echo "<option value='$c'>$c</option>"; ?>
                    </select>
                </td>
                <td><button type="button" class="btn btn-xs btn-danger remove-tag"><i class="fas fa-times"></i></button></td>
            </tr>`;
            $('#tags-table tbody').append(row);
        });

        $(document).on('click', '.remove-tag', function() { $(this).closest('tr').remove(); });

        $('.select2-mapping').select2({ width: '100%', dropdownParent: $('#zabbixMappingModal') });
    </script>
    <?php
    exit;
}

if ($action === 'update_zabbix_host') {
    header('Content-Type: application/json');
    try {
        $pdo = getPDO();
        $table_name = $_POST['table_name'] ?? '';
        $row_id = filter_input(INPUT_POST, 'row_id', FILTER_VALIDATE_INT);
        $zabbix_host_id = $_POST['zabbix_host_id'] ?? '';

        if (!$table_name || !$row_id || !$zabbix_host_id) throw new Exception("Faltan parámetros básicos.");

        // 1. OBTENER MAPEO
        $stmt_map = $pdo->prepare("SELECT * FROM zabbix_mappings WHERE cmdb_table_name = :tbl LIMIT 1");
        $stmt_map->execute([':tbl' => $table_name]);
        $mapping = $stmt_map->fetch(PDO::FETCH_ASSOC);

        if (!is_array($mapping)) {
            throw new Exception("Configuración de mapeo no encontrada para '$table_name'.");
        }

        // 2. OBTENER FILA DEL EQUIPO + DATA DE LOCALIDAD (JOIN)
        $sql_row = "
            SELECT e.*, l.latitud, l.longitud 
            FROM `$table_name` AS e
            LEFT JOIN sheet_localidades AS l ON e.sucursal = l.localidades
            WHERE e.id = :id
        ";
        $stmt_row = $pdo->prepare($sql_row);
        $stmt_row->execute([':id' => $row_id]);
        $row = $stmt_row->fetch(PDO::FETCH_ASSOC);

        if (!$row) throw new Exception("Equipo ID $row_id no encontrado.");

        // --- HELPER: PARSER ---
        $parser = function($text, $data) {
            return preg_replace_callback('/\{([^}]+)\}/', function($m) use ($data) {
                return (isset($data[$m[1]])) ? (string)$data[$m[1]] : '';
            }, (string)$text);
        };

        // 3. PROCESAR NOMBRES Y LIMPIEZA
        $h_raw = $parser($mapping['hostname_template'], $row);
        $v_raw = $parser($mapping['visible_name_template'], $row) ?: $h_raw;
        $visible_name = trim(str_replace(["\xc2\xa0", "\xa0"], ' ', $v_raw));
        $hostname = preg_replace('/[\s\x{00a0}]+/u', '_', $h_raw);
        $hostname = preg_replace('/[^A-Za-z0-9\.\-_]/', '', $hostname);
        $hostname = trim($hostname, '_');

        if (empty($hostname)) throw new Exception("Nombre técnico vacío tras limpieza.");

        // 4. DATOS DE RED Y GRUPO
        $hostgroup_name = $parser($mapping['hostgroup_template'], $row);
        $ip_final = $row[$mapping['ip_field']] ?? '';
        $community = $row[$mapping['snmp_community_field']] ?? 'public';

        if (empty($ip_final)) throw new Exception("IP no encontrada.");

        // 5. GESTIÓN DE GRUPO
        $group_res = call_zabbix_api('hostgroup.get', ['filter' => ['name' => $hostgroup_name]]);
        if (empty($group_res['result'])) {
            $g_create = call_zabbix_api('hostgroup.create', ['name' => $hostgroup_name]);
            $group_id = $g_create['result']['groupids'][0] ?? null;
        } else {
            $group_id = $group_res['result'][0]['groupid'];
        }

        // 6. GESTIÓN DE TEMPLATES
        $template_names = array_map('trim', explode(',', $mapping['template_name']));
        $tpl_res = call_zabbix_api('template.get', ['output' => ['templateid'], 'filter' => ['host' => $template_names]]);
        if (empty($tpl_res['result'])) throw new Exception("Templates no encontrados en Zabbix.");
        
        $template_ids_payload = array_map(function($t) { return ['templateid' => $t['templateid']]; }, $tpl_res['result']);

        // 7. PREPARAR INVENTARIO
        $inventory_data = [];
        $saved_inv_mapping = json_decode($mapping['inventory_fields_json'] ?? '[]', true);
        foreach ($saved_inv_mapping as $z_field => $cmdb_col) {
            if (!empty($cmdb_col) && isset($row[$cmdb_col]) && $row[$cmdb_col] !== '') {
                $inventory_data[$z_field] = (string)$row[$cmdb_col];
            } else {
                $inventory_data[$z_field] = ''; // Limpiar campos vacíos
            }
        }

        // Tags Dinámicos
        $tags_data = [];
        $saved_tags_mapping = json_decode($mapping['tags_json'] ?? '[]', true);
        foreach ($saved_tags_mapping as $tag_n => $cmdb_c) {
            if (!empty($row[$cmdb_c])) {
                $tags_data[] = ['tag' => (string)$tag_n, 'value' => (string)$row[$cmdb_c]];
            }
        }

        // 8. OBTENER INTERFACES ACTUALES PARA ACTUALIZAR
        $host_get = call_zabbix_api('host.get', [
            'hostids' => $zabbix_host_id,
            'selectInterfaces' => 'extend'
        ]);
        if (empty($host_get['result'])) throw new Exception("No se encontró el host en Zabbix con ID $zabbix_host_id");
        $interface_id = $host_get['result'][0]['interfaces'][0]['interfaceid'];

        // 9. CONSTRUCCIÓN DEL PAYLOAD FINAL (host.update)
        // Se enfoca en: Grupos, Templates, Macros, Inventario y Tags.
        $params = [
            'hostid'   => $zabbix_host_id,
            'groups'   => [['groupid' => $group_id]],
            'templates'=> $template_ids_payload,
            'macros'   => [[
                'macro' => '{$SNMP_COMMUNITY}',
                'value' => $community
            ]],
            'inventory_mode' => 1,
            'inventory'      => $inventory_data,
            'tags'           => $tags_data
        ];

        // Opcional: Si deseas mantener el nombre sincronizado descomenta estas líneas
        // $params['host'] = $hostname;
        // $params['name'] = $visible_name;

        $response = call_zabbix_api('host.update', $params);

        if (isset($response['error'])) {
            $err_msg = $response['error']['data'] ?? $response['error']['message'];
            throw new Exception("API Zabbix: " . $err_msg);
        }

        echo json_encode(['success' => true, 'log' => "Éxito: Actualizado '$hostname' (ID: $zabbix_host_id)"]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'log' => "ERROR: " . $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_zabbix_host') {
    header('Content-Type: application/json');
    try {
        $pdo = getPDO();
        $table_name = $_POST['table_name'] ?? '';
        $row_id = (int)($_POST['row_id'] ?? 0);
        $zabbix_host_id = $_POST['zabbix_host_id'] ?? '';

        if (!$table_name || !$row_id || !$zabbix_host_id) {
            throw new Exception("Faltan parámetros para la eliminación.");
        }

        // 1. Llamar a la API de Zabbix para eliminar el host
        $response = call_zabbix_api('host.delete', [$zabbix_host_id]);

        if (isset($response['error'])) {
            $err_msg = $response['error']['data'] ?? $response['error']['message'];
            throw new Exception("API Zabbix: " . $err_msg);
        }

        // 2. Limpiar el ID de Zabbix en la tabla de la CMDB
        $stmt = $pdo->prepare("UPDATE `$table_name` SET zabbix_host_id = NULL WHERE id = ?");
        $stmt->execute([$row_id]);

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'list_snmp_communities') {
    try {
        $pdo = getPDO();
        $stmt = $pdo->query("SELECT * FROM snmp_communities ORDER BY community ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'save_snmp_community') {
    try {
        $pdo = getPDO();
        $id = (int)($_POST['id'] ?? 0);
        $comm = trim($_POST['community'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        
        if (empty($comm)) throw new Exception("La comunidad es obligatoria.");
        
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE snmp_communities SET community = ?, description = ? WHERE id = ?");
            $stmt->execute([$comm, $desc, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO snmp_communities (community, description) VALUES (?, ?)");
            $stmt->execute([$comm, $desc]);
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
         echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_snmp_community') {
    try {
        $pdo = getPDO();
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM snmp_communities WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
         echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_snmp_scan_data') {
    try {
        $pdo = getPDO();
        $tables = listSheetTables();
        $all_ips = [];
        
        foreach ($tables as $t) {
            $cols = getTableColumns($t);
            $ip_col = null;
            $name_col = null;
            
            foreach ($cols as $c) {
                $c_low = strtolower($c);
                if (in_array($c_low, ['ip', 'ipaddress', 'direccion_ip', 'ip_address', 'host'])) { $ip_col = $c; }
                if (in_array($c_low, ['nombre', 'name', 'hostname', 'device_name'])) { $name_col = $c; }
            }
            
            if ($ip_col) {
                // Join con historial para saber si ya fue validado
                $sql = "
                    SELECT t.id, t.`$ip_col` as ip, " . ($name_col ? "t.`$name_col`" : "''") . " as name,
                           h.community_ok, h.last_success, h.interfaces_up_json, h.status
                    FROM `$t` t
                    LEFT JOIN snmp_scan_results h ON h.ip = t.`$ip_col` AND h.table_source = '$t' AND h.row_id = t.id
                    WHERE t.`$ip_col` IS NOT NULL AND t.`$ip_col` != ''
                ";
                $rows = $pdo->query($sql)->fetchAll();
                foreach($rows as $r) {
                    $all_ips[] = [
                        'table' => $t,
                        'id' => $r['id'],
                        'ip' => $r['ip'],
                        'name' => $r['name'],
                        'display_name' => str_replace('sheet_', '', $t),
                        'community_ok' => $r['community_ok'],
                        'last_success' => $r['last_success'],
                        'interfaces_up_json' => $r['interfaces_up_json'],
                        'status' => $r['status'] ?? 'PENDING'
                    ];
                }
            }
        }
        $communities = $pdo->query("SELECT id, community, description FROM snmp_communities ORDER BY community ASC")->fetchAll();
        echo json_encode(['success' => true, 'ips' => $all_ips, 'communities' => $communities]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'commit_snmp_results') {
    try {
        $pdo = getPDO();
        $results = json_decode($_POST['results'] ?? '[]', true);
        if (empty($results)) throw new Exception("No hay datos para guardar.");
        
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            INSERT INTO snmp_scan_results (ip, community_ok, table_source, row_id, interfaces_up_json, last_success, status)
            VALUES (:ip, :comm, :src, :rid, :up, NOW(), :status)
            ON DUPLICATE KEY UPDATE community_ok = :comm, interfaces_up_json = :up, last_success = NOW(), status = :status
        ");
        
        $affected = 0;
        foreach ($results as $res) {
            // Logging para depuración
            error_log("Intentando guardar SNMP: IP=" . ($res['ip'] ?? 'N/A') . " Table=" . ($res['table'] ?? 'N/A') . " ID=" . ($res['id'] ?? 'N/A'));
            
            $stmt->execute([
                ':ip' => $res['ip'],
                ':comm' => $res['community'] ?? '',
                ':src' => $res['table'],
                ':rid' => $res['id'],
                ':up' => isset($res['interfaces']) ? json_encode($res['interfaces']) : '[]',
                ':status' => $res['status'] ?? 'SUCCESS'
            ]);
            $affected += $stmt->rowCount();
        }
        $pdo->commit();
        error_log("SNMP Commit exitoso. Filas afectadas: $affected");
        echo json_encode(['success' => true, 'affected' => $affected]);
    } catch (Exception $e) {
        if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
        error_log("Error en commit_snmp_results: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_snmp_scan_result') {
    try {
        $pdo = getPDO();
        $ip = $_POST['ip'] ?? '';
        $src = $_POST['table'] ?? '';
        $rid = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM snmp_scan_results WHERE ip = ? AND table_source = ? AND row_id = ?")
            ->execute([$ip, $src, $rid]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'test_single_snmp') {
    $ip = $_POST['ip'] ?? '';
    $community = $_POST['community'] ?? '';
    
    if (empty($ip) || empty($community)) {
        exit(json_encode(['success' => false, 'error' => 'IP y Comunidad requeridos.']));
    }
    
    // Test using snmpget for sysObjectID.0 (1.3.6.1.2.1.1.2.0)
    $cmd = "snmpget -v2c -c " . escapeshellarg($community) . " -t 1 -r 0 " . escapeshellarg($ip) . " 1.3.6.1.2.1.1.2.0 2>&1";
    $output = [];
    $rv = -1;
    exec($cmd, $output, $rv);
    
    if ($rv === 0) {
        // Discovery of UP interfaces
        // ifOperStatus = 1.3.6.1.2.1.2.2.1.8
        // ifDescr = 1.3.6.1.2.1.2.2.1.2
        $up_interfaces = [];
        $walk_cmd = "snmpwalk -v2c -c " . escapeshellarg($community) . " -t 1 -r 0 " . escapeshellarg($ip) . " 1.3.6.1.2.1.2.2.1.8 2>&1";
        $walk_output = [];
        exec($walk_cmd, $walk_output);
        
        foreach ($walk_output as $line) {
            // Example: .1.3.6.1.2.1.2.2.1.8.1 = INTEGER: up(1)
            if (preg_match('/\.(\d+) = INTEGER: .*?\(1\)/', $line, $m)) {
                $index = $m[1];
                // Get Description for this index
                $desc_cmd = "snmpget -v2c -c " . escapeshellarg($community) . " -t 0.5 -r 0 " . escapeshellarg($ip) . " 1.3.6.1.2.1.2.2.1.2.$index 2>&1";
                $desc_out = [];
                exec($desc_cmd, $desc_out);
                if (isset($desc_out[0]) && preg_match('/STRING: (.*)/', $desc_out[0], $dm)) {
                    $up_interfaces[] = trim($dm[1], '" ');
                }
            }
        }

        exit(json_encode([
            'success' => true, 
            'status' => 'OK', 
            'response' => implode("\n", $output),
            'interfaces' => $up_interfaces
        ]));
    } else {
        $errText = implode("\n", $output);
        exit(json_encode(['success' => false, 'status' => 'FAIL', 'error' => $errText]));
    }
}

if ($action === 'check_snmp_port') {
    $ip = $_POST['ip'] ?? '';
    if (empty($ip)) exit(json_encode(['success' => false, 'error' => 'IP requerida.']));

    // 1. Quick Ping check (1 packet, 1 second timeout)
    $ping_cmd = "ping -c 1 -W 1 " . escapeshellarg($ip) . " > /dev/null 2>&1";
    $ping_rv = -1;
    exec($ping_cmd, $output, $ping_rv);

    if ($ping_rv !== 0) {
        exit(json_encode(['success' => true, 'online' => false, 'reason' => 'Host inalcanzable (Ping)']));
    }

    // 2. Probar envío de un paquete SNMP rápido (200ms) con comunidad pública
    // Solo para ver si el puerto responde ALGO o si el host está realmente vivo para UDP
    $snmp_probe = "snmpget -v2c -c public -t 0.2 -r 0 " . escapeshellarg($ip) . " 1.3.6.1.2.1.1.2.0 > /dev/null 2>&1";
    $snmp_rv = -1;
    exec($snmp_probe, $out, $snmp_rv);
    
    // Si snmp_rv es 0, ya sabemos que 'public' funciona, pero igual devolvemos online=true 
    // para que el ciclo principal haga su trabajo y registre el éxito correctamente.
    exit(json_encode(['success' => true, 'online' => true]));
}

exit(json_encode(['success'=>false,'error'=>'Accion desconocida']));
