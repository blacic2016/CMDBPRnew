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

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-microchip mr-2"></i> Escaneo y Persistencia SNMP</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#modalCommunity">
                        <i class="fas fa-key mr-1"></i> Gestionar Comunidades
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            
            <div class="row">
                <div class="col-12">
                        <div class="card-header border-bottom-0">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <h3 class="card-title text-bold text-success">
                                        <i class="fas fa-list-ul mr-1"></i> Cola de Revisión
                                    </h3>
                                </div>
                                <div class="col-md-8 text-right">
                                    <button type="button" id="btnStartSelectedScan" class="btn btn-success btn-sm shadow-sm">
                                        <i class="fas fa-play mr-1"></i> Ejecutar Revisión
                                    </button>
                                    <button type="button" id="btnCommitResults" class="btn btn-primary btn-sm shadow-sm ml-1" disabled>
                                        <i class="fas fa-save mr-1"></i> Almacenar en BDD
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-header bg-light py-2">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                                        </div>
                                        <input type="text" id="tableSearch" class="form-control" placeholder="Buscar por IP, nombre o tipo...">
                                    </div>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="btn-group btn-group-sm btn-group-toggle" data-toggle="buttons">
                                        <label class="btn btn-outline-secondary active" id="btnFilterNew">
                                            <input type="radio" name="options" autocomplete="off" checked> Pendientes
                                        </label>
                                        <label class="btn btn-outline-secondary" id="btnFilterAll">
                                            <input type="radio" name="options" autocomplete="off"> Todos los Equipos
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4 text-right">
                                    <span class="badge badge-outline-secondary py-1 px-2" id="infoSelected">0 seleccionados</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div id="scanProgress" style="display:none;" class="mb-3">
                                <div class="progress progress-sm active">
                                    <div id="progressBar" class="progress-bar progress-bar-success progress-bar-striped" role="progressbar" style="width: 0%"></div>
                                </div>
                                <div class="d-flex justify-content-between mt-1">
                                    <small id="progressText" class="text-muted"></small>
                                    <small id="progressStat" class="text-bold text-success"></small>
                                </div>
                            </div>
                            
                            <div class="table-responsive" style="max-height: 650px; overflow-y: auto;">
                                <table class="table table-sm table-bordered table-hover mb-0" id="tableScan">
                                    <thead class="bg-light sticky-top">
                                        <tr>
                                            <th style="width: 40px" class="text-center">
                                                <div class="custom-control custom-checkbox">
                                                    <input class="custom-control-input" type="checkbox" id="checkAll">
                                                    <label for="checkAll" class="custom-control-label"></label>
                                                </div>
                                            </th>
                                            <th class="sortable" data-col="display_name" style="cursor:pointer">Origen <i class="fas fa-sort"></i></th>
                                            <th class="sortable" data-col="ip" style="cursor:pointer">Dirección IP <i class="fas fa-sort"></i></th>
                                            <th class="sortable" data-col="name" style="cursor:pointer">Hostname/Nombre <i class="fas fa-sort"></i></th>
                                            <th class="sortable" data-col="last_success" style="cursor:pointer">Última Rev. <i class="fas fa-sort"></i></th>
                                            <th>Estado Actual</th>
                                            <th>Comunidad OK</th>
                                            <th style="width: 50px"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Se llena via JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer py-2">
                            <small class="text-muted">
                                <i class="fas fa-info-circle mr-1"></i> 
                                Solo los registros marcados con el checkbox serán procesados. Los registros con "Última Rev." ya están persistidos en la base de datos.
                            </small>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- Modal: Gestión de Comunidades (Compacto) -->
