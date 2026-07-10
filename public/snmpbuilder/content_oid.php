<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/permissions_helper.php';

require_login();
if (!has_module_access('snmp')) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once('funtions2.php');
define('MIBS_ALL_PATH', SNMP_MIBS_PATH . ':/usr/share/snmp/mibs:/var/lib/mibs/ietf');
$oid=filter_var($_GET['oid'],FILTER_SANITIZE_STRING);


//$filename="/var/www/html/snmp/".$path;

$oid_mib = escapeshellcmd($oid);

$oid_tree = get_oid_content($oid_mib);

echo  json_encode($oid_tree);


?>
