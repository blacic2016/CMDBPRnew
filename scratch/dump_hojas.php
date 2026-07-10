<?php
require_once __DIR__ . '/../vendor/autoload.php';
$s = PhpOffice\PhpSpreadsheet\IOFactory::load('cotizador/JCV001 - Renovación mantenimientos y bolsa horas de soporte.xlsm');
$n = 'Implementacion';
echo "=== $n ===\n";
$sheet = $s->getSheetByName($n);
for($r=70;$r<=85;$r++){
    $rowVal = [];
    for($c=1;$c<=20;$c++) {
        $cell = $sheet->getCellByColumnAndRow($c, $r);
        $val = $cell->getValue();
        $calcVal = $cell->getCalculatedValue();
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
        if ($val !== null) {
            $rowVal[] = "$colLetter$r: [$val] ($calcVal)";
        }
    }
    echo "Row $r: " . implode(' | ', $rowVal)."\n";
}