<div class="modal fade" id="modalCommunity" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Diccionario de Comunidades</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="formCommunity" class="mb-3 border-bottom pb-3">
                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                    <div class="row">
                        <div class="col-5">
                            <input type="text" name="community" class="form-control form-control-sm" placeholder="Comunidad" required>
                        </div>
                        <div class="col-5">
                            <input type="text" name="description" class="form-control form-control-sm" placeholder="Descripción">
                        </div>
                        <div class="col-2">
                            <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                </form>
                <div id="listCommunities" style="max-height: 300px; overflow-y: auto;">
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
    let tempResults = []; // Almacena resultados del escaneo actual antes de commit
    let filterMode = 'new'; // 'new' o 'all'
    let sortCol = 'ip';
    let sortAsc = true;
    let searchText = '';

    // 1. Inicialización
    loadCommunities();
    loadScanData();

    // Filtro de búsqueda
    document.getElementById('tableSearch').onkeyup = function() {
        searchText = this.value.toLowerCase();
        renderTable();
    };

    // --- ACCIONES DE UI ---
    document.getElementById('btnFilterNew').onclick = () => { filterMode = 'new'; updateFilterUI(); renderTable(); };
    document.getElementById('btnFilterAll').onclick = () => { filterMode = 'all'; updateFilterUI(); renderTable(); };

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
        badge.innerText = `${count} seleccionados`;
        badge.className = count > 0 ? 'badge badge-primary py-1 px-2 shadow-sm' : 'badge badge-outline-secondary py-1 px-2';
    }

    // --- CARGA DE DATOS ---
    function loadCommunities() {
        fetch('api_action.php?action=list_snmp_communities')
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                scanData.communities = res.data;
                const container = document.getElementById('listCommunities');
                container.innerHTML = '<ul class="list-group list-group-flush">';
                res.data.forEach(c => {
                    container.innerHTML += `
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <span><code>${c.community}</code> <small class="text-muted ml-2">${c.description || ''}</small></span>
                            <button class="btn btn-xs btn-outline-danger btnDeleteComm" data-id="${c.id}"><i class="fas fa-times"></i></button>
                        </li>
                    `;
                });
                container.innerHTML += '</ul>';
                attachDeleteCommEvents();
            }
        });
    }

    function loadScanData() {
        fetch('api_action.php?action=get_snmp_scan_data')
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                scanData.ips = res.ips;
                renderTable();
            }
        });
    }

    function renderTable() {
        const tbody = document.querySelector('#tableScan tbody');
        tbody.innerHTML = '';
        
        // 1. Filtrar
        let filtered = scanData.ips.filter(item => {
            const matchesFilter = filterMode === 'new' ? !item.community_ok : true;
            const matchesSearch = item.ip.toLowerCase().includes(searchText) || 
                                 (item.name || '').toLowerCase().includes(searchText) ||
                                 item.display_name.toLowerCase().includes(searchText);
            return matchesFilter && matchesSearch;
        });

        // 2. Ordenar
        filtered.sort((a, b) => {
            let valA = a[sortCol] || '';
            let valB = b[sortCol] || '';
            
            if (valA < valB) return sortAsc ? -1 : 1;
            if (valA > valB) return sortAsc ? 1 : -1;
            return 0;
        });

        if (filtered.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No hay registros que coincidan</td></tr>';
            return;
        }

        filtered.forEach(item => {
            const isPersisted = !!item.community_ok;
            const dateStr = item.last_success ? new Date(item.last_success).toLocaleString() : '--';
            
            tbody.innerHTML += `
                <tr id="row-${item.table}-${item.id}" class="${isPersisted ? 'table-light' : ''}">
                    <td class="text-center">
                        <div class="custom-control custom-checkbox">
                            <input class="custom-control-input check-item" type="checkbox" 
                                id="chk-${item.table}-${item.id}" 
                                data-ip="${item.ip}" data-table="${item.table}" data-id="${item.id}"
                                ${isPersisted ? 'disabled' : ''}>
                            <label for="chk-${item.table}-${item.id}" class="custom-control-label"></label>
                        </div>
                    </td>
                    <td><small class="badge badge-secondary">${item.display_name}</small></td>
                    <td class="text-bold">${item.ip}</td>
                    <td><small>${item.name || '-'}</small></td>
                    <td class="text-muted small">${dateStr}</td>
                    <td class="status-cell">
                        ${isPersisted ? '<span class="text-success"><i class="fas fa-check-double mr-1"></i> Validado</span>' : '<span class="text-muted">Pendiente</span>'}
                    </td>
                    <td class="community-cell">
                        ${isPersisted ? `<code>${item.community_ok}</code>` : '-'}
                    </td>
                    <td class="text-center">
                        ${isPersisted ? `<button class="btn btn-xs btn-link text-danger btnForget" data-ip="${item.ip}" data-table="${item.table}" data-id="${item.id}" title="Olvidar validación"><i class="fas fa-undo"></i></button>` : ''}
                    </td>
                </tr>
            `;
        });
        updateSortIcons();
        attachTableEvents();
    }

    function updateSortIcons() {
        document.querySelectorAll('th.sortable i').forEach(i => i.className = 'fas fa-sort');
        const activeTh = document.querySelector(`th.sortable[data-col="${sortCol}"] i`);
        if (activeTh) activeTh.className = sortAsc ? 'fas fa-sort-up' : 'fas fa-sort-down';
    }

    document.querySelectorAll('th.sortable').forEach(th => {
        th.onclick = function() {
            const col = this.dataset.col;
            if (sortCol === col) {
                sortAsc = !sortAsc;
            } else {
                sortCol = col;
                sortAsc = true;
            }
            renderTable();
        };
    });

    // --- COMUNDIADES CRUD ---
    document.getElementById('formCommunity').onsubmit = function(e) {
        e.preventDefault();
        fetch('api_action.php?action=save_snmp_community', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(res => { if(res.success) { this.reset(); loadCommunities(); } });
    };

    function attachDeleteCommEvents() {
        document.querySelectorAll('.btnDeleteComm').forEach(btn => {
            btn.onclick = function() {
                const fd = new FormData();
                fd.append('action', 'delete_snmp_community');
                fd.append('id', this.dataset.id);
                fd.append('csrf_token', csrfToken);
                fetch('api_action.php', { method: 'POST', body: fd }).then(() => loadCommunities());
            };
        });
    }

    function attachTableEvents() {
        updateSelectedCount(); // Reset count on render
        document.querySelectorAll('.check-item').forEach(cb => {
            cb.onchange = updateSelectedCount;
        });

        document.querySelectorAll('.btnForget').forEach(btn => {
            btn.onclick = function() {
                if(!confirm('¿Seguro de eliminar este registro histórico? El equipo volverá a estar pendiente de revisión.')) return;
                const fd = new FormData();
                fd.append('action', 'delete_snmp_scan_result');
                fd.append('ip', this.dataset.ip);
                fd.append('table', this.dataset.table);
                fd.append('id', this.dataset.id);
                fd.append('csrf_token', csrfToken);
                fetch('api_action.php', { method: 'POST', body: fd }).then(() => loadScanData());
            };
        });
    }

    // --- MOTOR DE ESCANEO ---
    document.getElementById('btnStartSelectedScan').onclick = async function() {
        const selected = Array.from(document.querySelectorAll('.check-item:checked'));
        if (selected.length === 0) return alert('Por favor, selecciona al menos un equipo.');
        if (scanData.communities.length === 0) return alert('No hay comunidades configuradas.');

        this.disabled = true;
        const oldHtml = this.innerHTML;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ejecutando...';
        
        const progressDiv = document.getElementById('scanProgress');
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const progressStat = document.getElementById('progressStat');
        progressDiv.style.display = 'block';
        tempResults = [];

        let completed = 0;
        let successCount = 0;
        const total = selected.length;

        for (const cb of selected) {
            const { ip, table, id } = cb.dataset;
            const rowElem = document.getElementById(`row-${table}-${id}`);
            const statusCell = rowElem.querySelector('.status-cell');
            const commCell = rowElem.querySelector('.community-cell');

            statusCell.innerHTML = '<i class="fas fa-satellite-dish fa-spin text-primary"></i>';
            
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
                    statusCell.innerHTML = '<span class="badge badge-success">EXITO</span>';
                    commCell.innerHTML = `<code class="text-success">${c.community}</code>`;
                    tempResults.push({ ip, table, id, community: c.community });
                    successCount++;
                    found = true;
                    // Marcar fila como completada para confirmar
                    rowElem.classList.add('table-success');
                    break;
                }
            }

            if (!found) {
                statusCell.innerHTML = '<span class="badge badge-danger">FAIL</span>';
                commCell.innerHTML = '<small class="text-danger">Inaccesible</small>';
                rowElem.classList.add('table-danger');
            }

            completed++;
            const pct = Math.round((completed / total) * 100);
            progressBar.style.width = pct + '%';
            progressText.innerText = `Procesando: ${completed} de ${total}`;
            progressStat.innerText = `OK: ${successCount}`;
        }

        this.disabled = false;
        this.innerHTML = oldHtml;
        document.getElementById('btnCommitResults').disabled = (tempResults.length === 0);
        alert('Escaneo finalizado. Revisa los resultados y haz clic en "Almacenar Seleccionados" para persistir los éxitos.');
    };

    // --- PERSISTENCIA (COMMIT) ---
    document.getElementById('btnCommitResults').onclick = function() {
        if (tempResults.length === 0) return;
        if (!confirm(`¿Almacenar los ${tempResults.length} resultados exitosos en la base de datos histórica?`)) return;

        const fd = new FormData();
        fd.append('action', 'commit_snmp_results');
        fd.append('results', JSON.stringify(tempResults));
        fd.append('csrf_token', csrfToken);

        fetch('api_action.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                toastr?.success('Resultados almacenados correctamente.');
                tempResults = [];
                this.disabled = true;
                loadScanData();
            } else {
                alert('Error al guardar: ' + res.error);
            }
        });
    };
});
</script>
