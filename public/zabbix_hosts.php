<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';

require_login();

$page_title = 'Gestión de Equipos Zabbix';
include __DIR__ . '/partials/header.php';
?>

<div class="container-fluid py-4">
    <!-- Main Tool Header -->
    <div class="card card-outline card-primary shadow-sm mb-4">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><i class="fas fa-server mr-2"></i> Gestión Avanzada de Equipos</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
            </div>
        </div>
        <div class="card-body">
            <div class="row items-align-end">
                <div class="col-md-3">
                    <label class="small font-weight-bold">1. Palabra Clave (Segmentación)</label>
                    <select id="keyword-select" class="form-control select2 shadow-sm">
                        <option value="">-- Todas las Categorías --</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="small font-weight-bold">2. Grupos de Host (Zabbix)</label>
                    <select id="groups-select" class="form-control select2 shadow-sm" multiple="multiple" data-placeholder="Filtrar por grupos...">
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="small font-weight-bold">3. Buscador</label>
                    <div class="input-group shadow-sm">
                        <input type="text" id="host-search" class="form-control" placeholder="Nombre, IP, Tag...">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 text-right">
                    <label class="d-block">&nbsp;</label>
                    <button id="btn-refresh" class="btn btn-primary shadow-sm"><i class="fas fa-sync-alt"></i></button>
                    <button id="btn-delete-bulk" class="btn btn-danger shadow-sm d-none"><i class="fas fa-trash-alt"></i></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hosts Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <div id="selection-summary" class="text-muted small font-weight-bold">
                Mostrando: <span id="count-visible">0</span> equipos
            </div>
            <div class="actions">
                <button id="btn-update-bulk" class="btn btn-success btn-sm shadow-sm d-none">
                    <i class="fas fa-edit mr-1"></i> Actualizar Bulk (<span class="selected-count">0</span>)
                </button>
                <button id="btn-update-single" class="btn btn-info btn-sm shadow-sm d-none">
                    <i class="fas fa-user-edit mr-1"></i> Detalle Equipo
                </button>
            </div>
        </div>
        <div class="card-body p-0 table-responsive" style="max-height: 70vh;">
            <table class="table table-hover table-striped text-nowrap mb-0" id="hosts-table">
                <thead class="bg-dark text-white sticky-top">
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" id="select-all"></th>
                        <th>Status</th>
                        <th>Hostname / Visible Name</th>
                        <th>IP / Interfaces</th>
                        <th>Hostgroups</th>
                        <th>Templates</th>
                        <th>SNMP / Macros</th>
                        <th>Inventory</th>
                    </tr>
                </thead>
                <tbody id="hosts-body">
                    <tr><td colspan="8" class="text-center py-5"><i class="fas fa-spinner fa-spin mr-2"></i> Cargando equipos...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-light py-2 d-flex justify-content-between align-items-center">
            <span class="badge badge-light border text-muted px-3 py-2" id="info-pagination">Mostrando ...</span>
            <nav aria-label="Navegación de tabla">
                <ul class="pagination pagination-sm mb-0" id="table-pagination">
                    <!-- Dinámico JS -->
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- MODAL: Bulk Update -->
<div class="modal fade" id="modal-bulk" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-layer-group mr-2"></i> Actualización Masiva (<span class="selected-count">0</span>)</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted mb-4 small">Selecciona los campos que deseas sobrescribir en todos los equipos seleccionados.</p>
                
                <div class="form-group">
                    <label class="font-weight-bold text-dark">Vincular Templates</label>
                    <select id="bulk-templates" class="form-control select2 w-100" multiple="multiple"></select>
                </div>
                <div class="form-group mt-3">
                    <label class="font-weight-bold text-dark">Asignar Hostgroups</label>
                    <select id="bulk-groups" class="form-control select2 w-100" multiple="multiple"></select>
                </div>
                <hr>
                <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                    <input type="checkbox" class="custom-control-input" id="bulk-inventory-mode" checked>
                    <label class="custom-control-label font-weight-bold" for="bulk-inventory-mode">Forzar Inventario: AUTOMÁTICO</label>
                </div>
                <p class="small text-info mt-1"><i class="fas fa-info-circle mr-1"></i> Recomendado para asegurar que Zabbix mantenga actualizada la data de hardware.</p>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" id="execute-bulk" class="btn btn-success px-4 font-weight-bold">EJECUTAR COMMIT API <i class="fas fa-cloud-upload-alt ml-2"></i></button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Single Host Update -->
