<?php
require_once __DIR__ . '/../../../../config.php';

$servername = DB_CONFIG['host'];
$username = DB_CONFIG['user'];
$password = DB_CONFIG['password'];
$dbname = "SONDAWPS"; // Base de datos específica para Veeam

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error . " Error N°: " . $conn->connect_errno);
}
?>