<?php
/**
 * get_hostgroups.php
 * Ubicación: /var/www/html/VILASECA/CMDBPRnew/public/informes/get_hostgroups.php
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_login();

header('Content-Type: application/json');

$host_groups_file = __DIR__ . '/hostgroups.txt';
$host_group_names = [];

if (file_exists($host_groups_file)) {
    $host_group_names = array_map('trim', file($host_groups_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    echo json_encode($host_group_names);
} else {
    http_response_code(500); 
    echo json_encode(['error' => 'Error: No se encontró el archivo hostgroups.txt.']);
}
?>
