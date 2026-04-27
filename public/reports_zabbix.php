<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';

require_login();

$page_title = 'Informes Personalizados Zabbix';
include __DIR__ . '/partials/header.php';
?>

<!-- CSS Libraries (Verified jsDelivr Paths) -->
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.11.5/css/dataTables.bootstrap4.min.css"/>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/datatables.net-buttons-bs4@2.2.2/css/buttons.bootstrap4.min.css"/>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<style>
    .report-card { border-radius: 12px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.05) !important; }
    .table-report thead th { background: #343a40; color: #fff; border: none; font-size: 0.8rem; }
    .btn-export { border-radius: 4px; font-weight: 500; font-size: 0.75rem; }
    .badge-tag { background: #f1f3f5; color: #333; border: 1px solid #dee2e6; }
    .callout-info { border-left: 5px solid #17a2b8; background: #fff; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
</style>

<div class="container-fluid py-4">
    <div class="row mb-3">
        <div class="col-12">
            <h1 class="h4 mb-1 font-weight-bold text-dark"><i class="fas fa-file-invoice mr-2 text-primary"></i> Generador de Reportes Técnicos</h1>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card report-card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 border-right">
                    <div class="form-group pb-2">
                        <label class="small font-weight-bold">Nombre Equipo</label>
                        <input type="text" id="filter-search" class="form-control form-control-sm" placeholder="Buscar...">
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Grupos Zabbix</label>
                        <select id="filter-groups" class="form-control select2bs4" multiple="multiple"></select>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Criterio Tags</label>
                        <select id="filter-evaltype" class="form-control form-control-sm">
                            <option value="0">Contiene cualquier tag (OR)</option>
                            <option value="2">Contiene todos los tags (AND)</option>
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold">Verificaciones de Estado</label>
                        <select id="filter-checks" class="form-control select2bs4" multiple="multiple">
                            <option value="icmp">Ping (ICMP)</option>
                            <option value="snmp">SNMP Status</option>
                            <option value="agent">Zabbix Agent</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-5 border-right">
                    <label class="small font-weight-bold d-flex justify-content-between">
                        Filtro Etiquetas (Tags)
                        <button class="btn btn-xs btn-success" id="btn-add-tag"><i class="fas fa-plus mr-1"></i>Añadir</button>
                    </label>
                    <div id="tags-container" style="max-height: 180px; overflow-y: auto;">
                        <p class="text-muted small text-center my-3" id="msg-no-tags">Sin filtros de etiquetas activos</p>
                    </div>
                </div>
                <div class="col-md-3 d-flex flex-column justify-content-center">
                    <button id="btn-run" class="btn btn-primary shadow-sm py-3 mb-2 font-weight-bold">
                        <i class="fas fa-sync-alt mr-2" id="icon-run"></i> GENERAR REPORTE
                    </button>
                    <button id="btn-reset" class="btn btn-light btn-sm text-muted">Limpiar Pantalla</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Resumen -->
    <div id="summary-section" class="d-none">
        <div class="callout-info shadow-sm bg-white border">
            <div class="row align-items-center">
                <div class="col-md-2 text-center border-right">
                    <span class="text-muted d-block small text-uppercase font-weight-bold">Total Equipos</span>
                    <h2 class="font-weight-bold text-primary mb-0" id="stat-total-hosts">0</h2>
                </div>
                <div id="debug-text-area" class="col-md-10 pl-4 small text-muted"></div>
            </div>
        </div>
    </div>

    <!-- Gráficas -->
    <div class="row mb-4 d-none" id="charts-section">
        <div class="col-md-6">
            <div class="card report-card shadow-sm h-100">
                <div class="card-header bg-white font-weight-bold border-0 py-3">Distribución por Hostgroup</div>
                <div class="card-body">
                    <div style="height: 320px; position: relative;"><canvas id="chart-groups"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card report-card shadow-sm h-100">
                <div class="card-header bg-white font-weight-bold border-0 py-3">Análisis de Etiquetas (Top 10)</div>
                <div class="card-body">
                    <div style="height: 320px; position: relative;"><canvas id="chart-tags"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card report-card d-none shadow-lg" id="table-section">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="report-table" class="table table-hover table-sm m-0" style="font-size:0.85rem">
                    <thead class="thead-dark">
                        <tr id="table-header">
                            <th>ZBX</th>
                            <th>Visible Name</th>
                            <th>Hostname</th>
                            <th>IPs</th>
                            <th>Grupos</th>
                            <th>Tags</th>
                            <!-- Dynamic Columns -->
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- JS Scripts (Verified jsDelivr Paths) -->
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.11.5/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons-bs4@2.2.2/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jszip@3.1.3/dist/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pdfmake@0.1.53/build/pdfmake.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pdfmake@0.1.53/build/vfs_fonts.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.2.2/js/buttons.html5.min.js"></script>

<script>
$(function() {
    $('.select2bs4').select2({ theme: 'bootstrap4' });
    let table = null, gChart = null, tChart = null;

    $.get('api_zabbix.php?action=get_groups', r => {
        if (r.success) r.data.forEach(g => { $('#filter-groups').append(new Option(g.name, g.groupid)); });
    });

    $('#btn-add-tag').click(() => {
        $('#msg-no-tags').hide();
        $('#tags-container').append(`
            <div class="tag-row d-flex mb-1">
                <input type="text" class="form-control form-control-sm mr-1 t-name" placeholder="Tag">
                <input type="text" class="form-control form-control-sm mr-1 t-val" placeholder="Valor">
                <button class="btn btn-xs btn-link text-danger b-rem"><i class="fas fa-times"></i></button>
            </div>`);
    });

    $(document).on('click', '.b-rem', function() { $(this).parent().remove(); if ($('.tag-row').length==0) $('#msg-no-tags').show(); });

    $('#btn-reset').click(() => { location.reload(); });

    $('#btn-run').click(function() {
        const btn = $(this);
        btn.prop('disabled', true);
        $('#icon-run').addClass('fa-spin');

        const tags = [];
        $('.tag-row').each(function() {
            const n = $(this).find('.t-name').val().trim();
            if (n) tags.push({ tag: n, value: $(this).find('.t-val').val().trim(), operator: 0 });
        });

        $.get('api_zabbix.php', {
            action: 'get_hosts',
            search: $('#filter-search').val(),
            groupids: $('#filter-groups').val()?.join(','),
            tags: tags.length ? JSON.stringify(tags) : '',
            evaltype: $('#filter-evaltype').val(),
            with_checks: $('#filter-checks').val()?.join(',')
        }, function(resp) {
            btn.prop('disabled', false);
            $('#icon-run').removeClass('fa-spin');
            if (resp.success) {
                render(resp.data);
                $('#table-section, #charts-section, #summary-section').removeClass('d-none');
            }
        });
    });

    function render(data) {
        if (table) table.destroy();
        const tbody = $('#report-table tbody').empty();
        const gStats = {}, tStats = {};
        
        const selectedChecks = $('#filter-checks').val() || [];
        const hasIcmp = selectedChecks.includes('icmp');
        const hasSnmp = selectedChecks.includes('snmp');
        const hasAgent = selectedChecks.includes('agent');

        // Update Header
        let headerHtml = '<th>ZBX</th><th>Visible Name</th><th>Hostname</th><th>IPs</th><th>Grupos</th><th>Tags</th>';
        if (hasIcmp) headerHtml += '<th>Ping</th>';
        if (hasSnmp) headerHtml += '<th>SNMP</th>';
        if (hasAgent) headerHtml += '<th>Agent</th>';
        headerHtml += '<th>Acciones</th>';
        $('#table-header').html(headerHtml);

        data.forEach(h => {
            const status = h.available == 1 ? '<span class="text-success">●</span>' : (h.available == 2 ? '<span class="text-danger">●</span>' : '<span class="text-secondary">○</span>');
            
            const rawGroups = h.groups || h.hostgroups || [];
            const grps = rawGroups.map(g => {
                const n = (g.name || 'Sin Nombre').trim();
                gStats[n] = (gStats[n] || 0) + 1;
                return `<span class="badge badge-light border mr-1 font-weight-normal">${n}</span>`;
            }).join('');
            const tags = (h.tags || []).map(t => {
                const l = `${t.tag}: ${t.value}`;
                tStats[l] = (tStats[l] || 0) + 1;
                return `<span class="badge badge-tag mr-1 small">${l}</span>`;
            }).join('');

            let rowHtml = `<tr>
                <td>${status}</td>
                <td class="font-weight-bold">${h.name}</td>
                <td>${h.host}</td>
                <td>${(h.interfaces || []).map(i=>i.ip).join(', ')}</td>
                <td>${grps}</td>
                <td>${tags}</td>`;
            
            if (hasIcmp) {
                // Determine Ping Status
                let pStat = '<span class="badge badge-secondary">N/A</span>';
                if (h.icmp_ping !== undefined && h.icmp_ping !== null) {
                    pStat = parseInt(h.icmp_ping) === 1 ? '<span class="badge badge-success">OK</span>' : '<span class="badge badge-danger">FAIL</span>';
                }
                rowHtml += `<td>${pStat}</td>`;
            }
            if (hasSnmp) {
                const sAvail = parseInt(h.snmp_available || 0);
                const sStat = sAvail === 1 ? '<span class="badge badge-success">OK</span>' : (sAvail === 2 ? '<span class="badge badge-danger">FAIL</span>' : '<span class="badge badge-secondary">N/A</span>');
                rowHtml += `<td>${sStat}</td>`;
            }
            if (hasAgent) {
                const aAvail = parseInt(h.available || 0);
                const aStat = aAvail === 1 ? '<span class="badge badge-success">OK</span>' : (aAvail === 2 ? '<span class="badge badge-danger">FAIL</span>' : '<span class="badge badge-secondary">N/A</span>');
                rowHtml += `<td>${aStat}</td>`;
            }

            rowHtml += `<td><a href="zabbix.php?action=host.view&hostids[]=${h.hostid}" target="_blank" class="btn btn-xs btn-primary"><i class="fas fa-eye"></i></a></td>
            </tr>`;
            tbody.append(rowHtml);
        });

        $('#stat-total-hosts').text(data.length);
        console.log('Report Data Rendered:', data.length, 'hosts. Checks:', selectedChecks);
        
        let topG = Object.entries(gStats).sort((a,b)=>b[1]-a[1]).slice(0,5);
        let debugHtml = '<strong>Análisis de Grupos:</strong> ' + topG.map(e => `<span class="badge badge-info ml-2">${e[1]}</span> ${e[0]}`).join(' | ');
        
        $('#debug-text-area').html(debugHtml);

        table = $('#report-table').DataTable({ dom: 'Bfrtip', buttons: ['excel', 'pdf'], language: { search: "" }, pageLength: 20 });

        if (gChart) gChart.destroy();
        if (tChart) tChart.destroy();

        if (topG.length) {
            gChart = new Chart(document.getElementById('chart-groups'), {
                type: 'bar',
                data: {
                    labels: topG.map(e => e[0]),
                    datasets: [{ label: 'Equipos', data: topG.map(e => e[1]), backgroundColor: '#17a2b8' }]
                },
                options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });
        }

        const sortedTags = Object.entries(tStats).sort((a, b) => b[1] - a[1]).slice(0, 10);
        if (sortedTags.length > 0) {
            tChart = new Chart(document.getElementById('chart-tags'), {
                type: 'bar',
                data: {
                    labels: sortedTags.map(e => e[0]),
                    datasets: [{ label: 'Equipos', data: sortedTags.map(e => e[1]), backgroundColor: '#ffc107' }]
                },
                options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });
        }
    }
});
</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
