<?php
// Cargar configuración central de la plataforma
require_once __DIR__ . '/../../../../../config.php';

// Configuraciones de rendimiento específicas del monitor
if (!defined('CACHE_ENABLED')) {
    define('CACHE_ENABLED', true);
}
if (!defined('CACHE_TTL')) {
    define('CACHE_TTL', 60); // 1 minuto
}
?>
