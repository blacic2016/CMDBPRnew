<?php
/**
 * CMDB VILASECA - Script de Análisis de Acceso
 * Propósito: Analizar la conectividad a la base de datos y el estado de las cuentas de usuario.
 */

// Detectar si se corre desde CLI para el formato de salida
$is_cli = (php_sapi_name() === 'cli');
$newline = $is_cli ? "\n" : "<br>";
$bold = $is_cli ? "\033[1m" : "<strong>";
$reset = $is_cli ? "\033[0m" : "</strong>";
$green = $is_cli ? "\033[32m" : "<span style='color:green'>";
$red = $is_cli ? "\033[31m" : "<span style='color:red'>";
$yellow = $is_cli ? "\033[33m" : "<span style='color:orange'>";
$end_color = $is_cli ? "\033[0m" : "</span>";

if (!$is_cli) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Análisis de Acceso</title>";
    echo "<style>body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; color: #333; padding: 20px; } 
          pre { background: #2d2d2d; color: #ccc; padding: 15px; border-radius: 8px; overflow-x: auto; }
          .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 20px; }
          h2 { color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 5px; }</style></head><body>";
    echo "<div class='card'><h2>🔍 Análisis de Acceso CMDB</h2>";
}

echo "{$bold}Iniciando análisis...{$reset}{$newline}{$newline}";

// 1. ANÁLISIS DE BASE DE DATOS
echo "{$bold}Step 1: Análisis de Base de Datos{$reset}$newline";
$config_path = __DIR__ . '/../config.php';

if (!file_exists($config_path)) {
    echo "{$red}❌ ERROR: Archivo config.php no encontrado.{$end_color}$newline";
    exit(1);
}

require_once $config_path;
require_once ROOT_PATH . '/src/db.php';

echo "Configuración cargada correctamente.$newline";
echo "Host: " . DB_CONFIG['host'] . "$newline";
echo "Base de datos: " . DB_CONFIG['database'] . "$newline";

$pdo = getPDO();

if ($pdo) {
    echo "{$green}✅ ÉXITO: Conexión a la base de datos establecida.{$end_color}$newline";
    
    // Verificar si la base de datos es la correcta
    $current_db = $pdo->query("SELECT DATABASE()")->fetchColumn();
    echo "Base de datos activa: $current_db$newline";
    
    // 2. ANÁLISIS DE TABLAS DE USUARIOS
    echo "$newline{$bold}Step 2: Análisis de Usuarios{$reset}$newline";
    
    try {
        // Verificar tabla de roles
        $roles_check = $pdo->query("SHOW TABLES LIKE 'roles'")->fetch();
        if ($roles_check) {
            $roles_count = $pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
            echo "{$green}✅ Tabla 'roles' encontrada.{$end_color} ($roles_count roles definidos)$newline";
        } else {
            echo "{$red}❌ ERROR: Tabla 'roles' no existe.{$end_color}$newline";
        }

        // Verificar tabla de usuarios
        $users_check = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
        if ($users_check) {
            $users_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            echo "{$green}✅ Tabla 'users' encontrada.{$end_color} ($users_count usuarios registrados)$newline";
            
            // Listar usuarios y sus roles
            echo "$newline--- Listado de Usuarios ---$newline";
            $stmt = $pdo->query("SELECT u.username, r.name as role, u.created_at 
                               FROM users u 
                               JOIN roles r ON u.role_id = r.id");
            $users = $stmt->fetchAll();
            
            if (empty($users)) {
                echo "{$yellow}⚠️ ADVERTENCIA: No hay usuarios en la base de datos.{$end_color}$newline";
            } else {
                foreach ($users as $u) {
                    $u_name = str_pad($u['username'], 15);
                    $u_role = str_pad($u['role'], 15);
                    echo "- Usuario: {$bold}{$u_name}{$reset} | Rol: {$u_role} | Creado: {$u['created_at']}$newline";
                }
            }
            
            // Verificar si el superadmin existe específicamente
            $has_admin = false;
            foreach ($users as $u) {
                if (strtolower($u['username']) === 'superadmin' || $u['role'] === 'SUPER_ADMIN') {
                    $has_admin = true;
                    break;
                }
            }
            
            if ($has_admin) {
                echo "{$green}✅ Usuario de administración crítica detectado.{$end_color}$newline";
            } else {
                echo "{$red}❌ ERROR CRÍTICO: No se detectó un usuario con privilegios de SUPER_ADMIN.{$end_color}$newline";
            }

        } else {
            echo "{$red}❌ ERROR: Tabla 'users' no existe.{$end_color}$newline";
        }
        
    } catch (PDOException $e) {
        echo "{$red}❌ ERROR durante la consulta: " . $e->getMessage() . "{$end_color}$newline";
    }
} else {
    echo "{$red}❌ ERROR: No se pudo conectar. Verifica DB_CONFIG en config.php.{$end_color}$newline";
    
    // Intento de diagnóstico detallado sin base de datos
    echo "Intentando conexión base al motor MySQL...$newline";
    $pdo_base = getPDOWithoutDB();
    if ($pdo_base) {
        echo "{$green}✅ El motor responde, pero la base de datos '" . DB_CONFIG['database'] . "' podría no existir.{$end_color}$newline";
        $dbs = $pdo_base->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
        echo "Bases de datos disponibles: " . implode(", ", $dbs) . "$newline";
    } else {
        echo "{$red}❌ El motor MySQL no responde en el host indicado.{$end_color}$newline";
    }
}

echo "$newline{$bold}Análisis completado.{$reset}$newline";

if (!$is_cli) {
    echo "</div></body></html>";
}