<div class="modal fade" id="modal-single" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-edit mr-2"></i> Detalle de Equipo: <span id="single-host-name">...</span></h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-0">
                <div class="nav-tabs-custom mb-0">
                    <ul class="nav nav-tabs p-2 bg-light">
                        <li class="nav-item"><a class="nav-link active" href="#tab-general" data-toggle="tab">General</a></li>
                        <li class="nav-item"><a class="nav-link" href="#tab-inventory" data-toggle="tab">Inventario</a></li>
                        <li class="nav-item"><a class="nav-link" href="#tab-macros" data-toggle="tab">Macros / Tags</a></li>
                    </ul>
                    <div class="tab-content p-4">
                        <div class="tab-pane active" id="tab-general">
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label>Host Name</label>
                                    <input type="text" id="single-host" class="form-control" disabled>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>Visible Name</label>
                                    <input type="text" id="single-name" class="form-control">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Hostgroups</label>
                                <select id="single-groups" class="form-control select2 w-100" multiple></select>
                            </div>
                            <div class="form-group">
                                <label>Templates</label>
                                <select id="single-templates" class="form-control select2 w-100" multiple></select>
                            </div>
                        </div>
                        <div class="tab-pane" id="tab-inventory">
                            <div class="alert alert-info py-2 small">Solo lectura. Los campos se llenan automáticamente si el modo está en 'Automatic'.</div>
                            <div id="single-inventory-container" class="row small">
                                <!-- JS fill -->
                            </div>
                        </div>
                        <div class="tab-pane" id="tab-macros">
                             <h6>Macros Heredadas / Propias</h6>
                             <div id="single-macros-container" class="small"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                <button type="button" id="execute-single" class="btn btn-info px-4 font-weight-bold">ACTUALIZAR EQUIPO <i class="fas fa-check ml-2"></i></button>
            </div>
        </div>
    </div>
</div>

