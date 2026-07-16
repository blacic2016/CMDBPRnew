<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../src/auth.php';
require_once __DIR__ . '/../../../../src/permissions_helper.php';

require_login();

if (!has_module_access('clientes')) {
    header("Location: " . PUBLIC_URL_PREFIX . "/dashboard.php");
    exit;
}

$page_title = "Wi-Fi Monitor Pro";
$page_icon = "fas fa-wifi text-success";
$hide_content_header = true;
require_once __DIR__ . '/../../../partials/header.php';
?>

<!-- Wi-Fi Monitor scoped styles -->
<link rel="stylesheet" href="wifi_css/style.css?v=<?php echo time(); ?>">
<script src="https://unpkg.com/gojs/release/go.js"></script>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?php echo PUBLIC_URL_PREFIX; ?>/dashboard.php">Inicio</a></li>
                    <li class="breadcrumb-item active">Clientes</li>
                    <li class="breadcrumb-item active">SONDA</li>
                    <li class="breadcrumb-item active">iin</li>
                    <li class="breadcrumb-item active">Wi-Fi Monitor Pro</li>
                </ol>
            </div>
        </div>
    </div>
</div>

                <div class="wifi-monitor-body">
                    <div id="app">
                        <header class="wifi-header">
                            <div class="logo">Wi-Fi Monitor Pro</div>
                            <div class="search-container">
                                <input type="text" id="global-search" placeholder="Buscar AP o Cliente (IP/Nombre)...">
                                <div id="search-results" class="autocomplete-results"></div>
                            </div>
                            <div class="tabs">
                                <button class="tab-btn active" data-tab="topology">Topología Lógica</button>
                                <button class="tab-btn" data-tab="topo-2d">Topología 2D/3D</button>
                                <button class="tab-btn" data-tab="trajectory">Trayectoria</button>
                                <button class="tab-btn" data-tab="ap-analysis">Análisis de AP</button>
                                <button class="tab-btn" data-tab="heatmap">Mapa de Calor</button>
                                <button class="tab-btn" data-tab="floor-config">Configuración de Planos</button>
                                <button id="theme-toggle" class="tab-btn" title="Cambiar Tema">🌓</button>
                            </div>
                        </header>

                        <main class="wifi-main">
                            <!-- Tab 1: Topología -->
                            <section id="topology" class="tab-content active">
                                <div class="topology-layout">
                                    <div class="topology-main">
                                        <div class="view-header">
                                            <div class="filter-group">
                                                <label>Access Point:</label>
                                                <select id="ap-selector" multiple style="display: none;">
                                                    <option value="ALL" selected>TODOS</option>
                                                </select>
                                                
                                                <!-- Custom Multiselect -->
                                                <div class="custom-multiselect" id="ap-multiselect-container">
                                                    <div class="multiselect-trigger" id="ap-multiselect-trigger">
                                                        <span class="trigger-text">TODOS</span>
                                                        <span class="trigger-arrow">▼</span>
                                                    </div>
                                                    <div class="multiselect-dropdown" id="ap-multiselect-dropdown">
                                                        <div class="multiselect-actions">
                                                            <button type="button" id="ap-select-all" class="mini-action-btn">Todos</button>
                                                            <button type="button" id="ap-clear-all" class="mini-action-btn">Limpiar</button>
                                                        </div>
                                                        <div class="multiselect-options" id="ap-multiselect-options">
                                                            <!-- Dynamic checkboxes populated by JS -->
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="canvas-container">
                                            <canvas id="topology-canvas"></canvas>
                                            <div class="topology-controls">
                                                <span id="ap-count-badge" class="badge">APs: 0</span>
                                                <span id="client-count-badge" class="badge">Clientes: 0</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="topology-sidebar" id="topology-sidebar">
                                        <div class="sidebar-resize-handle" id="sidebar-resize-handle"></div>
                                        <div class="sidebar-tabs">
                                            <button class="sidebar-tab-btn active" data-sidebar-tab="ap-summary">Resumen APs</button>
                                            <button class="sidebar-tab-btn" data-sidebar-tab="top-clients">Top Consumidores</button>
                                        </div>
                                        <div class="sidebar-tab-content active" id="sidebar-tab-ap-summary">
                                            <div class="ap-summary-dashboard" id="ap-summary-dashboard">
                                                <!-- Populated dynamically by JS -->
                                            </div>
                                        </div>
                                        <div class="sidebar-tab-content" id="sidebar-tab-top-clients">
                                            <div class="top-clients-controls">
                                                <input type="text" id="top-clients-search" placeholder="🔍 Buscar cliente...">
                                                <select id="top-clients-limit">
                                                    <option value="5">Top 5</option>
                                                    <option value="10">Top 10</option>
                                                    <option value="15" selected>Top 15</option>
                                                    <option value="30">Top 30</option>
                                                    <option value="ALL">Todos</option>
                                                </select>
                                            </div>
                                            <div class="top-clients-list" id="top-clients-list">
                                                <!-- Ranked cards populated by JS -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <!-- Tab 2: Trayectoria -->
                            <section id="trajectory" class="tab-content">
                                <div class="trajectory-filters">
                                    <div class="filter-group">
                                        <label>Filtrar por AP:</label>
                                        <select id="traj-ap-selector">
                                            <option value="">Cualquiera</option>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label>Cliente:</label>
                                        <select id="client-selector">
                                            <option value="">Seleccione un cliente...</option>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label>Fecha de Inicio:</label>
                                        <input type="datetime-local" id="traj-start-time">
                                    </div>
                                    <div class="filter-group">
                                        <label>Duración:</label>
                                        <select id="traj-duration">
                                            <option value="3600">1 Hora</option>
                                            <option value="14400">4 Horas</option>
                                            <option value="28800" selected>8 Horas</option>
                                            <option value="86400">24 Horas</option>
                                        </select>
                                    </div>
                                    <button id="search-trajectory" class="action-btn">Analizar Trayectoria</button>
                                    <button id="last-location" class="action-btn" style="background:#005cc5">Última Ubicación</button>
                                </div>

                                <div id="trajectory-wrapper" style="display: flex; flex: 1; height: calc(100% - 70px);">
                                    <div id="trajectory-canvas-container" style="flex: 1; position: relative;">
                                        <canvas id="trajectory-canvas"></canvas>
                                        <div id="trajectory-legend" class="mini-legend"></div>
                                    </div>
                                    <div id="trajectory-details-sidebar" class="details-sidebar">
                                        <div class="empty-state">Seleccione un equipo para ver detalles</div>
                                    </div>
                                </div>
                            </section>

                            <!-- Tab: Análisis de AP -->
                            <section id="ap-analysis" class="tab-content">
                                <div class="analysis-layout">
                                    <div class="analysis-main">
                                        <div class="view-header">
                                            <div class="filter-group">
                                                <label>Buscar AP:</label>
                                                <input type="text" id="ap-analysis-search" placeholder="Buscar por nombre o IP...">
                                            </div>
                                            <div class="filter-group">
                                                <label>Estado:</label>
                                                <select id="ap-analysis-status">
                                                    <option value="ALL">Todos los Estados</option>
                                                    <option value="CRITICAL">Crítico</option>
                                                    <option value="WARNING">Advertencia</option>
                                                    <option value="HEALTHY">Saludable</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="analysis-summary-cards">
                                            <div class="summary-card">
                                                <span class="summary-label">Salud Promedio</span>
                                                <span class="summary-value" id="global-health-score">--</span>
                                            </div>
                                            <div class="summary-card">
                                                <span class="summary-label">APs Críticos</span>
                                                <span class="summary-value critical-text" id="global-critical-count">--</span>
                                            </div>
                                            <div class="summary-card">
                                                <span class="summary-label">Clientes Baja Señal</span>
                                                <span class="summary-value warning-text" id="global-low-snr-count">--</span>
                                            </div>
                                            <div class="summary-card">
                                                <span class="summary-label">Consumo Total</span>
                                                <span class="summary-value" id="global-total-bw">--</span>
                                            </div>
                                        </div>

                                        <div class="ap-analysis-grid" id="ap-analysis-grid">
                                            <!-- Populated dynamically by JS -->
                                        </div>
                                    </div>

                                    <div class="analysis-sidebar" id="analysis-sidebar">
                                        <div class="sidebar-header-title">Diagnóstico del Access Point</div>
                                        <div id="analysis-sidebar-content">
                                            <div class="empty-state">Seleccione un AP de la lista para ver su análisis detallado</div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <!-- Tab 3: Mapa de Calor -->
                            <section id="heatmap" class="tab-content">
                                <div class="heatmap-container">
                                    <div class="floor-map" data-floor="5">
                                        <div class="floor-title">Piso 5</div>
                                        <div class="grid" id="grid-5"></div>
                                    </div>
                                </div>
                            </section>

                            <section id="floor-config" class="tab-content">
                                <div class="floor-config-container">
                                    <div class="sidebar-config">
                                        <h3>Gestión de Planos</h3>
                                        <div class="floor-list" id="floor-config-list">
                                            <!-- List of saved floors -->
                                        </div>
                                        <button class="action-btn" id="btn-new-floor">+ Nuevo Piso</button>
                                        <hr style="border:0; border-top:1px solid var(--line-color); margin: 20px 0;">
                                        <h3>Paleta de APs</h3>
                                        <div id="ap-palette-div" style="height: 300px; background: var(--bg-primary); border: 1px solid var(--line-color); border-radius: 8px;"></div>
                                    </div>
                                    <div class="main-config">
                                        <div class="config-toolbar">
                                            <div class="toolbar-field">
                                                <label>Nombre:</label>
                                                <input type="text" id="floor-name" placeholder="Piso 5">
                                            </div>
                                            <div class="toolbar-field">
                                                <label>Escala:</label>
                                                <input type="number" id="floor-scale" step="0.01" value="0.05" title="m/px">
                                            </div>
                                            <div class="toolbar-field">
                                                <label>Grilla(m):</label>
                                                <input type="number" id="grid-meters" step="1" value="5">
                                            </div>
                                            <div class="toolbar-field">
                                                <label>AP Size:</label>
                                                <input type="range" id="ap-icon-size" min="5" max="30" value="12">
                                            </div>
                                            <input type="file" id="floor-image-input" style="display:none" accept="image/*">
                                            <button class="action-btn" onclick="document.getElementById('floor-image-input').click()">Subir Plano</button>
                                            <div class="zoom-controls">
                                                <button class="action-btn" id="btn-zoom-in" title="Zoom In">+</button>
                                                <button class="action-btn" id="btn-zoom-out" title="Zoom Out">-</button>
                                                <button class="action-btn" id="btn-center" title="Centrar">⌖</button>
                                            </div>
                                            <button class="action-btn" id="btn-save-floor" style="background:#005cc5">Guardar Todo</button>
                                        </div>
                                        <div id="floor-diagram-div" style="flex: 1; background: #000; border-radius: 12px; border: 1px solid var(--line-color);"></div>
                                    </div>
                                </div>
                            </section>

                            <section id="topo-2d" class="tab-content">
                                <div class="view-header">
                                    <div class="filter-group">
                                        <label>Seleccionar Piso:</label>
                                        <select id="topo-2d-floor-select">
                                            <option value="">Cualquiera</option>
                                        </select>
                                    </div>
                                </div>
                                <div id="topo-2d-container" class="visual-view" style="position:relative">
                                    <canvas id="topo-2d-canvas"></canvas>
                                    <div id="topo-2d-legend" class="mini-legend"></div>
                                </div>
                            </section>
                        </main>

                        <div class="modal-overlay" id="modal-overlay">
                            <div class="modal" id="details-modal">
                                <h3 id="modal-title">Detalles del Equipo</h3>
                                <div id="modal-body">
                                    <!-- Dynamic content -->
                                </div>
                                <button class="close-modal" id="close-modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </div>

<!-- Scripts del Wi-Fi Monitor -->
<script src="wifi_js/app.js?v=<?php echo time(); ?>"></script>
<script src="wifi_js/topology.js?v=<?php echo time(); ?>"></script>
<script src="wifi_js/trajectory.js?v=<?php echo time(); ?>"></script>
<script src="wifi_js/ap_analysis.js?v=<?php echo time(); ?>"></script>
<script src="wifi_js/heatmap.js?v=<?php echo time(); ?>"></script>
<script src="wifi_js/floor_config.js?v=<?php echo time(); ?>"></script>
<script src="wifi_js/topology_2d.js?v=<?php echo time(); ?>"></script>

<?php
require_once __DIR__ . '/../../../partials/footer.php';
?>
