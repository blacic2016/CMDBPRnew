<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

require_login();
if (!has_role('SUPER_ADMIN')) {
    die("Acceso denegado. Se requiere rol SUPER_ADMIN.");
}

$page_title = 'Atributos Globales';
require_once __DIR__ . '/partials/header.php';
?>

<div class="container-fluid pt-4">
    <div class="row">
        <!-- Listado de Atributos -->
        <div class="col-md-7">
            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title"><i class="fas fa-tags mr-2"></i> Diccionario de Atributos</h3>
                    <button class="btn btn-sm btn-success" onclick="openCreateForm()"><i class="fas fa-plus"></i> Nuevo Atributo</button>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover table-striped m-0" id="attributes-table">
                        <thead class="bg-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Grupo</th>
                                <th>Tipo</th>
                                <th class="text-center">Obligatorio (Defecto)</th>
                                <th>Descripción</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Llenado vía JS -->
                            <tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin"></i> Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Formulario de Edición -->
        <div class="col-md-5">
            <div class="card card-outline card-warning shadow-sm" id="form-card" style="display:none;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title" id="form-title">Crear Atributo</h3>
                    <button class="btn btn-sm btn-danger d-none" id="btn-delete" onclick="deleteAttribute()"><i class="fas fa-trash"></i> Eliminar</button>
                </div>
                <div class="card-body">
                    <form id="attribute-form">
                        <input type="hidden" name="action" value="save_attribute">
                        <input type="hidden" name="id" id="attr_id" value="0">
                        
                        <div class="form-group">
                            <label>Nombre del Atributo <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="attr_name" class="form-control" required placeholder="Ej. mac_address" pattern="[a-zA-Z0-9_]+">
                            <small class="form-text text-muted">Use solo letras minúsculas, números y guiones bajos (_).</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Grupo / Categoría <span class="text-danger">*</span></label>
                            <select name="group_name" id="attr_group_name" class="form-control" required>
                                <option value="General">General</option>
                                <option value="Monitoreo">Monitoreo</option>
                                <option value="Comunicaciones">Comunicaciones</option>
                                <option value="Dependencias y Relaciones">Dependencias y Relaciones</option>
                                <option value="Propiedad">Propiedad</option>
                                <option value="Version">Version</option>
                                <option value="Ubicación">Ubicación</option>
                                <option value="Imagen">Imagen</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Tipo de Dato <span class="text-danger">*</span></label>
                            <select name="type" id="attr_type" class="form-control" required>
                                <option value="string">Texto Corto (String)</option>
                                <option value="textarea">Texto Largo (Textarea)</option>
                                <option value="number">Número (Integer/Float)</option>
                                <option value="boolean">Booleano (Sí/No)</option>
                                <option value="date">Fecha</option>
                                <option value="multiselect">Lista de Opciones (Select)</option>
                                <option value="image">Imagen / Archivo (Upload)</option>
                            </select>
                        </div>

                        <div class="form-group" id="group_multiselect_values" style="display: none;">
                            <label>Opciones del Selector <span class="text-danger">*</span></label>
                            <textarea name="multiselect_values" id="attr_multiselect_values" class="form-control" rows="2" placeholder="ej: Opción 1, Opción 2, Opción 3"></textarea>
                            <small class="form-text text-muted">Ingrese las opciones de la lista separadas por comas (,).</small>
                        </div>
                        
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" name="is_required" id="attr_is_required" value="1">
                                <label class="custom-control-label" for="attr_is_required">Obligatorio por defecto</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Descripción / Ayuda (Opcional)</label>
                            <textarea name="description" id="attr_description" class="form-control" rows="2" placeholder="Detalle para qué sirve este atributo..."></textarea>
                        </div>
                        
                        <div class="text-right">
                            <button type="button" class="btn btn-secondary mr-2" onclick="closeForm()">Cancelar</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div id="welcome-msg" class="text-center py-5 text-muted">
                <i class="fas fa-book fa-4x mb-3 opacity-2"></i>
                <h4>Diccionario de Datos</h4>
                <p>Selecciona un atributo para editarlo o crea uno nuevo para usarlo globalmente.</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let attributes = [];

