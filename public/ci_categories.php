<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

require_login();
if (!has_role('SUPER_ADMIN')) {
    die("Acceso denegado. Se requiere rol SUPER_ADMIN.");
}

$page_title = 'Gestor de Categorías de CMDB';
require_once __DIR__ . '/partials/header.php';
?>

<style>
    /* Estilos Premium para CMDB Admin */
    :root {
        --sonda-navy: #0F172A;
        --sonda-blue: #1E293B;
        --sonda-cyan: #0EA5E9;
        --sonda-orange: #F97316;
        --sonda-gray: #64748B;
        --sonda-light: #F8FAFC;
    }
    .cmdb-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        background: #ffffff;
    }
    .cmdb-card-header {
        background: #ffffff;
        border-bottom: 1px solid #E2E8F0;
        padding: 1.5rem;
        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
    }
    .tree-container {
        max-height: 700px;
        overflow-y: auto;
        padding: 1rem;
        background: var(--sonda-light);
        border-radius: 8px;
    }
    .node-item {
        transition: all 0.2s ease;
        border-radius: 6px;
        margin-bottom: 0.25rem;
    }
    .node-item:hover {
        background-color: #E2E8F0;
    }
    .node-item.active-node {
        background-color: #E0F2FE;
        border-left: 4px solid var(--sonda-cyan);
    }
    .node-link {
        color: var(--sonda-navy);
        font-weight: 500;
        text-decoration: none;
        display: flex;
        align-items: center;
        padding: 0.5rem;
    }
    .node-link:hover {
        color: var(--sonda-cyan);
        text-decoration: none;
    }
    .indent-line {
        border-left: 2px dashed #CBD5E1;
        margin-left: 1rem;
        padding-left: 0.5rem;
    }
    .auditoria-badge {
        font-size: 0.8rem;
        background: #F1F5F9;
        color: #475569;
        border: 1px solid #E2E8F0;
        border-radius: 6px;
        padding: 0.5rem;
    }
    .global-attribute-row {
        background-color: #F8FAFC;
        color: #64748B;
        font-style: italic;
    }
    
    /* Modal de Relaciones */
    #relationsModal .modal-dialog {
        max-width: 95vw !important;
        width: 95vw !important;
        margin: 1.5vh auto;
    }
    #relationsModal .modal-content {
        background-color: #ffffff;
        height: 97vh;
    }
    #relationsModal .modal-body {
        height: calc(97vh - 120px) !important;
        overflow: hidden;
    }
    #relationsModal .col-md-3 {
        max-height: calc(97vh - 120px) !important;
        height: calc(97vh - 120px) !important;
        overflow-y: auto;
    }
    #relationsDiagramDiv {
        width: 100%;
        height: calc(97vh - 120px) !important;
        background-color: #fcfcfc;
    }
    #relationsModal .badge-outline-success {
        color: #059669;
        border: 1px solid #a7f3d0;
        background-color: #ecfdf5;
    }
    #relationsModal .badge-outline-danger {
        color: #dc2626;
        border: 1px solid #fecaca;
        background-color: #fef2f2;
    }
</style>

