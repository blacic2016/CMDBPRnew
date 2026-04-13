<?php
/**
 * CMDB VILASECA - Panel de Monitoreo Zabbix (Premium)
 * Ubicación: /var/www/html/VILASECA/CMDBPRnew/public/monitoreo.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/zabbix_api.php';

require_login(); 

$host_count = '0';
$item_count = '0';
$api_error = null;
$problems_by_severity = [];

try {
    $res_hosts = call_zabbix_api('host.get', ['countOutput' => true]);
    if (isset($res_hosts['error'])) throw new Exception($res_hosts['error']['data'] ?? "Error API");
    $host_count = $res_hosts['result'];

    $res_items = call_zabbix_api('item.get', ['countOutput' => true, 'monitored' => true]);
    $item_count = $res_items['result'] ?? 0;

    $res_triggers = call_zabbix_api('trigger.get', [
        'output' => ['priority'],
        'only_true' => 1,
        'monitored' => 1
    ]);

    $severities = [
        0 => ['name' => 'No clasificado', 'count' => 0, 'bg' => 'secondary'],
        1 => ['name' => 'Información',   'count' => 0, 'bg' => 'info'],
        2 => ['name' => 'Advertencia',   'count' => 0, 'bg' => 'warning'],
        3 => ['name' => 'Promedio',      'count' => 0, 'bg' => 'orange'],
        4 => ['name' => 'Alta',          'count' => 0, 'bg' => 'danger'],
        5 => ['name' => 'Desastre',      'count' => 0, 'bg' => 'maroon']
    ];

    foreach ($res_triggers['result'] ?? [] as $trigger) {
        $p = (int)$trigger['priority'];
        if (isset($severities[$p])) $severities[$p]['count']++;
    }
    $problems_by_severity = $severities;

} catch (Exception $e) {
    $api_error = $e->getMessage();
}

$page_title = 'Estado del Monitoreo';
require_once __DIR__ . '/partials/header.php'; 
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<style>
    .monitor-box { border-radius: 15px; overflow: hidden; position: relative; transition: all 0.3s; border: none; }
    .monitor-box:hover { transform: scale(1.03); }
    .stat-icon { font-size: 3rem; position: absolute; right: 15px; bottom: 15px; opacity: 0.15; }
    .severity-card { border-radius: 12px; transition: all 0.2s; border: 1px solid rgba(0,0,0,0.05); }
    .severity-card:hover { border-color: rgba(0,0,0,0.2); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
    .bg-gradient-maroon { background: linear-gradient(135deg, #d81b60 0%, #880e4f 100%); }
    .bg-gradient-danger { background: linear-gradient(135deg, #dc3545 0%, #a71d2a 100%); }
    .bg-gradient-orange { background: linear-gradient(135deg, #fd7e14 0%, #e65100 100%); }
</style>

<div class="container-fluid pt-4 pb-5">
    
    <div class="row mb-5 animate__animated animate__fadeInDown">
        <div class="col-md-9">
            <h1 class="display-5 font-weight-bold text-dark"><i class="fas fa-satellite-dish text-primary mr-3"></i>Centro de Monitoreo</h1>
            <p class="text-muted lead">Estado dinámico de la infraestructura tecnológica de VILASECA.</p>
        </div>
        <div class="col-md-3 text-right">
            <div class="p-3 bg-white rounded-lg shadow-sm">
                <small class="text-uppercase text-muted font-weight-bold">Zabbix Server</small>
                <div class="h5 mb-0 text-success"><i class="fas fa-circle small mr-2"></i>Conectado</div>
            </div>
        </div>
    </div>

    <?php if ($api_error): ?>
        <div class="alert alert-danger shadow-sm mb-4 animate__animated animate__shakeX">
            <h5><i class="icon fas fa-plug"></i> Error de Conectividad</h5>
            <?php echo htmlspecialchars($api_error); ?>
        </div>
    <?php endif; ?>

    <!-- Main Stats Row -->
    <div class="row mb-5 animate__animated animate__fadeIn">
        <div class="col-lg-4">
            <div class="card bg-primary text-white monitor-box shadow-lg h-100">
                <div class="card-body">
                    <h5 class="opacity-7 text-uppercase small">Total Equipos</h5>
                    <div class="display-4 font-weight-bold"><?php echo number_format($host_count); ?></div>
                    <p class="mb-0 mt-3 small"><i class="fas fa-check-circle mr-1"></i> Sincronizados con el CMDB</p>
                    <i class="fas fa-server stat-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card bg-info text-white monitor-box shadow-lg h-100">
                <div class="card-body">
                    <h5 class="opacity-7 text-uppercase small">Items en Seguimiento</h5>
                    <div class="display-4 font-weight-bold"><?php echo number_format($item_count); ?></div>
                    <p class="mb-0 mt-3 small"><i class="fas fa-chart-line mr-1"></i> Telemetría activa</p>
                    <i class="fas fa-microchip stat-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <a href="crear_monitoreo.php" class="card bg-dark text-white monitor-box shadow-lg h-100 text-decoration-none">
                <div class="card-body">
                    <h5 class="opacity-7 text-uppercase small">Configurar Nuevo</h5>
                    <div class="display-4 font-weight-bold"><i class="fas fa-plus-circle"></i></div>
                    <p class="mb-0 mt-3 small text-warning">Añadir equipos del CMDB al monitoreo</p>
                    <i class="fas fa-cog stat-icon"></i>
                </div>
            </a>
        </div>
    </div>

    <!-- Severity Grid -->
    <h4 class="font-weight-bold mb-4 animate__animated animate__fadeIn">Alertas por Severidad</h4>
    <div class="row animate__animated animate__fadeInUp">
        <?php foreach($problems_by_severity as $id => $sev): ?>
            <div class="col-lg-2 col-md-4 col-6 mb-4">
                <a href="problems.php?severity=<?php echo $id; ?>" class="card severity-card h-100 text-decoration-none">
                    <div class="card-body text-center">
                        <div class="mb-2 text-<?php echo $sev['bg']; ?>">
                            <i class="fas fa-exclamation-circle fa-2x"></i>
                        </div>
                        <div class="h3 font-weight-bold mb-0 <?php echo $sev['count'] > 0 ? 'text-'.$sev['bg'] : 'text-muted'; ?>">
                            <?php echo $sev['count']; ?>
                        </div>
                        <div class="small font-weight-bold text-uppercase text-muted" style="letter-spacing: 1px;">
                            <?php echo $sev['name']; ?>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Background Actions -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="p-4 bg-white rounded-lg shadow-sm border d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1 font-weight-bold">Acciones de Administración</h5>
                    <p class="text-muted mb-0 small">Mantenimiento de vinculaciones Zabbix-CMDB.</p>
                </div>
                <div>
                    <a href="actualizar_monitoreo.php" class="btn btn-outline-dark px-4 mr-2">
                        <i class="fas fa-sync-alt mr-2"></i>Actualizar Masivo
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>