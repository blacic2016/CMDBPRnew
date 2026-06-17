<?php
/**
 * CMDB VILASECA - Lógica de Auditoría del Sistema
 */

function runSystemAudit() {
    $results = [
        'php' => [
            'version' => PHP_VERSION,
            'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'success' : 'warning',
            'message' => 'PHP ' . PHP_VERSION
        ],
        'extensions' => [],
        'directories' => [],
        'database' => [
            'connected' => false,
            'host' => 'N/A',
            'message' => ''
        ],
        'zabbix' => [
            'url' => 'N/A',
            'status' => 'info'
        ],
        'dependencies' => [
            'vendor' => file_exists(__DIR__ . '/../vendor/autoload.php')
        ]
    ];

    // Extensiones
    $required_extensions = [
        'pdo' => 'Bases de datos',
        'pdo_mysql' => 'MySQL/MariaDB',
        'curl' => 'API Zabbix',
        'json' => 'Datos JSON',
        'mbstring' => 'Textos UTF-8',
        'gd' => 'Imágenes',
        'zip' => 'Excel',
        'xml' => 'Excel',
        'dom' => 'Excel',
        'snmp' => 'Red/Escaneo'
    ];

    foreach ($required_extensions as $ext => $desc) {
        $loaded = extension_loaded($ext);
        $results['extensions'][$ext] = [
            'loaded' => $loaded,
            'description' => $desc,
            'status' => $loaded ? 'success' : 'error'
        ];
    }

    // Directorios
    $dirs = [
        'storage' => __DIR__ . '/../storage',
        'logs' => __DIR__ . '/../storage/logs',
        'sessions' => __DIR__ . '/../storage/sessions_fix',
        'uploads' => __DIR__ . '/../public/uploads',
        'vendor' => __DIR__ . '/../vendor'
    ];

    foreach ($dirs as $name => $path) {
        $exists = is_dir($path);
        $writable = $exists && is_writable($path);
        $results['directories'][$name] = [
            'path' => $path,
            'exists' => $exists,
            'writable' => $writable,
            'status' => $writable ? 'success' : 'error'
        ];
    }

    // DB
    if (defined('DB_CONFIG')) {
        $results['database']['host'] = DB_CONFIG['host'];
        try {
            $pdo = getPDO();
            if ($pdo) {
                $results['database']['connected'] = true;
                $results['database']['message'] = "Conexión exitosa";
                $results['database']['status'] = 'success';
                
                // Chequeo de tablas críticas con detalle individual
                $critical_tables = [
                    'roles' => 'Roles de Usuario',
                    'users' => 'Usuarios',
                    'asset_sequence' => 'Secuencias de Activos',
                    'sheet_configs' => 'Configuración de Hojas',
                    'user_sheet_permissions' => 'Permisos de Hojas',
                    'user_module_permissions' => 'Permisos de Módulos',
                    'import_logs' => 'Logs de Importación',
                    'zabbix_api_config' => 'Configuración API Zabbix',
                    'snmp_communities' => 'Comunidades SNMP',
                    'snmp_scan_results' => 'Resultados de Escaneo SNMP',
                    'host_interfaces' => 'Interfaces de Red CMDB',
                    'zabbix_cmdb_config' => 'Configuración Zabbix CMDB',
                    'zabbix_keywords' => 'Palabras Clave Zabbix',
                    'zabbix_mappings' => 'Mapeos de Inventario',
                    'images' => 'Repositorio de Imágenes',
                    'sheet_history' => 'Historial de Cambios',
                    'zabbix_costs_rules' => 'Reglas de Costos',
                    // CI Graph Tables
                    'ci_attributes' => 'Atributos de CI',
                    'ci_categories' => 'Categorías de CI',
                    'ci_instances' => 'Instancias de CI',
                    'ci_components' => 'Componentes de CI',
                    'ci_relationships' => 'Relaciones de CI',
                    // Datacenter Tables
                    'dc_rooms' => 'Salas de Datacenter',
                    'dc_racks' => 'Racks de Datacenter',
                    'dc_rack_devices' => 'Dispositivos en Racks',
                    'dc_floor_layers' => 'Capas de Planta de Datacenter',
                    'dc_floor_items' => 'Elementos en Piso de Sala'
                ];
                
                $existing = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                $table_status = [];
                $missing = [];

                // Definición de columnas esperadas para auditoría profunda
                $expected_schema = [
                    'snmp_scan_results' => ['ip', 'community_ok', 'interfaces_up_json', 'status'],
                    'host_interfaces' => ['hostid', 'interface_name', 'connected_hostid'],
                    'users' => ['username', 'password', 'role_id'],
                    'zabbix_costs_rules' => ['groupid', 'hourly_rate_capacity', 'hourly_rate_utilized'],
                    'dc_rooms' => ['name', 'floor_height_meters'],
                    'dc_racks' => ['room_id', 'rotation', 'z_index'],
                    'dc_rack_devices' => ['rack_id', 'start_u', 'height_u'],
                    'dc_floor_items' => ['room_id', 'layer_id', 'height_meters'],
                    'ci_categories' => ['name', 'description', 'created_at', 'created_by'],
                    'ci_instances' => ['category_id', 'hostname', 'status'],
                    'ci_attributes' => ['name', 'type', 'group_name'],
                    'ci_relationships' => ['source_type', 'source_id', 'target_type', 'target_id', 'relation_type', 'impact']
                ];

                foreach ($critical_tables as $table => $desc) {
                    $is_present = in_array($table, $existing);
                    $columns_ok = true;
                    $missing_cols = [];

                    if ($is_present) {
                        $cols = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_COLUMN);
                        // Verificar columnas críticas específicas si están definidas
                        if (isset($expected_schema[$table])) {
                            foreach ($expected_schema[$table] as $req_col) {
                                if (!in_array($req_col, $cols)) {
                                    $columns_ok = false;
                                    $missing_cols[] = $req_col;
                                }
                            }
                        }
                    }

                    $table_status[$table] = [
                        'exists' => $is_present,
                        'description' => $desc,
                        'columns_ok' => $columns_ok,
                        'missing_cols' => $missing_cols
                    ];
                    if (!$is_present || !$columns_ok) $missing[] = $table;
                }
                $results['database']['table_analysis'] = $table_status;
                $results['database']['missing_tables'] = $missing;
                if (!empty($missing)) {
                    $results['database']['status'] = 'warning';
                    $results['database']['message'] = "Estructura incompleta o desactualizada (" . count($missing) . " elementos)";
                } else {
                    $results['database']['status'] = 'success';
                    $results['database']['message'] = "Base de datos íntegra y actualizada.";
                }
            } else {
                $results['database']['status'] = 'error';
                $results['database']['message'] = "No se pudo obtener objeto PDO.";
            }
        } catch (Exception $e) {
            $results['database']['message'] = $e->getMessage();
            $results['database']['status'] = 'error';
        }
    }

    // Zabbix
    if (defined('ZABBIX_API_URL')) {
        $results['zabbix']['url'] = ZABBIX_API_URL;
        $results['zabbix']['status'] = (strpos(ZABBIX_API_URL, '172.32.1.50') !== false) ? 'warning' : 'success';
    }

    return $results;
}

