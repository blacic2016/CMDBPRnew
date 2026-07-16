<?php
require_once __DIR__ . '/../vendor/autoload.php';
$filePath = __DIR__ . '/../cotizador/novaiops/reporte_tareas_2026-04-01_a_2026-07-13.xlsx';

$spreadsheet = PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);

// Sheet 1
$sheet1 = $spreadsheet->getSheetByName('Reporte tareas');
$highestRow1 = $sheet1->getHighestRow();
$ids1 = [];
for ($r = 2; $r <= $highestRow1; $r++) {
    $val = $sheet1->getCellByColumnAndRow(1, $r)->getValue();
    if ($val !== null && $val !== '') {
        $ids1[] = $val;
    }
}
$uniqueIds1 = array_unique($ids1);
echo "Sheet 1 (Reporte tareas) ID tarea uniqueness: " . count($uniqueIds1) . " unique out of " . count($ids1) . " total IDs.\n";

// Sheet 2
$sheet2 = $spreadsheet->getSheetByName('Seguimientos');
$highestRow2 = $sheet2->getHighestRow();
$ids2 = [];
for ($r = 2; $r <= $highestRow2; $r++) {
    // ID seguimiento is column 3 (C)
    $val = $sheet2->getCellByColumnAndRow(3, $r)->getValue();
    if ($val !== null && $val !== '') {
        $ids2[] = $val;
    }
}
$uniqueIds2 = array_unique($ids2);
echo "Sheet 2 (Seguimientos) ID seguimiento uniqueness: " . count($uniqueIds2) . " unique out of " . count($ids2) . " total IDs.\n";

// Sheet 3
$sheet3 = $spreadsheet->getSheetByName('Información reportes');
$highestRow3 = $sheet3->getHighestRow();
$ids3 = [];
for ($r = 2; $r <= $highestRow3; $r++) {
    // ID tarea is column 1 (A)
    $val = $sheet3->getCellByColumnAndRow(1, $r)->getValue();
    if ($val !== null && $val !== '') {
        $ids3[] = $val;
    }
}
$uniqueIds3 = array_unique($ids3);
echo "Sheet 3 (Información reportes) ID tarea uniqueness: " . count($uniqueIds3) . " unique out of " . count($ids3) . " total IDs.\n";
