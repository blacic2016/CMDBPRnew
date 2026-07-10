<?php
/**
 * Módulo de Flujos y Diagramas (Mermaid) - CMDB VILASECA
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

$page_title = "Flujos de Procesos";
include 'partials/header.php';
?>

<style>
/* Modern Glassmorphic & Brand Palette */
.flows-container {
    padding: 1.5rem;
}

.flows-card-item {
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    cursor: pointer;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
}

.flows-card-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(0, 184, 212, 0.15);
    border-color: var(--sonda-cyan);
}

.code-editor-area {
    font-family: 'Courier New', Courier, monospace;
    font-size: 0.9rem;
    line-height: 1.4;
    background-color: #1e1e1e !important;
    color: #d4d4d4 !important;
    border-radius: 6px;
    border: 1px solid #333;
    padding: 10px;
    resize: vertical;
}

/* Zoom Container Style */
#svg-zoom-container {
    width: 100%;
    height: 520px;
    border: 1px solid var(--border-color);
    background-color: #f8f9fa;
    overflow: hidden;
    position: relative;
    cursor: grab;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
}

body.dark-mode #svg-zoom-container {
    background-color: #171d24;
}

#svg-zoom-container svg {
    max-width: 100% !important;
    max-height: 100% !important;
    transform-origin: center center;
    transition: transform 0.1s ease-out;
}

#svg-zoom-container:fullscreen {
    width: 100vw !important;
    height: 100vh !important;
    max-width: none !important;
    max-height: none !important;
    background-color: #171d24 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 0 !important;
    margin: 0 !important;
}

body:not(.dark-mode) #svg-zoom-container:fullscreen {
    background-color: #f8f9fa !important;
}

#svg-zoom-container:fullscreen svg {
    max-width: 90vw !important;
    max-height: 90vh !important;
}

/* Control bar hovering preview */
.zoom-controls {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 10;
    background: rgba(0, 42, 84, 0.85);
    padding: 5px;
    border-radius: 8px;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    display: flex;
    gap: 5px;
}

.zoom-controls .btn {
    color: #fff;
    border: none;
    background: transparent;
    padding: 5px 10px;
}

.zoom-controls .btn:hover {
    background: var(--sonda-orange);
    color: #fff;
}

.template-badge {
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.75rem;
    margin-right: 5px;
    margin-bottom: 5px;
}

.template-badge:hover {
    transform: scale(1.05);
}

.nav-tabs .nav-link {
    font-weight: 600;
    border: none;
    color: var(--text-color);
    opacity: 0.8;
}

.nav-tabs .nav-link.active {
    color: var(--sonda-cyan) !important;
    border-bottom: 3px solid var(--sonda-cyan);
    background-color: transparent !important;
    opacity: 1;
}
</style>

