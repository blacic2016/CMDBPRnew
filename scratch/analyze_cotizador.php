<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

function analyze_file($filename) {
    echo "==========================================\n";
    echo "Analyzing: $filename\n";
    echo "==========================================\n";
    $reader = IOFactory::createReaderForFile($filename);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($filename);
    
    $sheetNames = $spreadsheet->getSheetNames();
    echo "Sheets: " . implode(", ", $sheetNames) . "\n\n";
    
    foreach ($sheetNames as $sheetName) {
        $sheet = $spreadsheet->getSheetByName($sheetName);
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        echo "Sheet: $sheetName ($highestRow rows, $highestColumn columns)\n";
        
        // Print first 5 rows
        $rows = [];
        $colLimit = min(15, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn));
        for ($row = 1; $row <= min(15, $highestRow); $row++) {
            $rowData = [];
            for ($col = 1; $col <= $colLimit; $col++) {
                $cellVal = $sheet->getCellByColumnAndRow($col, $row)->getValue();
                $rowData[] = $cellVal !== null ? (string)$cellVal : '';
            }
            // Check if row is not empty
            if (strlen(implode('', $rowData)) > 0) {
                $rows[] = "Row $row: " . implode(" | ", array_map(function($v) { return substr(trim($v), 0, 40); }, $rowData));
            }
        }
        echo implode("\n", $rows) . "\n";
        echo "------------------------------------------\n";
    }
}

$dir = __DIR__ . '/../cotizador/';
analyze_file($dir . '1 Actividades Completo - Copia.xlsx');
analyze_file($dir . 'JCV001 - Renovación mantenimientos y bolsa horas de soporte.xlsm');
