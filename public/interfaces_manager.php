<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/zabbix_api.php';

require_login();

$page_title = 'Gestión de Interfaces de Red';
require_once __DIR__ . '/partials/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@x.x.x/dist/select2-bootstrap4.min.css">

<style>
    .premium-card {
        border-radius: 15px;
        border: none;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        background: #fff;
        overflow: hidden;
    }
    .gradient-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
    }
    .table thead th {
        background-color: #f8f9fa;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 1px;
        border-top: none;
    }
    .status-badge {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .status-up { background: #e6fffa; color: #2c7a7b; }
    .status-down { background: #fff5f5; color: #c53030; }
    .traffic-val { font-family: 'Monaco', 'Consolas', monospace; font-size: 0.85rem; }
    .select2-container--bootstrap4 .select2-selection {
        border-radius: 8px;
    }
</style>

<div class="container-fluid pt-4" id="app">
    <div class="premium-card animate__animated animate__fadeIn">
        <div class="gradient-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0"><i class="fas fa-network-wired mr-2"></i> Interfaces de Red</h4>
                <p class="mb-0 small opacity-8">Obtención de datos en tiempo real desde Zabbix</p>
            </div>
            <div class="d-flex" style="gap: 15px;">
                <div style="min-width: 200px;">
                    <label class="small text-white opacity-8 mb-1">Grupo de Host</label>
                    <select id="select-group" class="form-control form-control-sm"></select>
                </div>
                <div style="min-width: 200px;">
                    <label class="small text-white opacity-8 mb-1">Equipo (Host)</label>
                    <select id="select-host" class="form-control form-control-sm" disabled>
                        <option value="">Seleccione Grupo...</option>
                    </select>
                </div>
                <div class="align-self-end">
                    <button id="btn-obtener" class="btn btn-light btn-sm font-weight-bold px-4" disabled>
                        <i class="fas fa-sync-alt mr-1"></i> OBTENER
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div id="loading-state" class="text-center py-5 d-none">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 text-muted">Consultando datos de Zabbix...</p>
            </div>

            <div id="empty-state" class="text-center py-5">
                <i class="fas fa-search fa-3x text-light mb-3"></i>
                <h5 class="text-muted">Seleccione un equipo para ver sus interfaces</h5>
            </div>

            <div id="results-container" class="table-responsive d-none">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="pl-4">Tipo</th>
                            <th>Interfaz</th>
                            <th>Alias / Descripción</th>
                            <th>Estado</th>
                            <th>Bytes Recibidos</th>
                            <th>Bytes Enviados</th>
                            <th>VLAN</th>
                            <th class="text-right pr-4">Equipos Conectados</th>
                        </tr>
                    </thead>
                    <tbody id="interfaces-body">
                        <!-- Se llena dinámicamente -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Conectar Equipo -->
<div class="modal fade" id="connectModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 15px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-weight-bold">Conectar a Equipo</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-4">Seleccione el equipo que se encuentra conectado físicamente a esta interfaz.</p>
                
                <div class="form-group">
                    <label class="small font-weight-bold">Grupo de Host</label>
                    <select id="modal-select-group" class="form-control select2-modal"></select>
                </div>
                
                <div class="form-group">
                    <label class="small font-weight-bold">Equipo</label>
                    <select id="modal-select-host" class="form-control select2-modal" disabled>
                        <option value="">Seleccione Grupo...</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                <button type="button" id="btn-save-connection" class="btn btn-primary px-4 shadow-sm" disabled>CONECTAR</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    let currentHostId = null;
    let selectedInterfaceName = null;

    // Inicializar Select2 para Grupos
    $('#select-group, #modal-select-group').select2({
        theme: 'bootstrap4',
        placeholder: 'Buscar grupo...',
        ajax: {
            url: 'api_zabbix.php?action=get_groups',
            dataType: 'json',
            delay: 250,
            processResults: function(data) {
                return {
                    results: data.data.map(g => ({ id: g.groupid, text: g.name }))
                };
            }
        }
    });

    // Evento cambio de grupo en el filtro superior
    $('#select-group').on('change', function() {
        const groupId = $(this).val();
        $('#select-host').prop('disabled', !groupId).html('<option value="">Cargando hosts...</option>');
        $('#btn-obtener').prop('disabled', true);

        if (groupId) {
            $.get('api_zabbix.php', { action: 'get_hosts', groupids: groupId }, function(resp) {
                if (resp.success) {
                    let html = '<option value="">Seleccione un host...</option>';
                    resp.data.forEach(h => {
                        html += `<option value="${h.hostid}">${h.name}</option>`;
                    });
                    $('#select-host').html(html).prop('disabled', false);
                }
            }, 'json');
        }
    });

    $('#select-host').on('change', function() {
        $('#btn-obtener').prop('disabled', !$(this).val());
    });

    // Botón Obtener
    $('#btn-obtener').on('click', function() {
        currentHostId = $('#select-host').val();
        loadInterfaces();
    });

    function loadInterfaces() {
        $('#empty-state').addClass('d-none');
        $('#results-container').addClass('d-none');
        $('#loading-state').removeClass('d-none');

        $.get('api_zabbix.php', { action: 'get_interfaces_data', hostid: currentHostId }, function(resp) {
            $('#loading-state').addClass('d-none');
            if (resp.success) {
                renderInterfaces(resp.data);
                $('#results-container').removeClass('d-none');
            } else {
                Swal.fire('Error', resp.error || 'No se pudieron cargar las interfaces', 'error');
                $('#empty-state').removeClass('d-none');
            }
        }, 'json');
    }

    function renderInterfaces(interfaces) {
        const body = $('#interfaces-body');
        body.empty();

        if (interfaces.length === 0) {
            body.append('<tr><td colspan="7" class="text-center py-4 text-muted">No se encontraron interfaces con datos SNMP para este equipo.</td></tr>');
            return;
        }

        interfaces.forEach(it => {
            const statusClass = it.status === 'Up' ? 'status-up' : 'status-down';
            const connectedText = it.connected_host_name 
                ? `<span class="badge badge-info shadow-sm py-2 px-3"><i class="fas fa-server mr-1"></i> ${it.connected_host_name}</span>` 
                : `<span class="text-muted small">Sin conexión</span>`;

            const row = `
                <tr>
                    <td class="pl-4"><span class="badge badge-light border text-muted">${it.interface_type || 'Other'}</span></td>
                    <td class="font-weight-bold">${it.interface_name}</td>
                    <td class="small text-muted">${it.alias || '-'}</td>
                    <td><span class="status-badge ${statusClass}">${it.status}</span></td>
                    <td class="traffic-val">${formatBytes(it.bits_received)}</td>
                    <td class="traffic-val">${formatBytes(it.bits_sent)}</td>
                    <td>${it.vlan || '-'}</td>
                    <td class="text-right pr-4">
                        <div class="d-flex align-items-center justify-content-end" style="gap: 10px;">
                            ${connectedText}
                            <button class="btn btn-xs btn-outline-primary btn-connect" 
                                    data-name="${it.interface_index}" 
                                    data-iname="${it.interface_name}">
                                <i class="fas fa-link"></i>
                            </button>
                            ${it.connected_hostid ? `
                                <button class="btn btn-xs btn-outline-danger btn-disconnect" 
                                        data-name="${it.interface_index}">
                                    <i class="fas fa-unlink"></i>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
            body.append(row);
        });

        // Eventos de botones
        $('.btn-connect').on('click', function() {
            selectedInterfaceName = $(this).data('name');
            const readableName = $(this).data('iname');
            $('#connectModal .modal-title').text(`Conectar: ${readableName}`);
            $('#connectModal').modal('show');
        });

        $('.btn-disconnect').on('click', function() {
            const ifName = $(this).data('name');
            confirmDisconnect(ifName);
        });
    }

    function formatBytes(bytes) {
        if (!bytes || bytes == 0) return '0 bps';
        const k = 1000; // Bits are usually 1000
        const sizes = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Lógica del Modal
    $('#modal-select-group').on('change', function() {
        const groupId = $(this).val();
        $('#modal-select-host').prop('disabled', !groupId).html('<option value="">Cargando hosts...</option>');
        $('#btn-save-connection').prop('disabled', true);

        if (groupId) {
            $.get('api_zabbix.php', { action: 'get_hosts', groupids: groupId }, function(resp) {
                if (resp.success) {
                    let html = '<option value="">Seleccione un host...</option>';
                    resp.data.forEach(h => {
                        html += `<option value="${h.hostid}">${h.name}</option>`;
                    });
                    $('#modal-select-host').html(html).prop('disabled', false);
                }
            }, 'json');
        }
    });

    $('#modal-select-host').on('change', function() {
        $('#btn-save-connection').prop('disabled', !$(this).val());
    });

    $('#btn-save-connection').on('click', function() {
        const connectedHostId = $('#modal-select-host').val();
        
        $.post('api_zabbix.php?action=save_interface_connection', {
            hostid: currentHostId,
            interface_name: selectedInterfaceName,
            connected_hostid: connectedHostId
        }, function(resp) {
            if (resp.success) {
                $('#connectModal').modal('hide');
                Swal.fire('Conectado', 'La conexión ha sido guardada correctamente', 'success');
                loadInterfaces();
            } else {
                Swal.fire('Error', resp.error || 'No se pudo guardar la conexión', 'error');
            }
        }, 'json');
    });

    function confirmDisconnect(ifName) {
        Swal.fire({
            title: '¿Eliminar conexión?',
            text: "Se desvinculará este equipo de la interfaz seleccionada.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('api_zabbix.php?action=delete_interface_connection', {
                    hostid: currentHostId,
                    interface_name: ifName
                }, function(resp) {
                    if (resp.success) {
                        Swal.fire('Eliminado', 'La conexión ha sido removida', 'success');
                        loadInterfaces();
                    }
                }, 'json');
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
