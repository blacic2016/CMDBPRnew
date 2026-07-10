<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/permissions_helper.php';

require_login();
if (!has_module_access('portmapping')) {
    header("Location: dashboard.php");
    exit();
}

$page_title = 'Portmapping (Gestión y Conectividad)';
include __DIR__ . '/partials/header.php';
?>

<!-- Custom Premium CSS -->
<style>
    .premium-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        background: #fff;
        overflow: hidden;
        margin-bottom: 25px;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .premium-card:hover {
        box-shadow: 0 6px 25px rgba(0,0,0,0.09);
    }
    .gradient-header {
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        color: white;
        padding: 20px;
    }
    .gradient-header h3, .gradient-header h4 {
        margin: 0;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    .nav-tabs-premium {
        border-bottom: 2px solid #ebedf2;
        gap: 8px;
        padding: 0 15px;
    }
    .nav-tabs-premium .nav-link {
        border: none;
        color: #74788d;
        font-weight: 500;
        padding: 12px 20px;
        border-radius: 8px 8px 0 0;
        transition: all 0.3s;
        margin-bottom: -2px;
        position: relative;
    }
    .nav-tabs-premium .nav-link:hover {
        color: #1e3c72;
        background: rgba(30, 60, 114, 0.05);
    }
    .nav-tabs-premium .nav-link.active {
        color: #1e3c72;
        background: transparent;
        font-weight: 600;
    }
    .nav-tabs-premium .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: #1e3c72;
        border-radius: 3px 3px 0 0;
    }
    .table-premium thead th {
        background-color: #f8f9fa;
        text-transform: uppercase;
        font-size: 0.72rem;
        letter-spacing: 0.8px;
        border-bottom: 2px solid #ebedf2;
        color: #495057;
        font-weight: 700;
        padding: 12px 15px;
    }
    .table-premium tbody td {
        padding: 12px 15px;
        vertical-align: middle;
    }
    .status-dot {
        height: 10px;
        width: 10px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 6px;
    }
    .status-dot-up { background-color: #28a745; box-shadow: 0 0 8px rgba(40, 167, 69, 0.6); }
    .status-dot-down { background-color: #dc3545; box-shadow: 0 0 8px rgba(220, 53, 69, 0.6); }
    .status-dot-active { background-color: #007bff; box-shadow: 0 0 8px rgba(0, 123, 255, 0.6); }
    .status-dot-unknown { background-color: #6c757d; }

    .color-pill {
        display: inline-block;
        width: 25px;
        height: 10px;
        border-radius: 10px;
        border: 1px solid rgba(0,0,0,0.15);
        vertical-align: middle;
    }
    .device-info-badge {
        background: rgba(255, 255, 255, 0.15);
        color: white;
        border-radius: 30px;
        padding: 4px 15px;
        font-size: 0.85rem;
        font-weight: 500;
        display: inline-block;
    }
    .wizard-step { display: none; }
    .wizard-step.active { display: block; animation: fadeIn 0.4s; }
    .step-indicator {
        display: flex; justify-content: space-between; margin-bottom: 25px; text-align: center;
    }
    .step-item {
        flex: 1; padding: 12px; border-bottom: 4px solid #dee2e6; color: #6c757d; font-weight: 600;
        transition: all 0.3s;
    }
    .step-item.active { border-bottom-color: #1e3c72; color: #1e3c72; }
    .step-item.completed { border-bottom-color: #28a745; color: #28a745; }
    .arch-card { cursor: pointer; transition: transform 0.2s, border-color 0.2s; }
    .arch-card:hover { transform: translateY(-3px); }
    .arch-card.selected { border: 2px solid #1e3c72; background-color: #f4f7fc; }
    
    .path-trace-breadcrumb {
        font-family: 'Consolas', 'Courier New', monospace;
        font-size: 0.8rem;
        background: #f1f3f5;
        padding: 4px 10px;
        border-radius: 6px;
        color: #495057;
        display: inline-flex;
        align-items: center;
        margin-top: 4px;
        max-width: 100%;
        overflow-x: auto;
    }
    .path-trace-arrow {
        color: #1e3c72;
        margin: 0 6px;
        font-weight: bold;
    }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>

<!-- KPIs Gerenciales -->
<div class="row">
    <div class="col-lg-4 col-6">
        <div class="small-box bg-gradient-navy shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <div class="inner"><h3 id="kpi_total">0</h3><p>Mapeos Totales (CMDB)</p></div>
            <div class="icon"><i class="fas fa-network-wired"></i></div>
        </div>
    </div>
    <div class="col-lg-4 col-6">
        <div class="small-box bg-gradient-success shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <div class="inner"><h3 id="kpi_activos">0</h3><p>Puertos Monitoreados UP</p></div>
            <div class="icon"><i class="fas fa-link"></i></div>
        </div>
    </div>
    <div class="col-lg-4 col-6">
        <div class="small-box bg-gradient-warning shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <div class="inner"><h3 id="kpi_alertas">0</h3><p>Puertos Monitoreados DOWN</p></div>
            <div class="icon"><i class="fas fa-exclamation-triangle text-white"></i></div>
        </div>
    </div>
</div>

<!-- NAVEGACIÓN PRINCIPAL POR PESTAÑAS -->
<ul class="nav nav-tabs nav-tabs-premium mb-4" id="mainTab" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="device-tab" data-toggle="tab" href="#device-content" role="tab" aria-selected="true">
            <i class="fas fa-server mr-2"></i> Gestión por Equipo
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="wizard-tab" data-toggle="tab" href="#wizard-content" role="tab" aria-selected="false">
            <i class="fas fa-magic mr-2"></i> Asistente de Mapeo
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="all-tab" data-toggle="tab" href="#all-content" role="tab" aria-selected="false">
            <i class="fas fa-list mr-2"></i> Directorio Global
        </a>
    </li>
</ul>

<!-- CONTENIDO DE PESTAÑAS -->
<div class="tab-content" id="mainTabContent">

    <!-- PESTAÑA 1: GESTIÓN POR EQUIPO -->
    <div class="tab-pane fade show active" id="device-content" role="tabpanel">
        <div class="premium-card">
            <div class="gradient-header d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h4><i class="fas fa-network-wired mr-2"></i> Conexiones por Equipo</h4>
                    <p class="mb-0 small text-white-50">Seleccione un equipo para ver y editar sus puertos, conexiones y alimentación física.</p>
                </div>
                <div class="mt-2 mt-md-0" style="min-width: 320px;">
                    <select id="device_selector" class="form-control select2-bootstrap" style="width: 100%;">
                        <option value="">Buscar y seleccionar equipo...</option>
                    </select>
                </div>
            </div>
            
            <div class="card-body" id="device_manager_body" style="display: none;">
                <!-- Detalles del Equipo Seleccionado -->
                <div class="alert alert-light border d-flex flex-wrap justify-content-between align-items-center mb-4 shadow-sm" style="border-radius: 10px;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-cube fa-2x text-primary mr-3"></i>
                        <div>
                            <h5 class="mb-0 font-weight-bold" id="lbl_dev_name">-</h5>
                            <small class="text-muted">Categoría: <span class="badge badge-secondary" id="lbl_dev_cat">-</span></small>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
                        <span class="badge py-2 px-3 badge-dark" id="lbl_zabbix_status">
                            <i class="fas fa-chart-line mr-1"></i> Sin Monitoreo Zabbix
                        </span>
                        <button class="btn btn-sm btn-primary shadow-sm" onclick="showNewPortModal()">
                            <i class="fas fa-plus mr-1"></i> Nuevo Puerto desde Cero
                        </button>
                    </div>
                </div>

                <!-- Tabs de Conexiones (Red vs Energía) -->
                <ul class="nav nav-pills mb-3" id="connSubTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active font-weight-bold" id="net-conn-tab" data-toggle="pill" href="#net-conn-content" role="tab">
                            <i class="fas fa-ethernet mr-1"></i> Conexiones de Red
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold" id="power-conn-tab" data-toggle="pill" href="#power-conn-content" role="tab">
                            <i class="fas fa-bolt mr-1"></i> Conexiones de Energía (Power)
                        </a>
                    </li>
                </ul>

                <div class="tab-content" id="connSubTabContent">
                    <!-- Subtab 1: Red -->
                    <div class="tab-pane fade show active" id="net-conn-content" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover table-premium mb-0 border">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>Equipo Origen</th>
                                        <th>Puerto Origen</th>
                                        <th>Descripción del Puerto</th>
                                        <th>Estado del Puerto</th>
                                        <th>Tráfico</th>
                                        <th>Medio / Cable</th>
                                        <th>Color</th>
                                        <th>Observación</th>
                                        <th>Equipo Destino</th>
                                        <th>Puerto Destino</th>
                                        <th class="text-center" style="width: 120px;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbl_net_connections_body">
                                    <!-- Llenado dinámico -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Subtab 2: Energía -->
                    <div class="tab-pane fade" id="power-conn-content" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover table-premium mb-0 border">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>Equipo Origen</th>
                                        <th>Componente Energía / PSU</th>
                                        <th>Medio / Cable</th>
                                        <th>Color</th>
                                        <th>Observación</th>
                                        <th>Fuente Destino (PDU/UPS)</th>
                                        <th>Puerto / Toma Destino</th>
                                        <th class="text-center" style="width: 120px;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbl_power_connections_body">
                                    <!-- Llenado dinámico -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card-body text-center py-5" id="device_manager_empty">
                <i class="fas fa-search fa-3x text-muted mb-3 opacity-50"></i>
                <h5 class="text-muted">Por favor, seleccione un equipo en el buscador superior para gestionar su portmapping.</h5>
            </div>
        </div>
    </div>

    <!-- PESTAÑA 2: ASISTENTE DE MAPEO (Wizard original mejorado) -->
    <div class="tab-pane fade" id="wizard-content" role="tabpanel">
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card card-primary card-outline shadow-sm" style="border-radius: 12px;">
                    <div class="card-header bg-white"><h5 class="card-title font-weight-bold mb-0">Asistente Paso a Paso</h5></div>
                    <div class="card-body">
                        <!-- Indicadores de Paso -->
                        <div class="step-indicator">
                            <div class="step-item active" id="ind_step1">1. Ubicación</div>
                            <div class="step-item" id="ind_step2">2. Tipo</div>
                            <div class="step-item" id="ind_step3">3. Extremos</div>
                            <div class="step-item" id="ind_step4">4. Resumen</div>
                        </div>

                        <form id="wizard_form">
                            <!-- PASO 1: Ubicación -->
                            <div class="wizard-step active" id="step1">
                                <h5>Paso 1: Defina la Ubicación Física</h5>
                                <p class="text-muted text-sm">Debe llegar hasta el nivel de Cuarto de Telecomunicaciones para continuar.</p>
                                <div class="form-group">
                                    <label>País</label>
                                    <select id="sel_pais" class="form-control" onchange="loadHierarchy('País', this.value, 'sel_ciudad')">
                                        <option value="">Cargando...</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Ciudad</label>
                                    <select id="sel_ciudad" class="form-control" onchange="loadHierarchy('Ciudad', this.value, 'sel_localidad')" disabled></select>
                                </div>
                                <div class="form-group">
                                    <label>Localidad</label>
                                    <select id="sel_localidad" class="form-control" onchange="loadHierarchy('Localidad', this.value, 'sel_area')" disabled></select>
                                </div>
                                <div class="form-group">
                                    <label>Área</label>
                                    <select id="sel_area" class="form-control" onchange="loadHierarchy('Área', this.value, 'sel_cuarto')" disabled></select>
                                </div>
                                <div class="form-group">
                                    <label>Cuarto de Telecomunicaciones</label>
                                    <select id="sel_cuarto" class="form-control" onchange="checkStep1()" disabled></select>
                                </div>
                                <button type="button" class="btn btn-primary btn-block shadow-sm" id="btn_next_1" disabled onclick="nextStep(1, 2)">Siguiente <i class="fas fa-arrow-right"></i></button>
                            </div>

                            <!-- PASO 2: Tipo de Arquitectura -->
                            <div class="wizard-step" id="step2">
                                <h5>Paso 2: Seleccione la Arquitectura</h5>
                                <div class="row text-center mt-3">
                                    <div class="col-12 mb-3">
                                        <div class="card arch-card shadow-sm" onclick="selectArch('pasivo_activo', this)">
                                            <div class="card-body">
                                                <i class="fas fa-random fa-2x text-primary mb-2"></i><br><strong>Pasivo a Activo</strong><br>
                                                <small class="text-muted">(Ej. Patch Panel a Switch)</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="card arch-card shadow-sm" onclick="selectArch('activo_activo', this)">
                                            <div class="card-body">
                                                <i class="fas fa-server fa-2x text-success mb-2"></i><br><strong>Activo a Activo</strong><br>
                                                <small class="text-muted">(Ej. Enlace Troncal Switch a Switch)</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="card arch-card shadow-sm" onclick="selectArch('pasivo_pasivo', this)">
                                            <div class="card-body">
                                                <i class="fas fa-grip-horizontal fa-2x text-secondary mb-2"></i><br><strong>Pasivo a Pasivo</strong><br>
                                                <small class="text-muted">(Ej. Cross-connect entre Patch Panels)</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" id="mapping_type" required>
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-default" onclick="nextStep(2, 1)"><i class="fas fa-arrow-left"></i> Atrás</button>
                                    <button type="button" class="btn btn-primary shadow-sm" id="btn_next_2" disabled onclick="setupStep3(); nextStep(2, 3)">Siguiente <i class="fas fa-arrow-right"></i></button>
                                </div>
                            </div>

                            <!-- PASO 3: Selección de Extremos -->
                            <div class="wizard-step" id="step3">
                                <h5>Paso 3: Asignación de Puertos</h5>
                                <div class="alert alert-info py-2"><i class="fas fa-info-circle"></i> Solo se mostrarán puertos Libres registrados en la CMDB.</div>
                                
                                <div class="card bg-light shadow-none border">
                                    <div class="card-body py-3">
                                        <h6><strong>Extremo A (Origen)</strong></h6>
                                        <select id="source_device" class="form-control mb-2" onchange="loadPorts(this.value, 'source_port')" required>
                                            <option value="">Cargando equipos...</option>
                                        </select>
                                        <div class="input-group">
                                            <select id="source_port" class="form-control" required>
                                                <option value="">Seleccione Equipo Primero</option>
                                            </select>
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary" type="button" onclick="promptCreatePort('source_device', 'source_port')"><i class="fas fa-plus"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-center my-2"><i class="fas fa-arrow-down fa-lg text-muted"></i></div>

                                <div class="card bg-light shadow-none border">
                                    <div class="card-body py-3">
                                        <h6><strong>Extremo B (Destino)</strong></h6>
                                        <select id="target_device" class="form-control mb-2" onchange="loadPorts(this.value, 'target_port')" required>
                                            <option value="">Cargando equipos...</option>
                                        </select>
                                        <div class="input-group">
                                            <select id="target_port" class="form-control" required>
                                                <option value="">Seleccione Equipo Primero</option>
                                            </select>
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary" type="button" onclick="promptCreatePort('target_device', 'target_port')"><i class="fas fa-plus"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-default" onclick="nextStep(3, 2)"><i class="fas fa-arrow-left"></i> Atrás</button>
                                    <button type="button" class="btn btn-primary shadow-sm" onclick="prepareSummary(); nextStep(3, 4)">Siguiente <i class="fas fa-arrow-right"></i></button>
                                </div>
                            </div>

                            <!-- PASO 4: Resumen y Medio Físico -->
                            <div class="wizard-step" id="step4">
                                <h5>Paso 4: Trazabilidad Física y Confirmación</h5>
                                
                                <div class="form-row mt-3">
                                    <div class="form-group col-md-6">
                                        <label>Tipo de Medio</label>
                                        <select id="cable_type" class="form-control">
                                            <option>UTP Cat6A</option>
                                            <option>UTP Cat6</option>
                                            <option>Fibra OM4</option>
                                            <option>Fibra OS2</option>
                                            <option>DAC / Twinax</option>
                                            <option>Cable de Poder Estándar</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Color Físico</label>
                                        <input type="color" id="color_code" class="form-control" value="#007bff">
                                    </div>
                                </div>

                                <div class="alert alert-warning mt-3">
                                    <strong>Resumen de Conexión:</strong><br>
                                    Conectando <span id="sum_src" class="text-primary font-weight-bold"></span> 
                                    con <span id="sum_tgt" class="text-primary font-weight-bold"></span>.
                                </div>

                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-default" onclick="nextStep(4, 3)"><i class="fas fa-arrow-left"></i> Atrás</button>
                                    <button type="submit" class="btn btn-success shadow-sm"><i class="fas fa-check"></i> Confirmar y Guardar</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PESTAÑA 3: TODOS LOS MAPEOS -->
    <div class="tab-pane fade" id="all-content" role="tabpanel">
        <div class="premium-card">
            <div class="card-header bg-dark text-white d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="card-title font-weight-bold mb-0">Directorio de Mapeos (Auditoría Global)</h5>
            </div>
            <!-- Barra de búsqueda premium -->
            <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="input-group" style="max-width: 400px; min-width: 250px;">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span>
                    </div>
                    <input type="text" id="global_mappings_search" class="form-control border-left-0" placeholder="Filtrar por Equipo, Puerto, Cable, Obs...">
                </div>
                <div>
                    <select id="global_mappings_filter_type" class="form-control" style="min-width: 150px;">
                        <option value="">Todos los Tipos</option>
                        <option value="network">Solo Red</option>
                        <option value="power">Solo Energía</option>
                    </select>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-premium mb-0">
                        <thead>
                            <tr>
                                <th>Equipo Origen</th>
                                <th>Puerto Origen</th>
                                <th class="text-center">Tipo Conexión</th>
                                <th>Medio / Cable</th>
                                <th class="text-center">Color</th>
                                <th>Observación</th>
                                <th>Equipo Destino</th>
                                <th>Puerto Destino</th>
                                <th class="text-center">Estado (Zabbix)</th>
                            </tr>
                        </thead>
                        <tbody id="mappings_table_body">
                            <!-- Llenado dinámico -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- MODAL: CONECTAR / EDITAR PUERTO -->
<div class="modal fade" id="modalConnection" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-gradient-navy border-0">
                <h5 class="modal-title font-weight-bold text-white"><i class="fas fa-link mr-2"></i> Conectar Puerto</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="form_save_connection">
                    <input type="hidden" id="modal_device_id">
                    <input type="hidden" id="modal_connection_type">
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="small font-weight-bold">Equipo Origen</label>
                            <input type="text" id="modal_source_device_name" class="form-control" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="small font-weight-bold">Puerto Origen</label>
                            <input type="text" id="modal_port_name" class="form-control" readonly>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="small font-weight-bold mb-0">Equipo Destino</label>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="chk_manual_device" onchange="toggleManualDevice(this.checked)">
                                <label class="custom-control-label small" for="chk_manual_device">Escribir equipo manualmente</label>
                            </div>
                        </div>
                        <div id="wrapper_select_device">
                            <select id="modal_dest_device" class="form-control" style="width:100%;">
                                <option value="">Buscar equipo destino...</option>
                            </select>
                        </div>
                        <div id="wrapper_text_device" style="display: none;">
                            <input type="text" id="modal_dest_device_text" class="form-control" placeholder="Ej. SW-PISO2-MANUAL o SRV-BD-01">
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="d-flex justify-content-between align-items-center">
                            <label class="small font-weight-bold mb-0">Puerto / Toma Destino</label>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="chk_manual_port" onchange="toggleManualPort(this.checked)">
                                <label class="custom-control-label small" for="chk_manual_port">Escribir manualmente</label>
                            </div>
                        </div>
                        
                        <div id="wrapper_select_port" class="mt-2">
                            <select id="modal_dest_port" class="form-control" style="width:100%;">
                                <option value="">Seleccione Equipo Destino Primero</option>
                            </select>
                        </div>
                        <div id="wrapper_text_port" class="mt-2" style="display: none;">
                            <input type="text" id="modal_dest_port_text" class="form-control" placeholder="Ej. Gi1/0/2 o PSU-1-In">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="small font-weight-bold">Medio / Tipo Cable</label>
                            <select id="modal_cable_type" class="form-control">
                                <option>UTP Cat6A</option>
                                <option>UTP Cat6</option>
                                <option>Fibra OM4</option>
                                <option>Fibra OS2</option>
                                <option>DAC / Twinax</option>
                                <option>Cable de Poder Estándar</option>
                                <option>Cable de Poder NEMA 5-15P</option>
                                <option>Cable de Poder C13-C14</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="small font-weight-bold">Color del Cable</label>
                            <input type="color" id="modal_color_code" class="form-control" value="#0000FF">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="small font-weight-bold">Observación / Notas</label>
                        <textarea id="modal_notes" class="form-control" rows="2" placeholder="Notas sobre el cableado, parcheo o distribución..."></textarea>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="button" class="btn btn-light mr-2" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4 shadow-sm">Guardar Conexión</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: CREAR PUERTO DESDE CERO -->
<div class="modal fade" id="modalNewPort" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-gradient-navy border-0">
                <h5 class="modal-title font-weight-bold text-white"><i class="fas fa-plus mr-2"></i> Crear Puerto</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="form_create_port">
                    <div class="form-group">
                        <label class="small font-weight-bold">Nombre del Puerto / PSU</label>
                        <input type="text" id="new_port_name" class="form-control" placeholder="Ej. Gi/1, PSU 1, Console" required>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Tipo de Conexión</label>
                        <select id="new_port_type" class="form-control">
                            <option value="network">Conexión de Red</option>
                            <option value="power">Conexión de Energía (Power)</option>
                        </select>
                    </div>
                    <div class="d-flex justify-content-end mt-4">
                        <button type="button" class="btn btn-light mr-2" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success px-3 shadow-sm">Crear</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// VARIABLES GLOBALES
let cmdbDevices = [];
let selectedDevice = null;
let networkPorts = [];
let powerPorts = [];
let globalMappings = [];

document.addEventListener('DOMContentLoaded', () => {
    // Inicializar selectores y cargar datos iniciales
    initDeviceSelectors();
    loadMappings();
    loadHierarchy('País', null, 'sel_pais');

    // Eventos para el buscador global de mapeos
    const searchInput = document.getElementById('global_mappings_search');
    if (searchInput) {
        searchInput.addEventListener('input', filterGlobalMappings);
    }
    const filterType = document.getElementById('global_mappings_filter_type');
    if (filterType) {
        filterType.addEventListener('change', filterGlobalMappings);
    }

    // Inicializar Select2 en los modals
    $('#modal_dest_device').select2({
        theme: 'bootstrap4',
        placeholder: 'Buscar equipo destino...',
        dropdownParent: $('#modalConnection')
    }).on('change', function() {
        const destId = $(this).val();
        if (destId) {
            const dev = cmdbDevices.find(d => d.id == destId);
            if (dev && !dev.zabbix_host_id) {
                toastr.warning("Este equipo no cuenta con monitoreo Zabbix. Ingrese el puerto manualmente.");
                document.getElementById('chk_manual_port').checked = true;
                toggleManualPort(true);
            } else {
                document.getElementById('chk_manual_port').checked = false;
                toggleManualPort(false);
                loadDestDevicePorts(destId);
            }
        }
    });

    // Formulario Wizard
    document.getElementById('wizard_form').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const srcPort = document.getElementById('source_port').value;
        const tgtPort = document.getElementById('target_port').value;
        if(!srcPort || !tgtPort) {
            toastr.error("Debe seleccionar puertos en ambos extremos.");
            return;
        }

        const data = new FormData();
        data.append('action', 'save_portmapping');
        data.append('source_port_id', srcPort);
        data.append('target_port_id', tgtPort);
        data.append('cable_type', document.getElementById('cable_type').value);
        data.append('color_code', document.getElementById('color_code').value);

        try {
            const resp = await fetch('api_portmapping.php', { method: 'POST', body: data });
            const res = await resp.json();
            if (res.success) {
                Swal.fire('Guardado', 'Mapeo guardado con éxito. CMDB Actualizada.', 'success');
                this.reset();
                nextStep(4, 1);
                loadMappings();
            } else {
                toastr.error(res.error || 'Error al guardar');
            }
        } catch (error) {
            toastr.error('Error de comunicación con el servidor');
        }
    });

    // Formulario Conectar Puerto
    document.getElementById('form_save_connection').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const deviceId = document.getElementById('modal_device_id').value;
        const portName = document.getElementById('modal_port_name').value;
        const connectionType = document.getElementById('modal_connection_type').value;
        
        const isManualDevice = document.getElementById('chk_manual_device').checked;
        const destDeviceId = isManualDevice
            ? document.getElementById('modal_dest_device_text').value.trim()
            : document.getElementById('modal_dest_device').value;
        
        const isManual = document.getElementById('chk_manual_port').checked;
        const destPortName = isManual 
            ? document.getElementById('modal_dest_port_text').value.trim() 
            : document.getElementById('modal_dest_port').value;

        if (!destDeviceId || !destPortName) {
            Swal.fire('Atención', 'Debe ingresar el equipo y puerto de destino.', 'warning');
            return;
        }

        const data = new FormData();
        data.append('action', 'save_device_connection');
        data.append('device_id', deviceId);
        data.append('port_name', portName);
        data.append('connection_type', connectionType);
        data.append('dest_device_id', destDeviceId);
        data.append('dest_port_name', destPortName);
        data.append('cable_type', document.getElementById('modal_cable_type').value);
        data.append('color_code', document.getElementById('modal_color_code').value);
        data.append('notes', document.getElementById('modal_notes').value);

        try {
            const resp = await fetch('api_portmapping.php', { method: 'POST', body: data });
            const res = await resp.json();
            if (res.success) {
                Swal.fire('Guardado', 'La conexión ha sido registrada exitosamente.', 'success');
                $('#modalConnection').modal('hide');
                loadDevicePortsAndConnections(deviceId);
                loadMappings(); // Recargar pestaña global
            } else {
                Swal.fire('Error', res.error || 'No se pudo guardar la conexión', 'error');
            }
        } catch (error) {
            toastr.error('Error al comunicarse con el servidor');
        }
    });

    // Formulario Crear Puerto
    document.getElementById('form_create_port').addEventListener('submit', async function(e) {
        e.preventDefault();
        if (!selectedDevice) return;

        const portName = document.getElementById('new_port_name').value;
        const portType = document.getElementById('new_port_type').value;

        const data = new FormData();
        data.append('action', 'create_manual_port');
        data.append('device_id', selectedDevice);
        data.append('port_name', portName);
        data.append('connection_type', portType);

        try {
            const resp = await fetch('api_portmapping.php', { method: 'POST', body: data });
            const res = await resp.json();
            if (res.success) {
                toastr.success('Puerto creado correctamente');
                $('#modalNewPort').modal('hide');
                document.getElementById('form_create_port').reset();
                loadDevicePortsAndConnections(selectedDevice);
            } else {
                Swal.fire('Error', res.error || 'No se pudo crear el puerto', 'error');
            }
        } catch (error) {
            toastr.error('Error al comunicarse con el servidor');
        }
    });
});

// INICIALIZACIÓN SELECTORES DE EQUIPOS (Select2)
async function initDeviceSelectors() {
    try {
        const resp = await fetch('api_portmapping.php?action=get_all_devices');
        const res = await resp.json();
        if (res.success) {
            cmdbDevices = res.data;
            
            // Llenar selector principal
            const selector = $('#device_selector');
            selector.empty().append('<option value="">Buscar y seleccionar equipo...</option>');
            
            // Llenar selector en modal
            const modalSelector = $('#modal_dest_device');
            modalSelector.empty().append('<option value="">Buscar equipo destino...</option>');

            cmdbDevices.forEach(d => {
                const optText = `${d.name} (${d.category_name})`;
                selector.append(`<option value="${d.id}">${optText}</option>`);
                modalSelector.append(`<option value="${d.id}">${optText}</option>`);
            });

            // Inicializar Select2 principal
            selector.select2({
                theme: 'bootstrap4',
                placeholder: 'Buscar y seleccionar equipo...'
            }).on('change', function() {
                const val = $(this).val();
                selectedDevice = val;
                if (val) {
                    $('#device_manager_empty').hide();
                    $('#device_manager_body').show();
                    loadDevicePortsAndConnections(val);
                } else {
                    $('#device_manager_empty').show();
                    $('#device_manager_body').hide();
                }
            });

            // Auto-select device from query parameters if present
            const urlParams = new URLSearchParams(window.location.search);
            const devId = urlParams.get('device_id');
            if (devId) {
                selector.val(devId).trigger('change');
            }
        }
    } catch (err) {
        toastr.error('Error cargando catálogo de equipos de la CMDB');
    }
}

// CARGAR PUERTOS Y MAPEOS DE UN EQUIPO SELECCIONADO
async function loadDevicePortsAndConnections(deviceId) {
    const netBody = document.getElementById('tbl_net_connections_body');
    const powerBody = document.getElementById('tbl_power_connections_body');
    
    netBody.innerHTML = '<tr><td colspan="12" class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i> Cargando interfaces de red...</td></tr>';
    powerBody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i> Cargando componentes de energía...</td></tr>';

    try {
        const resp = await fetch(`api_portmapping.php?action=get_device_ports_and_connections&device_id=${deviceId}`);
        const res = await resp.json();
        
        if (res.success) {
            // Actualizar etiquetas cabecera
            document.getElementById('lbl_dev_name').innerText = res.device.hostname;
            document.getElementById('lbl_dev_cat').innerText = $('#device_selector option:selected').text().match(/\(([^)]+)\)/)?.[1] || 'Hardware';
            
            const zStatus = document.getElementById('lbl_zabbix_status');
            if (res.device.zabbix_host_id) {
                zStatus.className = 'badge py-2 px-3 badge-success';
                zStatus.innerHTML = `<i class="fas fa-chart-line mr-1"></i> Zabbix ID: ${res.device.zabbix_host_id}`;
            } else {
                zStatus.className = 'badge py-2 px-3 badge-secondary';
                zStatus.innerHTML = '<i class="fas fa-chart-line mr-1"></i> Sin Monitoreo Zabbix';
            }

            networkPorts = res.network_ports;
            powerPorts = res.power_ports;

            // Renderizar Red
            renderNetworkPorts(networkPorts);
            // Renderizar Energía
            renderPowerPorts(powerPorts);
        } else {
            toastr.error(res.error || 'Error al obtener puertos');
        }
    } catch (err) {
        toastr.error('Error de red al consultar puertos del equipo');
    }
}

// RENDERIZAR TABLA DE RED
function renderNetworkPorts(ports) {
    const body = document.getElementById('tbl_net_connections_body');
    body.innerHTML = '';

    if (ports.length === 0) {
        body.innerHTML = '<tr><td colspan="12" class="text-center text-muted py-4">No se han registrado interfaces de red en el equipo.</td></tr>';
        return;
    }

    const srcDevice = document.getElementById('lbl_dev_name').innerText;

    ports.forEach((p, idx) => {
        const destDevice = p.dest_device_name ? `<span class="font-weight-bold text-navy"><i class="fas fa-server mr-1"></i> ${p.dest_device_name}</span>` : '<span class="text-muted small">Sin conexión</span>';
        const destPort = p.dest_port_name ? `<span class="badge badge-light border"><i class="fas fa-ethernet text-muted mr-1"></i> ${p.dest_port_name}</span>` : '-';
        
        let statusBadge = '<span class="badge badge-secondary">Manual</span>';
        let trafficText = '-';
        
        if (p.is_zabbix) {
            const dotClass = p.status === 'Up' ? 'status-dot-up' : 'status-dot-down';
            statusBadge = `<div><span class="status-dot ${dotClass}"></span> <span class="font-weight-bold">${p.status}</span></div>`;
            trafficText = `<div class="small text-muted"><i class="fas fa-arrow-down text-success mr-1"></i>${formatBits(p.bits_received)}</div>
                           <div class="small text-muted"><i class="fas fa-arrow-up text-primary mr-1"></i>${formatBits(p.bits_sent)}</div>`;
        }

        const colorPill = p.mapping_id ? `<span class="color-pill" style="background-color: ${p.color_code};" title="${p.color_code}"></span>` : '-';
        
        let actions = '';
        if (p.mapping_id) {
            actions = `
                <button class="btn btn-xs btn-outline-info mr-1" onclick="openConnectModal('${p.port_name}', 'network', '${p.dest_device_id}', '${p.dest_port_name}', '${p.cable_type}', '${p.color_code}', \`${p.notes || ''}\`)" title="Editar Conexión">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-xs btn-outline-danger" onclick="deleteConnection(${p.mapping_id})" title="Desconectar / Eliminar Mapeo">
                    <i class="fas fa-unlink"></i>
                </button>
            `;
        } else {
            actions = `
                <button class="btn btn-xs btn-primary shadow-sm" onclick="openConnectModal('${p.port_name}', 'network')" title="Conectar">
                    <i class="fas fa-link mr-1"></i> Conectar
                </button>
            `;
        }

        // Trazado de ruta si existe mapeo
        let pathTraceHtml = '';
        if (p.mapping_id) {
            pathTraceHtml = `
                <div class="path-trace-breadcrumb mt-1">
                    <span>${srcDevice}</span>
                    <span class="badge badge-secondary ml-1">${p.port_name}</span>
                    <span class="path-trace-arrow">➔</span>
                    <span>${p.dest_device_name}</span>
                    <span class="badge badge-light border ml-1">${p.dest_port_name}</span>
                </div>
            `;
        }

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${idx + 1}</td>
            <td><strong>${srcDevice}</strong></td>
            <td>
                <div class="font-weight-bold text-primary">${p.port_name}</div>
                ${pathTraceHtml}
            </td>
            <td><small class="text-muted" title="${p.alias || ''}">${p.alias || '-'}</small></td>
            <td>${statusBadge}</td>
            <td class="font-family-monospace">${trafficText}</td>
            <td><small>${p.cable_type || '-'}</small></td>
            <td>${colorPill}</td>
            <td><small class="text-muted text-truncate d-block" style="max-width:140px;" title="${p.notes || ''}">${p.notes || '-'}</small></td>
            <td>${destDevice}</td>
            <td>${destPort}</td>
            <td class="text-center">${actions}</td>
        `;
        body.appendChild(tr);
    });
}

// RENDERIZAR TABLA DE ENERGÍA (POWER)
function renderPowerPorts(ports) {
    const body = document.getElementById('tbl_power_connections_body');
    body.innerHTML = '';

    if (ports.length === 0) {
        body.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No se han registrado componentes de alimentación eléctrica (PSU/Power) en el equipo.</td></tr>';
        return;
    }

    const srcDevice = document.getElementById('lbl_dev_name').innerText;

    ports.forEach((p, idx) => {
        const destDevice = p.dest_device_name ? `<span class="font-weight-bold text-warning"><i class="fas fa-charging-station mr-1"></i> ${p.dest_device_name}</span>` : '<span class="text-muted small">Sin conexión</span>';
        const destPort = p.dest_port_name ? `<span class="badge badge-light border"><i class="fas fa-plug text-muted mr-1"></i> ${p.dest_port_name}</span>` : '-';
        const colorPill = p.mapping_id ? `<span class="color-pill" style="background-color: ${p.color_code};" title="${p.color_code}"></span>` : '-';
        
        let actions = '';
        if (p.mapping_id) {
            actions = `
                <button class="btn btn-xs btn-outline-info mr-1" onclick="openConnectModal('${p.port_name}', 'power', '${p.dest_device_id}', '${p.dest_port_name}', '${p.cable_type}', '${p.color_code}', \`${p.notes || ''}\`)" title="Editar Conexión">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-xs btn-outline-danger" onclick="deleteConnection(${p.mapping_id})" title="Desconectar / Eliminar Mapeo">
                    <i class="fas fa-unlink"></i>
                </button>
            `;
        } else {
            actions = `
                <button class="btn btn-xs btn-warning text-white shadow-sm" onclick="openConnectModal('${p.port_name}', 'power')" title="Conectar Energía">
                    <i class="fas fa-plug mr-1"></i> Conectar
                </button>
            `;
        }

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${idx + 1}</td>
            <td><strong>${srcDevice}</strong></td>
            <td class="font-weight-bold text-orange"><i class="fas fa-bolt mr-1"></i> ${p.port_name}</td>
            <td><small>${p.cable_type || '-'}</small></td>
            <td>${colorPill}</td>
            <td><small class="text-muted text-truncate d-block" style="max-width:180px;" title="${p.notes || ''}">${p.notes || '-'}</small></td>
            <td>${destDevice}</td>
            <td>${destPort}</td>
            <td class="text-center">${actions}</td>
        `;
        body.appendChild(tr);
    });
}

// MOSTRAR MODAL DE CONEXIÓN
function openConnectModal(portName, connType, destDeviceId = '', destPortName = '', cableType = 'UTP Cat6A', colorCode = '#0000FF', notes = '') {
    if (!selectedDevice) return;

    document.getElementById('modal_device_id').value = selectedDevice;
    document.getElementById('modal_port_name').value = portName;
    document.getElementById('modal_connection_type').value = connType;

    const devName = document.getElementById('lbl_dev_name').innerText;
    const srcDevInput = document.getElementById('modal_source_device_name');
    if (srcDevInput) {
        srcDevInput.value = devName;
    }

    // Configurar valores por defecto para tipo de cable si es Energía
    const cableSelect = document.getElementById('modal_cable_type');
    if (connType === 'power') {
        cableSelect.value = cableType.includes('Poder') ? cableType : 'Cable de Poder Estándar';
    } else {
        cableSelect.value = cableType.includes('Poder') ? 'UTP Cat6A' : cableType;
    }

    document.getElementById('modal_color_code').value = colorCode;
    document.getElementById('modal_notes').value = notes;

    // Resetear formulario manual device
    const chkManualDev = document.getElementById('chk_manual_device');
    if (chkManualDev) {
        chkManualDev.checked = false;
        toggleManualDevice(false);
    }

    // Resetear formulario dest
    $('#modal_dest_device').val(destDeviceId).trigger('change');
    
    // Si estamos editando y tiene puerto dest manual, o queremos configurar puerto
    if (destPortName) {
        setTimeout(() => {
            // Comprobamos si el puerto existe en el select
            const exists = $(`#modal_dest_port option[value="${destPortName}"]`).length > 0;
            if (exists) {
                document.getElementById('chk_manual_port').checked = false;
                toggleManualPort(false);
                $('#modal_dest_port').val(destPortName);
            } else {
                document.getElementById('chk_manual_port').checked = true;
                toggleManualPort(true);
                document.getElementById('modal_dest_port_text').value = destPortName;
            }
        }, 1000);
    } else {
        document.getElementById('chk_manual_port').checked = false;
        toggleManualPort(false);
    }

    $('#modalConnection').modal('show');
}

// CAMBIAR ENTRADA EQUIPO DESTINO: SELECT vs MANUAL
function toggleManualDevice(isManual) {
    const wrapperSelect = document.getElementById('wrapper_select_device');
    const wrapperText = document.getElementById('wrapper_text_device');
    const chkManualPort = document.getElementById('chk_manual_port');
    const modalDestDeviceText = document.getElementById('modal_dest_device_text');
    
    if (isManual) {
        if (wrapperSelect) wrapperSelect.style.display = 'none';
        if (wrapperText) wrapperText.style.display = 'block';
        if (modalDestDeviceText) modalDestDeviceText.required = true;
        
        // Force manual port input
        if (chkManualPort) {
            chkManualPort.checked = true;
            chkManualPort.disabled = true;
        }
        toggleManualPort(true);
    } else {
        if (wrapperSelect) wrapperSelect.style.display = 'block';
        if (wrapperText) wrapperText.style.display = 'none';
        if (modalDestDeviceText) {
            modalDestDeviceText.required = false;
            modalDestDeviceText.value = '';
        }
        
        // Restore manual port input controls
        if (chkManualPort) {
            chkManualPort.disabled = false;
            chkManualPort.checked = false;
        }
        toggleManualPort(false);
    }
}

// CAMBIAR ENTRADA PUERTO DESTINO: SELECT vs MANUAL
function toggleManualPort(isManual) {
    if (isManual) {
        document.getElementById('wrapper_select_port').style.display = 'none';
        document.getElementById('wrapper_text_port').style.display = 'block';
        document.getElementById('modal_dest_port_text').required = true;
    } else {
        document.getElementById('wrapper_select_port').style.display = 'block';
        document.getElementById('wrapper_text_port').style.display = 'none';
        document.getElementById('modal_dest_port_text').required = false;
    }
}

// CARGAR PUERTOS DEL EQUIPO DESTINO
async function loadDestDevicePorts(destDeviceId) {
    const select = document.getElementById('modal_dest_port');
    select.innerHTML = '<option value="">Cargando puertos...</option>';
    
    try {
        const response = await fetch(`api_portmapping.php?action=get_device_ports&device_id=${destDeviceId}`);
        const result = await response.json();

        if (result.success) {
            if (result.data.length === 0) {
                document.getElementById('chk_manual_port').checked = true;
                toggleManualPort(true);
                select.innerHTML = '<option value="">Sin interfaces registradas. Ingrese manual.</option>';
            } else {
                select.innerHTML = '<option value="">Seleccione puerto...</option>';
                result.data.forEach(p => {
                    const disabled = p.is_mapped ? 'disabled' : '';
                    const text = p.is_mapped ? `${p.name} (Ocupado)` : p.name;
                    select.innerHTML += `<option value="${p.name}" ${disabled}>${text}</option>`;
                });
            }
        } else {
            select.innerHTML = '<option value="">Error cargando puertos</option>';
        }
    } catch (error) {
        select.innerHTML = '<option value="">Error de conexión</option>';
    }
}

// ELIMINAR / DESVINCULAR CONEXIÓN
function deleteConnection(mappingId) {
    Swal.fire({
        title: '¿Desvincular puerto?',
        text: "Se eliminará el enlace físico en el portmapping, liberando ambos extremos.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, desvincular',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const data = new FormData();
                data.append('action', 'delete_device_connection');
                data.append('mapping_id', mappingId);

                const resp = await fetch('api_portmapping.php', { method: 'POST', body: data });
                const res = await resp.json();
                
                if (res.success) {
                    toastr.success('Conexión desvinculada');
                    if (selectedDevice) {
                        loadDevicePortsAndConnections(selectedDevice);
                    }
                    loadMappings(); // Actualizar tab global
                } else {
                    Swal.fire('Error', res.error || 'No se pudo desvincular', 'error');
                }
            } catch (err) {
                toastr.error('Error al conectarse con el servidor');
            }
        }
    });
}

