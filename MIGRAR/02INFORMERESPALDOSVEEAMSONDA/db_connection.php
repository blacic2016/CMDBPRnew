<?php
// It is recommended to store database credentials in a more secure way,
// for example, using environment variables or a configuration file outside the web root.
$servername = "localhost";
$username = "root";
$password = "zabbix";
$dbname = "SONDAWPS";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error . " Error N°: " . $conn->connect_errno);
}
?>