<div class="flows-container">
    <div class="card card-primary card-outline card-outline-tabs shadow-sm">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs" id="flows-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="list-tab" data-toggle="tab" href="#list-tab-content" role="tab" aria-controls="list-tab-content" aria-selected="true">
                        <i class="fas fa-list mr-2 text-info"></i>Flujos Guardados
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="editor-tab" data-toggle="tab" href="#editor-tab-content" role="tab" aria-controls="editor-tab-content" aria-selected="false">
                        <i class="fas fa-edit mr-2 text-warning"></i>Editor de Diagrama
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="card-body">
            <div class="tab-content" id="flows-tabs-tabContent">
                
                <!-- TAB 1: SAVED FLOWS LIST -->
                <div class="tab-pane fade show active" id="list-tab-content" role="tabpanel" aria-labelledby="list-tab">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="input-group" style="max-width: 400px;">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-transparent border-right-0"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" id="search-flow" class="form-control border-left-0" placeholder="Buscar diagramas por título o descripción...">
                        </div>
                        <button class="btn btn-success font-weight-bold" onclick="createNewFlow()">
                            <i class="fas fa-plus mr-2"></i>Crear Nuevo Diagrama
                        </button>
                    </div>
                    
                    <!-- Flows Cards Grid -->
                    <div class="row" id="flows-grid-container">
                        <!-- Dynamic content -->
                    </div>
                </div>
                
                <!-- TAB 2: INTERACTIVE EDITOR -->
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

                    <!-- Editor Top Control Bar -->
                    <div class="row align-items-center bg-light p-3 rounded mb-4 shadow-sm border" id="editor-control-header" style="background-color: rgba(0,0,0,0.02) !important;">
                        <input type="hidden" id="flow-id" value="0">
                        
                        <div class="col-md-4">
                            <label for="flow-title" class="font-weight-bold mb-1">Título del Diagrama *</label>
                            <input type="text" id="flow-title" class="form-control" placeholder="Ej. Ciclo de Vida del Proyecto">
                        </div>
                        
                        <div class="col-md-4">
                            <label for="flow-desc" class="font-weight-bold mb-1">Descripción / Notas</label>
                            <input type="text" id="flow-desc" class="form-control" placeholder="Resumen o detalles adicionales...">
                        </div>
                        
                        <div class="col-md-4 text-md-right mt-3 mt-md-0 d-flex justify-content-md-end align-items-center gap-2" style="gap: 8px;">
                            <span id="save-status" class="badge badge-secondary mr-2 px-3 py-2" style="font-size:0.8rem;">No Guardado</span>
                            
                            <!-- History Dropdown -->
                            <div class="dropdown d-none" id="history-dropdown-wrapper">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="historyDropdownBtn" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-history mr-1"></i> Historial
                                </button>
                                <div class="dropdown-menu dropdown-menu-right shadow-lg" id="history-items-container" aria-labelledby="historyDropdownBtn" style="max-height: 250px; overflow-y: auto; width: 280px;">
                                    <!-- Carga dinámica -->
                                </div>
                            </div>

                            <button class="btn btn-success" onclick="resetEditorAndCreate()" title="Crear un diagrama completamente nuevo">
                                <i class="fas fa-plus mr-1"></i> Nuevo
                            </button>
                            <button class="btn btn-primary" onclick="saveFlow()" title="Guardar modificaciones">
                                <i class="fas fa-save mr-1"></i> Guardar
                            </button>
                            <button class="btn btn-danger d-none" id="delete-flow-btn" onclick="deleteFlow()" title="Eliminar este diagrama">
                                <i class="fas fa-trash-alt mr-1"></i> Eliminar
                            </button>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Left Side: Mermaid Editor -->
                        <div class="col-md-5">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="font-weight-bold mb-0"><i class="fas fa-code mr-1 text-secondary"></i> Código Fuente Mermaid</label>
                            </div>
                            
                            <!-- Snippet Buttons -->
                            <div class="mb-2 p-2 border rounded bg-light" style="background-color: rgba(0,0,0,0.01) !important;">
                                <span class="badge badge-info template-badge" onclick="loadTemplate('flowchart')"><i class="fas fa-project-diagram mr-1"></i> Flowchart</span>
                                <span class="badge badge-info template-badge" onclick="loadTemplate('sequence')"><i class="fas fa-exchange-alt mr-1"></i> Sequence</span>
                                <span class="badge badge-info template-badge" onclick="loadTemplate('gantt')"><i class="fas fa-tasks mr-1"></i> Gantt</span>
                                <span class="badge badge-info template-badge" onclick="loadTemplate('state')"><i class="fas fa-circle-notch mr-1"></i> State</span>
                                <span class="badge badge-info template-badge" onclick="loadTemplate('class')"><i class="fas fa-cube mr-1"></i> Class</span>
                            </div>
                            
                            <textarea id="mermaid-code" class="form-control code-editor-area w-100" style="height: 480px;" placeholder="graph TD&#10;  A[Inicio] --> B(Fin)"></textarea>
                        </div>
                        
                        <!-- Right Side: Live Renderer -->
                        <div class="col-md-7">
                            <label class="font-weight-bold"><i class="fas fa-eye mr-1 text-success"></i> Visualización en Vivo</label>
                            <div id="svg-zoom-container" class="position-relative">
                                <!-- Float Toolbar -->
                                <div class="zoom-controls">
                                    <button class="btn btn-xs" onclick="zoomIn()" title="Acercar (Lupa +)"><i class="fas fa-search-plus"></i></button>
                                    <button class="btn btn-xs" onclick="zoomOut()" title="Alejar (Lupa -)"><i class="fas fa-search-minus"></i></button>
                                    <button class="btn btn-xs" onclick="resetZoom()" title="Restablecer (1:1)"><i class="fas fa-sync-alt"></i></button>
                                    <button class="btn btn-xs" onclick="copySVGToClipboard()" title="Copiar SVG"><i class="fas fa-copy"></i></button>
                                    <button class="btn btn-xs" onclick="toggleFullscreen()" title="Pantalla Completa"><i class="fas fa-expand"></i></button>
                                </div>
                                <div id="preview-wrapper" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                    <div id="preview-container" class="w-100 h-100 d-flex align-items-center justify-content-center">
                                        <div class="text-muted p-4 text-center">
                                            <i class="fas fa-code fa-2x mb-2 text-info"></i>
                                            <p>Carga una plantilla o edita el código para ver el flujo en tiempo real.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'partials/footer.php'; ?>

