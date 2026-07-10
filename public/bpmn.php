<?php
/**
 * Módulo de Procesos BPMN - CMDB VILASECA
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/permissions_helper.php';

// Validar login
require_login();
if (!has_module_access('diagrams')) {
    header("Location: dashboard.php");
    exit();
}

$page_title = "Procesos de Negocio (BPMN)";
$hide_content_header = true;
include 'partials/header.php';
?>

<!-- bpmn-js styles (loaded locally to prevent CORS and network errors) -->
<link rel="stylesheet" href="assets/css/bpmn-diagram-js.css" />
<link rel="stylesheet" href="assets/css/bpmn-embedded.css" />
<link rel="stylesheet" href="assets/css/bpmn-js.css" />

<style>
/* Modern Styling */
.bpmn-container {
    padding: 0.5rem;
}

.bpmn-card-item {
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    cursor: pointer;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
}

.bpmn-card-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(255, 92, 5, 0.15);
    border-color: var(--sonda-orange);
}

/* Modeler Canvas styling */
#bpmn-canvas-wrapper {
    width: 100%;
    height: 720px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background-color: #fff;
    position: relative;
    overflow: hidden;
}

#bpmn-canvas {
    width: 100%;
    height: 100%;
}

/* Uniform Toolbar Buttons */
.btn-tool-uniform {
    height: 28px !important;
    font-size: 0.78rem !important;
    padding: 2px 8px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    line-height: 1 !important;
}

/* Palette on the Left */
.djs-palette {
    left: 20px !important;
    right: auto !important;
    top: 80px !important;
}

.nav-tabs .nav-link {
    font-weight: 600;
    border: none;
    color: var(--text-color);
    opacity: 0.8;
}

.nav-tabs .nav-link.active {
    color: var(--sonda-orange) !important;
    border-bottom: 3px solid var(--sonda-orange);
    background-color: transparent !important;
    opacity: 1;
}

/* Float Controls for Canvas */
.canvas-float-controls {
    position: absolute;
    top: 15px;
    right: 15px;
    z-index: 100;
    background: rgba(0, 42, 84, 0.9);
    padding: 6px;
    border-radius: 8px;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    display: flex;
    gap: 6px;
}

.canvas-float-controls .btn {
    color: #fff;
    border: none;
    background: transparent;
    padding: 5px 10px;
}

.canvas-float-controls .btn:hover {
    background: var(--sonda-orange);
    color: #fff;
}

/* Hide Camunda brand logo to keep layout clean */
.bjs-powered-by {
    display: none !important;
}

/* Fullscreen styling */
#editor-tab-content:fullscreen {
    width: 100vw !important;
    height: 100vh !important;
    border: none !important;
    border-radius: 0 !important;
    background-color: #f8f9fa !important;
    padding: 15px !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
}
#editor-tab-content:fullscreen #bpmn-canvas-wrapper {
    flex: 1 !important;
    height: auto !important;
}
#editor-tab-content:fullscreen #bpmn-canvas {
    height: calc(100vh - 120px) !important;
}

/* Windows Style Window Controls */
.win-btn-min, .win-btn-close {
    transition: background-color 0.15s, color 0.15s;
    background: transparent !important;
}
.win-btn-min:hover {
    background-color: rgba(0, 0, 0, 0.1) !important;
    color: #000000 !important;
}
.win-btn-close:hover {
    background-color: #e81123 !important;
    color: #ffffff !important;
}

/* Context pad FontAwesome entry integration */
.djs-context-pad .entry.fas {
    font-family: "Font Awesome 5 Free" !important;
    font-weight: 900 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 14px !important;
    line-height: 1 !important;
}
.djs-context-pad .entry.fas:before {
    margin: 0 !important;
}

/* Custom brush cursor */
.brush-cursor-active .djs-container, 
.brush-cursor-active .djs-element, 
.brush-cursor-active .djs-parent {
    cursor: cell !important;
}
</style>

