<?php
/**
 * Archivo de Configuración Centralizado
 * 
 * IMPORTANTE: Este archivo contiene credenciales sensibles.
 * Asegúrate de que tenga permisos restrictivos (chmod 600) y
 * considera moverlo fuera del webroot en producción.
 */

declare(strict_types=1);

// === CARGAR CONFIGURACIÓN CENTRAL ===
require_once __DIR__ . '/../../../../config.php';

// === CONFIGURACIÓN ZABBIX ===
// Se heredan ZABBIX_API_URL y ZABBIX_API_TOKEN de config.php central
if (!defined('ZABBIX_API_URL')) {
    define('ZABBIX_API_URL', 'http://172.32.1.50/zabbix/api_jsonrpc.php');
}
if (!defined('ZABBIX_API_TOKEN')) {
    define('ZABBIX_API_TOKEN', '23c5e835efd1c26742b6848ee63b2547ce5349efb88b4ecefee83fa27683cb9a');
}

// === CONFIGURACIÓN DE BASE DE DATOS ===
define('DB_HOST', DB_CONFIG['host'] ?? 'localhost');
define('DB_USER', DB_CONFIG['user'] ?? 'root');
define('DB_PASSWORD', DB_CONFIG['password'] ?? 'zabbix');
define('DB_NAME', 'SONDAIOC');
define('DB_TABLE', 'inventario_equipos');

// === CONFIGURACIÓN DE AUTENTICACIÓN ===
// Hashes de contraseñas (generados con password_hash)
// Para generar nuevos hashes: password_hash('tu_password', PASSWORD_BCRYPT)
// Contraseñas originales: 'Sonda2023.admin', 'Sonda2025_serverBOC'
const ADMIN_PASSWORD_HASHES = [
    '$2y$12$FgISwCaglybbHcpBpQvsh.5ZjNlmfUPinZK.9Kh0U6XMnN13Scxn.', // Sonda2023.admin
    '$2y$12$HmeYA8LyDGvcZzXOi9gvIe/1Qu0TS8ZKTj1E/xuXyecvpYFXVOseq'  // Sonda2025_serverBOC
];

// Función para verificar contraseña de forma segura
function verify_admin_password(string $password): bool {
    foreach (ADMIN_PASSWORD_HASHES as $hash) {
        if (password_verify($password, $hash)) {
            return true;
        }
    }
    return false;
}

// === CONFIGURACIÓN DE LOGGING ===
define('LOG_ENABLED', true);
define('LOG_FILE', __DIR__ . '/logs/app.log');
define('LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB

// Función de logging estructurado
function log_message(string $level, string $message, array $context = []): void {
    if (!LOG_ENABLED) {
        return;
    }
    
    $logDir = dirname(LOG_FILE);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    $logEntry = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
    
    // Rotación de logs si el archivo es muy grande
    if (file_exists(LOG_FILE) && filesize(LOG_FILE) > LOG_MAX_SIZE) {
        $backupFile = LOG_FILE . '.' . date('Y-m-d_His');
        @rename(LOG_FILE, $backupFile);
    }
    
    @file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}

// === CONFIGURACIÓN DE SEGURIDAD ===
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_MAX_ATTEMPTS', 5);
define('RATE_LIMIT_WINDOW', 300); // 5 minutos en segundos

// Función para verificar rate limiting
function check_rate_limit(string $identifier): bool {
    if (!RATE_LIMIT_ENABLED) {
        return true;
    }
    
    $rateLimitFile = __DIR__ . '/logs/rate_limit.json';
    $rateLimitData = [];
    
    if (file_exists($rateLimitFile)) {
        $rateLimitData = json_decode(file_get_contents($rateLimitFile), true) ?? [];
    }
    
    $now = time();
    $key = md5($identifier);
    
    // Limpiar entradas antiguas
    $rateLimitData = array_filter($rateLimitData, function($entry) use ($now) {
        return ($now - $entry['timestamp']) < RATE_LIMIT_WINDOW;
    });
    
    // Contar intentos del identificador
    $attempts = 0;
    foreach ($rateLimitData as $entry) {
        if ($entry['identifier'] === $key) {
            $attempts++;
        }
    }
    
    if ($attempts >= RATE_LIMIT_MAX_ATTEMPTS) {
        log_message('WARNING', 'Rate limit exceeded', ['identifier' => $identifier]);
        return false;
    }
    
    // Registrar nuevo intento
    $rateLimitData[] = [
        'identifier' => $key,
        'timestamp' => $now
    ];
    
    @file_put_contents($rateLimitFile, json_encode($rateLimitData), LOCK_EX);
    return true;
}

// === CONFIGURACIÓN DE CORS Y HEADERS DE SEGURIDAD ===
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
