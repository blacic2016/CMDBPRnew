<?php
/**
 * CMDB VILASECA - Detalle de Problemas (Premium)
 * Ubicación: /var/www/html/VILASECA/CMDBPRnew/public/problems.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/zabbix_api.php';

require_login();

function format_zabbix_duration($seconds) {
    if ($seconds < 60) return "{$seconds}s";
    $m = floor($seconds / 60);
    $s = $seconds % 60;
    if ($m < 60) return "{$m}m {$s}s";
    $h = floor($m / 60);
    $m = $m % 60;
    if ($h < 24) return "{$h}h {$m}m";
    $d = floor($h / 24);
    $h = $h % 24;
    return "{$d}d {$h}h";
}

$severity_id = isset($_GET['severity']) ? (int)$_GET['severity'] : -1;
$severities = [
    0 => ['name' => 'No clasificado', 'bg' => 'secondary'], 1 => ['name' => 'Información', 'bg' => 'info'],
    2 => ['name' => 'Advertencia', 'bg' => 'warning'], 3 => ['name' => 'Promedio', 'bg' => 'orange'],
    4 => ['name' => 'Alta', 'bg' => 'danger'], 5 => ['name' => 'Desastre', 'bg' => 'maroon']
];

$severity_name = "Todos";
$problems = [];
$api_error = null;

$api_params = [
    'output' => 'extend',
    'selectHosts' => ['host'],
    'only_true' => 1,
    'expandDescription' => 1,
    'sortfield' => 'lastchange',
    'sortorder' => 'DESC'
];

if (isset($severities[$severity_id])) {
    $api_params['filter'] = ['priority' => $severity_id];
    $severity_name = $severities[$severity_id]['name'];
}

try {
    $response = call_zabbix_api('trigger.get', $api_params);
    if (isset($response['error'])) throw new Exception($response['error']['data'] ?? "Error API");
    $problems = $response['result'] ?? [];
} catch (Exception $e) {
    $api_error = $e->getMessage();
}

$page_title = 'Problemas Detectados';
require_once __DIR__ . '/partials/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<style>
    .problem-table-card { border: none; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
    .table-premium thead th { background: #f8f9fa; border-top: none; color: #666; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; padding: 15px 20px; }
    .table-premium tbody td { vertical-align: middle; padding: 18px 20px; }
    .host-badge { background: #e9ecef; color: #495057; font-weight: bold; border-radius: 4px; padding: 4px 8px; font-size: 11px; }
    .duration-text { font-family: monospace; font-weight: bold; color: #dc3545; }
</style>

<div class="container-fluid pt-4 pb-5">

    <div class="row mb-4 animate__animated animate__fadeIn">
        <div class="col-md-8">
            <h1 class="font-weight-bold h2 mb-1">
                <i class="fas fa-exclamation-triangle text-danger mr-2"></i>Alarmas: 
                <span class="text-<?php echo $severities[$severity_id]['bg'] ?? 'dark'; ?>">
                    <?php echo htmlspecialchars($severity_name); ?>
                </span>
            </h1>
            <p class="text-muted">Desglose de eventos activos detectados por el sistema de monitoreo.</p>
        </div>
        <div class="col-md-4 text-right">
            <a href="monitoreo.php" class="btn btn-light shadow-sm px-4">
                <i class="fas fa-arrow-left mr-2"></i>Volver al Panel
            </a>
        </div>
    </div>

    <?php if ($api_error): ?>
        <div class="alert alert-danger shadow-sm mb-4"><?php echo htmlspecialchars($api_error); ?></div>
    <?php endif; ?>

    <div class="problem-table-card bg-white animate__animated animate__fadeInUp">
        <div class="table-responsive">
            <table class="table table-hover table-premium mb-0">
                <thead>
                    <tr>
                        <th style="width: 150px;">Antigüedad</th>
                        <th>Origen (Host)</th>
                        <th>Descripción del Problema</th>
                        <th style="width: 150px;" class="text-center">Severidad</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($problems)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">
                                <i class="fas fa-check-circle fa-3x mb-3 text-success opacity-2"></i>
                                <h5>No se encontraron problemas activos</h5>
                                <p>Todos los sistemas operan dentro de los parámetros normales.</p>
                            </td>
                        </tr>
                    <?php else: foreach ($problems as $problem): ?>
                        <tr>
                            <td>
                                <span class="duration-text">
                                    <i class="far fa-clock mr-1"></i>
                                    <?php echo format_zabbix_duration(time() - $problem['lastchange']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="host-badge">
                                    <i class="fas fa-desktop mr-1"></i>
                                    <?php echo htmlspecialchars($problem['hosts'][0]['host'] ?? 'N/A'); ?>
                                </span>
                            </td>
                            <td>
                                <div class="font-weight-bold text-dark"><?php echo htmlspecialchars($problem['description']); ?></div>
                                <small class="text-muted">Aparición: <?php echo date("d/m/Y H:i:s", $problem['lastchange']); ?></small>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-<?php echo $severities[$problem['priority']]['bg']; ?> px-3 py-1">
                                    <?php echo htmlspecialchars($severities[$problem['priority']]['name']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
