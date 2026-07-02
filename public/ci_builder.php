<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

require_login();

$page_title = 'Asistente de Creación de CI (Graph-Based)';
$edit_ci = null;
$edit_relations = [];
$category_name = '';

$pdo = getPDO();

if (!empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM ci_instances WHERE id = ?");
    $stmt->execute([$id]);
    $edit_ci = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($edit_ci) {
        $stmtRel = $pdo->prepare("SELECT r.*, c.hostname as target_name FROM ci_relationships r JOIN ci_instances c ON r.target_id = c.id WHERE r.source_type='ci_instances' AND r.source_id=? ORDER BY c.hostname ASC");
        $stmtRel->execute([$id]);
        $rels = $stmtRel->fetchAll(PDO::FETCH_ASSOC);
        foreach($rels as $r) {
            $edit_relations[] = [
                'target_id' => $r['target_id'],
                'target_name' => $r['target_name'],
                'type' => $r['relation_type'],
                'impact' => $r['impact']
            ];
        }
        
        $stmt_cat = $pdo->prepare("SELECT name FROM ci_categories WHERE id = ?");
        $stmt_cat->execute([$edit_ci['category_id']]);
        $category_name = $stmt_cat->fetchColumn();
    }
}

$preload_category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
if (empty($category_name) && $preload_category_id > 0) {
    $stmt_cat = $pdo->prepare("SELECT name FROM ci_categories WHERE id = ?");
    $stmt_cat->execute([$preload_category_id]);
    $category_name = $stmt_cat->fetchColumn();
}

$next_ci_unique = '';
if (!$edit_ci) {
    $seq_val = $pdo->query("SELECT ci_last_seq FROM cmdb_sequences WHERE id = 1")->fetchColumn();
    $next_ci_unique = 'SND-' . str_pad($seq_val + 1, 10, '0', STR_PAD_LEFT);
}

$page_title = ($edit_ci ? 'Edición de CI: ' : 'Creación de nuevo CI: ') . ($category_name ?: 'CI');
$hide_content_header = true;

require_once __DIR__ . '/partials/header.php';
?>

<div class="container-fluid pt-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card card-outline card-primary shadow-lg">
                <div class="card-body p-4">
                    <!-- Step 1: Seleccionar Categoría (Oculto) -->
                    <div id="step-1" class="mb-4" style="display: none;">
                        <div class="row" id="dynamic-category-selectors">
                        </div>
                    </div>

                    <!-- Step 2: Formulario Dinámico -->
                    <div id="step-2" class="d-none animate__animated animate__fadeIn">
                        <h3 class="text-primary mb-4 pb-2 border-bottom"><i class="fas fa-edit mr-2"></i> Detalles del CI: <span id="category-name-title" class="badge badge-primary px-3 py-2">Cargando...</span></h3>
                        
                        <form id="ci-form" novalidate>
                            <input type="hidden" name="action" value="save_instance">
                            <input type="hidden" name="id" id="instance_id" value="0">
                            <input type="hidden" name="category_id" id="hidden_category_id" value="">
                            
                            <!-- Origen de datos (Igual que en Rack Builder) -->
                            <div class="row mb-4 bg-light p-3 rounded">
                                <div class="col-md-12 mb-3">
                                    <label class="font-weight-bold">Origen de Datos:</label>
                                    <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                                        <label class="btn btn-outline-primary active">
                                            <input type="radio" name="source" value="manual" checked autocomplete="off"> <i class="fas fa-keyboard"></i> Manual
                                        </label>
                                        <label class="btn btn-outline-primary">
                                            <input type="radio" name="source" value="zabbix" autocomplete="off"> <i class="fas fa-server"></i> Zabbix
                                        </label>
                                    </div>
                                </div>
                                
                                <div id="zabbix-area" class="col-md-12 d-none">
                                    <div class="row">
                                        <div class="col-md-5 form-group">
                                            <label>Hostgroup Zabbix</label>
                                            <select id="zabbix_hg" class="form-control"></select>
                                        </div>
                                        <div class="col-md-5 form-group">
                                            <label>Host</label>
                                            <select id="zabbix_h" class="form-control" disabled></select>
                                        </div>
                                        <div class="col-md-2 form-group d-flex align-items-end">
                                            <button type="button" class="btn btn-info w-100" id="btn-fetch-zabbix" disabled><i class="fas fa-download"></i> Cargar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Atributos Estándar (Base) -->
                            <div class="row" id="base-attributes-container">
                                <div class="col-md-12 form-group" id="geo-parent-container" style="display: none;">
                                    <label id="geo-parent-label" class="font-weight-bold text-success">Ubicación Padre Estricta <span class="text-danger">*</span></label>
                                    <select id="f_geo_parent_id" class="form-control border-success">
                                        <option value="">Cargando...</option>
                                    </select>
                                    <small class="form-text text-muted"><i class="fas fa-lock"></i> Por gobierno de datos, esta ubicación debe enlazarse obligatoriamente a su nivel superior.</small>
                                </div>
                                
                                <!-- Contenedor de dependencias inter-categoría -->
                                <div class="col-md-12" id="cross-category-dependencies-container">
                                    <!-- Rendered dynamically via JS -->
                                </div>

                                <div style="display: none;">
                                    <input type="hidden" name="status" id="f_status" value="Activo">
                                </div>
                            </div>
                            
                            <input type="hidden" name="zabbix_host_id" id="f_zabbix_id">

                            <!-- Atributos Globales -->
                            <div class="row bg-light p-3 rounded mb-3 border">
                                <div class="col-md-12"><h5 class="text-secondary mb-3"><i class="fas fa-globe"></i> Atributos Globales</h5></div>
                                <div class="col-md-3 form-group">
                                    <label>Código Único (ci_unique)</label>
                                    <input type="text" class="form-control" id="display_ci_unique" readonly value="<?php echo htmlspecialchars($edit_ci ? $edit_ci['ci_unique'] : $next_ci_unique); ?>">
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Nombre del CI <span class="text-danger">*</span></label>
                                    <input type="text" name="hostname" id="f_hostname" class="form-control" placeholder="Ej. Switch Principal" data-required="true" value="<?php echo htmlspecialchars($edit_ci ? $edit_ci['hostname'] : ''); ?>">
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Sigla / Etiqueta <span class="text-danger">*</span></label>
                                    <input type="text" name="sigla" id="f_sigla" class="form-control" placeholder="Ej. SW-CORE-01" data-required="true" value="<?php echo htmlspecialchars($edit_ci ? $edit_ci['sigla'] : ''); ?>">
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Dirección IP</label>
                                    <input type="text" name="ip_address" id="f_ip" class="form-control" placeholder="Ej. 192.168.1.1" value="<?php echo htmlspecialchars($edit_ci ? $edit_ci['ip_address'] : ''); ?>">
                                </div>
                                <div class="col-md-9 form-group mt-2">
                                    <label>Descripción</label>
                                    <textarea name="description" class="form-control" rows="2" placeholder="Información adicional del CI..."><?php echo htmlspecialchars($edit_ci ? $edit_ci['description'] : ''); ?></textarea>
                                </div>
                                <div class="col-md-3 form-group mt-2">
                                    <label>Fecha de Creación</label>
                                    <input type="text" class="form-control" id="display_created_at" readonly value="<?php echo htmlspecialchars($edit_ci ? $edit_ci['created_at'] : date('Y-m-d H:i:s')); ?>">
                                </div>
                            </div>

                            <!-- Dynamic Fields Container (Tabs) -->
                            <div id="dynamic-fields" class="mt-3">
                                <!-- Rendered via JS -->
                            </div>
                            
                            <div class="form-group text-right mt-4 pt-3 border-top">
                                <input type="hidden" name="ci_relations" id="ci_relations_input" value="[]">
                                <button type="submit" class="btn btn-success btn-lg px-5 shadow" id="btn-submit-ci"><i class="fas fa-save mr-2"></i> Guardar CI</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let categories = [];