<div class="container-fluid pt-4">
    <!-- Breadcrumb o Título -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 font-weight-bold text-dark mb-0">CMDB Admin</h1>
            <p class="text-muted mb-0">Reingeniería del Módulo de CMDB Jerárquica y Administrador de Categorías</p>
        </div>
        <div>
            <span class="badge badge-primary px-3 py-2" style="font-size: 0.9rem;"><i class="fas fa-layer-group"></i> Gestor de Categorías</span>
        </div>
    </div>

    <div class="row">
        <!-- Panel Izquierdo: Árbol Jerárquico -->
        <div class="col-lg-4 col-md-5 mb-4">
            <div class="card cmdb-card">
                <div class="cmdb-card-header d-flex justify-content-between align-items-center">
                    <h5 class="m-0 font-weight-bold text-dark" style="font-size: 1.05rem;"><i class="fas fa-sitemap text-primary mr-1"></i> Jerarquía</h5>
                    <div class="d-flex align-items-center">
                        <button type="button" class="btn btn-xs btn-outline-info mr-2" onclick="showGlobalCategoryRelations()" title="Ver Relaciones Globales de Categorías">
                            <i class="fas fa-project-diagram mr-1"></i> Mapa Global
                        </button>
                        <button class="btn btn-sm btn-success btn-round" onclick="openCreateForm()"><i class="fas fa-plus mr-1"></i> Nueva</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="tree-container" id="category-tree">
                        <!-- JS Rendered Tree -->
                        <div class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-2"></i><br>Cargando jerarquía de categorías...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel Derecho: Formulario de Creación / Edición -->
        <div class="col-lg-8 col-md-7 mb-4">
            <!-- Mensaje Bienvenida -->
            <div id="welcome-msg" class="card cmdb-card text-center py-5">
                <div class="card-body">
                    <i class="fas fa-project-diagram fa-4x text-muted opacity-2 mb-3"></i>
                    <h4 class="font-weight-bold text-dark">Gestión de Categorías CMDB</h4>
                    <p class="text-muted max-width-500 mx-auto">Selecciona una categoría de la estructura jerárquica a la izquierda para modificar su esquema, atributos y dependencias, o haz clic en "Nueva" para crear una categoría desde cero.</p>
                </div>
            </div>

            <!-- Formulario Principal -->
            <div class="card cmdb-card shadow-sm" id="form-card" style="display:none;">
                <div class="cmdb-card-header d-flex justify-content-between align-items-center">
                    <h5 class="m-0 font-weight-bold text-dark" id="form-title">Editar Categoría</h5>
                    <div class="d-flex align-items-center">
                        <button type="button" class="btn btn-sm btn-outline-info mr-2 d-none" id="btn-visualize-relations" onclick="showCategoryRelations($('#cat_id').val())">
                            <i class="fas fa-project-diagram mr-1"></i> Ver Árbol de Relaciones
                        </button>
                        <button class="btn btn-sm btn-danger d-none" id="btn-delete" onclick="deleteCategory()"><i class="fas fa-trash mr-1"></i> Eliminar</button>
                    </div>
                </div>
                <div class="card-body">
                    <form id="category-form">
                        <input type="hidden" name="action" value="save_category">
                        <input type="hidden" name="id" id="cat_id" value="0">
                        <input type="hidden" name="dependencies_json" id="cat_dependencies" value="[]">
                        
                        <!-- Sección Auditoría Directa en BDD -->
                        <div class="row mb-4" id="auditoria-section" style="display:none;">
                            <div class="col-md-6 mb-2">
                                <div class="auditoria-badge">
                                    <i class="fas fa-hashtag text-primary mr-1"></i> <strong>ID Único (cat_unique):</strong> 
                                    <span id="display_cat_unique" class="badge badge-dark ml-1 font-weight-bold">CAT-000000</span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="auditoria-badge">
                                    <i class="fas fa-history text-success mr-1"></i> <strong>Última Actualización:</strong> 
                                    <span id="display_ultima_actualizacion" class="text-secondary ml-1">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Datos Básicos -->
                        <h6 class="text-uppercase font-weight-bold text-primary mb-3" style="font-size:0.85rem; letter-spacing:1px;">1. Datos Básicos de la Categoría</h6>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Nombre de la Categoría <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="cat_name" class="form-control" required placeholder="Ej. Hardware CI">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Categoría Padre</label>
                                <select name="parent_id" id="cat_parent_id" class="form-control">
                                    <option value="">-- Ninguna (Categoría Raíz) --</option>
                                </select>
                                <small class="form-text text-muted">Establece la relación jerárquica (Máx. 10 niveles).</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Ícono de la Categoría</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light" id="icon-preview"><i class="fas fa-cube"></i></span>
                                    </div>
                                    <input type="text" name="icon" id="cat_icon" class="form-control" value="fa-cube" placeholder="ej: fa-server">
                                </div>
                                <div class="mt-2 p-2 border rounded bg-light">
                                    <input type="text" id="search-icons" class="form-control form-control-sm mb-2" placeholder="🔍 Buscar ícono por nombre o etiqueta...">
                                    <div style="max-height: 150px; overflow-y: auto;" class="d-flex flex-wrap" id="icon-list-container">
                                        <!-- Servidores y Cómputo -->
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-server" title="Servidor"><i class="fas fa-server"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-database" title="Base de Datos"><i class="fas fa-database"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-desktop" title="Computadora de Escritorio"><i class="fas fa-desktop"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-laptop" title="Computadora Portátil"><i class="fas fa-laptop"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-microchip" title="Procesador / CPU"><i class="fas fa-microchip"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-memory" title="Memoria RAM"><i class="fas fa-memory"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-hdd" title="Disco Duro / Almacenamiento"><i class="fas fa-hdd"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-terminal" title="Terminal de Comandos"><i class="fas fa-terminal"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-print" title="Impresora"><i class="fas fa-print"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-tablet-alt" title="Tablet"><i class="fas fa-tablet-alt"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-mobile-alt" title="Teléfono Móvil"><i class="fas fa-mobile-alt"></i></button>

                                        <!-- Redes y Comunicaciones -->
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-network-wired" title="Red Cableada"><i class="fas fa-network-wired"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-wifi" title="WiFi / Red Inalámbrica"><i class="fas fa-wifi"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-route" title="Enrutador / Router"><i class="fas fa-route"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-ethernet" title="Puerto / Cable Ethernet"><i class="fas fa-ethernet"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-signal" title="Señal de Red"><i class="fas fa-signal"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-broadcast-tower" title="Torre de Transmisión"><i class="fas fa-broadcast-tower"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-satellite-dish" title="Antena Satelital"><i class="fas fa-satellite-dish"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-satellite" title="Satélite"><i class="fas fa-satellite"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-globe" title="Internet / Global"><i class="fas fa-globe"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-link" title="Enlace / Vínculo"><i class="fas fa-link"></i></button>

                                        <!-- Energía e Infraestructura -->
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-plug" title="Energía / Enchufe"><i class="fas fa-plug"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-bolt" title="UPS / Corriente / Rayo"><i class="fas fa-bolt"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-solar-panel" title="Panel Solar"><i class="fas fa-solar-panel"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-battery-full" title="Batería"><i class="fas fa-battery-full"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-building" title="Edificio / Datacenter"><i class="fas fa-building"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-city" title="Ciudad"><i class="fas fa-city"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-warehouse" title="Bodega / Almacén"><i class="fas fa-warehouse"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-fan" title="Climatización / Ventilador / Aire Acondicionado"><i class="fas fa-fan"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-thermometer-half" title="Temperatura"><i class="fas fa-thermometer-half"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-wind" title="Flujo de Aire"><i class="fas fa-wind"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-fire-extinguisher" title="Extintor de Incendios"><i class="fas fa-fire-extinguisher"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-door-open" title="Puerta Abierta"><i class="fas fa-door-open"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-door-closed" title="Puerta Cerrada"><i class="fas fa-door-closed"></i></button>

                                        <!-- Seguridad y Control -->
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-shield-alt" title="Cortafuegos / Firewall / Seguridad"><i class="fas fa-shield-alt"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-key" title="Llave de Acceso"><i class="fas fa-key"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-lock" title="Bloqueado"><i class="fas fa-lock"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-unlock" title="Desbloqueado"><i class="fas fa-unlock"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-user-shield" title="Administrador de Seguridad"><i class="fas fa-user-shield"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-eye" title="Monitoreo / Cámara / Ojo"><i class="fas fa-eye"></i></button>

                                        <!-- Software, Datos y Nube -->
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-cube" title="Elemento de Configuración / CI"><i class="fas fa-cube"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-cubes" title="Contenedores / Cluster"><i class="fas fa-cubes"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-cloud" title="Nube / Internet"><i class="fas fa-cloud"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-code" title="Código / Software / API"><i class="fas fa-code"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-cogs" title="Servicio / Configuración"><i class="fas fa-cogs"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-archive" title="Respaldo / Backup / Archivo"><i class="fas fa-archive"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-folder" title="Carpeta / Directorio"><i class="fas fa-folder"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-file-alt" title="Archivo de Texto / Config"><i class="fas fa-file-alt"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-project-diagram" title="Topología / Diagrama"><i class="fas fa-project-diagram"></i></button>

                                        <!-- Geografía y Ubicación -->
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-map-marker-alt" title="Marcador GPS / Ubicación"><i class="fas fa-map-marker-alt"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-map" title="Mapa Geográfico"><i class="fas fa-map"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-compass" title="Brújula"><i class="fas fa-compass"></i></button>

                                        <!-- Soporte y Personal -->
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-user" title="Personal / Usuario"><i class="fas fa-user"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-users" title="Grupo de Usuarios / Departamento"><i class="fas fa-users"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-headset" title="Soporte Técnico / Helpdesk"><i class="fas fa-headset"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-tools" title="Herramientas de Mantenimiento"><i class="fas fa-tools"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-wrench" title="Reparación / Mantenimiento"><i class="fas fa-wrench"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-info-circle" title="Información"><i class="fas fa-info-circle"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-exclamation-triangle" title="Alerta / Advertencia"><i class="fas fa-exclamation-triangle"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-check-circle" title="Verificado / OK"><i class="fas fa-check-circle"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary icon-btn m-1" data-icon="fa-clock" title="Historial / Tiempo"><i class="fas fa-clock"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 form-group d-flex align-items-center">
                                <div class="custom-control custom-switch mt-3">
                                    <input type="checkbox" class="custom-control-input" name="requires_parent_instance" id="cat_requires_parent_instance" value="1">
                                    <label class="custom-control-label font-weight-bold" for="cat_requires_parent_instance">Requiere Instancia Padre</label>
                                    <small class="form-text text-muted d-block">Indica si el CI hijo necesitará obligatoriamente un CI padre para existir (e.g. Ubicación -> Ciudad requiere de País).</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Descripción / Función</label>
                            <textarea name="description" id="cat_description" class="form-control" rows="2" placeholder="Describa el rol o CIs agrupados en esta categoría..."></textarea>
                        </div>

                        <!-- Relaciones de Dependencia e Inter-Categoría (Cross-Category) -->
                        <hr class="my-4">
                        <h6 class="text-uppercase font-weight-bold text-primary mb-3" style="font-size:0.85rem; letter-spacing:1px;">2. Relaciones de Dependencia e Inter-Categoría (Cross-Category)</h6>
                        <div class="form-group">
                            <p class="small text-muted mb-2">Permite que esta categoría herede conceptualmente o requiera los datos de otra pestaña/rama para autocompletarse (ej: Switch requiere Ubicación obligatoriamente, pero Personal o Software opcionalmente).</p>
                            <div class="border p-3 bg-light rounded mb-3">
                                <table class="table table-sm table-bordered bg-white" id="dependencies-table">
                                    <thead>
                                        <tr class="bg-light">
                                            <th>Categoría Relacionada</th>
                                            <th>Tipo de Dependencia</th>
                                            <th>Tipo de Relación</th>
                                            <th>Color de Línea</th>
                                            <th>Tipo de Conector</th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- JS populated -->
                                    </tbody>
                                </table>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addDependencyRow()"><i class="fas fa-plus mr-1"></i> Añadir Dependencia Inter-Categoría</button>
                            </div>
                        </div>

                        <!-- Constructor Visual de Atributos -->
                        <hr class="my-4">
                        <h6 class="text-uppercase font-weight-bold text-primary mb-3" style="font-size:0.85rem; letter-spacing:1px;">3. Constructor de Atributos Específicos</h6>
                        <div class="form-group">
                            <p class="small text-muted mb-2">Configure los campos específicos que tendrán los CIs asociados a esta categoría. Los atributos obligatorios globales se inyectan automáticamente y no se pueden modificar.</p>
                            <div class="border p-3 bg-white rounded shadow-sm">
                                <table class="table table-sm table-striped" id="schema-table">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Atributo</th>
                                            <th>Grupo</th>
                                            <th>Tipo</th>
                                            <th class="text-center">Req.</th>
                                            <th>Descripción</th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- JS rendered rows -->
                                    </tbody>
                                </table>
                                <div class="d-flex justify-content-between mt-3">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addAttributeRow()"><i class="fas fa-plus"></i> Atributo Específico</button>
                                    
                                    <div class="dropdown" id="global-attrs-dropdown-container">
                                        <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" id="dropdownGlobalAttrs" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-globe"></i> Añadir de Plantilla Global
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownGlobalAttrs" id="global-attrs-menu" style="max-height: 380px; min-width: 320px; overflow-y: auto;">
                                            <!-- JS populated -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <textarea name="schema_json" id="cat_schema" class="d-none">{}</textarea>
                        </div>
                        
                        <div class="text-right mt-4 pt-3 border-top">
                            <button type="button" class="btn btn-secondary mr-2" onclick="closeForm()">Cancelar</button>
                            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save mr-1"></i> Guardar Categoría</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Árbol de Relaciones Inter-Categoría -->
<div class="modal fade" id="relationsModal" tabindex="-1" role="dialog" aria-labelledby="relationsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <div class="modal-header bg-dark text-white" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <h5 class="modal-title font-weight-bold" id="relationsModalLabel">
                    <i class="fas fa-project-diagram mr-2 text-info"></i> Árbol de Relaciones: <span id="modal-category-name" class="text-info font-weight-bold"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div class="row no-gutters">
                    <!-- Área del Diagrama Interactivo (GoJS) (Ancho completo) -->
                    <div class="col-md-12" style="position: relative;">
                        <!-- Selector de Distribución y Filtro -->
                        <div style="position: absolute; top: 15px; left: 15px; z-index: 10; display: flex; gap: 10px; align-items: center;">
                            <div class="input-group input-group-sm shadow-sm" style="width: 200px;">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-white text-muted font-weight-bold"><i class="fas fa-sitemap mr-1"></i> Diseño:</span>
                                </div>
                                <select class="form-control form-control-sm" id="diagram-layout-select" onchange="changeDiagramLayout(this.value)">
                                    <option value="tree">Árbol (Jerárquico)</option>
                                    <option value="radial">Estrella (Redial/Fuerza)</option>
                                    <option value="layered">Red (Flujo/Capas)</option>
                                    <option value="grid">Rejilla (Cuadrícula)</option>
                                </select>
                            </div>
                            <div class="bg-white border rounded px-3 py-1 shadow-sm d-flex align-items-center" style="height: 31px;">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="toggle-dependencies-chk" checked onchange="toggleDependencies(this.checked)">
                                    <label class="custom-control-label small font-weight-bold text-secondary" style="cursor: pointer; user-select: none;" for="toggle-dependencies-chk">Ver Dependencias</label>
                                </div>
                            </div>
                        </div>
                        <div id="relationsDiagramDiv" style="width: 100%; height: 600px; background-color: #fcfcfc;"></div>
                        <!-- Controles de Zoom/Ajuste -->
                        <div style="position: absolute; bottom: 15px; right: 15px; z-index: 10;">
                            <div class="btn-group shadow-sm">
                                <button class="btn btn-sm btn-white bg-white border" onclick="zoomInDiagram()" title="Acercar"><i class="fas fa-plus"></i></button>
                                <button class="btn btn-sm btn-white bg-white border" onclick="zoomOutDiagram()" title="Alejar"><i class="fas fa-minus"></i></button>
                                <button class="btn btn-sm btn-white bg-white border" onclick="resetDiagram()" title="Ajustar Vista"><i class="fas fa-sync-alt"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/gojs/release/go.js"></script>
