<?php
require_once __DIR__ . '/../vendor/autoload.php';
$s = PhpOffice\PhpSpreadsheet\IOFactory::load('cotizador/JCV001 - Renovación mantenimientos y bolsa horas de soporte.xlsm');

foreach ($s->getSheetNames() as $sName) {
    $sheet = $s->getSheetByName($sName);
    $highestRow = $sheet->getHighestRow();
    $highestCol = $sheet->getHighestColumn();
    $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);
    for ($row = 1; $row <= $highestRow; $row++) {
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $val = (string)$sheet->getCellByColumnAndRow($col, $row)->getValue();
            if (stripos($val, 'Transferencia') !== false) {
                echo "Sheet: $sName | Row: $row\n";
                // Dump this row
                for ($c = 1; $c <= $highestColIndex; $c++) {
                    $cellVal = $sheet->getCellByColumnAndRow($c, $row)->getValue();
                    $cellCalc = $sheet->getCellByColumnAndRow($c, $row)->getCalculatedValue();
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                    if ($cellVal !== null && $cellVal !== '') {
                        echo "  $colLetter: [$cellVal] -> ($cellCalc)\n";
                    }
                }
            }
        }
    }
}
