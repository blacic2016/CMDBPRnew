<?php
/**
 * alcance.php (Informe de Alcance y Plantillas)
 * Ubicación: /var/www/html/VILASECA/CMDBPRnew/public/informes/alcance.php
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';

require_login();

$page_title = 'Alcance y Plantillas de Monitoreo';
require_once __DIR__ . '/../partials/header.php';
?>

<!-- Datepicker & Select2 bootstrap theme -->
<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.11.5/css/dataTables.bootstrap4.min.css"/>

<!-- JS Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.11.5/js/dataTables.bootstrap4.min.js"></script>

<style>
    .filter-card {
        border-radius: 12px;
        border: 1px solid var(--border-color);
        background: var(--card-bg);
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    }
    .nav-report-tabs {
        border-bottom: 2px solid var(--border-color);
        margin-bottom: 25px;
    }
    .nav-report-link {
        font-weight: 700;
        color: var(--text-muted);
        padding: 10px 20px;
        display: inline-block;
        border-bottom: 3px solid transparent;
        transition: all 0.3s ease;
    }
    .nav-report-link:hover {
        color: var(--sonda-orange);
        text-decoration: none;
    }
    .nav-report-link.active {
        color: var(--sonda-orange);
        border-bottom-color: var(--sonda-orange);
        text-decoration: none;
    }
    .metric-card {
        border-radius: 10px;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .metric-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }
    .badge-severity-0 { background-color: #6c757d; color: white; } /* Not classified */
    .badge-severity-1 { background-color: #17a2b8; color: white; } /* Information */
    .badge-severity-2 { background-color: #ffc107; color: black; } /* Warning */
    .badge-severity-3 { background-color: #fd7e14; color: white; } /* Average */
    .badge-severity-4 { background-color: #e83e8c; color: white; } /* High */
    .badge-severity-5 { background-color: #dc3545; color: white; } /* Disaster */
    
    .status-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 5px;
    }
    .status-dot-active { background-color: #28a745; box-shadow: 0 0 5px #28a745; }
    .status-dot-inactive { background-color: #dc3545; box-shadow: 0 0 5px #dc3545; }

    .nav-pills .nav-link.active {
        background-color: var(--sonda-orange) !important;
        color: #fff !important;
    }
    .nav-pills .nav-link {
        color: var(--text-muted);
        font-weight: 600;
        border: 1px solid var(--border-color);
        margin-right: 8px;
    }
</style>

<div class="container-fluid pt-2">
    <!-- Sub-navigation Tabs (Identical to existing report views) -->
    <div class="nav-report-tabs d-flex align-items-center">
        <a href="index.php" class="nav-report-link mr-3"><i class="fas fa-desktop mr-2"></i>Vista por Equipos</a>
        <a href="grupos.php" class="nav-report-link mr-3"><i class="fas fa-layer-group mr-2"></i>Vista por Grupos</a>
        <a href="alarmas.php" class="nav-report-link mr-3"><i class="fas fa-exclamation-triangle mr-2"></i>Distribución de Alarmas</a>
        <a href="alcance.php" class="nav-report-link active"><i class="fas fa-chart-line mr-2"></i>Alcance y Plantillas</a>
    </div>

    <!-- Inner report tabs -->
    <div class="row mb-3">
        <div class="col-12">
            <ul class="nav nav-pills" id="alcanceReportTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="tab-general-link" data-toggle="pill" href="#pane-general" role="tab"><i class="fas fa-satellite-dish mr-2"></i>1. Alcance de Monitoreo</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab-detalles-link" data-toggle="pill" href="#pane-detalles" role="tab"><i class="fas fa-desktop mr-2"></i>2. Monitoreo por Equipo</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab-plantillas-link" data-toggle="pill" href="#pane-plantillas" role="tab"><i class="fas fa-file-invoice mr-2"></i>3. Alertas de Plantilla Principal</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="tab-content" id="alcanceTabContent">
        <!-- TAB 1: ALCANCE GENERAL DE MONITOREO -->
        <div class="tab-pane fade show active" id="pane-general" role="tabpanel">
            <div id="general-loading" class="text-center py-5 text-primary">
                <i class="fas fa-spinner fa-spin fa-3x mb-3"></i>
                <h5>Cargando alcance general de monitoreo, por favor espere...</h5>
            </div>
            
            <div id="general-content" style="display:none;">
                <!-- Summary Cards Row -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card metric-card shadow-sm border bg-white">
                            <div class="card-body d-flex align-items-center">
                                <div class="p-3 rounded-lg mr-3" style="background: rgba(0,184,212,0.1); color: var(--sonda-cyan);">
                                    <i class="fas fa-server fa-2x"></i>
                                </div>
                                <div>
                                    <small class="text-uppercase text-muted font-weight-bold">Total General Equipos</small>
                                    <h3 class="mb-0 font-weight-bold" id="lbl-total-hosts">0</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card metric-card shadow-sm border bg-white">
                            <div class="card-body d-flex align-items-center">
                                <div class="p-3 rounded-lg mr-3" style="background: rgba(40,167,69,0.1); color: #28a745;">
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                                <div>
                                    <small class="text-uppercase text-muted font-weight-bold">Equipos Monitoreados</small>
                                    <h3 class="mb-0 font-weight-bold text-success" id="lbl-monitored-hosts">0</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card metric-card shadow-sm border bg-white">
                            <div class="card-body d-flex align-items-center">
                                <div class="p-3 rounded-lg mr-3" style="background: rgba(220,53,69,0.1); color: #dc3545;">
                                    <i class="fas fa-times-circle fa-2x"></i>
                                </div>
                                <div>
                                    <small class="text-uppercase text-muted font-weight-bold">Equipos No Monitoreados</small>
                                    <h3 class="mb-0 font-weight-bold text-danger" id="lbl-unmonitored-hosts">0</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Charts & Table Row -->
                <div class="row mb-4">
                    <div class="col-lg-7">
                        <div class="card filter-card h-100 mb-0">
                            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-chart-bar mr-2 text-primary"></i>Distribución de Equipos por Tipo y Estado</h5>
                            </div>
                            <div class="card-body">
                                <div style="height: 320px; position: relative;">
                                    <canvas id="typeStateChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="card filter-card h-100 mb-0">
                            <div class="card-header bg-white border-0 py-3">
                                <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-list mr-2 text-primary"></i>Resumen Cuantitativo por Tipo</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped mb-0" style="font-size:0.85rem">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th>Tipo de Equipo</th>
                                                <th class="text-center">Monitoreados</th>
                                                <th class="text-center">No Monit.</th>
                                                <th class="text-center">Total</th>
                                                <th class="text-center">% Alcance</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbl-type-summary-body">
                                            <!-- JS populated -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monitoring Revision / Methods Section -->
                <div class="row mb-4">
                    <div class="col-lg-4">
                        <div class="card filter-card h-100 mb-0">
                            <div class="card-header bg-white border-0 py-3">
                                <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-chart-pie mr-2 text-primary"></i>Método de Revisión</h5>
                            </div>
                            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                                <div style="height: 220px; width: 100%; position: relative;">
                                    <canvas id="methodChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="card filter-card h-100 mb-0">
                            <div class="card-header bg-white border-0 py-3">
                                <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-stethoscope mr-2 text-primary"></i>Detalle de Métodos de Monitoreo</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center mt-2">
                                    <div class="col-md-3 border-right">
                                        <h2 class="font-weight-bold text-success" id="val-method-snmp">0</h2>
                                        <span class="badge badge-success p-2"><i class="fas fa-network-wired mr-1"></i>Ping y SNMP</span>
                                        <p class="text-muted small mt-2">Dispositivos de red y servidores monitoreados por SNMP.</p>
                                    </div>
                                    <div class="col-md-3 border-right">
                                        <h2 class="font-weight-bold text-info" id="val-method-agent">0</h2>
                                        <span class="badge badge-info p-2"><i class="fas fa-heartbeat mr-1"></i>Ping y Agente</span>
                                        <p class="text-muted small mt-2">Servidores físicos o virtuales con agente local Zabbix.</p>
                                    </div>
                                    <div class="col-md-3 border-right">
                                        <h2 class="font-weight-bold text-warning" id="val-method-ping">0</h2>
                                        <span class="badge badge-warning p-2"><i class="fas fa-exchange-alt mr-1"></i>Solo Ping (ICMP)</span>
                                        <p class="text-muted small mt-2">Equipos verificados exclusivamente mediante ping.</p>
                                    </div>
                                    <div class="col-md-3">
                                        <h2 class="font-weight-bold text-danger" id="val-method-none">0</h2>
                                        <span class="badge badge-danger p-2"><i class="fas fa-times mr-1"></i>Sin Monitoreo</span>
                                        <p class="text-muted small mt-2">Equipos deshabilitados o inactivos en Zabbix.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Group, Type and State breakdown table -->
                <div class="card filter-card mb-4">
                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-table mr-2 text-primary"></i>Desglose por Grupo de Host, Tipo y Estado</h5>
                        <button class="btn btn-xs btn-outline-primary" type="button" data-toggle="collapse" data-target="#collapseGroupBreakdown">
                            <i class="fas fa-compress-arrows-alt"></i> Minimizar / Expandir
                        </button>
                    </div>
                    <div class="collapse show" id="collapseGroupBreakdown">
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-hover table-striped mb-0" style="font-size:0.85rem">
                                    <thead class="thead-dark sticky-top">
                                        <tr>
                                            <th>Grupo de Host Zabbix</th>
                                            <th>Tipo de Equipo</th>
                                            <th>Estado</th>
                                            <th class="text-center">Cantidad</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbl-group-breakdown-body">
                                        <!-- JS populated -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Complete Host Details list -->
                <div class="card filter-card mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-list-ol mr-2 text-primary"></i>Listado Detallado de Equipos Evaluados</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="tbl-hosts-general-details" class="table table-bordered table-striped table-hover m-0" style="font-size:0.85rem; width:100%">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Visibilidad</th>
                                        <th>Direcciones IP</th>
                                        <th>Clasificación</th>
                                        <th>Método de Monitoreo</th>
                                        <th>Estado Zabbix</th>
                                        <th>Grupos Zabbix</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- JS Datatable -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: MONITOREO DETALLADO POR EQUIPO -->
        <div class="tab-pane fade" id="pane-detalles" role="tabpanel">
            <div class="card filter-card mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-search mr-2 text-primary"></i>Consultar Métricas y Alarmas de un Equipo</h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <label class="small font-weight-bold text-uppercase">Seleccionar Host</label>
                            <select id="sel-detail-host" class="form-control select2bs4 w-100">
                                <option value="">Cargando equipos...</option>
                            </select>
                        </div>
                        <div class="col-md-4 pt-4">
                            <button id="btn-fetch-details" class="btn btn-primary btn-block font-weight-bold py-2 shadow-sm" disabled>
                                <i class="fas fa-sync-alt mr-2"></i> OBTENER DETALLE
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loader -->
            <div id="detail-loading" class="text-center py-5 text-primary" style="display:none;">
                <i class="fas fa-spinner fa-spin fa-3x mb-3"></i>
                <h5>Consultando métricas y alarmas en tiempo real...</h5>
            </div>

            <!-- Detail view -->
            <div id="detail-content" style="display:none;">
                <div class="row">
                    <!-- Column Left: Monitored items -->
                    <div class="col-lg-6 mb-4">
                        <div class="card filter-card h-100 mb-0">
                            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-tachometer-alt mr-2 text-info"></i>Métricas Monitoreadas (Items)</h5>
                                <span class="badge badge-info h6 font-weight-bold p-2" id="lbl-item-count">0 Items</span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="tbl-host-items" class="table table-sm table-hover table-striped" style="font-size: 0.85rem; width: 100%">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th>Nombre del Item</th>
                                                <th>Clave / Key</th>
                                                <th>Último Valor</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Datatable dynamic -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Column Right: Triggers / Alarms -->
                    <div class="col-lg-6 mb-4">
                        <div class="card filter-card h-100 mb-0">
                            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-bell mr-2 text-danger"></i>Alarmas Asociadas (Triggers)</h5>
                                <span class="badge badge-danger h6 font-weight-bold p-2" id="lbl-trigger-count">0 Alertas</span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="tbl-host-triggers" class="table table-sm table-hover table-striped" style="font-size: 0.85rem; width: 100%">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th>Nombre Alarma / Trigger</th>
                                                <th class="text-center">Severidad</th>
                                                <th>Expresión Zabbix</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Datatable dynamic -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: ALERTAS DE PLANTILLA PRINCIPAL -->
        <div class="tab-pane fade" id="pane-plantillas" role="tabpanel">
            <div class="row mb-4">
                <div class="col-md-9">
                    <div class="card filter-card mb-0 h-100">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-file-invoice-dollar mr-2 text-primary"></i>Plantillas Zabbix & Alertas Asociadas</h5>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <label class="small font-weight-bold text-uppercase">Seleccionar Plantilla Zabbix</label>
                                    <select id="sel-template" class="form-control select2bs4 w-100">
                                        <option value="">Cargando plantillas...</option>
                                    </select>
                                </div>
                                <div class="col-md-4 pt-4">
                                    <button id="btn-fetch-template" class="btn btn-dark btn-block font-weight-bold py-2 shadow-sm" disabled>
                                        <i class="fas fa-search mr-2"></i> CONSULTAR DISPARADORES
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-gradient-navy text-white h-100 mb-0 shadow-sm border-0 d-flex flex-column justify-content-center p-3" style="background-color: var(--sonda-navy); border-radius: 12px;">
                        <h6 class="text-uppercase text-light small font-weight-bold mb-2">Plantilla Más Utilizada</h6>
                        <h5 class="font-weight-bold text-warning mb-1" id="lbl-most-used-template-name">...</h5>
                        <p class="mb-0 small"><i class="fas fa-link mr-1"></i> Asignada a <span class="font-weight-bold text-white h5" id="lbl-most-used-template-count">0</span> equipos</p>
                    </div>
                </div>
            </div>

            <!-- Loader -->
            <div id="template-loading" class="text-center py-5 text-primary" style="display:none;">
                <i class="fas fa-spinner fa-spin fa-3x mb-3"></i>
                <h5>Consultando disparadores del template...</h5>
            </div>

            <!-- Triggers list -->
            <div id="template-content" style="display:none;">
                <!-- Card 1: Triggers / Alarms -->
                <div class="card filter-card mb-4">
                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-exclamation-triangle mr-2 text-danger"></i>Disparadores (Alertas) que se desplegarán al usar esta plantilla</h5>
                        <span class="badge badge-primary h6 font-weight-bold p-2" id="lbl-template-trigger-count">0 Disparadores</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="tbl-template-triggers" class="table table-bordered table-striped table-hover m-0" style="font-size: 0.85rem; width: 100%">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Disparador / Alerta</th>
                                        <th class="text-center" style="width: 15%">Severidad</th>
                                        <th>Fórmula / Expresión Lógica de Alarma</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Datatable dynamic -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Associated Hosts -->
                <div class="card filter-card mb-4">
                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-desktop mr-2 text-info"></i>Equipos vinculados a esta Plantilla</h5>
                        <span class="badge badge-info h6 font-weight-bold p-2" id="lbl-template-hosts-count">0 Equipos</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="tbl-template-hosts" class="table table-bordered table-striped table-hover m-0" style="font-size: 0.85rem; width: 100%">
                                <thead class="thead-dark">
                                    <tr>
                                        <th style="width: 15%">ID Zabbix</th>
                                        <th>Nombre Visible</th>
                                        <th>Nombre Técnico (Host)</th>
                                        <th>Dirección IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Datatable dynamic -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    // Inicializaciones
    $('.select2bs4').select2({ theme: 'bootstrap4' });
    
    let hostsTable = null;
    let itemsTable = null;
    let triggersTable = null;
    let templateTriggersTable = null;
    let templateHostsTable = null;
    
    let typeChartInstance = null;
    let methodChartInstance = null;

    // Severity translation & badge helper
    const getSeverityBadge = (priority) => {
        const p = parseInt(priority);
        const severities = {
            0: { name: 'No clasificado', class: 'badge-severity-0' },
            1: { name: 'Información',   class: 'badge-severity-1' },
            2: { name: 'Advertencia',   class: 'badge-severity-2' },
            3: { name: 'Promedio',      class: 'badge-severity-3' },
            4: { name: 'Alta',          class: 'badge-severity-4' },
            5: { name: 'Desastre',      class: 'badge-severity-5' }
        };
        const sev = severities[p] || { name: 'Desconocida', class: 'badge-secondary' };
        return `<span class="badge ${sev.class} px-2 py-1 font-weight-bold text-uppercase" style="font-size: 0.75rem">${sev.name}</span>`;
    };

    // 1. CARGA DE TAB 1 (ALCANCE GENERAL)
    function loadGeneralAlcance() {
        $('#general-loading').show();
        $('#general-content').hide();
        
        $.getJSON('process_alcance.php', { action: 'get_general_alcance' }, function(resp) {
            $('#general-loading').hide();
            if (!resp.success) {
                Swal.fire('Error', resp.error || 'No se pudo cargar el alcance', 'error');
                return;
            }

            // Totales
            $('#lbl-total-hosts').text(resp.summary.total);
            $('#lbl-monitored-hosts').text(resp.summary.monitored);
            $('#lbl-unmonitored-hosts').text(resp.summary.unmonitored);

            // Métodos
            $('#val-method-snmp').text(resp.by_method.ping_snmp);
            $('#val-method-agent').text(resp.by_method.ping_agent);
            $('#val-method-ping').text(resp.by_method.ping);
            $('#val-method-none').text(resp.by_method.none);

            // Tabla Resumen
            let summaryHtml = '';
            const types = Object.keys(resp.by_type);
            const typeLabels = [];
            const monitoredData = [];
            const unmonitoredData = [];
            
            types.forEach(t => {
                const data = resp.by_type[t];
                if (data.total > 0) {
                    typeLabels.push(t);
                    monitoredData.push(data.monitored);
                    unmonitoredData.push(data.unmonitored);
                    
                    const percent = data.total > 0 ? ((data.monitored / data.total) * 100).toFixed(1) : 0;
                    summaryHtml += `
                        <tr>
                            <td class="font-weight-bold">${t}</td>
                            <td class="text-center text-success font-weight-bold">${data.monitored}</td>
                            <td class="text-center text-danger">${data.unmonitored}</td>
                            <td class="text-center font-weight-bold">${data.total}</td>
                            <td class="text-center">
                                <div class="progress progress-xs">
                                    <div class="progress-bar bg-success" style="width: ${percent}%"></div>
                                </div>
                                <span class="small font-weight-bold">${percent}%</span>
                            </td>
                        </tr>
                    `;
                }
            });
            $('#tbl-type-summary-body').html(summaryHtml);

            // Gráfico Tipo y Estado
            const ctxType = document.getElementById('typeStateChart').getContext('2d');
            if (typeChartInstance) typeChartInstance.destroy();
            typeChartInstance = new Chart(ctxType, {
                type: 'bar',
                data: {
                    labels: typeLabels,
                    datasets: [
                        {
                            label: 'Monitoreados (Activos)',
                            data: monitoredData,
                            backgroundColor: '#28a745',
                            borderRadius: 4
                        },
                        {
                            label: 'No Monitoreados (Inactivos)',
                            data: unmonitoredData,
                            backgroundColor: '#dc3545',
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { stacked: true },
                        y: { stacked: true, beginAtZero: true }
                    },
                    plugins: {
                        legend: { position: 'top' }
                    }
                }
            });

            // Gráfico de Métodos
            const ctxMethod = document.getElementById('methodChart').getContext('2d');
            if (methodChartInstance) methodChartInstance.destroy();
            methodChartInstance = new Chart(ctxMethod, {
                type: 'doughnut',
                data: {
                    labels: ['Ping y SNMP', 'Ping y Agente', 'Solo Ping (ICMP)', 'Sin Monitoreo'],
                    datasets: [{
                        data: [
                            resp.by_method.ping_snmp,
                            resp.by_method.ping_agent,
                            resp.by_method.ping,
                            resp.by_method.none
                        ],
                        backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#dc3545'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } }
                    }
                }
            });

            // Tabla Desglose por Grupo, Tipo y Estado
            let breakdownHtml = '';
            resp.by_group_type_state.forEach(row => {
                const badge = row.state === 'Monitoreado' ? 'badge-success' : 'badge-danger';
                breakdownHtml += `
                    <tr>
                        <td class="font-weight-bold text-dark">${row.group}</td>
                        <td><span class="badge badge-light border font-weight-normal">${row.type}</span></td>
                        <td><span class="badge ${badge}">${row.state}</span></td>
                        <td class="text-center font-weight-bold text-primary">${row.count}</td>
                    </tr>
                `;
            });
            $('#tbl-group-breakdown-body').html(breakdownHtml || '<tr><td colspan="4" class="text-center text-muted">No hay datos de desglose disponibles</td></tr>');

            // Datatable de hosts
            if (hostsTable) hostsTable.destroy();
            const detailsBody = $('#tbl-hosts-general-details tbody').empty();
            resp.hosts_detail.forEach(h => {
                const statusDot = h.status === 'Monitoreado' ? '<span class="status-dot status-dot-active"></span>' : '<span class="status-dot status-dot-inactive"></span>';
                const statusBadge = h.status === 'Monitoreado' ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-danger">Inactivo</span>';
                
                detailsBody.append(`
                    <tr>
                        <td>
                            <div class="font-weight-bold text-dark">${h.name}</div>
                        </td>
                        <td><code>${h.ip}</code></td>
                        <td><span class="badge badge-light border font-weight-normal">${h.type}</span></td>
                        <td>${h.method}</td>
                        <td>${statusDot} ${statusBadge}</td>
                        <td style="white-space: normal; max-width: 200px;">${h.groups}</td>
                    </tr>
                `);
            });
            hostsTable = $('#tbl-hosts-general-details').DataTable({
                pageLength: 15,
                language: { search: "Buscar en tabla:", lengthMenu: "Mostrar _MENU_ registros", info: "Mostrando _START_ a _END_ de _TOTAL_ equipos" }
            });

            $('#general-content').fadeIn();
        });
    }

    // 2. CARGA DE TAB 2 (MONITOREO DETALLADO POR EQUIPO)
    function loadHostsDropdown() {
        $.getJSON('process_alcance.php', { action: 'get_hosts_list' }, function(resp) {
            if (resp.success) {
                const select = $('#sel-detail-host').empty();
                select.append('<option value="">Seleccione un equipo para ver detalle...</option>');
                resp.data.forEach(h => {
                    select.append(new Option(`${h.name} (${h.host})`, h.hostid));
                });
                $('#btn-fetch-details').prop('disabled', false);
            }
        });
    }

    $('#btn-fetch-details').click(function() {
        const hostid = $('#sel-detail-host').val();
        if (!hostid) return;
        
        $('#detail-loading').show();
        $('#detail-content').hide();
        
        $.getJSON('process_alcance.php', { action: 'get_host_items_triggers', hostid: hostid }, function(resp) {
            $('#detail-loading').hide();
            if (!resp.success) {
                Swal.fire('Error', resp.error || 'No se pudo cargar el detalle', 'error');
                return;
            }

            // Render Items Table
            if (itemsTable) itemsTable.destroy();
            const itemsBody = $('#tbl-host-items tbody').empty();
            resp.items.forEach(item => {
                const val = item.lastvalue !== undefined ? `${item.lastvalue} ${item.units || ''}` : 'N/A';
                itemsBody.append(`
                    <tr>
                        <td class="font-weight-bold text-dark">${item.name}</td>
                        <td><code>${item.key_}</code></td>
                        <td class="text-primary font-weight-bold">${val}</td>
                    </tr>
                `);
            });
            $('#lbl-item-count').text(`${resp.items.length} Items`);
            itemsTable = $('#tbl-host-items').DataTable({
                pageLength: 10,
                language: { search: "Filtrar items:" }
            });

            // Render Triggers Table
            if (triggersTable) triggersTable.destroy();
            const triggersBody = $('#tbl-host-triggers tbody').empty();
            resp.triggers.forEach(trig => {
                triggersBody.append(`
                    <tr>
                        <td class="font-weight-bold text-dark">${trig.description}</td>
                        <td class="text-center">${getSeverityBadge(trig.priority)}</td>
                        <td><code style="word-break: break-all;">${trig.expression}</code></td>
                    </tr>
                `);
            });
            $('#lbl-trigger-count').text(`${resp.triggers.length} Alertas`);
            triggersTable = $('#tbl-host-triggers').DataTable({
                pageLength: 10,
                language: { search: "Filtrar alertas:" }
            });

            $('#detail-content').fadeIn();
        });
    });

    // 3. CARGA DE TAB 3 (ALERTAS DE PLANTILLAS)
    function loadTemplateData(templateid = '') {
        $('#template-loading').show();
        $('#template-content').hide();
        
        const params = { action: 'get_template_alerts' };
        if (templateid) params.templateid = templateid;
        
        $.getJSON('process_alcance.php', params, function(resp) {
            $('#template-loading').hide();
            if (!resp.success) {
                Swal.fire('Error', resp.error || 'No se pudo cargar la plantilla', 'error');
                return;
            }

            // Si es la carga inicial, rellenar el dropdown
            if (!templateid) {
                const select = $('#sel-template').empty();
                resp.templates_list.forEach((t, idx) => {
                    const opt = new Option(`${t.name} (${t.count} equipos)`, t.templateid);
                    if (t.templateid == resp.templateid) {
                        opt.selected = true;
                        // Set stats for the most used one (first in array since sorted descending)
                        if (idx === 0) {
                            $('#lbl-most-used-template-name').text(t.name);
                            $('#lbl-most-used-template-count').text(t.count);
                        }
                    }
                    select.append(opt);
                });
                $('#btn-fetch-template').prop('disabled', false);
            }

            // Render triggers list
            if (templateTriggersTable) templateTriggersTable.destroy();
            const templateBody = $('#tbl-template-triggers tbody').empty();
            resp.triggers.forEach(trig => {
                templateBody.append(`
                    <tr>
                        <td class="font-weight-bold text-dark">${trig.description}</td>
                        <td class="text-center">${getSeverityBadge(trig.priority)}</td>
                        <td><code style="word-break: break-all;">${trig.expression}</code></td>
                    </tr>
                `);
            });
            $('#lbl-template-trigger-count').text(`${resp.triggers.length} Disparadores`);
            templateTriggersTable = $('#tbl-template-triggers').DataTable({
                pageLength: 15,
                language: { search: "Filtrar disparadores:" }
            });

            // Render hosts list
            if (templateHostsTable) templateHostsTable.destroy();
            const templateHostsBody = $('#tbl-template-hosts tbody').empty();
            if (resp.hosts) {
                resp.hosts.forEach(h => {
                    templateHostsBody.append(`
                        <tr>
                            <td><code>${h.hostid}</code></td>
                            <td class="font-weight-bold text-dark">${h.name}</td>
                            <td><code>${h.host}</code></td>
                            <td><code>${h.ip}</code></td>
                        </tr>
                    `);
                });
                $('#lbl-template-hosts-count').text(`${resp.hosts.length} Equipos`);
            } else {
                $('#lbl-template-hosts-count').text(`0 Equipos`);
            }
            templateHostsTable = $('#tbl-template-hosts').DataTable({
                pageLength: 10,
                language: { search: "Filtrar equipos:" }
            });

            $('#template-content').fadeIn();
        });
    }

    $('#btn-fetch-template').click(function() {
        const tid = $('#sel-template').val();
        if (tid) {
            loadTemplateData(tid);
        }
    });

    // Cargar Tab 1 al abrir la página
    loadGeneralAlcance();

    // Cargar datos de Tab 2 y Tab 3 de manera diferida al hacer clic en las pestañas correspondientes
    let loadedTab2 = false;
    let loadedTab3 = false;

    $('#tab-detalles-link').on('shown.bs.tab', function() {
        if (!loadedTab2) {
            loadHostsDropdown();
            loadedTab2 = true;
        }
    });

    $('#tab-plantillas-link').on('shown.bs.tab', function() {
        if (!loadedTab3) {
            loadTemplateData();
            loadedTab3 = true;
        }
    });
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