<div class="bpmn-container">    <div class="card card-orange card-outline card-outline-tabs shadow-sm">
        <div class="card-header p-0 border-bottom-0 d-flex justify-content-between align-items-center flex-wrap" style="background: #ffffff; padding: 4px 10px !important;">
            <ul class="nav nav-tabs border-bottom-0" id="bpmn-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="list-tab" data-toggle="tab" href="#list-tab-content" role="tab" aria-controls="list-tab-content" aria-selected="true" style="padding: 8px 12px; font-size: 0.85rem;">
                        <i class="fas fa-sitemap mr-1 text-info"></i>Diagramas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="editor-tab" data-toggle="tab" href="#editor-tab-content" role="tab" aria-controls="editor-tab-content" aria-selected="false" style="padding: 8px 12px; font-size: 0.85rem;">
                        <i class="fas fa-pencil-ruler mr-1 text-warning"></i>Modelador Visual
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="xml-tab" data-toggle="tab" href="#xml-tab-content" role="tab" aria-controls="xml-tab-content" aria-selected="false" style="padding: 8px 12px; font-size: 0.85rem;">
                        <i class="fas fa-code mr-1 text-secondary"></i>XML
                    </a>
                </li>
            </ul>

            <!-- Compact Title and Desc integrated directly on the tab level header (right side) -->
            <div id="integrated-title-bar" class="d-none d-flex flex-column align-items-end mr-3" style="gap: 2px; max-width: 450px; min-width: 250px;">
                <input type="hidden" id="diagram-id" value="0">
                <div class="d-flex align-items-center" style="gap: 4px; width: 100%;">
                    <i class="fas fa-project-diagram text-orange" style="font-size: 0.8rem;"></i>
                    <input type="text" id="diagram-title" class="form-control form-control-sm border-0 font-weight-bold p-0 text-right" style="font-size: 0.88rem; background: transparent; box-shadow: none; height: auto;" placeholder="Título del Proceso *">
                </div>
                <input type="text" id="diagram-desc" class="form-control form-control-sm border-0 text-muted p-0 text-right" style="font-size: 0.75rem; background: transparent; box-shadow: none; height: auto;" placeholder="Descripción / Notas">
            </div>
        </div>

        <div class="card-body">
            <div class="tab-content" id="bpmn-tabs-tabContent">

                <!-- TAB 1: SAVED DIAGRAMS CATALOG -->
                <div class="tab-pane fade show active" id="list-tab-content" role="tabpanel" aria-labelledby="list-tab">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="input-group" style="max-width: 400px;">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-transparent border-right-0"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" id="search-diagram" class="form-control border-left-0" placeholder="Buscar procesos por título o descripción...">
                        </div>
                        <button class="btn btn-success font-weight-bold" onclick="createNewDiagram()">
                            <i class="fas fa-plus mr-2"></i>Crear Nuevo Proceso
                        </button>
                    </div>

                    <!-- Diagram cards container -->
                    <div class="row" id="diagrams-grid-container">
                        <!-- Dynamic content -->
                    </div>
                </div>

                <!-- TAB 2: BPMN MODELER WORKSPACE (FULL WIDTH) -->
                <div class="tab-pane fade" id="editor-tab-content" role="tabpanel" aria-labelledby="editor-tab">

                    <!-- History Banner Alert -->
                    <div class="alert alert-warning d-none mb-3" id="history-view-banner" style="font-size: 0.9rem;">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div>
                                <i class="fas fa-exclamation-triangle mr-2 text-dark"></i>
                                <span>Estás viendo una <strong>versión del historial</strong> (Guardada el <span id="history-date-span"></span>).</span>
                            </div>
                            <div>
                                <button class="btn btn-xs btn-primary font-weight-bold mr-1" onclick="restoreActiveVersion()"><i class="fas fa-check mr-1"></i>Restaurar como Actual</button>
                                <button class="btn btn-xs btn-secondary font-weight-bold" onclick="exitHistoryView()"><i class="fas fa-times mr-1"></i>Salir del Historial</button>
                            </div>
                        </div>
                    </div>

                    <!-- Top Compact Unified Toolbar (Only Buttons) -->
                    <div class="card shadow-sm mb-2 border" style="border-radius: 4px; margin-top: -2px;">
                        <div class="card-body p-1 d-flex justify-content-between align-items-center flex-wrap" style="gap: 6px; background-color: #f8f9fa;">
                            <div class="d-flex align-items-center flex-wrap" style="gap: 6px;">
                                <span id="save-status" class="badge badge-secondary px-2 py-2 btn-tool-uniform" style="font-size:0.75rem;">No Guardado</span>

                                <!-- History Dropdown -->
                                <div class="dropdown d-none" id="history-dropdown-wrapper">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle btn-tool-uniform" type="button" id="historyDropdownBtn" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fas fa-history mr-1"></i> Historial
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right shadow-lg" id="history-items-container" aria-labelledby="historyDropdownBtn" style="max-height: 250px; overflow-y: auto; width: 280px;">
                                        <!-- Dynamic history items -->
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-success font-weight-bold btn-tool-uniform" onclick="resetEditorAndCreate()" title="Nuevo Proceso"><i class="fas fa-plus mr-1"></i>Nuevo</button>
                                    <button class="btn btn-sm btn-primary font-weight-bold btn-tool-uniform" onclick="saveDiagram()" title="Guardar cambios"><i class="fas fa-save mr-1"></i>Guardar</button>
                                    <button class="btn btn-sm btn-outline-danger d-none font-weight-bold btn-tool-uniform" id="delete-diagram-btn" onclick="deleteDiagram()" title="Eliminar"><i class="fas fa-trash-alt mr-1"></i>Eliminar</button>
                                </div>

                                <!-- Zoom & View -->
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-secondary btn-tool-uniform" onclick="zoomOut()" title="Alejar (Zoom -)"><i class="fas fa-search-minus mr-1"></i>Alejar</button>
                                    <button class="btn btn-sm btn-outline-secondary btn-tool-uniform" onclick="zoomIn()" title="Acercar (Zoom +)"><i class="fas fa-search-plus mr-1"></i>Acercar</button>
                                    <button class="btn btn-sm btn-outline-secondary btn-tool-uniform" onclick="resetZoom()" title="Centrar"><i class="fas fa-sync-alt mr-1"></i>Centrar</button>
                                    <button class="btn btn-sm btn-outline-secondary btn-tool-uniform" onclick="toggleFullscreen()" title="Pantalla Completa"><i class="fas fa-expand mr-1"></i>Pantalla</button>
                                    <button class="btn btn-sm btn-outline-secondary btn-tool-uniform" onclick="$('.djs-palette').toggleClass('d-none')" title="Mostrar/Ocultar Paleta"><i class="fas fa-toolbox mr-1"></i>Paleta</button>
                                </div>

                                <!-- Edit / Selection Tools -->
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-secondary btn-tool-uniform" onclick="activateLassoTool(event)" title="Lazo de Selección (Selección Múltiple - Mantén Ctrl o presiona L)"><i class="fas fa-mouse-pointer mr-1"></i>Lazo</button>
                                    <button class="btn btn-sm btn-outline-secondary btn-tool-uniform" onclick="activateSpaceTool(event)" title="Herramienta Espacio (Desplazar partes del diagrama - S)"><i class="fas fa-arrows-alt mr-1"></i>Espacio</button>
                                    <button class="btn btn-sm btn-outline-secondary btn-tool-uniform" onclick="activateHandTool(event)" title="Herramienta Mano (Desplazar lienzo - H)"><i class="fas fa-hand-paper mr-1"></i>Mano</button>
                                    <button class="btn btn-sm btn-outline-secondary font-weight-bold btn-tool-uniform" onclick="togglePropertiesPanel()" id="btn-toggle-prop" title="Propiedades"><i class="fas fa-sliders-h mr-1"></i>Propiedades</button>
                                </div>
                                    
                                    <!-- Brush (Brocha) Dropdown & Activation Button -->
                                    <div class="dropdown d-inline-block" id="brush-dropdown-wrapper">
                                        <button class="btn btn-sm btn-outline-secondary btn-tool-uniform dropdown-toggle font-weight-bold" type="button" id="btn-brush-tool" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Herramienta Brocha (Seleccionar color y pintar haciendo clic)">
                                            <i class="fas fa-paint-brush mr-1" id="brush-icon-indicator" style="color: #FF5C05;"></i> Brocha
                                        </button>
                                        <div class="dropdown-menu p-3 shadow-lg" aria-labelledby="btn-brush-tool" style="width: 240px; border-radius: 8px; z-index: 1050;">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="font-weight-bold text-muted" style="font-size: 0.8rem;">Modo Brocha</span>
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input" id="brush-mode-toggle" onchange="toggleBrushMode(this.checked)">
                                                    <label class="custom-control-label" for="brush-mode-toggle"></label>
                                                </div>
                                            </div>
                                            <hr class="my-2">
                                            <span class="dropdown-header font-weight-bold px-0 pb-1 pt-0" style="font-size: 0.75rem; color: var(--text-color);">Sólidos</span>
                                            <div class="d-flex flex-wrap mb-2" id="brush-picker-solid" style="gap: 8px;">
                                                <button class="color-badge active" data-stroke="#000000" data-fill="#ffffff" style="background:#ffffff; border: 1.5px solid #000000; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Predeterminado"></button>
                                                <button class="color-badge" data-stroke="#002A54" data-fill="#E6F0FA" style="background:#E6F0FA; border: 1.5px solid #002A54; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Azul Sonda"></button>
                                                <button class="color-badge" data-stroke="#FF5C05" data-fill="#FFEFE6" style="background:#FFEFE6; border: 1.5px solid #FF5C05; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Naranja Sonda"></button>
                                                <button class="color-badge" data-stroke="#28a745" data-fill="#EBF7EE" style="background:#EBF7EE; border: 1.5px solid #28a745; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Verde Éxito"></button>
                                                <button class="color-badge" data-stroke="#dc3545" data-fill="#FDF2F3" style="background:#FDF2F3; border: 1.5px solid #dc3545; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Rojo Peligro"></button>
                                                <button class="color-badge" data-stroke="#ffc107" data-fill="#FFFDF2" style="background:#FFFDF2; border: 1.5px solid #ffc107; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Amarillo Alerta"></button>
                                                <button class="color-badge" data-stroke="#6f42c1" data-fill="#F6F2FC" style="background:#F6F2FC; border: 1.5px solid #6f42c1; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Púrpura"></button>
                                            </div>
                                            <span class="dropdown-header font-weight-bold px-0 pb-1 pt-1" style="font-size: 0.75rem; color: var(--text-color);">Degradados</span>
                                            <div class="d-flex flex-wrap" id="brush-picker-gradient" style="gap: 8px;">
                                                <button class="color-badge" data-stroke="#002A54" data-fill="url(#gradient-sonda-blue)" style="background: linear-gradient(135deg, #E6F0FA 0%, #B3D4F5 100%); border: 1.5px solid #002A54; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Degradado Azul"></button>
                                                <button class="color-badge" data-stroke="#FF5C05" data-fill="url(#gradient-sonda-orange)" style="background: linear-gradient(135deg, #FFEFE6 0%, #FFD9C6 100%); border: 1.5px solid #FF5C05; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Degradado Naranja"></button>
                                                <button class="color-badge" data-stroke="#28a745" data-fill="url(#gradient-success-green)" style="background: linear-gradient(135deg, #EBF7EE 0%, #C7E9D0 100%); border: 1.5px solid #28a745; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Degradado Verde"></button>
                                                <button class="color-badge" data-stroke="#dc3545" data-fill="url(#gradient-danger-red)" style="background: linear-gradient(135deg, #FDF2F3 0%, #F9D6D9 100%); border: 1.5px solid #dc3545; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Degradado Rojo"></button>
                                                <button class="color-badge" data-stroke="#ffc107" data-fill="url(#gradient-warning-yellow)" style="background: linear-gradient(135deg, #FFFDF2 0%, #FFECA8 100%); border: 1.5px solid #ffc107; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Degradado Amarillo"></button>
                                                <button class="color-badge" data-stroke="#6f42c1" data-fill="url(#gradient-purple)" style="background: linear-gradient(135deg, #F6F2FC 0%, #E3D5F7 100%); border: 1.5px solid #6f42c1; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Degradado Púrpura"></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Exports -->
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-info font-weight-bold btn-tool-uniform" onclick="exportXMLFile()" title="Descargar XML"><i class="fas fa-download mr-1"></i>XML</button>
                                    <button class="btn btn-sm btn-outline-primary font-weight-bold btn-tool-uniform" onclick="exportSVGFile()" title="Exportar SVG"><i class="fas fa-file-image mr-1"></i>SVG</button>
                                    <button class="btn btn-sm btn-outline-danger font-weight-bold btn-tool-uniform" onclick="exportPDFFile()" title="Exportar PDF"><i class="fas fa-file-pdf mr-1"></i>PDF</button>
                                </div>
                            </div>
                        </div>

                    <!-- Visual Modeler Canvas & Properties Row -->
                    <div class="row">
                        <!-- Canvas Column -->
                        <div id="bpmn-canvas-col" class="col-md-12">
                            <div id="bpmn-canvas-wrapper" class="position-relative">
                                <div id="bpmn-canvas" style="height: 720px;"></div>

                                <!-- Floating Windows-Style Properties Panel -->
                                <div id="bpmn-properties-panel" class="card shadow-sm border border-secondary d-none" style="position: absolute; top: 20px; right: 20px; width: 350px; max-height: 550px; display: flex; flex-direction: column; margin-bottom: 0; background: #ffffff; border-radius: 4px; box-shadow: 0 4px 15px rgba(0,0,0,0.2) !important; z-index: 1000;">
                                    <div class="card-header text-white d-flex justify-content-between align-items-center py-2 px-3 border-bottom" style="user-select: none; background-color: #002A54 !important; cursor: move;">
                                        <div class="d-flex align-items-center" style="gap: 6px; font-size: 0.85rem;">
                                            <i class="fas fa-sliders-h"></i>
                                            <span class="font-weight-bold">Propiedades</span>
                                        </div>
                                        <div class="d-flex align-items-center" style="gap: 4px; margin-right: -8px;">
                                            <button type="button" class="btn btn-link text-white p-0 d-flex align-items-center justify-content-center win-btn-min" onclick="minimizePropertiesPanel()" style="width: 28px; height: 28px; border-radius: 0; text-decoration: none;" title="Minimizar">
                                                <i class="fas fa-window-minimize" id="properties-panel-min-icon" style="font-size: 0.85rem; margin-top: -6px;"></i>
                                            </button>
                                            <button type="button" class="btn btn-link text-white p-0 d-flex align-items-center justify-content-center win-btn-close" onclick="togglePropertiesPanel()" style="width: 28px; height: 28px; border-radius: 0; text-decoration: none;" title="Cerrar">
                                                <i class="fas fa-times" style="font-size: 0.85rem;"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body p-3" id="properties-panel-body" style="overflow-y: auto; flex: 1;">
                                    <div class="form-group mb-2">
                                        <label class="font-weight-bold mb-1" style="font-size:0.85rem;">ID Elemento</label>
                                        <input type="text" id="prop-id" class="form-control form-control-sm" readonly style="background-color:#e9ecef;">
                                    </div>
                                    <div class="form-group mb-2">
                                        <label class="font-weight-bold mb-1" style="font-size:0.85rem;">Tipo</label>
                                        <span id="prop-type" class="badge badge-info d-block text-left p-2" style="font-size:0.8rem;">Process</span>
                                    </div>
                                    <div class="form-group mb-2">
                                        <label class="font-weight-bold mb-1" style="font-size:0.85rem;">Nombre / Etiqueta</label>
                                        <input type="text" id="prop-name" class="form-control form-control-sm" placeholder="Ingresa nombre del nodo...">
                                    </div>
                                    <div class="form-group mb-2">
                                        <label class="font-weight-bold mb-1" style="font-size:0.85rem;">Descripción / Documentación</label>
                                        <textarea id="prop-doc" class="form-control form-control-sm" rows="2" placeholder="Documentación técnica..."></textarea>
                                    </div>
                                    
                                    <hr class="my-2">

                                    <!-- Border Contour Color -->
                                    <div class="form-group mb-2">
                                        <label class="font-weight-bold mb-1" style="font-size:0.85rem;">Color del Contorno (Borde)</label>
                                        <select id="prop-stroke" class="form-control form-control-sm">
                                            <option value="#000000">Negro (Predeterminado)</option>
                                            <option value="#002A54">Azul Sonda</option>
                                            <option value="#FF5C05">Naranja Sonda</option>
                                            <option value="#28a745">Verde Éxito</option>
                                            <option value="#dc3545">Rojo Peligro</option>
                                            <option value="#ffc107">Amarillo Alerta</option>
                                            <option value="#6f42c1">Púrpura</option>
                                        </select>
                                    </div>

                                    <!-- Font Customizations -->
                                    <div class="form-group mb-2">
                                        <label class="font-weight-bold mb-1" style="font-size:0.85rem;">Tipo de Letra (Fuente)</label>
                                        <select id="prop-font-family" class="form-control form-control-sm">
                                            <option value="">Predeterminado (System)</option>
                                            <option value="'Inter', sans-serif">Inter</option>
                                            <option value="'Roboto', sans-serif">Roboto</option>
                                            <option value="Arial, sans-serif">Arial</option>
                                            <option value="'Courier New', monospace">Courier New</option>
                                            <option value="Georgia, serif">Georgia</option>
                                        </select>
                                    </div>

                                    <div class="form-row mb-2">
                                        <div class="col-6">
                                            <label class="font-weight-bold mb-1" style="font-size:0.85rem;">Tamaño Letra</label>
                                            <select id="prop-font-size" class="form-control form-control-sm">
                                                <option value="">Default</option>
                                                <option value="10px">10px</option>
                                                <option value="12px">12px</option>
                                                <option value="14px">14px</option>
                                                <option value="16px">16px</option>
                                                <option value="18px">18px</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="font-weight-bold mb-1" style="font-size:0.85rem;">Color Letra</label>
                                            <select id="prop-font-color" class="form-control form-control-sm">
                                                <option value="">Default</option>
                                                <option value="#000000">Negro</option>
                                                <option value="#ffffff">Blanco</option>
                                                <option value="#002A54">Azul Sonda</option>
                                                <option value="#FF5C05">Naranja</option>
                                                <option value="#28a745">Verde</option>
                                                <option value="#dc3545">Rojo</option>
                                            </select>
                                        </div>
                                    </div>

                                    <hr class="my-2">
                                    
                                    <!-- Solid Colors -->
                                    <div class="form-group mb-2">
                                        <label class="font-weight-bold mb-1" style="font-size:0.85rem;">Relleno Sólido</label>
                                        <div class="d-flex flex-wrap align-items-center" id="color-picker-solid" style="gap: 6px;">
                                            <button class="color-badge active" data-stroke="#000000" data-fill="#ffffff" style="background:#ffffff; border: 1.5px solid #000000; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Predeterminado"></button>
                                            <button class="color-badge" data-stroke="#002A54" data-fill="#E6F0FA" style="background:#E6F0FA; border: 1.5px solid #002A54; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Azul Sonda"></button>
                                            <button class="color-badge" data-stroke="#FF5C05" data-fill="#FFEFE6" style="background:#FFEFE6; border: 1.5px solid #FF5C05; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Naranja Sonda"></button>
                                            <button class="color-badge" data-stroke="#28a745" data-fill="#EBF7EE" style="background:#EBF7EE; border: 1.5px solid #28a745; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Verde Éxito"></button>
                                            <button class="color-badge" data-stroke="#dc3545" data-fill="#FDF2F3" style="background:#FDF2F3; border: 1.5px solid #dc3545; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Rojo Peligro"></button>
                                            <button class="color-badge" data-stroke="#ffc107" data-fill="#FFFDF2" style="background:#FFFDF2; border: 1.5px solid #ffc107; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Amarillo Alerta"></button>
                                            <button class="color-badge" data-stroke="#6f42c1" data-fill="#F6F2FC" style="background:#F6F2FC; border: 1.5px solid #6f42c1; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Púrpura"></button>
                                        </div>
                                    </div>

                                    <!-- Gradient Colors -->
                                    <div class="form-group mb-0">
                                        <label class="font-weight-bold mb-1" style="font-size:0.85rem;">Relleno Degradado</label>
                                        <div class="d-flex flex-wrap align-items-center" id="color-picker-gradient" style="gap: 6px;">
                                            <button class="color-badge" data-stroke="#002A54" data-fill="url(#gradient-sonda-blue)" style="background: linear-gradient(135deg, #E6F0FA 0%, #B3D4F5 100%); border: 1.5px solid #002A54; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Degradado Azul"></button>
                                            <button class="color-badge" data-stroke="#FF5C05" data-fill="url(#gradient-sonda-orange)" style="background: linear-gradient(135deg, #FFEFE6 0%, #FFD9C6 100%); border: 1.5px solid #FF5C05; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Degradado Naranja"></button>
                                            <button class="color-badge" data-stroke="#28a745" data-fill="url(#gradient-success-green)" style="background: linear-gradient(135deg, #EBF7EE 0%, #C7E9D0 100%); border: 1.5px solid #28a745; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Degradado Verde"></button>
                                            <button class="color-badge" data-stroke="#dc3545" data-fill="url(#gradient-danger-red)" style="background: linear-gradient(135deg, #FDF2F3 0%, #F9D6D9 100%); border: 1.5px solid #dc3545; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Degradado Rojo"></button>
                                            <button class="color-badge" data-stroke="#ffc107" data-fill="url(#gradient-warning-yellow)" style="background: linear-gradient(135deg, #FFFDF2 0%, #FFECA8 100%); border: 1.5px solid #ffc107; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Degradado Amarillo"></button>
                                            <button class="color-badge" data-stroke="#6f42c1" data-fill="url(#gradient-purple)" style="background: linear-gradient(135deg, #F6F2FC 0%, #E3D5F7 100%); border: 1.5px solid #6f42c1; width:18px; height:18px; border-radius:50%; padding:0; cursor:pointer;" title="Degradado Púrpura"></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>                 </div>

                </div>

                <!-- TAB 3: XML CODE EDITOR -->
                <div class="tab-pane fade" id="xml-tab-content" role="tabpanel" aria-labelledby="xml-tab">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <label class="font-weight-bold mb-0"><i class="fas fa-code mr-1 text-secondary"></i> Código XML BPMN 2.0 (Edición Directa)</label>
                        <div>
                            <input type="file" id="bpmn-file-input" accept=".bpmn,.xml" style="display: none;">
                            <button class="btn btn-sm btn-outline-info font-weight-bold" onclick="$('#bpmn-file-input').click()">
                                <i class="fas fa-upload mr-1"></i> Subir Archivo .bpmn
                            </button>
                        </div>
                    </div>
                    <textarea id="bpmn-xml" class="form-control w-100" style="height: 600px; font-family: 'Courier New', Courier, monospace; font-size: 0.85rem; background-color: #1e1e1e; color: #abb2bf; border: 1px solid #3e4451; border-radius: 6px; padding: 15px; resize: none;" placeholder="Ingresa o sube el XML de tu proceso BPMN..."></textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'partials/footer.php'; ?>

