<?php
require_once __DIR__ . '/../vendor/autoload.php';
$s = PhpOffice\PhpSpreadsheet\IOFactory::load('cotizador/JCV001 - Renovación mantenimientos y bolsa horas de soporte.xlsm');
$sheet = $s->getSheetByName('Implementacion');
for ($c = 1; $c <= 30; $c++) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
    $cell = $sheet->getCellByColumnAndRow($c, 73);
    $val = $cell->getValue();
    $calc = $cell->getCalculatedValue();
    if ($val !== null && $val !== '') {
        echo "$colLetter: Raw: [$val] | Calc: [$calc]\n";
    }
}
