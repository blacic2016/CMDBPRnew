<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

require_login();
if (!has_role('SUPER_ADMIN')) {
    die("Acceso denegado. Se requiere rol SUPER_ADMIN.");
}

$page_title = 'Gestor de Categorías de CI';
require_once __DIR__ . '/partials/header.php';
?>

<div class="container-fluid pt-4">
    <div class="row">
        <!-- Listado de Categorías -->
        <div class="col-md-5">
            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title"><i class="fas fa-sitemap mr-2"></i> Grupos y Clases</h3>
                    <button class="btn btn-sm btn-success" onclick="openCreateForm()"><i class="fas fa-plus"></i> Nueva Categoría</button>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" id="category-list">
                        <!-- Llenado vía JS -->
                        <div class="list-group-item text-center text-muted py-4"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulario de Edición -->
        <div class="col-md-7">
            <div class="card card-outline card-warning shadow-sm" id="form-card" style="display:none;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title" id="form-title">Crear Categoría</h3>
                    <button class="btn btn-sm btn-danger d-none" id="btn-delete" onclick="deleteCategory()"><i class="fas fa-trash"></i> Eliminar</button>
                </div>
                <div class="card-body">
                    <form id="category-form">
                        <input type="hidden" name="action" value="save_category">
                        <input type="hidden" name="id" id="cat_id" value="0">
                        
                        <div class="form-group">
                            <label>Nombre de la Clase/Grupo <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="cat_name" class="form-control" required placeholder="Ej. Router">
                        </div>
                        
                        <div class="form-group">
                            <label>Descripción / Función (Opcional)</label>
                            <textarea name="description" id="cat_description" class="form-control" rows="2" placeholder="Detalle qué tipo de activos agrupa esta clase..."></textarea>
                        </div>

                        <div class="form-group">
                            <label>Categoría Padre (Opcional)</label>
                            <select name="parent_id" id="cat_parent_id" class="form-control">
                                <option value="">-- Ninguna (Nivel 1) --</option>
                            </select>
                            <small class="form-text text-muted">Para crear jerarquías como Hardware > Redes > Router</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Icono de la Clase (FontAwesome)</label>
                            <div class="input-group mb-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text" id="icon-preview"><i class="fas fa-cube"></i></span>
                                </div>
                                <input type="text" name="icon" id="cat_icon" class="form-control" value="fa-cube" placeholder="ej: fa-server, fa-network-wired">
                            </div>
                            <div class="mt-2" style="max-height: 120px; overflow-y: auto;">
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-server"><i class="fas fa-server"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-network-wired"><i class="fas fa-network-wired"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-desktop"><i class="fas fa-desktop"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-laptop"><i class="fas fa-laptop"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-database"><i class="fas fa-database"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-wifi"><i class="fas fa-wifi"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-microchip"><i class="fas fa-microchip"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-hdd"><i class="fas fa-hdd"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-plug"><i class="fas fa-plug"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-cubes"><i class="fas fa-cubes"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-sitemap"><i class="fas fa-sitemap"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-building"><i class="fas fa-building"></i></button>
                                <!-- More Icons -->
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-cloud"><i class="fas fa-cloud"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-mobile-alt"><i class="fas fa-mobile-alt"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-tablet-alt"><i class="fas fa-tablet-alt"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-print"><i class="fas fa-print"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-shield-alt"><i class="fas fa-shield-alt"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-memory"><i class="fas fa-memory"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-keyboard"><i class="fas fa-keyboard"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-mouse"><i class="fas fa-mouse"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-tv"><i class="fas fa-tv"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-project-diagram"><i class="fas fa-project-diagram"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-route"><i class="fas fa-route"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-satellite-dish"><i class="fas fa-satellite-dish"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-broadcast-tower"><i class="fas fa-broadcast-tower"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-ethernet"><i class="fas fa-ethernet"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-router"><i class="fas fa-router"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-battery-full"><i class="fas fa-battery-full"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-battery-half"><i class="fas fa-battery-half"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-fan"><i class="fas fa-fan"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-power-off"><i class="fas fa-power-off"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-temperature-low"><i class="fas fa-temperature-low"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-box"><i class="fas fa-box"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-boxes"><i class="fas fa-boxes"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-layer-group"><i class="fas fa-layer-group"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-cogs"><i class="fas fa-cogs"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-wrench"><i class="fas fa-wrench"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-tools"><i class="fas fa-tools"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-phone"><i class="fas fa-phone"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-headset"><i class="fas fa-headset"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-video"><i class="fas fa-video"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-camera"><i class="fas fa-camera"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-barcode"><i class="fas fa-barcode"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-qrcode"><i class="fas fa-qrcode"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-id-card"><i class="fas fa-id-card"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-key"><i class="fas fa-key"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-user-shield"><i class="fas fa-user-shield"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-fire-extinguisher"><i class="fas fa-fire-extinguisher"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-car"><i class="fas fa-car"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-truck"><i class="fas fa-truck"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-plane"><i class="fas fa-plane"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-ship"><i class="fas fa-ship"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-charging-station"><i class="fas fa-charging-station"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-sd-card"><i class="fas fa-sd-card"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-compact-disc"><i class="fas fa-compact-disc"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-save"><i class="fas fa-save"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-tachometer-alt"><i class="fas fa-tachometer-alt"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-globe"><i class="fas fa-globe"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary icon-btn m-1" data-icon="fa-link"><i class="fas fa-link"></i></button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Constructor Visual de Atributos</label>
                            <div id="schema-builder" class="border p-3 bg-white rounded shadow-sm">
                                <table class="table table-sm table-borderless" id="schema-table">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Atributo</th>
                                            <th>Grupo</th>
                                            <th>Tipo</th>
                                            <th class="text-center">Req.</th>
                                            <th>Descripción</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Js rendered rows -->
                                    </tbody>
                                </table>
                                <div class="d-flex justify-content-between mt-3">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addAttributeRow()"><i class="fas fa-plus"></i> Atributo Específico</button>
                                    
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" id="dropdownGlobalAttrs" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-globe"></i> Añadir Global
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownGlobalAttrs" id="global-attrs-menu" style="max-height: 200px; overflow-y: auto;">
                                            <!-- JS populated -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Campo oculto para compatibilidad con backend -->
                            <textarea name="schema_json" id="cat_schema" class="d-none">{}</textarea>
                        </div>
                        
                        <div class="text-right">
                            <button type="button" class="btn btn-secondary mr-2" onclick="closeForm()">Cancelar</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div id="welcome-msg" class="text-center py-5 text-muted">
                <i class="fas fa-project-diagram fa-4x mb-3 opacity-2"></i>
                <h4>Gestión de Clases CMDB</h4>
                <p>Selecciona una categoría de la izquierda para editar su esquema o crea una nueva.</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let categories = [];