<script>
let categories = [];
let globalAttributes = [];
let relationshipTypes = [];
let currentCategoryDependencies = [];

$(document).ready(function() {
    loadCategories();
    loadGlobalAttributes();
    loadRelationshipTypes();

    // Evento de apertura para redibujar el dropdown con los estados de atributos actuales
    $('#global-attrs-dropdown-container').on('show.bs.dropdown', function() {
        renderGlobalAttributesDropdownMenu();
    });

    // Eventos de iconos
    $(document).on('click', '.icon-btn', function() {
        let icon = $(this).data('icon');
        $('#cat_icon').val(icon);
        $('#icon-preview').html(`<i class="fas ${icon}"></i>`);
    });
    $('#cat_icon').on('input', function() {
        let val = $(this).val();
        $('#icon-preview').html(`<i class="fas ${val}"></i>`);
    });
    // Búsqueda / filtrado en tiempo real de íconos
    $(document).on('input', '#search-icons', function() {
        let query = $(this).val().toLowerCase();
        $('.icon-btn').each(function() {
            let iconClass = ($(this).data('icon') || '').toLowerCase();
            let iconTitle = ($(this).attr('title') || '').toLowerCase();
            if (iconClass.includes(query) || iconTitle.includes(query)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // Envío del Formulario
    $('#category-form').submit(function(e) {
        e.preventDefault();
        
        buildJsonFromUI();
        buildDependenciesJsonFromUI();
        
        // Validar JSON
        try {
            JSON.parse($('#cat_schema').val());
        } catch(err) {
            Swal.fire('JSON Inválido', 'El formato de atributos no es correcto: ' + err.message, 'error');
            return;
        }

        $.post('api_ci.php', $(this).serialize(), function(res) {
            if (res.success) {
                Swal.fire('Éxito', res.message, 'success');
                loadCategories();
                closeForm();
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json').fail(function(xhr) {
            Swal.fire('Error Técnico', xhr.responseJSON ? xhr.responseJSON.message : 'Error al guardar la categoría', 'error');
        });
    });
});

function loadCategories() {
    $.get('api_ci.php?action=get_categories', function(res) {
        if (res.success) {
            categories = res.data;
            renderCategoryTree();
            updateParentSelect();
        }
    }, 'json');
}

function loadRelationshipTypes() {
    $.get('api_ci.php?action=get_relationship_types', function(res) {
        if (res.success) {
            relationshipTypes = res.data;
        }
    }, 'json');
}

function renderCategoryTree() {
    let container = $('#category-tree');
    container.empty();
    
    if (categories.length === 0) {
        container.append('<div class="text-center text-muted py-4">No hay categorías creadas.</div>');
        return;
    }

    // Estructurar árbol en JS
    let map = {};
    categories.forEach(c => {
        map[c.id] = {...c, children: []};
    });
    
    let tree = [];
    categories.forEach(c => {
        if (c.parent_id && map[c.parent_id]) {
            map[c.parent_id].children.push(map[c.id]);
        } else {
            tree.push(map[c.id]);
        }
    });

    function buildHtml(nodes, level) {
        let h = '';
        nodes.forEach(node => {
            let hasChildren = node.children.length > 0;
            let icon = node.icon || (hasChildren ? 'fa-folder' : 'fa-cube');
            if (icon.indexOf('fa-') === -1) icon = 'fa-' + icon;
            
            // Flechita para expandir
            let caret = hasChildren ? 
                `<i class="fas fa-chevron-right toggle-caret mr-2 text-secondary" style="cursor: pointer; width: 12px; font-size: 0.8rem;" data-target="children-of-${node.id}"></i>` : 
                `<span style="display: inline-block; width: 20px;"></span>`;
            
            h += `
            <div class="node-container">
                <div class="node-item" id="node-${node.id}">
                    <div class="d-flex align-items-center px-2">
                        ${caret}
                        <a href="javascript:void(0)" class="node-link flex-grow-1 p-2 m-0" onclick="openEditForm(${node.id})">
                            <i class="fas ${icon} text-primary mr-2" style="width: 20px;"></i>
                            <span>${node.name}</span>
                            <span class="badge badge-light text-muted ml-auto font-weight-normal">${node.cat_unique || 'CAT'}</span>
                        </a>
                        ${node.id == 45 ? `
                        <button type="button" class="btn btn-xs btn-outline-info ml-2" onclick="showCategoryRelations(${node.id}); event.stopPropagation();" title="Ver Árbol de Relaciones">
                            <i class="fas fa-project-diagram"></i>
                        </button>
                        ` : ''}
                    </div>
                </div>
            `;
            if (hasChildren) {
                h += `<div class="indent-line" id="children-of-${node.id}" style="display: none;">`;
                h += buildHtml(node.children, level + 1);
                h += `</div>`;
            }
            h += `</div>`;
        });
        return h;
    }

    container.append(buildHtml(tree, 0));
    
    // Agregar manejador de eventos para el toggle-caret
    $('.toggle-caret').off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        let targetId = $(this).data('target');
        let targetDiv = $('#' + targetId);
        
        if (targetDiv.is(':visible')) {
            targetDiv.slideUp(200);
            $(this).removeClass('fa-chevron-down').addClass('fa-chevron-right');
        } else {
            targetDiv.slideDown(200);
            $(this).removeClass('fa-chevron-right').addClass('fa-chevron-down');
        }
    });
}

function updateParentSelect() {
    let sel = $('#cat_parent_id');
    sel.html('<option value="">-- Ninguna (Categoría Raíz) --</option>');
    
    // Lista plana con indentación para el selector de padre
    let map = {};
    categories.forEach(c => {
        map[c.id] = {...c, children: []};
    });
    let tree = [];
    categories.forEach(c => {
        if (c.parent_id && map[c.parent_id]) {
            map[c.parent_id].children.push(map[c.id]);
        } else {
            tree.push(map[c.id]);
        }
    });

    function addOptions(nodes, level) {
        let prefix = '— '.repeat(level);
        nodes.forEach(n => {
            sel.append(`<option value="${n.id}">${prefix}${n.name}</option>`);
            if (n.children && n.children.length > 0) {
                addOptions(n.children, level + 1);
            }
        });
    }
    
    addOptions(tree, 0);
}

function openCreateForm() {
    $('.node-item').removeClass('active-node');
    $('#form-card').show();
    $('#welcome-msg').hide();
    $('#btn-delete').addClass('d-none');
    $('#btn-visualize-relations').addClass('d-none');
    $('#form-title').html('<i class="fas fa-plus text-success mr-2"></i>Nueva Categoría');
    
    // Limpiar campos
    $('#cat_id').val(0);
    $('#cat_name').val('');
    $('#cat_description').val('');
    $('#cat_parent_id').val('');
    $('#cat_requires_parent_instance').prop('checked', false);
    $('#cat_icon').val('fa-cube');
    $('#icon-preview').html('<i class="fas fa-cube"></i>');
    
    // Ocultar sección de auditoría para nuevas categorías
    $('#auditoria-section').hide();
    
    // Limpiar dependencias inter-categoría
    $('#dependencies-table tbody').empty();
    
    // Limpiar y cargar esquema de atributos (inyectando globales por defecto)
    resetSchemaTable();
}

function openEditForm(id) {
    $('.node-item').removeClass('active-node');
    $(`#node-${id}`).addClass('active-node');
    
    let cat = categories.find(c => c.id == id);
    if (!cat) return;
    
    $('#form-card').show();
    $('#welcome-msg').hide();
    $('#btn-delete').removeClass('d-none');
    if (id == 45) {
        $('#btn-visualize-relations').removeClass('d-none');
    } else {
        $('#btn-visualize-relations').addClass('d-none');
    }
    $('#form-title').html('<i class="fas fa-edit text-warning mr-2"></i>Editar Categoría');
    
    $('#cat_id').val(cat.id);
    $('#cat_name').val(cat.name);
    $('#cat_description').val(cat.description || '');
    $('#cat_parent_id').val(cat.parent_id || '');
    $('#cat_requires_parent_instance').prop('checked', cat.requires_parent_instance == 1);
    
    let icon = cat.icon || 'fa-cube';
    $('#cat_icon').val(icon);
    $('#icon-preview').html(`<i class="fas ${icon}"></i>`);
    
    // Mostrar sección de auditoría
    $('#auditoria-section').show();
    $('#display_cat_unique').text(cat.cat_unique || 'CAT-' + String(cat.id).padStart(6, '0'));
    $('#display_ultima_actualizacion').text(cat.ultima_actualizacion || 'No registrada');
    
    // Expandir automáticamente los ancestros para mostrar dónde está ubicada
    let currentParentId = cat.parent_id;
    let loopVisited = {};
    while(currentParentId) {
        if (loopVisited[currentParentId]) break;
        loopVisited[currentParentId] = true;
        let parentDiv = $('#children-of-' + currentParentId);
        if (parentDiv.length > 0) {
            parentDiv.show();
            // Cambiar caret del padre a chevron-down
            $(`#node-${currentParentId} .toggle-caret`).removeClass('fa-chevron-right').addClass('fa-chevron-down');
        }
        let pCat = categories.find(c => c.id == currentParentId);
        currentParentId = pCat ? pCat.parent_id : null;
    }
    
    // Cargar dependencias inter-categoría
    let tbodyDep = $('#dependencies-table tbody');
    tbodyDep.empty();
    if (cat.dependencies && cat.dependencies.length > 0) {
        cat.dependencies.forEach(d => {
            addDependencyRow(d.target_category_id, d.dependency_type, d.line_color, d.line_style, d.relationship_type_id);
        });
    }
    
    // Cargar atributos
    resetSchemaTable();
    try {
        let schemaObj = typeof cat.schema_json === 'string' ? JSON.parse(cat.schema_json) : cat.schema_json;
        parseJsonToUI(schemaObj);
        
        // Cargar atributos heredados de padres
        let currentId = cat.parent_id;
        let visited = {};
        while(currentId) {
            if (visited[currentId]) break;
            visited[currentId] = true;
            let pCat = categories.find(c => c.id == currentId);
            if (pCat) {
                let pSchema = typeof pCat.schema_json === 'string' ? JSON.parse(pCat.schema_json) : pCat.schema_json;
                parseJsonToUI(pSchema, true, pCat.name);
                currentId = pCat.parent_id;
            } else {
                break;
            }
        }
    } catch(e) {
        console.error("Error al parsear schema_json", e);
    }
}

function closeForm() {
    $('#form-card').hide();
    $('#welcome-msg').show();
    $('.node-item').removeClass('active-node');
}

// Inyección Obligatoria de Atributos Globales (Regla de negocio 2.A)
function resetSchemaTable() {
    let tbody = $('#schema-table tbody');
    tbody.empty();
    
    // Atributos obligatorios inmutables
    let globalMandatory = [
        { name: 'nombre', group: 'General', type: 'string', required: true, desc: 'Nombre del CI (Estandarizado en backend)' },
        { name: 'sigla', group: 'General', type: 'string', required: true, desc: 'Sigla o etiqueta de CI' },
        { name: 'fecha_creacion', group: 'General', type: 'date', required: true, desc: 'Fecha de creación del elemento' },
        { name: 'ci_unique', group: 'General', type: 'string', required: true, desc: 'Código Único de CI (Nomenclatura SND-XXXXXXXXXX)' }
    ];
    
    globalMandatory.forEach(attr => {
        let tr = `
            <tr class="global-attribute-row">
                <td><i class="fas fa-lock mr-2 text-muted" title="Atributo Global Obligatorio Inmutable"></i> <strong>${attr.name}</strong></td>
                <td>${attr.group}</td>
                <td>${attr.type === 'string' ? 'Texto Corto' : 'Fecha'}</td>
                <td class="text-center"><i class="fas fa-check-circle text-success"></i></td>
                <td>${attr.desc}</td>
                <td></td>
            </tr>
        `;
        tbody.append(tr);
    });
}

function addAttributeRow(name = '', type = 'string', req = false, desc = '', isGlobal = false, groupName = 'General', isInherited = false, parentName = '') {
    let tbody = $('#schema-table tbody');
    
    // Validar duplicados con los atributos globales inmutables
    let reserved = ['nombre', 'sigla', 'fecha_creacion', 'ci_unique'];
    if (reserved.includes(name.toLowerCase())) {
        return;
    }
    
    let inputReadonly = (isGlobal || isInherited) ? 'readonly' : '';
    let disabledSelect = (isGlobal || isInherited) ? 'disabled' : '';
    let rowClass = isInherited ? 'bg-secondary text-white' : (isGlobal ? 'bg-light' : '');
    
    let inheritIcon = isInherited ? `<span class="badge badge-warning mr-1" title="Heredado de ${parentName}"><i class="fas fa-level-up-alt"></i> ${parentName}</span> ` : '';
    let globalIcon = isGlobal && !isInherited ? '<i class="fas fa-globe text-success mr-1" title="Atributo Global de Plantilla"></i> ' : '';
    
    let trId = 'attr_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5);
    
    let tr = `
        <tr class="attr-row ${isInherited ? 'inherited-attr' : ''} ${rowClass}">
            <td>
                ${inheritIcon}${globalIcon}
                <input type="text" class="form-control form-control-sm attr-name" value="${name}" placeholder="ej: ram" required ${inputReadonly}>
            </td>
            <td>
                <select class="form-control form-control-sm attr-group" required ${disabledSelect}>
                    <option value="General" ${groupName=='General'?'selected':''}>General</option>
                    <option value="Monitoreo" ${groupName=='Monitoreo'?'selected':''}>Monitoreo</option>
                    <option value="Comunicaciones" ${groupName=='Comunicaciones'?'selected':''}>Comunicaciones</option>
                    <option value="Dependencias y Relaciones" ${groupName=='Dependencias y Relaciones'?'selected':''}>Dependencias y Relaciones</option>
                    <option value="Propiedad" ${groupName=='Propiedad'?'selected':''}>Propiedad</option>
                    <option value="Version" ${groupName=='Version'?'selected':''}>Version</option>
                    <option value="Ubicación" ${groupName=='Ubicación'?'selected':''}>Ubicación</option>
                    <option value="Imagen" ${groupName=='Imagen'?'selected':''}>Imagen</option>
                </select>
                ${inputReadonly ? `<input type="hidden" class="attr-group" value="${groupName}">` : ''}
            </td>
            <td>
                <select class="form-control form-control-sm attr-type" ${disabledSelect}>
                    <option value="string" ${type=='string'?'selected':''}>Texto Corto</option>
                    <option value="textarea" ${type=='textarea'?'selected':''}>Texto Largo</option>
                    <option value="number" ${type=='number'?'selected':''}>Número</option>
                    <option value="boolean" ${type=='boolean'?'selected':''}>Booleano</option>
                    <option value="date" ${type=='date'?'selected':''}>Fecha</option>
                    <option value="multiselect" ${type=='multiselect'?'selected':''}>Select</option>
                    <option value="image" ${type=='image'?'selected':''}>Imagen / Archivo</option>
                </select>
                ${inputReadonly ? `<input type="hidden" class="attr-type-hidden" value="${type}">` : ''}
            </td>
            <td class="text-center align-middle">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input attr-req" id="req_${trId}" ${req?'checked':''} ${disabledSelect}>
                    <label class="custom-control-label" for="req_${trId}"></label>
                </div>
            </td>
            <td>
                <input type="text" class="form-control form-control-sm attr-desc" value="${desc}" placeholder="Descripción opcional" ${inputReadonly}>
            </td>
            <td>
                ${isInherited ? 
                    '<i class="fas fa-lock text-white" title="Atributo Heredado (Bloqueado)"></i>' : 
                    `<button type="button" class="btn btn-sm btn-outline-danger" onclick="$(this).closest('tr').remove()"><i class="fas fa-times"></i></button>`
                }
            </td>
        </tr>
    `;
    tbody.append(tr);
}

function loadGlobalAttributes() {
    $.get('api_ci.php?action=get_attributes', function(res) {
        if (res.success) {
            globalAttributes = res.data;
            renderGlobalAttributesDropdownMenu();
        }
    }, 'json');
}

function renderGlobalAttributesDropdownMenu() {
    let menu = $('#global-attrs-menu');
    menu.empty();
    
    // Filtrar para no mostrar los obligatorios de sistema en el menú
    let reserved = ['nombre', 'sigla', 'fecha_creacion', 'ci_unique'];
    let items = globalAttributes.filter(a => !reserved.includes(a.name.toLowerCase()));
    
    if (items.length === 0) {
        menu.append('<span class="dropdown-item text-muted px-3 py-2">No hay atributos globales de plantilla</span>');
        return;
    }
    
    // Obtener atributos actualmente en la tabla del constructor
    let existingNames = [];
    $('#schema-table tbody tr').each(function() {
        let name = $(this).find('.attr-name').val();
        if (name) {
            existingNames.push(name.toLowerCase().trim());
        }
    });

    // 1. Sección: Gestión por Grupo de Atributos
    menu.append('<h6 class="dropdown-header text-uppercase font-weight-bold text-success px-3 py-2"><i class="fas fa-object-group mr-1"></i> Gestión por Grupo de Atributos</h6>');
    
    // Obtener grupos únicos
    let groups = [...new Set(items.map(a => a.group_name || 'General'))].sort();
    
    groups.forEach(grp => {
        let grpItems = items.filter(a => (a.group_name || 'General') === grp);
        let totalInGrp = grpItems.length;
        
        // Contar cuántos de este grupo ya están en el constructor
        let addedInGrp = 0;
        grpItems.forEach(attr => {
            if (existingNames.includes(attr.name.toLowerCase().trim())) {
                addedInGrp++;
            }
        });
        
        let disableAdd = (addedInGrp === totalInGrp) ? 'disabled' : '';
        let disableRemove = (addedInGrp === 0) ? 'disabled' : '';
        
        let grpRow = $(`
            <div class="dropdown-item d-flex justify-content-between align-items-center py-2 px-3" style="background: transparent; cursor: default;">
                <span class="text-dark"><i class="fas fa-folder-open text-warning mr-2"></i> <strong>${grp}</strong> <small class="text-muted">(${addedInGrp}/${totalInGrp})</small></span>
                <div class="btn-group" role="group">
                    <button class="btn btn-sm btn-outline-success py-0 px-2 font-weight-bold" type="button" style="font-size: 0.75rem; border-radius: 4px 0 0 4px;" title="Añadir grupo completo" ${disableAdd}>
                        <i class="fas fa-plus"></i> Añadir
                    </button>
                    <button class="btn btn-sm btn-outline-danger py-0 px-2 font-weight-bold" type="button" style="font-size: 0.75rem; border-radius: 0 4px 4px 0;" title="Quitar grupo completo" ${disableRemove}>
                        <i class="fas fa-trash-alt"></i> Quitar
                    </button>
                </div>
            </div>
        `);
        
        // Click en Añadir Grupo
        grpRow.find('.btn-outline-success').click(function(e) {
            e.stopPropagation();
            if (!$(this).prop('disabled')) {
                addGlobalAttributesByGroup(grp);
            }
        });
        
        // Click en Quitar Grupo
        grpRow.find('.btn-outline-danger').click(function(e) {
            e.stopPropagation();
            if (!$(this).prop('disabled')) {
                removeGlobalAttributesByGroup(grp);
            }
        });
        
        menu.append(grpRow);
    });
    
    menu.append('<div class="dropdown-divider"></div>');
    
    // 2. Sección: Selección Individual (Toggle)
    menu.append('<h6 class="dropdown-header text-uppercase font-weight-bold text-primary px-3 py-2"><i class="fas fa-tag mr-1"></i> Selección Individual (Toggle)</h6>');
    
    items.forEach(attr => {
        let grpName = attr.group_name || 'General';
        let normalizedName = attr.name.toLowerCase().trim();
        let isAdded = existingNames.includes(normalizedName);
        
        let btn = $(`
            <button class="dropdown-item d-flex justify-content-between align-items-center py-2" type="button">
                <span>
                    <i class="${isAdded ? 'fas fa-check-circle text-success' : 'far fa-plus-square text-muted'} mr-2"></i>
                    <span class="${isAdded ? 'text-success font-weight-bold' : ''}">${attr.name}</span>
                </span>
                <span class="badge ${isAdded ? 'badge-success' : 'badge-secondary'}" style="font-size: 0.7rem;">${grpName}</span>
            </button>
        `);
        
        btn.click(function(e) {
            e.stopPropagation(); // Evitar que el menú se cierre
            if (isAdded) {
                removeGlobalAttributeByName(attr.name);
            } else {
                addGlobalAttributeByName(attr);
            }
        });
        
        menu.append(btn);
    });
}

function addGlobalAttributeByName(attr) {
    let isReq = attr.is_required == 1;
    addAttributeRow(attr.name, attr.type, isReq, attr.description || '', true, attr.group_name || 'General');
    
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: `Atributo "${attr.name}" añadido`,
        showConfirmButton: false,
        timer: 1500
    });
    
    renderGlobalAttributesDropdownMenu();
}

function removeGlobalAttributeByName(name) {
    let normalizedTarget = name.toLowerCase().trim();
    let removed = false;
    
    $('#schema-table tbody tr').each(function() {
        let currentInput = $(this).find('.attr-name');
        if (currentInput.length && currentInput.val().toLowerCase().trim() === normalizedTarget) {
            if (!$(this).hasClass('global-attribute-row') && !$(this).hasClass('inherited-attr')) {
                $(this).remove();
                removed = true;
            }
        }
    });
    
    if (removed) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'info',
            title: `Atributo "${name}" quitado`,
            showConfirmButton: false,
            timer: 1500
        });
    }
    
    renderGlobalAttributesDropdownMenu();
}