let currentSchema = {};

let editData = <?php echo $edit_ci ? json_encode($edit_ci) : 'null'; ?>;
let editRelationsData = <?php echo json_encode($edit_relations); ?>;
let preloadCategoryId = <?php echo $preload_category_id; ?>;

let hostnameManuallyEdited = editData ? true : false;
let siglaManuallyEdited = editData ? true : false;

$(document).ready(function() {
    loadCategories();

    // Sincronización de campos globales a dinámicos
    $(document).on('input', '#f_hostname', function() {
        hostnameManuallyEdited = true;
        let val = $(this).val();
        let nameField = $('#dynamic-fields [name="nombre"], #dynamic-fields [name="name"], #dynamic-fields [name="hostname"]');
        if (nameField.length) {
            nameField.val(val);
        }
    });

    $(document).on('input', '#f_sigla', function() {
        siglaManuallyEdited = true;
        let val = $(this).val();
        let sigField = $('#dynamic-fields [name="sigla"], #dynamic-fields [name="siglas"], #dynamic-fields [name="codigo"], #dynamic-fields [name="code"]');
        if (sigField.length) {
            sigField.val(val);
        }
    });

    // Delegación de eventos para selectores dinámicos
    $(document).on('change', '.dynamic-cat-select', function() {
        let level = parseInt($(this).data('level'));
        let val = $(this).val();
        
        // Eliminar selectores de niveles inferiores
        $('.dynamic-cat-select').each(function() {
            if (parseInt($(this).data('level')) > level) {
                $(this).closest('.col-md-4').remove();
            }
        });

        if (val) {
            handleCategorySelect(val);
            renderDynamicSelectors(val, level + 1);
        } else {
            // Si deseleccionó, vuelve al valor del padre
            let prevLevel = level - 1;
            let parentVal = prevLevel > 0 ? $(`#cat_level_${prevLevel}`).val() : null;
            handleCategorySelect(parentVal);
        }
    });

    $('input[name="source"]').change(function() {
        if ($(this).val() === 'zabbix') {
            $('#zabbix-area').removeClass('d-none');
            loadZabbixHG();
        } else {
            $('#zabbix-area').addClass('d-none');
        }
    });

    $('#zabbix_hg').change(function() {
        let gid = $(this).val();
        if (gid) loadZabbixHosts(gid);
    });

    $('#zabbix_h').change(function() {
        $('#btn-fetch-zabbix').prop('disabled', !$(this).val());
    });

    $('#btn-fetch-zabbix').click(function() {
        let sel = $('#zabbix_h option:selected');
        let hostId = sel.val();
        if (!hostId) return;

        // Show loading state
        let originalText = $('#btn-fetch-zabbix').html();
        $('#btn-fetch-zabbix').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Cargando...');

        $.get('api_zabbix.php', { action: 'get_host_details', hostid: hostId }, function(res) {
            $('#btn-fetch-zabbix').prop('disabled', false).html(originalText);
            
            if (res.success && res.data) {
                let data = res.data;

                // 1. Base Attributes (Nombre, Sigla/Etiqueta, IP, Zabbix ID, Descripcion)
                $('#f_hostname').val(data.host || '');
                hostnameManuallyEdited = true;

                $('#f_sigla').val(data.name || '');
                siglaManuallyEdited = true;

                let ipVal = '';
                if (data.interfaces && data.interfaces.length > 0) {
                    ipVal = data.interfaces[0].ip || '';
                }
                $('#f_ip').val(ipVal);

                $('#f_zabbix_id').val(data.hostid || '');
                $('textarea[name="description"]').val(data.hostid || '');

                // 2. Inventory attributes (data.inventory)
                if (data.inventory) {
                    let mac = data.inventory.macaddress_a || data.inventory.macaddress_b || '';
                    let macField = $('#dynamic-fields [name="mac_address"], #dynamic-fields [name="mac"], #dynamic-fields [name="macaddress"]');
                    if (macField.length) macField.val(mac).trigger('change');

                    let vendor = data.inventory.vendor || '';
                    let vendorField = $('#dynamic-fields [name="marca"], #dynamic-fields [name="vendor"], #dynamic-fields [name="brand"]');
                    if (vendorField.length) vendorField.val(vendor).trigger('change');

                    let model = data.inventory.model || '';
                    let modelField = $('#dynamic-fields [name="modelo"], #dynamic-fields [name="model"]');
                    if (modelField.length) modelField.val(model).trigger('change');

                    let contact = data.inventory.contact || '';
                    let contactField = $('#dynamic-fields [name="contacto"], #dynamic-fields [name="contact"]');
                    if (contactField.length) contactField.val(contact).trigger('change');

                    let lat = data.inventory.location_lat || '';
                    let latField = $('#dynamic-fields [name="latitud"], #dynamic-fields [name="latitude"]');
                    if (latField.length) latField.val(lat).trigger('change');

                    let lon = data.inventory.location_lon || '';
                    let lonField = $('#dynamic-fields [name="longitud"], #dynamic-fields [name="longitude"]');
                    if (lonField.length) lonField.val(lon).trigger('change');

                    let os = data.inventory.os || '';
                    let osField = $('#dynamic-fields [name="os"], #dynamic-fields [name="so"]');
                    if (osField.length) osField.val(os).trigger('change');
                }

                // 3. Technical fields & macros
                let tipoField = $('#dynamic-fields [name="tipo"]');
                if (tipoField.length) {
                    tipoField.val('activo').trigger('change');
                }

                let monHostnameField = $('#dynamic-fields [name="hostname"]');
                if (monHostnameField.length) {
                    monHostnameField.val(data.host || '').trigger('change');
                }

                let community = 'public';
                if (data.macros && Array.isArray(data.macros)) {
                    let snmpMacro = data.macros.find(m => m.macro === '{$SNMP_COMMUNITY}');
                    if (snmpMacro) community = snmpMacro.value;
                }
                let snmpField = $('#dynamic-fields [name="snmp_read_community"], #dynamic-fields [name="snmp"], #dynamic-fields [name="snmp_community"]');
                if (snmpField.length) {
                    snmpField.val(community).trigger('change');
                }

                let monitoreoField = $('#dynamic-fields [name="monitoreo"]');
                if (monitoreoField.length) {
                    if (monitoreoField.attr('multiple')) {
                        monitoreoField.val(['Zabbix']).trigger('change');
                    } else {
                        monitoreoField.val('Zabbix').trigger('change');
                    }
                }

                let statMonField = $('#dynamic-fields [name="status_monitoreo"]');
                if (statMonField.length) {
                    if (statMonField.is(':checkbox')) {
                        statMonField.prop('checked', true).trigger('change');
                    } else {
                        statMonField.val('1').trigger('change');
                    }
                }

                let hostgroups = data.groups ? data.groups.map(g => g.name).join(', ') : '';
                let hgField = $('#dynamic-fields [name="hostgroup"], #dynamic-fields [name="hostgroups"]');
                if (hgField.length) {
                    hgField.val(hostgroups).trigger('change');
                }

                let templates = data.parentTemplates ? data.parentTemplates.map(t => t.name).join(', ') : '';
                let templField = $('#dynamic-fields [name="templates_asociados"], #dynamic-fields [name="templates"]');
                if (templField.length) {
                    templField.val(templates).trigger('change');
                }

                toastr.success('Datos completos cargados de Zabbix y mapeados al formulario');
            } else {
                toastr.error('No se pudieron obtener los detalles del host de Zabbix');
            }
        }, 'json').fail(function() {
            $('#btn-fetch-zabbix').prop('disabled', false).html(originalText);
            toastr.error('Error de red al conectar con el servidor');
        });
    });

    $('#ci-form').submit(function(e) {
        e.preventDefault();
        
        updateHostnameFromFields();

        // Validacion manual de campos requeridos (evita el error de foco en campos ocultos de pestañas)
        let firstInvalid = null;
        $(this).find('[data-required="true"]').each(function() {
            if (!$(this).val() || $(this).val().toString().trim() === '') {
                firstInvalid = this;
                return false; // Break loop
            }
        });
        if (firstInvalid) {
            let label = $(firstInvalid).closest('.form-group').find('label').text().replace(/\*|\(Opcional\)/g, '').trim();
            let tabPane = $(firstInvalid).closest('.tab-pane');
            if (tabPane.length && !tabPane.hasClass('active')) {
                let tabId = tabPane.attr('id');
                $(`a[href="#${tabId}"]`).tab('show');
            }
            setTimeout(() => {
                $(firstInvalid).focus();
            }, 100);
            Swal.fire('Atención', `El campo "${label}" es obligatorio.`, 'warning');
            return;
        }
        
        // GEO-PARENT INTERCEPT:
        let finalRelations = [];
        if(typeof pendingRelations !== 'undefined') {
            finalRelations = [...pendingRelations];
        }

        if ($('#geo-parent-container').is(':visible')) {
            let parentId = $('#f_geo_parent_id').val();
            let parentName = $('#f_geo_parent_id option:selected').text();
            let isRequired = $('#f_geo_parent_id').attr('data-required') === 'true';
            
            $(this).find('input[name="parent_ci_id"]').remove();
            
            if (isRequired && !parentId) {
                Swal.fire('Atención', 'Debe seleccionar la Dependencia Padre obligatoria', 'warning');
                return;
            }
            
            if (parentId) {
                $('<input>').attr({type: 'hidden', name: 'parent_ci_id', value: parentId}).appendTo(this);
                finalRelations.push({
                    target_id: parentId,
                    target_name: parentName,
                    type: 'Contains',
                    impact: 'Sí'
                });
            } else {
                $('<input>').attr({type: 'hidden', name: 'parent_ci_id', value: ''}).appendTo(this);
            }
        }

        // Inter-categoría dependencias intercept
        let depValidationPassed = true;
        $('.dep-ci-select').each(function() {
            let val = $(this).val();
            let labelText = $(this).closest('.form-group').find('label').text().replace(/\*|\(Opcional\)/g, '').trim();
            let isRequired = $(this).attr('data-required') === 'true';
            
            if (isRequired && !val) {
                Swal.fire('Atención', 'Debe seleccionar ' + labelText, 'warning');
                depValidationPassed = false;
                return false; // Break loop
            }
            
            if (val) {
                if (!finalRelations.find(r => r.target_id == val)) {
                    finalRelations.push({
                        target_id: val,
                        target_name: $(this).find('option:selected').text(),
                        type: 'Depends on',
                        impact: $(this).data('type') === 'required' ? 'Sí' : 'No'
                    });
                }
            }
        });

        if (!depValidationPassed) return;

        $('#ci_relations_input').val(JSON.stringify(finalRelations));

        $.post('api_ci.php', $(this).serialize(), function(res) {
            if (res.success) {
                Swal.fire('Éxito', res.message, 'success').then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json').fail(function(xhr) {
            console.error(xhr.responseText);
            Swal.fire('Error del Servidor', 'Hubo un error fatal al intentar guardar en la base de datos. Revise la consola.', 'error');
        });
    });

    $(document).on('input change', '#dynamic-fields input, #dynamic-fields select, #dynamic-fields textarea', function() {
        let name = $(this).attr('name');
        let val = $(this).val();
        
        if (name === 'nombre' || name === 'name') {
            if (!hostnameManuallyEdited) {
                $('#f_hostname').val(val);
            }
        }
        if (name === 'siglas' || name === 'sigla' || name === 'codigo' || name === 'code') {
            if (!siglaManuallyEdited) {
                $('#f_sigla').val(val);
            }
        }
        updateHostnameFromFields();
    });

    // Evento para subir imagen de atributo dinámico
    $(document).on('change', '.attr-image-file-input', function() {
        let fileInput = this;
        let key = $(this).data('key');
        let file = fileInput.files[0];
        if (!file) return;

        let formData = new FormData();
        formData.append('image', file);
        formData.append('table', 'ci_attributes');
        formData.append('id', '0');

        $(`#img_spinner_${key}`).show();
        $(`#img_preview_container_${key}`).hide();
        $(`#img_file_${key}`).hide();

        $.ajax({
            url: 'upload_image.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                $(`#img_spinner_${key}`).hide();
                if (res.success) {
                    $(`#img_val_${key}`).val(res.filepath);
                    $(`#img_preview_img_${key}`).attr('src', res.filepath);
                    $(`#img_preview_container_${key}`).show();
                    
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Imagen subida correctamente',
                        showConfirmButton: false,
                        timer: 1500
                    });
                } else {
                    $(`#img_file_${key}`).show();
                    Swal.fire('Error', res.error || 'No se pudo subir la imagen', 'error');
                }
            },
            error: function() {
                $(`#img_spinner_${key}`).hide();
                $(`#img_file_${key}`).show();
                Swal.fire('Error', 'Error técnico al intentar subir la imagen.', 'error');
            }
        });
    });

    // Evento para quitar imagen de atributo dinámico
    $(document).on('click', '.btn-remove-attr-image', function() {
        let key = $(this).data('key');
        $(`#img_val_${key}`).val('');
        $(`#img_preview_img_${key}`).attr('src', '');
        $(`#img_preview_container_${key}`).hide();
        $(`#img_file_${key}`).val('').show();
        
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'info',
            title: 'Imagen removida',
            showConfirmButton: false,
            timer: 1500
        });
    });
});

