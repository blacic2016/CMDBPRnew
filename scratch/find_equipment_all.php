<?php
require_once __DIR__ . '/../vendor/autoload.php';
$s = PhpOffice\PhpSpreadsheet\IOFactory::load('cotizador/JCV001 - Renovación mantenimientos y bolsa horas de soporte.xlsm');

foreach ($s->getSheetNames() as $n) {
    $sheet = $s->getSheetByName($n);
    $highestRow = $sheet->getHighestRow();
    $highestCol = $sheet->getHighestColumn();
    $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);
    for ($r = 1; $r <= $highestRow; $r++) {
        for ($c = 1; $c <= $highestColIndex; $c++) {
            $val = (string)$sheet->getCellByColumnAndRow($c, $r)->getValue();
            if (stripos($val, 'blades') !== false || stripos($val, 'chasis') !== false) {
                echo "Sheet: $n | Cell " . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c) . "$r: [$val]\n";
                // Print surrounding cells
                $rowVals = [];
                for ($col = 1; $col <= min(15, $highestColIndex); $col++) {
                    $cellVal = $sheet->getCellByColumnAndRow($col, $r)->getValue();
                    $cellCalc = $sheet->getCellByColumnAndRow($col, $r)->getCalculatedValue();
                    if ($cellVal !== null && $cellVal !== '') {
                        $rowVals[] = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . ": [$cellVal] ($cellCalc)";
                    }
                }
                echo "  Row $r: " . implode(' | ', $rowVals) . "\n";
            }
        }
    }
}
