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
</style>

<div class="container-fluid py-4">
    <div class="row align-items-center mb-4">
        <div class="col">
            <h1 class="h3 mb-0 font-weight-bold text-primary"><i class="fas fa-microchip mr-2"></i> Motor de Escaneo SNMP</h1>
            <p class="text-muted small mb-0">Gestión de comunidades y validación de red para activos del inventario.</p>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-primary shadow-sm px-4" data-toggle="modal" data-target="#modalCommunity">
                <i class="fas fa-key mr-2"></i> Comunidades
            </button>
        </div>
    </div>

    <div class="card premium-card">
        <div class="card-header bg-white py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-transparent border-right-0"><i class="fas fa-search text-muted"></i></span>
                        </div>
                        <input type="text" id="tableSearch" class="form-control border-left-0" placeholder="Filtrar por IP, Nombre o Tipo...">
                    </div>
                </div>
                <div class="col-md-6 text-right">
                    <div class="btn-group btn-group-toggle mr-3" data-toggle="buttons">
                        <label class="btn btn-outline-secondary btn-sm active" id="btnFilterNew">
                            <input type="radio" name="options" autocomplete="off" checked> Pendientes
                        </label>
                        <label class="btn btn-outline-secondary btn-sm" id="btnFilterAll">
                            <input type="radio" name="options" autocomplete="off"> Todos
                        </label>
                    </div>
                    <button type="button" id="btnStartSelectedScan" class="btn btn-success btn-sm px-3 shadow-sm">
                        <i class="fas fa-play mr-1"></i> Iniciar Escaneo
                    </button>
                    <button type="button" id="btnCommitResults" class="btn btn-dark btn-sm px-3 shadow-sm ml-1" disabled>
                        <i class="fas fa-save mr-1"></i> Persistir Datos
                    </button>
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
                            <th style="width: 60px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dinámico JS -->
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-light py-2 text-right">
            <span class="badge badge-light border text-muted px-3 py-2" id="infoSelected">0 equipos seleccionados</span>
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

    loadCommunities();
    loadScanData();

    document.getElementById('tableSearch').onkeyup = function() {
        searchText = this.value.toLowerCase();
        renderTable();
    };

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
        
        let filtered = scanData.ips.filter(item => {
            const matchesFilter = filterMode === 'new' ? !item.community_ok : true;
            const matchesSearch = item.ip.toLowerCase().includes(searchText) || 
                                 (item.name || '').toLowerCase().includes(searchText) ||
                                 item.display_name.toLowerCase().includes(searchText);
            return matchesFilter && matchesSearch;
        });

        filtered.sort((a, b) => {
            let valA = (a[sortCol] || '').toString().toLowerCase();
            let valB = (b[sortCol] || '').toString().toLowerCase();
            if (valA < valB) return sortAsc ? -1 : 1;
            if (valA > valB) return sortAsc ? 1 : -1;
            return 0;
        });

        if (filtered.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="fas fa-folder-open fa-2x mb-2 d-block opacity-25"></i>No hay registros para mostrar</td></tr>';
            return;
        }

        filtered.forEach(item => {
            const isPersisted = !!item.community_ok;
            const dateStr = item.last_success ? new Date(item.last_success).toLocaleDateString() : '--';
            
            tbody.innerHTML += `
                <tr id="row-${item.table}-${item.id}" class="${isPersisted ? 'text-muted' : ''}">
                    <td class="text-center align-middle">
                        <div class="custom-control custom-checkbox ml-1">
                            <input class="custom-control-input check-item" type="checkbox" 
                                id="chk-${item.table}-${item.id}" 
                                data-ip="${item.ip}" data-table="${item.table}" data-id="${item.id}"
                                ${isPersisted ? 'disabled' : ''}>
                            <label for="chk-${item.table}-${item.id}" class="custom-control-label"></label>
                        </div>
                    </td>
                    <td class="align-middle"><span class="badge badge-light border font-weight-normal">${item.display_name.replace('Inventario ', '')}</span></td>
                    <td class="align-middle font-weight-bold">${item.ip}</td>
                    <td class="align-middle small">${item.name || '-'}</td>
                    <td class="align-middle text-muted small">${dateStr}</td>
                    <td class="align-middle status-cell">
                        ${isPersisted ? '<span class="status-badge badge-success-snmp"><i class="fas fa-check mr-1"></i> Validado</span>' : '<span class="status-badge badge-pending">Pendiente</span>'}
                    </td>
                    <td class="align-middle community-cell small">
                        ${isPersisted ? `<code class="bg-light p-1 rounded">${item.community_ok}</code>` : '-'}
                    </td>
                    <td class="align-middle text-center">
                        ${isPersisted ? `<button class="btn btn-xs btn-link text-danger btnForget" data-ip="${item.ip}" data-table="${item.table}" data-id="${item.id}" title="Reiniciar Validación"><i class="fas fa-sync-alt"></i></button>` : ''}
                    </td>
                </tr>
            `;
        });
        updateSortIcons();
        attachTableEvents();
    }

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

    document.getElementById('btnStartSelectedScan').onclick = async function() {
        const selected = Array.from(document.querySelectorAll('.check-item:checked'));
        if (selected.length === 0) return Swal.fire('Sin selección', 'Por favor, selecciona al menos un equipo de la lista.', 'info');
        if (scanData.communities.length === 0) return Swal.fire('Error', 'No hay comunidades configuradas en el diccionario.', 'error');

        this.disabled = true;
        const oldHtml = this.innerHTML;
        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Escaneando...';
        
        const progressDiv = document.getElementById('scanProgress');
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const progressStat = document.getElementById('progressStat');
        progressDiv.style.display = 'block';
        tempResults = [];

        let completed = 0; let successCount = 0; const total = selected.length;

        for (const cb of selected) {
            const { ip, table, id } = cb.dataset;
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
                    completed++;
                    updateProgress();
                    continue;
                }
            } catch (e) {}

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
                    tempResults.push({ ip, table, id, community: c.community });
                    successCount++;
                    found = true;
                    rowElem.classList.add('bg-success-light');
                    break;
                }
            }

            if (!found) {
                statusCell.innerHTML = '<span class="status-badge badge-fail-snmp">FALLO</span>';
                commCell.innerHTML = '<small class="text-danger">Rehuso</small>';
            }

            completed++;
            updateProgress();
        }

        function updateProgress() {
            const pct = Math.round((completed / total) * 100);
            progressBar.style.width = pct + '%';
            progressText.innerText = `Escaneando: ${completed} de ${total} equipos`;
            progressStat.innerText = `${successCount} Éxitos`;
        }

        this.disabled = false;
        this.innerHTML = oldHtml;
        document.getElementById('btnCommitResults').disabled = (tempResults.length === 0);
        
        Swal.fire({
            title: 'Escaneo Finalizado',
            text: `Se validaron ${successCount} equipos exitosamente. Haz clic en "Persistir Datos" para guardar los cambios.`,
            icon: 'success'
        });
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
