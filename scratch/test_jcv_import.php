<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$excelPath = __DIR__ . '/../cotizador/JCV001 - Renovación mantenimientos y bolsa horas de soporte.xlsm';
if (!file_exists($excelPath)) {
    die("Excel file not found at: $excelPath\n");
}

echo "Loading spreadsheet...\n";
$reader = IOFactory::createReaderForFile($excelPath);
$spreadsheet = $reader->load($excelPath);

$sheetNames = $spreadsheet->getSheetNames();
$skippedSheets = ['Índice', 'Extras'];

$insertedCount = 0;
$brandCounters = [];

foreach ($sheetNames as $sName) {
    if (in_array($sName, $skippedSheets)) continue;
    
    $sheet = $spreadsheet->getSheetByName($sName);
    $highestRow = $sheet->getHighestRow();
    
    // Find header row and map columns dynamically
    $headerRow = null;
    $colMap = [
        'actividad' => null,
        'detalle' => null,
        'n1' => null,
        'n2' => null,
        'n3' => null,
        'e1' => null,
        'e2' => null,
        'tipo' => null,
        'h_lab' => null,
        'h_50' => null,
        'h_100' => null,
        'obs' => null
    ];
    
    // Scan first 10 rows for a header
    for ($r = 1; $r <= 10; $r++) {
        $hasDetalle = false;
        $rowVals = [];
        for ($c = 1; $c <= 20; $c++) {
            $val = trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue());
            $rowVals[$c] = $val;
            if (stripos($val, 'Detalle') !== false) {
                $hasDetalle = true;
            }
        }
        if ($hasDetalle) {
            $headerRow = $r;
            foreach ($rowVals as $c => $val) {
                $valLower = strtolower($val);
                if (stripos($valLower, 'actividad') !== false) {
                    $colMap['actividad'] = $c;
                } elseif (stripos($valLower, 'detalle') !== false) {
                    $colMap['detalle'] = $c;
                } elseif ($valLower === 'n1') {
                    $colMap['n1'] = $c;
                } elseif ($valLower === 'n2') {
                    $colMap['n2'] = $c;
                } elseif ($valLower === 'n3') {
                    $colMap['n3'] = $c;
                } elseif ($valLower === 'e1') {
                    $colMap['e1'] = $c;
                } elseif ($valLower === 'e2') {
                    $colMap['e2'] = $c;
                } elseif ($valLower === 'tipo') {
                    $colMap['tipo'] = $c;
                } elseif (stripos($valLower, 'horas laborables') !== false || stripos($valLower, 'h. lab') !== false || stripos($valLower, 'horas lab') !== false) {
                    $colMap['h_lab'] = $c;
                } elseif (stripos($valLower, '50%') !== false || stripos($valLower, 'no laborables 50') !== false) {
                    $colMap['h_50'] = $c;
                } elseif (stripos($valLower, '100%') !== false || stripos($valLower, 'no laborables 100') !== false) {
                    $colMap['h_100'] = $c;
                } elseif (stripos($valLower, 'observaciones') !== false) {
                    $colMap['obs'] = $c;
                }
            }
            
            // Handle case where "Horas no Laborables" is present but without explicit 50%/100% in header
            if (!$colMap['h_50'] || !$colMap['h_100']) {
                foreach ($rowVals as $c => $val) {
                    $valLower = strtolower($val);
                    if (stripos($valLower, 'no laborables') !== false || stripos($valLower, 'no lab') !== false) {
                        if (!$colMap['h_50']) {
                            $colMap['h_50'] = $c;
                        } elseif (!$colMap['h_100'] && $c !== $colMap['h_50']) {
                            $colMap['h_100'] = $c;
                        }
                    }
                }
            }
            break;
        }
    }
    
    // If no header found, it's not a service sheet. Skip it.
    if ($headerRow === null) {
        echo "Skipping sheet: $sName (no service header found)\n";
        continue;
    }
    
    $currentGroup = '';
    $sheetRows = 0;
    
    for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
        $n1 = ($colMap['n1'] && trim(strtolower((string)$sheet->getCellByColumnAndRow($colMap['n1'], $row)->getValue())) === 'x') ? 1 : 0;
        $n2 = ($colMap['n2'] && trim(strtolower((string)$sheet->getCellByColumnAndRow($colMap['n2'], $row)->getValue())) === 'x') ? 1 : 0;
        $n3 = ($colMap['n3'] && trim(strtolower((string)$sheet->getCellByColumnAndRow($colMap['n3'], $row)->getValue())) === 'x') ? 1 : 0;
        $e1 = ($colMap['e1'] && trim(strtolower((string)$sheet->getCellByColumnAndRow($colMap['e1'], $row)->getValue())) === 'x') ? 1 : 0;
        $e2 = ($colMap['e2'] && trim(strtolower((string)$sheet->getCellByColumnAndRow($colMap['e2'], $row)->getValue())) === 'x') ? 1 : 0;
        
        $tipo = $colMap['tipo'] ? trim((string)$sheet->getCellByColumnAndRow($colMap['tipo'], $row)->getValue()) : '';
        
        $h_lab = 0.0;
        $h_50 = 0.0;
        $h_100 = 0.0;
        
        if ($colMap['h_lab']) {
            try {
                $h_lab = floatval($sheet->getCellByColumnAndRow($colMap['h_lab'], $row)->getCalculatedValue());
            } catch (Exception $ex) {
                $h_lab = floatval($sheet->getCellByColumnAndRow($colMap['h_lab'], $row)->getValue());
            }
        }
        if ($colMap['h_50']) {
            try {
                $h_50 = floatval($sheet->getCellByColumnAndRow($colMap['h_50'], $row)->getCalculatedValue());
            } catch (Exception $ex) {
                $h_50 = floatval($sheet->getCellByColumnAndRow($colMap['h_50'], $row)->getValue());
            }
        }
        if ($colMap['h_100']) {
            try {
                $h_100 = floatval($sheet->getCellByColumnAndRow($colMap['h_100'], $row)->getCalculatedValue());
            } catch (Exception $ex) {
                $h_100 = floatval($sheet->getCellByColumnAndRow($colMap['h_100'], $row)->getValue());
            }
        }
        
        $obs = $colMap['obs'] ? trim((string)$sheet->getCellByColumnAndRow($colMap['obs'], $row)->getValue()) : '';
        
        // Determine values based on column mappings
        $groupVal = '';
        $detailVal = '';
        
        if ($colMap['actividad']) {
            $groupVal = trim((string)$sheet->getCellByColumnAndRow($colMap['actividad'], $row)->getValue());
        }
        if ($colMap['detalle']) {
            $detailVal = trim((string)$sheet->getCellByColumnAndRow($colMap['detalle'], $row)->getValue());
        }
        
        $hasCheckboxesOrHours = ($n1 || $n2 || $n3 || $e1 || $e2 || $h_lab > 0 || $h_50 > 0 || $h_100 > 0);
        
        if ($colMap['actividad'] !== null) {
            if ($groupVal === '' && $detailVal === '') {
                continue;
            }
            if ($groupVal !== '') {
                $currentGroup = $groupVal;
            }
            $detail = $detailVal;
            if ($detail === '') {
                $detail = $currentGroup;
            }
        } else {
            // JCV001-style sheet: only Detalle column
            $val = $detailVal !== '' ? $detailVal : $groupVal;
            if ($val === '') {
                continue;
            }
            if ($hasCheckboxesOrHours) {
                $detail = $val;
            } else {
                $currentGroup = $val;
                continue; // Skip inserting the group header row itself as a leaf item
            }
        }
        
        $insertedCount++;
        $sheetRows++;
        if ($sheetRows <= 5) {
            echo "  Row: Activity [$currentGroup] | Detail [$detail] | N1:$n1, N2:$n2, N3:$n3, E1:$e1, E2:$e2 | Hours: $h_lab / $h_50 / $h_100\n";
        }
    }
    echo "Parsed sheet $sName: $sheetRows valid service rows found.\n";
}

echo "Total simulated services: $insertedCount\n";
