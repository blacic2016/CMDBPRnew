<?php
require_once('funtions2.php');
define('MIBS_ALL_PATH', '/var/www/html/snmp/mibs:/var/lib/mibs/ietf');
$path=filter_var($_GET['path']);


$filename="/var/www/html/snmp/".$path;

$mib = escapeshellcmd($filename);

$oid_tree = get_oid_tree($mib);

echo  json_encode($oid_tree);


?>
