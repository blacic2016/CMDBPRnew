<?php
require_once __DIR__ . '/../vendor/autoload.php';
$filePath = __DIR__ . '/../cotizador/novaiops/reporte_tareas_2026-04-01_a_2026-07-13.xlsx';

if (!file_exists($filePath)) {
    die("File not found at $filePath\n");
}

echo "Loading file: $filePath\n";
$spreadsheet = PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
$sheetNames = $spreadsheet->getSheetNames();
echo "Sheet names: " . implode(', ', $sheetNames) . "\n\n";

foreach ($sheetNames as $sheetName) {
    echo "--- Sheet: $sheetName ---\n";
    $sheet = $spreadsheet->getSheetByName($sheetName);
    $highestColumn = $sheet->getHighestColumn();
    $highestRow = $sheet->getHighestRow();
    
    // Get headers (first row)
    $headers = [];
    $colLimit = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
    for ($col = 1; $col <= $colLimit; $col++) {
        $val = $sheet->getCellByColumnAndRow($col, 1)->getValue();
        if ($val !== null && $val !== '') {
            $headers[] = $val;
        }
    }
    echo "Columns: " . implode(', ', $headers) . "\n";
    echo "Total Rows: $highestRow\n\n";
}