$(document).ready(function() {
    loadAttributes();

    $('#attr_type').change(function() {
        if ($(this).val() === 'multiselect') {
            $('#group_multiselect_values').show();
            $('#attr_multiselect_values').prop('required', true);
        } else {
            $('#group_multiselect_values').hide();
            $('#attr_multiselect_values').prop('required', false).val('');
        }
    });

    $('#attribute-form').submit(function(e) {
        e.preventDefault();
        $.post('api_ci.php', $(this).serialize(), function(res) {
            if (res.success) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: res.message,
                    showConfirmButton: false,
                    timer: 2000
                });
                loadAttributes();
                closeForm();
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    });
});

function loadAttributes() {
    $.get('api_ci.php?action=get_attributes', function(res) {
        if (res.success) {
            attributes = res.data;
            renderTable();
        }
    }, 'json');
}

function renderTable() {
    let tbody = $('#attributes-table tbody');
    tbody.empty();
    
    if (attributes.length === 0) {
        tbody.append('<tr><td colspan="5" class="text-center text-muted">No hay atributos globales configurados.</td></tr>');
        return;
    }
    
    attributes.forEach(attr => {
        let typeMap = {
            'string': '<span class="badge badge-info">Texto</span>',
            'textarea': '<span class="badge badge-secondary">Texto Largo</span>',
            'number': '<span class="badge badge-primary">Número</span>',
            'boolean': '<span class="badge badge-success">Booleano</span>',
            'date': '<span class="badge badge-warning">Fecha</span>',
            'multiselect': '<span class="badge badge-dark">Select</span>',
            'image': '<span class="badge badge-danger"><i class="fas fa-image"></i> Imagen</span>'
        };
        let req = attr.is_required == 1 ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-muted"></i>';
        
        let tr = `
            <tr style="cursor:pointer;" onclick="openEditForm(${attr.id})">
                <td class="font-weight-bold">${attr.name}</td>
                <td><span class="badge badge-secondary">${attr.group_name || 'General'}</span></td>
                <td>${typeMap[attr.type] || attr.type}</td>
                <td class="text-center">${req}</td>
                <td class="text-muted small">${attr.description || ''}</td>
                <td class="text-right"><i class="fas fa-chevron-right text-muted"></i></td>
            </tr>
        `;
        tbody.append(tr);
    });
}

function openCreateForm() {
    $('#form-card').show();
    $('#welcome-msg').hide();
    $('#btn-delete').addClass('d-none');
    $('#form-title').text('Nuevo Atributo');
    
    $('#attr_id').val(0);
    $('#attr_name').val('');
    $('#attr_group_name').val('General');
    $('#attr_type').val('string').trigger('change');
    $('#attr_is_required').prop('checked', false);
    $('#attr_description').val('');
    $('#attr_multiselect_values').val('');
}

function openEditForm(id) {
    let attr = attributes.find(a => a.id == id);
    if (!attr) return;
    
    $('#form-card').show();
    $('#welcome-msg').hide();
    $('#btn-delete').removeClass('d-none');
    $('#form-title').text('Editar Atributo');
    
    $('#attr_id').val(attr.id);
    $('#attr_name').val(attr.name);
    $('#attr_group_name').val(attr.group_name || 'General');
    $('#attr_type').val(attr.type).trigger('change');
    $('#attr_is_required').prop('checked', attr.is_required == 1);
    $('#attr_description').val(attr.description || '');
    $('#attr_multiselect_values').val(attr.multiselect_values || '');
}

function closeForm() {
    $('#form-card').hide();
    $('#welcome-msg').show();
}

function deleteAttribute() {
    let id = $('#attr_id').val();
    Swal.fire({
        title: '¿Eliminar Atributo?',
        text: 'Esto no afectará a las clases que ya lo tengan en su esquema, pero no podrá ser añadido a nuevas clases globalmente.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('api_ci.php', {action: 'delete_attribute', id: id}, function(res) {
                if (res.success) {
                    Swal.fire('Eliminado', res.message, 'success');
                    loadAttributes();
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
