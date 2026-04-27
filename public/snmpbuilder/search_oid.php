<?php
require_once __DIR__ . '/../../config.php';
define('MIBS_ALL_PATH', SNMP_MIBS_PATH . ':/usr/share/snmp/mibs:/var/lib/mibs/ietf');

$oid = isset($_GET['oid']) ? filter_var($_GET['oid'], FILTER_SANITIZE_STRING) : '';

if (empty($oid)) {
    echo json_encode(array('error' => 'OID vacío'));
    exit;
}

$safe_oid = escapeshellarg($oid);

// snmptranslate -Td -OS -IR to deeply resolve the OID and dump its description.
exec("snmptranslate -Td -OS -IR -M ".MIBS_ALL_PATH." -m ALL ".$safe_oid." 2>&1", $output, $return_var);

$output_str = implode("\n", $output);

if ($return_var !== 0 || strpos($output_str, 'Unknown Object Identifier') !== false) {
    echo json_encode(array('error' => 'El OID no fue encontrado en ninguna MIB instalada en el servidor. Revise la sintaxis.'));
    exit;
}

// Extract Module Name: Usually the first line is "MODULE-NAME::NodeName"
$module = "Modulo Desconocido";
if (preg_match('/^([A-Za-z0-9\-_]+)::/', $output[0], $matches)) {
    $module = $matches[1];
} else if (preg_match('/FROM\s+([A-Za-z0-9\-_]+)/', $output_str, $matches)) {
    // Fallback: "-- FROM MODULE-NAME"
    $module = $matches[1];
}

echo json_encode(array(
    'module' => $module,
    'full_desc' => str_replace("\n", "<br>", htmlspecialchars($output_str))
));
?>
