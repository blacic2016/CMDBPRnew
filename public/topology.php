<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$page_title = 'Topología de Red Dinámica';
require_once __DIR__ . '/partials/header.php';
?>

<div class="content-wrapper">
    <!-- Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-project-diagram mr-2"></i>Topología de Red</h1>
                </div>
            </div>
        </div>
    </section>

    <!-- Filter Bar -->
    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline card-primary shadow-sm mb-4">
                <div class="card-body p-3">
                    <div class="row align-items-end">
                        <div class="col-md-6">
                            <label class="small text-muted mb-1">Grupo de Host (Zabbix)</label>
                            <select id="subgrupo-select" class="form-control select2bs4">
                                <option value="">Seleccione un Grupo</option>
                            </select>
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
                            <button id="generate-btn" class="btn btn-primary btn-block" disabled>
                                <i class="fas fa-sync-alt mr-1"></i> Generar Gráfico
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
                        <button class="btn btn-tool" onclick="zoomToFit()"><i class="fas fa-expand"></i> Ajustar</button>
                        <button class="btn btn-tool" data-card-widget="maximize"><i class="fas fa-expand-arrows-alt"></i></button>
                    </div>
                </div>
                <div class="card-body p-0 position-relative">
                    <div id="myDiagramDiv" style="width:100%; height:100%; background-color: #f8f9fa;"></div>
                    
                    <!-- Loading Overlay -->
                    <div id="diagram-loader" class="overlay d-none">
                        <i class="fas fa-2x fa-sync-alt fa-spin"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>
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
<script src="assets/js/topology.js"></script>

