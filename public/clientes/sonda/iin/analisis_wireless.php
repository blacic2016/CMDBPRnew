<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../src/auth.php';
require_once __DIR__ . '/../../../../src/permissions_helper.php';

require_login();

if (!has_module_access('clientes')) {
    header("Location: " . PUBLIC_URL_PREFIX . "/dashboard.php");
    exit;
}

$page_title = "Dashboard Aruba - Monitoreo Avanzado";
$page_icon = "fas fa-network-wired text-primary";
$hide_content_header = true;
require_once __DIR__ . '/../../../partials/header.php';
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

<style>
    /* Scoped styling to protect AdminLTE layout and avoid style bleeding */
    /* Break out of standard AdminLTE page margins/paddings to cover 100% of the screen */
    .content-wrapper > .content,
    .content-wrapper > .content > .container-fluid {
        padding: 0 !important;
        margin: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
    }
    .analisis-wireless-wrapper {
        --primary-bg: #ffffff;
        --secondary-bg: #f4f6f9;
        --accent-bg: #e9ecef;
        --card-bg: #ffffff;
        --text-primary: #000000;
        --text-secondary: #495057;
        --accent-color: #ff5c05;
        --success-color: #28a745;
        --warning-color: #ffc107;
        --danger-color: #ff4757;
        --orange-color: #ff5c05;
        --border-color: #dee2e6;
        --button-text-color: #ffffff;
        background: linear-gradient(135deg, var(--primary-bg) 0%, var(--secondary-bg) 100%);
        color: var(--text-primary);
        font-family: 'Inter', sans-serif;
        padding: 20px;
        border-radius: 8px;
        position: relative;
    }

    .analisis-wireless-wrapper.dark-mode {
        --primary-bg: #101B31;
        --secondary-bg: #0b1323;
        --accent-bg: #1f3050;
        --card-bg: #1a2844;
        --text-primary: #ffffff;
        --text-secondary: #cddbe8;
        --accent-color: #ff5c05;
        --success-color: #c0da20;
        --warning-color: #ffb800;
        --danger-color: #ff4757;
        --orange-color: #ff5c05;
        --border-color: #1f3050;
        --button-text-color: #ffffff;
    }

    .analisis-wireless-wrapper.gray-mode {
        --primary-bg: #e0e0e0;
        --secondary-bg: #cccccc;
        --accent-bg: #bbbbbb;
        --card-bg: #e0e0e0;
        --text-primary: #333333;
        --text-secondary: #555555;
        --accent-color: #6c757d;
        --success-color: #20c997;
        --warning-color: #ffc107;
        --danger-color: #dc3545;
        --orange-color: #fd7e14;
        --border-color: #999999;
        --button-text-color: #ffffff;
    }

    .analisis-wireless-wrapper .header-aruba {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding: 12px 20px;
        background: var(--card-bg);
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border: 1px solid var(--border-color);
    }

    .analisis-wireless-wrapper .header-aruba h1 {
        font-size: 1.4em;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .analisis-wireless-wrapper .header-actions {
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .analisis-wireless-wrapper .header-icon-btn {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        border: 1px solid var(--border-color);
        background: var(--accent-bg);
        color: var(--text-primary);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        font-size: 1em;
    }

    .analisis-wireless-wrapper .header-icon-btn:hover {
        background: var(--accent-color);
        color: var(--button-text-color);
        transform: translateY(-2px);
    }

    .analisis-wireless-wrapper .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }

    .analisis-wireless-wrapper .stat-card {
        background: var(--card-bg);
        padding: 16px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border: 1px solid var(--border-color);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
    }

    .analisis-wireless-wrapper .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
    }

    .analisis-wireless-wrapper .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--accent-color), var(--success-color));
    }

    .analisis-wireless-wrapper .stat-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 10px;
    }

    .analisis-wireless-wrapper .stat-card h3 {
        font-size: 0.75em;
        color: var(--text-secondary);
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 500;
    }

    .analisis-wireless-wrapper .stat-card-icon {
        width: 28px;
        height: 28px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9em;
        background: var(--accent-bg);
        color: var(--accent-color);
    }

    .analisis-wireless-wrapper .stat-card .value {
        font-size: 1.6em;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 6px;
        line-height: 1.2;
    }

    .analisis-wireless-wrapper .stat-card .label {
        font-size: 0.75em;
        color: var(--text-secondary);
        margin-top: auto;
    }

    .analisis-wireless-wrapper .stat-card-progress {
        margin-top: 8px;
        height: 4px;
        background: var(--accent-bg);
        border-radius: 2px;
        overflow: hidden;
    }

    .analisis-wireless-wrapper .stat-card-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--accent-color), var(--success-color));
        border-radius: 3px;
        transition: width 0.8s ease;
    }

    .analisis-wireless-wrapper .stat-card.color-green { background-color: var(--success-color); }
    .analisis-wireless-wrapper .stat-card.color-yellow { background-color: var(--warning-color); }
    .analisis-wireless-wrapper .stat-card.color-orange { background-color: var(--orange-color); }
    .analisis-wireless-wrapper .stat-card.color-red { background-color: var(--danger-color); }

    .analisis-wireless-wrapper .stat-card.color-green *, 
    .analisis-wireless-wrapper .stat-card.color-yellow *, 
    .analisis-wireless-wrapper .stat-card.color-orange * { color: #000 !important; }
    .analisis-wireless-wrapper .stat-card.color-red * { color: #fff !important; }

    .analisis-wireless-wrapper .top-consumers-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 16px;
    }

    .analisis-wireless-wrapper .top-consumers {
        background: var(--card-bg);
        padding: 16px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }

    .analisis-wireless-wrapper .top-consumers h3 {
        font-size: 0.95em;
        color: var(--text-primary);
        margin-bottom: 12px;
        font-weight: 600;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 8px;
    }

    .analisis-wireless-wrapper .consumer-item {
        display: flex;
        align-items: center;
        padding: 10px;
        background: var(--accent-bg);
        border-radius: 6px;
        margin-bottom: 8px;
        transition: all 0.3s ease;
    }

    .analisis-wireless-wrapper .consumer-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(0, 212, 255, 0.2);
    }

    .analisis-wireless-wrapper .consumer-item.color-green { background-color: var(--success-color); }
    .analisis-wireless-wrapper .consumer-item.color-yellow { background-color: var(--warning-color); }
    .analisis-wireless-wrapper .consumer-item.color-orange { background-color: var(--orange-color); }
    .analisis-wireless-wrapper .consumer-item.color-red { background-color: var(--danger-color); }

    .analisis-wireless-wrapper .consumer-item.color-green *, 
    .analisis-wireless-wrapper .consumer-item.color-yellow *, 
    .analisis-wireless-wrapper .consumer-item.color-orange * { color: #000 !important; }
    .analisis-wireless-wrapper .consumer-item.color-red * { color: #fff !important; }

    .analisis-wireless-wrapper .consumer-rank {
        font-size: 1.2em;
        font-weight: 700;
        color: var(--accent-color);
        margin-right: 15px;
        width: 30px;
    }

    .analisis-wireless-wrapper .consumer-info {
        flex: 1;
        min-width: 0;
    }

    .analisis-wireless-wrapper .consumer-name {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .analisis-wireless-wrapper .consumer-details {
        font-size: 0.85em;
        color: var(--text-secondary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .analisis-wireless-wrapper .consumer-usage {
        text-align: right;
        font-weight: 600;
        color: var(--success-color);
        flex-shrink: 0;
    }

    .analisis-wireless-wrapper .charts-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 16px;
    }

    .analisis-wireless-wrapper .chart-card {
        background: var(--card-bg);
        padding: 16px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border: 1px solid var(--border-color);
    }

    .analisis-wireless-wrapper .chart-card h3 {
        font-size: 0.95em;
        color: var(--text-primary);
        margin-bottom: 12px;
        font-weight: 600;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 8px;
    }

    .analisis-wireless-wrapper .distribution-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        margin: 6px 0;
        background: var(--accent-bg);
        border-radius: 6px;
        border-left: 3px solid var(--accent-color);
        transition: all 0.3s ease;
    }

    .analisis-wireless-wrapper .distribution-item:hover {
        background: var(--secondary-bg);
        transform: translateX(5px);
    }

    .analisis-wireless-wrapper .distribution-label {
        font-weight: 500;
        color: var(--text-primary);
    }

    .analisis-wireless-wrapper .distribution-value {
        font-weight: 600;
        color: var(--accent-color);
        font-size: 1.1em;
    }

    .analisis-wireless-wrapper .distribution-bar {
        width: 150px;
        height: 6px;
        background: var(--accent-bg);
        border-radius: 3px;
        overflow: hidden;
        margin-top: 5px;
    }

    .analisis-wireless-wrapper .distribution-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--accent-color), var(--success-color));
        transition: width 0.8s ease;
    }

    .analisis-wireless-wrapper .table-container {
        background: var(--card-bg);
        padding: 16px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border: 1px solid var(--border-color);
        overflow-x: auto;
        margin-bottom: 16px;
    }

    .analisis-wireless-wrapper .table-container h3 {
        font-size: 0.95em;
        color: var(--text-primary);
        margin-bottom: 12px;
        font-weight: 600;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 8px;
    }

    .analisis-wireless-wrapper table.dataTable {
        width: 100% !important;
        background-color: transparent;
        border-collapse: collapse;
    }

    .analisis-wireless-wrapper table.dataTable thead th {
        padding: 10px 8px;
        border-bottom: 2px solid var(--accent-color);
        color: var(--text-primary);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75em;
        letter-spacing: 0.5px;
        background: var(--accent-bg);
    }

    .analisis-wireless-wrapper table.dataTable tbody td {
        padding: 10px 8px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        font-size: 0.85em;
    }

    .analisis-wireless-wrapper table.dataTable tbody td.color-green { background-color: var(--success-color); color: #000; }
    .analisis-wireless-wrapper table.dataTable tbody td.color-yellow { background-color: var(--warning-color); color: #000; }
    .analisis-wireless-wrapper table.dataTable tbody td.color-orange { background-color: var(--orange-color); color: #000; }
    .analisis-wireless-wrapper table.dataTable tbody td.color-red { background-color: var(--danger-color); color: #fff; }

    .analisis-wireless-wrapper table.dataTable tbody tr:hover {
        background-color: var(--accent-bg);
    }

    .analisis-wireless-wrapper .status-indicator {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 8px;
    }

    .analisis-wireless-wrapper .status-online { background-color: var(--success-color); }
    .analisis-wireless-wrapper .status-warning { background-color: var(--warning-color); }
    .analisis-wireless-wrapper .status-offline { background-color: var(--danger-color); }

    .analisis-wireless-wrapper .usage-bar {
        width: 100px;
        height: 8px;
        background: var(--accent-bg);
        border-radius: 4px;
        overflow: hidden;
        display: inline-block;
        margin-right: 10px;
    }

    .analisis-wireless-wrapper .usage-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--success-color), var(--warning-color), var(--danger-color));
        transition: width 0.3s ease;
    }

    /* Floating buttons placed at the card corner or floating inside wrapper */
    .analisis-wireless-wrapper .floating-btn {
        position: fixed;
        right: 20px;
        border: none;
        border-radius: 50%;
        width: 48px;
        height: 48px;
        font-size: 1.2em;
        cursor: pointer;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.3);
        transition: all 0.3s ease;
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .analisis-wireless-wrapper .floating-btn:hover {
        transform: scale(1.1);
    }

    .analisis-wireless-wrapper .inventory-btn {
        bottom: 200px;
        background: var(--orange-color);
        color: #fff;
    }

    .analisis-wireless-wrapper .mute-alarm-btn {
        bottom: 140px;
        background: var(--accent-color);
        color: #fff;
    }

    .analisis-wireless-wrapper .mute-alarm-btn.muted {
        background-color: var(--danger-color);
        color: #fff;
    }

    .analisis-wireless-wrapper .theme-toggle-btn {
        bottom: 80px;
        background: var(--accent-color);
        color: #fff;
    }

    .analisis-wireless-wrapper .refresh-btn {
        bottom: 20px;
        background: var(--accent-color);
        color: #fff;
    }

    @media (max-width: 1200px) {
        .analisis-wireless-wrapper .top-consumers-grid,
        .analisis-wireless-wrapper .charts-container {
            grid-template-columns: 1fr;
        }
    }
</style>

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
                    <li class="breadcrumb-item active">Monitoreo Aruba</li>
                </ol>
            </div>
        </div>
    </div>
</div>

                <div class="analisis-wireless-wrapper" id="analisis-wrapper">
                    <!-- Header -->
                    <div class="header-aruba">
                        <div></div>
                        <div class="header-actions">
                            <button class="header-icon-btn" onclick="toggleTheme()" title="Cambiar tema">
                                <span id="themeIcon">&#9728;</span>
                            </button>
                            <button class="header-icon-btn" onclick="refreshData()" title="Actualizar datos">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button class="header-icon-btn" onclick="openInventoryManager()" title="Inventario">
                                <i class="fas fa-boxes"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Stats Grid -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <h3>Total Clientes</h3>
                                <div class="stat-card-icon"><i class="fas fa-users"></i></div>
                            </div>
                            <div class="value" id="totalClientes">0</div>
                            <div class="label">Dispositivos conectados</div>
                            <div class="stat-card-progress">
                                <div class="stat-card-progress-fill" id="progressClientes" style="width: 0%"></div>
                            </div>
                        </div>
                        <div class="stat-card" id="cardTxThroughputTotal">
                            <div class="stat-card-header">
                                <h3>Throughput Tx Total</h3>
                                <div class="stat-card-icon"><i class="fas fa-upload"></i></div>
                            </div>
                            <div class="value" id="txThroughputTotal">0 bps</div>
                            <div class="label">Total de datos transmitidos</div>
                            <div class="stat-card-progress">
                                <div class="stat-card-progress-fill" id="progressTx" style="width: 0%"></div>
                            </div>
                        </div>
                        <div class="stat-card" id="cardRxThroughputTotal">
                            <div class="stat-card-header">
                                <h3>Throughput Rx Total</h3>
                                <div class="stat-card-icon"><i class="fas fa-download"></i></div>
                            </div>
                            <div class="value" id="rxThroughputTotal">0 bps</div>
                            <div class="label">Total de datos recibidos</div>
                            <div class="stat-card-progress">
                                <div class="stat-card-progress-fill" id="progressRx" style="width: 0%"></div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <h3>Promedio SNR</h3>
                                <div class="stat-card-icon"><i class="fas fa-signal"></i></div>
                            </div>
                            <div class="value" id="avgSNR">0 dB</div>
                            <div class="label">Calidad de señal promedio</div>
                            <div class="stat-card-progress">
                                <div class="stat-card-progress-fill" id="progressSNR" style="width: 0%"></div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <h3>Clientes Activos</h3>
                                <div class="stat-card-icon"><i class="fas fa-check-circle"></i></div>
                            </div>
                            <div class="value" id="activeClients">0</div>
                            <div class="label">Últimos 5 minutos</div>
                            <div class="stat-card-progress">
                                <div class="stat-card-progress-fill" id="progressActive" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Consumers Grid -->
                    <div class="top-consumers-grid">
                        <div class="top-consumers">
                            <h3><i class="fas fa-chevron-circle-up mr-2 text-warning"></i>Top 10 Consumidores de Throughput Tx</h3>
                            <div id="topTxConsumers"></div>
                        </div>

                        <div class="top-consumers">
                            <h3><i class="fas fa-chevron-circle-down mr-2 text-success"></i>Top 10 Consumidores de Throughput Rx</h3>
                            <div id="topRxConsumers"></div>
                        </div>
                    </div>

                    <!-- Charts Container -->
                    <div class="charts-container">
                        <div class="chart-card">
                            <h3><i class="fas fa-laptop-code mr-2"></i>Distribución por Sistema Operativo</h3>
                            <div id="osDistribution" class="distribution-list"></div>
                        </div>
                        <div class="chart-card">
                            <h3><i class="fas fa-chart-bar mr-2"></i>Distribución de Throughput</h3>
                            <div id="throughputDistribution" class="distribution-list"></div>
                        </div>
                    </div>

                    <!-- Table Container -->
                    <div class="table-container">
                        <h3><i class="fas fa-table mr-2"></i>Tabla Detallada de Clientes (Última hora)</h3>
                        <table id="tablaClientes" class="display nowrap" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Estado</th>
                                    <th>Cliente</th>
                                    <th>Hostname</th>
                                    <th>IP</th>
                                    <th>MAC</th>
                                    <th>OS</th>
                                    <th>AP</th>
                                    <th>SNR</th>
                                    <th>Throughput Rx</th>
                                    <th>Throughput Tx</th>
                                    <th>Última Actualización</th>
                                </tr>
                            </thead>
                        </table>
                    </div>

                    <!-- Floating Buttons -->
                    <button class="floating-btn inventory-btn" onclick="openInventoryManager()" title="Gestionar Inventario de Equipos">
                        <i class="fas fa-boxes"></i>
                    </button>
                    <button class="floating-btn mute-alarm-btn" onclick="toggleMute()" title="Silenciar/Activar Alarma">
                        <span id="muteIcon"><i class="fas fa-volume-up"></i></span>
                    </button>
                    <button class="floating-btn theme-toggle-btn" onclick="toggleTheme()" title="Cambiar tema (Oscuro/Claro/Gris)">
                        <span id="themeIcon">&#9728;</span>
                    </button>
                    <button class="floating-btn refresh-btn" onclick="refreshData()" title="Actualizar datos">
                        <i class="fas fa-sync-alt"></i>
                    </button>

                    <audio id="alarmSound" src="alert_high.mp3" preload="auto" loop></audio>
                </div>

<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>

<script>
    let datosClientes = [];
    let tabla;
    let currentThemeIndex = 0;
    const themes = ['', 'dark-mode', 'gray-mode'];
    const themeIcons = ['&#9728;', '&#127762;', '&#127780;'];

    let isAlarmMuted = false;

    // Variables de umbral de throughput en Mbps
    const throughputThresholds = {
        red: 40,
        orange: 25,
        yellow: 10,
    };
    const alarmThresholdRedMbps = throughputThresholds.red;
    const alarmThresholdOrangeMbps = throughputThresholds.orange;

    const alarmSound = document.getElementById('alarmSound');

    function getThroughputColorClass(mbps) {
        if (mbps >= throughputThresholds.red) return 'color-red';
        if (mbps >= throughputThresholds.orange) return 'color-orange';
        if (mbps >= throughputThresholds.yellow) return 'color-yellow';
        return 'color-green';
    }

    function formatBps(bps, decimals = 2) {
        if (bps === 0 || isNaN(bps)) return '0 bps';
        const k = 1000;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
        const i = Math.floor(Math.log(bps) / Math.log(k));
        return parseFloat((bps / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    function formatFechaEcuador(fechaString) {
        if (!fechaString) return 'Sin fecha';
        try {
            const fecha = new Date(fechaString);
            if (isNaN(fecha.getTime())) return fechaString;
            
            const opciones = {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                timeZone: 'America/Guayaquil',
                hour12: false
            };
            
            return fecha.toLocaleString('es-EC', opciones);
        } catch (e) {
            return fechaString;
        }
    }

    function determinarEstado(snr, lastdate) {
        const now = new Date();
        const lastUpdate = new Date(lastdate);
        const diffMinutes = (now - lastUpdate) / (1000 * 60);

        if (diffMinutes > 30) return 'offline';
        if (parseFloat(snr) < 20) return 'warning';
        return 'online';
    }

    function actualizarEstadisticas() {
        const totalClientes = datosClientes.length;
        const txThroughputTotal = datosClientes.reduce((sum, cliente) => sum + (parseFloat(cliente.tx_throughput) || 0), 0);
        const rxThroughputTotal = datosClientes.reduce((sum, cliente) => sum + (parseFloat(cliente.rx_throughput) || 0), 0);
        const avgSNR = totalClientes > 0 ? datosClientes.reduce((sum, cliente) => sum + (parseFloat(cliente.snr) || 0), 0) / totalClientes : 0;
        const activeClients = datosClientes.filter(cliente => determinarEstado(cliente.snr, cliente.lastdate) === 'online').length;

        document.getElementById('totalClientes').textContent = totalClientes;
        document.getElementById('txThroughputTotal').textContent = formatBps(txThroughputTotal);
        document.getElementById('rxThroughputTotal').textContent = formatBps(rxThroughputTotal);
        document.getElementById('avgSNR').textContent = avgSNR.toFixed(1) + ' dB';
        document.getElementById('activeClients').textContent = activeClients;

        // Actualizar barras de progreso
        const maxClientes = 500; 
        const progressClientes = Math.min((totalClientes / maxClientes) * 100, 100);
        document.getElementById('progressClientes').style.width = progressClientes + '%';

        const txMbps = txThroughputTotal / 1000000;
        const rxMbps = rxThroughputTotal / 1000000;
        const maxThroughput = 100; 
        document.getElementById('progressTx').style.width = Math.min((txMbps / maxThroughput) * 100, 100) + '%';
        document.getElementById('progressRx').style.width = Math.min((rxMbps / maxThroughput) * 100, 100) + '%';

        const maxSNR = 50; 
        document.getElementById('progressSNR').style.width = Math.min((avgSNR / maxSNR) * 100, 100) + '%';

        const progressActive = totalClientes > 0 ? (activeClients / totalClientes) * 100 : 0;
        document.getElementById('progressActive').style.width = progressActive + '%';

        const cardTx = document.getElementById('cardTxThroughputTotal');
        const cardRx = document.getElementById('cardRxThroughputTotal');

        ['color-green', 'color-yellow', 'color-orange', 'color-red'].forEach(cls => {
            cardTx.classList.remove(cls);
            cardRx.classList.remove(cls);
        });

        cardTx.classList.add(getThroughputColorClass(txMbps));
        cardRx.classList.add(getThroughputColorClass(rxMbps));
    }

    function crearDistribucionOS() {
        const osCounts = {};
        datosClientes.forEach(cliente => {
            const os = cliente.os || 'Desconocido';
            osCounts[os] = (osCounts[os] || 0) + 1;
        });

        const total = datosClientes.length;
        const sorted = Object.entries(osCounts).sort((a, b) => b[1] - a[1]);

        const html = sorted.map(([os, count]) => {
            const percentage = total > 0 ? ((count / total) * 100).toFixed(1) : 0;
            return `
                <div class="distribution-item">
                    <div>
                        <div class="distribution-label">${os}</div>
                        <div class="distribution-bar">
                            <div class="distribution-fill" style="width: ${percentage}%"></div>
                        </div>
                    </div>
                    <div class="distribution-value">${count} (${percentage}%)</div>
                </div>
            `;
        }).join('');

        document.getElementById('osDistribution').innerHTML = html;
    }

    function crearDistribucionThroughput() {
        const rangos = {
            'Bajo (<1 Mbps)': 0,
            'Medio (1-10 Mbps)': 0,
            'Alto (10-100 Mbps)': 0,
            'Muy Alto (>100 Mbps)': 0
        };

        datosClientes.forEach(cliente => {
            const totalThroughput = (parseFloat(cliente.rx_throughput) || 0) + (parseFloat(cliente.tx_throughput) || 0);
            const totalMbps = totalThroughput / 1000000;

            if (totalMbps < 1) rangos['Bajo (<1 Mbps)']++;
            else if (totalMbps < 10) rangos['Medio (1-10 Mbps)']++;
            else if (totalMbps < 100) rangos['Alto (10-100 Mbps)']++;
            else rangos['Muy Alto (>100 Mbps)']++;
        });

        const total = datosClientes.length;
        const maxCount = Math.max(...Object.values(rangos));

        const html = Object.entries(rangos).map(([rango, count]) => {
            const percentage = total > 0 ? ((count / total) * 100).toFixed(1) : 0;
            const barWidth = maxCount > 0 ? ((count / maxCount) * 100).toFixed(1) : 0;
            return `
                <div class="distribution-item">
                    <div>
                        <div class="distribution-label">${rango}</div>
                        <div class="distribution-bar">
                            <div class="distribution-fill" style="width: ${barWidth}%"></div>
                        </div>
                    </div>
                    <div class="distribution-value">${count} (${percentage}%)</div>
                </div>
            `;
        }).join('');

        document.getElementById('throughputDistribution').innerHTML = html;
    }

    function mostrarTopNConsumers(elementId, throughputType, title) {
        const sorted = [...datosClientes].sort((a, b) =>
            (parseFloat(b[throughputType]) || 0) - (parseFloat(a[throughputType]) || 0)
        ).slice(0, 10);

        const html = sorted.map((cliente, index) => {
            const throughputValue = parseFloat(cliente[throughputType]) || 0;
            const throughputMbps = throughputValue / 1000000;
            const estado = determinarEstado(cliente.snr, cliente.lastdate);
            const colorClass = getThroughputColorClass(throughputMbps);

            return `
                <div class="consumer-item ${colorClass}">
                    <div class="consumer-rank">#${index + 1}</div>
                    <div class="consumer-info">
                        <div class="consumer-name">
                            <span class="status-indicator status-${estado}"></span>
                            ${cliente.nombre || 'Cliente sin nombre'}
                        </div>
                        <div class="consumer-details">
                            IP: ${cliente.ip_cliente} - S.O.: ${cliente.os || 'OS desconocido'} - SNR: ${cliente.snr}dB - AP: ${cliente.ip_ap} - HOSTNAME: ${cliente.nombre_equipo}
                        </div>
                    </div>
                    <div class="consumer-usage">
                        ${formatBps(throughputValue)}
                    </div>
                </div>
            `;
        }).join('');

        document.getElementById(elementId).innerHTML = html;
    }

    function checkAndPlayAlarm() {
        let alarmTriggered = false;
        for (const cliente of datosClientes) {
            const rxMbps = (parseFloat(cliente.rx_throughput) || 0) / 1000000;
            const txMbps = (parseFloat(cliente.tx_throughput) || 0) / 1000000;

            if (rxMbps >= throughputThresholds.red || txMbps >= throughputThresholds.red) {
                alarmTriggered = true;
                break;
            }
        }

        if (alarmTriggered && !isAlarmMuted) {
            if (alarmSound.paused) {
                alarmSound.play().catch(e => console.error("Error al reproducir alarma:", e));
            }
        } else {
            alarmSound.pause();
            alarmSound.currentTime = 0;
        }
    }

    function cargarDatos() {
        $.ajax({
            url: 'wireless_obtener_datos.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                datosClientes = response.data || [];
                actualizarEstadisticas();
                crearDistribucionOS();
                crearDistribucionThroughput();
                mostrarTopNConsumers('topTxConsumers', 'tx_throughput', 'Top 10 Consumidores de Throughput Tx');
                mostrarTopNConsumers('topRxConsumers', 'rx_throughput', 'Top 10 Consumidores de Throughput Rx');

                if ($.fn.DataTable.isDataTable('#tablaClientes')) {
                    tabla.destroy();
                    $('#tablaClientes tbody').empty();
                }

                tabla = $('#tablaClientes').DataTable({
                    responsive: true,
                    scrollX: true,
                    pageLength: 25,
                    order: [[7, 'desc']],
                    language: {
                        url: 'wireless_js/Spanish.json'
                    },
                    columnDefs: [
                        { targets: [0], searchable: false },
                        {
                            targets: [8, 9],
                            type: 'html-num-fmt',
                            createdCell: function (td, cellData, rowData, row, col) {
                                const throughputValue = (col === 8) ? (parseFloat(rowData.rx_throughput) || 0) : (parseFloat(rowData.tx_throughput) || 0);
                                const throughputMbps = throughputValue / 1000000;
                                $(td).addClass(getThroughputColorClass(throughputMbps));
                            },
                            render: function(data, type, row) {
                                if (type === 'display' || type === 'type') {
                                    var valueMatch = data.match(/data-order="([^"]+)"/);
                                    const actualValue = valueMatch ? parseFloat(valueMatch[1]) : 0;
                                    const throughputMbps = actualValue / 1000000;
                                    const usagePercent = Math.min((throughputMbps / throughputThresholds.red) * 100, 100);
                                    
                                    return `<span data-order="${actualValue}"><div class="usage-bar"><div class="usage-fill" style="width: ${usagePercent}%"></div></div>${formatBps(actualValue)}</span>`;
                                }
                                var match = data.match(/data-order="([^"]+)"/);
                                return match ? parseFloat(match[1]) : 0;
                            }
                        }
                    ],
                    data: datosClientes.map(cliente => {
                        const estado = determinarEstado(cliente.snr, cliente.lastdate);
                        const rxThroughput = parseFloat(cliente.rx_throughput) || 0;
                        const txThroughput = parseFloat(cliente.tx_throughput) || 0;

                        return [
                            `<span class="status-indicator status-${estado}"></span>`,
                            cliente.nombre || 'Sin nombre',
                            cliente.nombre_equipo || 'Sin nombre',
                            cliente.ip_cliente,
                            cliente.mac,
                            cliente.os || 'Desconocido',
                            cliente.ip_ap,
                            cliente.snr + ' dB',
                            `<span data-order="${rxThroughput}">${formatBps(rxThroughput)}</span>`,
                            `<span data-order="${txThroughput}">${formatBps(txThroughput)}</span>`,
                            formatFechaEcuador(cliente.lastdate)
                        ];
                    })
                });
                
                checkAndPlayAlarm();
            },
            error: function(xhr, status, error) {
                console.error('Error al cargar datos:', error);
                alarmSound.pause();
                alarmSound.currentTime = 0;
            }
        });
    }

    function toggleTheme() {
        currentThemeIndex = (currentThemeIndex + 1) % themes.length;
        const wrapper = document.getElementById('analisis-wrapper');
        const themeIconElement = document.getElementById('themeIcon');

        themes.forEach(theme => {
            if (theme) wrapper.classList.remove(theme);
        });

        if (themes[currentThemeIndex]) {
            wrapper.classList.add(themes[currentThemeIndex]);
        }

        themeIconElement.innerHTML = themeIcons[currentThemeIndex];
        localStorage.setItem('selectedThemeIndex', currentThemeIndex);
    }

    function toggleMute() {
        isAlarmMuted = !isAlarmMuted;
        const muteButton = document.querySelector('.mute-alarm-btn');
        const muteIcon = document.getElementById('muteIcon');

        if (isAlarmMuted) {
            alarmSound.pause();
            alarmSound.currentTime = 0;
            muteButton.classList.add('muted');
            muteIcon.innerHTML = '<i class="fas fa-volume-mute"></i>';
        } else {
            checkAndPlayAlarm();
            muteButton.classList.remove('muted');
            muteIcon.innerHTML = '<i class="fas fa-volume-up"></i>';
        }
        localStorage.setItem('isAlarmMuted', isAlarmMuted);
    }

    function refreshData() {
        const btn = document.querySelector('.refresh-btn');
        btn.style.transition = 'transform 0.5s ease-in-out';
        btn.style.transform = 'rotate(360deg)';
        setTimeout(() => {
            btn.style.transition = 'none';
            btn.style.transform = 'rotate(0deg)';
            void btn.offsetWidth;
            btn.style.transition = 'transform 0.3s ease';
        }, 500);

        cargarDatos();
    }

    function openInventoryManager() {
         window.open('wireless_inventario.php', '_blank');
    }

    $(document).ready(function() {
        const savedThemeIndex = localStorage.getItem('selectedThemeIndex');
        if (savedThemeIndex !== null) {
            currentThemeIndex = parseInt(savedThemeIndex);
            const wrapper = document.getElementById('analisis-wrapper');
            if (themes[currentThemeIndex]) {
                wrapper.classList.add(themes[currentThemeIndex]);
            }
        }
        document.getElementById('themeIcon').innerHTML = themeIcons[currentThemeIndex];

        const savedMuteState = localStorage.getItem('isAlarmMuted');
        if (savedMuteState !== null) {
            isAlarmMuted = JSON.parse(savedMuteState);
            const muteButton = document.querySelector('.mute-alarm-btn');
            const muteIcon = document.getElementById('muteIcon');
            if (isAlarmMuted) {
                muteButton.classList.add('muted');
                muteIcon.innerHTML = '<i class="fas fa-volume-mute"></i>';
            } else {
                muteButton.classList.remove('muted');
                muteIcon.innerHTML = '<i class="fas fa-volume-up"></i>';
            }
        }

        tabla = $('#tablaClientes').DataTable({
            responsive: true,
            scrollX: true,
            pageLength: 25,
            order: [[7, 'desc']],
            language: {
                url: 'wireless_js/Spanish.json'
            },
            columnDefs: [
                { targets: [0], searchable: false },
                {
                    targets: [8, 9],
                    type: 'html-num-fmt',
                    createdCell: function (td, cellData, rowData, row, col) {
                        const throughputValue = (col === 8) ? (parseFloat(rowData.rx_throughput) || 0) : (parseFloat(rowData.tx_throughput) || 0);
                        const throughputMbps = throughputValue / 1000000;
                        $(td).addClass(getThroughputColorClass(throughputMbps));
                    },
                    render: function(data, type, row) {
                        if (type === 'display' || type === 'type') {
                            var valueMatch = data.match(/data-order="([^"]+)"/);
                            const actualValue = valueMatch ? parseFloat(valueMatch[1]) : 0;
                            const throughputMbps = actualValue / 1000000;
                            const usagePercent = Math.min((throughputMbps / throughputThresholds.red) * 100, 100);
                            return `<span data-order="${actualValue}"><div class="usage-bar"><div class="usage-fill" style="width: ${usagePercent}%"></div></div>${formatBps(actualValue)}</span>`;
                        }
                        var match = data.match(/data-order="([^"]+)"/);
                        return match ? parseFloat(match[1]) : 0;
                    }
                }
            ],
            data: []
        });

        cargarDatos();
        setInterval(cargarDatos, 30000);
    });
</script>

<?php
require_once __DIR__ . '/../../../partials/footer.php';
?>