<?php
/**
 * CMDB VILASECA - Gestión y Escaneo SNMP (Refined Version)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$page_title = 'Gestión SNMP Avanzada';
require_once __DIR__ . '/partials/header.php';
?>


<style>
    .premium-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05) !important;
    }
    .status-badge {
        padding: 5px 12px;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
    }
    .badge-pending { background: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6; }
    .badge-success-snmp { background: #e8f5e9; color: #2e7d32; }
    .badge-fail-snmp { background: #ffebee; color: #c62828; }
    .badge-offline { background: #fff3e0; color: #ef6c00; }
    
    .table-snmp thead th {
        background: #f1f3f5;
        border-top: none;
        font-size: 0.8rem;
        color: #495057;
    }
    .dark-mode .table-snmp thead th {
        background: #343a40;
        color: #ced4da;
    }
    
    #scanProgress {
        background: #fff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }
    .dark-mode #scanProgress {
        background: #2c3034;
    }
    .bg-success-light { background: #e8f5e9; }
    .bg-danger-light { background: #ffebee; }
    .bg-warning-light { background: #fff3e0; }

    /* Select2 Premium Customization */
    .select2-container--bootstrap4 .select2-selection {
        border-radius: 8px !important;
        border: 1px solid #ced4da !important;
        height: auto !important;
        min-height: 31px !important;
    }
    .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice {
        background-color: #e9ecef !important;
        border: 1px solid #dee2e6 !important;
        color: #495057 !important;
        border-radius: 4px !important;
        padding: 0 8px !important;
        margin-top: 4px !important;
    }
    .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice__remove {
        color: #dc3545 !important;
        margin-right: 5px !important;
    }
    .select2-container--bootstrap4.select2-container--focus .select2-selection {
        border-color: #80bdff !important;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
    }
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">



