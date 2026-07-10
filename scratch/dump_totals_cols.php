<?php
require_once __DIR__ . '/../vendor/autoload.php';
$s = PhpOffice\PhpSpreadsheet\IOFactory::load('cotizador/JCV001 - Renovación mantenimientos y bolsa horas de soporte.xlsm');
$sheet = $s->getSheetByName('Implementacion');

$cols = ['AA', 'AN', 'BA'];
for ($r = 70; $r <= 115; $r++) {
    $rowVal = [];
    foreach ($cols as $col) {
        $cell = $sheet->getCell($col . $r);
        $val = $cell->getValue();
        $calc = $cell->getCalculatedValue();
        if ($val !== null && $val !== '') {
            $rowVal[] = "$col$r: [$val] -> ($calc)";
        }
    }
    if (count($rowVal) > 0) {
        echo "Row $r: " . implode(' | ', $rowVal) . "\n";
    }
}
