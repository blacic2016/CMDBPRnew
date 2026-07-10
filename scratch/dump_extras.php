<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$filename = __DIR__ . '/../cotizador/1 Actividades Completo - Copia.xlsx';
$reader = IOFactory::createReaderForFile($filename);
$spreadsheet = $reader->load($filename);

$sheet = $spreadsheet->getSheetByName('Extras');
$highestRow = $sheet->getHighestRow();
echo "Extras sheet rows:\n";
for ($row = 1; $row <= min(15, $highestRow); $row++) {
    $rowData = [];
    for ($col = 1; $col <= 10; $col++) {
        $cell = $sheet->getCellByColumnAndRow($col, $row);
        $rowData[] = $cell->getValue() !== null ? (string)$cell->getValue() : '';
    }
    echo "Row $row: " . implode(" | ", $rowData) . "\n";
}