function showNewPortModal() {
    $('#modalNewPort').modal('show');
}

// FORMATO DE ANCHO DE BANDA
function formatBits(bits) {
    if (!bits || bits == 0) return '0 bps';
    const k = 1000;
    const sizes = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
    const i = Math.floor(Math.log(bits) / Math.log(k));
    return parseFloat((bits / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}


// WIZARD TAB LOGIC (PASO A PASO)
function nextStep(current, next) {
    document.getElementById(`step${current}`).classList.remove('active');
    document.getElementById(`ind_step${current}`).classList.remove('active');
    
    if(next > current) {
        document.getElementById(`ind_step${current}`).classList.add('completed');
    } else {
        document.getElementById(`ind_step${current}`).classList.remove('completed');
    }

    document.getElementById(`step${next}`).classList.add('active');
    document.getElementById(`ind_step${next}`).classList.add('active');
}

// Filtrar mapeos globales (Directorio Global)
function filterGlobalMappings() {
    const query = document.getElementById('global_mappings_search').value.toLowerCase();
    const type = document.getElementById('global_mappings_filter_type').value;
    
    const filtered = globalMappings.filter(map => {
        const matchesQuery = 
            map.source_device.toLowerCase().includes(query) ||
            map.source_port.toLowerCase().includes(query) ||
            map.target_device.toLowerCase().includes(query) ||
            map.target_port.toLowerCase().includes(query) ||
            (map.cable_type && map.cable_type.toLowerCase().includes(query)) ||
            (map.notes && map.notes.toLowerCase().includes(query));
            
        const matchesType = !type || map.connection_type === type;
        
        return matchesQuery && matchesType;
    });
    
    renderGlobalMappingsTable(filtered);
}

function selectArch(type, el) {
    document.querySelectorAll('.arch-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('mapping_type').value = type;
    document.getElementById('btn_next_2').disabled = false;
}

function checkStep1() {
    const cuarto = document.getElementById('sel_cuarto').value;
    document.getElementById('btn_next_1').disabled = (cuarto === "");
}

async function loadHierarchy(category, parentId, targetSelectId) {
    const targetSelect = document.getElementById(targetSelectId);
    if (!targetSelect) return;
    
    targetSelect.innerHTML = '<option value="">Cargando...</option>';
    targetSelect.disabled = true;

    const selects = ['sel_ciudad', 'sel_localidad', 'sel_area', 'sel_cuarto'];
    const idx = selects.indexOf(targetSelectId);
    if (idx !== -1) {
        for (let i = idx + 1; i < selects.length; i++) {
            const s = document.getElementById(selects[i]);
            if (s) { s.innerHTML = '<option value="">Seleccione...</option>'; s.disabled = true; }
        }
    }

    try {
        let url = `api_portmapping.php?action=get_hierarchy&category=${encodeURIComponent(category)}`;
        if (parentId) url += `&parent_id=${parentId}`;
        
        const response = await fetch(url);
        const result = await response.json();

        if (result.success) {
            targetSelect.innerHTML = '<option value="">Seleccione...</option>';
            result.data.forEach(item => {
                targetSelect.innerHTML += `<option value="${item.id}">${item.name}</option>`;
            });
            targetSelect.disabled = false;
        } else {
            targetSelect.innerHTML = '<option value="">Error</option>';
        }
    } catch (error) {
        console.error("Error:", error);
    }
}

async function setupStep3() {
    const roomId = document.getElementById('sel_cuarto').value;
    const type = document.getElementById('mapping_type').value;
    
    const srcDevice = document.getElementById('source_device');
    const tgtDevice = document.getElementById('target_device');
    srcDevice.innerHTML = '<option value="">Cargando...</option>';
    tgtDevice.innerHTML = '<option value="">Cargando...</option>';
    
    try {
        const response = await fetch(`api_portmapping.php?action=get_devices&room_id=${roomId}&arch_type=${type}`);
        const result = await response.json();

        if (result.success) {
            let options = '<option value="">Seleccione equipo...</option>';
            result.data.forEach(item => {
                options += `<option value="${item.id}">${item.name} (${item.category_name})</option>`;
            });
            srcDevice.innerHTML = options;
            tgtDevice.innerHTML = options;
        }
    } catch (error) {
        console.error(error);
    }
}

async function loadPorts(deviceId, targetSelectId) {
    const select = document.getElementById(targetSelectId);
    if(!deviceId) {
        select.innerHTML = '<option value="">Seleccione Equipo Primero</option>';
        return;
    }
    
    select.innerHTML = '<option value="">Cargando puertos...</option>';
    try {
        const response = await fetch(`api_portmapping.php?action=get_device_ports&device_id=${deviceId}`);
        const result = await response.json();

        if (result.success) {
            select.innerHTML = '<option value="">Seleccione Puerto...</option>';
            result.data.forEach(p => {
                const disabled = p.is_mapped ? 'disabled' : '';
                const text = p.is_mapped ? `${p.name} (Ocupado)` : p.name;
                select.innerHTML += `<option value="${p.id}" ${disabled}>${text}</option>`;
            });
        }
    } catch (error) {
        select.innerHTML = '<option value="">Error</option>';
    }
}

async function promptCreatePort(deviceSelectId, portSelectId) {
    const deviceId = document.getElementById(deviceSelectId).value;
    if(!deviceId) {
        toastr.warning("Seleccione un equipo primero antes de crearle un puerto.");
        return;
    }
    
    const { value: portName } = await Swal.fire({
        title: 'Añadir Nuevo Puerto (CMDB)',
        input: 'text',
        inputLabel: 'Nombre del Puerto (Ej. GigabitEthernet1/0/24)',
        inputPlaceholder: 'GigabitEthernet...',
        showCancelButton: true
    });

    if (portName) {
        const data = new FormData();
        data.append('action', 'create_port');
        data.append('device_id', deviceId);
        data.append('port_name', portName);
        
        const resp = await fetch('api_portmapping.php', {method: 'POST', body: data});
        const res = await resp.json();
        
        if(res.success) {
            toastr.success("Puerto registrado en la CMDB exitosamente.");
            loadPorts(deviceId, portSelectId);
        } else {
            toastr.error(res.error || "Error al crear el puerto");
        }
    }
}

function prepareSummary() {
    const srcSel = document.getElementById('source_port');
    const tgtSel = document.getElementById('target_port');
    const srcText = srcSel.options[srcSel.selectedIndex]?.text || '';
    const tgtText = tgtSel.options[tgtSel.selectedIndex]?.text || '';
    
    document.getElementById('sum_src').innerText = srcText;
    document.getElementById('sum_tgt').innerText = tgtText;
}


// CARGAR HISTORIAL GLOBAL DE MAPEOS (AUDITORÍA)
async function loadMappings() {
    const tbody = document.getElementById('mappings_table_body');
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Cargando mapeos globales...</td></tr>';
    
    try {
        const response = await fetch('api_portmapping.php?action=get_mappings');
        const result = await response.json();

        if (result.success) {
            globalMappings = result.data;
            document.getElementById('kpi_total').innerText = globalMappings.length;
            renderGlobalMappingsTable(globalMappings);
        }
    } catch (error) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-danger text-center py-4">Error al cargar mapeos.</td></tr>';
    }
}

function renderGlobalMappingsTable(mappings) {
    const tbody = document.getElementById('mappings_table_body');
    tbody.innerHTML = '';
    
    if (mappings.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No hay mapeos registrados en la CMDB.</td></tr>';
        return;
    }
    
    // Contadores de Zabbix
    let countUp = 0;
    let countDown = 0;

    mappings.forEach(map => {
        const tr = document.createElement('tr');
        const connTypeBadge = map.connection_type === 'power' 
            ? '<span class="badge badge-warning text-white"><i class="fas fa-bolt"></i> Energía</span>' 
            : '<span class="badge badge-info"><i class="fas fa-ethernet"></i> Red</span>';

        const colorPill = map.color_code ? `<span class="color-pill" style="background-color: ${map.color_code};" title="${map.color_code}"></span>` : '-';

        tr.innerHTML = `
            <td><strong>${map.source_device}</strong></td>
            <td><span class="badge badge-secondary">${map.source_port}</span></td>
            <td class="text-center align-middle">${connTypeBadge}</td>
            <td><small>${map.cable_type || '-'}</small></td>
            <td class="text-center align-middle">${colorPill}</td>
            <td><small class="text-muted text-truncate d-block" style="max-width:180px;" title="${map.notes || ''}">${map.notes || '-'}</small></td>
            <td><strong>${map.target_device}</strong></td>
            <td><span class="badge badge-light border">${map.target_port}</span></td>
            <td id="global_telemetry_${map.id}" class="align-middle text-center">
                <i class="fas fa-spinner fa-spin text-muted"></i>
            </td>
        `;
        tbody.appendChild(tr);
        
        // Carga asíncrona de telemetría por fila
        loadRowTelemetry(map.target_component_id, `global_telemetry_${map.id}`, (status) => {
            if (status === 'UP') {
                countUp++;
                document.getElementById('kpi_activos').innerText = countUp;
            } else if (status === 'DOWN') {
                countDown++;
                document.getElementById('kpi_alertas').innerText = countDown;
            }
        });
    });
}

// OBTENER TELEMETRÍA DE RED EN TIEMPO REAL
async function loadRowTelemetry(portId, elementId, statusCallback) {
    const container = document.getElementById(elementId);
    if (!container) return;

    try {
        const response = await fetch(`api_portmapping.php?action=get_hybrid_port_data&port_id=${portId}`);
        const result = await response.json();

        if (result.success && result.data.zabbix_telemetry) {
            const zbx = result.data.zabbix_telemetry;
            const badgeClass = zbx.status === 'UP' ? 'badge-success' : 'badge-danger';
            container.innerHTML = `<span class="badge ${badgeClass} shadow-none">${zbx.status}</span>`;
            statusCallback(zbx.status);
        } else {
            container.innerHTML = `<span class="badge badge-secondary shadow-none">CMDB Solo</span>`;
        }
    } catch (error) {
        container.innerHTML = `<span class="text-danger small"><i class="fas fa-times-circle"></i> Error</span>`;
    }
}
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