<!-- Load bpmn-js Modeler -->
<script src="https://cdn.jsdelivr.net/npm/bpmn-js@17/dist/bpmn-modeler.production.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>

<script>
const blankBPMNXML = '<' + '?xml version="1.0" encoding="UTF-8"?' + '>\n' + `
<bpmn:definitions xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL" xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI" xmlns:dc="http://www.omg.org/spec/DD/20100524/DC" xmlns:di="http://www.omg.org/spec/DD/20100524/DI" id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:startEvent id="StartEvent_1" name="Inicio">
      <bpmn:outgoing>Flow_1</bpmn:outgoing>
    </bpmn:startEvent>
    <bpmn:task id="Task_1" name="Nueva Actividad">
      <bpmn:incoming>Flow_1</bpmn:incoming>
      <bpmn:outgoing>Flow_2</bpmn:outgoing>
    </bpmn:task>
    <bpmn:sequenceFlow id="Flow_1" sourceRef="StartEvent_1" targetRef="Task_1" />
    <bpmn:endEvent id="EndEvent_1" name="Fin">
      <bpmn:incoming>Flow_2</bpmn:incoming>
    </bpmn:endEvent>
    <bpmn:sequenceFlow id="Flow_2" sourceRef="Task_1" targetRef="EndEvent_1" />
  </bpmn:process>
  <bpmndi:BPMNDiagram id="BPMNDiagram_1">
    <bpmndi:BPMNPlane id="BPMNPlane_1" bpmnElement="Process_1">
      <bpmndi:BPMNShape id="_BPMNShape_StartEvent_2" bpmnElement="StartEvent_1">
        <dc:Bounds x="173" y="102" width="36" height="36" />
        <bpmndi:BPMNLabel>
          <dc:Bounds x="178" y="145" width="26" height="14" />
        </bpmndi:BPMNLabel>
      </bpmndi:BPMNShape>
      <bpmndi:BPMNShape id="Task_1_di" bpmnElement="Task_1">
        <dc:Bounds x="260" y="80" width="100" height="80" />
      </bpmndi:BPMNShape>
      <bpmndi:BPMNShape id="EndEvent_1_di" bpmnElement="EndEvent_1">
        <dc:Bounds x="422" y="102" width="36" height="36" />
        <bpmndi:BPMNLabel>
          <dc:Bounds x="432" y="145" width="16" height="14" />
        </bpmndi:BPMNLabel>
      </bpmndi:BPMNShape>
      <bpmndi:BPMNEdge id="Flow_1_di" bpmnElement="Flow_1">
        <di:waypoint x="209" y="120" />
        <di:waypoint x="260" y="120" />
      </bpmndi:BPMNEdge>
      <bpmndi:BPMNEdge id="Flow_2_di" bpmnElement="Flow_2">
        <di:waypoint x="360" y="120" />
        <di:waypoint x="422" y="120" />
      </bpmndi:BPMNEdge>
    </bpmndi:BPMNPlane>
  </bpmndi:BPMNDiagram>
</bpmn:definitions>`;