function addGlobalAttributesByGroup(groupName) {
    let reserved = ['nombre', 'sigla', 'fecha_creacion', 'ci_unique'];
    let itemsToAdd = globalAttributes.filter(a => 
        !reserved.includes(a.name.toLowerCase()) && 
        (a.group_name || 'General').toLowerCase() === groupName.toLowerCase()
    );
    
    let countAdded = 0;
    let existingNames = [];
    $('#schema-table tbody tr').each(function() {
        let name = $(this).find('.attr-name').val();
        if (name) {
            existingNames.push(name.toLowerCase().trim());
        }
    });

    itemsToAdd.forEach(attr => {
        let normalizedName = attr.name.toLowerCase().trim();
        if (!existingNames.includes(normalizedName)) {
            let isReq = attr.is_required == 1;
            addAttributeRow(attr.name, attr.type, isReq, attr.description || '', true, attr.group_name || 'General');
            countAdded++;
        }
    });

    if (countAdded > 0) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: `Se añadieron ${countAdded} atributos del grupo ${groupName}`,
            showConfirmButton: false,
            timer: 2000
        });
    }
    
    renderGlobalAttributesDropdownMenu();
}

function removeGlobalAttributesByGroup(groupName) {
    let reserved = ['nombre', 'sigla', 'fecha_creacion', 'ci_unique'];
    let itemsToRemove = globalAttributes.filter(a => 
        !reserved.includes(a.name.toLowerCase()) && 
        (a.group_name || 'General').toLowerCase() === groupName.toLowerCase()
    );
    
    let countRemoved = 0;
    itemsToRemove.forEach(attr => {
        let normalizedName = attr.name.toLowerCase().trim();
        $('#schema-table tbody tr').each(function() {
            let currentInput = $(this).find('.attr-name');
            if (currentInput.length && currentInput.val().toLowerCase().trim() === normalizedName) {
                if (!$(this).hasClass('global-attribute-row') && !$(this).hasClass('inherited-attr')) {
                    $(this).remove();
                    countRemoved++;
                }
            }
        });
    });
    
    if (countRemoved > 0) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'info',
            title: `Se quitaron ${countRemoved} atributos del grupo ${groupName}`,
            showConfirmButton: false,
            timer: 2000
        });
    }
    
    renderGlobalAttributesDropdownMenu();
}

