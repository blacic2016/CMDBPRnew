<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
$page_title = "Costos Zabbix";
require_once __DIR__ . '/../partials/header.php';
?>

<style>
    /* Professional Light Theme Style */
    :root {
        --page-bg: #f4f6f9;
        --card-bg: #ffffff;
        --accent-color: #007bff;
        --text-main: #2b2b2b;
        --text-muted: #6c757d;
        --border-color: #dee2e6;
        --table-header-bg: #f8f9fa;
        --success-color: #28a745;
        --danger-color: #dc3545;
    }

    /* Force Light Mode if needed, otherwise respect AdminLTE */
    body:not(.dark-mode) .content-wrapper { background: var(--page-bg); }
    body:not(.dark-mode) .card { background: var(--card-bg); color: var(--text-main); }

    .pricing-summary {
        background: #e7f3ff;
        border-radius: 8px;
        padding: 15px 20px;
        margin-bottom: 20px;
        border: 1px solid #b8daff;
        border-left: 5px solid var(--accent-color);
    }
    .pricing-summary h6 { color: #555; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 1px; margin-bottom: 4px; font-weight: 700; }
    .pricing-summary p { margin: 0; color: var(--text-main); font-size: 1rem; font-weight: 600; }

    .filter-bar {
        background: #fff;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid var(--border-color);
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .cost-table { width: 100% !important; background: #fff; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; }
    .cost-table thead th { 
        background: var(--table-header-bg); 
        color: var(--text-main) !important; 
        border-bottom: 2px solid var(--border-color);
        font-weight: 700; 
        font-size: 0.8rem; 
        padding: 15px 10px;
        text-transform: uppercase;
    }
    .cost-table tbody td { 
        padding: 15px 10px; 
        vertical-align: top;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-main) !important;
        font-size: 0.9rem;
    }
    
    .host-name { color: var(--accent-color); font-weight: 600; text-decoration: none; cursor: pointer; }
    .host-name:hover { text-decoration: underline; }
    
    .progress { background-color: #ebedef; height: 10px; border-radius: 5px; }
    .progress-bar { border-radius: 5px; }
    
    .text-used-sub { color: #218838; font-size: 0.75rem; font-weight: 600; }
    .text-idle-sub { color: #c82333; font-size: 0.75rem; font-weight: 600; }
    .text-total-val { font-weight: 700; color: #000; }
    .text-savings { color: #f39c12; font-weight: 700; }

    .summary-row { background: #f1f3f5 !important; }
    .summary-row td { border-top: 2px solid var(--border-color) !important; color: #000 !important; font-weight: 700; vertical-align: middle; }

    .nav-tabs .nav-link { color: var(--text-muted); font-weight: 600; border: none; }
    .nav-tabs .nav-link.active { color: var(--accent-color); border-bottom: 3px solid var(--accent-color); background: transparent !important; }

    /* Select2 and Select heights normalization */
    .form-control, 
    .select2-container--default .select2-selection--single,
    .select2-container--default .select2-selection--multiple {
        border-color: var(--border-color) !important;
        height: 42px !important;
        min-height: 42px !important;
        display: flex;
        align-items: center;
        background-color: #fff !important;
        border-radius: 6px !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 42px !important;
        padding-left: 12px !important;
        color: var(--text-main) !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__rendered {
        display: flex !important;
        flex-wrap: nowrap;
        overflow: hidden;
        padding: 0 10px !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: var(--accent-color) !important;
        border: none !important;
        color: #fff !important;
        margin-top: 7px !important;
        font-size: 0.75rem;
    }

    .select2-container--default .select2-results > .select2-results__options {
        max-height: 200px !important;
        overflow-y: auto;
    }
</style>

<div class="row">
    <div class="col-12">
        <div class="card card-primary card-outline card-tabs">
            <div class="card-header p-0 pt-1 border-bottom-0">
                <ul class="nav nav-tabs" id="costos-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="tab-reporte-link" data-toggle="pill" href="#tab-reporte" role="tab" aria-selected="true">
                            <i class="fas fa-chart-line mr-1"></i> Análisis de Costos (Reporte)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-config-link" data-toggle="pill" href="#tab-config" role="tab" aria-selected="false">
                            <i class="fas fa-cogs mr-1"></i> Configuración de Costos
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="custom-tabs-three-tabContent">
                    
                    <!-- TAB 1: REPORTE -->
                    <div class="tab-pane fade show active" id="tab-reporte" role="tabpanel">
                        
                        <div class="row align-items-start mb-3">
                            <div class="col-md-9">
                                <div id="pricing-info" class="pricing-summary d-none">
                                    <h6>Current Pricing Configuration</h6>
                                    <p id="pricing-info-text">CPU per core/hour: $0.00 | Memory per GB/hour: $0.00</p>
                                </div>
                            </div>
                            <div class="col-md-3 text-right">
                                <button class="btn btn-outline-secondary btn-sm rounded-pill" id="btn-toggle-filters">
                                    <i class="fas fa-filter mr-1"></i> Filter
                                </button>
                                <button class="btn btn-outline-primary btn-sm rounded-pill" id="btn-config-pricing">
                                    <i class="fas fa-cog mr-1"></i> Configure Pricing
                                </button>
                            </div>
                        </div>

                        <div class="filter-bar d-none" id="filter-container">
                            <div class="row align-items-end">
                                <div class="col-md-3">
                                    <label class="small text-muted">Hostgroup de Zabbix</label>
                                    <select id="filter-group" class="form-control select2">
                                        <option value="all">-- Todos los Grupos --</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="small text-muted">Año</label>
                                    <select id="filter-year" class="form-control">
                                        <?php 
                                            $currentYear = date('Y');
                                            for($y = $currentYear; $y >= $currentYear - 5; $y--) {
                                                echo "<option value='$y'>$y</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="small text-muted">Meses (Históricos)</label>
                                    <select id="filter-months" class="form-control select2" multiple="multiple" data-placeholder="Carga actual">
                                        <option value="1">Enero</option>
                                        <option value="2">Febrero</option>
                                        <option value="3">Marzo</option>
                                        <option value="4">Abril</option>
                                        <option value="5">Mayo</option>
                                        <option value="6">Junio</option>
                                        <option value="7">Julio</option>
                                        <option value="8">Agosto</option>
                                        <option value="9">Septiembre</option>
                                        <option value="10">Octubre</option>
                                        <option value="11">Noviembre</option>
                                        <option value="12">Diciembre</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button id="btn-refresh-report" class="btn btn-primary btn-block shadow-sm">
                                        <i class="fas fa-play mr-1"></i> Procesar
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Summary Cards (Visible after processing) -->
                        <div id="summary-widgets" class="row d-none mb-4">
                            <!-- Populated via JS -->
                        </div>

                        <div class="table-responsive">
                            <table id="table-report" class="table cost-table">
                                <thead>
                                    <tr>
                                        <th>Host</th>
                                        <th>Status</th>
                                        <th class="text-center">CPU Cores</th>
                                        <th width="120">CPU Usage</th>
                                        <th class="text-center">Memory (GB)</th>
                                        <th width="120">Memory Usage</th>
                                        <th>CPU Cost/hour</th>
                                        <th>Memory Cost/hour</th>
                                        <th>Total/hour</th>
                                        <th>Total/month</th>
                                        <th>Potential Savings</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="no-data">
                                        <td colspan="11" class="text-center py-5 text-muted">
                                            Seleccione los filtros para visualizar el análisis.
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot id="table-report-footer" class="d-none">
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- TAB 2: CONFIGURACIÓN -->
                    <div class="tab-pane fade" id="tab-config" role="tabpanel">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card card-secondary shadow-sm">
                                    <div class="card-header">
                                        <h3 class="card-title">Nueva Regla de Costo</h3>
                                    </div>
                                    <form id="form-rule">
                                        <div class="card-body">
                                            <div class="form-group">
                                                <label>Alcance de la Regla</label>
                                                <select name="apply_to" id="apply_to" class="form-control">
                                                    <option value="GLOBAL">Global (Todas las VMs/Equipos)</option>
                                                    <option value="HOSTGROUP">Por Hostgroup Específico</option>
                                                </select>
                                            </div>
                                            <div class="form-group d-none" id="group-select-container">
                                                <label>Seleccionar Hostgroup</label>
                                                <select name="hostgroup_id" id="config-hostgroup" class="form-control select2" style="width: 100%;">
                                                </select>
                                                <input type="hidden" name="hostgroup_name" id="hostgroup_name">
                                            </div>
                                            <div class="row">
                                                <div class="col-4">
                                                    <div class="form-group">
                                                        <label>Costo/Hora por Core CPU</label>
                                                        <input type="number" step="0.0001" name="cpu_cost" class="form-control" placeholder="0.00" required>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="form-group">
                                                        <label>Costo/Hora por GB RAM</label>
                                                        <input type="number" step="0.0001" name="mem_cost" class="form-control" placeholder="0.00" required>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="form-group">
                                                        <label>Costo/Hora por GB Disco</label>
                                                        <input type="number" step="0.0001" name="disk_cost" class="form-control" placeholder="0.00" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="small text-warning mt-n2"><i class="fas fa-info-circle mr-1"></i> El costo total se calcula multiplicando esta base por la capacidad asignada a cada host.</p>
                                            <div class="form-group">
                                                <label>Moneda</label>
                                                <input type="text" name="currency" class="form-control" value="USD">
                                            </div>
                                        </div>
                                        <div class="card-footer">
                                            <button type="submit" class="btn btn-success float-right">Guardar Regla</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="card shadow-sm">
                                    <div class="card-header border-0">
                                        <h3 class="card-title">Reglas Activas</h3>
                                    </div>
                                    <div class="card-body p-0">
                                        <table class="table table-striped table-valign-middle" id="table-rules">
                                            <thead>
                                                <tr>
                                                    <th>Alcance</th>
                                                    <th>CPU Cost</th>
                                                    <th>MEM Cost</th>
                                                    <th>DSK Cost</th>
                                                    <th>Moneda</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            </tbody>
                                        </table>
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

<?php require_once __DIR__ . '/../partials/footer.php'; ?>

<script>
$(document).ready(function() {
    // 1. Initial Load
    loadGroups();
    loadRules();

    // 2. Fetch Hostgroups from Zabbix to fill selects
    function loadGroups() {
        $.get('../api_zabbix.php?action=get_groups', function(res) {
            if (res.success) {
                res.data.forEach(g => {
                    $('#filter-group, #config-hostgroup').append(`<option value="${g.groupid}">${g.name}</option>`);
                });
            }
        });
    }

    // 3. Rules Logic
    function loadRules() {
        $.get('../api_costs.php?action=get_rules', function(res) {
            if (res.success) {
                let html = '';
                res.data.forEach(r => {
                    let scopeText = r.apply_to === 'GLOBAL' ? '<span class="badge badge-primary">GLOBAL</span>' : `<span class="badge badge-info">GRUPO: ${r.hostgroup_name}</span>`;
                    
                    if (r.apply_to === 'GLOBAL') {
                        $('#pricing-info-text').html(`CPU per core/hour: <b>$${r.cpu_cost_base}</b> | Memory per GB/hour: <b>$${r.mem_cost_base}</b>`);
                        $('#pricing-info').removeClass('d-none');
                    }

                    html += `
                        <tr>
                            <td>${scopeText}</td>
                            <td>${r.cpu_cost_base}</td>
                            <td>${r.mem_cost_base}</td>
                            <td>${r.disk_cost_base || '0.0000'}</td>
                            <td>${r.currency}</td>
                            <td>
                                <button class="btn btn-xs btn-danger btn-delete-rule" data-id="${r.id}"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    `;
                });
                $('#table-rules tbody').html(html);
            }
        });
    }

    // UI Controls
    $('#btn-toggle-filters').on('click', function() {
        $('#filter-container').toggleClass('d-none');
    });

    $('#btn-config-pricing').on('click', function() {
        $('#tab-config-link').tab('show');
    });

    $('#apply_to').on('change', function() {
        if ($(this).val() === 'HOSTGROUP') {
            $('#group-select-container').removeClass('d-none');
        } else {
            $('#group-select-container').addClass('d-none');
        }
    });

    $('#config-hostgroup').on('change', function() {
        $('#hostgroup_name').val($(this).find('option:selected').text());
    });

    $('#form-rule').on('submit', function(e) {
        e.preventDefault();
        let data = $(this).serialize();
        $.post('../api_costs.php?action=save_rule', data, function(res) {
            if (res.success) {
                toastr.success('Regla guardada correctamente');
                loadRules();
                $('#form-rule')[0].reset();
                $('#group-select-container').addClass('d-none');
            } else {
                toastr.error(res.error);
            }
        });
    });

    $(document).on('click', '.btn-delete-rule', function() {
        if (!confirm('¿Seguro que desea eliminar esta regla?')) return;
        let id = $(this).data('id');
        $.post('../api_costs.php?action=delete_rule', { id: id }, function(res) {
            if (res.success) {
                toastr.success('Regla eliminada');
                loadRules();
            }
        });
    });

    // 4. Report Logic
    $('#btn-refresh-report').on('click', function() {
        let groupid = $('#filter-group').val();
        let year = $('#filter-year').val();
        let months = $('#filter-months').val();

        $(this).prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin mr-1"></i> ANALIZANDO...');
        
        Swal.fire({
            title: 'Procesando Costos',
            text: 'Generando análisis financiero detallado...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading() }
        });
        
        let url = `../api_costs.php?action=calculate_costs&hostgroup_id=${groupid}&year=${year}&months=${months.join(',')}`;
        
        $.get(url, function(res) {
            Swal.close();
            $('#btn-refresh-report').prop('disabled', false).html('<i class="fas fa-play mr-1"></i> Procesar');
            
            if (res.success) {
                let html = '';
                
                // Update Pricing Info Box (Generic estimation if no specific rule but global exists)
                if (res.data.length > 0) {
                    $('#pricing-info').removeClass('d-none');
                    // We take the first host's rule as an example of current config
                    let first = res.data[0];
                    // We need to fetch the actual base rates. Since they are not directly in the report, 
                    // we can infer them or ideally just show a static text from a dedicated API.
                    // For now, let's keep it simple.
                    $('#pricing-info-text').html(`Análisis de ${res.data.length} hosts activos.`);
                }

                if (res.data.length === 0) {
                    html = '<tr><td colspan="11" class="text-center py-5">No se hallaron datos para el filtro seleccionado.</td></tr>';
                    $('#table-report-footer').addClass('d-none');
                } else {
                    let totals = { hosts: res.data.length, cores: 0, mem: 0, usedH: 0, idleH: 0, capH: 0, period: 0 };
                    
                    res.data.forEach(h => {
                        let cur = h.currency || '$';
                        totals.cores += parseFloat(h.capacity.cpu || 0);
                        totals.mem += parseFloat(h.capacity.mem || 0);
                        totals.capH += h.costs.total_hour.cap;
                        totals.usedH += h.costs.total_hour.used;
                        totals.idleH += h.costs.total_hour.idle;
                        totals.period += h.costs.total_period.cap;

                        html += `
                            <tr>
                                <td>
                                    <div class="host-name">${h.name}</div>
                                    <div class="small text-muted">${h.rule_source}</div>
                                </td>
                                <td><span class="badge badge-status text-success">Active</span></td>
                                <td class="text-center">${h.capacity.cpu}</td>
                                <td>
                                    <div class="progress mb-1">
                                        <div class="progress-bar bg-success" style="width: ${h.usage.cpu}%"></div>
                                    </div>
                                    <small class="text-muted">${h.usage.cpu}%</small>
                                </td>
                                <td class="text-center">${h.capacity.mem}</td>
                                <td>
                                    <div class="progress mb-1">
                                        <div class="progress-bar bg-info" style="width: ${h.usage.mem}%"></div>
                                    </div>
                                    <small class="text-muted">${h.usage.mem}%</small>
                                </td>
                                <td>
                                    <div class="text-total-val">$${h.costs.cpu.cap.toFixed(4)}</div>
                                    <div class="text-used-sub">Used: $${h.costs.cpu.used.toFixed(4)}</div>
                                    <div class="text-idle-sub">Idle: $${h.costs.cpu.idle.toFixed(4)}</div>
                                </td>
                                <td>
                                    <div class="text-total-val">$${h.costs.mem.cap.toFixed(4)}</div>
                                    <div class="text-used-sub">Used: $${h.costs.mem.used.toFixed(4)}</div>
                                    <div class="text-idle-sub">Idle: $${h.costs.mem.idle.toFixed(4)}</div>
                                </td>
                                <td>
                                    <div class="text-total-val">$${h.costs.total_hour.cap.toFixed(4)}</div>
                                    <div class="text-used-sub">Used: ${(h.usage.cpu/2 + h.usage.mem/2).toFixed(1)}%</div>
                                    <div class="text-idle-sub">Idle: ${(100 - (h.usage.cpu/2 + h.usage.mem/2)).toFixed(1)}%</div>
                                </td>
                                <td>
                                    <div class="text-total-val" style="font-size: 1rem">$${h.costs.total_period.cap.toLocaleString()}</div>
                                </td>
                                <td>
                                    <div class="text-savings">$${h.costs.total_period.idle.toLocaleString()}/mo</div>
                                    <small class="text-muted">${(h.costs.total_hour.idle / h.costs.total_hour.cap * 100).toFixed(1)}% idle</small>
                                </td>
                            </tr>
                        `;
                    });

                    // Summary Row
                    let avgEff = (totals.usedH / totals.capH * 100).toFixed(1);
                    foot = `
                        <tr class="summary-row">
                            <td>Summary</td>
                            <td class="text-center">${totals.hosts}</td>
                            <td class="text-center">${totals.cores}</td>
                            <td class="text-center">${totals.mem.toFixed(2)}</td>
                            <td colspan="2">
                                <div class="progress" style="height: 12px; min-width: 150px;">
                                    <div class="progress-bar bg-success" style="width: ${avgEff}%"></div>
                                </div>
                                <small class="text-muted font-weight-bold">${avgEff}% used</small>
                            </td>
                            <td><span class="text-used-sub">$${totals.usedH.toFixed(2)}</span></td>
                            <td><span class="text-idle-sub">$${totals.idleH.toFixed(2)}</span></td>
                            <td><span class="text-total-val">$${totals.capH.toFixed(2)}</span></td>
                            <td colspan="2" class="text-right">
                                <span class="text-total-val" style="font-size: 1.3rem; font-weight: 800;">$${totals.period.toLocaleString()}</span>
                            </td>
                        </tr>
                    `;
                    $('#table-report-footer').removeClass('d-none').html(foot);
                    $('#hosts-count-footer').text(`Displaying ${totals.hosts} of ${totals.hosts} found`);
                }
                $('#table-report tbody').html(html);
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        });
    });

});
</script>