<!-- Load Mermaid JS -->
<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>

<script>
// Initialize Mermaid
mermaid.initialize({
    startOnLoad: false,
    theme: 'default',
    securityLevel: 'loose'
});

// App State
let activeFlowId = 0;
let savedCode = '';
let scale = 1.0;
let panX = 0;
let panY = 0;
let isDragging = false;
let startX = 0;
let startY = 0;
let debounceTimer = null;

$(function() {
    loadFlowsGrid();
    
    // Live compile on editor inputs
    $('#mermaid-code').on('input', function() {
        checkSavedStatus();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(renderLiveMermaid, 300);
    });

    // Client-side grid filtering
    $('#search-flow').on('input', function() {
        let q = $(this).val().toLowerCase();
        $('.flow-grid-card-wrapper').each(function() {
            let title = $(this).find('.card-title').text().toLowerCase();
            let desc = $(this).find('.card-text').text().toLowerCase();
            $(this).toggle(title.indexOf(q) > -1 || desc.indexOf(q) > -1);
        });
    });

    // Zoom/Pan drag listeners
    let $container = $('#svg-zoom-container');
    $container.on('mousedown', function(e) {
        if ($(e.target).closest('.zoom-controls').length) return;
        
        isDragging = true;
        $container.css('cursor', 'grabbing');
        startX = e.clientX - panX;
        startY = e.clientY - panY;
        e.preventDefault();
    });

    $(document).on('mousemove', function(e) {
        if (isDragging) {
            panX = e.clientX - startX;
            panY = e.clientY - startY;
            updateTransform();
        }
    });

    $(document).on('mouseup mouseleave', function() {
        if (isDragging) {
            isDragging = false;
            $container.css('cursor', 'grab');
        }
    });
});

// Zoom & Pan transforms
function updateTransform() {
    let svg = $('#preview-container svg');
    if (svg.length) {
        svg.css({
            'transform': `translate(${panX}px, ${panY}px) scale(${scale})`,
            'transition': isDragging ? 'none' : 'transform 0.1s ease-out'
        });
    }
}

function zoomIn() {
    scale = Math.min(scale + 0.15, 3.0);
    updateTransform();
}

function zoomOut() {
    scale = Math.max(scale - 0.15, 0.3);
    updateTransform();
}

function resetZoom() {
    scale = 1.0;
    panX = 0;
    panY = 0;
    updateTransform();
}