// Auto-layout utility for semantic-only BPMN XML missing visual DI elements
function autoLayoutBPMN(xmlString) {
    if (!xmlString) return xmlString;
    try {
        let parser = new DOMParser();
        let xmlDoc = parser.parseFromString(xmlString, "application/xml");
        
        // If it already has BPMNDiagram, return original
        if (xmlDoc.getElementsByTagName("bpmndi:BPMNDiagram").length > 0 || 
            xmlDoc.getElementsByTagName("BPMNDiagram").length > 0) {
            return xmlString;
        }
        
        let definitions = xmlDoc.documentElement;
        if (!definitions) return xmlString;
        
        let processNode = xmlDoc.getElementsByTagName("bpmn:process")[0] || 
                          xmlDoc.getElementsByTagName("process")[0];
        if (!processNode) return xmlString;
        
        let processId = processNode.getAttribute("id") || "Process_1";
        
        let collaboration = xmlDoc.getElementsByTagName("bpmn:collaboration")[0] || 
                            xmlDoc.getElementsByTagName("collaboration")[0];
        let participant = xmlDoc.getElementsByTagName("bpmn:participant")[0] || 
                          xmlDoc.getElementsByTagName("participant")[0];
        
        let lanes = Array.from(xmlDoc.getElementsByTagName("bpmn:lane") || 
                               xmlDoc.getElementsByTagName("lane"));
        let nodesInLanes = new Map();
        let nodeLaneMap = new Map();
        
        let laneY = 100;
        let laneHeight = 160;
        
        let nodeTypes = [
            "bpmn:startEvent", "startEvent",
            "bpmn:endEvent", "endEvent",
            "bpmn:task", "task",
            "bpmn:userTask", "userTask",
            "bpmn:serviceTask", "serviceTask",
            "bpmn:sendTask", "sendTask",
            "bpmn:receiveTask", "receiveTask",
            "bpmn:manualTask", "manualTask",
            "bpmn:businessRuleTask", "businessRuleTask",
            "bpmn:scriptTask", "scriptTask",
            "bpmn:callActivity", "callActivity",
            "bpmn:exclusiveGateway", "exclusiveGateway",
            "bpmn:parallelGateway", "parallelGateway",
            "bpmn:inclusiveGateway", "inclusiveGateway",
            "bpmn:complexGateway", "complexGateway",
            "bpmn:eventBasedGateway", "eventBasedGateway",
            "bpmn:intermediateCatchEvent", "intermediateCatchEvent",
            "bpmn:intermediateThrowEvent", "intermediateThrowEvent",
            "bpmn:boundaryEvent", "boundaryEvent"
        ];
        
        let allNodes = [];
        nodeTypes.forEach(type => {
            let elms = xmlDoc.getElementsByTagName(type);
            for (let i = 0; i < elms.length; i++) {
                let id = elms[i].getAttribute("id");
                if (id && !allNodes.includes(id)) {
                    allNodes.push(id);
                }
            }
        });
        
        if (lanes.length > 0) {
            lanes.forEach(lane => {
                let laneId = lane.getAttribute("id");
                nodesInLanes.set(laneId, []);
                let flowNodeRefs = Array.from(lane.getElementsByTagName("bpmn:flowNodeRef") || 
                                              lane.getElementsByTagName("flowNodeRef"));
                flowNodeRefs.forEach(ref => {
                    let nodeId = ref.textContent.trim();
                    nodesInLanes.get(laneId).push(nodeId);
                    nodeLaneMap.set(nodeId, laneId);
                });
            });
            
            let unassignedNodes = allNodes.filter(id => !nodeLaneMap.has(id));
            if (unassignedNodes.length > 0) {
                let firstLaneId = lanes[0].getAttribute("id");
                unassignedNodes.forEach(id => {
                    nodesInLanes.get(firstLaneId).push(id);
                    nodeLaneMap.set(id, firstLaneId);
                });
            }
        } else {
            nodesInLanes.set("default_lane", allNodes);
            allNodes.forEach(id => {
                nodeLaneMap.set(id, "default_lane");
            });
        }
        
        let nodePositions = new Map();
        let maxLaneWidth = 300;
        let currentLaneIdx = 0;
        
        nodesInLanes.forEach((nodeList, laneId) => {
            let currentY = laneY + (currentLaneIdx * laneHeight);
            let currentX = 220;
            
            nodeList.forEach(nodeId => {
                let nodeEl = xmlDoc.getElementById(nodeId);
                let type = nodeEl ? nodeEl.tagName.replace(/^bpmn:/, '') : 'task';
                
                let w = 100, h = 80;
                let offset_y = 40;
                if (type.toLowerCase().includes('event')) {
                    w = 36; h = 36;
                    offset_y = 62;
                } else if (type.toLowerCase().includes('gateway')) {
                    w = 50; h = 50;
                    offset_y = 55;
                }
                
                nodePositions.set(nodeId, {
                    x: currentX,
                    y: currentY + offset_y,
                    w: w,
                    h: h
                });
                
                currentX += w + 80;
            });
            
            if (currentX > maxLaneWidth) {
                maxLaneWidth = currentX;
            }
            currentLaneIdx++;
        });
        
        let totalLanesHeight = currentLaneIdx * laneHeight;
        
        let bpmndiNS = "http://www.omg.org/spec/BPMN/20100524/DI";
        let dcNS = "http://www.omg.org/spec/DD/20100524/DC";
        let diNS = "http://www.omg.org/spec/DD/20100524/DI";
        
        let bpmndiDiagram = xmlDoc.createElementNS(bpmndiNS, "bpmndi:BPMNDiagram");
        bpmndiDiagram.setAttribute("id", "BPMNDiagram_Sonda_Auto");
        
        let bpmndiPlane = xmlDoc.createElementNS(bpmndiNS, "bpmndi:BPMNPlane");
        bpmndiPlane.setAttribute("id", "BPMNPlane_Sonda_Auto");
        
        if (collaboration) {
            bpmndiPlane.setAttribute("bpmnElement", collaboration.getAttribute("id"));
        } else {
            bpmndiPlane.setAttribute("bpmnElement", processId);
        }
        
        if (collaboration && participant) {
            let poolShape = xmlDoc.createElementNS(bpmndiNS, "bpmndi:BPMNShape");
            poolShape.setAttribute("id", participant.getAttribute("id") + "_di");
            poolShape.setAttribute("bpmnElement", participant.getAttribute("id"));
            poolShape.setAttribute("isHorizontal", "true");
            
            let bounds = xmlDoc.createElementNS(dcNS, "dc:Bounds");
            bounds.setAttribute("x", "120");
            bounds.setAttribute("y", laneY.toString());
            bounds.setAttribute("width", (maxLaneWidth + 100).toString());
            bounds.setAttribute("height", totalLanesHeight.toString());
            poolShape.appendChild(bounds);
            bpmndiPlane.appendChild(poolShape);
        }
        
        if (lanes.length > 0) {
            lanes.forEach((lane, laneIdx) => {
                let laneShape = xmlDoc.createElementNS(bpmndiNS, "bpmndi:BPMNShape");
                laneShape.setAttribute("id", lane.getAttribute("id") + "_di");
                laneShape.setAttribute("bpmnElement", lane.getAttribute("id"));
                laneShape.setAttribute("isHorizontal", "true");
                
                let bounds = xmlDoc.createElementNS(dcNS, "dc:Bounds");
                bounds.setAttribute("x", "150");
                bounds.setAttribute("y", (laneY + (laneIdx * laneHeight)).toString());
                bounds.setAttribute("width", (maxLaneWidth + 70).toString());
                bounds.setAttribute("height", laneHeight.toString());
                laneShape.appendChild(bounds);
                bpmndiPlane.appendChild(laneShape);
            });
        }
        
        nodePositions.forEach((pos, nodeId) => {
            let shape = xmlDoc.createElementNS(bpmndiNS, "bpmndi:BPMNShape");
            shape.setAttribute("id", nodeId + "_di");
            shape.setAttribute("bpmnElement", nodeId);
            
            let bounds = xmlDoc.createElementNS(dcNS, "dc:Bounds");
            bounds.setAttribute("x", pos.x.toString());
            bounds.setAttribute("y", pos.y.toString());
            bounds.setAttribute("width", pos.w.toString());
            bounds.setAttribute("height", pos.h.toString());
            shape.appendChild(bounds);
            bpmndiPlane.appendChild(shape);
        });
        
        let flows = Array.from(xmlDoc.getElementsByTagName("bpmn:sequenceFlow") || 
                               xmlDoc.getElementsByTagName("sequenceFlow"));
        flows.forEach(flow => {
            let flowId = flow.getAttribute("id");
            let sourceRef = flow.getAttribute("sourceRef");
            let targetRef = flow.getAttribute("targetRef");
            
            let sourcePos = nodePositions.get(sourceRef);
            let targetPos = nodePositions.get(targetRef);
            
            if (sourcePos && targetPos) {
                let edge = xmlDoc.createElementNS(bpmndiNS, "bpmndi:BPMNEdge");
                edge.setAttribute("id", flowId + "_di");
                edge.setAttribute("bpmnElement", flowId);
                
                let wp1 = xmlDoc.createElementNS(diNS, "di:waypoint");
                wp1.setAttribute("x", (sourcePos.x + sourcePos.w).toString());
                wp1.setAttribute("y", (sourcePos.y + (sourcePos.h / 2)).toString());
                edge.appendChild(wp1);
                
                let wp2 = xmlDoc.createElementNS(diNS, "di:waypoint");
                wp2.setAttribute("x", targetPos.x.toString());
                wp2.setAttribute("y", (targetPos.y + (targetPos.h / 2)).toString());
                edge.appendChild(wp2);
                
                bpmndiPlane.appendChild(edge);
            }
        });
        
        bpmndiDiagram.appendChild(bpmndiPlane);
        definitions.appendChild(bpmndiDiagram);
        
        let serializer = new XMLSerializer();
        return serializer.serializeToString(xmlDoc);
    } catch(e) {
        console.error("Auto-layout generation failed:", e);
        return xmlString;
    }
}

// Modeler and UI App state
let bpmnModeler = null;
let activeDiagramId = 0;
let savedXML = '';
let isDirty = false;
let isSyncing = false;
let syncTimeout = null;
let selectedElement = null;

// Brush Tool State
let brushModeActive = false;
let brushColor = { stroke: '#FF5C05', fill: '#FFEFE6' }; // Default Sonda Orange

// Gradient to solid color mapping for fallback compatibility
const gradientToSolidFill = {
    'url(#gradient-sonda-blue)': '#E6F0FA',
    'url(#gradient-sonda-orange)': '#FFEFE6',
    'url(#gradient-success-green)': '#EBF7EE',
    'url(#gradient-danger-red)': '#FDF2F3',
    'url(#gradient-warning-yellow)': '#FFFDF2',
    'url(#gradient-purple)': '#F6F2FC'
};

// Custom Context Pad Provider to add a Color Palette button
const CustomContextPadProvider = {
    __init__: [ 'customContextPadProvider' ],
    customContextPadProvider: [ 'type', [ 'contextPad', 'translate', function(contextPad, translate) {
        contextPad.registerProvider(this);
        
        this.getContextPadEntries = function(element) {
            return function(entries) {
                if (element.type === 'bpmn:Process') return entries;
                
                entries['color-picker'] = {
                    group: 'edit',
                    className: 'entry fas fa-palette text-success',
                    title: 'Cambiar Color de este elemento',
                    action: {
                        click: function(event) {
                            openContextColorPopover(event, element);
                        }
                    }
                };
                return entries;
            };
        };
    }]]
};

