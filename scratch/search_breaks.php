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
            if ($val !== '' && (stripos($val, 'Break') !== false || stripos($val, 'Taller') !== false || stripos($val, 'Capacita') !== false)) {
                echo "Sheet: $sName | Row: $row | Col: $col | Val: $val\n";
            }
        }
    }
}