function toggleFullscreen() {
    let container = document.getElementById('svg-zoom-container');
    if (!document.fullscreenElement) {
        container.requestFullscreen().then(() => {
            setTimeout(resetZoom, 50);
        }).catch(err => {
            toastr.error('No se pudo activar pantalla completa: ' + err.message);
        });
    } else {
        document.exitFullscreen();
    }
}

// Check saved state compared to editor content
function checkSavedStatus() {
    let currentCode = $('#mermaid-code').val();
    if (currentCode === savedCode && currentCode.trim() !== '') {
        $('#save-status').text('Guardado').removeClass('badge-secondary').addClass('badge-success');
    } else {
        $('#save-status').text('Sin Guardar').removeClass('badge-success').addClass('badge-secondary');
    }
}

// Render code live
function renderLiveMermaid() {
    let code = $('#mermaid-code').val().trim();
    if (!code) {
        $('#preview-container').html('<div class="text-muted p-4">Escribe código Mermaid para ver el diagrama...</div>');
        return;
    }

    let randomId = 'mermaid-svg-' + Math.floor(Math.random() * 10000);
    try {
        mermaid.render(randomId, code).then(({ svg }) => {
            $('#preview-container').html(svg);
            resetZoom();
        }).catch(err => {
            let errStr = err.message || err.toString();
            $('#preview-container').html(`<div class="alert alert-danger text-left p-3 w-100 m-2" style="font-family: monospace; font-size: 0.82rem; white-space: pre-wrap;"><i class="fas fa-exclamation-triangle mr-2"></i>Error de sintaxis Mermaid:<br><br>${escapeHtml(errStr)}</div>`);
        });
    } catch(e) {
        $('#preview-container').html(`<div class="alert alert-danger text-left p-3 w-100 m-2" style="font-family: monospace; font-size: 0.82rem;"><i class="fas fa-exclamation-triangle mr-2"></i>Excepción de Render:<br><br>${escapeHtml(e.message)}</div>`);
    }
}

function escapeHtml(text) {
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Load grid catalog in Tab 1
function loadFlowsGrid() {
    $.getJSON('api_flows.php', { action: 'list' }, function(res) {
        if (res.success) {
            let html = '';
            if (res.flows.length === 0) {
                html = `
                <div class="col-12 text-center py-5">
                    <i class="fas fa-route fa-3x text-muted mb-3"></i>
                    <h5>No hay diagramas creados aún</h5>
                    <p class="text-muted">Comienza creando tu primer diagrama de flujos interactivo.</p>
                    <button class="btn btn-success" onclick="createNewFlow()">Crear Diagrama</button>
                </div>`;
            } else {
                res.flows.forEach(flow => {
                    let desc = flow.description ? flow.description : 'Sin descripción adicional.';
                    let dateStr = flow.updated_at.substring(0, 16);
                    html += `
                    <div class="col-md-4 mb-3 flow-grid-card-wrapper">
                        <div class="card h-100 flows-card-item shadow-sm" onclick="loadFlowAndSwitchTab(${flow.id})">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title font-weight-bold text-primary mb-2 text-truncate">${escapeHtml(flow.title)}</h5>
                                <p class="card-text text-muted flex-grow-1" style="font-size:0.88rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                    ${escapeHtml(desc)}
                                </p>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted"><i class="far fa-calendar-alt mr-1"></i>${dateStr}</small>
                                    <span class="btn btn-xs btn-outline-info font-weight-bold">Cargar <i class="fas fa-arrow-right ml-1"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>`;
                });
            }
            $('#flows-grid-container').html(html);
        }
    });
}

// Load a specific flow and switch to Tab 2
function loadFlowAndSwitchTab(id) {
    activeFlowId = id;
    $.getJSON('api_flows.php', { action: 'get', id: id }, function(res) {
        if (res.success) {
            let f = res.flow;
            $('#flow-id').val(f.id);
            $('#flow-title').val(f.title);
            $('#flow-desc').val(f.description);
            $('#mermaid-code').val(f.code);
            savedCode = f.code;
            
            $('#delete-flow-btn').removeClass('d-none');
            $('#history-view-banner').addClass('d-none');
            
            checkSavedStatus();
            renderLiveMermaid();
            loadHistoryDropdown(f.id);
            
            // Switch Tab to Editor
            $('#editor-tab').tab('show');
            toastr.info(`Diagrama "${f.title}" cargado.`);
        } else {
            Swal.fire('Error', res.error, 'error');
        }
    });
}

// Action: Switch to Tab 2 and clear fields for a new diagram
function createNewFlow() {
    resetEditorFields();
    $('#editor-tab').tab('show');
}

// Action: Clear fields directly from editor tab
function resetEditorAndCreate() {
    Swal.fire({
        title: '¿Crear nuevo diagrama?',
        text: 'Se limpiará el editor actual. Asegúrate de haber guardado tus cambios.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, nuevo',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            resetEditorFields();
            toastr.success('Editor listo para un nuevo diagrama.');
        }
    });
}

