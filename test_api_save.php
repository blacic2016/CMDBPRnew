<?php
session_start();
$_SESSION['user_id'] = 1;
$_POST = [
    'action' => 'save_instance',
    'category_id' => 29,
    'hostname' => 'Test_Pais_Debug',
    'status' => 'Activo',
    'ci_relations' => '[]'
];
$_REQUEST = $_POST;
require_once __DIR__ . '/public/api_ci.php';
