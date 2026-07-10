<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$filename = __DIR__ . '/../cotizador/JCV001 - Renovación mantenimientos y bolsa horas de soporte.xlsm';
$reader = IOFactory::createReaderForFile($filename);
$spreadsheet = $reader->load($filename);

foreach ($spreadsheet->getSheetNames() as $sName) {
    $sheet = $spreadsheet->getSheetByName($sName);
    $highestRow = $sheet->getHighestRow();
    $highestCol = $sheet->getHighestColumn();
    $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);
    
    for ($row = 1; $row <= $highestRow; $row++) {
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $val = (string)$sheet->getCellByColumnAndRow($col, $row)->getValue();
            if ($val !== '' && (stripos($val, 'switch') !== false || stripos($val, 'router') !== false || stripos($val, 'equipos') !== false || stripos($val, 'cantidad') !== false)) {
                echo "Sheet: $sName | Cell " . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . "$row | Val: " . substr(trim($val), 0, 100) . "\n";
            }
        }
    }
}
