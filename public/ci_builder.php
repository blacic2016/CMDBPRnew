<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

require_login();

$page_title = 'Asistente de Creación de CI (Graph-Based)';
$edit_ci = null;
$edit_relations = [];

if (!empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM ci_instances WHERE id = ?");
    $stmt->execute([$id]);
    $edit_ci = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($edit_ci) {
        $page_title = 'Editar CI: ' . htmlspecialchars($edit_ci['hostname']);
        
        $stmtRel = $pdo->prepare("SELECT r.*, c.hostname as target_name FROM ci_relationships r JOIN ci_instances c ON r.target_id = c.id WHERE r.source_type='ci_instance' AND r.source_id=?");
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
    }
}

require_once __DIR__ . '/partials/header.php';
?>

<div class="container-fluid pt-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card card-outline card-primary shadow-lg">
                <div class="card-header border-bottom-0">
                    <h3 class="card-title"><i class="fas fa-magic mr-2"></i> Creación Dinámica de CI</h3>
                </div>
                <div class="card-body p-4">
                    <!-- Step 1: Seleccionar Categoría -->
                    <div id="step-1" class="mb-4">
                        <h4 class="text-primary mb-3">1. Seleccione el Tipo de Equipo (Jerarquía)</h4>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Nivel 1 (Grupo)</label>
                                <select id="cat_level_1" class="form-control form-control-lg border-primary">
                                    <option value="">Cargando...</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Nivel 2 (Clase)</label>
                                <select id="cat_level_2" class="form-control form-control-lg border-primary" disabled>
                                    <option value="">Seleccione Nivel 1...</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Nivel 3 (Subclase)</label>
                                <select id="cat_level_3" class="form-control form-control-lg border-primary" disabled>
                                    <option value="">Seleccione Nivel 2...</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Formulario Dinámico -->
                    <div id="step-2" class="d-none animate__animated animate__fadeIn">
                        <hr class="mb-4">
                        <h4 class="text-primary mb-3">2. Ingrese los Detalles del CI</h4>
                        
                        <form id="ci-form">
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

                            <!-- Atributos Estándar (Base) ocultos inicialmente para moverlos a la pestaña General -->
                            <div class="row" id="base-attributes-container" style="display: none;">
                                <div class="col-md-6 form-group">
                                    <label>Nombre del CI / Hostname <span class="text-danger">*</span></label>
                                    <input type="text" name="hostname" class="form-control" required placeholder="Ej. SRV-DB-01">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>Estado</label>
                                    <select name="status" id="f_status" class="form-control">
                                        <option value="Activo">Activo</option>
                                        <option value="Pasivo">Pasivo</option>
                                        <option value="Planificación">Planificación</option>
                                        <option value="Mantenimiento">Mantenimiento</option>
                                        <option value="Retirado">Retirado</option>
                                    </select>
                                </div>
                            </div>
                            
                            <input type="hidden" name="zabbix_host_id" id="f_zabbix_id">

                            <!-- Dynamic Fields Container (Tabs) -->
                            <div id="dynamic-fields" class="mt-3">
                                <!-- Rendered via JS -->
                            </div>
                            
                            <div class="form-group text-right mt-4 pt-3 border-top">
                                <input type="hidden" name="ci_relations" id="ci_relations_input" value="[]">
                                <button type="submit" class="btn btn-success btn-lg px-5 shadow"><i class="fas fa-save mr-2"></i> Crear CI</button>
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

