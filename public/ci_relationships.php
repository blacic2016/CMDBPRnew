<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

require_login();
if (!has_role('SUPER_ADMIN')) {
    die("Acceso denegado. Se requiere rol SUPER_ADMIN.");
}

$page_title = 'Tipos de Relación';
require_once __DIR__ . '/partials/header.php';
?>

<div class="container-fluid pt-4">
    <div class="row">
        <!-- Listado de Relaciones -->
        <div class="col-md-7">
            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold"><i class="fas fa-link mr-2 text-primary"></i> Tipos de Relaciones (Directa / Inversa)</h3>
                    <button class="btn btn-sm btn-success shadow-sm ml-auto" onclick="openCreateForm()"><i class="fas fa-plus"></i> Nueva Relación</button>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover table-striped m-0" id="relations-table">
                        <thead class="bg-light">
                            <tr>
                                <th>Relación Directa</th>
                                <th>Relación Inversa</th>
                                <th>Descripción</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Llenado vía JS -->
                            <tr><td colspan="4" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin"></i> Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Formulario de Edición -->
        <div class="col-md-5">
            <div class="card card-outline card-warning shadow-sm" id="form-card" style="display:none;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold" id="form-title">Crear Relación</h3>
                    <button class="btn btn-sm btn-danger d-none ml-auto" id="btn-delete" onclick="deleteRelation()"><i class="fas fa-trash"></i> Eliminar</button>
                </div>
                <div class="card-body">
                    <form id="relation-form">
                        <input type="hidden" name="action" value="save_relationship_type">
                        <input type="hidden" name="id" id="rel_id" value="0">
                        
                        <div class="form-group">
                            <label class="font-weight-bold">Nombre Relación Directa <span class="text-danger">*</span></label>
                            <input type="text" name="name_direct" id="rel_name_direct" class="form-control" required placeholder="Ej. contiene, alimenta a, conecta a">
                            <small class="form-text text-muted">La acción del origen hacia el destino (ej. A contiene a B).</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="font-weight-bold">Nombre Relación Inversa <span class="text-danger">*</span></label>
                            <input type="text" name="name_inverse" id="rel_name_inverse" class="form-control" required placeholder="Ej. está dentro de, es alimentado por, es conectado por">
                            <small class="form-text text-muted">La acción recíproca del destino hacia el origen (ej. B está dentro de A).</small>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Descripción (Opcional)</label>
                            <textarea name="description" id="rel_description" class="form-control" rows="3" placeholder="Propósito o ejemplo de uso de esta relación..."></textarea>
                        </div>
                        
                        <div class="text-right border-top pt-3">
                            <button type="button" class="btn btn-secondary mr-2" onclick="closeForm()">Cancelar</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div id="welcome-msg" class="text-center py-5 text-muted">
                <i class="fas fa-project-diagram fa-4x mb-3 opacity-2" style="color: #cbd5e1;"></i>
                <h4>Gestión de Tipos de Relaciones</h4>
                <p class="small">Selecciona un tipo de relación existente para editarlo o define una nueva relación con su correspondencia directa/inversa.</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let relationsList = [];

$(document).ready(function() {
    loadRelations();

    $('#relation-form').submit(function(e) {
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
                loadRelations();
                closeForm();
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    });
});

function loadRelations() {
    $.get('api_ci.php?action=get_relationship_types', function(res) {
        if (res.success) {
            relationsList = res.data;
            renderTable();
        }
    }, 'json');
}

function renderTable() {
    let tbody = $('#relations-table tbody');
    tbody.empty();
    
    if (relationsList.length === 0) {
        tbody.append('<tr><td colspan="4" class="text-center text-muted">No hay tipos de relaciones configuradas.</td></tr>');
        return;
    }
    
    relationsList.forEach(rel => {
        let tr = `
            <tr style="cursor:pointer;" onclick="openEditForm(${rel.id})">
                <td class="font-weight-bold text-primary"><i class="fas fa-arrow-right mr-1 small text-success"></i> ${rel.name_direct}</td>
                <td class="font-weight-bold text-info"><i class="fas fa-arrow-left mr-1 small text-warning"></i> ${rel.name_inverse}</td>
                <td class="text-muted small">${rel.description || 'Sin descripción'}</td>
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
    $('#form-title').text('Crear Relación');
    
    $('#rel_id').val(0);
    $('#rel_name_direct').val('');
    $('#rel_name_inverse').val('');
    $('#rel_description').val('');
}

function openEditForm(id) {
    let rel = relationsList.find(r => r.id == id);
    if (!rel) return;
    
    $('#form-card').show();
    $('#welcome-msg').hide();
    $('#btn-delete').removeClass('d-none');
    $('#form-title').text('Editar Relación');
    
    $('#rel_id').val(rel.id);
    $('#rel_name_direct').val(rel.name_direct);
    $('#rel_name_inverse').val(rel.name_inverse);
    $('#rel_description').val(rel.description || '');
}

function closeForm() {
    $('#form-card').hide();
    $('#welcome-msg').show();
}

function deleteRelation() {
    let id = $('#rel_id').val();
    Swal.fire({
        title: '¿Eliminar Relación?',
        text: 'Esto removerá la definición de esta relación de la lista global de tipos.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('api_ci.php', {action: 'delete_relationship_type', id: id}, function(res) {
                if (res.success) {
                    Swal.fire('Eliminado', res.message, 'success');
                    loadRelations();
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
