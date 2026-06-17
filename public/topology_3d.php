<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$page_title = 'Topología de Red 3D (Experimental)';
require_once __DIR__ . '/partials/header.php';
?>

<!-- Contenedor Principal -->
<div class="card card-outline card-primary shadow-lg overflow-hidden" style="height: calc(100vh - 180px); min-height: 600px;">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="fas fa-cube mr-2"></i>Visualizador 3D Inmersivo</h3>
        
        <div class="card-tools ml-auto d-flex align-items-center">
            <div class="d-flex align-items-center mr-3 ml-2" style="color: #666; font-size: 13px;">
                <i class="fas fa-arrows-alt-h mr-2"></i>
                <input type="range" id="distance-range-3d" min="0.5" max="3" step="0.1" value="1" style="width: 100px;">
                <span class="ml-2 font-weight-bold" id="distance-val">1.0</span>
            </div>
            <div class="input-group input-group-sm mr-2" style="width: 200px;">
                <input type="text" id="search-node-3d" class="form-control" placeholder="Buscar equipo...">
                <div class="input-group-append">
                    <button class="btn btn-default"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <select id="subgrupo-select" class="form-control form-control-sm select2bs4 mr-2" style="min-width: 200px;">
                <option value="">Cargando Grupos...</option>
            </select>
            <select id="layout-3d-select" class="form-control form-control-sm mr-2" style="width: 120px;">
                <option value="sphere">Esfera</option>
                <option value="spiral">Helicoidal</option>
                <option value="grid">Grid</option>
            </select>
            <button id="toggle-down-ports-3d" class="btn btn-sm btn-outline-info mr-2" data-show="false">
                <i class="fas fa-eye-slash"></i> Puertos Down
            </button>
            <button id="refresh-3d-btn" class="btn btn-sm btn-primary">
                <i class="fas fa-sync-alt"></i>
            </button>
            <button class="btn btn-tool" data-card-widget="maximize"><i class="fas fa-expand-arrows-alt"></i></button>
        </div>
    </div>
    
    <div class="card-body p-0 position-relative" style="background: radial-gradient(circle, #f8f9fa 0%, #e9ecef 100%); cursor: move;">
        <!-- Canvas para Three.js -->
        <div id="three-container" style="width: 100%; height: 100%;"></div>
        
        <!-- UI flotante -->
        <div class="position-absolute" style="top: 15px; left: 15px; z-index: 100;">
            <div class="badge badge-info p-2 shadow-sm" style="border-radius: 20px; background: rgba(22, 33, 62, 0.8); backdrop-filter: blur(5px);">
                <i class="fas fa-mouse mr-1"></i> Arrastrar: Rotar | Scroll: Zoom | Botón Derecho: Desplazar
            </div>
        </div>

        <div id="loading-overlay" class="overlay d-none" style="background: rgba(0,0,0,0.4);">
            <div class="text-center text-white">
                <i class="fas fa-3x fa-cog fa-spin mb-3"></i>
                <p>Generando Entorno 3D...</p>
            </div>
        </div>

        <!-- Panel de Detalles Flotante -->
        <div id="node-info-panel" class="position-absolute d-none" style="bottom: 20px; left: 20px; width: 300px; z-index: 100;">
            <div class="card card-dark shadow-lg m-0" style="background: rgba(22, 33, 62, 0.95); backdrop-filter: blur(10px); border: 1px solid #1f4068;">
                <div class="card-body p-3">
                    <h5 id="node-name" class="text-info mb-1">Nombre Equipo</h5>
                    <p id="node-ip" class="small text-muted mb-2">127.0.0.1</p>
                    <hr class="mt-2 mb-2" style="border-color: #1f4068;">
                    <div id="node-details">
                        <span class="badge badge-success">Online</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Styles -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<style>
    .select2-container--default .select2-selection--single { background: #1a1a2e; border: 1px solid #1f4068; color: #fff; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { color: #fff; }
</style>

<?php require_once __DIR__ . '/partials/footer.php'; ?>

<!-- Three.js and Dependencies -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/renderers/CSS2DRenderer.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Script de Topología 3D -->
<script src="assets/js/topology_3d.js?v=<?php echo time(); ?>"></script>
