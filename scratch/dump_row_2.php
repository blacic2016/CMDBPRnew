<?php
require_once __DIR__ . '/../vendor/autoload.php';
$filePath = __DIR__ . '/../cotizador/novaiops/reporte_tareas_2026-04-01_a_2026-07-13.xlsx';

$spreadsheet = PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);

foreach ($spreadsheet->getSheetNames() as $sheetName) {
    echo "=== Sheet: $sheetName ===\n";
    $sheet = $spreadsheet->getSheetByName($sheetName);
    
    // Headers
    $headers = [];
    $colLimit = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
    for ($col = 1; $col <= $colLimit; $col++) {
        $headers[$col] = $sheet->getCellByColumnAndRow($col, 1)->getValue();
    }
    
    // Row 2 values
    $row2 = [];
    for ($col = 1; $col <= $colLimit; $col++) {
        $row2[$col] = $sheet->getCellByColumnAndRow($col, 2)->getValue();
    }
    
    echo "Headers:\n";
    foreach ($headers as $col => $name) {
        if ($name !== null) {
            echo "  $col: $name => " . json_encode($row2[$col]) . "\n";
        }
    }
    echo "\n";
}