$(function() {
    // Add page title next to hamburger button in navbar
    $('.navbar-nav').first().append('<li class="nav-item d-flex align-items-center ml-2"><span class="text-dark font-weight-bold" style="font-size: 1.1rem; font-family: \'Kumbh Sans\', sans-serif;">Procesos de Negocio (BPMN)</span></li>');

    // Instantiate bpmn-js Modeler
    bpmnModeler = new BpmnJS({
        container: '#bpmn-canvas',
        keyboard: {
            bindTo: window
        },
        additionalModules: [
            CustomContextPadProvider
        ]
    });

    // Make properties panel draggable (Windows style)
    makeElementDraggable(document.getElementById('bpmn-properties-panel'), document.querySelector('#bpmn-properties-panel .card-header'));

    // Handle canvas resizing when entering/exiting fullscreen
    document.addEventListener('fullscreenchange', function() {
        if (bpmnModeler) {
            const canvas = bpmnModeler.get('canvas');
            try {
                canvas.resized();
                setTimeout(() => {
                    canvas.zoom('fit-viewport');
                }, 100);
            } catch(e) {
                console.warn(e);
            }
        }
    });

    // Selection changed listener to update properties panel
    bpmnModeler.on('selection.changed', function(e) {
        const selection = e.newSelection;
        if (selection && selection.length > 0) {
            showElementProperties(selection[0]);
        } else {
            showProcessProperties();
        }
    });

    // Element click listener for brush tool
    bpmnModeler.on('element.click', function(e) {
        if (brushModeActive) {
            e.originalEvent.preventDefault();
            e.originalEvent.stopPropagation();
            const element = e.element;
            if (element && element.type !== 'bpmn:Process') {
                applyColorToElement(element, brushColor.stroke, brushColor.fill);
                try {
                    bpmnModeler.get('contextPad').close();
                } catch (err) {}
            }
        }
    });

    // Brush dropdown color selection
    $('#brush-dropdown-wrapper .color-badge').on('click', function(e) {
        e.stopPropagation(); // Prevent closing dropdown on badge click
        const stroke = $(this).attr('data-stroke');
        const fill = $(this).attr('data-fill');
        
        brushColor.stroke = stroke;
        brushColor.fill = fill;
        
        // Highlight active badge in brush dropdown
        $('#brush-dropdown-wrapper .color-badge').removeClass('active').css('box-shadow', 'none');
        $(this).addClass('active').css('box-shadow', '0 0 0 2px var(--sonda-orange)');
        
        // Update brush indicator color
        $('#brush-icon-indicator').css('color', stroke === '#ffffff' ? '#000000' : stroke);
        
        // Automatically activate brush mode if not active
        if (!brushModeActive) {
            $('#brush-mode-toggle').prop('checked', true).trigger('change');
        }
        
        toastr.info('Color de brocha actualizado.');
    });

    // Escape key listener to deactivate brush mode
    $(document).on('keyup', function(e) {
        if (e.key === 'Escape' && brushModeActive) {
            toggleBrushMode(false);
        }
    });

    // Element changed listener to keep panel inputs synced and apply custom styles
    bpmnModeler.on('element.changed', function(e) {
        const element = e.element;
        // Apply text styles if they exist in businessObject
        const bo = element.businessObject;
        if (bo && bo.$attrs) {
            const color = bo.$attrs['labelColor'];
            const size = bo.$attrs['labelSize'];
            const family = bo.$attrs['labelFont'];
            if (color || size || family) {
                applyLabelStyles(element, color, size, family);
            }
            
            const customFill = bo.$attrs['customFill'];
            if (customFill) {
                updateElementDOMGradient(element, customFill);
            }
        }

        if (selectedElement && selectedElement.id === element.id) {
            if ($('#prop-name').is(':not(:focus)')) {
                $('#prop-name').val(bo.name || '');
            }
            if ($('#prop-doc').is(':not(:focus)')) {
                let doc = '';
                if (bo.documentation && bo.documentation.length > 0) {
                    doc = bo.documentation[0].text || '';
                }
                $('#prop-doc').val(doc);
            }
        }
    });

    // Properties panel input listeners
    $('#prop-name').on('input', function() {
        if (!selectedElement) return;
        const name = $(this).val();
        const modeling = bpmnModeler.get('modeling');
        modeling.updateProperties(selectedElement, { name: name });
        
        if (selectedElement.type === 'bpmn:Process') {
            $('#diagram-title').val(name);
        }
    });

    $('#prop-doc').on('input', function() {
        if (!selectedElement) return;
        const docText = $(this).val();
        const bpmnFactory = bpmnModeler.get('bpmnFactory');
        const modeling = bpmnModeler.get('modeling');
        const newDocumentation = bpmnFactory.create('bpmn:Documentation', { text: docText });
        modeling.updateProperties(selectedElement, {
            documentation: [ newDocumentation ]
        });
    });

    // Contour / stroke color listener
    $('#prop-stroke').on('change', function() {
        if (!selectedElement) return;
        const stroke = $(this).val();
        let fill = '#ffffff';
        if (selectedElement.di) {
            fill = selectedElement.di.get('fill') || '#ffffff';
        }
        const modeling = bpmnModeler.get('modeling');
        try {
            modeling.setColor(selectedElement, {
                stroke: stroke,
                fill: fill
            });
        } catch (err) {
            if (selectedElement.di) {
                modeling.updateModdleProperties(selectedElement, selectedElement.di, {
                    'bioc:stroke': stroke,
                    'bioc:fill': fill
                });
            }
        }
    });

    // Font settings listeners
    $('#prop-font-family').on('change', function() {
        if (!selectedElement) return;
        const family = $(this).val();
        applyLabelStyles(selectedElement, null, null, family);
        const modeling = bpmnModeler.get('modeling');
        modeling.updateProperties(selectedElement, {
            'labelFont': family
        });
    });

    $('#prop-font-size').on('change', function() {
        if (!selectedElement) return;
        const size = $(this).val();
        applyLabelStyles(selectedElement, null, size, null);
        const modeling = bpmnModeler.get('modeling');
        modeling.updateProperties(selectedElement, {
            'labelSize': size
        });
    });

    $('#prop-font-color').on('change', function() {
        if (!selectedElement) return;
        const color = $(this).val();
        applyLabelStyles(selectedElement, color, null, null);
        const modeling = bpmnModeler.get('modeling');
        modeling.updateProperties(selectedElement, {
            'labelColor': color
        });
    });

    // Detect model changes to toggle dirty state & sync to XML Textarea
    bpmnModeler.on('commandStack.changed', function() {
        checkDirtyStatus();
        if (!isSyncing) {
            clearTimeout(syncTimeout);
            syncTimeout = setTimeout(function() {
                isSyncing = true;
                bpmnModeler.saveXML({ format: true }).then(({ xml }) => {
                    $('#bpmn-xml').val(xml);
                    isSyncing = false;
                }).catch(err => {
                    isSyncing = false;
                });
            }, 300);
        }
    });

    // Handle File Upload Input
    $('#bpmn-file-input').on('change', function(e) {
        let file = e.target.files[0];
        if (!file) return;
        let reader = new FileReader();
        reader.onload = function(evt) {
            let content = evt.target.result;
            $('#bpmn-xml').val(content);
            isSyncing = true;
            bpmnModeler.importXML(content).then(() => {
                injectGradients();
                applyAllSavedStyles();
                checkDirtyStatus();
                isSyncing = false;
                toastr.success('Archivo BPMN cargado en el editor.');
            }).catch(err => {
                isSyncing = false;
                toastr.error('Error al importar archivo XML.');
            });
        };
        reader.readAsText(file);
    });

    // Load initial blank diagram
    savedXML = blankBPMNXML;
    $('#bpmn-xml').val(blankBPMNXML);
    bpmnModeler.importXML(blankBPMNXML).then(() => {
        injectGradients();
        applyAllSavedStyles();
        setTimeout(resetZoom, 100);
        showProcessProperties();
    });

    // Color badge click event handler (solid & gradient)
    $(document).on('click', '#color-picker-solid .color-badge, #color-picker-gradient .color-badge', function() {
        if (!selectedElement) {
            toastr.warning('Por favor selecciona un elemento en el diagrama para cambiar su color.');
            return;
        }
        const stroke = $(this).attr('data-stroke');
        const fill = $(this).attr('data-fill');
        
        applyColorToElement(selectedElement, stroke, fill);
        
        // Highlight active badge in properties panel
        $('#color-picker-solid .color-badge, #color-picker-gradient .color-badge').removeClass('active').css('box-shadow', 'none');
        $(this).addClass('active').css('box-shadow', '0 0 0 2px var(--sonda-orange)');
        $('#prop-stroke').val(stroke);
        toastr.success('Color aplicado al elemento.');
    });

    // Bidirectional Tab switching logic
    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
        const targetTabId = $(e.target).attr('id');
        
        if (targetTabId === 'xml-tab') {
            // Switch to XML Editor tab -> Sync from canvas to textarea
            isSyncing = true;
            bpmnModeler.saveXML({ format: true }).then(({ xml }) => {
                $('#bpmn-xml').val(xml);
                isSyncing = false;
            }).catch(err => {
                isSyncing = false;
            });
        } else if (targetTabId === 'editor-tab') {
            // Switch to Modeler tab -> Sync from textarea to canvas
            const xml = $('#bpmn-xml').val();
            const processedXML = autoLayoutBPMN(xml);
            if (processedXML !== xml) {
                $('#bpmn-xml').val(processedXML);
            }
            
            isSyncing = true;
            bpmnModeler.importXML(processedXML).then(() => {
                if (bpmnModeler) {
                    try {
                        bpmnModeler.get('canvas').resized();
                        bpmnModeler.get('canvas').zoom('fit-viewport');
                        injectGradients();
                        applyAllSavedStyles();
                    } catch(err) {
                        console.error("Error updating bpmn layout:", err);
                    }
                }
                showProcessProperties();
                checkDirtyStatus();
                isSyncing = false;
            }).catch(err => {
                isSyncing = false;
                toastr.error('Error al sincronizar el XML en el lienzo visual.');
            });
        }
    });

    loadDiagramsGrid();

    // Client-side grid filtering
    $('#search-diagram').on('input', function() {
        let q = $(this).val().toLowerCase();
        $('.diagram-grid-card-wrapper').each(function() {
            let title = $(this).find('.card-title').text().toLowerCase();
            let desc = $(this).find('.card-text').text().toLowerCase();
            $(this).toggle(title.indexOf(q) > -1 || desc.indexOf(q) > -1);
        });
    });
});

// Detect change state vs last saved
function checkDirtyStatus() {
    bpmnModeler.saveXML({ format: true }).then(({ xml }) => {
        if (xml.trim() === savedXML.trim()) {
            $('#save-status').text('Guardado').removeClass('badge-secondary').addClass('badge-success');
            isDirty = false;
        } else {
            $('#save-status').text('Sin Guardar').removeClass('badge-success').addClass('badge-secondary');
            isDirty = true;
        }
    });
}

// Canvas Zoom controls via bpmn-js API
function zoomIn() {
    bpmnModeler.get('canvas').zoom(bpmnModeler.get('canvas').zoom() + 0.15);
}

function zoomOut() {
    bpmnModeler.get('canvas').zoom(bpmnModeler.get('canvas').zoom() - 0.15);
}

function resetZoom() {
    if (!bpmnModeler) return;
    const canvas = bpmnModeler.get('canvas');
    const container = document.getElementById('bpmn-canvas');
    if (container && container.offsetWidth > 0 && container.offsetHeight > 0) {
        try {
            canvas.zoom('fit-viewport');
        } catch(err) {
            console.warn("Could not fit viewport:", err);
        }
    }
}

function toggleFullscreen() {
    let container = document.getElementById('editor-tab-content');
    if (!document.fullscreenElement) {
        container.requestFullscreen().then(() => {
            setTimeout(resetZoom, 100);
        }).catch(err => {
            toastr.error('No se pudo activar pantalla completa: ' + err.message);
        });
    } else {
        document.exitFullscreen();
    }
}

// Load diagrams catalog list in Tab 1
function loadDiagramsGrid() {
    $.getJSON('api_bpmn.php', { action: 'list' }, function(res) {
        if (res.success) {
            let html = '';
            if (res.diagrams.length === 0) {
                html = `
                <div class="col-12 text-center py-5">
                    <i class="fas fa-sitemap fa-3x text-muted mb-3"></i>
                    <h5>No hay procesos BPMN guardados</h5>
                    <p class="text-muted">Crea tu primer mapa de procesos utilizando el modelador visual estándar.</p>
                    <button class="btn btn-success" onclick="createNewDiagram()">Crear Proceso</button>
                </div>`;
            } else {
                res.diagrams.forEach(diag => {
                    let desc = diag.description ? diag.description : 'Sin descripción adicional.';
                    let dateStr = diag.updated_at.substring(0, 16);
                    html += `
                    <div class="col-md-4 mb-3 diagram-grid-card-wrapper">
                        <div class="card h-100 bpmn-card-item shadow-sm" onclick="loadDiagramAndSwitchTab(${diag.id})">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title font-weight-bold text-orange mb-2 text-truncate">${escapeHtml(diag.title)}</h5>
                                <p class="card-text text-muted flex-grow-1" style="font-size:0.88rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                    ${escapeHtml(desc)}
                                </p>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted"><i class="far fa-calendar-alt mr-1"></i>${dateStr}</small>
                                    <span class="btn btn-xs btn-outline-warning font-weight-bold">Modelar <i class="fas fa-arrow-right ml-1"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>`;
                });
            }
            $('#diagrams-grid-container').html(html);
        }
    });
}