function loadCategories() {
    $.get('api_ci.php?action=get_categories', function(res) {
        if (res.success) {
            categories = res.data;
            if (editData) {
                preloadEditData();
            } else if (preloadCategoryId > 0) {
                handleCategorySelect(preloadCategoryId);
            } else {
                Swal.fire({
                    title: 'Atención',
                    text: 'No se ha seleccionado una categoría de CI.',
                    icon: 'warning',
                    confirmButtonText: 'Ir al Listado'
                }).then(() => {
                    window.location.href = 'ci_list.php';
                });
            }
        }
    }, 'json');
}

function preloadEditData() {
    $('#instance_id').val(editData.id);
    $('#f_hostname').val(editData.hostname);
    $('input[name="ip_address"]').val(editData.ip_address);
    $('textarea[name="description"]').val(editData.description);
    $('#f_status').val(editData.status);
    
    // Rellenar Atributos Globales Obligatorios
    $('#display_ci_unique').val(editData.ci_unique || '');
    $('#f_sigla').val(editData.sigla || '');
    $('#display_created_at').val(editData.created_at || '');
    
    if (editData.source === 'zabbix') {
        $('input[name="source"][value="zabbix"]').prop('checked', true).closest('label').addClass('active');
        $('input[name="source"][value="manual"]').prop('checked', false).closest('label').removeClass('active');
        $('#zabbix-area').removeClass('d-none');
        $('#f_zabbix_id').val(editData.zabbix_host_id);
    }
    
    if (editRelationsData && editRelationsData.length > 0) {
        pendingRelations = editRelationsData;
        updateRelationsUI();
    }
    
    handleCategorySelect(editData.category_id);
    fillDynamicData();
}

