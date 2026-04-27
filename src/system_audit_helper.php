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
                    'users' => 'Usuarios del sistema',
                    'roles' => 'Roles y permisos',
                    'asset_sequence' => 'Secuencia de activos',
                    'sheet_configs' => 'Configuración de hojas',
                    'user_sheet_permissions' => 'Permisos de hojas',
                    'user_module_permissions' => 'Permisos de módulos',
                    'import_logs' => 'Logs de importación',
                    'snmp_communities' => 'Comunidades SNMP',
                    'snmp_scan_results' => 'Resultados de escaneo',
                    'zabbix_api_config' => 'Configuración API Zabbix',
                    'zabbix_mappings' => 'Mapeos Zabbix',
                    'images' => 'Galería de imágenes',
                    'sheet_history' => 'Historial de cambios',
                    'zabbix_costs_rules' => 'Reglas de costos Zabbix',
                    'host_interfaces' => 'Gestión de Interfaces y Conexiones',
                    'zabbix_cmdb_config' => 'Configuración Global Zabbix/CMDB',
                    'zabbix_keywords' => 'Palabras Clave de Monitoreo'
                ];
                $existing = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                $table_status = [];
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
                    'zabbix_costs_rules' => 'Reglas de Costos'
                ];
                
                $existing = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                $table_status = [];
                $missing = [];

                // Definición de columnas esperadas para auditoría profunda
                $expected_schema = [
                    'snmp_scan_results' => ['ip', 'community_ok', 'interfaces_up_json', 'status'],
                    'host_interfaces' => ['hostid', 'interface_name', 'connected_hostid'],
                    'users' => ['username', 'password', 'role_id'],
                    'zabbix_costs_rules' => ['groupid', 'hourly_rate_capacity', 'hourly_rate_utilized']
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
        "user_sheet_perms" => "CREATE TABLE IF NOT EXISTS user_sheet_permissions (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, sheet_name VARCHAR(100) NOT NULL, can_view TINYINT(1) DEFAULT 1, can_edit TINYINT(1) DEFAULT 0, can_delete TINYINT(1) DEFAULT 0, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, UNIQUE KEY (user_id, sheet_name))",
        "user_mod_perms" => "CREATE TABLE IF NOT EXISTS user_module_permissions (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, module_name VARCHAR(100) NOT NULL, can_access TINYINT(1) DEFAULT 1, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, UNIQUE KEY (user_id, module_name))",
        "import_logs" => "CREATE TABLE IF NOT EXISTS import_logs (id INT AUTO_INCREMENT PRIMARY KEY, filename VARCHAR(255), table_name VARCHAR(100), total_rows INT, imported_rows INT, errors TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
        "zabbix_api_config" => "CREATE TABLE IF NOT EXISTS zabbix_api_config (id INT AUTO_INCREMENT PRIMARY KEY, url VARCHAR(255) NOT NULL, token VARCHAR(255) NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)",
        "snmp_communities" => "CREATE TABLE IF NOT EXISTS `snmp_communities` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, community VARCHAR(255) NOT NULL, description TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY (name))",
        "snmp_scan_results" => "CREATE TABLE IF NOT EXISTS snmp_scan_results (id INT AUTO_INCREMENT PRIMARY KEY, ip VARCHAR(50) NOT NULL, table_source VARCHAR(100) NOT NULL, row_id VARCHAR(100) NOT NULL, community_ok VARCHAR(255), interfaces_up_json LONGTEXT, status VARCHAR(20) DEFAULT 'PENDING', last_success DATETIME, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY idx_ip_rel (ip, table_source, row_id))",
        "host_interfaces" => "CREATE TABLE IF NOT EXISTS host_interfaces (id INT AUTO_INCREMENT PRIMARY KEY, hostid VARCHAR(50) NOT NULL, interface_index VARCHAR(100), interface_name VARCHAR(255), interface_type VARCHAR(50), alias TEXT, vlan VARCHAR(50), status VARCHAR(20), bits_received BIGINT DEFAULT 0, bits_sent BIGINT DEFAULT 0, connected_hostid VARCHAR(50), updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY (hostid, interface_name))",
        "zabbix_cmdb_config" => "CREATE TABLE IF NOT EXISTS zabbix_cmdb_config (id INT AUTO_INCREMENT PRIMARY KEY, setting_key VARCHAR(100) NOT NULL, setting_value TEXT, UNIQUE KEY (setting_key))",
        "zabbix_keywords" => "CREATE TABLE IF NOT EXISTS zabbix_keywords (id INT AUTO_INCREMENT PRIMARY KEY, keyword VARCHAR(100) NOT NULL, category VARCHAR(50), UNIQUE KEY (keyword))",
        "zabbix_mappings" => "CREATE TABLE IF NOT EXISTS zabbix_mappings (cmdb_table_name VARCHAR(255) PRIMARY KEY, hostname_template VARCHAR(255), visible_name_template VARCHAR(255), hostgroup_template VARCHAR(255), ip_field VARCHAR(100), snmp_community_field VARCHAR(100), template_name VARCHAR(255), inventory_fields_json TEXT, tags_json TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)",
        "images" => "CREATE TABLE IF NOT EXISTS images (id INT AUTO_INCREMENT PRIMARY KEY, entity_type VARCHAR(100), entity_id INT, filepath VARCHAR(255), filename VARCHAR(255), uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
        "sheet_history" => "CREATE TABLE IF NOT EXISTS sheet_history (id INT AUTO_INCREMENT PRIMARY KEY, table_name VARCHAR(255), row_id INT, action VARCHAR(20), changed_by VARCHAR(255), old_data LONGTEXT, new_data LONGTEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
        "zabbix_costs_rules" => "CREATE TABLE IF NOT EXISTS zabbix_costs_rules (id INT AUTO_INCREMENT PRIMARY KEY, groupid VARCHAR(50), hourly_rate_capacity DECIMAL(10,4), hourly_rate_utilized DECIMAL(10,4), currency VARCHAR(10) DEFAULT 'USD', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)"
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
        $log[] = "✅ Datos base (roles) verificados.";
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
