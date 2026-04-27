<?php
$page_title = "Kanban Zabbix - NOVAIOPS";
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../partials/header.php';
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- SortableJS -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<style>
    :root {
        --bg-column: #ebedf0;
        --primary-blue: #003366;
        --text-muted: #7c98b6;
        --white: #ffffff;
        
        /* Severidades Zabbix */
        --sev-5: #ff3939; --sev-4: #ffb689; --sev-3: #ffefac;
        --sev-2: #d6f6ff; --sev-1: #d1f7c4; --sev-0: #97aab3;
    }

    .kanban-wrapper {
        display: flex;
        height: calc(100vh - 150px); /* Ajuste para header/footer de AdminLTE */
        margin: -15px; /* Contrarestar padding de container-fluid */
        overflow: hidden;
        background: #f4f7f9;
    }

    /* Sidebar interno de filtros */
    .kanban-sidebar {
        width: 300px;
        background: #fff;
        border-right: 1px solid #dee2e6;
        padding: 20px;
        overflow-y: auto;
    }

    .kanban-sidebar h4 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--primary-blue);
        margin-bottom: 20px;
    }

    .filter-group {
        margin-bottom: 20px;
    }

    .filter-group label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: #555;
        margin-bottom: 5px;
        text-transform: uppercase;
    }

    .kanban-content {
        flex: 1;
        padding: 20px;
        overflow-x: auto;
        display: flex;
        flex-direction: column;
    }

    .kanban-board {
        display: flex;
        gap: 15px;
        height: 100%;
        align-items: flex-start;
    }

    .kanban-column {
        flex: 1;
        min-width: 280px;
        background-color: var(--bg-column);
        border-radius: 10px;
        display: flex;
        flex-direction: column;
        max-height: 100%;
        padding: 12px;
    }

    .column-header {
        margin-bottom: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .column-header h3 {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--primary-blue);
        margin: 0;
    }

    .cards-container {
        flex: 1;
        overflow-y: auto;
        min-height: 100px;
    }

    .card-kanban {
        background: #fff;
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        border-left: 4px solid transparent;
        cursor: grab;
        position: relative;
    }

    .card-kanban:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }

    .sev-badge {
        font-size: 0.65rem;
        font-weight: 700;
        padding: 1px 6px;
        border-radius: 3px;
        margin-bottom: 5px;
        display: inline-block;
    }

    .incident-title {
        font-size: 0.85rem;
        font-weight: 600;
        display: block;
        margin-bottom: 5px;
        color: var(--primary-blue);
    }

    .host-text { font-size: 0.75rem; color: #777; margin-bottom: 3px; }
    .date-text { font-size: 0.7rem; color: #999; }

    /* Severity Colors */
    .sev-5 { border-left-color: var(--sev-5); }
    .sev-4 { border-left-color: var(--sev-4); }
    .sev-3 { border-left-color: var(--sev-3); }
    .sev-2 { border-left-color: var(--sev-2); }
    .sev-1 { border-left-color: var(--sev-1); }
    .sev-0 { border-left-color: var(--sev-0); }
    
    .bg-sev-5 { background: var(--sev-5); color: #fff; }
    .bg-sev-4 { background: var(--sev-4); color: #000; }
    .bg-sev-3 { background: var(--sev-3); color: #000; }
    .bg-sev-2 { background: var(--sev-2); color: #000; }
    .bg-sev-1 { background: var(--sev-1); color: #000; }
    .bg-sev-0 { background: var(--sev-0); color: #fff; }

    .col-open h3 { color: #d63031 !important; }
    .col-progress h3 { color: #0984e3 !important; }
    .col-resolved h3 { color: #27ae60 !important; }
    .col-maintenance h3 { color: #f39c12 !important; }

    #loader {
        position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
        z-index: 100; background: rgba(255,255,255,0.8); padding: 20px; border-radius: 10px;
        font-weight: bold; color: var(--primary-blue);
    }
</style>

<div class="kanban-wrapper">
    <div id="loader">Cargando...</div>
    
    <!-- Sidebar Interno -->
    <aside class="kanban-sidebar">
        <h4>Analítica y Filtros</h4>
        
        <div class="filter-group">
            <label>Severidad</label>
            <select id="filter-severity" class="form-control form-control-sm">
                <option value="all">Todas</option>
                <option value="5">Desastre</option>
                <option value="4">Alta</option>
                <option value="3">Promedio</option>
                <option value="2">Advertencia</option>
                <option value="1">Información</option>
                <option value="0">No clasificado</option>
            </select>
        </div>

        <div class="filter-group">
            <label>Buscar Tag / Host</label>
            <input type="text" id="filter-tags" class="form-control form-control-sm" placeholder="Filtrar...">
        </div>

        <div class="chart-container mt-4">
            <h6 class="text-center small font-weight-bold">Incidents by Severity</h6>
            <canvas id="chartSeverity" height="200"></canvas>
        </div>

        <div class="chart-container mt-4">
            <h6 class="text-center small font-weight-bold">Incidents by Status</h6>
            <canvas id="chartStatus" height="200"></canvas>
        </div>
    </aside>

    <!-- Board -->
    <div class="kanban-content">
        <div class="kanban-board">
            <div class="kanban-column col-open" data-status="open">
                <div class="column-header">
                    <h3>Open</h3>
                    <span class="badge badge-light" id="count-open">0</span>
                </div>
                <div class="cards-container" id="cards-open"></div>
            </div>

            <div class="kanban-column col-progress" data-status="in_progress">
                <div class="column-header">
                    <h3>In Progress</h3>
                    <span class="badge badge-light" id="count-in_progress">0</span>
                </div>
                <div class="cards-container" id="cards-in_progress"></div>
            </div>

            <div class="kanban-column col-resolved" data-status="resolved">
                <div class="column-header">
                    <h3>Resolved</h3>
                    <span class="badge badge-light" id="count-resolved">0</span>
                </div>
                <div class="cards-container" id="cards-resolved"></div>
            </div>

            <div class="kanban-column col-maintenance" data-status="maintenance">
                <div class="column-header">
                    <h3>Maintenance</h3>
                    <span class="badge badge-light" id="count-maintenance">0</span>
                </div>
                <div class="cards-container" id="cards-maintenance"></div>
            </div>
        </div>
    </div>
</div>

<script>
const sevNames = ["Not classified", "Information", "Warning", "Average", "High", "Disaster"];
const sevColors = ['#97aab3', '#d1f7c4', '#d6f6ff', '#ffefac', '#ffb689', '#ff3939'];
let chartSev, chartStat, rawData = null;

$(document).ready(function() {
    initCharts();
    loadBoard();
    initSortable();

    $('#filter-severity').on('change', loadBoard);
    $('#filter-tags').on('input', debounce(renderFilteredCards, 400));
});

function debounce(func, wait) {
    let timeout;
    return function() {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, arguments), wait);
    };
}

function initCharts() {
    chartSev = new Chart($('#chartSeverity'), {
        type: 'doughnut',
        data: { labels: sevNames, datasets: [{ data: [0,0,0,0,0,0], backgroundColor: sevColors }] },
        options: { responsive: true, plugins: { legend: { display: false } }, cutout: '70%' }
    });
    chartStat = new Chart($('#chartStatus'), {
        type: 'pie',
        data: { labels: ['Open', 'Progress', 'Resolved', 'Maint'], datasets: [{ data: [0,0,0,0], backgroundColor: ['#d63031', '#0984e3', '#27ae60', '#f39c12'] }] },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, font: { size: 9 } } } } }
    });
}

async function loadBoard() {
    $('#loader').show();
    const sev = $('#filter-severity').val();
    try {
        const response = await fetch(`fetch_data.php?severity=${sev}`);
        const data = await response.json();
        if (data.success) {
            rawData = data;
            renderFilteredCards();
            updateAnalytics(data.analytics);
        }
    } catch(e) { console.error(e); }
    $('#loader').hide();
}

function renderFilteredCards() {
    if (!rawData) return;
    const query = $('#filter-tags').val().toLowerCase();
    const cols = ['open', 'in_progress', 'resolved', 'maintenance'];
    
    cols.forEach(col => {
        const container = $(`#cards-${col}`);
        const countSpan = $(`#count-${col}`);
        const filtered = rawData.columns[col].filter(c => 
            c.name.toLowerCase().includes(query) || c.host.toLowerCase().includes(query)
        );
        
        countSpan.text(filtered.length);
        container.empty();
        
        filtered.forEach(c => {
            const cardEl = $(`
                <div class="card-kanban sev-${c.severity}" data-id="${c.eventid}" data-hostid="${c.hostid}">
                    <span class="sev-badge bg-sev-${c.severity}">${sevNames[c.severity]}</span>
                    <strong class="incident-title">${c.name}</strong>
                    <div class="host-text"><i class="fas fa-server mr-1"></i>${c.host}</div>
                    <div class="date-text"><i class="far fa-clock mr-1"></i>${c.clock}</div>
                </div>
            `);
            container.append(cardEl);
        });
    });
}

function updateAnalytics(analytics) {
    chartSev.data.datasets[0].data = Object.values(analytics.severity);
    chartSev.update();
    chartStat.data.datasets[0].data = [analytics.status['Open'], analytics.status['In Progress'], analytics.status['Resolved'], analytics.status['Maintenance']];
    chartStat.update();
}

function initSortable() {
    $('.cards-container').each(function() {
        new Sortable(this, {
            group: 'kanban',
            animation: 150,
            onEnd: async (evt) => {
                const target = $(evt.to).parent().data('status');
                const source = $(evt.from).parent().data('status');
                if (target === source) return;
                
                $('#loader').show();
                try {
                    await fetch('update_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            eventid: evt.item.dataset.id,
                            target_column: target,
                            hostid: evt.item.dataset.hostid
                        })
                    });
                    loadBoard();
                } catch(e) { console.error(e); $('#loader').hide(); }
            }
        });
    });
}
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