function buildJsonFromUI() {
    let schema = {
        type: "object",
        properties: {},
        required: []
    };
    
    // Leer filas que NO son las globales obligatorias fijas (que no tienen la clase global-attribute-row) ni las heredadas
    $('#schema-table tbody tr:not(.global-attribute-row):not(.inherited-attr)').each(function() {
        let name = $(this).find('.attr-name').val().trim();
        let type = $(this).find('.attr-type-hidden').length ? $(this).find('.attr-type-hidden').val() : $(this).find('.attr-type').val();
        let groupName = $(this).find('.attr-group').val().trim() || 'General';
        let req = $(this).find('.attr-req').is(':checked');
        let desc = $(this).find('.attr-desc').val().trim();
        
        if (name) {
            name = name.toLowerCase().replace(/[^a-z0-9_]/g, '_');
            schema.properties[name] = { type: type, group: groupName };
            if (desc) schema.properties[name].description = desc;
            
            // Si el tipo es multiselect, buscar opciones en los atributos globales cargados
            if (type === 'multiselect') {
                let globalAttr = globalAttributes.find(a => {
                    let normalizedA = a.name.toLowerCase().replace(/[^a-z0-9_]/g, '_');
                    return normalizedA === name;
                });
                if (globalAttr && globalAttr.multiselect_values) {
                    schema.properties[name].choices = globalAttr.multiselect_values.split(',').map(s => s.trim()).filter(s => s.length > 0);
                }
            }
            
            if (req) schema.required.push(name);
        }
    });
    
    if (schema.required.length === 0) {
        delete schema.required;
    }
    
    $('#cat_schema').val(JSON.stringify(schema, null, 2));
}

