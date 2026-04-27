<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
$page_title = "Análisis de Crecimiento de Almacenamiento";
require_once __DIR__ . '/../public/partials/header.php';
?>

<style>
    :root {
        --page-bg: #f4f6f9;
        --accent-primary: #007bff;
        --border-color: #dee2e6;
        --text-main: #333;
    }

    .analysis-header {
        background: #fff;
        padding: 20px;
        border-bottom: 1px solid var(--border-color);
        margin-bottom: 20px;
    }

    .stat-card {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        border: 1px solid var(--border-color);
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        transition: transform 0.2s;
    }
    .stat-card:hover { transform: translateY(-2px); }
    .stat-card .label { color: #6c757d; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .stat-card .value { font-size: 1.8rem; font-weight: 700; color: var(--text-main); display: block; margin: 8px 0; }
    .stat-card .subtext { font-size: 0.8rem; color: #6c757d; }

    .filter-section {
        background: #fff;
        border-radius: 8px;
        padding: 15px;
        border: 1px solid var(--border-color);
        margin-bottom: 20px;
        border-top: 3px solid var(--accent-primary);
    }

    .table-container {
        background: #fff;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        overflow: hidden;
        margin-bottom: 20px;
    }
    .table-container h5 { padding: 15px; margin: 0; background: #f8f9fa; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; font-weight: 700; color: #495057; }

    .fs-table { width: 100% !important; margin-bottom: 0; }
    .fs-table thead th { background: #f8f9fa; color: #495057; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; padding: 12px; border-bottom: 2px solid var(--border-color); }
    .fs-table td { vertical-align: middle; padding: 12px; color: #444; }

    .row-expand { cursor: pointer; transition: background 0.2s; }
    .row-expand:hover { background: rgba(0,123,255,0.03); }
    .expanded-content { background: #fcfcfc; padding: 25px !important; border-left: 4px solid var(--accent-primary); }

    .badge-risk { background: #fee2e2; color: #dc2626; font-weight: 700; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; border: 1px solid #fecaca; }
    .badge-safe { background: #dcfce7; color: #16a34a; font-weight: 700; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; border: 1px solid #bbf7d0; }

    .progress { background-color: #e9ecef; height: 10px; border-radius: 5px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.1); }

    /* Normalize Select Heights */
    .form-control, .select2-container--default .select2-selection--single {
        height: 42px !important;
        border-radius: 6px !important;
        border-color: #ced4da !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 40px !important; padding-left: 12px !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px !important; }
    .select2-container--default .select2-results > .select2-results__options { max-height: 250px !important; }

    .simulator-box {
        background: #f0f7ff;
        border: 1px dashed #007bff;
        border-radius: 8px;
        padding: 15px;
    }
</style>

<div class="content-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold"><i class="fas fa-chart-line mr-2 text-primary"></i>Análisis de Almacenamiento</h1>
                <p class="text-muted small">Visualiza el crecimiento de discos y proyecta la capacidad futura</p>
            </div>
            <div class="col-sm-6 text-right">
                <button class="btn btn-outline-primary btn-sm rounded-pill" id="btn-toggle-filter">
                    <i class="fas fa-filter mr-1"></i> Filtros Disponibles
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="filter-section shadow-sm" id="filter-panel" style="display: block;">
        <div class="row align-items-end">
            <div class="col-md-3">
                <label class="small font-weight-bold text-muted mb-2"><i class="fas fa-layer-group mr-1"></i> GRUPO DE HOSTS</label>
                <select id="sel-group" class="form-control select2">
                    <option value="all">-- Todos los Grupos --</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="small font-weight-bold text-muted mb-2"><i class="fas fa-server mr-1"></i> HOST ESPECÍFICO</label>
                <select id="sel-host" class="form-control select2">
                    <option value="all">-- Todos los Equipos --</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="small font-weight-bold text-muted mb-2"><i class="fas fa-calendar-alt mr-1"></i> RANGO DE ANÁLISIS</label>
                <div class="d-flex">
                    <input type="date" id="sel-start" class="form-control mr-1" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                    <input type="date" id="sel-end" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary btn-block font-weight-bold" id="btn-analyze" style="height: 42px;">
                    <i class="fas fa-sync-alt mr-2"></i> EJCUTAR ANÁLISIS
                </button>
            </div>
        </div>
    </div>

    <!-- Summary Widgets -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <span class="label">Capacidad Total</span>
                <span class="value" id="stat-total-storage">0.00 TB</span>
                <span class="subtext">Espacio total detectado</span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <span class="label">Espacio Utilizado</span>
                <span class="value text-primary" id="stat-total-used">0.00 TB</span>
                <span class="subtext" id="stat-used-pct">0% de ocupación global</span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                 <span class="label">Crecimiento Diario (Est.)</span>
                 <span class="value text-success" id="stat-avg-growth">0.00 GB/día</span>
                 <span class="subtext">Basado en tendencias detectadas</span>
            </div>
        </div>
    </div>

    <!-- Main Inventory Table -->
    <div class="table-container shadow-sm border-0">
        <h5><i class="fas fa-list mr-2"></i>Detalle de Inventario y Proyecciones</h5>
        <div class="table-responsive">
            <table class="table fs-table mb-0" id="table-main">
                <thead class="bg-light">
                    <tr>
                        <th width="40"></th>
                        <th>Host / Sistema</th>
                         <th class="text-center">Plataforma</th>
                        <th class="text-right">Capacidad</th>
                        <th class="text-right">Usado</th>
                        <th width="180">Ocupación %</th>
                        <th class="text-right">Crecimiento</th>
                        <th>Días Restantes</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="9" class="text-center py-5 text-muted">Presiona "Analizar" para extraer datos de Zabbix.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../public/partials/footer.php'; ?>

<script>
$(document).ready(function() {
    $('.select2').select2({ theme: 'bootstrap4' });

    // Load Groups con filtrado encadenado
    $.get('../api_zabbix.php?action=get_groups', function(res) {
        if (res.success) res.data.forEach(g => $('#sel-group').append(`<option value="${g.groupid}">${g.name}</option>`));
    });

    $('#sel-group').on('change', function() {
        let groupid = $(this).val();
        $('#sel-host').html('<option value="all">-- Todos los Equipos --</option>');
        if (groupid && groupid !== 'all') {
            $.get(`../api_zabbix.php?action=get_hosts&groupids=${groupid}`, function(res) {
                if (res.success) res.data.forEach(h => $('#sel-host').append(`<option value="${h.hostid}">${h.name}</option>`));
            });
        }
    });

    $('#btn-toggle-filter').on('click', () => $('#filter-panel').slideToggle());

    $('#btn-analyze').on('click', function() {
        let btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Analizando...');
        
        let params = {
            action: 'get_storage_analysis',
            groupid: $('#sel-group').val(),
            hostid: $('#sel-host').val()
        };

        $.get('../api_storage.php', params, function(res) {
            btn.prop('disabled', false).html('<i class="fas fa-sync-alt mr-2"></i> EJECUTAR ANÁLISIS');
            if (res.success) {
                renderSummary(res.summary);
                renderMainTable(res.data);
            } else {
                toastr.error(res.error || 'Error al obtener datos');
            }
        });
    });

    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 B';
        const k = 1024, dm = decimals < 0 ? 0 : decimals, sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    function renderSummary(s) {
        $('#stat-total-storage').text(formatBytes(s.total_storage));
        $('#stat-total-used').text(formatBytes(s.total_used));
        let pct = s.total_storage > 0 ? (s.total_used / s.total_storage * 100).toFixed(1) : 0;
        $('#stat-used-pct').text(`${pct}% de ocupación global`);
        $('#stat-avg-growth').text(formatBytes(s.avg_growth) + '/día');
    }

    function renderMainTable(data) {
        let tbody = $('#table-main tbody');
        tbody.empty();
        
        if (data.length === 0) {
            tbody.append('<tr><td colspan="9" class="text-center py-5 text-muted">No se encontraron datos para los filtros seleccionados.</td></tr>');
            return;
        }

        data.forEach(h => {
            let statusBadge = h.usage_pct > 85 ? '<span class="badge-risk">CRÍTICO</span>' : '<span class="badge-safe">ÓPTIMO</span>';
            let minDays = Math.min(...h.filesystems.map(f => f.days_until_full).filter(d => d !== null));
            let daysLabel = minDays >= 365 ? '365+ días' : minDays + ' días';
            let growthTotal = h.filesystems.reduce((acc, f) => acc + (f.growth_rate || 0), 0);

            let rowHtml = `
                <tr class="row-expand" data-hostid="${h.hostid}">
                    <td class="text-center align-middle"><i class="fas fa-chevron-right text-muted transition-icon" id="icon-${h.hostid}"></i></td>
                    <td class="align-middle"><div class="font-weight-bold">${h.name}</div><small class="text-muted text-uppercase">${h.os}</small></td>
                    <td class="text-center align-middle"><span class="badge badge-light border">${h.platform || 'Agente'}</span></td>
                    <td class="text-right align-middle font-weight-bold">${formatBytes(h.total_space)}</td>
                    <td class="text-right align-middle text-primary">${formatBytes(h.used_space)}</td>
                    <td class="align-middle">
                        <div class="progress shadow-sm mb-1"><div class="progress-bar ${h.usage_pct > 80 ? 'bg-danger' : 'bg-primary'}" style="width:${h.usage_pct}%"></div></div>
                        <small class="text-muted font-weight-bold">${h.usage_pct}% utilizado</small>
                    </td>
                    <td class="text-right align-middle text-success font-weight-bold">${formatBytes(growthTotal)}/día</td>
                    <td class="align-middle font-weight-bold">${daysLabel}</td>
                    <td class="align-middle">${statusBadge}</td>
                </tr>
                <tr id="expand-${h.hostid}" class="d-none bg-light shadow-inner">
                    <td colspan="9" class="expanded-content">
                        <div class="p-3 bg-white rounded shadow-sm border">
                            <h6 class="font-weight-bold border-bottom pb-2 mb-3"><i class="fas fa-hdd mr-2 text-primary"></i>Particiones Detectadas</h6>
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr class="text-muted">
                                        <th>Punto de Montaje</th>
                                        <th class="text-right">Capacidad</th>
                                        <th class="text-right">Usado</th>
                                        <th width="150">Ocupación %</th>
                                        <th class="text-right">Crecimiento</th>
                                        <th class="text-right">Proyección</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${h.filesystems.map(fs => `
                                        <tr>
                                            <td><code class="text-dark font-weight-bold">${fs.mount}</code></td>
                                            <td class="text-right">${formatBytes(fs.total)}</td>
                                            <td class="text-right">${formatBytes(fs.used)}</td>
                                            <td>
                                                <div class="progress" style="height: 6px;"><div class="progress-bar ${fs.pused > 85 ? 'bg-danger' : 'bg-info'}" style="width:${fs.pused}%"></div></div>
                                                <small class="font-weight-bold">${fs.pused}%</small>
                                            </td>
                                            <td class="text-right text-success">${formatBytes(fs.growth_rate)}/día</td>
                                            <td class="text-right font-weight-bold">${fs.days_until_full >= 365 ? 'Estable' : fs.days_until_full + ' días'}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </td>
                </tr>`;
            tbody.append(rowHtml);
        });
    }

    $(document).on('click', '.row-expand', function() {
        let id = $(this).data('hostid');
        let target = $(`#expand-${id}`);
        let icon = $(`#icon-${id}`);
        
        $('.expanded-content').parent().not(target).addClass('d-none'); // Opción: Cerrar otros al abrir uno
        
        target.toggleClass('d-none');
        icon.toggleClass('fa-chevron-right fa-chevron-down');
    });
});
</script>
