<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$filename = __DIR__ . '/../cotizador/JCV001 - Renovación mantenimientos y bolsa horas de soporte.xlsm';
$reader = IOFactory::createReaderForFile($filename);
$spreadsheet = $reader->load($filename);

$sheet = $spreadsheet->getSheetByName('Costos');
$highestRow = $sheet->getHighestRow();
$highestColumn = $sheet->getHighestColumn();

echo "Sheet Costos details:\n";
for ($row = 1; $row <= 25; $row++) {
    $rowData = [];
    for ($col = 1; $col <= 12; $col++) {
        $cell = $sheet->getCellByColumnAndRow($col, $row);
        $val = $cell->getValue();
        $calcVal = $cell->getCalculatedValue();
        if ($val !== $calcVal && strpos((string)$val, '=') === 0) {
            $rowData[] = "$val ($calcVal)";
        } else {
            $rowData[] = $val !== null ? (string)$val : '';
        }
    }
    echo "Row $row: " . implode(" | ", $rowData) . "\n";
}