function parseJsonToUI(schemaObj, isInherited = false, parentName = '') {
    try {
        if (schemaObj && schemaObj.properties) {
            let requiredFields = schemaObj.required || [];
            
            for (let key in schemaObj.properties) {
                // Saltar si es un atributo obligatorio de sistema
                let reserved = ['nombre', 'sigla', 'fecha_creacion', 'ci_unique'];
                if (reserved.includes(key.toLowerCase())) continue;
                
                let prop = schemaObj.properties[key];
                let isReq = requiredFields.includes(key);
                let type = prop.format === 'date' ? 'date' : 
                           (prop.type === 'string' && prop.maxLength && prop.maxLength > 255) ? 'textarea' : 
                           prop.type;
                if (!type) type = 'string';
                
                let groupName = prop.group || 'General';
                
                // Verificar si es un atributo global
                let isGlobal = globalAttributes.find(a => {
                    let normalizedA = a.name.toLowerCase().replace(/[^a-z0-9_]/g, '_');
                    return normalizedA === key.toLowerCase().replace(/[^a-z0-9_]/g, '_');
                });
                if (isGlobal) groupName = isGlobal.group_name || 'General';
                
                addAttributeRow(key, type, isReq, prop.description || '', !!isGlobal, groupName, isInherited, parentName);
            }
        }
    } catch(e) {
        console.error("Error parseando schema", e);
    }
}

// Lógica de dependencias inter-categoría
function addDependencyRow(targetCatId = '', dependencyType = 'optional', lineColor = '', lineStyle = '', relationshipTypeId = '') {
    let tbody = $('#dependencies-table tbody');
    let rowId = 'dep_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5);
    
    let options = '<option value="">-- Seleccionar Categoría --</option>';
    categories.forEach(c => {
        // No depender de sí misma y mostrar solo categorías principales (parent_id == 45 o null)
        let isMainCategory = (c.parent_id == 45 || !c.parent_id);
        if (c.id != $('#cat_id').val() && isMainCategory) {
            let isSelected = c.id == targetCatId ? 'selected' : '';
            options += `<option value="${c.id}" ${isSelected}>${c.name} (${c.cat_unique || 'CAT'})</option>`;
        }
    });

    let relOptions = '<option value="">-- Seleccionar Relación --</option>';
    relationshipTypes.forEach(rt => {
        let isSel = rt.id == relationshipTypeId ? 'selected' : '';
        relOptions += `<option value="${rt.id}" ${isSel}>${rt.name_direct} / ${rt.name_inverse}</option>`;
    });
    
    let tr = `
        <tr id="${rowId}">
            <td>
                <select class="form-control form-control-sm dep-target" required>
                    ${options}
                </select>
            </td>
            <td>
                <select class="form-control form-control-sm dep-type" required>
                    <option value="optional" ${dependencyType == 'optional' ? 'selected' : ''}>Dato Opcional</option>
                    <option value="required" ${dependencyType == 'required' ? 'selected' : ''}>Dato Requerido</option>
                </select>
            </td>
            <td>
                <select class="form-control form-control-sm dep-relation">
                    ${relOptions}
                </select>
            </td>
            <td>
                <select class="form-control form-control-sm dep-color">
                    <option value="" ${lineColor == '' ? 'selected' : ''}>Por Defecto (Plomo)</option>
                    <option value="green" ${lineColor == 'green' ? 'selected' : ''}>Verde (#059669)</option>
                    <option value="orange" ${lineColor == 'orange' ? 'selected' : ''}>Naranja (#EA580C)</option>
                    <option value="blue" ${lineColor == 'blue' ? 'selected' : ''}>Azul (#2563EB)</option>
                    <option value="grey" ${lineColor == 'grey' ? 'selected' : ''}>Plomo (#94A3B8)</option>
                </select>
            </td>
            <td>
                <select class="form-control form-control-sm dep-style">
                    <option value="" ${lineStyle == '' ? 'selected' : ''}>Por Defecto (Ortogonal)</option>
                    <option value="orthogonal" ${lineStyle == 'orthogonal' ? 'selected' : ''}>Ortogonal (Cuadriculada)</option>
                    <option value="straight" ${lineStyle == 'straight' ? 'selected' : ''}>Recta (Directa)</option>
                    <option value="curved" ${lineStyle == 'curved' ? 'selected' : ''}>Curva (Bézier)</option>
                </select>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="$('#${rowId}').remove()"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `;
    tbody.append(tr);
}

function buildDependenciesJsonFromUI() {
    let deps = [];
    $('#dependencies-table tbody tr').each(function() {
        let targetId = $(this).find('.dep-target').val();
        let depType = $(this).find('.dep-type').val();
        let relationId = $(this).find('.dep-relation').val();
        let lineColor = $(this).find('.dep-color').val();
        let lineStyle = $(this).find('.dep-style').val();
        if (targetId) {
            deps.push({
                target_category_id: parseInt(targetId),
                dependency_type: depType,
                relationship_type_id: relationId ? parseInt(relationId) : null,
                line_color: lineColor,
                line_style: lineStyle
            });
        }
    });
    $('#cat_dependencies').val(JSON.stringify(deps));
}

function deleteCategory() {
    let id = $('#cat_id').val();
    Swal.fire({
        title: '¿Eliminar Categoría?',
        text: 'Esto eliminará la categoría y sus relaciones de dependencia si no tiene CIs asociados.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('api_ci.php', {action: 'delete_category', id: id}, function(res) {
                if (res.success) {
                    Swal.fire('Eliminada', res.message, 'success');
                    loadCategories();
                    closeForm();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json');
        }
    });
}

// Visualización de Relaciones de Categorías con GoJS
let relationsDiagram = null;

const faUnicodeMap = {
    'fa-cube': '\uf1b2',
    'fa-server': '\uf233',
    'fa-globe': '\uf0ac',
    'fa-building': '\uf1ad',
    'fa-network-wired': '\uf6ff',
    'fa-user-shield': '\uf3f4',
    'fa-laptop': '\uf109',
    'fa-mobile-alt': '\uf3cd',
    'fa-database': '\uf1c0',
    'fa-desktop': '\uf108',
    'fa-shield-alt': '\uf3ed',
    'fa-hdd': '\uf0a0',
    'fa-print': '\uf02f',
    'fa-ethernet': '\uf796',
    'fa-wifi': '\uf1eb',
    'fa-key': '\uf084',
    'fa-users': '\uf0c0',
    'fa-envelope': '\uf0e0',
    'fa-phone': '\uf095',
    'fa-cogs': '\uf085',
    'fa-folder': '\uf07b',
    'fa-file-alt': '\uf15c',
    'fa-door-open': '\uf52b',
    'fa-city': '\uf64f',
    'fa-microchip': '\uf2db',
    'fa-cubes': '\uf1b3',
    'fa-cloud': '\uf0c2',
    'fa-code': '\uf121',
    'fa-archive': '\uf187',
    'fa-route': '\uf4d7',
    'fa-plug': '\uf1e6',
    'fa-bolt': '\uf0e7',
    'fa-fan': '\uf863',
    'fa-headset': '\uf590',
    'fa-tools': '\uf7d9',
    'fa-wrench': '\uf0ad',
    'fa-info-circle': '\uf05a',
    'fa-exclamation-triangle': '\uf071',
    'fa-check-circle': '\uf058',
    'fa-clock': '\uf017',
    'fa-map-marker-alt': '\uf3c5',
    'fa-map': '\uf279',
    'fa-compass': '\uf14e',
    'fa-user': '\uf007'
};

function getUnicodeIcon(iconClass) {
    if (!iconClass) return '\uf1b2';
    let parts = iconClass.split(' ');
    let cleanClass = parts[parts.length - 1].trim();
    if (!cleanClass.startsWith('fa-')) {
        cleanClass = 'fa-' + cleanClass;
    }
    return faUnicodeMap[cleanClass] || '\uf1b2';
}

