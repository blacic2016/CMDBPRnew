<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$filename = __DIR__ . '/../cotizador/1 Actividades Completo - Copia.xlsx';
$reader = IOFactory::createReaderForFile($filename);
$spreadsheet = $reader->load($filename);

$sheet = $spreadsheet->getSheetByName('ARUBA');
echo "Row 1 (Headers):\n";
for ($col = 1; $col <= 15; $col++) {
    $cellAddress = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . "1";
    echo "$cellAddress: '" . $sheet->getCell($cellAddress)->getValue() . "'\n";
}

echo "\nRow 2:\n";
for ($col = 1; $col <= 15; $col++) {
    $cellAddress = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . "2";
    echo "$cellAddress: '" . $sheet->getCell($cellAddress)->getValue() . "'\n";
}