function fillDynamicData() {
    let attrs = {};
    try { attrs = JSON.parse(editData.attributes_json); } catch(e) {}
    for (let key in attrs) {
        let el = $(`[name="${key}"]`);
        if (el.length) {
            let val = attrs[key];
            if (Array.isArray(val)) {
                val = val[0];
            }
            el.val(val);
            
            if (el.hasClass('image-filepath-input') && val) {
                $(`#img_preview_img_${key}`).attr('src', val);
                $(`#img_preview_container_${key}`).show();
                $(`#img_file_${key}`).hide();
            }
        }
    }
}

function updateHostnameFromFields() {
    if (hostnameManuallyEdited) return;
    let hostnameVal = '';
    
    // 1. Try to find a field named 'nombre' or 'name' or 'hostname'
    let nameField = $('#dynamic-fields [name="nombre"], #dynamic-fields [name="name"], #dynamic-fields [name="hostname"]').first();
    if (nameField.length && nameField.val()) {
        hostnameVal = nameField.val().trim();
    }
    
    // 2. If empty, try 'siglas' or 'codigo' or 'code'
    if (!hostnameVal) {
        let sigField = $('#dynamic-fields [name="siglas"], #dynamic-fields [name="codigo"], #dynamic-fields [name="code"]').first();
        if (sigField.length && sigField.val()) {
            hostnameVal = sigField.val().trim();
        }
    }
    
    // 3. If empty, try combination of 'marca' and 'modelo'
    if (!hostnameVal) {
        let marca = $('#dynamic-fields [name="marca"]').val();
        let modelo = $('#dynamic-fields [name="modelo"]').val();
        if (marca || modelo) {
            hostnameVal = ((marca || '') + ' ' + (modelo || '')).trim();
        }
    }
    
    // 4. If empty, get the first input/select/textarea in the dynamic fields that has a value
    if (!hostnameVal) {
        $('#dynamic-fields input, #dynamic-fields select, #dynamic-fields textarea').each(function() {
            let val = $(this).val();
            let type = $(this).attr('type');
            if (val && type !== 'hidden' && type !== 'submit' && type !== 'button') {
                if (Array.isArray(val)) {
                    hostnameVal = val.join(', ').trim();
                } else if (typeof val === 'string') {
                    hostnameVal = val.trim();
                } else {
                    hostnameVal = String(val).trim();
                }
                return false; // break loop
            }
        });
    }
    
    // 5. Fallback to category name
    if (!hostnameVal) {
        let lineage = getCategoryLineage($('#hidden_category_id').val());
        if (lineage.length > 0) {
            hostnameVal = lineage[lineage.length - 1].name;
        } else {
            hostnameVal = 'CI sin nombre';
        }
    }
    
    $('#f_hostname').val(hostnameVal);
}

function getCategoryLineage(catId) {
    let lineage = [];
    let currentId = catId;
    while (currentId) {
        let cat = categories.find(c => c.id == currentId);
        if (cat) {
            lineage.unshift(cat); // Root category first
            currentId = cat.parent_id;
        } else {
            break;
        }
    }
    return lineage;
}

function handleCategorySelect(catId) {
    if (!catId) {
        $('#step-2').addClass('d-none');
        return;
    }
    $('#hidden_category_id').val(catId);
    let lineage = getCategoryLineage(catId);
    if (lineage.length > 0) {
        let targetCat = lineage[lineage.length - 1];
        $('#category-name-title').text(targetCat.name);
        
        // Dynamically update page title in content header
        let pageTitleText = (editData ? 'Edición de CI: ' : 'Creación de nuevo CI: ') + targetCat.name;
        $('.content-header h1').text(pageTitleText);
        document.title = pageTitleText;
    }
    buildDynamicForm(lineage);
    checkGeoHierarchy(lineage);
    renderCrossCategoryDependencies(catId);
    $('#step-2').removeClass('d-none');
}

function renderCrossCategoryDependencies(catId) {
    $('#cross-category-dependencies-container').empty();
}