$(document).ready(function() {
    loadCategories();

    $('#cat_level_1').change(function() {
        let val = $(this).val();
        $('#cat_level_2').html('<option value="">-- Seleccione Nivel 2 --</option>').prop('disabled', true);
        $('#cat_level_3').html('<option value="">-- Seleccione Nivel 3 --</option>').prop('disabled', true);
        
        handleCategorySelect(val);

        if (val) {
            let children = categories.filter(c => c.parent_id == val);
            if (children.length > 0) {
                let sel2 = $('#cat_level_2');
                children.forEach(c => sel2.append(`<option value="${c.id}">${c.name}</option>`));
                sel2.prop('disabled', false);
            }
        }
    });

    $('#cat_level_2').change(function() {
        let val = $(this).val();
        $('#cat_level_3').html('<option value="">-- Seleccione Nivel 3 --</option>').prop('disabled', true);
        
        let activeId = val ? val : $('#cat_level_1').val();
        handleCategorySelect(activeId);

        if (val) {
            let children = categories.filter(c => c.parent_id == val);
            if (children.length > 0) {
                let sel3 = $('#cat_level_3');
                children.forEach(c => sel3.append(`<option value="${c.id}">${c.name}</option>`));
                sel3.prop('disabled', false);
            }
        }
    });

    $('#cat_level_3').change(function() {
        let val = $(this).val();
        let activeId = val ? val : $('#cat_level_2').val();
        handleCategorySelect(activeId);
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
        $('#f_hostname').val(sel.data('name'));
        $('#f_ip').val(sel.data('ip'));
        $('#f_zabbix_id').val(sel.val());
        toastr.success('Datos cargados de Zabbix');
    });

    $('#ci-form').submit(function(e) {
        e.preventDefault();
        $.post('api_ci.php', $(this).serialize(), function(res) {
            if (res.success) {
                Swal.fire('Éxito', res.message, 'success').then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    });
});

function loadCategories() {
    $.get('api_ci.php?action=get_categories', function(res) {
        if (res.success) {
            categories = res.data;
            let sel = $('#cat_level_1');
            sel.html('<option value="">-- Seleccione Nivel 1 --</option>');
            let l1 = categories.filter(c => !c.parent_id);
            l1.forEach(c => {
                sel.append(`<option value="${c.id}">${c.name}</option>`);
            });
            
            if (editData) {
                preloadEditData();
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
    
    if (editData.source === 'zabbix') {
        $('#source_zabbix').trigger('click');
        $('#f_zabbix_id').val(editData.zabbix_host_id);
    }
    
    if (editRelationsData && editRelationsData.length > 0) {
        pendingRelations = editRelationsData;
        updateRelationsUI();
    }
    
    // Reverse lookup the category lineage to populate the dropdowns
    let lineage = getCategoryLineage(editData.category_id);
    if (lineage.length > 0) {
        $('#cat_level_1').val(lineage[0].id).trigger('change');
        setTimeout(() => {
            if (lineage[1]) {
                $('#cat_level_2').val(lineage[1].id).trigger('change');
                setTimeout(() => {
                    if (lineage[2]) {
                        $('#cat_level_3').val(lineage[2].id).trigger('change');
                    }
                    fillDynamicData();
                }, 100);
            } else {
                fillDynamicData();
            }
        }, 100);
    }
}

function fillDynamicData() {
    let attrs = {};
    try { attrs = JSON.parse(editData.attributes_json); } catch(e) {}
    for (let key in attrs) {
        let el = $(`[name="${key}"]`);
        if (el.length) el.val(attrs[key]);
    }
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
    buildDynamicForm(lineage);
    $('#step-2').removeClass('d-none');
}

function buildDynamicForm(lineage) {
    let container = $('#dynamic-fields');
    
    // Rescatar atributos base si ya estaban en una pestaña
    if ($('#general-base-placeholder').length) {
        $('#general-base-placeholder').contents().appendTo('#base-attributes-container');
    }
    
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

    if (Object.keys(allProperties).length === 0) {
        container.append('<div class="col-12"><p class="text-muted">No hay atributos específicos definidos en el JSON Schema para esta clase ni sus padres.</p></div>');
        return;
    }

    // Group properties by their 'group' attribute
    let groups = {};
    for (let key in allProperties) {
        let prop = allProperties[key];
        let groupName = prop.group || 'General';
        if (!groups[groupName]) groups[groupName] = {};
        groups[groupName][key] = prop;
    }
    
    // Asegurarnos de que el grupo General exista para poner los atributos base
    if (!groups['General']) {
        groups['General'] = {};
    }

    // Render by group using Bootstrap Tabs
    let tabsHtml = '<ul class="nav nav-tabs w-100 mb-3" id="ciTabs" role="tablist">';
    let contentHtml = '<div class="tab-content w-100" id="ciTabsContent">';

    let groupKeys = Object.keys(groups).sort();
    groupKeys.forEach((groupName, index) => {
        let safeId = groupName.replace(/\s+/g, '-').toLowerCase() + '-' + index;
        let activeClass = index === 0 ? 'active' : '';
        let ariaSelected = index === 0 ? 'true' : 'false';

        tabsHtml += `
            <li class="nav-item">
                <a class="nav-link font-weight-bold ${activeClass}" id="tab-${safeId}" data-toggle="tab" href="#content-${safeId}" role="tab" aria-controls="content-${safeId}" aria-selected="${ariaSelected}">
                    <i class="fas fa-layer-group mr-1"></i> ${groupName}
                </a>
            </li>`;

        contentHtml += `<div class="tab-pane fade show ${activeClass}" id="content-${safeId}" role="tabpanel" aria-labelledby="tab-${safeId}">`;
        
        if (groupName === 'General') {
            contentHtml += `<div id="general-base-placeholder"></div><hr>`;
        }
        
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
                inputHtml = `<select name="${key}" class="form-control" ${isRequired ? 'required' : ''}>`;
                inputHtml += `<option value="">Seleccionar...</option>`;
                prop.enum.forEach(val => {
                    inputHtml += `<option value="${val}">${val}</option>`;
                });
                inputHtml += `</select>`;
            } else if (prop.type === 'boolean') {
                inputHtml = `<select name="${key}" class="form-control" ${isRequired ? 'required' : ''}>
                    <option value="1">Sí</option><option value="0">No</option>
                </select>`;
            } else if (prop.type === 'integer' || prop.type === 'number') {
                inputHtml = `<input type="number" name="${key}" class="form-control" ${isRequired ? 'required' : ''}>`;
            } else if (prop.type === 'textarea') {
                inputHtml = `<textarea name="${key}" class="form-control" rows="2" ${isRequired ? 'required' : ''}></textarea>`;
            } else if (prop.type === 'date' || prop.format === 'date') {
                inputHtml = `<input type="date" name="${key}" class="form-control" ${isRequired ? 'required' : ''}>`;
            } else {
                inputHtml = `<input type="text" name="${key}" class="form-control" ${isRequired ? 'required' : ''}>`;
            }

            contentHtml += `
                <div class="col-md-6 form-group">
                    <label>${label} ${reqMark}</label>
                    ${inputHtml}
                </div>
            `;
        }
        contentHtml += `</div></div>`;
    });

    tabsHtml += '</ul>';
    contentHtml += '</div>';

    container.append(tabsHtml);
    container.append(contentHtml);
    
    // Mover atributos base a la pestaña General
    if ($('#general-base-placeholder').length) {
        $('#base-attributes-container').contents().appendTo('#general-base-placeholder');
    }
    
    // Refresh UI specifically for edit if needed
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
