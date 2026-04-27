<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$page_title = 'Topología de Red Dinámica';
require_once __DIR__ . '/partials/header.php';
?>

<script>
    // Dinamicamente obtener la URL base de Zabbix desde la configuración PHP
    const api_url = '<?php echo ZABBIX_API_URL; ?>';
    window.zabbixBaseUrl = api_url.replace('api_jsonrpc.php', '');
</script>





    <!-- Filter Bar -->
    <div class="card card-outline card-primary shadow-sm mb-4">

                <div class="card-body p-3">
                    <div class="row align-items-end">
                        <div class="col-md-5">
                            <label class="small text-muted mb-1">Grupo de Host (Zabbix)</label>
                            <select id="subgrupo-select" class="form-control select2bs4">
                                <option value="">Seleccione un Grupo</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="small text-muted mb-1">Filtro de Estado</label>
                            <div class="custom-control custom-switch mt-1">
                                <input type="checkbox" class="custom-control-input" id="toggle-down-ports" checked>
                                <label class="custom-control-label small" for="toggle-down-ports">Ver Puertos Down</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="small text-muted mb-1">Diseño</label>
                            <select id="layout-select" class="form-control select2bs4">
                                <option value="tree">Árbol (Vertical)</option>
                                <option value="network">Red (Horizontal)</option>
                                <option value="star">Estrella (Radial)</option>
                                <option value="grid">Grilla (Cuadrados)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button id="generate-btn" class="btn btn-primary btn-block pt-2 pb-2" disabled>
                                <i class="fas fa-sync-alt"></i> Actualizar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Diagram Container -->
            <div class="card shadow-lg" style="height: calc(100vh - 250px); min-height: 600px;">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title" id="diagram-title"><i class="fas fa-network-wired mr-2"></i>Visualizador de Topología</h3>
                    <div class="card-tools ml-auto">
                        <button class="btn btn-tool" onclick="zoomIn()" title="Zoom In"><i class="fas fa-plus"></i></button>
                        <button class="btn btn-tool" onclick="zoomOut()" title="Zoom Out"><i class="fas fa-minus"></i></button>
                        <button class="btn btn-tool" onclick="zoomToFit()" title="Ajustar Pantalla"><i class="fas fa-expand"></i></button>
                        <button class="btn btn-tool" data-card-widget="maximize"><i class="fas fa-expand-arrows-alt"></i></button>
                    </div>
                </div>
                <div class="card-body p-0 position-relative">
                    <div id="myDiagramDiv" style="width:100%; height:100%; background-color: #f8f9fa;"></div>
                    
                    <!-- Overview Minimap -->
                    <div id="myOverviewDiv" style="position: absolute; bottom: 15px; right: 15px; width: 200px; height: 130px; border: 1px solid #E5E7E9; background-color: white; z-index: 100; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-radius: 8px; overflow: hidden; pointer-events: auto;">
                    </div>
                    
                    <!-- Loading Overlay -->
                    <div id="diagram-loader" class="overlay d-none">
                        <i class="fas fa-2x fa-sync-alt fa-spin"></i>
                    </div>
                </div>
            </div>

<!-- Styles -->
<link rel="stylesheet" href="assets/css/topology.css">
<!-- Select2 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">

<?php require_once __DIR__ . '/partials/footer.php'; ?>

<!-- Scripts required for Topology (Loaded after footer to use its jQuery) -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://unpkg.com/gojs/release/go.js"></script>
<script src="assets/js/topology.js?v=<?php echo time(); ?>"></script>