function checkGeoHierarchy(lineage) {
    let targetCat = lineage[lineage.length - 1]; 
    
    // Todos los subniveles N-1 requieren/permiten un CI del nivel padre si el padre no es categoría raíz (menú izquierdo)
    if (targetCat.parent_id) {
        let parentCatObj = categories.find(c => c.id == targetCat.parent_id);
        
        if (parentCatObj && parentCatObj.parent_id) {
            let isRequired = targetCat.requires_parent_instance == 1;
            $('#geo-parent-container').show();
            $('#f_geo_parent_id').attr('data-required', isRequired ? 'true' : 'false').html('<option value="">Cargando...</option>');
            
            if (isRequired) {
                $('#geo-parent-label').html(`Dependencia Obligatoria (${parentCatObj.name}) <span class="text-danger">*</span>`);
            } else {
                $('#geo-parent-label').html(`Dependencia Opcional (${parentCatObj.name})`);
            }
            
            $('#relations-table').closest('.col-12').show();

            $.get(`api_ci.php?action=get_ci_by_category&category_id=${parentCatObj.id}`, function(res) {
                if (res.success) {
                    let sel = $('#f_geo_parent_id');
                    sel.html(`<option value="">-- Seleccione ${parentCatObj.name} --</option>`);
                    res.data.forEach(ci => {
                        let isSelected = editData && editData.parent_ci_id == ci.id ? 'selected' : '';
                        sel.append(`<option value="${ci.id}" ${isSelected}>${ci.hostname}</option>`);
                    });
                }
            }, 'json');
            return;
        }
    }
    
    // Nivel raíz o secundario directo (cuyo padre es la raíz del menú izquierdo como Ubicaciones)
    $('#geo-parent-container').hide();
    $('#f_geo_parent_id').attr('data-required', 'false').val('');
    $('#relations-table').closest('.col-12').show();
}

function getDependencyChain(rootCatId) {
    let rootCat = categories.find(c => c.id == rootCatId);
    if (!rootCat) return [];
    
    let chain = [];
    let children = categories.filter(c => c.parent_id == rootCatId);
    if (children.length === 0) {
        chain.push(rootCat);
    } else {
        function traverse(parentCatId) {
            let subChildren = categories.filter(c => c.parent_id == parentCatId);
            subChildren.forEach(child => {
                chain.push(child);
                traverse(child.id);
            });
        }
        traverse(rootCatId);
    }
    return chain;
}

function setupDependencyCascades(depLevels) {
    if (depLevels.length === 0) return;
    
    let rootLevel = depLevels[0];
    
    loadCIsForLevel(rootLevel.id, null, function() {
        if (editData || (editRelationsData && editRelationsData.length > 0)) {
            preselectDependencyChain(depLevels, 0);
        }
    });
    
    $(`#dep_select_${rootLevel.id}`).prop('disabled', false);
    
    for (let i = 0; i < depLevels.length - 1; i++) {
        let currentLevel = depLevels[i];
        let nextLevel = depLevels[i+1];
        
        $(`#dep_select_${currentLevel.id}`).on('change', function() {
            let val = $(this).val();
            let nextSelect = $(`#dep_select_${nextLevel.id}`);
            
            for (let j = i + 1; j < depLevels.length; j++) {
                let subSelect = $(`#dep_select_${depLevels[j].id}`);
                subSelect.val('').prop('disabled', true).html('<option value="">Seleccione el nivel anterior...</option>');
            }
            
            if (val) {
                nextSelect.prop('disabled', false).html('<option value="">Cargando...</option>');
                loadCIsForLevel(nextLevel.id, val);
            }
        });
    }
}

function loadCIsForLevel(catId, parentCiId, callback) {
    let selectEl = $(`#dep_select_${catId}`);
    $.get(`api_ci.php?action=get_ci_by_category&category_id=${catId}&parent_ci_id=${parentCiId || ''}`, function(res) {
        if (res.success) {
            let catObj = categories.find(c => c.id == catId);
            selectEl.html(`<option value="">-- Seleccione ${catObj.name} --</option>`);
            res.data.forEach(ci => {
                selectEl.append(`<option value="${ci.id}">${ci.hostname}</option>`);
            });
            if (callback) callback();
        }
    }, 'json');
}

function preselectDependencyChain(depLevels, currentIndex) {
    if (currentIndex >= depLevels.length) return;
    
    let currentLevel = depLevels[currentIndex];
    let selectEl = $(`#dep_select_${currentLevel.id}`);
    
    let matchedOptionId = null;
    if (editRelationsData && editRelationsData.length > 0) {
        selectEl.find('option').each(function() {
            let optId = $(this).val();
            if (optId && editRelationsData.find(r => r.target_id == optId)) {
                matchedOptionId = optId;
                return false; // break loop
            }
        });
    }
    
    if (matchedOptionId) {
        selectEl.val(matchedOptionId);
        selectEl.prop('disabled', false);
        
        if (currentIndex + 1 < depLevels.length) {
            let nextLevel = depLevels[currentIndex + 1];
            let nextSelect = $(`#dep_select_${nextLevel.id}`);
            nextSelect.prop('disabled', false).html('<option value="">Cargando...</option>');
            
            loadCIsForLevel(nextLevel.id, matchedOptionId, function() {
                preselectDependencyChain(depLevels, currentIndex + 1);
            });
        }
    }
}