<div class="container-fluid py-4">
    <div class="row align-items-center mb-3">
        <div class="col">
            <h1 class="h3 mb-0 font-weight-bold text-primary"><i class="fas fa-microchip mr-2"></i> Motor de Escaneo SNMP</h1>
            <p class="text-muted small mb-0">Gestión de comunidades y validación de red para activos del inventario.</p>
        </div>
        <div class="col-auto d-flex align-items-center">
            <div class="btn-group btn-group-toggle mr-3 shadow-sm" data-toggle="buttons" id="source-toggle">
                <label class="btn btn-dark active btn-sm">
                    <input type="radio" name="source" id="source_cmdb" checked> <i class="fas fa-database mr-1"></i> Inventario CMDB
                </label>
                <label class="btn btn-secondary btn-sm">
                    <input type="radio" name="source" id="source_zabbix"> <i class="fas fa-server mr-1"></i> Servidor Zabbix
                </label>
            </div>
            <button type="button" class="btn btn-primary shadow-sm px-4 btn-sm" data-toggle="modal" data-target="#modalCommunity">
                <i class="fas fa-key mr-2"></i> Comunidades
            </button>
        </div>
    </div>

    <!-- Estadísticas Rápidas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 bg-white p-3 d-flex flex-row align-items-center">
                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                    <i class="fas fa-list text-primary"></i>
                </div>
                <div class="ml-3">
                    <h6 class="mb-0 text-muted small font-weight-bold uppercase">Total Equipos</h6>
                    <h3 class="mb-0 font-weight-bold text-dark" id="statTotal">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 bg-white p-3 d-flex flex-row align-items-center">
                <div class="rounded-circle bg-success-light d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                    <i class="fas fa-check-circle text-success"></i>
                </div>
                <div class="ml-3">
                    <h6 class="mb-0 text-muted small font-weight-bold uppercase">SNMP OK</h6>
                    <h3 class="mb-0 font-weight-bold text-success" id="statSuccess">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 bg-white p-3 d-flex flex-row align-items-center">
                <div class="rounded-circle bg-danger-light d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                    <i class="fas fa-exclamation-triangle text-danger"></i>
                </div>
                <div class="ml-3">
                    <h6 class="mb-0 text-muted small font-weight-bold uppercase">Fallo / Offline</h6>
                    <h3 class="mb-0 font-weight-bold text-danger" id="statFailed">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 bg-white p-3 d-flex flex-row align-items-center">
                <div class="rounded-circle bg-warning-light d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                    <i class="fas fa-clock text-warning"></i>
                </div>
                <div class="ml-3">
                    <h6 class="mb-0 text-muted small font-weight-bold uppercase">Pendientes</h6>
                    <h3 class="mb-0 font-weight-bold text-warning" id="statPending">0</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card premium-card">
        <div class="card-header bg-white py-3 border-bottom-0">
            <div class="row align-items-center">
                <div class="col-lg-3 col-md-4 mb-2 mb-md-0">
                    <div class="input-group input-group-sm mb-0">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-light border-right-0"><i class="fas fa-search text-muted"></i></span>
                        </div>
                        <input type="text" id="tableSearch" class="form-control border-left-0" placeholder="Buscar por IP, Nombre...">
                    </div>
                </div>
                <div class="col-lg-4 col-md-8 mb-2 mb-md-0">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-filter text-muted mr-2 small"></i>
                        <div class="flex-grow-1">
                            <select id="categoryFilter" class="form-control select2" multiple="multiple" data-placeholder="Todas las Categorías">
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5 col-md-12 text-lg-right">
                    <div class="btn-group btn-group-toggle mr-2" data-toggle="buttons">
                        <label class="btn btn-outline-secondary btn-sm active px-3" id="btnFilterNew">
                            <input type="radio" name="options" autocomplete="off" checked> Nuevos
                        </label>
                        <label class="btn btn-outline-secondary btn-sm px-3" id="btnFilterAll">
                            <input type="radio" name="options" autocomplete="off"> Todos
                        </label>
                    </div>
                    <div class="btn-group">
                        <button type="button" id="btnSyncZabbix" class="btn btn-info btn-sm shadow-sm" title="Sincronizar con Zabbix">
                            <i class="fas fa-sync"></i>
                        </button>
                        <button type="button" id="btnStartSelectedScan" class="btn btn-success btn-sm shadow-sm px-3" title="Ejecutar Escaneo">
                            <i class="fas fa-play mr-1"></i> Escanear
                        </button>
                        <button type="button" id="btnCommitResults" class="btn btn-dark btn-sm shadow-sm px-3" disabled title="Guardar cambios">
                            <i class="fas fa-save"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div id="scanProgress" style="display:none;" class="m-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span id="progressText" class="font-weight-bold small">Preparando...</span>
                    <span id="progressStat" class="badge badge-success px-3"></span>
                </div>
                <div class="progress progress-sm" style="height: 8px;">
                    <div id="progressBar" class="progress-bar bg-success" role="progressbar" style="width: 0%"></div>
                </div>
            </div>
            
            <div class="table-responsive" style="max-height: 700px;">
                <table class="table table-hover table-snmp mb-0" id="tableScan">
                    <thead class="sticky-top shadow-sm">
                        <tr>
                            <th class="text-center" style="width: 50px">
                                <div class="custom-control custom-checkbox">
                                    <input class="custom-control-input" type="checkbox" id="checkAll">
                                    <label for="checkAll" class="custom-control-label"></label>
                                </div>
                            </th>
                            <th class="sortable" data-col="display_name">Categoría <i class="fas fa-sort ml-1 opacity-50"></i></th>
                            <th class="sortable" data-col="ip">Dirección IP <i class="fas fa-sort ml-1 opacity-50"></i></th>
                            <th class="sortable" data-col="name">Hostname <i class="fas fa-sort ml-1 opacity-50"></i></th>
                            <th class="sortable" data-col="last_success">Última Ref. <i class="fas fa-sort ml-1 opacity-50"></i></th>
                            <th>Estatus</th>
                            <th>Comunidad</th>
                            <th class="text-center">Int. UP</th>
                            <th style="width: 60px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dinámico JS -->
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-light py-2 d-flex justify-content-between align-items-center">
            <span class="badge badge-light border text-muted px-3 py-2" id="infoSelected">0 equipos seleccionados</span>
            <nav aria-label="Navegación de tabla">
                <ul class="pagination pagination-sm mb-0" id="tablePagination">
                    <!-- Dinámico JS -->
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Modal: Gestión de Comunidades -->
<div class="modal fade" id="modalCommunity" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content overflow-hidden border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-key mr-2"></i> Diccionario SNMP</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-4">
                <form id="formCommunity" class="mb-4">
                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                    <div class="row no-gutters">
                        <div class="col">
                            <input type="text" name="community" class="form-control form-control-sm rounded-0 border-right-0" placeholder="Community String" required>
                        </div>
                        <div class="col">
                            <input type="text" name="description" class="form-control form-control-sm rounded-0 border-right-0" placeholder="Descripción">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-sm rounded-0"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                </form>
                <div id="listCommunities" style="max-height: 350px; overflow-y: auto;">
                    <!-- Se carga via JS -->
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '<?php echo get_csrf_token(); ?>';
    let scanData = { ips: [], communities: [] };
    let tempResults = [];
    let filterMode = 'new';
    let sortCol = 'ip';
    let sortAsc = true;
    let searchText = '';
    let selectedCategory = [];
    let currentPage = 1;
    const itemsPerPage = 15;


    loadCommunities();
    loadScanData();

    document.getElementById('tableSearch').onkeyup = function() {
        searchText = this.value.toLowerCase();
        currentPage = 1;
        renderTable();
    };

    $('#categoryFilter').select2({
        width: '100%',
        theme: 'bootstrap4'
    }).on('change', function() {
        selectedCategory = $(this).val();
        currentPage = 1;
        renderTable();
    });

    document.getElementById('btnFilterNew').onclick = () => { filterMode = 'new'; currentPage = 1; updateFilterUI(); renderTable(); };
    document.getElementById('btnFilterAll').onclick = () => { filterMode = 'all'; currentPage = 1; updateFilterUI(); renderTable(); };

    function updateFilterUI() {
        document.getElementById('btnFilterNew').classList.toggle('active', filterMode === 'new');
        document.getElementById('btnFilterAll').classList.toggle('active', filterMode === 'all');
    }

    document.getElementById('checkAll').onchange = function() {
        document.querySelectorAll('.check-item:not(:disabled)').forEach(cb => cb.checked = this.checked);
        updateSelectedCount();
    };

    function updateSelectedCount() {
        const count = document.querySelectorAll('.check-item:checked').length;
        const badge = document.getElementById('infoSelected');
        badge.innerText = `${count} equipos seleccionados`;
        badge.className = count > 0 ? 'badge badge-primary px-3 py-2 shadow-sm' : 'badge badge-light border text-muted px-3 py-2';
    }

    function loadCommunities() {
        fetch('api_action.php?action=list_snmp_communities')
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                scanData.communities = res.data;
                const container = document.getElementById('listCommunities');
                container.innerHTML = '<div class="list-group list-group-flush">';
                res.data.forEach(c => {
                    container.innerHTML += `
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-0">
                            <div>
                                <code class="font-weight-bold text-primary">${c.community}</code>
                                <div class="text-muted small">${c.description || 'Sin descripción'}</div>
                            </div>
                            <button class="btn btn-xs btn-outline-danger btnDeleteComm" data-id="${c.id}"><i class="fas fa-times"></i></button>
                        </div>
                    `;
                });
                container.innerHTML += '</div>';
                attachDeleteCommEvents();
            }
        });
    }

    function loadScanData() {
        const source = document.querySelector('input[name="source"]:checked').id;
        
        if (source === 'source_zabbix') {
            fetch('snmpbuilder/get_zabbix_hosts.php')
            .then(r => r.json())
            .then(res => {
                if (res.error) {
                    toastr.error('Error Zabbix: ' + res.error);
                    return;
                }
                // Mapear formato Zabbix a formato ScanData
                scanData.ips = res.result.map(z => ({
                    table: 'zabbix',
                    id: z.ip,
                    ip: z.ip,
                    name: z.name,
                    display_name: z.group || 'Zabbix Host',
                    community_ok: z.available == 1 ? z.community : null,
                    last_success: z.available == 1 ? new Date() : null,
                    zabbix_available: z.available,
                    zabbix_error: z.error
                }));
                populateCategoryFilter();
                renderTable();
            });
        } else {
            fetch('api_action.php?action=get_snmp_scan_data')
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    scanData.ips = res.ips;
                    populateCategoryFilter();
                    renderTable();
                }
            });
        }
    }

    function populateCategoryFilter() {
        const select = $('#categoryFilter');
        const categories = [...new Set(scanData.ips.map(item => item.display_name))];
        
        select.empty();
        categories.sort().forEach(cat => {
            const option = new Option(cat.replace('Inventario ', ''), cat, false, false);
            select.append(option);
        });
        
        select.trigger('change');
    }



    // Eventos de cambio de fuente
    $('input[name="source"]').change(function() {
        $("#source-toggle label").removeClass('active btn-dark').addClass('btn-secondary');
        $(this).parent().addClass('active btn-dark').removeClass('btn-secondary');
        loadScanData();
    });

    function renderTable() {
        const tbody = document.querySelector('#tableScan tbody');
        tbody.innerHTML = '';
        
        let stats = { total: scanData.ips.length, success: 0, failed: 0, pending: 0 };
        
        scanData.ips.forEach(item => {
            if (item.table === 'zabbix') {
                if (item.zabbix_available == 1) stats.success++;
                else if (item.zabbix_available == 2) stats.failed++;
                else stats.pending++;
            } else {
                if (item.community_ok) stats.success++;
                else stats.pending++;
            }
        });

        document.getElementById('statTotal').innerText = stats.total;
        document.getElementById('statSuccess').innerText = stats.success;
        document.getElementById('statFailed').innerText = stats.failed;
        document.getElementById('statPending').innerText = stats.pending;

        const matchesAny = selectedCategory && selectedCategory.length > 0;
        
        let filtered = !matchesAny ? [] : scanData.ips.filter(item => {
            const matchesFilter = filterMode === 'new' ? !item.community_ok : true;
            const matchesSearch = item.ip.toLowerCase().includes(searchText) || 
                                 (item.name || '').toLowerCase().includes(searchText) ||
                                 item.display_name.toLowerCase().includes(searchText);
            const matchesCategory = selectedCategory.includes(item.display_name);
            return matchesFilter && matchesSearch && matchesCategory;
        });

        filtered.sort((a, b) => {
            let valA = (a[sortCol] || '').toString().toLowerCase();
            let valB = (b[sortCol] || '').toString().toLowerCase();
            if (valA < valB) return sortAsc ? -1 : 1;
            if (valA > valB) return sortAsc ? 1 : -1;
            return 0;
        });
        
        // Paginación
        const totalFiltered = filtered.length;
        const totalPages = Math.ceil(totalFiltered / itemsPerPage);
        if (currentPage > totalPages) currentPage = Math.max(1, totalPages);
        
        const start = (currentPage - 1) * itemsPerPage;
        const end = start + itemsPerPage;
        const pagedData = filtered.slice(start, end);

        renderPagination(totalPages);

        if (!matchesAny) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="fas fa-filter fa-2x mb-2 d-block opacity-25"></i>Selecciona una o más categorías para comenzar...</td></tr>';
            return;
        }

        if (pagedData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="fas fa-folder-open fa-2x mb-2 d-block opacity-25"></i>No hay registros para mostrar</td></tr>';
            return;
        }

        pagedData.forEach(item => {
            const isPersisted = !!item.community_ok || (item.status && item.status !== 'PENDING');
            const dateStr = item.last_success ? new Date(item.last_success).toLocaleDateString() : '--';
            
            const upInterfaces = item.interfaces_up_json ? JSON.parse(item.interfaces_up_json) : [];
            const intUpCount = upInterfaces.length;
            const intUpList = upInterfaces.join(', ');

            let statusBadge = '<span class="status-badge badge-pending">Pendiente</span>';
            const dbStatus = item.status || 'PENDING';

            if (dbStatus === 'SUCCESS') {
                statusBadge = '<span class="status-badge badge-success-snmp"><i class="fas fa-check mr-1"></i> Validado</span>';
            } else if (dbStatus === 'OFFLINE') {
                statusBadge = '<span class="status-badge badge-offline"><i class="fas fa-ghost mr-1"></i> Offline</span>';
            } else if (dbStatus === 'FAILED') {
                statusBadge = '<span class="status-badge badge-fail-snmp"><i class="fas fa-times mr-1"></i> Falló</span>';
            }
            
            if (item.table === 'zabbix') {
               if (item.zabbix_available == 1) {
                   statusBadge = '<span class="status-badge badge-success-snmp"><i class="fas fa-check-double mr-1"></i> Zabbix UP</span>';
               } else if (item.zabbix_available == 2) {
                   statusBadge = `<span class="status-badge badge-fail-snmp" title="${item.zabbix_error}"><i class="fas fa-exclamation-triangle mr-1"></i> Zabbix Down</span>`;
               }
            }

            tbody.innerHTML += `
                <tr id="row-${item.table}-${item.id}" class="${isPersisted && item.status !== 'PENDING' ? 'text-muted' : ''}">
                    <td class="text-center align-middle">
                        <div class="custom-control custom-checkbox ml-1">
                            <input class="custom-control-input check-item" type="checkbox" 
                                id="chk-${item.table}-${item.id}" 
                                data-ip="${item.ip}" data-table="${item.table}" data-id="${item.id}">
                            <label for="chk-${item.table}-${item.id}" class="custom-control-label"></label>
                        </div>
                    </td>
                    <td class="align-middle"><span class="badge badge-light border font-weight-normal">${item.display_name.replace('Inventario ', '')}</span></td>
                    <td class="align-middle font-weight-bold">${item.ip}</td>
                    <td class="align-middle small">${item.name || '-'}</td>
                    <td class="align-middle text-muted small">${dateStr}</td>
                    <td class="align-middle status-cell">
                        ${statusBadge}
                    </td>
                    <td class="align-middle community-cell small">
                        ${isPersisted ? `<code class="bg-light p-1 rounded">${item.community_ok}</code>` : '-'}
                    </td>
                    <td class="align-middle text-center">
                        ${intUpCount > 0 ? `<span class="badge badge-pill badge-info pointer" title="${intUpList}">${intUpCount}</span>` : '<span class="text-muted">-</span>'}
                    </td>
                    <td class="align-middle text-right">
                        ${isPersisted && item.table !== 'zabbix' ? `<button class="btn btn-xs btn-link text-danger btnForget" data-ip="${item.ip}" data-table="${item.table}" data-id="${item.id}" title="Reiniciar Validación"><i class="fas fa-sync-alt"></i></button>` : ''}
                    </td>
                </tr>
            `;
        });
        updateSortIcons();
        attachTableEvents();
    }

    function renderPagination(totalPages) {
        const container = document.getElementById('tablePagination');
        container.innerHTML = '';
        
        if (totalPages <= 1) return;

        // Botón Anterior
        container.innerHTML += `
            <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="changePage(${currentPage - 1}); return false;"><i class="fas fa-chevron-left"></i></a>
            </li>
        `;

        // Lógica saltos de página (simplificada)
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, startPage + 4);
        if (endPage === totalPages) startPage = Math.max(1, endPage - 4);

        for (let i = startPage; i <= endPage; i++) {
            container.innerHTML += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
                </li>
            `;
        }

        // Botón Siguiente
        container.innerHTML += `
            <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="changePage(${currentPage + 1}); return false;"><i class="fas fa-chevron-right"></i></a>
            </li>
        `;
    }

    window.changePage = function(page) {
        currentPage = page;
        renderTable();
    };

    function updateSortIcons() {
        document.querySelectorAll('th.sortable i').forEach(i => i.className = 'fas fa-sort ml-1 opacity-50');
        const activeTh = document.querySelector(`th.sortable[data-col="${sortCol}"] i`);
        if (activeTh) activeTh.className = sortAsc ? 'fas fa-sort-up ml-1 text-primary' : 'fas fa-sort-down ml-1 text-primary';
    }

    document.querySelectorAll('th.sortable').forEach(th => {
        th.style.cursor = 'pointer';
        th.onclick = function() {
            const col = this.dataset.col;
            if (sortCol === col) { sortAsc = !sortAsc; } else { sortCol = col; sortAsc = true; }
            renderTable();
        };
    });

    document.getElementById('formCommunity').onsubmit = function(e) {
        e.preventDefault();
        fetch('api_action.php?action=save_snmp_community', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(res => { if(res.success) { this.reset(); loadCommunities(); toastr.success('Comunidad agregada.'); } });
    };

    function attachDeleteCommEvents() {
        document.querySelectorAll('.btnDeleteComm').forEach(btn => {
            btn.onclick = function() {
                const fd = new FormData();
                fd.append('action', 'delete_snmp_community');
                fd.append('id', this.dataset.id);
                fd.append('csrf_token', csrfToken);
                fetch('api_action.php', { method: 'POST', body: fd }).then(() => { loadCommunities(); toastr.info('Comunidad eliminada.'); });
            };
        });
    }

    function attachTableEvents() {
        updateSelectedCount();
        document.querySelectorAll('.check-item').forEach(cb => { cb.onchange = updateSelectedCount; });
        document.querySelectorAll('.btnForget').forEach(btn => {
            btn.onclick = function() {
                Swal.fire({
                    title: '¿Confirmar reinicio?',
                    text: "Se eliminará el registro histórico de este equipo. Volverá a aparecer como pendiente.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, reiniciar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const fd = new FormData();
                        fd.append('action', 'delete_snmp_scan_result');
                        fd.append('ip', this.dataset.ip);
                        fd.append('table', this.dataset.table);
                        fd.append('id', this.dataset.id);
                        fd.append('csrf_token', csrfToken);
                        fetch('api_action.php', { method: 'POST', body: fd }).then(() => { loadScanData(); toastr.success('Validación reiniciada.'); });
                    }
                });
            };
        });
    }

    document.getElementById('btnSyncZabbix').onclick = function() {
        const btn = this;
        const oldHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Sincronizando...';

        fetch('snmpbuilder/get_zabbix_hosts.php')
        .then(r => r.json())
        .then(res => {
            if (res.error) {
                toastr.error('Error al conectar con Zabbix: ' + res.error);
                return;
            }
            
            let syncCount = 0;
            const zabbixData = res.result;
            
            scanData.ips.forEach(item => {
                // Si ya está validado, no hacemos nada
                if (item.community_ok) return;

                const match = zabbixData.find(z => z.ip === item.ip);
                if (match) {
                    const rowElem = document.getElementById(`row-${item.table}-${item.id}`);
                    if (rowElem) {
                        const statusCell = rowElem.querySelector('.status-cell');
                        const commCell = rowElem.querySelector('.community-cell');
                        
                        if (match.available == 1) {
                            statusCell.innerHTML = '<span class="status-badge badge-success-snmp"><i class="fas fa-check-double mr-1"></i> Zabbix UP</span>';
                            commCell.innerHTML = `<code class="bg-info text-white p-1 rounded px-2" style="font-size:0.7rem;">${match.community}</code>`;
                            
                            tempResults.push({ 
                                ip: item.ip, 
                                table: item.table, 
                                id: item.id, 
                                community: match.community 
                            });
                            
                            rowElem.classList.add('bg-info-light');
                            syncCount++;
                        } else {
                            statusCell.innerHTML = `<span class="status-badge badge-fail-snmp" title="${match.error}"><i class="fas fa-exclamation-triangle mr-1"></i> Zabbix Down</span>`;
                            commCell.innerHTML = `<small class="text-muted italic">${match.community}</small>`;
                        }
                    }
                }
            });

            btn.disabled = false;
            btn.innerHTML = oldHtml;
            
            if (syncCount > 0) {
                document.getElementById('btnCommitResults').disabled = false;
                Swal.fire('Sincronización Exitosa', `Se encontraron ${syncCount} coincidencias en Zabbix. Haz clic en "Persistir Datos" para guardar los cambios.`, 'success');
            } else {
                Swal.fire('Sin coincidencias', 'No se encontraron equipos en Zabbix que coincidan con las IPs pendientes en el inventario.', 'info');
            }
        })
        .catch(err => {
            toastr.error('Error de comunicación con el motor de sincronización.');
            btn.disabled = false;
            btn.innerHTML = oldHtml;
        });
    };

    document.getElementById('btnStartSelectedScan').onclick = async function(e) {
        if (e) e.preventDefault();
        const selected = Array.from(document.querySelectorAll('.check-item:checked'));
        if (selected.length === 0) return Swal.fire('Sin selección', 'Por favor, selecciona al menos un equipo de la lista.', 'info');
        if (scanData.communities.length === 0) return Swal.fire('Error', 'No hay comunidades configuradas en el diccionario.', 'error');

        const scanBtn = this;
        scanBtn.disabled = true;
        const oldHtml = scanBtn.innerHTML;
        scanBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Escaneando...';
        
        const progressDiv = document.getElementById('scanProgress');
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const progressStat = document.getElementById('progressStat');
        progressDiv.style.display = 'block';
        tempResults = [];

        let completed = 0; let successCount = 0; const total = selected.length;
        updateProgress();

        for (const cb of selected) {
            const { ip, table, id } = cb.dataset;
            console.log(`Iniciando escaneo para ${ip}...`);
            const rowElem = document.getElementById(`row-${table}-${id}`);
            const statusCell = rowElem.querySelector('.status-cell');
            const commCell = rowElem.querySelector('.community-cell');

            statusCell.innerHTML = '<span class="status-badge badge-pending"><i class="fas fa-spinner fa-spin mr-1"></i> IP...</span>';
            
            const fdPre = new FormData();
            fdPre.append('action', 'check_snmp_port');
            fdPre.append('ip', ip);
            fdPre.append('csrf_token', csrfToken);
            
            try {
                const rPre = await fetch('api_action.php', { method: 'POST', body: fdPre });
                const resPre = await rPre.json();
                if (resPre.success && !resPre.online) {
                    statusCell.innerHTML = '<span class="status-badge badge-offline">Offline</span>';
                    commCell.innerHTML = '<small class="text-muted italic">Timeout</small>';
                    tempResults.push({ ip, table, id, status: 'OFFLINE', community: null });
                } else {
                    statusCell.innerHTML = '<span class="status-badge badge-pending text-primary"><i class="fas fa-key fa-spin mr-1"></i> SNMP...</span>';
                    let found = false;
                    for (const c of scanData.communities) {
                        const fd = new FormData();
                        fd.append('action', 'test_single_snmp');
                        fd.append('ip', ip);
                        fd.append('community', c.community);
                        fd.append('csrf_token', csrfToken);

                        const r = await fetch('api_action.php', { method: 'POST', body: fd });
                        const res = await r.json();

                        if (res.status === 'OK') {
                            statusCell.innerHTML = '<span class="status-badge badge-success-snmp">ÉXITO</span>';
                            commCell.innerHTML = `<code class="bg-success text-white p-1 rounded px-2" style="font-size:0.7rem;">${c.community}</code>`;
                            
                            const upCount = res.interfaces ? res.interfaces.length : 0;
                            const upList = res.interfaces ? res.interfaces.join(', ') : '';
                            const intCell = rowElem.children[7];
                            if (intCell) {
                                intCell.innerHTML = upCount > 0 ? `<span class="badge badge-pill badge-info pointer" title="${upList}">${upCount}</span>` : '<span class="text-muted">-</span>';
                            }

                            tempResults.push({ 
                                ip, table, id, community: c.community,
                                interfaces: res.interfaces || [],
                                status: 'SUCCESS'
                            });
                            successCount++;
                            found = true;
                            rowElem.classList.add('bg-success-light');
                            break;
                        }
                    }

                    if (!found) {
                        statusCell.innerHTML = '<span class="status-badge badge-fail-snmp">FALLO</span>';
                        commCell.innerHTML = '<small class="text-danger">Rechazo</small>';
                        tempResults.push({ ip, table, id, status: 'FAILED', community: null });
                    }
                }
            } catch (e) {
                console.error(`Error en IP ${ip}:`, e);
            }

            completed++;
            updateProgress();

            // Autocommit individual result con await para garantizar la persistencia
            const lastResult = tempResults[tempResults.length - 1];
            if (lastResult) {
                console.log(`Persistiendo datos para ${lastResult.ip}...`);
                const fdCommit = new FormData();
                fdCommit.append('action', 'commit_snmp_results');
                fdCommit.append('results', JSON.stringify([lastResult]));
                fdCommit.append('csrf_token', csrfToken);
                try {
                    const rCommit = await fetch('api_action.php', { method: 'POST', body: fdCommit });
                    const resCommit = await rCommit.json();
                    if (!resCommit.success) {
                        toastr.warning(`No se pudo persistir la IP ${lastResult.ip}: ${resCommit.error}`);
                    }
                } catch(err) {
                    console.error("Error persistiendo IP: " + lastResult.ip, err);
                }
            }
        }

        scanBtn.disabled = false;
        scanBtn.innerHTML = oldHtml;
        if (tempResults.length > 0) {
            document.getElementById('btnCommitResults').disabled = false;
            Swal.fire({
                title: 'Escaneo Completado',
                text: `Se procesaron ${tempResults.length} equipos. Los resultados han sido persistidos automáticamente.`,
                icon: 'success',
                timer: 3000
            });
            setTimeout(loadScanData, 1000);
        } else {
            Swal.fire('Escaneo Finalizado', 'No se procesaron equipos.', 'info');
        }

        function updateProgress() {
            const pct = Math.round((completed / total) * 100);
            progressBar.style.width = pct + '%';
            progressText.innerText = `Escaneando: ${completed} de ${total} equipos`;
            progressStat.innerText = `${successCount} Éxitos`;
        }
    };

    document.getElementById('btnCommitResults').onclick = function() {
        if (tempResults.length === 0) return;
        Swal.fire({
            title: '¿Guardar resultados?',
            text: `Se almacenarán ${tempResults.length} validaciones en el historial.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, guardar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                const fd = new FormData();
                fd.append('action', 'commit_snmp_results');
                fd.append('results', JSON.stringify(tempResults));
                fd.append('csrf_token', csrfToken);

                fetch('api_action.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        Swal.fire('Guardado', 'Los resultados han sido persistidos.', 'success');
                        tempResults = [];
                        this.disabled = true;
                        document.getElementById('scanProgress').style.display = 'none';
                        loadScanData();
                    } else {
                        Swal.fire('Error', res.error, 'error');
                    }
                });
            }
        });
    };
});
</script>
