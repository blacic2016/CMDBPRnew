<?php
// get_hostgroups.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$host_groups_file = __DIR__ . '/hostgroups.txt';
$host_group_names = [];

if (file_exists($host_groups_file)) {
    $host_group_names = array_map('trim', file($host_groups_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    echo json_encode($host_group_names);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Error: No se encontró el archivo hostgroups.txt.']);
}
?>