function initRelationsDiagram() {
    if (relationsDiagram) return;
    
    const $ = go.GraphObject.make;
    
    relationsDiagram = $(go.Diagram, "relationsDiagramDiv", {
        "undoManager.isEnabled": false,
        layout: $(go.TreeLayout, { 
            angle: 90, 
            layerSpacing: 50, 
            nodeSpacing: 35,
            alignment: go.TreeLayout.AlignmentCenterChildren
        }),
        initialContentAlignment: go.Spot.Center,
        allowMove: true,
        allowCopy: false,
        allowDelete: false,
        allowInsert: false,
        hasVerticalScrollbar: true,
        hasHorizontalScrollbar: true
    });
    
    // Node template (Org Chart Card style using Horizontal layout)
    relationsDiagram.nodeTemplate = $(go.Node, "Spot",
        { 
            locationSpot: go.Spot.Center,
            click: (e, obj) => {
                showCategoryRelations(obj.data.key);
            }
        },
        new go.Binding("isTreeExpanded", "isExpanded"),
        $(go.Panel, "Auto",
            // Outer shadow / border
            $(go.Shape, "RoundedRectangle", {
                fill: "#FFFFFF",
                stroke: "#CBD5E1",
                strokeWidth: 1,
                parameter1: 6
            }),
            // Horizontal panel instead of Table to avoid measurement bugs
            $(go.Panel, "Horizontal",
                { defaultAlignment: go.Spot.Center, margin: 0 },
                // Left accent bar
                $(go.Shape, "Rectangle", {
                    width: 6,
                    stretch: go.GraphObject.Fill,
                    stroke: null, fill: "#bdc3c7"
                },
                new go.Binding("fill", "category", cat => {
                    if (cat === 'central') return '#0EA5E9'; // cyan
                    if (cat === 'parent') return '#64748B'; // slate
                    if (cat === 'child') return '#0D9488'; // teal
                    return '#D97706'; // amber
                })),
                
                // Icon and Text container
                $(go.Panel, "Horizontal", { margin: new go.Margin(10, 16, 10, 12) },
                    // Icon
                    $(go.TextBlock, {
                        font: '900 15pt "Font Awesome 5 Free"',
                        stroke: "#334155",
                        margin: new go.Margin(0, 12, 0, 0),
                        width: 24,
                        textAlign: "center"
                    },
                    new go.Binding("text", "icon", getUnicodeIcon),
                    new go.Binding("stroke", "category", cat => {
                        if (cat === 'central') return '#0284C7';
                        if (cat === 'parent') return '#475569';
                        if (cat === 'child') return '#0F766E';
                        return '#B45309';
                    })),
                    // Text blocks
                    $(go.Panel, "Vertical", { defaultAlignment: go.Spot.Left },
                        $(go.TextBlock, {
                            font: "bold 10pt sans-serif",
                            stroke: "#1E293B",
                            maxSize: new go.Size(160, NaN),
                            wrap: go.TextBlock.WrapFit
                        },
                        new go.Binding("text", "name")),
                        $(go.TextBlock, {
                            font: "7.5pt monospace",
                            stroke: "#64748B",
                            margin: new go.Margin(2, 0, 0, 0)
                        },
                        new go.Binding("text", "code"))
                    )
                )
            )
        ),
        // Expander Button
        $("TreeExpanderButton", {
            alignment: go.Spot.Bottom,
            alignmentFocus: go.Spot.Top,
            "ButtonBorder.figure": "Circle",
            "ButtonBorder.fill": "#FFFFFF",
            "ButtonBorder.stroke": "#CBD5E1",
            "ButtonBorder.strokeWidth": 1.5,
            "_buttonFillOver": "#F1F5F9",
            "_buttonStrokeOver": "#94A3B8"
        })
    );
    
    // Link template (Customizable dynamic lines)
    relationsDiagram.linkTemplate = $(go.Link,
        { 
            corner: 10
        },
        new go.Binding("routing", "", link => {
            if (link.type === 'hierarchy') return go.Link.Orthogonal;
            if (link.lineStyle === 'straight') return go.Link.Normal;
            if (link.lineStyle === 'curved') return go.Link.Normal;
            return go.Link.Orthogonal; // Default is Orthogonal (Cuadriculada)
        }),
        new go.Binding("curve", "", link => {
            if (link.type === 'hierarchy') return go.Link.None;
            if (link.lineStyle === 'curved') return go.Link.Bezier;
            return go.Link.None; // Default is None
        }),
        new go.Binding("isTreeLink", "type", t => t === 'hierarchy'),
        $(go.Shape, 
            { strokeWidth: 1.5 },
            new go.Binding("stroke", "", link => {
                if (link.type === 'hierarchy') return '#059669'; // hierarchy is emerald green
                
                let c = link.lineColor;
                if (c === 'green') return '#059669';
                if (c === 'orange') return '#EA580C';
                if (c === 'blue') return '#2563EB';
                if (c === 'grey') return '#94A3B8';
                return '#94A3B8'; // default plomo
            }),
            new go.Binding("strokeDashArray", "type", t => t === 'hierarchy' ? null : [4, 4]), // solid for hierarchy, dashed for dependencies
            new go.Binding("strokeWidth", "type", t => (t === 'required' || t === 'hierarchy') ? 2 : 1.5)
        ),
        $(go.Shape, 
            { toArrow: "Standard", stroke: null, scale: 1.1 },
            new go.Binding("fill", "", link => {
                if (link.type === 'hierarchy') return '#059669';
                
                let c = link.lineColor;
                if (c === 'green') return '#059669';
                if (c === 'orange') return '#EA580C';
                if (c === 'blue') return '#2563EB';
                if (c === 'grey') return '#94A3B8';
                return '#94A3B8'; // default plomo
            })
        ),
        $(go.Panel, "Auto",
            $(go.Shape, "RoundedRectangle", 
                { fill: "#ffffff", stroke: null },
                new go.Binding("fill", "", link => {
                    if (link.type === 'hierarchy') return '#ECFDF5';
                    
                    let c = link.lineColor;
                    if (c === 'green') return '#ECFDF5';
                    if (c === 'orange') return '#FFF7ED';
                    if (c === 'blue') return '#EFF6FF';
                    if (c === 'grey') return '#F8FAFC';
                    return '#F8FAFC';
                })
            ),
            $(go.TextBlock, 
                { 
                    margin: 3, 
                    font: "7.5pt sans-serif"
                },
                new go.Binding("text", "label", lbl => {
                    if (lbl === 'Hijo' || lbl === 'Padre') return 'Requiere'; // Display 'Requiere' for hierarchy links
                    return lbl;
                }),
                new go.Binding("stroke", "", link => {
                    if (link.type === 'hierarchy') return '#047857';
                    
                    let c = link.lineColor;
                    if (c === 'green') return '#047857';
                    if (c === 'orange') return '#C2410C';
                    if (c === 'blue') return '#1D4ED8';
                    if (c === 'grey') return '#475569';
                    return '#475569';
                })
            )
        )
    );
}

function zoomInDiagram() {
    if (relationsDiagram) relationsDiagram.commandHandler.increaseZoom();
}

function zoomOutDiagram() {
    if (relationsDiagram) relationsDiagram.commandHandler.decreaseZoom();
}

function resetDiagram() {
    if (relationsDiagram) {
        relationsDiagram.zoomToFit();
        relationsDiagram.contentAlignment = go.Spot.Center;
    }
}

function changeDiagramLayout(layoutType) {
    if (!relationsDiagram) return;
    
    relationsDiagram.startTransaction("changeLayout");
    const $ = go.GraphObject.make;
    
    if (layoutType === 'tree') {
        relationsDiagram.layout = $(go.TreeLayout, {
            angle: 90,
            layerSpacing: 50,
            nodeSpacing: 35,
            alignment: go.TreeLayout.AlignmentCenterChildren
        });
    } else if (layoutType === 'radial') {
        relationsDiagram.layout = $(go.ForceDirectedLayout, {
            defaultSpringLength: 80,
            defaultElectricalCharge: -300
        });
    } else if (layoutType === 'layered') {
        relationsDiagram.layout = $(go.LayeredDigraphLayout, {
            direction: 90,
            layerSpacing: 60,
            columnSpacing: 35,
            setsPortSpots: false
        });
    } else if (layoutType === 'grid') {
        relationsDiagram.layout = $(go.GridLayout, {
            wrappingColumn: 4,
            spacing: new go.Size(40, 40)
        });
    }
    
    relationsDiagram.commitTransaction("changeLayout");
}

function toggleDependencies(visible) {
    if (!relationsDiagram) return;
    relationsDiagram.startTransaction("toggleDeps");
    relationsDiagram.links.each(link => {
        if (link.data.type !== 'hierarchy') {
            link.visible = visible;
        }
    });
    relationsDiagram.commitTransaction("toggleDeps");
}

