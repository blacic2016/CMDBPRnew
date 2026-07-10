<?php
require_once __DIR__ . '/../vendor/autoload.php';
$s = PhpOffice\PhpSpreadsheet\IOFactory::load('cotizador/JCV001 - Renovación mantenimientos y bolsa horas de soporte.xlsm');

$n = 'Detalle Servicios';
echo "=== $n ===\n";
$sheet = $s->getSheetByName($n);

for ($r = 100; $r <= 118; $r++) {
    $valK = $sheet->getCell('K' . $r)->getValue();
    $valL = $sheet->getCell('L' . $r)->getValue();
    if (($valK !== null && $valK !== '') || ($valL !== null && $valL !== '')) {
        echo "Row $r: K: [$valK] | L: [$valL]\n";
    }
}