function escapeHtml(text) {
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Load diagram detail and switch tab
function loadDiagramAndSwitchTab(id) {
    activeDiagramId = id;
    $.getJSON('api_bpmn.php', { action: 'get', id: id }, function(res) {
        if (res.success) {
            let d = res.diagram;
            $('#diagram-id').val(d.id);
            $('#diagram-title').val(d.title);
            $('#diagram-desc').val(d.description);
            
            let processedXML = autoLayoutBPMN(d.xml_content);
            savedXML = d.xml_content;
            $('#bpmn-xml').val(processedXML);
            bpmnModeler.importXML(processedXML).then(() => {
                setTimeout(resetZoom, 100);
                showProcessProperties();
            });

            $('#delete-diagram-btn').removeClass('d-none');
            $('#history-view-banner').addClass('d-none');

            checkDirtyStatus();
            loadHistoryDropdown(d.id);

            // Switch to editor Tab
            $('#editor-tab').tab('show');
            toastr.info(`Proceso "${d.title}" cargado.`);
        } else {
            Swal.fire('Error', res.error, 'error');
        }
    });
}

// Action: Switch to Tab 2 and clear fields for a new diagram
function createNewDiagram() {
    resetEditorFields();
    $('#editor-tab').tab('show');
}

// Action: Clear fields directly from editor tab
function resetEditorAndCreate() {
    if (isDirty) {
        Swal.fire({
            title: '¿Crear nuevo diagrama?',
            text: 'Tienes cambios sin guardar. Se limpiará el editor actual.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, nuevo',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                resetEditorFields();
            }
        });
    } else {
        resetEditorFields();
    }
}

function resetEditorFields() {
    activeDiagramId = 0;
    $('#diagram-id').val('0');
    $('#diagram-title').val('');
    $('#diagram-desc').val('');
    
    savedXML = blankBPMNXML;
    $('#bpmn-xml').val(blankBPMNXML);
    bpmnModeler.importXML(blankBPMNXML).then(() => {
        setTimeout(resetZoom, 100);
        showProcessProperties();
    });

    $('#delete-diagram-btn').addClass('d-none');
    $('#history-dropdown-wrapper').addClass('d-none');
    $('#history-view-banner').addClass('d-none');
    checkDirtyStatus();
}

// Save diagram (xml export and database update)
function saveDiagram() {
    let id = $('#diagram-id').val();
    let title = $('#diagram-title').val().trim();
    let description = $('#diagram-desc').val().trim();

    if (!title) {
        Swal.fire('Atención', 'Por favor ingresa un título para el proceso.', 'warning');
        return;
    }

    bpmnModeler.saveXML({ format: true }).then(({ xml }) => {
        $.post('api_bpmn.php', {
            action: 'save',
            id: id,
            title: title,
            description: description,
            xml_content: xml
        }, function(res) {
            if (res.success) {
                toastr.success(res.message || 'Guardado correctamente.');
                savedXML = xml;
                $('#bpmn-xml').val(xml);
                activeDiagramId = res.id;
                
                $('#diagram-id').val(res.id);
                $('#delete-diagram-btn').removeClass('d-none');
                $('#history-view-banner').addClass('d-none');
                
                checkDirtyStatus();
                loadDiagramsGrid();
                loadHistoryDropdown(res.id);
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        });
    }).catch(err => {
        Swal.fire('Error', 'No se pudo exportar el XML de BPMN: ' + err.message, 'error');
    });
}

// Delete diagram
function deleteDiagram() {
    let id = $('#diagram-id').val();
    if (id <= 0) return;

    Swal.fire({
        title: '¿Eliminar Diagrama?',
        text: 'Esta acción eliminará el proceso permanentemente. ¿Deseas continuar?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('api_bpmn.php', {
                action: 'delete',
                id: id
            }, function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    resetEditorFields();
                    loadDiagramsGrid();
                    $('#list-tab').tab('show');
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            });
        }
    });
}

// XML download export
function exportXMLFile() {
    bpmnModeler.saveXML({ format: true }).then(({ xml }) => {
        let title = $('#diagram-title').val().trim() || 'proceso_bpmn';
        let filename = title.toLowerCase().replace(/\s+/g, '_') + '.bpmn';
        
        let blob = new Blob([xml], { type: 'application/xml' });
        let link = document.createElement('a');
        link.href = window.URL.createObjectURL(blob);
        link.download = filename;
        link.click();
        toastr.success('Archivo XML BPMN descargado.');
    });
}

// History Dropdown management
function loadHistoryDropdown(diagramId) {
    if (diagramId <= 0) return;
    $.getJSON('api_bpmn.php', { action: 'history', diagram_id: diagramId }, function(res) {
        if (res.success) {
            let html = '';
            if (res.history.length === 0) {
                html = '<a class="dropdown-item disabled text-muted" href="#">Sin historial aún</a>';
            } else {
                res.history.forEach((h, index) => {
                    let versionNum = res.history.length - index;
                    html += `
                    <a class="dropdown-item" href="#" onclick="loadHistoricalVersion(${h.id}, '${h.created_at}')">
                        <i class="fas fa-history mr-2 text-info"></i>
                        <strong>Versión ${versionNum}</strong>
                        <div class="text-muted" style="font-size:0.75rem; margin-left:22px;">${h.created_at}</div>
                    </a>`;
                });
            }
            $('#history-items-container').html(html);
            $('#history-dropdown-wrapper').removeClass('d-none');
        }
    });
}

function loadHistoricalVersion(historyId, dateCreated) {
    $.getJSON('api_bpmn.php', { action: 'get_history', id: historyId }, function(res) {
        if (res.success) {
            let v = res.version;
            $('#diagram-title').val(v.title);
            $('#diagram-desc').val(v.description);
            
            let processedXML = autoLayoutBPMN(v.xml_content);
            bpmnModeler.importXML(processedXML).then(() => {
                $('#bpmn-xml').val(processedXML);
                setTimeout(resetZoom, 100);
                showProcessProperties();
            });

            $('#history-date-span').text(dateCreated);
            $('#history-view-banner').removeClass('d-none');
            
            checkDirtyStatus();
            toastr.warning(`Mostrando revisión histórica del ${dateCreated}`);
        } else {
            Swal.fire('Error', res.error, 'error');
        }
    });
}

// SVG export
function exportSVGFile() {
    bpmnModeler.saveSVG().then(({ svg }) => {
        let title = $('#diagram-title').val().trim() || 'proceso_bpmn';
        let filename = title.toLowerCase().replace(/\s+/g, '_') + '.svg';
        
        let blob = new Blob([svg], { type: 'image/svg+xml;charset=utf-8' });
        let link = document.createElement('a');
        link.href = window.URL.createObjectURL(blob);
        link.download = filename;
        link.click();
        toastr.success('Archivo SVG exportado.');
    }).catch(err => {
        toastr.error('Error al exportar SVG: ' + err.message);
    });
}

// PDF export via canvas and jsPDF
function exportPDFFile() {
    toastr.info('Generando PDF...');
    bpmnModeler.saveSVG().then(({ svg }) => {
        // Parse SVG to extract dimensions (handles 0/NaN issues on some browsers)
        let width = 1000;
        let height = 600;
        try {
            const parser = new DOMParser();
            const svgDoc = parser.parseFromString(svg, "image/svg+xml");
            const svgEl = svgDoc.documentElement;
            
            let parsedWidth = parseFloat(svgEl.getAttribute('width'));
            let parsedHeight = parseFloat(svgEl.getAttribute('height'));
            
            const viewBox = svgEl.getAttribute('viewBox');
            if (viewBox) {
                const parts = viewBox.split(/\s+/);
                if (parts.length === 4) {
                    if (isNaN(parsedWidth) || !parsedWidth) parsedWidth = parseFloat(parts[2]);
                    if (isNaN(parsedHeight) || !parsedHeight) parsedHeight = parseFloat(parts[3]);
                }
            }
            
            if (!isNaN(parsedWidth) && parsedWidth > 0) width = parsedWidth;
            if (!isNaN(parsedHeight) && parsedHeight > 0) height = parsedHeight;
        } catch (e) {
            console.error("Error parsing SVG dimensions:", e);
        }

        const img = new Image();
        img.width = width;
        img.height = height;
        
        img.onload = function() {
            try {
                // Create a canvas with high-res scale (2x)
                const canvas = document.createElement('canvas');
                const scale = 2;
                canvas.width = width * scale;
                canvas.height = height * scale;
                
                const ctx = canvas.getContext('2d');
                // Draw white background
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                
                // Generate PDF using jsPDF
                const { jsPDF } = window.jspdf;
                
                // Determine orientation (landscape if width > height)
                const orientation = width > height ? 'l' : 'p';
                
                // Create PDF page with matching size (in points)
                const pdf = new jsPDF({
                    orientation: orientation,
                    unit: 'pt',
                    format: [width, height]
                });
                
                // Add image to PDF
                pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 0, 0, width, height);
                
                let title = $('#diagram-title').val().trim() || 'proceso_bpmn';
                let filename = title.toLowerCase().replace(/\s+/g, '_') + '.pdf';
                
                pdf.save(filename);
                toastr.success('Archivo PDF exportado.');
            } catch (canvasErr) {
                console.error("Error rendering canvas to PDF:", canvasErr);
                toastr.error('Error al generar la imagen del PDF.');
            }
        };
        
        img.onerror = function(err) {
            console.error("Image load error:", err);
            toastr.error('Error al renderizar el SVG para el PDF (bloqueo de seguridad o formato).');
        };
        
        // Use base64 data URI to completely bypass any CSP blob: restrictions
        try {
            const base64Svg = btoa(unescape(encodeURIComponent(svg)));
            img.src = 'data:image/svg+xml;base64,' + base64Svg;
        } catch (encodeErr) {
            console.error("Base64 encoding error:", encodeErr);
            toastr.error('Error al procesar los caracteres especiales del diagrama.');
        }
    }).catch(err => {
        toastr.error('Error al exportar PDF: ' + err.message);
    });
}

// Toggle properties panel visibility
function togglePropertiesPanel() {
    const panel = $('#bpmn-properties-panel');
    
    if (panel.hasClass('d-none')) {
        panel.removeClass('d-none');
        // Ensure body is visible and restore icon is correct
        $('#properties-panel-body').removeClass('d-none');
        $('#properties-panel-min-icon').removeClass('fa-window-restore').addClass('fa-window-minimize');
        
        // Show correct properties based on selection
        if (selectedElement) {
            showElementProperties(selectedElement);
        } else {
            showProcessProperties();
        }
    } else {
        panel.addClass('d-none');
    }
    
    // Resize modeler canvas just in case
    if (bpmnModeler) {
        setTimeout(function() {
            try {
                bpmnModeler.get('canvas').resized();
            } catch(e) {
                console.error(e);
            }
        }, 150);
    }
}

// Display selected element's details in properties panel
function showElementProperties(element) {
    selectedElement = element;
    
    // Ensure panel is updated with selected element metadata
    const bo = element.businessObject;
    $('#prop-id').val(bo.id || '');
    $('#prop-type').text(element.type.replace(/^bpmn:/, ''));
    $('#prop-name').val(bo.name || '');
    
    let doc = '';
    if (bo.documentation && bo.documentation.length > 0) {
        doc = bo.documentation[0].text || '';
    }
    $('#prop-doc').val(doc);

    // Set custom font fields from Moddle $attrs
    if (bo && bo.$attrs) {
        $('#prop-font-family').val(bo.$attrs['labelFont'] || '');
        $('#prop-font-size').val(bo.$attrs['labelSize'] || '');
        $('#prop-font-color').val(bo.$attrs['labelColor'] || '');
    } else {
        $('#prop-font-family').val('');
        $('#prop-font-size').val('');
        $('#prop-font-color').val('');
    }
    
    // Get colors from DI
    let fill = '';
    let stroke = '';
    if (element.di) {
        fill = element.di.get('fill') || '';
        stroke = element.di.get('stroke') || '';
    }
    
    $('#prop-stroke').val(stroke || '#000000');
    updateColorPickerActive(fill, stroke);
}

