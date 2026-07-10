<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$filename = __DIR__ . '/../cotizador/JCV001 - Renovación mantenimientos y bolsa horas de soporte.xlsm';
$reader = IOFactory::createReaderForFile($filename);
$spreadsheet = $reader->load($filename);

foreach (['Mant Prev', 'Mant Corr'] as $sName) {
    echo "==========================================\n";
    echo "Sheet: $sName\n";
    echo "==========================================\n";
    $sheet = $spreadsheet->getSheetByName($sName);
    $highestRow = $sheet->getHighestRow();
    
    for ($row = 1; $row <= min(40, $highestRow); $row++) {
        $rowData = [];
        for ($col = 1; $col <= 15; $col++) {
            $cell = $sheet->getCellByColumnAndRow($col, $row);
            $val = $cell->getValue();
            $calcVal = $cell->getCalculatedValue();
            if ($val !== null && strpos((string)$val, '=') === 0) {
                $rowData[] = "$val ($calcVal)";
            } else {
                $rowData[] = $val !== null ? (string)$val : '';
            }
        }
        // Trim empty trailing cells
        while (count($rowData) > 0 && end($rowData) === '') {
            array_pop($rowData);
        }
        if (count($rowData) > 0) {
            echo "Row $row: " . implode(" | ", array_map(function($v) { return substr(trim($v), 0, 45); }, $rowData)) . "\n";
        }
    }
}