function buildDynamicForm(lineage) {
    let container = $('#dynamic-fields');
    
    container.empty();

    let allProperties = {};
    let requiredFields = [];
    
    // Merge all schemas from lineage
    lineage.forEach(cat => {
        let schema = {};
        try {
            schema = typeof cat.schema_json === 'string' ? JSON.parse(cat.schema_json) : cat.schema_json;
        } catch(e) { }

        if (schema && schema.properties) {
            for (let key in schema.properties) {
                allProperties[key] = schema.properties[key];
            }
            if (schema.required) {
                requiredFields = requiredFields.concat(schema.required);
            }
        }
    });

    let targetCat = lineage[lineage.length - 1];
    let dependenciesList = [];
    
    if (targetCat && targetCat.dependencies && targetCat.dependencies.length > 0) {
        targetCat.dependencies.forEach(dep => {
            let rootCat = categories.find(c => c.id == dep.target_category_id);
            if (rootCat) {
                let depChain = getDependencyChain(dep.target_category_id);
                dependenciesList.push({
                    root: rootCat,
                    chain: depChain,
                    depType: dep.dependency_type
                });
            }
        });
    }

    if (Object.keys(allProperties).length === 0 && dependenciesList.length === 0) {
        container.append('<div class="col-12"><p class="text-muted">No hay atributos específicos ni dependencias definidas para esta clase.</p></div>');
        return;
    }

    // Group properties by their 'group' attribute
    let groups = {};
    for (let key in allProperties) {
        let prop = allProperties[key];
        let groupName = prop.group || 'Atributos';
        if (!groups[groupName]) groups[groupName] = {};
        groups[groupName][key] = prop;
    }

    // Render by group using Bootstrap Tabs
    let tabsHtml = '<ul class="nav nav-tabs w-100 mb-3" id="ciTabs" role="tablist">';
    let contentHtml = '<div class="tab-content w-100" id="ciTabsContent">';

    let groupKeys = Object.keys(groups).sort();
    let tabIndex = 0;
    
    groupKeys.forEach((groupName) => {
        let safeId = 'group-' + groupName.replace(/\s+/g, '-').toLowerCase() + '-' + tabIndex;
        let activeClass = tabIndex === 0 ? 'active' : '';
        let ariaSelected = tabIndex === 0 ? 'true' : 'false';

        tabsHtml += `
            <li class="nav-item">
                <a class="nav-link font-weight-bold ${activeClass}" id="tab-${safeId}" data-toggle="tab" href="#content-${safeId}" role="tab" aria-controls="content-${safeId}" aria-selected="${ariaSelected}">
                    <i class="fas fa-layer-group mr-1"></i> ${groupName}
                </a>
            </li>`;

        contentHtml += `<div class="tab-pane fade show ${activeClass}" id="content-${safeId}" role="tabpanel" aria-labelledby="tab-${safeId}">`;
        
        contentHtml += `<div class="row pt-3">`;
        
        if (groupName === 'Dependencias y Relaciones') {
            contentHtml += `
                <div class="col-12 mt-2 mb-3">
                    <button type="button" class="btn btn-outline-primary btn-sm mb-2" onclick="openRelationModal()"><i class="fas fa-project-diagram"></i> Añadir Relación</button>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered" id="relations-table">
                            <thead class="bg-light">
                                <tr><th>Relación</th><th>Destino</th><th>Impacto</th><th>Acción</th></tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="4" class="text-center text-muted small">Sin relaciones.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }
        
        let props = groups[groupName];
        for (let key in props) {
            let prop = props[key];
            let isRequired = requiredFields.includes(key);
            let reqMark = isRequired ? '<span class="text-danger">*</span>' : '';
            let label = key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
            if (prop.description) label += ` <small class="text-muted">(${prop.description})</small>`;
            
            let inputHtml = '';
            if (prop.enum) {
                inputHtml = `<select name="${key}" class="form-control" ${isRequired ? 'data-required="true"' : ''}>`;
                inputHtml += `<option value="">Seleccionar...</option>`;
                prop.enum.forEach(val => {
                    inputHtml += `<option value="${val}">${val}</option>`;
                });
                inputHtml += `</select>`;
            } else if (prop.type === 'boolean') {
                inputHtml = `<select name="${key}" class="form-control" ${isRequired ? 'data-required="true"' : ''}>
                    <option value="1">Sí</option><option value="0">No</option>
                </select>`;
            } else if (prop.type === 'integer' || prop.type === 'number') {
                inputHtml = `<input type="number" name="${key}" class="form-control" ${isRequired ? 'data-required="true"' : ''}>`;
            } else if (prop.type === 'textarea') {
                inputHtml = `<textarea name="${key}" class="form-control" rows="2" ${isRequired ? 'data-required="true"' : ''}></textarea>`;
            } else if (prop.type === 'multiselect') {
                let choices = prop.choices || [];
                inputHtml = `<select name="${key}" class="form-control" ${isRequired ? 'data-required="true"' : ''}>`;
                inputHtml += `<option value="">Seleccione...</option>`;
                choices.forEach(val => {
                    inputHtml += `<option value="${val}">${val}</option>`;
                });
                inputHtml += `</select>`;
            } else if (prop.type === 'date' || prop.format === 'date') {
                inputHtml = `<input type="date" name="${key}" class="form-control" ${isRequired ? 'data-required="true"' : ''}>`;
            } else if (prop.type === 'image') {
                inputHtml = `
                    <input type="hidden" name="${key}" id="img_val_${key}" class="image-filepath-input" ${isRequired ? 'data-required="true"' : ''}>
                    <input type="file" class="form-control-file attr-image-file-input" id="img_file_${key}" data-key="${key}" accept="image/*">
                    <div id="img_preview_container_${key}" class="mt-2" style="display:none; position: relative; max-width: 180px;">
                        <img src="" id="img_preview_img_${key}" class="img-thumbnail img-fluid" style="max-height: 150px; border: 2px solid #ddd; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <button type="button" class="btn btn-sm btn-danger position-absolute btn-remove-attr-image" data-key="${key}" style="top: 5px; right: 5px; border-radius: 50%; width: 28px; height: 28px; padding: 0;" title="Eliminar Imagen"><i class="fas fa-trash-alt" style="font-size: 0.8rem;"></i></button>
                    </div>
                    <div id="img_spinner_${key}" class="mt-2 text-primary font-weight-bold" style="display:none;">
                        <i class="fas fa-spinner fa-spin mr-1"></i> Subiendo imagen...
                    </div>
                `;
            } else {
                inputHtml = `<input type="text" name="${key}" class="form-control" ${isRequired ? 'data-required="true"' : ''}>`;
            }

            contentHtml += `
                <div class="col-md-6 form-group">
                    <label>${label} ${reqMark}</label>
                    ${inputHtml}
                </div>
            `;
        }
        contentHtml += `</div></div>`;
        tabIndex++;
    });

    let thematicDeps = {
        'Ubicación': [],
        'Personal / Contacto': [],
        'Facility': [],
        'Hardware / Infraestructura': [],
        'Servicios / Software': [],
        'Otros / Relacionados': []
    };

    function getThematicGroup(categoryName) {
        if (!categoryName) return 'Otros / Relacionados';
        let catLower = categoryName.toLowerCase();
        const groupMappings = {
            'Ubicación': ['país', 'pais', 'ciudad', 'datacenter', 'rooms', 'room', 'rack', 'fila', 'ubicación', 'ubicacion', 'geografía', 'geografia', 'sector', 'edificio', 'localidad', 'área', 'area', 'cuartos', 'cuarto'],
            'Personal / Contacto': ['personal', 'soporte', 'propietario', 'contacto', 'proveedor', 'usuario', 'creador', 'cliente'],
            'Facility': ['facility', 'eléctrico', 'electrico', 'aire', 'climatización', 'climatizacion', 'energía', 'energia', 'generador', 'ups', 'pdu', 'batería', 'bateria', 'chiller', 'tablero'],
            'Hardware / Infraestructura': ['servidor', 'storage', 'switch', 'router', 'firewall', 'chasis', 'blade', 'hardware', 'equipo', 'monitoreo', 'enlace', 'red'],
            'Servicios / Software': ['servicio', 'software', 'sistema operativo', 'base de datos', 'aplicación', 'aplicacion', 'api', 'licencia', 'vlan', 'puerto']
        };
        for (let group in groupMappings) {
            if (groupMappings[group].some(keyword => catLower.includes(keyword))) {
                return group;
            }
        }
        return 'Otros / Relacionados';
    }

    dependenciesList.forEach((dep) => {
        let grp = getThematicGroup(dep.root.name);
        thematicDeps[grp].push(dep);
    });

    let thematicKeys = Object.keys(thematicDeps);
    thematicKeys.forEach((groupName) => {
        let depsInGroup = thematicDeps[groupName];
        if (depsInGroup.length === 0) return;

        let safeGroupId = 'dep-theme-' + groupName.replace(/[^a-zA-Z0-9]/g, '-').toLowerCase() + '-' + tabIndex;
        let activeClass = tabIndex === 0 ? 'active' : '';
        let ariaSelected = tabIndex === 0 ? 'true' : 'false';

        let iconClass = 'fa-link';
        if (groupName === 'Ubicación') iconClass = 'fa-map-marker-alt';
        else if (groupName === 'Personal / Contacto') iconClass = 'fa-users';
        else if (groupName === 'Hardware / Infraestructura') iconClass = 'fa-laptop-house';
        else if (groupName === 'Servicios / Software') iconClass = 'fa-code-branch';

        tabsHtml += `
            <li class="nav-item">
                <a class="nav-link font-weight-bold ${activeClass}" id="tab-${safeGroupId}" data-toggle="tab" href="#content-${safeGroupId}" role="tab" aria-controls="content-${safeGroupId}" aria-selected="${ariaSelected}">
                    <i class="fas ${iconClass} mr-1.5"></i> ${groupName}
                </a>
            </li>`;

        contentHtml += `<div class="tab-pane fade show ${activeClass}" id="content-${safeGroupId}" role="tabpanel" aria-labelledby="tab-${safeGroupId}">`;
        contentHtml += `<div class="row pt-3 px-3">`;

        depsInGroup.forEach((dep) => {
            let isRequired = dep.depType === 'required';
            contentHtml += `
                <div class="col-12 mb-2">
                    <h6 class="text-secondary font-weight-bold border-bottom pb-1">
                        <i class="fas fa-sitemap mr-1"></i> ${dep.root.name.replace(/^\d+\s+/, '')}
                    </h6>
                </div>`;

            dep.chain.forEach((levelCat) => {
                let selectId = 'dep_select_' + levelCat.id;
                let reqMark = isRequired ? '<span class="text-danger">*</span>' : '';
                
                contentHtml += `
                    <div class="col-md-4 form-group mb-3">
                        <label class="font-weight-bold ${isRequired ? 'text-primary' : 'text-secondary'}">
                            ${levelCat.name} ${reqMark}
                        </label>
                        <select id="${selectId}" class="form-control dep-ci-select border-primary" data-target-cat="${levelCat.id}" data-type="${dep.depType}" ${isRequired ? 'data-required="true"' : ''} disabled>
                            <option value="">Seleccione el nivel anterior...</option>
                        </select>
                        <small class="form-text text-muted">Dependencia de ${levelCat.name}.</small>
                    </div>
                `;
            });
        });

        contentHtml += `</div></div>`;
        tabIndex++;
    });

    // Inject the "Racks / Ubicación" tab
    let safeGroupId = 'racks-ubicacion';
    tabsHtml += `
        <li class="nav-item">
            <a class="nav-link font-weight-bold" id="tab-${safeGroupId}" data-toggle="tab" href="#content-${safeGroupId}" role="tab" aria-controls="content-${safeGroupId}" aria-selected="false">
                <i class="fas fa-server mr-1"></i> Racks / Ubicación
            </a>
        </li>`;

    contentHtml += `<div class="tab-pane fade" id="content-${safeGroupId}" role="tabpanel" aria-labelledby="tab-${safeGroupId}">`;
    contentHtml += `<div class="row pt-3 px-3">`;
    contentHtml += `
        <div class="col-md-6 form-group">
            <label>Rack / Gabinete</label>
            <select name="rack_id" id="rack_id_select" class="form-control">
                <option value="">-- No asignado a Rack --</option>
            </select>
        </div>
        <div class="col-md-6 form-group">
            <label>Posición U Inicial (Start U)</label>
            <input type="number" name="rack_start_u" id="rack_start_u_input" class="form-control" min="1" value="">
            <small class="form-text text-muted">Ej: 1 para la base del rack (o depende del orden).</small>
        </div>
        <div class="col-md-6 form-group">
            <label>Alto en U (U Height)</label>
            <input type="number" name="rack_height_u" id="rack_height_u_input" class="form-control" min="1" value="1">
        </div>
        <div class="col-md-6 form-group">
            <label>Orientación / Lado</label>
            <select name="rack_orientation" id="rack_orientation_select" class="form-control">
                <option value="front">Frente (Front)</option>
                <option value="rear">Atrás (Rear)</option>
                <option value="both">Ambos Lados (Both)</option>
            </select>
        </div>
        <div class="col-md-6 form-group">
            <label>Color en el Rack</label>
            <input type="color" name="rack_color" id="rack_color_input" class="form-control" style="height: 38px;" value="#2a2a2a">
        </div>
        <div class="col-md-6 form-group">
            <label>Profundidad (Depth)</label>
            <select name="rack_depth" id="rack_depth_select" class="form-control">
                <option value="full">Completa (Full)</option>
                <option value="half">Media (1/2)</option>
                <option value="third">Un Tercio (1/3)</option>
            </select>
        </div>
    `;
    contentHtml += `</div></div>`;

    tabsHtml += '</ul>';
    contentHtml += '</div>';

    container.append(tabsHtml);
    container.append(contentHtml);

    // Asynchronously load the racks list and pre-populate fields
    $.get('api_ci.php?action=get_racks', function(res) {
        if (res.success) {
            let sel = $('#rack_id_select');
            res.data.forEach(r => {
                sel.append(`<option value="${r.id}">${r.name} (${r.room_name || 'Sin sala'})</option>`);
            });
            if (editData) {
                let attrs = {};
                try { attrs = JSON.parse(editData.attributes_json); } catch(e) {}
                if (attrs.rack_id) sel.val(attrs.rack_id);
                if (attrs.rack_start_u) $('#rack_start_u_input').val(attrs.rack_start_u);
                if (attrs.rack_height_u) $('#rack_height_u_input').val(attrs.rack_height_u);
                if (attrs.rack_orientation) $('#rack_orientation_select').val(attrs.rack_orientation);
                if (attrs.rack_color) $('#rack_color_input').val(attrs.rack_color);
                if (attrs.rack_depth) $('#rack_depth_select').val(attrs.rack_depth);
            }
        }
    }, 'json');

    
    dependenciesList.forEach((dep) => {
        if (dep.chain.length > 0) {
            setupDependencyCascades(dep.chain);
        }
    });
    
    if (typeof pendingRelations !== 'undefined' && pendingRelations.length > 0) {
        updateRelationsUI();
    }
}

// Funciones Zabbix reutilizando el endpoint de datacenter (datacenter/api.php)
function loadZabbixHG() {
    let sel = $('#zabbix_hg');
    sel.html('<option>Cargando...</option>').prop('disabled', true);
    $.get('datacenter/api.php?action=get_zabbix_hostgroups', function(res) {
        if (res.success) {
            sel.html('<option value="">Seleccione Hostgroup</option>');
            res.data.forEach(hg => sel.append(`<option value="${hg.groupid}">${hg.name}</option>`));
            sel.prop('disabled', false);
        }
    }, 'json');
}

function loadZabbixHosts(gid) {
    let sel = $('#zabbix_h');
    sel.html('<option>Cargando...</option>').prop('disabled', true);
    $.get(`datacenter/api.php?action=get_zabbix_hosts&groupid=${gid}`, function(res) {
        if (res.success) {
            sel.html('<option value="">Seleccione Host</option>');
            res.data.forEach(h => sel.append(`<option value="${h.hostid}" data-name="${h.name}" data-ip="${h.ip}">${h.name} (${h.ip})</option>`));
            sel.prop('disabled', false);
        }
    }, 'json');
}
</script>

<!-- Relaciones Modal -->
<div class="modal fade" id="relationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title"><i class="fas fa-project-diagram mr-2"></i> Añadir Relación</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Categoría Destino</label>
                    <select id="rel_cat_1" class="form-control form-control-sm mb-2">
                        <option value="">-- Nivel 1 --</option>
                    </select>
                    <select id="rel_cat_2" class="form-control form-control-sm mb-2" disabled>
                        <option value="">-- Nivel 2 --</option>
                    </select>
                    <select id="rel_cat_3" class="form-control form-control-sm" disabled>
                        <option value="">-- Nivel 3 --</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>CI Destino <span class="text-danger">*</span></label>
                    <select id="rel_target_id" class="form-control" disabled>
                        <option value="">Seleccione Categoría Primero...</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tipo de Relación <span class="text-danger">*</span></label>
                    <select id="rel_type" class="form-control">
                        <optgroup label="Dependencia Técnica">
                            <option value="Runs on">Runs on (Se ejecuta en)</option>
                            <option value="Communicates with">Communicates with (Se comunica con)</option>
                            <option value="Storage provided by">Storage provided by (Almacenamiento provisto por)</option>
                        </optgroup>
                        <optgroup label="Composición (Jerárquicas)">
                            <option value="Contains">Contains (Contiene)</option>
                            <option value="Is Member of">Is Member of (Es miembro de)</option>
                        </optgroup>
                        <optgroup label="Despliegue de Software">
                            <option value="Instantiated from">Instantiated from (Instanciado de)</option>
                            <option value="Depends on">Depends on (Depende de)</option>
                        </optgroup>
                        <optgroup label="Negocio / Servicios">
                            <option value="Supports">Supports (Soporta a)</option>
                            <option value="Owned by">Owned by (Propiedad de)</option>
                            <option value="Used by">Used by (Usado por)</option>
                        </optgroup>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Impacto si Destino falla <span class="text-danger">*</span></label>
                    <select id="rel_impact" class="form-control">
                        <option value="Sí">Sí (Fallo total)</option>
                        <option value="Parcial">Parcial (Degradación)</option>
                        <option value="No">No (Independiente)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="addRelation()">Añadir</button>
            </div>
        </div>
    </div>
</div>

<script>
let pendingRelations = [];

function openRelationModal() {
    $('#rel_target_id').html('<option value="">Seleccione Categoría Primero...</option>').prop('disabled', true);
    
    // Fill Level 1
    let sel = $('#rel_cat_1');
    sel.html('<option value="">-- Nivel 1 --</option>');
    let l1 = categories.filter(c => !c.parent_id);
    l1.forEach(c => sel.append(`<option value="${c.id}">${c.name}</option>`));
    
    $('#rel_cat_2').html('<option value="">-- Nivel 2 --</option>').prop('disabled', true);
    $('#rel_cat_3').html('<option value="">-- Nivel 3 --</option>').prop('disabled', true);
    
    $('#relationModal').modal('show');
}

$('#rel_cat_1').change(function() {
    let val = $(this).val();
    $('#rel_cat_2').html('<option value="">-- Nivel 2 --</option>').prop('disabled', true);
    $('#rel_cat_3').html('<option value="">-- Nivel 3 --</option>').prop('disabled', true);
    if (val) {
        let children = categories.filter(c => c.parent_id == val);
        if (children.length > 0) {
            let sel = $('#rel_cat_2');
            children.forEach(c => sel.append(`<option value="${c.id}">${c.name}</option>`));
            sel.prop('disabled', false);
        }
        fetchCIsForCategory(val);
    }
});

$('#rel_cat_2').change(function() {
    let val = $(this).val();
    $('#rel_cat_3').html('<option value="">-- Nivel 3 --</option>').prop('disabled', true);
    if (val) {
        let children = categories.filter(c => c.parent_id == val);
        if (children.length > 0) {
            let sel = $('#rel_cat_3');
            children.forEach(c => sel.append(`<option value="${c.id}">${c.name}</option>`));
            sel.prop('disabled', false);
        }
        fetchCIsForCategory(val);
    } else {
        fetchCIsForCategory($('#rel_cat_1').val());
    }
});

$('#rel_cat_3').change(function() {
    let val = $(this).val();
    if (val) {
        fetchCIsForCategory(val);
    } else {
        fetchCIsForCategory($('#rel_cat_2').val());
    }
});

function fetchCIsForCategory(catId) {
    let sel = $('#rel_target_id');
    sel.html('<option>Cargando...</option>').prop('disabled', true);
    $.get(`api_ci.php?action=get_ci_by_category&category_id=${catId}`, function(res) {
        if (res.success) {
            sel.html('<option value="">-- Seleccione CI --</option>');
            res.data.forEach(ci => sel.append(`<option value="${ci.id}">${ci.hostname} (${ci.ip_address || 'Sin IP'})</option>`));
            sel.prop('disabled', false);
        }
    }, 'json');
}

function addRelation() {
    let targetId = $('#rel_target_id').val();
    let targetName = $('#rel_target_id option:selected').text();
    let type = $('#rel_type').val();
    let impact = $('#rel_impact').val();
    
    if (!targetId) {
        Swal.fire('Atención', 'Debe seleccionar un CI destino', 'warning');
        return;
    }
    
    // Check duplicate
    if (pendingRelations.find(r => r.target_id == targetId && r.type == type)) {
        Swal.fire('Atención', 'Ya existe esta relación', 'warning');
        return;
    }
    
    pendingRelations.push({
        target_id: targetId,
        target_name: targetName,
        type: type,
        impact: impact
    });
    
    updateRelationsUI();
    $('#relationModal').modal('hide');
}

function removeRelation(index) {
    pendingRelations.splice(index, 1);
    updateRelationsUI();
}

function updateRelationsUI() {
    $('#ci_relations_input').val(JSON.stringify(pendingRelations));
    let tbody = $('#relations-table tbody');
    tbody.empty();
    
    if (pendingRelations.length === 0) {
        tbody.append('<tr><td colspan="4" class="text-center text-muted small">Sin relaciones.</td></tr>');
        return;
    }
    
    pendingRelations.forEach((rel, idx) => {
        let impBadge = rel.impact == 'Sí' ? 'danger' : (rel.impact == 'Parcial' ? 'warning' : 'info');
        tbody.append(`
            <tr>
                <td class="font-weight-bold text-primary">${rel.type}</td>
                <td><i class="fas fa-server text-muted mr-1"></i> ${rel.target_name}</td>
                <td><span class="badge badge-${impBadge}">${rel.impact}</span></td>
                <td><button type="button" class="btn btn-xs btn-danger" onclick="removeRelation(${idx})"><i class="fas fa-times"></i></button></td>
            </tr>
        `);
    });
}
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
