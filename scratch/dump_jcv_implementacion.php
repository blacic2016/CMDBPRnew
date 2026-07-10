<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$filename = __DIR__ . '/../cotizador/JCV001 - Renovación mantenimientos y bolsa horas de soporte.xlsm';
$reader = IOFactory::createReaderForFile($filename);
$spreadsheet = $reader->load($filename);

function dump_sheet($sheet, $maxRow = 120, $maxCol = 30) {
    echo "==========================================\n";
    echo "Sheet: " . $sheet->getTitle() . "\n";
    echo "==========================================\n";
    for ($row = 1; $row <= $maxRow; $row++) {
        $rowData = [];
        $hasValue = false;
        for ($col = 1; $col <= $maxCol; $col++) {
            $cell = $sheet->getCellByColumnAndRow($col, $row);
            $val = $cell->getValue();
            if ($val !== null) {
                $hasValue = true;
                $calc = $cell->getCalculatedValue();
                if (strpos((string)$val, '=') === 0) {
                    $rowData[] = "$val ($calc)";
                } else {
                    $rowData[] = (string)$val;
                }
            } else {
                $rowData[] = '';
            }
        }
        // Trim empty trailing cells
        while (count($rowData) > 0 && end($rowData) === '') {
            array_pop($rowData);
        }
        if ($hasValue && count($rowData) > 0) {
            echo "Row $row: " . implode(" | ", array_map(function($v) { return substr(trim($v), 0, 80); }, $rowData)) . "\n";
        }
    }
}

dump_sheet($spreadsheet->getSheetByName('Implementacion'), 120, 20);