function resetEditorFields() {
    activeFlowId = 0;
    $('#flow-id').val('0');
    $('#flow-title').val('');
    $('#flow-desc').val('');
    $('#mermaid-code').val('');
    savedCode = '';
    
    $('#delete-flow-btn').addClass('d-none');
    $('#history-dropdown-wrapper').addClass('d-none');
    $('#history-view-banner').addClass('d-none');
    checkSavedStatus();
    $('#preview-container').html(`
        <div class="text-muted p-4 text-center">
            <i class="fas fa-code fa-2x mb-2 text-info"></i>
            <p>Carga una plantilla o edita el código para ver el flujo en tiempo real.</p>
        </div>
    `);
}

// Save or update flow
function saveFlow() {
    let id = $('#flow-id').val();
    let title = $('#flow-title').val().trim();
    let description = $('#flow-desc').val().trim();
    let code = $('#mermaid-code').val();

    if (!title) {
        Swal.fire('Atención', 'Por favor ingresa un título para el diagrama.', 'warning');
        return;
    }
    if (!code.trim()) {
        Swal.fire('Atención', 'El código del diagrama no puede estar vacío.', 'warning');
        return;
    }

    $.post('api_flows.php', {
        action: 'save',
        id: id,
        title: title,
        description: description,
        code: code
    }, function(res) {
        if (res.success) {
            toastr.success(res.message || 'Guardado correctamente.');
            savedCode = code;
            activeFlowId = res.id;
            
            $('#flow-id').val(res.id);
            $('#delete-flow-btn').removeClass('d-none');
            $('#history-view-banner').addClass('d-none');
            
            checkSavedStatus();
            loadFlowsGrid();
            loadHistoryDropdown(res.id);
        } else {
            Swal.fire('Error', res.error, 'error');
        }
    });
}

// Delete flow
function deleteFlow() {
    let id = $('#flow-id').val();
    if (id <= 0) return;

    Swal.fire({
        title: '¿Eliminar Diagrama?',
        text: 'Esta acción no se puede deshacer. ¿Seguro que deseas eliminar este flujo?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('api_flows.php', {
                action: 'delete',
                id: id
            }, function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    resetEditorFields();
                    loadFlowsGrid();
                    // Switch back to Tab 1
                    $('#list-tab').tab('show');
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            });
        }
    });
}

