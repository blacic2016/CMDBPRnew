<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$filename = __DIR__ . '/../cotizador/1 Actividades Completo - Copia.xlsx';
$reader = IOFactory::createReaderForFile($filename);
$spreadsheet = $reader->load($filename);

$sheet = $spreadsheet->getSheetByName('ARUBA');
$highestRow = $sheet->getHighestRow();
$highestColumn = $sheet->getHighestColumn();

echo "ARUBA sheet rows:\n";
for ($row = 1; $row <= 15; $row++) {
    $rowData = [];
    for ($col = 1; $col <= 14; $col++) {
        $cell = $sheet->getCellByColumnAndRow($col, $row);
        $val = $cell->getValue();
        $rowData[] = $val !== null ? (string)$val : '';
    }
    echo "Row $row: " . implode(" | ", $rowData) . "\n";
}