// Default properties display when process/canvas itself is selected
function showProcessProperties() {
    selectedElement = null;
    try {
        const elementRegistry = bpmnModeler.get('elementRegistry');
        const processElement = elementRegistry.filter(e => e.type === 'bpmn:Process')[0];
        if (processElement) {
            selectedElement = processElement;
            const bo = processElement.businessObject;
            $('#prop-id').val(bo.id || 'Process_1');
            $('#prop-type').text('Process (Diagrama)');
            $('#prop-name').val(bo.name || $('#diagram-title').val() || '');
            
            let doc = '';
            if (bo.documentation && bo.documentation.length > 0) {
                doc = bo.documentation[0].text || '';
            }
            $('#prop-doc').val(doc);

            if (bo && bo.$attrs) {
                $('#prop-font-family').val(bo.$attrs['labelFont'] || '');
                $('#prop-font-size').val(bo.$attrs['labelSize'] || '');
                $('#prop-font-color').val(bo.$attrs['labelColor'] || '');
            } else {
                $('#prop-font-family').val('');
                $('#prop-font-size').val('');
                $('#prop-font-color').val('');
            }
            
            $('#prop-stroke').val('#000000');
            updateColorPickerActive('', '');
        } else {
            // General fallback reset
            $('#prop-id').val('-');
            $('#prop-type').text('Ninguno');
            $('#prop-name').val('');
            $('#prop-doc').val('');
            $('#prop-font-family').val('');
            $('#prop-font-size').val('');
            $('#prop-font-color').val('');
            $('#prop-stroke').val('#000000');
            updateColorPickerActive('', '');
        }
    } catch(err) {
        console.error("Error setting process properties:", err);
    }
}

// Highlight the color button that matches current element styles
function updateColorPickerActive(fill, stroke) {
    $('#color-picker-solid .color-badge, #color-picker-gradient .color-badge').removeClass('active').css('box-shadow', 'none');
    
    const normFill = (fill || '').toLowerCase();
    const normStroke = (stroke || '').toLowerCase();
    
    let matched = false;
    $('#color-picker-solid .color-badge, #color-picker-gradient .color-badge').each(function() {
        const badgeStroke = $(this).attr('data-stroke').toLowerCase();
        const badgeFill = $(this).attr('data-fill').toLowerCase();
        
        if (badgeStroke === normStroke && badgeFill === normFill) {
            $(this).addClass('active').css('box-shadow', '0 0 0 2px var(--sonda-orange)');
            matched = true;
        }
    });
    
    if (!matched && !normFill) {
        // Select predeterminado color badge if empty
        $('#color-picker-solid .color-badge[data-stroke="#000000"][data-fill="#ffffff"]').addClass('active').css('box-shadow', '0 0 0 2px var(--sonda-orange)');
    }
}

// Apply label font configurations directly to the SVG element
function applyLabelStyles(element, color, size, family) {
    const bo = element.businessObject;
    if (!bo) return;
    if (!bo.$attrs) bo.$attrs = {};
    
    if (color !== null && color !== undefined) bo.$attrs['labelColor'] = color;
    if (size !== null && size !== undefined) bo.$attrs['labelSize'] = size;
    if (family !== null && family !== undefined) bo.$attrs['labelFont'] = family;
    
    const activeColor = bo.$attrs['labelColor'] || '';
    const activeSize = bo.$attrs['labelSize'] || '';
    const activeFamily = bo.$attrs['labelFont'] || '';

    const elementRegistry = bpmnModeler.get('elementRegistry');
    
    function styleGraphics(targetElement) {
        if (!targetElement) return;
        const gfx = elementRegistry.getGraphics(targetElement);
        if (gfx) {
            const textElements = gfx.querySelectorAll('text, tspan');
            textElements.forEach(text => {
                if (activeColor !== null) text.style.fill = activeColor;
                if (activeSize !== null) text.style.fontSize = activeSize;
                if (activeFamily !== null) text.style.fontFamily = activeFamily;
            });
        }
    }
    
    // Style main element
    styleGraphics(element);
    
    // Style external labels if they exist
    if (element.label) {
        styleGraphics(element.label);
    }
    const externalLabelEl = elementRegistry.get(element.id + '_label');
    if (externalLabelEl) {
        styleGraphics(externalLabelEl);
    }
}

// Apply text styles for all elements
function applyAllSavedStyles() {
    injectGradients();
    if (!bpmnModeler) return;
    const elementRegistry = bpmnModeler.get('elementRegistry');
    const modeling = bpmnModeler.get('modeling');
    
    elementRegistry.forEach(element => {
        const bo = element.businessObject;
        if (bo) {
            const attrs = bo.$attrs || {};
            const color = attrs['labelColor'];
            const size = attrs['labelSize'];
            const family = attrs['labelFont'];
            if (color || size || family) {
                applyLabelStyles(element, color, size, family);
            }
            
            let customFill = attrs['customFill'];
            let customStroke = attrs['customStroke'];
            
            // Fallback to bioc attributes in DI (very common for stored diagrams)
            if (!customFill && element.di) {
                customFill = element.di.get('bioc:fill') || element.di.get('fill');
            }
            if (!customStroke && element.di) {
                customStroke = element.di.get('bioc:stroke') || element.di.get('stroke');
            }
            
            if (customFill || customStroke) {
                const fillVal = customFill || '#ffffff';
                const strokeVal = customStroke || '#000000';
                const solidFill = gradientToSolidFill[fillVal] || fillVal;
                
                // Keep references updated in attrs
                if (fillVal.startsWith('url(#') && !attrs['customFill']) {
                    attrs['customFill'] = fillVal;
                }
                if (strokeVal && !attrs['customStroke']) {
                    attrs['customStroke'] = strokeVal;
                }
                
                try {
                    modeling.setColor(element, {
                        fill: solidFill,
                        stroke: strokeVal
                    });
                } catch(e) {
                    if (element.di) {
                        modeling.updateModdleProperties(element, element.di, {
                            'bioc:stroke': strokeVal,
                            'bioc:fill': solidFill
                        });
                    }
                }
                
                updateElementDOMGradient(element, fillVal);
            }
        }
    });
}

// Apply a gradient fill to all activities / task cubes in the process
function applyGradientToAllCubes(gradientId, strokeColor) {
    if (!bpmnModeler) return;
    const elementRegistry = bpmnModeler.get('elementRegistry');
    const modeling = bpmnModeler.get('modeling');
    const fillValue = 'url(#' + gradientId + ')';
    const solidFill = gradientToSolidFill[fillValue] || fillValue;
    let count = 0;
    
    elementRegistry.forEach(element => {
        if (element.type.includes('Task') || element.type === 'bpmn:SubProcess' || element.type === 'bpmn:CallActivity') {
            count++;
            try {
                modeling.setColor(element, {
                    fill: solidFill,
                    stroke: strokeColor
                });
            } catch (err) {
                if (element.di) {
                    modeling.updateModdleProperties(element, element.di, {
                        'bioc:stroke': strokeColor,
                        'bioc:fill': solidFill
                    });
                }
            }
            
            modeling.updateProperties(element, {
                'customFill': fillValue,
                'customStroke': strokeColor
            });
            
            updateElementDOMGradient(element, fillValue);
        }
    });
    
    if (count > 0) {
        toastr.success('Degradado aplicado a todos los cubos.');
    } else {
        toastr.warning('No se encontraron actividades (cubos) en el diagrama.');
    }
}

// Inject custom linear gradients into modeler SVG definitions for standard-compliant rendering
function injectGradients() {
    const svgEl = document.querySelector('#bpmn-canvas svg');
    if (!svgEl) return;
    
    let defs = svgEl.querySelector('defs');
    if (!defs) {
        defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
        svgEl.insertBefore(defs, svgEl.firstChild);
    }
    
    const gradients = {
        'gradient-sonda-blue': { start: '#E6F0FA', end: '#B3D4F5' },
        'gradient-sonda-orange': { start: '#FFEFE6', end: '#FFD9C6' },
        'gradient-success-green': { start: '#EBF7EE', end: '#C7E9D0' },
        'gradient-danger-red': { start: '#FDF2F3', end: '#F9D6D9' },
        'gradient-warning-yellow': { start: '#FFFDF2', end: '#FFECA8' },
        'gradient-purple': { start: '#F6F2FC', end: '#E3D5F7' }
    };
    
    for (const [id, colors] of Object.entries(gradients)) {
        if (!defs.querySelector('#' + id)) {
            const grad = document.createElementNS('http://www.w3.org/2000/svg', 'linearGradient');
            grad.setAttribute('id', id);
            grad.setAttribute('x1', '0%');
            grad.setAttribute('y1', '0%');
            grad.setAttribute('x2', '100%');
            grad.setAttribute('y2', '100%');
            
            const stop1 = document.createElementNS('http://www.w3.org/2000/svg', 'stop');
            stop1.setAttribute('offset', '0%');
            stop1.setAttribute('stop-color', colors.start);
            
            const stop2 = document.createElementNS('http://www.w3.org/2000/svg', 'stop');
            stop2.setAttribute('offset', '100%');
            stop2.setAttribute('stop-color', colors.end);
            
            grad.appendChild(stop1);
            grad.appendChild(stop2);
            defs.appendChild(grad);
        }
    }
}

function restoreActiveVersion() {
    Swal.fire({
        title: '¿Restaurar esta versión?',
        text: 'Esta versión histórica reemplazará a la actual del diagrama.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, restaurar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            saveDiagram();
        }
    });
}

function exitHistoryView() {
    if (activeDiagramId > 0) {
        loadDiagramAndSwitchTab(activeDiagramId);
    }
}

// Draggable window implementation for properties panel
function makeElementDraggable(elmnt, handle) {
    let pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
    if (handle) {
        handle.onmousedown = dragMouseDown;
    } else {
        elmnt.onmousedown = dragMouseDown;
    }

    function dragMouseDown(e) {
        e = e || window.event;
        // Don't drag if clicking interactive fields/buttons
        if (['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON', 'A', 'OPTION'].includes(e.target.tagName) || e.target.closest('button')) {
            return;
        }
        e.preventDefault();
        pos3 = e.clientX;
        pos4 = e.clientY;
        document.onmouseup = closeDragElement;
        document.onmousemove = elementDrag;
    }

    function elementDrag(e) {
        e = e || window.event;
        e.preventDefault();
        pos1 = pos3 - e.clientX;
        pos2 = pos4 - e.clientY;
        pos3 = e.clientX;
        pos4 = e.clientY;
        elmnt.style.top = (elmnt.offsetTop - pos2) + "px";
        elmnt.style.left = (elmnt.offsetLeft - pos1) + "px";
        elmnt.style.right = "auto";
        elmnt.style.bottom = "auto";
    }

    function closeDragElement() {
        document.onmouseup = null;
        document.onmousemove = null;
    }
}

