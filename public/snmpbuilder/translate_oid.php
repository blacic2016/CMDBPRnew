<?php
require_once __DIR__ . '/../../config.php';
define('MIBS_ALL_PATH', SNMP_MIBS_PATH . ':/usr/share/snmp/mibs:/var/lib/mibs/ietf');

$oid = isset($_GET['oid']) ? filter_var($_GET['oid'], FILTER_SANITIZE_STRING) : '';

if (empty($oid)) {
    echo json_encode(array('numeric' => '', 'fulltext' => ''));
    exit;
}

$safe_oid = escapeshellarg($oid);
// We MUST use -IR (Random access lookups) to resolve abbreviated table OIDs like 'ifDescr.1'
$numeric = exec("snmptranslate -On -IR -M ".MIBS_ALL_PATH." -m ALL ".$safe_oid);
$fulltext = exec("snmptranslate -Of -IR -M ".MIBS_ALL_PATH." -m ALL ".$safe_oid);

echo json_encode(array('numeric' => $numeric, 'fulltext' => $fulltext));
?>
