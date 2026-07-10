<?php
require_once __DIR__ . '/../vendor/autoload.php';
$s = PhpOffice\PhpSpreadsheet\IOFactory::load('cotizador/1 Actividades Completo - Copia.xlsx');
echo "Sheets:\n";
foreach ($s->getSheetNames() as $n) {
    echo "- $n\n";
    $sheet = $s->getSheetByName($n);
    $highestRow = $sheet->getHighestRow();
    for ($r=1; $r<=min(10, $highestRow); $r++) {
        $rowVal = [];
        for ($c=1; $c<=14; $c++) {
            $rowVal[] = $sheet->getCellByColumnAndRow($c, $r)->getValue();
        }
        echo "  Row $r: " . implode(' | ', array_map(function($v){ return substr(trim($v), 0, 30); }, $rowVal)) . "\n";
    }
}
