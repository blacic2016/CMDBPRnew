<?php
/**
 * Módulo de Análisis de Logs - CMDB VILASECA
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';

// Validar login
require_login();

// Manejar la petición de análisis (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'analyze') {
    header('Content-Type: application/json');
    
    if (!isset($_FILES['log_file']) || $_FILES['log_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Error al subir el archivo de log.']);
        exit;
    }

    $tmpPath = $_FILES['log_file']['tmp_name'];
    $filename = $_FILES['log_file']['name'];
    
    // Validar extensión básica
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, ['log', 'txt', 'csv', 'err', 'out'])) {
        echo json_encode(['success' => false, 'error' => 'Formato de archivo no admitido. Use .log, .txt o .csv.']);
        exit;
    }

    $result = parseLogFile($tmpPath, $filename);
    echo json_encode($result);
    exit;
}

// Función del parser de logs
function parseLogFile($filepath, $originalFilename) {
    if (!file_exists($filepath) || !is_readable($filepath)) {
        return ['success' => false, 'error' => 'El archivo no se puede leer.'];
    }

    $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $totalLines = count($lines);
    
    // Limitar procesamiento para evitar desbordamiento de memoria
    $maxLines = 10000;
    if ($totalLines > $maxLines) {
        $lines = array_slice($lines, 0, $maxLines);
    }
    
    $parsedLines = [];
    $severityCounts = ['error' => 0, 'warning' => 0, 'info' => 0, 'success' => 0, 'unknown' => 0];
    
    // Muestreo para detectar formato de log
    $sample = implode("\n", array_slice($lines, 0, 15));
    $logType = 'generic';
    
    // Detección: Nginx/Apache Combined/Common Log Format
    if (preg_match('/^\S+ \S+ \S+ \[\d{2}\/[A-Za-z]{3}\/\d{4}:\d{2}:\d{2}:\d{2}/m', $sample)) {
        $logType = 'webserver';
    } 
    // Detección: PHP Error Log
    elseif (preg_match('/^\[\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2}/m', $sample)) {
        $logType = 'php';
    }
    // Detección: Syslog estándar
    elseif (preg_match('/^[A-Za-z]{3}\s+\d+\s+\d{2}:\d{2}:\d{2}/m', $sample)) {
        $logType = 'syslog';
    }
    
    $topIPs = [];
    $topRequests = [];
    $topMessages = [];
    $timeline = [];
    
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        if (empty($line)) continue;

        $parsed = [
            'line' => $lineNum + 1,
            'timestamp' => null,
            'level' => 'info',
            'ip' => null,
            'message' => $line,
            'extra' => null
        ];
        
        if ($logType === 'webserver') {
            // Nginx/Apache Web Server Logs
            $pattern = '/^(\S+) \S+ \S+ \[(.*?)\] "(.*?)" (\d{3}) (\S+)(?: "(.*?)" "(.*?)")?$/';
            if (preg_match($pattern, $line, $matches)) {
                $parsed['ip'] = $matches[1];
                $parsed['timestamp'] = $matches[2];
                $parsed['message'] = $matches[3]; // Request (Ej: "GET /index.php HTTP/1.1")
                $status = (int)$matches[4];
                $size = $matches[5] === '-' ? '0' : $matches[5];
                $parsed['extra'] = 'Status: ' . $status . ' | Tamaño: ' . $size . ' B';
                
                // Clasificar por código HTTP
                if ($status >= 500) {
                    $parsed['level'] = 'error';
                } elseif ($status >= 400) {
                    $parsed['level'] = 'warning';
                } elseif ($status >= 300) {
                    $parsed['level'] = 'info';
                } else {
                    $parsed['level'] = 'success';
                }
                
                // Contar IPs e URIs
                $topIPs[$parsed['ip']] = ($topIPs[$parsed['ip']] ?? 0) + 1;
                $reqParts = explode(' ', $matches[3]);
                $requestUri = $reqParts[1] ?? $matches[3];
                $topRequests[$requestUri] = ($topRequests[$requestUri] ?? 0) + 1;
            }
        } elseif ($logType === 'php') {
            // PHP Error Logs
            $pattern = '/^\[(.*?)\] (?:PHP )?([A-Za-z\s]+): (.*)$/';
            if (preg_match($pattern, $line, $matches)) {
                $parsed['timestamp'] = $matches[1];
                $type = strtolower(trim($matches[2]));
                $parsed['message'] = $matches[3];
                
                if (strpos($type, 'error') !== false || strpos($type, 'fatal') !== false || strpos($type, 'exception') !== false) {
                    $parsed['level'] = 'error';
                } elseif (strpos($type, 'warning') !== false) {
                    $parsed['level'] = 'warning';
                } else {
                    $parsed['level'] = 'info';
                }
                
                // Agrupar mensajes limpios
                $cleanMsg = preg_replace('/in \/.*? on line \d+/', '', $matches[3]);
                $topMessages[substr($cleanMsg, 0, 150)] = ($topMessages[substr($cleanMsg, 0, 150)] ?? 0) + 1;
            }
        } elseif ($logType === 'syslog') {
            // Syslog estándar
            $pattern = '/^([A-Za-z]{3}\s+\d+\s+\d{2}:\d{2}:\d{2})\s+(\S+)\s+(\S+?):\s*(.*)$/';
            if (preg_match($pattern, $line, $matches)) {
                $parsed['timestamp'] = $matches[1];
                $parsed['extra'] = 'Host: ' . $matches[2] . ' | Servicio: ' . $matches[3];
                $parsed['message'] = $matches[4];
                
                $msgLower = strtolower($matches[4]);
                if (preg_match('/(error|fatal|fail|crit|exception)/', $msgLower)) {
                    $parsed['level'] = 'error';
                } elseif (preg_match('/(warn|alert|warning)/', $msgLower)) {
                    $parsed['level'] = 'warning';
                } elseif (preg_match('/(success|ok|done|started)/', $msgLower)) {
                    $parsed['level'] = 'success';
                } else {
                    $parsed['level'] = 'info';
                }
                
                $topMessages[$matches[3]] = ($topMessages[$matches[3]] ?? 0) + 1; // Agrupar por Servicio
            }
        } else {
            // Log Genérico por expresiones regulares
            if (preg_match('/(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2})|(\d{2}\/[A-Za-z]{3}\/\d{4}:\d{2}:\d{2}:\d{2})/', $line, $matches)) {
                $parsed['timestamp'] = $matches[0];
            }
            
            $lineLower = strtolower($line);
            if (preg_match('/(error|fatal|fail|crit|exception|severe)/', $lineLower)) {
                $parsed['level'] = 'error';
            } elseif (preg_match('/(warn|alert|warning)/', $lineLower)) {
                $parsed['level'] = 'warning';
            } elseif (preg_match('/(success|ok|done|resolved)/', $lineLower)) {
                $parsed['level'] = 'success';
            } else {
                $parsed['level'] = 'info';
            }
            
            $cleanMsg = substr($line, 0, 100);
            $topMessages[$cleanMsg] = ($topMessages[$cleanMsg] ?? 0) + 1;
        }
        
        $severityCounts[$parsed['level']]++;
        
        // Formatear línea de tiempo (por horas)
        if ($parsed['timestamp']) {
            if (preg_match('/(\d{2}:\d{2})/', $parsed['timestamp'], $timeMatches)) {
                $hour = explode(':', $timeMatches[1])[0] . ':00';
                $timeline[$hour] = ($timeline[$hour] ?? 0) + 1;
            } else {
                $timeline['Bloques'] = ($timeline['Bloques'] ?? 0) + 1;
            }
        } else {
            $timeline['Sin Fecha'] = ($timeline['Sin Fecha'] ?? 0) + 1;
        }
        
        $parsedLines[] = $parsed;
    }
    
    // Ordenar estadísticas principales
    arsort($topIPs);
    $topIPs = array_slice($topIPs, 0, 10, true);
    
    arsort($topRequests);
    $topRequests = array_slice($topRequests, 0, 10, true);
    
    arsort($topMessages);
    $topMessages = array_slice($topMessages, 0, 10, true);
    
    ksort($timeline);
    
    // Generar insights automáticos e inteligentes
    $insights = [];
    
    if ($severityCounts['error'] > 0) {
        $errorPercent = round(($severityCounts['error'] / count($lines)) * 100, 1);
        $insights[] = [
            'type' => 'danger',
            'title' => 'Volumen de errores detectados',
            'description' => "Se registraron {$severityCounts['error']} errores críticos ({$errorPercent}% del archivo). Analice el listado de eventos de nivel Error para diagnosticar excepciones o fallos."
        ];
    }
    
    if ($logType === 'webserver') {
        $err404 = 0;
        $err500 = 0;
        foreach ($parsedLines as $pl) {
            if (isset($pl['extra'])) {
                if (strpos($pl['extra'], 'Status: 404') !== false) $err404++;
                if (strpos($pl['extra'], 'Status: 500') !== false) $err500++;
            }
        }
        
        if ($err404 > 0) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Recursos no encontrados (404)',
                'description' => "Se detectaron {$err404} respuestas HTTP 404. Indica enlaces rotos, llamadas a scripts inexistentes o accesos no autorizados escaneando directorios."
            ];
        }
        
        if ($err500 > 0) {
            $insights[] = [
                'type' => 'danger',
                'title' => 'Errores del lado del Servidor (500)',
                'description' => "Se registraron {$err500} errores HTTP 500. Revise los registros del sistema PHP para encontrar excepciones sin capturar en el backend."
            ];
        }
        
        if (count($topIPs) > 0) {
            reset($topIPs);
            $maxIP = key($topIPs);
            $maxIPCount = current($topIPs);
            if ($maxIPCount > 100) {
                $insights[] = [
                    'type' => 'info',
                    'title' => 'Actividad inusual de dirección IP',
                    'description' => "La IP <strong>{$maxIP}</strong> realizó {$maxIPCount} peticiones en el log. Podría tratarse de un bot automatizado o de un ataque de fuerza bruta."
                ];
            }
        }
    } elseif ($logType === 'php') {
        $memoryErrors = 0;
        $dbErrors = 0;
        foreach ($topMessages as $msg => $cnt) {
            if (stripos($msg, 'memory') !== false) $memoryErrors += $cnt;
            if (stripos($msg, 'database') !== false || stripos($msg, 'connection') !== false || stripos($msg, 'pdo') !== false) $dbErrors += $cnt;
        }
        
        if ($memoryErrors > 0) {
            $insights[] = [
                'type' => 'danger',
                'title' => 'Límite de Memoria PHP Excedido',
                'description' => "Se detectaron errores de memoria llena en PHP. Considere aumentar el valor de <code>memory_limit</code> en la configuración del servidor web."
            ];
        }
        if ($dbErrors > 0) {
            $insights[] = [
                'type' => 'danger',
                'title' => 'Fallo de Conexión a Base de Datos',
                'description' => "El log registra fallas en consultas SQL o conexión a base de datos. Verifique los servicios de red de base de datos."
            ];
        }
    }
    
    if (empty($insights)) {
        $insights[] = [
            'type' => 'success',
            'title' => 'Análisis Exitoso',
            'description' => 'No se encontraron anomalías evidentes ni patrones de fallos recurrentes en el archivo de registro.'
        ];
    }
    
    return [
        'success' => true,
        'filename' => $originalFilename,
        'log_type' => $logType,
        'total_lines' => $totalLines,
        'parsed_lines_count' => count($parsedLines),
        'severity_counts' => $severityCounts,
        'top_ips' => $topIPs,
        'top_requests' => $topRequests,
        'top_messages' => $topMessages,
        'timeline' => $timeline,
        'insights' => $insights,
        'parsed_lines' => $parsedLines
    ];
}

$page_title = "Análisis Inteligente de Logs";
include 'partials/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<style>
.drop-zone {
    border: 2px dashed #FF5C05;
    border-radius: 8px;
    background-color: rgba(255, 92, 5, 0.02);
    transition: all 0.3s ease;
    cursor: pointer;
}
.drop-zone:hover, .drop-zone.dragover {
    background-color: rgba(255, 92, 5, 0.08);
    border-color: #002A54;
}
.log-row {
    transition: background-color 0.15s ease;
}
.log-row:hover {
    background-color: rgba(0, 42, 84, 0.04) !important;
}
.badge-level {
    font-size: 0.72rem;
    padding: 0.25rem 0.5rem;
}
</style>

<div class="content-wrapper-inner p-3">
    <div class="card card-primary card-outline shadow-sm mb-4">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><i class="fas fa-terminal mr-2 text-warning"></i>Analizador de Archivos de Logs</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-7">
                    <div id="drop-zone" class="drop-zone p-5 text-center d-flex flex-column align-items-center justify-content-center" style="min-height: 250px;">
                        <i class="fas fa-file-code fa-4x text-warning mb-3"></i>
                        <h4 class="font-weight-bold">Arrastra tu archivo de log aquí</h4>
                        <p class="text-muted small">Admite formatos de servidores Nginx, Apache, PHP error logs, Syslog y texto genérico (.log, .txt, .csv)</p>
                        <button class="btn btn-warning btn-sm font-weight-bold px-4 mt-2 text-white" onclick="$('#log-file-input').click()">
                            Seleccionar Archivo
                        </button>
                        <input type="file" id="log-file-input" class="d-none" accept=".log,.txt,.csv,.err,.out">
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="card shadow-none border h-100 bg-light">
                        <div class="card-body d-flex flex-column justify-content-center">
                            <h5><i class="fas fa-info-circle text-info mr-2"></i>¿Cómo funciona?</h5>
                            <p class="text-muted small mt-2">
                                Sube archivos de registro de tus servidores web o sistemas operativos. El analizador de forma local y dinámica:
                            </p>
                            <ul class="text-muted small pl-3">
                                <li>Detecta automáticamente el formato (Web, PHP, Syslog).</li>
                                <li>Clasifica los eventos por severidad (Éxito, Información, Advertencia, Error).</li>
                                <li>Muestra diagramas interactivos de actividad temporal y distribución.</li>
                                <li>Genera recomendaciones técnicas basadas en patrones de error.</li>
                            </ul>
                            <small class="text-muted mt-2"><i class="fas fa-exclamation-triangle mr-1 text-warning"></i>Límite de lectura: primeras 10,000 líneas para optimizar el rendimiento del servidor.</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Progress loader -->
            <div id="loader-section" class="d-none mt-4 text-center">
                <i class="fas fa-spinner fa-spin fa-3x text-warning mb-2"></i>
                <h5>Analizando archivo de log...</h5>
                <div class="progress progress-sm mt-3" style="max-width: 400px; margin: 0 auto;">
                    <div class="progress-bar bg-warning progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN REPORT REGION (HIDDEN UNTIL ANALYZED) -->
    <div id="report-section" class="d-none">
        
        <!-- Summary Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="info-box shadow-sm border">
                    <span class="info-box-icon bg-info"><i class="fas fa-list"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text text-muted small">Líneas Totales</span>
                        <span class="info-box-number font-weight-bold" id="stat-lines">0</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box shadow-sm border">
                    <span class="info-box-icon bg-danger"><i class="fas fa-times-circle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text text-muted small">Errores Críticos</span>
                        <span class="info-box-number font-weight-bold" id="stat-errors">0</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box shadow-sm border">
                    <span class="info-box-icon bg-warning"><i class="fas fa-exclamation-triangle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text text-muted small">Advertencias</span>
                        <span class="info-box-number font-weight-bold" id="stat-warnings">0</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box shadow-sm border">
                    <span class="info-box-icon bg-success"><i class="fas fa-cog"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text text-muted small">Formato Detectado</span>
                        <span class="info-box-number font-weight-bold text-uppercase" id="stat-format">N/A</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border">
                    <div class="card-header bg-transparent border-bottom-0 pb-0">
                        <h3 class="card-title font-weight-bold text-muted small">DISTRIBUCIÓN DE SEVERIDAD</h3>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center" style="min-height: 250px;">
                        <div style="width: 100%; height: 220px; position: relative;">
                            <canvas id="chart-severity"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card h-100 shadow-sm border">
                    <div class="card-header bg-transparent border-bottom-0 pb-0">
                        <h3 class="card-title font-weight-bold text-muted small">LÍNEA DE TIEMPO (EVENTOS DETECTADOS POR HORA)</h3>
                    </div>
                    <div class="card-body" style="min-height: 250px;">
                        <div style="width: 100%; height: 220px; position: relative;">
                            <canvas id="chart-timeline"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Insights and Stats Lists -->
        <div class="row mb-4">
            <div class="col-md-6" id="specific-stats-col">
                <div class="card h-100 shadow-sm border">
                    <div class="card-header">
                        <h3 class="card-title font-weight-bold" id="specific-stats-title"><i class="fas fa-chart-line mr-2"></i>Estadísticas Clave</h3>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush" id="specific-stats-list">
                            <!-- Dynamic stats lists -->
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card h-100 shadow-sm border">
                    <div class="card-header">
                        <h3 class="card-title font-weight-bold"><i class="fas fa-lightbulb mr-2 text-warning"></i>Recomendaciones y Diagnósticos</h3>
                    </div>
                    <div class="card-body" id="insights-container">
                        <!-- Dynamic Insights -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Filterable Interactive Logs Table -->
        <div class="card shadow-sm border mb-4">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap pb-2" style="gap: 10px;">
                <h3 class="card-title font-weight-bold m-0"><i class="fas fa-list-ul mr-2 text-info"></i>Detalle de Eventos del Archivo</h3>
                
                <div class="d-flex align-items-center flex-wrap" style="gap: 10px;">
                    <!-- Filter buttons -->
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary active font-weight-bold" onclick="filterTable('all', this)">Todos</button>
                        <button type="button" class="btn btn-outline-danger font-weight-bold" onclick="filterTable('error', this)">Errores</button>
                        <button type="button" class="btn btn-outline-warning font-weight-bold" onclick="filterTable('warning', this)">Advertencias</button>
                        <button type="button" class="btn btn-outline-info font-weight-bold" onclick="filterTable('info', this)">Info</button>
                        <button type="button" class="btn btn-outline-success font-weight-bold" onclick="filterTable('success', this)">Éxito</button>
                    </div>
                    
                    <!-- Search input -->
                    <div class="input-group input-group-sm" style="max-width: 250px;">
                        <input type="text" id="table-search" class="form-control" placeholder="Buscar en logs...">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-sm table-striped m-0" id="logs-table">
                        <thead class="bg-light sticky-top">
                            <tr>
                                <th style="width: 70px;">Línea</th>
                                <th style="width: 140px;">Fecha/Hora</th>
                                <th style="width: 90px;">Nivel</th>
                                <th>Mensaje / Evento</th>
                                <th>Detalles Extra</th>
                            </tr>
                        </thead>
                        <tbody id="logs-table-body">
                            <!-- Dynamic rows via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-transparent d-flex justify-content-between align-items-center border-top">
                <span class="text-muted small" id="table-pagination-info">Mostrando 0 de 0 registros</span>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm m-0" id="table-pagination-controls">
                        <!-- Dynamic page list -->
                    </ul>
                </nav>
            </div>
        </div>

    </div>
</div>

<?php include 'partials/footer.php'; ?>

<script>
// Log Data
let logData = null;
let filteredData = [];
let currentPage = 1;
const pageSize = 100;

// Charts instances
let severityChart = null;
let timelineChart = null;

$(document).ready(function() {
    // Configurar Drag & Drop
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('log-file-input');

    if (dropZone) {
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            if (e.dataTransfer.files.length > 0) {
                uploadLogFile(e.dataTransfer.files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                uploadLogFile(e.target.files[0]);
            }
        });
    }

    // Configurar búsqueda local de tabla
    $('#table-search').on('input', function() {
        applyFilters();
    });
});

// Subir y analizar el archivo
function uploadLogFile(file) {
    if (!file) return;

    let fd = new FormData();
    fd.append('action', 'analyze');
    fd.append('log_file', file);

    $('#loader-section').removeClass('d-none');
    $('#report-section').addClass('d-none');

    $.ajax({
        url: 'log_analysis.php',
        type: 'POST',
        data: fd,
        contentType: false,
        processData: false,
        dataType: 'json',
        success: function(res) {
            $('#loader-section').addClass('d-none');
            if (res.success) {
                toastr.success("Log analizado correctamente.");
                logData = res;
                filteredData = res.parsed_lines;
                renderReport();
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        },
        error: function() {
            $('#loader-section').addClass('d-none');
            Swal.fire('Error', 'Fallo al procesar el archivo en el servidor.', 'error');
        }
    });
}

// Renderizar reporte completo
function renderReport() {
    $('#report-section').removeClass('d-none');

    // Desplazamiento animado al reporte
    $('html, body').animate({
        scrollTop: $("#report-section").offset().top - 20
    }, 800);

    // Llenar Tarjetas de Resumen
    $('#stat-lines').text(logData.total_lines.toLocaleString());
    $('#stat-errors').text(logData.severity_counts.error.toLocaleString());
    $('#stat-warnings').text(logData.severity_counts.warning.toLocaleString());
    
    let formatLabel = 'Genérico';
    if (logData.log_type === 'webserver') formatLabel = 'Servidor Web';
    if (logData.log_type === 'php') formatLabel = 'Errores PHP';
    if (logData.log_type === 'syslog') formatLabel = 'Syslog';
    $('#stat-format').text(formatLabel);

    // Destruir gráficos anteriores si existen
    if (severityChart) severityChart.destroy();
    if (timelineChart) timelineChart.destroy();

    // Renderizar gráfico de torta (Severidad)
    const sevCtx = document.getElementById('chart-severity').getContext('2d');
    severityChart = new Chart(sevCtx, {
        type: 'doughnut',
        data: {
            labels: ['Errores', 'Advertencias', 'Información', 'Éxito', 'Desconocido'],
            datasets: [{
                data: [
                    logData.severity_counts.error,
                    logData.severity_counts.warning,
                    logData.severity_counts.info,
                    logData.severity_counts.success,
                    logData.severity_counts.unknown
                ],
                backgroundColor: ['#dc3545', '#ffc107', '#17a2b8', '#28a745', '#6c757d'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, font: { size: 10 } }
                }
            }
        }
    });

    // Renderizar gráfico de línea (Actividad temporal)
    const timelineLabels = Object.keys(logData.timeline);
    const timelineValues = Object.values(logData.timeline);
    const lineCtx = document.getElementById('chart-timeline').getContext('2d');
    
    timelineChart = new Chart(lineCtx, {
        type: 'line',
        data: {
            labels: timelineLabels,
            datasets: [{
                label: 'Eventos registrados',
                data: timelineValues,
                borderColor: '#FF5C05',
                backgroundColor: 'rgba(255, 92, 5, 0.05)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                x: { grid: { display: false } }
            }
        }
    });

    // Renderizar Estadísticas Clave
    let statsTitle = '<i class="fas fa-chart-line mr-2"></i>Estadísticas de Frecuencia';
    let statsHtml = '';

    if (logData.log_type === 'webserver') {
        statsTitle = '<i class="fas fa-network-wired mr-2 text-info"></i>Top 10 Direcciones IP y Peticiones';
        
        statsHtml += '<div class="p-3 bg-light border-bottom font-weight-bold text-muted small"><i class="fas fa-laptop-code mr-1"></i>IPS MÁS ACTIVAS</div>';
        if (Object.keys(logData.top_ips).length === 0) {
            statsHtml += '<li class="list-group-item text-center text-muted small py-3">Sin datos.</li>';
        } else {
            for (let [ip, count] of Object.entries(logData.top_ips)) {
                statsHtml += `
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                        <span class="small font-weight-bold text-dark">${ip}</span>
                        <span class="badge badge-info badge-pill">${count}</span>
                    </li>
                `;
            }
        }

        statsHtml += '<div class="p-3 bg-light border-bottom border-top font-weight-bold text-muted small"><i class="fas fa-globe mr-1"></i>PAGINAS MÁS SOLICITADAS</div>';
        if (Object.keys(logData.top_requests).length === 0) {
            statsHtml += '<li class="list-group-item text-center text-muted small py-3">Sin datos.</li>';
        } else {
            for (let [uri, count] of Object.entries(logData.top_requests)) {
                statsHtml += `
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                        <span class="small text-truncate text-muted d-inline-block" style="max-width: 80%;" title="${uri}">${uri}</span>
                        <span class="badge badge-secondary badge-pill">${count}</span>
                    </li>
                `;
            }
        }
    } else {
        statsTitle = '<i class="fas fa-exclamation-circle mr-2 text-danger"></i>Eventos Más Repetidos';
        statsHtml += '<div class="p-3 bg-light border-bottom font-weight-bold text-muted small"><i class="fas fa-bug mr-1"></i>MENSAJES RECURRENTES</div>';
        if (Object.keys(logData.top_messages).length === 0) {
            statsHtml += '<li class="list-group-item text-center text-muted small py-3">Sin registros de errores agrupados.</li>';
        } else {
            for (let [msg, count] of Object.entries(logData.top_messages)) {
                statsHtml += `
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                        <span class="small text-muted text-truncate d-inline-block" style="max-width: 85%;" title="${msg}">${msg}</span>
                        <span class="badge badge-warning badge-pill">${count}</span>
                    </li>
                `;
            }
        }
    }
    $('#specific-stats-title').html(statsTitle);
    $('#specific-stats-list').html(statsHtml);

    // Renderizar Recomendaciones (Insights)
    let insightsHtml = '';
    logData.insights.forEach(ins => {
        let icon = 'fa-info-circle text-info';
        if (ins.type === 'danger') icon = 'fa-times-circle text-danger';
        if (ins.type === 'warning') icon = 'fa-exclamation-triangle text-warning';
        if (ins.type === 'success') icon = 'fa-check-circle text-success';

        insightsHtml += `
            <div class="callout callout-${ins.type} mb-3 shadow-none border">
                <h5><i class="fas ${icon} mr-2"></i>${ins.title}</h5>
                <p class="small text-muted mb-0">${ins.description}</p>
            </div>
        `;
    });
    $('#insights-container').html(insightsHtml);

    // Inicializar visualización de tabla
    currentPage = 1;
    applyFilters();
}

// Filtro de severidad clicable
let currentSeverityFilter = 'all';

function filterTable(severity, btn) {
    currentSeverityFilter = severity;
    $(btn).siblings().removeClass('active');
    $(btn).addClass('active');
    currentPage = 1;
    applyFilters();
}

// Aplicar filtros locales (búsqueda y severidad)
function applyFilters() {
    let search = $('#table-search').val().toLowerCase().trim();
    
    filteredData = logData.parsed_lines.filter(pl => {
        // Filtro de nivel
        if (currentSeverityFilter !== 'all' && pl.level !== currentSeverityFilter) {
            return false;
        }
        
        // Filtro de búsqueda
        if (search !== '') {
            let msgMatch = pl.message.toLowerCase().includes(search);
            let extraMatch = pl.extra ? pl.extra.toLowerCase().includes(search) : false;
            let ipMatch = pl.ip ? pl.ip.toLowerCase().includes(search) : false;
            let timeMatch = pl.timestamp ? pl.timestamp.toLowerCase().includes(search) : false;
            return msgMatch || extraMatch || ipMatch || timeMatch;
        }
        
        return true;
    });

    renderTable();
}

// Renderizar filas de tabla y paginación
function renderTable() {
    let start = (currentPage - 1) * pageSize;
    let end = start + pageSize;
    let sliced = filteredData.slice(start, end);

    let html = '';
    if (sliced.length === 0) {
        html = '<tr><td colspan="5" class="text-center py-4 text-muted">No se encontraron registros de log que coincidan.</td></tr>';
    } else {
        sliced.forEach(row => {
            let badgeClass = 'badge-secondary';
            if (row.level === 'error') badgeClass = 'badge-danger';
            if (row.level === 'warning') badgeClass = 'badge-warning';
            if (row.level === 'success') badgeClass = 'badge-success';
            if (row.level === 'info') badgeClass = 'badge-info';

            let extraVal = row.extra ? `<code class="small">${row.extra}</code>` : '-';
            let ipVal = row.ip ? `<span class="badge badge-dark mr-1">${row.ip}</span> ` : '';

            html += `
                <tr class="log-row">
                    <td class="text-muted font-weight-bold small">${row.line}</td>
                    <td class="small">${row.timestamp || '-'}</td>
                    <td><span class="badge ${badgeClass} badge-level text-uppercase">${row.level}</span></td>
                    <td class="small text-wrap" style="word-break: break-all;">${ipVal}${row.message}</td>
                    <td class="small">${extraVal}</td>
                </tr>
            `;
        });
    }

    $('#logs-table-body').html(html);

    // Actualizar paginador e info
    let total = filteredData.length;
    let showingEnd = Math.min(end, total);
    let showingStart = total === 0 ? 0 : start + 1;
    $('#table-pagination-info').text(`Mostrando ${showingStart} a ${showingEnd} de ${total.toLocaleString()} registros`);

    // Renderizar controles de paginación
    let totalPages = Math.ceil(total / pageSize);
    let pagHtml = '';

    if (totalPages > 1) {
        // Botón Anterior
        pagHtml += `
            <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="javascript:void(0)" onclick="changePage(${currentPage - 1})"><i class="fas fa-chevron-left"></i></a>
            </li>
        `;

        // Generar rangos de páginas para no mostrar 100 números
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, startPage + 4);
        if (endPage - startPage < 4) {
            startPage = Math.max(1, endPage - 4);
        }

        if (startPage > 1) {
            pagHtml += `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="changePage(1)">1</a></li>`;
            if (startPage > 2) {
                pagHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            pagHtml += `
                <li class="page-item ${currentPage === i ? 'active' : ''}">
                    <a class="page-link" href="javascript:void(0)" onclick="changePage(${i})">${i}</a>
                </li>
            `;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                pagHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            pagHtml += `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="changePage(${totalPages})">${totalPages}</a></li>`;
        }

        // Botón Siguiente
        pagHtml += `
            <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="javascript:void(0)" onclick="changePage(${currentPage + 1})"><i class="fas fa-chevron-right"></i></a>
            </li>
        `;
    }

    $('#table-pagination-controls').html(pagHtml);
}

// Cambiar de página
function changePage(page) {
    let total = filteredData.length;
    let totalPages = Math.ceil(total / pageSize);
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    renderTable();
}
</script>
