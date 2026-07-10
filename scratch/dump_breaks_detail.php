<?php
require_once __DIR__ . '/../vendor/autoload.php';
$s = PhpOffice\PhpSpreadsheet\IOFactory::load('cotizador/JCV001 - Renovación mantenimientos y bolsa horas de soporte.xlsm');

$tests = [
    ['Detalle Servicios', 76],
    ['Detalle Servicios', 78],
    ['Detalle Servicios (2)', 76],
    ['Detalle Servicios (2)', 77]
];

foreach ($tests as $t) {
    list($sName, $row) = $t;
    echo "=== $sName | Row $row ===\n";
    $sheet = $s->getSheetByName($sName);
    for ($c = 1; $c <= 35; $c++) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
        $cell = $sheet->getCellByColumnAndRow($c, $row);
        $val = $cell->getValue();
        $calc = $cell->getCalculatedValue();
        if ($val !== null && $val !== '') {
            echo "  $colLetter: Raw: [$val] | Calc: [$calc]\n";
        }
    }
}