function showCategoryRelations(categoryId) {
    if (categoryId == 45) {
        showGlobalCategoryRelations(false);
        return;
    }
    let cat = categories.find(c => c.id == categoryId);
    if (!cat) return;
    
    // Set title
    $('#modal-category-name').text(`${cat.name} (${cat.cat_unique || 'CAT-' + cat.id})`);
    
    // Ensure Diagram is initialized
    initRelationsDiagram();
    
    // Reset layout selection and dependencies checkbox
    $('#diagram-layout-select').val('tree');
    $('#toggle-dependencies-chk').prop('checked', true);
    changeDiagramLayout('tree');
    
    // Clear list areas
    let textParentsList = $('#text-parents-list');
    let textChildrenList = $('#text-children-list');
    let textRequiresList = $('#text-requires-list');
    let textDependentsList = $('#text-dependents-list');
    
    textParentsList.empty();
    textChildrenList.empty();
    textRequiresList.empty();
    textDependentsList.empty();
    
    // Gather Node and Link Data
    let nodeDataArray = [];
    let linkDataArray = [];
    let processedNodeIds = new Set();
    
    function addNode(c, nodeType) {
        if (processedNodeIds.has(c.id)) {
            // Update node category if central
            if (nodeType === 'central') {
                let existing = nodeDataArray.find(n => n.key === c.id);
                if (existing) {
                    existing.category = 'central';
                    existing.isExpanded = true;
                }
            }
            return;
        }
        processedNodeIds.add(c.id);
        
        let icon = c.icon || 'fa-cube';
        nodeDataArray.push({
            key: c.id,
            name: c.name,
            code: c.cat_unique || 'CAT-' + c.id,
            icon: icon,
            category: nodeType,
            isExpanded: (c.id == categoryId) // root starts expanded
        });
    }
    
    // 1. Central Node
    addNode(cat, 'central');
    
    // 2. Parents hierarchy
    let parentChain = [];
    let currentParentId = cat.parent_id;
    let loopVisited = {};
    while (currentParentId) {
        if (loopVisited[currentParentId]) break;
        loopVisited[currentParentId] = true;
        
        let pCat = categories.find(c => c.id == currentParentId);
        if (pCat) {
            parentChain.unshift(pCat); // oldest parent first
            addNode(pCat, 'parent');
            currentParentId = pCat.parent_id;
        } else {
            break;
        }
    }
    
    // Link parents hierarchy
    if (parentChain.length > 0) {
        let parentNames = parentChain.map(p => `<span class="badge badge-secondary" style="cursor:pointer;" onclick="showCategoryRelations(${p.id})">${p.name}</span>`).join(' <i class="fas fa-chevron-right mx-1 text-muted"></i> ');
        textParentsList.html(parentNames);
        
        // Link oldest parent to next parent, down to central node
        let chain = [...parentChain, cat];
        for (let i = 0; i < chain.length - 1; i++) {
            linkDataArray.push({
                from: chain[i].id,
                to: chain[i+1].id,
                label: 'Padre',
                type: 'hierarchy'
            });
        }
    } else {
        textParentsList.html('<span class="text-muted">Ninguno (Es categoría raíz)</span>');
    }
    
    // 3. Children (Subcategories)
    let children = categories.filter(c => c.parent_id == cat.id);
    if (children.length > 0) {
        let childrenHtml = children.map(c => `<span class="badge badge-info mr-1 mb-1" style="cursor:pointer;" onclick="showCategoryRelations(${c.id})">${c.name}</span>`).join('');
        textChildrenList.html(childrenHtml);
        
        children.forEach(child => {
            addNode(child, 'child');
            linkDataArray.push({
                from: cat.id,
                to: child.id,
                label: 'Hijo',
                type: 'hierarchy'
            });
        });
    } else {
        textChildrenList.html('<span class="text-muted">Ninguna subcategoría directa</span>');
    }
    
    // 4. Inter-Category Dependencies (what this category requires)
    let deps = cat.dependencies || [];
    if (deps.length > 0) {
        let requiresHtml = [];
        deps.forEach(d => {
            let target = categories.find(c => c.id == d.target_category_id);
            if (target) {
                addNode(target, 'dependency');
                let relTypeObj = relationshipTypes.find(rt => rt.id == d.relationship_type_id);
                let linkLabel = relTypeObj ? relTypeObj.name_direct : (d.dependency_type === 'required' ? 'Requiere' : 'Asociado');
                linkDataArray.push({
                    from: cat.id,
                    to: target.id,
                    label: linkLabel,
                    type: d.dependency_type,
                    lineColor: d.line_color,
                    lineStyle: d.line_style
                });
                
                let badgeClass = d.dependency_type === 'required' ? 'badge-success' : 'badge-outline-success';
                requiresHtml.push(`<div class="mb-1"><span class="badge ${badgeClass} mr-1">${d.dependency_type === 'required' ? 'Obligatorio' : 'Opcional'}</span> <a href="javascript:void(0)" onclick="showCategoryRelations(${target.id})">${target.name}</a></div>`);
            }
        });
        if (requiresHtml.length > 0) {
            textRequiresList.html(requiresHtml.join(''));
        } else {
            textRequiresList.html('<span class="text-muted">Ninguna dependencia inter-categoría</span>');
        }
    } else {
        textRequiresList.html('<span class="text-muted">Ninguna dependencia inter-categoría</span>');
    }
    
    // 5. Dependent Categories (other categories that require/associate this category)
    let dependentsHtml = [];
    categories.forEach(otherCat => {
        if (otherCat.id == cat.id) return;
        let otherDeps = otherCat.dependencies || [];
        otherDeps.forEach(d => {
            if (d.target_category_id == cat.id) {
                addNode(otherCat, 'dependent');
                let relTypeObj = relationshipTypes.find(rt => rt.id == d.relationship_type_id);
                let linkLabel = relTypeObj ? relTypeObj.name_direct : (d.dependency_type === 'required' ? 'Requiere' : 'Asociado');
                linkDataArray.push({
                    from: otherCat.id,
                    to: cat.id,
                    label: linkLabel,
                    type: d.dependency_type,
                    lineColor: d.line_color,
                    lineStyle: d.line_style
                });
                
                let badgeClass = d.dependency_type === 'required' ? 'badge-danger font-weight-bold' : 'badge-outline-danger';
                dependentsHtml.push(`<div class="mb-1"><span class="badge ${badgeClass} mr-1">${d.dependency_type === 'required' ? 'Requerido' : 'Opcional'}</span> <a href="javascript:void(0)" onclick="showCategoryRelations(${otherCat.id})">${otherCat.name}</a></div>`);
            }
        });
    });
    
    if (dependentsHtml.length > 0) {
        textDependentsList.html(dependentsHtml.join(''));
    } else {
        textDependentsList.html('<span class="text-muted">Ninguna categoría depende de esta</span>');
    }
    
    // Set model in diagram
    relationsDiagram.model = new go.GraphLinksModel(nodeDataArray, linkDataArray);
    
    // Apply dependency toggle state
    toggleDependencies($('#toggle-dependencies-chk').is(':checked'));
    
    // Show Modal
    $('#relationsModal').modal('show');
    
    // Delay zoomToFit slightly to ensure DOM has rendered modal content size
    setTimeout(() => {
        relationsDiagram.zoomToFit();
        relationsDiagram.contentAlignment = go.Spot.Center;
    }, 250);
}

function showGlobalCategoryRelations(isGlobal = true) {
    // Set title
    let title = isGlobal ? 'Mapa de Relaciones Global de Categorías' : 'Árbol de Relaciones: 00 Cliente';
    $('#modal-category-name').text(title);
    
    // Ensure Diagram is initialized
    initRelationsDiagram();
    
    // Reset layout selection and dependencies checkbox
    $('#diagram-layout-select').val('tree');
    $('#toggle-dependencies-chk').prop('checked', true);
    changeDiagramLayout('tree');
    
    // Clear list areas and show global stats
    let textParentsList = $('#text-parents-list');
    let textChildrenList = $('#text-children-list');
    let textRequiresList = $('#text-requires-list');
    let textDependentsList = $('#text-dependents-list');
    
    textParentsList.html(`<div class="mb-1"><i class="fas fa-layer-group text-primary mr-1"></i> Total Categorías: <strong>${categories.length}</strong></div>`);
    
    let rootCats = categories.filter(c => !c.parent_id);
    textChildrenList.html(`<div class="mb-1"><i class="fas fa-folder text-info mr-1"></i> Raíces: <strong>${rootCats.length}</strong></div>`);
    
    let totalDeps = 0;
    categories.forEach(c => totalDeps += (c.dependencies || []).length);
    textRequiresList.html(`<div class="mb-1"><i class="fas fa-link text-success mr-1"></i> Dependencias: <strong>${totalDeps}</strong></div>`);
    
    textDependentsList.html('<small class="text-muted">Mostrando el modelo de datos completo del CMDB.</small>');
    
    // Gather Node and Link Data for ALL categories
    let nodeDataArray = [];
    let linkDataArray = [];
    
    categories.forEach(c => {
        let nodeType = 'dependency'; // default
        if (c.id == 45) {
            nodeType = 'central';
        } else if (!c.parent_id) {
            nodeType = 'parent';
        } else {
            // Check if it is a descendant of 01 Ubicaciones (id 37)
            let isLocation = false;
            let current = c;
            let visited = {};
            while (current.parent_id && !visited[current.parent_id]) {
                visited[current.parent_id] = true;
                if (current.parent_id == 37) {
                    isLocation = true;
                    break;
                }
                let p = categories.find(parent => parent.id == current.parent_id);
                if (p) {
                    current = p;
                } else {
                    break;
                }
            }
            if (isLocation) {
                nodeType = 'child';
            }
        }
        
        let icon = c.icon || 'fa-cube';
        nodeDataArray.push({
            key: c.id,
            name: c.name,
            code: c.cat_unique || 'CAT-' + c.id,
            icon: icon,
            category: nodeType,
            isExpanded: (c.id == 45) // only root 00 Cliente is expanded initially
        });
        
        // Parent-child hierarchy link
        if (c.parent_id) {
            linkDataArray.push({
                from: c.parent_id,
                to: c.id,
                label: 'Hijo',
                type: 'hierarchy'
            });
        }
        
        // Cross-category dependencies
        let deps = c.dependencies || [];
        deps.forEach(d => {
            let relTypeObj = relationshipTypes.find(rt => rt.id == d.relationship_type_id);
            let linkLabel = relTypeObj ? relTypeObj.name_direct : (d.dependency_type === 'required' ? 'Requiere' : 'Asociado');
            linkDataArray.push({
                from: c.id,
                to: d.target_category_id,
                label: linkLabel,
                type: d.dependency_type,
                lineColor: d.line_color,
                lineStyle: d.line_style
            });
        });
    });
    
    // Set model in diagram
    relationsDiagram.model = new go.GraphLinksModel(nodeDataArray, linkDataArray);
    
    // Apply dependency toggle state
    toggleDependencies($('#toggle-dependencies-chk').is(':checked'));
    
    // Show Modal
    $('#relationsModal').modal('show');
    
    // Delay zoomToFit slightly to ensure DOM has rendered modal content size
    setTimeout(() => {
        relationsDiagram.zoomToFit();
        relationsDiagram.contentAlignment = go.Spot.Center;
    }, 250);
}

</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