/**
 * Intenta corregir problemas detectados (principalmente permisos)
 */
function fixSystemIssues() {
    $dirs = [
        'Storage Root' => __DIR__ . '/../storage',
        'Logs' => __DIR__ . '/../storage/logs',
        'Sessions' => __DIR__ . '/../storage/sessions_fix',
        'Uploads' => __DIR__ . '/../public/uploads'
    ];
    $log = [];
    foreach ($dirs as $name => $dir) {
        if (!is_dir($dir)) {
            if (@mkdir($dir, 0777, true)) {
                $log[] = "✅ Carpeta '$name' creada con éxito.";
            } else {
                $log[] = "❌ No se pudo crear la carpeta '$name'.";
                continue;
            }
        }
        
        if (@chmod($dir, 0777)) {
            $log[] = "✅ Permisos corregidos (777) en '$name'.";
        } else {
            $log[] = "⚠️ No se pudo cambiar permisos en '$name' (posiblemente falta de privilegios).";
        }
    }
    // 2. Ejecutar inicialización de base de datos
    $dbLogs = initializeDatabase();
    $log = array_merge($log, $dbLogs);

    return $log;
}

/**
 * Inicializa la estructura de la base de datos si falta algo
 */
function initializeDatabase()
{
    $pdo = getPDO();
    if (!$pdo) return ["❌ No se pudo conectar a la base de datos para inicializar."];

    $log = [];
    $queries = [
        "roles" => "CREATE TABLE IF NOT EXISTS `roles` (`id` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(50) NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `name` (`name`))",
        "users" => "CREATE TABLE IF NOT EXISTS `users` (`id` int(11) NOT NULL AUTO_INCREMENT, `username` varchar(100) NOT NULL, `password` varchar(255) NOT NULL, `role_id` int(11) NOT NULL, `created_at` datetime DEFAULT current_timestamp(), PRIMARY KEY (`id`), UNIQUE KEY `username` (`username`), CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`))",
        "asset_sequence" => "CREATE TABLE IF NOT EXISTS `asset_sequence` (`id` int(11) NOT NULL AUTO_INCREMENT, `prefix` varchar(10) NOT NULL DEFAULT 'AE', `last_id` int(11) NOT NULL DEFAULT 0, PRIMARY KEY (`id`))",
        "sheet_configs" => "CREATE TABLE IF NOT EXISTS `sheet_configs` (`id` int(11) NOT NULL AUTO_INCREMENT, `sheet_name` varchar(255) NOT NULL, `table_name` varchar(255) NOT NULL, `unique_columns` text DEFAULT NULL, `created_at` datetime DEFAULT current_timestamp(), PRIMARY KEY (`id`), UNIQUE KEY `sheet_name` (`sheet_name`), UNIQUE KEY `table_name` (`table_name`))",
        "user_sheet_permissions" => "CREATE TABLE IF NOT EXISTS user_sheet_permissions (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, sheet_name VARCHAR(100) NOT NULL, can_view TINYINT(1) DEFAULT 1, can_edit TINYINT(1) DEFAULT 0, can_delete TINYINT(1) DEFAULT 0, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, UNIQUE KEY (user_id, sheet_name))",
        "user_module_permissions" => "CREATE TABLE IF NOT EXISTS user_module_permissions (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, module_name VARCHAR(100) NOT NULL, can_access TINYINT(1) DEFAULT 1, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, UNIQUE KEY (user_id, module_name))",
        "import_logs" => "CREATE TABLE IF NOT EXISTS import_logs (id INT AUTO_INCREMENT PRIMARY KEY, filename VARCHAR(255), table_name VARCHAR(100), total_rows INT, imported_rows INT, errors TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
        "zabbix_api_config" => "CREATE TABLE IF NOT EXISTS zabbix_api_config (id INT AUTO_INCREMENT PRIMARY KEY, url VARCHAR(255) NOT NULL, token VARCHAR(255) NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)",
        "snmp_communities" => "CREATE TABLE IF NOT EXISTS `snmp_communities` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, community VARCHAR(255) NOT NULL, description TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY (name))",
        "snmp_scan_results" => "CREATE TABLE IF NOT EXISTS snmp_scan_results (id INT AUTO_INCREMENT PRIMARY KEY, ip VARCHAR(50) NOT NULL, table_source VARCHAR(255) NOT NULL, row_id VARCHAR(255) NOT NULL, community_ok VARCHAR(255), interfaces_up_json LONGTEXT, status VARCHAR(20) DEFAULT 'PENDING', last_success DATETIME, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY idx_ip_rel (ip, table_source, row_id))",
        "host_interfaces" => "CREATE TABLE IF NOT EXISTS host_interfaces (id INT AUTO_INCREMENT PRIMARY KEY, hostid VARCHAR(50) NOT NULL, interface_index VARCHAR(100), interface_name VARCHAR(255), interface_type VARCHAR(50), alias TEXT, vlan VARCHAR(50), status VARCHAR(20), bits_received BIGINT DEFAULT 0, bits_sent BIGINT DEFAULT 0, connected_hostid VARCHAR(50), updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY (hostid, interface_name))",
        "zabbix_cmdb_config" => "CREATE TABLE IF NOT EXISTS zabbix_cmdb_config (id INT AUTO_INCREMENT PRIMARY KEY, setting_key VARCHAR(100) NOT NULL, setting_value TEXT, UNIQUE KEY (setting_key))",
        "zabbix_keywords" => "CREATE TABLE IF NOT EXISTS zabbix_keywords (id INT AUTO_INCREMENT PRIMARY KEY, keyword VARCHAR(100) NOT NULL, category VARCHAR(50), UNIQUE KEY (keyword))",
        "zabbix_mappings" => "CREATE TABLE IF NOT EXISTS zabbix_mappings (cmdb_table_name VARCHAR(255) PRIMARY KEY, hostname_template VARCHAR(255), visible_name_template VARCHAR(255), hostgroup_template VARCHAR(255), ip_field VARCHAR(100), snmp_community_field VARCHAR(100), template_name VARCHAR(255), inventory_fields_json TEXT, tags_json TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)",
        "images" => "CREATE TABLE IF NOT EXISTS images (id INT AUTO_INCREMENT PRIMARY KEY, entity_type VARCHAR(100), entity_id INT, filepath VARCHAR(255), filename VARCHAR(255), uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
        "sheet_history" => "CREATE TABLE IF NOT EXISTS sheet_history (id INT AUTO_INCREMENT PRIMARY KEY, table_name VARCHAR(255), row_id INT, action VARCHAR(20), changed_by VARCHAR(255), old_data LONGTEXT, new_data LONGTEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
        "zabbix_costs_rules" => "CREATE TABLE IF NOT EXISTS zabbix_costs_rules (id INT AUTO_INCREMENT PRIMARY KEY, groupid VARCHAR(50), hourly_rate_capacity DECIMAL(10,4), hourly_rate_utilized DECIMAL(10,4), currency VARCHAR(10) DEFAULT 'USD', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)",
        
        // CI Graph Tables
        "ci_attributes" => "CREATE TABLE IF NOT EXISTS ci_attributes (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, type VARCHAR(50) NOT NULL DEFAULT 'string', group_name VARCHAR(100) DEFAULT 'General', description TEXT, is_required TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, created_by INT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ci_categories" => "CREATE TABLE IF NOT EXISTS ci_categories (id INT(11) NOT NULL AUTO_INCREMENT, parent_id INT(11) DEFAULT NULL, name VARCHAR(100) NOT NULL, description TEXT DEFAULT NULL, schema_json JSON DEFAULT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, created_by INT DEFAULT NULL, icon VARCHAR(50) DEFAULT 'fa-cube', PRIMARY KEY (id), FOREIGN KEY (parent_id) REFERENCES ci_categories(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ci_instances" => "CREATE TABLE IF NOT EXISTS ci_instances (id INT(11) NOT NULL AUTO_INCREMENT, category_id INT(11) NOT NULL, hostname VARCHAR(255) NOT NULL, ip_address VARCHAR(50) DEFAULT NULL, source ENUM('manual', 'zabbix') DEFAULT 'manual', zabbix_host_id VARCHAR(100) DEFAULT NULL, attributes_json JSON DEFAULT NULL, status ENUM('Planificación', 'Activo', 'Mantenimiento', 'Retirado') DEFAULT 'Activo', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, description TEXT DEFAULT NULL, created_by INT DEFAULT NULL, PRIMARY KEY (id), FOREIGN KEY (category_id) REFERENCES ci_categories(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ci_components" => "CREATE TABLE IF NOT EXISTS ci_components (id INT(11) NOT NULL AUTO_INCREMENT, parent_ci_id INT(11) NOT NULL, name VARCHAR(255) NOT NULL, attributes_json JSON DEFAULT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), FOREIGN KEY (parent_ci_id) REFERENCES ci_instances(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ci_relationships" => "CREATE TABLE IF NOT EXISTS ci_relationships (id INT(11) NOT NULL AUTO_INCREMENT, source_type VARCHAR(50) NOT NULL, source_id INT(11) NOT NULL, target_type VARCHAR(50) NOT NULL, target_id INT(11) NOT NULL, relation_type VARCHAR(50) NOT NULL, impact VARCHAR(50) DEFAULT 'Desconocido', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY idx_relation (source_type, source_id, target_type, target_id, relation_type)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Datacenter Tables
        "dc_rooms" => "CREATE TABLE IF NOT EXISTS dc_rooms (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, location_detail VARCHAR(255) DEFAULT NULL, width_meters DECIMAL(10,2) DEFAULT 6.00, length_meters DECIMAL(10,2) DEFAULT 6.00, tile_size DECIMAL(4,2) DEFAULT 0.60, floor_height_meters DECIMAL(10,2) DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "dc_racks" => "CREATE TABLE IF NOT EXISTS dc_racks (id INT AUTO_INCREMENT PRIMARY KEY, room_id INT NOT NULL, name VARCHAR(255) NOT NULL, total_u INT DEFAULT 42, grid_x INT DEFAULT 0, grid_y INT DEFAULT 0, width_tiles INT DEFAULT 1, depth_tiles INT DEFAULT 2, rotation INT NOT NULL DEFAULT 0, numbering_dir ENUM('UP','DOWN') NOT NULL DEFAULT 'DOWN', description TEXT DEFAULT NULL, z_index INT DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (room_id) REFERENCES dc_rooms(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "dc_rack_devices" => "CREATE TABLE IF NOT EXISTS dc_rack_devices (id INT AUTO_INCREMENT PRIMARY KEY, rack_id INT NOT NULL, name VARCHAR(255) NOT NULL, start_u INT NOT NULL, height_u INT NOT NULL, orientation VARCHAR(50) DEFAULT 'front', cmdb_reference VARCHAR(255) DEFAULT NULL, details_json TEXT DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (rack_id) REFERENCES dc_racks(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "dc_floor_layers" => "CREATE TABLE IF NOT EXISTS dc_floor_layers (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, z_index INT NOT NULL DEFAULT 10) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "dc_floor_items" => "CREATE TABLE IF NOT EXISTS dc_floor_items (id INT AUTO_INCREMENT PRIMARY KEY, room_id INT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, layer_id INT DEFAULT NULL, grid_x INT DEFAULT 0, grid_y INT DEFAULT 0, width_tiles INT DEFAULT 1, depth_tiles INT DEFAULT 1, height_meters DECIMAL(10,2) DEFAULT 0.00, rotation INT NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (room_id) REFERENCES dc_rooms(id) ON DELETE CASCADE, FOREIGN KEY (layer_id) REFERENCES dc_floor_layers(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($queries as $name => $sql) {
        try {
            $pdo->exec($sql);
            
            // Verificación extra para columnas si la tabla ya existía
            if ($name === 'snmp_scan_results') {
                $cols = $pdo->query("DESCRIBE `$name`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('interfaces_up_json', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN interfaces_up_json LONGTEXT AFTER row_id");
                    $log[] = "✅ Columna 'interfaces_up_json' añadida a $name.";
                }
                if (!in_array('status', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN status VARCHAR(20) DEFAULT 'PENDING' AFTER interfaces_up_json");
                    $log[] = "✅ Columna 'status' añadida a $name.";
                }
                if (!in_array('community_ok', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN community_ok VARCHAR(255) AFTER ip");
                    $log[] = "✅ Columna 'community_ok' añadida a $name.";
                }
                // Asegurar que row_id y table_source sean suficientemente largos
                $pdo->exec("ALTER TABLE `$name` MODIFY COLUMN table_source VARCHAR(255) NOT NULL");
                $pdo->exec("ALTER TABLE `$name` MODIFY COLUMN row_id VARCHAR(255) NOT NULL");
            }

            if ($name === 'host_interfaces') {
                $cols = $pdo->query("DESCRIBE `$name`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('connected_hostid', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN connected_hostid VARCHAR(50) AFTER bits_sent");
                    $log[] = "✅ Columna 'connected_hostid' añadida a $name.";
                }
                // Si existe 'name' pero no 'interface_name', hacer el rename
                if (in_array('name', $cols) && !in_array('interface_name', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` CHANGE COLUMN name interface_name VARCHAR(255)");
                    $log[] = "✅ Columna 'name' renombrada a 'interface_name' en $name.";
                } elseif (!in_array('interface_name', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN interface_name VARCHAR(255) AFTER interface_index");
                    $log[] = "✅ Columna 'interface_name' añadida a $name.";
                }
            }

            if ($name === 'zabbix_costs_rules') {
                $cols = $pdo->query("DESCRIBE `$name`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('groupid', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN groupid VARCHAR(50) AFTER id");
                    $log[] = "✅ Columna 'groupid' añadida a $name.";
                }
                if (!in_array('hourly_rate_capacity', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN hourly_rate_capacity DECIMAL(10,4) AFTER groupid");
                    $log[] = "✅ Columna 'hourly_rate_capacity' añadida a $name.";
                }
                if (!in_array('hourly_rate_utilized', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN hourly_rate_utilized DECIMAL(10,4) AFTER hourly_rate_capacity");
                    $log[] = "✅ Columna 'hourly_rate_utilized' añadida a $name.";
                }
                if (!in_array('currency', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN currency VARCHAR(10) DEFAULT 'USD' AFTER hourly_rate_utilized");
                    $log[] = "✅ Columna 'currency' añadida a $name.";
                }
            }

            // Verificaciones de columnas para dc_rooms
            if ($name === 'dc_rooms') {
                $cols = $pdo->query("DESCRIBE `$name`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('floor_height_meters', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN floor_height_meters DECIMAL(10,2) DEFAULT NULL AFTER tile_size");
                    $log[] = "✅ Columna 'floor_height_meters' añadida a $name.";
                }
            }

            // Verificaciones de columnas para dc_racks
            if ($name === 'dc_racks') {
                $cols = $pdo->query("DESCRIBE `$name`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('rotation', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN rotation INT NOT NULL DEFAULT 0 AFTER depth_tiles");
                    $log[] = "✅ Columna 'rotation' añadida a $name.";
                }
                if (!in_array('z_index', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN z_index INT DEFAULT NULL AFTER description");
                    $log[] = "✅ Columna 'z_index' añadida a $name.";
                }
            }

            // Verificaciones de columnas para dc_floor_items
            if ($name === 'dc_floor_items') {
                $cols = $pdo->query("DESCRIBE `$name`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('height_meters', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN height_meters DECIMAL(10,2) DEFAULT 0.00 AFTER depth_tiles");
                    $log[] = "✅ Columna 'height_meters' añadida a $name.";
                }
                if (!in_array('rotation', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN rotation INT NOT NULL DEFAULT 0 AFTER height_meters");
                    $log[] = "✅ Columna 'rotation' añadida a $name.";
                }
            }

            // Verificaciones de columnas para ci_categories
            if ($name === 'ci_categories') {
                $cols = $pdo->query("DESCRIBE `$name`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('description', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN description TEXT DEFAULT NULL AFTER name");
                    $log[] = "✅ Columna 'description' añadida a $name.";
                }
                if (!in_array('created_at', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER description");
                    $log[] = "✅ Columna 'created_at' añadida a $name.";
                }
                if (!in_array('created_by', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN created_by INT DEFAULT NULL AFTER created_at");
                    $log[] = "✅ Columna 'created_by' añadida a $name.";
                }
                if (!in_array('icon', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN icon VARCHAR(50) DEFAULT 'fa-cube' AFTER created_at");
                    $log[] = "✅ Columna 'icon' añadida a $name.";
                }
            }

            // Verificaciones de columnas para ci_instances
            if ($name === 'ci_instances') {
                $cols = $pdo->query("DESCRIBE `$name`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('description', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN description TEXT DEFAULT NULL AFTER updated_at");
                    $log[] = "✅ Columna 'description' añadida a $name.";
                }
                if (!in_array('created_by', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN created_by INT DEFAULT NULL AFTER description");
                    $log[] = "✅ Columna 'created_by' añadida a $name.";
                }
            }

            // Verificaciones de columnas para ci_attributes
            if ($name === 'ci_attributes') {
                $cols = $pdo->query("DESCRIBE `$name`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('group_name', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN group_name VARCHAR(100) DEFAULT 'General' AFTER type");
                    $log[] = "✅ Columna 'group_name' añadida a $name.";
                }
            }

            // Verificaciones de columnas para ci_relationships
            if ($name === 'ci_relationships') {
                $cols = $pdo->query("DESCRIBE `$name`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('impact', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN impact VARCHAR(50) DEFAULT 'Desconocido' AFTER relation_type");
                    $log[] = "✅ Columna 'impact' añadida a $name.";
                }
            }
            
            $log[] = "✅ Estructura confirmada para: $name";
        } catch (Exception $e) {
            $log[] = "⚠️ Error remediando $name: " . $e->getMessage();
        }
    }

    // Seeds
    try {
        $pdo->exec("INSERT IGNORE INTO roles (id, name) VALUES (1, 'SUPER_ADMIN'), (2, 'ADMIN'), (3, 'USER')");
        
        // Crear superadmin por defecto si no hay usuarios
        $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($count == 0) {
            $pass = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->exec("INSERT INTO users (username, password, role_id) VALUES ('admin', '$pass', 1)");
            $log[] = "👤 Usuario inicial 'admin' creado (Clave: admin123).";
        }
        
        // Seed dc_floor_layers
        $countLayers = $pdo->query("SELECT COUNT(*) FROM dc_floor_layers")->fetchColumn();
        if ($countLayers == 0) {
            $pdo->exec("INSERT INTO dc_floor_layers (name, z_index) VALUES 
                ('Piso Perforado', 1), 
                ('Aire Acondicionado', 5), 
                ('Racks', 10), 
                ('UPS', 11), 
                ('Escalerillas', 20)");
            $log[] = "✅ Capas de piso por defecto (dc_floor_layers) insertadas.";
        }
        
        $log[] = "✅ Datos base (roles, capas) verificados.";
    } catch (Exception $e) {
        $log[] = "⚠️ Error al insertar datos base: " . $e->getMessage();
    }

    return $log;
}

/**
 * Genera sugerencias de comandos de terminal para corregir errores manuales
 */
function getTerminalSuggestions($audit)
{
    $cmds = [];
    $root = ROOT_PATH;

    // Extensiones faltantes
    $missing_exts = [];
    foreach ($audit['extensions'] as $ext => $info) {
        if (!$info['loaded']) {
            // Ajuste para nombres de paquetes comunes
            if ($ext === 'pdo_mysql') continue; // Viene con pdo
            $missing_exts[] = "php-$ext";
        }
    }
    if (!empty($missing_exts)) {
        $cmds[] = "# Instalación de extensiones faltantes:";
        $cmds[] = "sudo apt-get update && sudo apt-get install -y " . implode(' ', $missing_exts) . " && sudo systemctl restart apache2";
    }

    // Permisos de carpetas
    $broken_dirs = [];
    foreach ($audit['directories'] as $name => $info) {
        if (!$info['writable']) $broken_dirs[] = $info['path'];
    }
    if (!empty($broken_dirs)) {
        $cmds[] = "# Corrección de permisos y dueños:";
        $cmds[] = "sudo chown -R www-data:www-data " . implode(' ', array_map('escapeshellarg', $broken_dirs));
        $cmds[] = "sudo chmod -R 777 " . implode(' ', array_map('escapeshellarg', $broken_dirs));
    }

    return $cmds;
}
