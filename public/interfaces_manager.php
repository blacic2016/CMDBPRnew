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

<!-- Modal para Conectar Equipo (CMDB) -->
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
                <p class="text-muted small mb-4">Seleccione el equipo que se encuentra conectado físicamente a esta interfaz en la CMDB.</p>
                
                <div class="form-group">
                    <label class="small font-weight-bold">Equipo Destino (CMDB)</label>
                    <select id="modal-select-device" class="form-control select2-modal" style="width: 100%;"></select>
                </div>
                
                <div class="form-group">
                    <div class="d-flex justify-content-between align-items-center">
                        <label class="small font-weight-bold mb-0">Puerto / Toma Destino</label>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="modal_chk_manual_port" onchange="toggleModalManualPort(this.checked)">
                            <label class="custom-control-label small" for="modal_chk_manual_port">Escribir manualmente</label>
                        </div>
                    </div>
                    <div id="modal_wrapper_select_port" class="mt-2">
                        <select id="modal-select-port" class="form-control animate__animated animate__fadeIn" style="width:100%;"></select>
                    </div>
                    <div id="modal_wrapper_text_port" class="mt-2" style="display: none;">
                        <input type="text" id="modal-text-port" class="form-control animate__animated animate__fadeIn" placeholder="Ej. Gi1/0/2 o PSU-1-In">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label class="small font-weight-bold">Medio / Tipo Cable</label>
                        <select id="modal-cable-type" class="form-control">
                            <option>UTP Cat6A</option>
                            <option>UTP Cat6</option>
                            <option>Fibra OM4</option>
                            <option>Fibra OS2</option>
                            <option>DAC / Twinax</option>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label class="small font-weight-bold">Color del Cable</label>
                        <input type="color" id="modal-color-code" class="form-control" value="#0000FF">
                    </div>
                </div>

                <div class="form-group">
                    <label class="small font-weight-bold">Observación / Notas</label>
                    <textarea id="modal-notes" class="form-control" rows="2" placeholder="Notas sobre el cableado..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                <button type="button" id="btn-save-connection" class="btn btn-primary px-4 shadow-sm">CONECTAR</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    let currentHostId = null;
    let currentCIDeviceId = null;
    let selectedInterfaceName = null;

    // Inicializar Select2 para Grupos
    $('#select-group').select2({
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

    // Cargar equipos destino CMDB en el modal
    $.get('api_portmapping.php?action=get_all_devices', function(resp) {
        if (resp.success) {
            let options = '<option value="">Seleccione equipo destino...</option>';
            resp.data.forEach(d => {
                options += `<option value="${d.id}">${d.name} (${d.category_name})</option>`;
            });
            $('#modal-select-device').html(options).select2({
                theme: 'bootstrap4',
                dropdownParent: $('#connectModal'),
                placeholder: 'Seleccione equipo destino...'
            });
        }
    }, 'json');

    // Evento cambio de equipo destino en el modal
    $('#modal-select-device').on('change', function() {
        const destId = $(this).val();
        const selectPort = $('#modal-select-port');
        selectPort.html('<option value="">Cargando puertos...</option>');
        
        if (destId) {
            $.get('api_portmapping.php?action=get_device_ports', { device_id: destId }, function(resp) {
                if (resp.success) {
                    let html = '<option value="">Seleccione puerto...</option>';
                    resp.data.forEach(p => {
                        const disabled = p.is_mapped ? 'disabled' : '';
                        const text = p.is_mapped ? `${p.name} (Ocupado)` : p.name;
                        html += `<option value="${p.name}" ${disabled}>${text}</option>`;
                    });
                    selectPort.html(html);
                } else {
                    selectPort.html('<option value="">Error cargando puertos</option>');
                }
            }, 'json');
        } else {
            selectPort.html('<option value="">Seleccione equipo primero</option>');
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
                currentCIDeviceId = resp.ci_device_id;
                renderInterfaces(resp.data);
                $('#results-container').removeClass('d-none');
            } else {
                Swal.fire('Error', resp.error || 'No se pudieron cargar las interfaces', 'error');
                $('#empty-state').removeClass('d-none');
            }
        }, 'json');
    }
    window.loadInterfaces = loadInterfaces;

    function renderInterfaces(interfaces) {
        const body = $('#interfaces-body');
        body.empty();

        if (interfaces.length === 0) {
            body.append('<tr><td colspan="8" class="text-center py-4 text-muted">No se encontraron interfaces con datos SNMP para este equipo.</td></tr>');
            return;
        }

        interfaces.forEach(it => {
            const statusClass = it.status === 'Up' ? 'status-up' : 'status-down';
            
            let connectedText = '<span class="text-muted small">Sin conexión</span>';
            if (it.connected_host_name) {
                let portNamePart = it.connected_port_name ? ` <span class="badge badge-light border ml-1"><i class="fas fa-ethernet text-muted mr-1"></i> ${it.connected_port_name}</span>` : '';
                let cablePart = it.cable_type ? `<br><small class="text-muted"><span class="color-pill mr-1" style="background-color: ${it.color_code || '#ccc'}; display: inline-block; width: 10px; height: 10px; border-radius: 50%;"></span>${it.cable_type}</small>` : '';
                let notesPart = it.notes ? `<br><small class="text-muted font-italic" title="${it.notes}">Obs: ${it.notes}</small>` : '';
                connectedText = `<div>
                    <span class="badge badge-info shadow-sm py-2 px-3"><i class="fas fa-server mr-1"></i> ${it.connected_host_name}</span>
                    ${portNamePart}
                    ${cablePart}
                    ${notesPart}
                </div>`;
            }

            let actions = '';
            if (it.mapping_id) {
                actions = `
                    <button class="btn btn-xs btn-outline-info mr-1 btn-edit-conn" 
                            data-mapping="${it.mapping_id}"
                            data-iname="${it.interface_name}"
                            data-destid="${it.dest_device_id}"
                            data-destport="${it.connected_port_name}"
                            data-cable="${it.cable_type}"
                            data-color="${it.color_code}"
                            data-notes="${it.notes || ''}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-xs btn-outline-danger" onclick="deleteCMDBConnection(${it.mapping_id})">
                        <i class="fas fa-unlink"></i>
                    </button>
                `;
            } else {
                actions = `
                    <button class="btn btn-xs btn-primary shadow-sm btn-connect" 
                            data-iname="${it.interface_name}">
                        <i class="fas fa-link mr-1"></i> Conectar
                    </button>
                `;
                if (it.connected_hostid) {
                    actions += `
                        <button class="btn btn-xs btn-outline-danger ml-1 btn-disconnect-legacy" 
                                data-name="${it.interface_index}">
                            <i class="fas fa-unlink"></i>
                        </button>
                    `;
                }
            }

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
                            ${actions}
                        </div>
                    </td>
                </tr>
            `;
            body.append(row);
        });
    }

    function formatBytes(bytes) {
        if (!bytes || bytes == 0) return '0 bps';
        const k = 1000;
        const sizes = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Eventos de botones
    $(document).on('click', '.btn-connect', function() {
        selectedInterfaceName = $(this).data('iname');
        $('#connectModal .modal-title').text(`Conectar Interfaz: ${selectedInterfaceName}`);
        $('#modal-select-device').val('').trigger('change');
        $('#modal-select-port').html('<option value="">Seleccione equipo primero</option>');
        $('#modal-text-port').val('');
        $('#modal_chk_manual_port').prop('checked', false);
        toggleModalManualPort(false);
        $('#modal-cable-type').val('UTP Cat6A');
        $('#modal-color-code').val('#0000FF');
        $('#modal-notes').val('');
        $('#connectModal').modal('show');
    });

    $(document).on('click', '.btn-edit-conn', function() {
        selectedInterfaceName = $(this).data('iname');
        const mappingId = $(this).data('mapping');
        const destId = $(this).data('destid');
        const destPort = $(this).data('destport');
        const cableType = $(this).data('cable') || 'UTP Cat6A';
        const colorCode = $(this).data('color') || '#0000FF';
        const notes = $(this).data('notes') || '';

        $('#connectModal .modal-title').text(`Editar Conexión: ${selectedInterfaceName}`);
        $('#modal-cable-type').val(cableType);
        $('#modal-color-code').val(colorCode);
        $('#modal-notes').val(notes);

        $('#modal-select-device').val(destId).trigger('change');

        setTimeout(() => {
            const exists = $(`#modal-select-port option[value="${destPort}"]`).length > 0;
            if (exists) {
                $('#modal_chk_manual_port').prop('checked', false);
                toggleModalManualPort(false);
                $('#modal-select-port').val(destPort);
            } else {
                $('#modal_chk_manual_port').prop('checked', true);
                toggleModalManualPort(true);
                $('#modal-text-port').val(destPort);
            }
        }, 800);

        $('#connectModal').modal('show');
    });

    $(document).on('click', '.btn-disconnect-legacy', function() {
        const ifName = $(this).data('name');
        confirmDisconnectLegacy(ifName);
    });

    // Guardar conexión CMDB
    $('#btn-save-connection').on('click', function() {
        const destDeviceId = $('#modal-select-device').val();
        const isManual = $('#modal_chk_manual_port').is(':checked');
        const destPortName = isManual ? $('#modal-text-port').val() : $('#modal-select-port').val();
        
        if (!destDeviceId || !destPortName) {
            Swal.fire('Atención', 'Debe seleccionar el equipo y puerto de destino.', 'warning');
            return;
        }

        if (!currentCIDeviceId) {
            Swal.fire('Atención', 'Este equipo no tiene un CI vinculado en la CMDB. Regístrelo en la CMDB primero.', 'warning');
            return;
        }

        $.post('api_portmapping.php', {
            action: 'save_device_connection',
            device_id: currentCIDeviceId,
            port_name: selectedInterfaceName,
            connection_type: 'network',
            dest_device_id: destDeviceId,
            dest_port_name: destPortName,
            cable_type: $('#modal-cable-type').val(),
            color_code: $('#modal-color-code').val(),
            notes: $('#modal-notes').val()
        }, function(resp) {
            if (resp.success) {
                $('#connectModal').modal('hide');
                Swal.fire('Conectado', 'La conexión ha sido guardada en la CMDB correctamente', 'success');
                loadInterfaces();
            } else {
                Swal.fire('Error', resp.error || 'No se pudo guardar la conexión', 'error');
            }
        }, 'json');
    });

    // Desconectar Legacy (Zabbix cache local solamente)
    function confirmDisconnectLegacy(ifName) {
        Swal.fire({
            title: '¿Eliminar conexión legacy?',
            text: "Se desvinculará este equipo de la interfaz en el historial de Zabbix.",
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
                        Swal.fire('Eliminado', 'La conexión legacy ha sido removida', 'success');
                        loadInterfaces();
                    }
                }, 'json');
            }
        });
    }
});

// Funciones Globales para toggle y borrar
function toggleModalManualPort(isManual) {
    if (isManual) {
        $('#modal_wrapper_select_port').hide();
        $('#modal_wrapper_text_port').show();
        $('#modal-text-port').prop('required', true);
    } else {
        $('#modal_wrapper_select_port').show();
        $('#modal_wrapper_text_port').hide();
        $('#modal-text-port').prop('required', false);
    }
}

function deleteCMDBConnection(mappingId) {
    Swal.fire({
        title: '¿Eliminar conexión CMDB?',
        text: "Se eliminará el enlace físico en el portmapping de la CMDB, liberando ambos extremos.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, desvincular',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('api_portmapping.php', {
                action: 'delete_device_connection',
                mapping_id: mappingId
            }, function(resp) {
                if (resp.success) {
                    Swal.fire('Desvinculado', 'Conexión eliminada correctamente en la CMDB.', 'success');
                    if (typeof loadInterfaces === 'function') {
                        loadInterfaces();
                    }
                } else {
                    Swal.fire('Error', resp.error || 'No se pudo eliminar la conexión', 'error');
                }
            }, 'json');
        }
    });
}
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
