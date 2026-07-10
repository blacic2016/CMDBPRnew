<?php
require_once __DIR__ . '/../vendor/autoload.php';
$s = PhpOffice\PhpSpreadsheet\IOFactory::load('cotizador/JCV001 - Renovación mantenimientos y bolsa horas de soporte.xlsm');
foreach (['Detalle Servicios', 'Detalle Servicios (2)'] as $sheetName) {
    if (!$s->sheetNameExists($sheetName)) {
        echo "Sheet $sheetName does not exist.\n";
        continue;
    }
    $sheet = $s->getSheetByName($sheetName);
    echo "Sheet $sheetName:\n";
    $highestRow = $sheet->getHighestRow();
    for ($r=1; $r<=min(15, $highestRow); $r++) {
        $rowVal = [];
        for ($c=1; $c<=14; $c++) {
            $rowVal[] = $sheet->getCellByColumnAndRow($c, $r)->getValue();
        }
        echo "  Row $r: " . implode(' | ', array_map(function($v){ return substr(trim($v ?? ''), 0, 30); }, $rowVal)) . "\n";
    }
}