let globalAttributes = [];

$(document).ready(function() {
    loadCategories();
    loadGlobalAttributes();

    $('.icon-btn').click(function() {
        let icon = $(this).data('icon');
        $('#cat_icon').val(icon);
        $('#icon-preview').html(`<i class="fas ${icon}"></i>`);
    });
    $('#cat_icon').on('input', function() {
        $('#icon-preview').html(`<i class="fas ${$(this).val()}"></i>`);
    });

    $('#category-form').submit(function(e) {
        e.preventDefault();
        
        buildJsonFromUI();
        
        // Validar JSON antes de enviar
        try {
            JSON.parse($('#cat_schema').val());
        } catch(e) {
            Swal.fire('JSON Inválido', 'El formato del JSON Schema no es correcto: ' + e.message, 'error');
            return;
        }

        $.post('api_ci.php', $(this).serialize(), function(res) {
            if (res.success) {
                Swal.fire('Éxito', res.message, 'success');
                loadCategories();
                closeForm();
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
            renderCategoryList();
            updateParentSelect();
        }
    }, 'json');
}

function renderCategoryList() {
    let list = $('#category-list');
    list.empty();
    
    if (categories.length === 0) {
        list.append('<li class="list-group-item text-muted">No hay categorías creadas.</li>');
        return;
    }

    let map = {};
    categories.forEach(c => {
        map[c.id] = {...c, children: []};
    });
    
    let tree = [];
    categories.forEach(c => {
        if (c.parent_id && map[c.parent_id]) {
            map[c.parent_id].children.push(map[c.id]);
        } else {
            tree.push(map[c.id]);
        }
    });

    function buildHtml(nodes, level) {
        if (!nodes || nodes.length === 0) return '';
        let h = '';
        let collapseClass = level > 0 ? 'collapse' : '';
        if (level > 0) h += `<div class="pl-3 border-left ml-2 mt-1 ${collapseClass}">`;
        nodes.forEach(node => {
            let hasChildren = node.children.length > 0;
            let icon = node.icon || (hasChildren ? 'fa-folder' : 'fa-cube');
            if (icon.indexOf('fa-') === -1) icon = 'fa-' + icon;
            
            let bg = level === 0 ? 'bg-light font-weight-bold' : '';
            let border = level > 0 ? 'border-left: 3px solid var(--sonda-cyan);' : '';
            
            let toggleBtn = hasChildren ? `<button class="btn btn-sm btn-link text-dark p-0 mr-2 toggle-btn" onclick="toggleNode(this, event)"><i class="fas fa-chevron-right"></i></button>` : `<span class="mr-3 ml-2"></span>`;
            
            let dateStr = node.created_at ? node.created_at.split(' ')[0] : '';
            let creator = node.creator_name || 'Sistema';
            let desc = node.description ? `<small class="text-muted d-block mt-1">${node.description}</small>` : '';
            
            h += `
            <div class="mb-1 node-container">
                <div class="list-group-item list-group-item-action ${bg} rounded" style="cursor:pointer; ${border}" onclick="openEditForm(${node.id}); event.stopPropagation();">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span>${toggleBtn} <i class="fas ${icon} text-primary mr-2"></i> ${node.name}</span>
                            ${desc}
                        </div>
                        <div class="text-right">
                            <small class="text-muted d-block" title="Creado el ${dateStr}"><i class="far fa-calendar-alt"></i> ${dateStr}</small>
                            <small class="text-muted" title="Creado por"><i class="far fa-user"></i> ${creator}</small>
                        </div>
                    </div>
                </div>
                ${hasChildren ? buildHtml(node.children, level + 1) : ''}
            </div>
            `;
        });
        if (level > 0) h += `</div>`;
        return h;
    }

    list.append(buildHtml(tree, 0));
}

function toggleNode(btn, event) {
    event.stopPropagation();
    let $btn = $(btn);
    let $icon = $btn.find('i');
    let $childrenContainer = $btn.closest('.node-container').children('.collapse');
    
    if ($childrenContainer.is(':visible')) {
        $childrenContainer.slideUp();
        $icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
    } else {
        $childrenContainer.slideDown();
        $icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
    }
}

function updateParentSelect() {
    let sel = $('#cat_parent_id');
    sel.html('<option value="">-- Ninguna (Nivel 1) --</option>');
    
    let map = {};
    categories.forEach(c => {
        map[c.id] = {...c, children: []};
    });
    let tree = [];
    categories.forEach(c => {
        if (c.parent_id && map[c.parent_id]) {
            map[c.parent_id].children.push(map[c.id]);
        } else {
            tree.push(map[c.id]);
        }
    });

    function addOptions(nodes, level) {
        let prefix = '-'.repeat(level * 2);
        if (prefix) prefix += ' ';
        nodes.forEach(n => {
            sel.append(`<option value="${n.id}">${prefix}${n.name}</option>`);
            if (n.children && n.children.length > 0) {
                addOptions(n.children, level + 1);
            }
        });
    }
    
    addOptions(tree, 0);
}

function openCreateForm() {
    $('#form-card').show();
    $('#welcome-msg').hide();
    $('#btn-delete').addClass('d-none');
    $('#form-title').text('Nueva Categoría');
    
    $('#cat_id').val(0);
    $('#cat_name').val('');
    $('#cat_description').val('');
    $('#cat_parent_id').val('');
    $('#cat_icon').val('fa-cube');
    $('#icon-preview').html('<i class="fas fa-cube"></i>');
    $('#cat_schema').val('{\n  "type": "object",\n  "properties": {\n    \n  }\n}');
    parseJsonToUI('{\n  "type": "object",\n  "properties": {\n    \n  }\n}');
}

function openEditForm(id) {
    let cat = categories.find(c => c.id == id);
    if (!cat) return;
    
    $('#form-card').show();
    $('#welcome-msg').hide();
    $('#btn-delete').removeClass('d-none');
    $('#form-title').text('Editar: ' + cat.name);
    
    $('#cat_id').val(cat.id);
    $('#cat_name').val(cat.name);
    $('#cat_description').val(cat.description || '');
    $('#cat_parent_id').val(cat.parent_id || '');
    let icon = cat.icon || 'fa-cube';
    $('#cat_icon').val(icon);
    $('#icon-preview').html(`<i class="fas ${icon}"></i>`);
    
    // Parse and stringify for pretty print
    try {
        let schemaObj = typeof cat.schema_json === 'string' ? JSON.parse(cat.schema_json) : cat.schema_json;
        $('#cat_schema').val(JSON.stringify(schemaObj, null, 2));
        parseJsonToUI(schemaObj);
        
        // Load inherited
        let currentId = cat.parent_id;
        while(currentId) {
            let pCat = categories.find(c => c.id == currentId);
            if (pCat) {
                let pSchema = typeof pCat.schema_json === 'string' ? JSON.parse(pCat.schema_json) : pCat.schema_json;
                parseJsonToUI(pSchema, true, pCat.name);
                currentId = pCat.parent_id;
            } else {
                break;
            }
        }
    } catch(e) {
        $('#cat_schema').val(cat.schema_json);
        parseJsonToUI(cat.schema_json);
    }
}

function closeForm() {
    $('#form-card').hide();
    $('#welcome-msg').show();
}

function addAttributeRow(name = '', type = 'string', req = false, desc = '', isGlobal = false, groupName = 'General', isInherited = false, parentName = '') {
    let tbody = $('#schema-table tbody');
    let inputReadonly = (isGlobal || isInherited) ? 'readonly' : '';
    let bgColor = isInherited ? 'bg-secondary text-white' : (isGlobal ? 'bg-light' : '');
    let globalIcon = isGlobal && !isInherited ? '<i class="fas fa-globe text-success" title="Atributo Global"></i> ' : '';
    let inheritIcon = isInherited ? `<span class="badge badge-warning mr-1" title="Heredado de ${parentName}"><i class="fas fa-level-up-alt"></i> ${parentName}</span> ` : '';
    
    let tr = `
        <tr class="attr-row ${isInherited ? 'inherited-attr' : ''} ${bgColor}">
            <td>${inheritIcon}${globalIcon}<input type="text" class="form-control form-control-sm attr-name" value="${name}" placeholder="ej: ram" required ${inputReadonly}></td>
            <td>
                <select class="form-control form-control-sm attr-group" required ${inputReadonly?'disabled':''}>
                    <option value="General" ${groupName=='General'?'selected':''}>General</option>
                    <option value="Monitoreo" ${groupName=='Monitoreo'?'selected':''}>Monitoreo</option>
                    <option value="Comunicaciones" ${groupName=='Comunicaciones'?'selected':''}>Comunicaciones</option>
                    <option value="Dependencias y Relaciones" ${groupName=='Dependencias y Relaciones'?'selected':''}>Dependencias y Relaciones</option>
                    <option value="Propiedad" ${groupName=='Propiedad'?'selected':''}>Propiedad</option>
                    <option value="Version" ${groupName=='Version'?'selected':''}>Version</option>
                    <option value="Ubicación" ${groupName=='Ubicación'?'selected':''}>Ubicación</option>
                </select>
                ${inputReadonly ? `<input type="hidden" class="attr-group" value="${groupName}">` : ''}
            </td>
            <td>
                <select class="form-control form-control-sm attr-type" ${inputReadonly?'disabled':''}>
                    <option value="string" ${type=='string'?'selected':''}>Texto Corto</option>
                    <option value="textarea" ${type=='textarea'?'selected':''}>Texto Largo</option>
                    <option value="number" ${type=='number'?'selected':''}>Número</option>
                    <option value="boolean" ${type=='boolean'?'selected':''}>Booleano</option>
                    <option value="date" ${type=='date'?'selected':''}>Fecha</option>
                </select>
                ${inputReadonly ? `<input type="hidden" class="attr-type-hidden" value="${type}">` : ''}
            </td>
            <td class="text-center align-middle">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input attr-req" id="req_${Date.now()}_${name}" ${req?'checked':''} ${(isGlobal||isInherited)?'disabled':''}>
                    <label class="custom-control-label" for="req_${Date.now()}_${name}"></label>
                </div>
            </td>
            <td><input type="text" class="form-control form-control-sm attr-desc" value="${desc}" placeholder="Descripción opcional" ${inputReadonly}></td>
            <td>
                ${isInherited ? 
                    '<i class="fas fa-lock text-white" title="No se puede eliminar porque es heredado"></i>' : 
                    '<button type="button" class="btn btn-sm btn-outline-danger" onclick="$(this).closest(\'tr\').remove()"><i class="fas fa-times"></i></button>'
                }
            </td>
        </tr>
    `;
    tbody.append(tr);
}

function loadGlobalAttributes() {
    $.get('api_ci.php?action=get_attributes', function(res) {
        if (res.success) {
            globalAttributes = res.data;
            let menu = $('#global-attrs-menu');
            menu.empty();
            if (globalAttributes.length === 0) {
                menu.append('<span class="dropdown-item text-muted">No hay atributos globales</span>');
            } else {
                globalAttributes.forEach(attr => {
                    let btn = $(`<button class="dropdown-item" type="button"><i class="fas fa-tag text-primary mr-1"></i> ${attr.name}</button>`);
                    btn.click(function() {
                        let isReq = attr.is_required == 1;
                        addAttributeRow(attr.name, attr.type, isReq, attr.description || '', true, attr.group_name || 'General');
                    });
                    menu.append(btn);
                });
            }
        }
    }, 'json');
}

function buildJsonFromUI() {
    let schema = {
        type: "object",
        properties: {},
        required: []
    };
    
    $('#schema-table tbody tr:not(.inherited-attr)').each(function() {
        let name = $(this).find('.attr-name').val().trim();
        let type = $(this).find('.attr-type-hidden').length ? $(this).find('.attr-type-hidden').val() : $(this).find('.attr-type').val();
        let groupName = $(this).find('.attr-group').val().trim() || 'General';
        let req = $(this).find('.attr-req').is(':checked');
        let desc = $(this).find('.attr-desc').val().trim();
        
        if (name) {
            name = name.toLowerCase().replace(/[^a-z0-9_]/g, '_');
            schema.properties[name] = { type: type, group: groupName };
            if (desc) schema.properties[name].description = desc;
            if (req) schema.required.push(name);
        }
    });
    
    if (schema.required.length === 0) {
        delete schema.required;
    }
    
    $('#cat_schema').val(JSON.stringify(schema, null, 2));
}

function parseJsonToUI(jsonStr, isInherited = false, parentName = '') {
    if (!isInherited) {
        $('#schema-table tbody').empty();
    }
    try {
        let schema = typeof jsonStr === 'string' ? JSON.parse(jsonStr) : jsonStr;
        if (schema && schema.properties) {
            let requiredFields = schema.required || [];
            
            for (let key in schema.properties) {
                let prop = schema.properties[key];
                let isReq = requiredFields.includes(key);
                let type = prop.format === 'date' ? 'date' : 
                           (prop.type === 'string' && prop.maxLength && prop.maxLength > 255) ? 'textarea' : 
                           prop.type;
                if (!type) type = 'string';
                
                let groupName = prop.group || 'General';
                
                // Check if it's a global attribute to render it readonly
                let isGlobal = globalAttributes.find(a => a.name === key);
                if (isGlobal) groupName = isGlobal.group_name || 'General';
                
                addAttributeRow(key, type, isReq, prop.description || '', !!isGlobal, groupName, isInherited, parentName);
            }
        }
    } catch(e) {}
}

function deleteCategory() {
    let id = $('#cat_id').val();
    Swal.fire({
        title: '¿Eliminar Categoría?',
        text: 'Esto eliminará la estructura (si no hay CIs enlazados).',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('api_ci.php', {action: 'delete_category', id: id}, function(res) {
                if (res.success) {
                    Swal.fire('Eliminada', res.message, 'success');
                    loadCategories();
                    closeForm();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json');
        }
    });
}
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