<style>
    .status-badge { width: 12px; height: 12px; border-radius: 50%; display: inline-block; border: 1px solid rgba(0,0,0,0.1); }
    .status-ok { background: #28a745; box-shadow: 0 0 5px rgba(40,167,69,0.5); }
    .status-err { background: #dc3545; }
    .status-off { background: #6c757d; }
    .badge-outline { background: transparent; border: 1px solid #ddd; color: #666; font-weight: normal; }
    .table-hover tbody tr:hover { background-color: rgba(0,123,255,0.05) !important; cursor: pointer; }
    .sticky-top { top: 0; z-index: 1020; }
    .select2-container--default .select2-selection--multiple { border: 1px solid #ced4da; }
</style>

<script>
$(function() {
    $('.select2').select2();

    let allHosts = [];
    let selectedHostIds = [];
    let currentPage = 1;
    const itemsPerPage = 15;

    // 1. Initial Load
    loadKeywords();
    loadTemplates();
    loadAllGroups();
    refreshHosts();

    function loadKeywords() {
        $.post('api_action.php', { action: 'list_keywords' }, function(resp) {
            if (resp.success) {
                resp.data.forEach(kw => {
                    $('#keyword-select').append(new Option(kw.keyword, kw.keyword));
                });
            }
        });
    }

    function loadTemplates() {
        $.get('api_zabbix.php?action=get_templates', function(resp) {
            if (resp.success) {
                $('#bulk-templates, #single-templates').empty();
                resp.data.forEach(t => {
                    $('#bulk-templates, #single-templates').append(new Option(t.name, t.templateid));
                });
            }
        });
    }

    function loadAllGroups() {
        $.get('api_zabbix.php?action=get_groups', function(resp) {
            if (resp.success) {
                $('#bulk-groups, #single-groups').empty();
                resp.data.forEach(g => {
                    $('#bulk-groups, #single-groups').append(new Option(g.name, g.groupid));
                });
            }
        });
    }

    // 2. Keyword Filter (Dynamic Groups)
    $('#keyword-select').on('change', function() {
        const kw = $(this).val();
        $.get('api_zabbix.php?action=get_groups&keyword=' + kw, function(resp) {
            if (resp.success) {
                $('#groups-select').empty();
                resp.data.forEach(g => {
                    $('#groups-select').append(new Option(g.name, g.groupid));
                });
                refreshHosts();
            }
        });
    });

    $('#groups-select, #host-search').on('change keyup', function() {
        currentPage = 1;
        refreshHosts();
    });

    $('#btn-refresh').click(function() { refreshHosts(); });

    function refreshHosts() {
        const gids = $('#groups-select').val();
        const search = $('#host-search').val();

        $('#hosts-body').html('<tr><td colspan="8" class="text-center py-5"><i class="fas fa-spinner fa-spin mr-2"></i> Consultando API de Zabbix...</td></tr>');
        
        $.get('api_zabbix.php?action=get_hosts', { groupids: gids ? gids.join(',') : '', search: search }, function(resp) {
            if (resp.success) {
                allHosts = resp.data;
                currentPage = 1; // Reset to page 1 on search/refresh
                renderHosts();
            } else {
                Swal.fire('Error API', resp.error, 'error');
            }
        });
    }

    function renderHosts() {
        const hosts = allHosts;
        const totalItems = hosts.length;
        const totalPages = Math.ceil(totalItems / itemsPerPage);
        
        if (currentPage > totalPages) currentPage = Math.max(1, totalPages);
        
        const start = (currentPage - 1) * itemsPerPage;
        const end = start + itemsPerPage;
        const pagedData = hosts.slice(start, end);

        renderPagination(totalPages, totalItems, start, end);

        let html = '';
        pagedData.forEach(h => {
            const isSelected = selectedHostIds.includes(h.hostid);
            const statusZbx = h.available == 1 ? 'status-ok' : (h.available == 2 ? 'status-err' : 'status-off');
            const statusSnmp = h.snmp_available == 1 ? 'status-ok' : (h.snmp_available == 2 ? 'status-err' : 'status-off');
            
            const groups = (h.groups || []).map(g => `<span class="badge badge-outline mr-1">${g.name}</span>`).join('');
            const templates = (h.parentTemplates || []).map(t => `<span class="badge badge-info mr-1">${t.name}</span>`).join('');
            const inventoryMode = h.inventory_mode == 1 ? '<span class="text-success"><i class="fas fa-check-circle"></i> Auto</span>' : '<span class="text-muted">Manual</span>';
            const interfaces = h.interfaces || [];
            const tagsCount = (h.tags || []).length;
            const macrosCount = (h.macros || []).length;
            
            html += `
                <tr data-hostid="${h.hostid}" class="${isSelected ? 'table-primary' : ''}">
                    <td><input type="checkbox" class="host-selector" value="${h.hostid}" ${isSelected ? 'checked' : ''}></td>
                    <td>
                        <span class="status-badge ${statusZbx}" title="Zabbix Agent"></span>
                        <span class="status-badge ${statusSnmp} ml-1" title="SNMP"></span>
                    </td>
                    <td>
                        <div class="font-weight-bold">${h.name}</div>
                        <div class="small text-muted">${h.host}</div>
                    </td>
                    <td class="small">
                        ${interfaces.map(i => `<div><i class="fas fa-network-wired mr-1"></i> ${i.ip}:${i.port}</div>`).join('')}
                    </td>
                    <td style="max-width: 200px; white-space: normal;">${groups}</td>
                    <td style="max-width: 200px; white-space: normal;">${templates}</td>
                    <td>
                        <div class="small"><i class="fas fa-tag mr-1"></i> Tags: ${tagsCount}</div>
                        <div class="small text-info"><i class="fas fa-code mr-1"></i> Macros: ${macrosCount}</div>
                    </td>
                    <td>${inventoryMode}</td>
                </tr>
            `;
        });
        $('#hosts-body').html(html || '<tr><td colspan="8" class="text-center py-5">No se encontraron equipos bajo estos filtros.</td></tr>');
        $('#count-visible').text(totalItems);
        updateSelectionState();
    }

    function renderPagination(totalPages, totalItems, start, end) {
        const container = $('#table-pagination');
        container.empty();
        
        $('#info-pagination').text(`Mostrando ${totalItems > 0 ? start + 1 : 0} - ${Math.min(end, totalItems)} de ${totalItems} equipos`);

        if (totalPages <= 1) return;

        // Botón Anterior
        container.append(`
            <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage - 1}"><i class="fas fa-chevron-left"></i></a>
            </li>
        `);

        // Lógica saltos de página
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, startPage + 4);
        if (endPage === totalPages) startPage = Math.max(1, endPage - 4);

        for (let i = startPage; i <= endPage; i++) {
            container.append(`
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `);
        }

        // Botón Siguiente
        container.append(`
            <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage + 1}"><i class="fas fa-chevron-right"></i></a>
            </li>
        `);

        // Eventos
        container.find('.page-link').click(function(e) {
            e.preventDefault();
            const page = $(this).data('page');
            if (page >= 1 && page <= totalPages) {
                currentPage = page;
                renderHosts();
            }
        });
    }

    // 3. Selection Logic
    $(document).on('change', '.host-selector', function() {
        const id = $(this).val();
        if ($(this).is(':checked')) {
            if (!selectedHostIds.includes(id)) selectedHostIds.push(id);
        } else {
            selectedHostIds = selectedHostIds.filter(i => i !== id);
        }
        renderHosts();
    });

    $('#select-all').on('change', function() {
        const checked = $(this).is(':checked');
        if (checked) {
            allHosts.forEach(h => { if (!selectedHostIds.includes(h.hostid)) selectedHostIds.push(h.hostid); });
        } else {
            selectedHostIds = [];
        }
        renderHosts();
    });

    function updateSelectionState() {
        const count = selectedHostIds.length;
        $('.selected-count').text(count);
        if (count > 0) {
            $('#btn-delete-bulk').removeClass('d-none');
            $('#btn-update-bulk').removeClass('d-none');
            if (count === 1) $('#btn-update-single').removeClass('d-none');
            else $('#btn-update-single').addClass('d-none');
        } else {
            $('#btn-delete-bulk').addClass('d-none');
            $('#btn-update-bulk').addClass('d-none');
            $('#btn-update-single').addClass('d-none');
        }
    }

    // 4. Update Actions
    $('#btn-update-bulk').click(function() {
        $('#modal-bulk').modal('show');
    });

    $('#execute-bulk').click(function() {
        const data = {
            hostids: selectedHostIds,
            templates: $('#bulk-templates').val(),
            groups: $('#bulk-groups').val(),
            inventory_mode: $('#bulk-inventory-mode').is(':checked') ? 1 : 0
        };

        Swal.fire({
            title: 'Ejecutando Cambios Masivos',
            text: `Se actualizarán ${selectedHostIds.length} equipos en Zabbix.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, aplicar commit',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return $.post('api_zabbix.php?action=update_hosts_bulk', data).then(resp => {
                    if (!resp.success) throw new Error(resp.error || 'Error desconocido');
                    return resp;
                }).catch(err => { Swal.showValidationMessage(`Request failed: ${err}`); });
            }
        }).then(res => {
            if (res.isConfirmed) {
                Swal.fire('¡Listo!', `Actualizados ${res.value.updated} equipos correctamente.`, 'success');
                $('#modal-bulk').modal('hide');
                refreshHosts();
            }
        });
    });

    $('#btn-update-single').click(function() {
        const hostid = selectedHostIds[0];
        $.get('api_zabbix.php?action=get_host_details', { hostid: hostid }, function(resp) {
            if (resp.success && resp.data) {
                const h = resp.data;
                $('#single-host-name').text(h.name);
                $('#single-host').val(h.host);
                $('#single-name').val(h.name);
                $('#single-groups').val((h.groups || []).map(g => g.groupid)).trigger('change');
                $('#single-templates').val((h.parentTemplates || []).map(t => t.templateid)).trigger('change');
                
                // Inventory
                let invHtml = '';
                const inv = h.inventory || {};
                for (const k in inv) {
                    if (inv[k]) invHtml += `<div class="col-md-4 mb-2"><strong>${k}:</strong> <span class="text-secondary">${inv[k]}</span></div>`;
                }
                $('#single-inventory-container').html(invHtml || '<div class="col-12 text-muted">No hay data de inventario disponible.</div>');

                // Macros
                let macroHtml = '<table class="table table-sm table-bordered"><thead><tr><th>Macro</th><th>Value</th></tr></thead><tbody>';
                h.macros.forEach(m => {
                    macroHtml += `<tr><td><code>${m.macro}</code></td><td>${m.value}</td></tr>`;
                });
                macroHtml += '</tbody></table>';
                $('#single-macros-container').html(h.macros.length > 0 ? macroHtml : 'Sin macros definidas.');

                $('#modal-single').modal('show');
            }
        });
    });

    $('#btn-delete-bulk').click(function() {
        Swal.fire({
            title: '¿Eliminar Equipos?',
            text: `¡CUIDADO! Se borrarán definitivamente ${selectedHostIds.length} hosts de Zabbix.`,
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, ELIMINAR'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('api_zabbix.php?action=delete_hosts', { hostids: selectedHostIds }, function(resp) {
                    if (resp.success) {
                        Swal.fire('Eliminados', `${resp.deleted} equipos borrados.`, 'success');
                        selectedHostIds = [];
                        refreshHosts();
                    } else {
                        Swal.fire('Error', resp.error, 'error');
                    }
                });
            }
        });
    });
});
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