// Preload standard templates
function loadTemplate(type) {
    let code = '';
    switch (type) {
        case 'flowchart':
            code = `graph TD
    A[Inicio de Proceso] --> B{¿Requiere Aprobación?}
    B -- Sí --> C[Enviar a Gerencia]
    B -- No --> D[Procesamiento Directo]
    C --> E{¿Aprobado?}
    E -- Sí --> D
    E -- No --> F[Rechazar y Notificar]
    D --> G[Registrar en CMDB]
    G --> H[Fin del Flujo]`;
            break;
        case 'sequence':
            code = `sequenceDiagram
    autonumber
    actor Operador
    participant WebUI as CMDB Frontend
    participant API as CMDB API Engine
    participant DB as MySQL Database
    participant ZBX as Zabbix Server

    Operador->>WebUI: Crear Nuevo Proyecto
    WebUI->>API: POST /api_project.php (action: save)
    API->>DB: INSERT INTO projects (...)
    DB-->>API: Confirmación (id: 42)
    API->>ZBX: Registrar Hostgroup de Monitoreo
    ZBX-->>API: Confirmación Hostgroup OK
    API-->>WebUI: Respuesta Exitosa (JSON)
    WebUI-->>Operador: Mostrar Notificación de Éxito`;
            break;
        case 'gantt':
            code = `gantt
    title Cronograma General de Proyecto
    dateFormat  YYYY-MM-DD
    section Fase Inicial
    Levantamiento de Requerimientos :a1, 2026-07-01, 7d
    Aprobación de Arquitectura     :a2, after a1, 3d
    section Fase Ejecución
    Despliegue de Servidores       :b1, 2026-07-10, 10d
    Configuración de Zabbix        :b2, after b1, 8d
    section Cierre
    Pruebas y Certificación        :c1, after b2, 5d`;
            break;
        case 'state':
            code = `stateDiagram-v2
    [*] --> Nuevo
    Nuevo --> Planificado : Definir Fechas
    Planificado --> EnEjecucion : Play (Iniciar)
    EnEjecucion --> Pausado : Detener
    Pausado --> EnEjecucion : Reanudar
    EnEjecucion --> Completado : Cerrar todas las tareas
    Completado --> [*]`;
            break;
        case 'class':
            code = `classDiagram
    class Project {
        +int id
        +string code
        +string name
        +datetime start_date
        +datetime end_date
        +calculateProgress()
    }
    class Milestone {
        +int id
        +string code
        +string name
        +datetime estimated_start_date
        +float progress_percentage
    }
    class Task {
        +int id
        +string code
        +string title
        +string status
        +int progress_percentage
    }
    Project "1" *-- "many" Milestone : contiene
    Milestone "1" *-- "many" Task : agrupa`;
            break;
    }
    
    $('#mermaid-code').val(code);
    checkSavedStatus();
    renderLiveMermaid();
}

// Copy SVG to clipboard
function copySVGToClipboard() {
    let svgHtml = $('#preview-container').html();
    if (!svgHtml || svgHtml.indexOf('<svg') === -1) {
        toastr.warning('No hay ningún diagrama renderizado para copiar.');
        return;
    }
    navigator.clipboard.writeText(svgHtml).then(() => {
        toastr.success('Código SVG copiado al portapapeles.');
    }).catch(err => {
        toastr.error('Error al copiar: ' + err);
    });
}

// History Revision helper functions
function loadHistoryDropdown(flowId) {
    if (flowId <= 0) return;
    $.getJSON('api_flows.php', { action: 'history', flow_id: flowId }, function(res) {
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
    $.getJSON('api_flows.php', { action: 'get_history', id: historyId }, function(res) {
        if (res.success) {
            let v = res.version;
            $('#flow-title').val(v.title);
            $('#flow-desc').val(v.description);
            $('#mermaid-code').val(v.code);
            
            $('#history-date-span').text(dateCreated);
            $('#history-view-banner').removeClass('d-none');
            
            checkSavedStatus();
            renderLiveMermaid();
            toastr.warning(`Mostrando versión histórica del ${dateCreated}`);
        } else {
            Swal.fire('Error', res.error, 'error');
        }
    });
}

function restoreActiveVersion() {
    Swal.fire({
        title: '¿Restaurar esta versión?',
        text: 'Esta versión histórica reemplazará a la versión actual al guardar.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, restaurar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            saveFlow();
        }
    });
}

function exitHistoryView() {
    if (activeFlowId > 0) {
        loadFlowAndSwitchTab(activeFlowId);
    }
}
</script>