// Minimize / collapse properties panel (Windows style window toggle)
function minimizePropertiesPanel() {
    const body = $('#properties-panel-body');
    const icon = $('#properties-panel-min-icon');
    const panel = $('#bpmn-properties-panel');
    
    if (body.hasClass('d-none')) {
        body.removeClass('d-none');
        icon.removeClass('fa-window-restore').addClass('fa-window-minimize');
        // Restore to top-right style
        panel.css({
            top: '20px',
            bottom: 'auto',
            right: '20px',
            left: 'auto'
        });
    } else {
        body.addClass('d-none');
        icon.removeClass('fa-window-minimize').addClass('fa-window-restore');
        // Collapse and dock to bottom right
        panel.css({
            top: 'auto',
            bottom: '10px',
            right: '10px',
            left: 'auto'
        });
    }
}

// Global helper to apply colors to elements
function applyColorToElement(element, stroke, fill) {
    try {
        const modeling = bpmnModeler.get('modeling');
        const solidFill = gradientToSolidFill[fill] || fill;
        try {
            modeling.setColor(element, {
                stroke: stroke,
                fill: solidFill
            });
        } catch (err) {
            if (element.di) {
                modeling.updateModdleProperties(element, element.di, {
                    'bioc:stroke': stroke,
                    'bioc:fill': solidFill
                });
            } else {
                throw err;
            }
        }
        
        // Persist color properties in the businessObject attrs to ensure compatibility and save in XML
        modeling.updateProperties(element, {
            'customFill': fill,
            'customStroke': stroke
        });

        // Apply gradient to SVG DOM if applicable
        updateElementDOMGradient(element, fill);
    } catch(err) {
        console.error("Error applying color:", err);
        toastr.error('No se pudo aplicar el color a este elemento.');
    }
}

// Update the DOM representation of an element to render SVG gradient or solid fill
function updateElementDOMGradient(element, fill) {
    if (!bpmnModeler) return;
    const elementRegistry = bpmnModeler.get('elementRegistry');
    const gfx = elementRegistry.getGraphics(element);
    if (gfx) {
        const mainShape = $(gfx).find('.djs-visual').children().first();
        if (fill) {
            mainShape.css('fill', fill);
        } else {
            mainShape.css('fill', '');
        }
    }
}

// Toggle paintbrush tool mode
function toggleBrushMode(active) {
    brushModeActive = active;
    if (active) {
        $('#bpmn-canvas').addClass('brush-cursor-active');
        $('#btn-brush-tool').removeClass('btn-outline-secondary').addClass('btn-warning');
        $('#brush-mode-toggle').prop('checked', true);
        toastr.success('Modo brocha activado. Haz clic en cualquier elemento para pintarlo. Presiona Esc para salir.');
    } else {
        $('#bpmn-canvas').removeClass('brush-cursor-active');
        $('#btn-brush-tool').removeClass('btn-warning').addClass('btn-outline-secondary');
        $('#brush-mode-toggle').prop('checked', false);
        toastr.info('Modo brocha desactivado.');
    }
}

// Programmatically activate BPMN-JS Lasso Tool (Group Selection)
function activateLassoTool(event) {
    if (!bpmnModeler) return;
    try {
        const lassoTool = bpmnModeler.get('lassoTool');
        lassoTool.activateSelection(event);
        toastr.info('Herramienta Lazo activa: Haz clic y arrastra en el lienzo para seleccionar múltiples elementos.');
    } catch(err) {
        console.error("Error activating Lasso Tool:", err);
    }
}

// Programmatically activate BPMN-JS Space Tool (Shifts sections of the diagram)
function activateSpaceTool(event) {
    if (!bpmnModeler) return;
    try {
        const spaceTool = bpmnModeler.get('spaceTool');
        spaceTool.activateSelection(event);
        toastr.info('Herramienta Espacio activa: Haz clic y arrastra para desplazar una sección entera del diagrama.');
    } catch(err) {
        console.error("Error activating Space Tool:", err);
    }
}

// Programmatically activate BPMN-JS Hand Tool (Canvas panning / drag)
function activateHandTool(event) {
    if (!bpmnModeler) return;
    try {
        const handTool = bpmnModeler.get('handTool');
        handTool.activateDrag(event);
        toastr.info('Herramienta Mano activa: Haz clic y arrastra para desplazarte por el lienzo.');
    } catch(err) {
        console.error("Error activating Hand Tool:", err);
    }
}

// Open context pad floating color selector popover
function openContextColorPopover(event, element) {
    // Prevent default contextpad click event behavior
    if (event.preventDefault) event.preventDefault();
    if (event.stopPropagation) event.stopPropagation();
    
    // Close existing popover if any
    $('#bpmn-context-color-popover').remove();
    
    // Create new popover container
    const popover = $('<div id="bpmn-context-color-popover" class="card shadow-lg p-2 border" style="position: fixed; z-index: 9999; width: 220px; background: rgba(255, 255, 255, 0.98); border-radius: 8px; backdrop-filter: blur(8px); margin: 0; box-shadow: 0 10px 30px rgba(0,0,0,0.15) !important;"></div>');
    
    // Populate with header, solid and gradient color options
    let html = `
        <div class="d-flex justify-content-between align-items-center mb-2 px-1">
            <span class="font-weight-bold" style="font-size: 0.8rem; color: #002A54;"><i class="fas fa-palette mr-1"></i> Color de Relleno</span>
            <button class="btn btn-xs btn-link p-0 text-muted" onclick="$('#bpmn-context-color-popover').remove()"><i class="fas fa-times"></i></button>
        </div>
        <div class="px-1" style="font-size: 0.72rem; font-weight: bold; opacity: 0.8; margin-bottom: 4px;">Sólido</div>
        <div class="d-flex flex-wrap mb-2 px-1" style="gap: 6px;" id="ctx-colors-solid">
            <button class="color-badge" data-stroke="#000000" data-fill="#ffffff" style="background:#ffffff; border: 1.5px solid #000000; width:16px; height:16px; border-radius:50%; padding:0; cursor:pointer;" title="Predeterminado"></button>
            <button class="color-badge" data-stroke="#002A54" data-fill="#E6F0FA" style="background:#E6F0FA; border: 1.5px solid #002A54; width:16px; height:16px; border-radius:50%; padding:0; cursor:pointer;" title="Azul Sonda"></button>
            <button class="color-badge" data-stroke="#FF5C05" data-fill="#FFEFE6" style="background:#FFEFE6; border: 1.5px solid #FF5C05; width:16px; height:16px; border-radius:50%; padding:0; cursor:pointer;" title="Naranja Sonda"></button>
            <button class="color-badge" data-stroke="#28a745" data-fill="#EBF7EE" style="background:#EBF7EE; border: 1.5px solid #28a745; width:16px; height:16px; border-radius:50%; padding:0; cursor:pointer;" title="Verde Éxito"></button>
            <button class="color-badge" data-stroke="#dc3545" data-fill="#FDF2F3" style="background:#FDF2F3; border: 1.5px solid #dc3545; width:16px; height:16px; border-radius:50%; padding:0; cursor:pointer;" title="Rojo Peligro"></button>
            <button class="color-badge" data-stroke="#ffc107" data-fill="#FFFDF2" style="background:#FFFDF2; border: 1.5px solid #ffc107; width:16px; height:16px; border-radius:50%; padding:0; cursor:pointer;" title="Amarillo Alerta"></button>
            <button class="color-badge" data-stroke="#6f42c1" data-fill="#F6F2FC" style="background:#F6F2FC; border: 1.5px solid #6f42c1; width:16px; height:16px; border-radius:50%; padding:0; cursor:pointer;" title="Púrpura"></button>
        </div>
        <div class="px-1" style="font-size: 0.72rem; font-weight: bold; opacity: 0.8; margin-bottom: 4px;">Degradado</div>
        <div class="d-flex flex-wrap mb-2 px-1" style="gap: 6px;" id="ctx-colors-gradient">
            <button class="color-badge" data-stroke="#002A54" data-fill="url(#gradient-sonda-blue)" style="background: linear-gradient(135deg, #E6F0FA 0%, #B3D4F5 100%); border: 1.5px solid #002A54; width:16px; height:16px; border-radius:50%; padding:0; cursor:pointer;" title="Degradado Azul"></button>
            <button class="color-badge" data-stroke="#FF5C05" data-fill="url(#gradient-sonda-orange)" style="background: linear-gradient(135deg, #FFEFE6 0%, #FFD9C6 100%); border: 1.5px solid #FF5C05; width:16px; height:16px; border-radius:50%; padding:0; cursor:pointer;" title="Degradado Naranja"></button>
            <button class="color-badge" data-stroke="#28a745" data-fill="url(#gradient-success-green)" style="background: linear-gradient(135deg, #EBF7EE 0%, #C7E9D0 100%); border: 1.5px solid #28a745; width:16px; height:16px; border-radius:50%; padding:0; cursor:pointer;" title="Degradado Verde"></button>
            <button class="color-badge" data-stroke="#dc3545" data-fill="url(#gradient-danger-red)" style="background: linear-gradient(135deg, #FDF2F3 0%, #F9D6D9 100%); border: 1.5px solid #dc3545; width:16px; height:16px; border-radius:50%; padding:0; cursor:pointer;" title="Degradado Rojo"></button>
            <button class="color-badge" data-stroke="#ffc107" data-fill="url(#gradient-warning-yellow)" style="background: linear-gradient(135deg, #FFFDF2 0%, #FFECA8 100%); border: 1.5px solid #ffc107; width:16px; height:16px; border-radius:50%; padding:0; cursor:pointer;" title="Degradado Amarillo"></button>
            <button class="color-badge" data-stroke="#6f42c1" data-fill="url(#gradient-purple)" style="background: linear-gradient(135deg, #F6F2FC 0%, #E3D5F7 100%); border: 1.5px solid #6f42c1; width:16px; height:16px; border-radius:50%; padding:0; cursor:pointer;" title="Degradado Púrpura"></button>
        </div>
    `;
    
    popover.html(html);
    
    // Position popover relative to contextpad click point
    let popoverX = event.clientX + 10;
    let popoverY = event.clientY - 10;
    
    // Adjust if running off viewport boundaries
    if (popoverX + 220 > window.innerWidth) {
        popoverX = event.clientX - 230;
    }
    if (popoverY + 180 > window.innerHeight) {
        popoverY = event.innerHeight - 190;
    }
    
    popover.css({
        left: popoverX + 'px',
        top: popoverY + 'px'
    });
    
    $('body').append(popover);
    
    // Click events on popover badges
    popover.find('.color-badge').on('click', function(e) {
        e.stopPropagation();
        const stroke = $(this).attr('data-stroke');
        const fill = $(this).attr('data-fill');
        
        applyColorToElement(element, stroke, fill);
        
        // Hide popover
        popover.remove();
        
        // Sync properties panel if the selected element matches
        if (selectedElement && selectedElement.id === element.id) {
            $('#prop-stroke').val(stroke);
            updateColorPickerActive(fill, stroke);
        }
        
        // Trigger commandStack change so that dirty status is updated
        try {
            bpmnModeler.get('commandStack')._fire('changed');
        } catch (err) {}
    });
}

// Click outside popover helper
$(document).on('mousedown', function(e) {
    if (!$(e.target).closest('#bpmn-context-color-popover').length && !$(e.target).closest('.djs-context-pad').length) {
        $('#bpmn-context-color-popover').remove();
    }
});
</script>
