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
                
                // Chequeo de tablas críticas
                $critical_tables = [
                    'users', 'roles', 'asset_sequence', 'sheet_configs',
                    'user_sheet_permissions', 'user_module_permissions', 'import_logs',
                    'snmp_communities', 'snmp_scan_results', 'zabbix_api_config', 'zabbix_mappings',
                    'images', 'sheet_history'
                ];
                $existing = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                $missing = [];
                foreach ($critical_tables as $table) {
                    if (!in_array($table, $existing)) $missing[] = $table;
                }
                $results['database']['missing_tables'] = $missing;
                if (!empty($missing)) {
                    $results['database']['status'] = 'warning';
                    $results['database']['message'] = "Tablas faltantes: " . implode(', ', $missing);
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
        "user_module_perms" => "CREATE TABLE IF NOT EXISTS user_module_permissions (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, module_name VARCHAR(100) NOT NULL, can_view TINYINT(1) DEFAULT 1, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, UNIQUE KEY (user_id, module_name))"
    ];

    foreach ($queries as $name => $sql) {
        try {
            $pdo->exec($sql);
            $log[] = "✅ Estructura confirmada para: $name";
        } catch (Exception $e) {
            $log[] = "⚠️ Error creando tabla $name: " . $e->getMessage();
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
