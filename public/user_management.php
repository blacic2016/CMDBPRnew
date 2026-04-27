<?php
/**
 * Gestión de Usuarios y Permisos - CMDB VILASECA
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';

require_login();
if (!has_role(['SUPER_ADMIN'])) {
    header("Location: dashboard.php");
    exit();
}

$page_title = "Gestión de Usuarios";
include 'partials/header.php';

$pdo = getPDO();
$roles = $pdo->query("SELECT * FROM roles")->fetchAll(PDO::FETCH_ASSOC);
$all_sheets = listSheetTables();
$all_modules = [
    'dashboard' => 'Dashboard Principal',
    'import' => 'Importar Excel',
    'distribrack' => 'Galería de Imágenes',
    'topology' => 'Topología de Red',
    'snmp_builder' => 'SNMP Builder',
    'monitoreo' => 'Zabbix (Dashboard/Equipos)'
];
?>

<div class="row">
    <div class="col-12">
        <div class="card card-primary card-outline">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title"><i class="fas fa-users mr-1"></i> Usuarios Registrados</h3>
                <div class="card-tools ml-auto">
                    <button class="btn btn-success btn-sm" onclick="showCreateModal()">
                        <i class="fas fa-user-plus mr-1"></i> Nuevo Usuario
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover table-striped mb-0" id="users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Fecha Registro</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="users-list">
                        <!-- Cargado vía AJAX -->
                        <tr><td colspan="5" class="text-center py-4">Cargando usuarios...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Crear/Editar Usuario -->
<div class="modal fade" id="userModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle">Usuario</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="userForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="userId">
                    <div class="form-group">
                        <label>Nombre de Usuario</label>
                        <input type="text" name="username" id="userName" class="form-control" required>
                    </div>
                    <div id="passwordSection">
                        <div class="form-group">
                            <label>Contraseña</label>
                            <input type="password" name="password" id="userPass" class="form-control">
                            <small class="text-muted" id="passHelp">Mínimo 6 caracteres.</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Rol del Sistema</label>
                        <select name="role_id" id="userRoleId" class="form-control">
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r['id']; ?>"><?php echo $r['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Permisos -->
<div class="modal fade" id="permsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-shield-alt mr-2"></i> Gestión de Permisos: <span id="permsUser"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="permsUserId">
                
                <h6 class="font-weight-bold text-primary border-bottom pb-2">Módulos del Sistema</h6>
                <div class="row mb-4">
                    <?php foreach ($all_modules as $key => $label): ?>
                    <div class="col-md-4 mb-2">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input module-perm" id="mod_<?php echo $key; ?>" data-module="<?php echo $key; ?>">
                            <label class="custom-control-label" for="mod_<?php echo $key; ?>"><?php echo $label; ?></label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <h6 class="font-weight-bold text-success border-bottom pb-2">Pestañas de CMDB (Hojas Excel)</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="bg-light">
                            <tr>
                                <th>Hoja de Datos</th>
                                <th class="text-center">Ver</th>
                                <th class="text-center">Editar</th>
                                <th class="text-center">Eliminar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_sheets as $s): ?>
                            <?php $clean = str_replace('sheet_', '', $s); ?>
                            <tr>
                                <td><?php echo ucfirst($clean); ?></td>
                                <td class="text-center"><input type="checkbox" class="sheet-perm-view" data-sheet="<?php echo $s; ?>"></td>
                                <td class="text-center"><input type="checkbox" class="sheet-perm-edit" data-sheet="<?php echo $s; ?>"></td>
                                <td class="text-center"><input type="checkbox" class="sheet-perm-delete" data-sheet="<?php echo $s; ?>"></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-info" onclick="savePermissions()">
                    <i class="fas fa-save mr-1"></i> Aplicar Permisos
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'partials/footer.php'; ?>

<script>
$(function() {
    loadUsers();

    $('#userForm').on('submit', function(e) {
        e.preventDefault();
        const id = $('#userId').val();
        const action = id ? 'update_user' : 'create_user';
        const data = $(this).serialize() + '&action=' + action;

        $.post('api_users.php', data, function(res) {
            if (res.success) {
                Swal.fire('Éxito', id ? 'Usuario actualizado' : 'Usuario creado', 'success');
                $('#userModal').modal('hide');
                loadUsers();
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        });
    });
});

function loadUsers() {
    $.post('api_users.php', { action: 'list_users' }, function(res) {
        if (res.success) {
            let html = '';
            res.users.forEach(u => {
                html += `
                <tr>
                    <td>${u.id}</td>
                    <td><strong>${u.username}</strong></td>
                    <td><span class="badge badge-info">${u.role}</span></td>
                    <td>${u.created_at}</td>
                    <td class="text-right">
                        <div class="btn-group">
                            <button class="btn btn-xs btn-outline-info" onclick="showPermsModal(${u.id}, '${u.username}')" title="Permisos">
                                <i class="fas fa-lock"></i> Permisos
                            </button>
                            <button class="btn btn-xs btn-outline-primary" onclick="showEditModal(${u.id}, '${u.username}', ${u.role_id})" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-xs btn-outline-warning" onclick="resetPassword(${u.id})" title="Reset Password">
                                <i class="fas fa-key"></i>
                            </button>
                            <button class="btn btn-xs btn-outline-danger" onclick="deleteUser(${u.id})" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
            });
            $('#users-list').html(html);
        }
    });
}

function showCreateModal() {
    $('#userModalTitle').text('Nuevo Usuario');
    $('#userId').val('');
    $('#userName').val('').prop('readonly', false);
    $('#userPass').val('').prop('required', true);
    $('#passwordSection').show();
    $('#userModal').modal('show');
}

function showEditModal(id, name, roleId) {
    $('#userModalTitle').text('Editar Usuario');
    $('#userId').val(id);
    $('#userName').val(name).prop('readonly', true);
    $('#userPass').val('').prop('required', false);
    $('#passwordSection').hide();
    $('#userRoleId').val(roleId);
    $('#userModal').modal('show');
}

function deleteUser(id) {
    Swal.fire({
        title: '¿Confirmar eliminación?',
        text: "Esta acción borrará al usuario permanentemente.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('api_users.php', { action: 'delete_user', id: id }, function(res) {
                if (res.success) {
                    Swal.fire('Eliminado', 'Usuario borrado con éxito', 'success');
                    loadUsers();
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            });
        }
    });
}

function resetPassword(id) {
    Swal.fire({
        title: 'Nueva Contraseña',
        input: 'password',
        inputLabel: 'Introduce la nueva contraseña para el usuario',
        inputAttributes: {
            autocapitalize: 'off',
            autocorrect: 'off'
        },
        showCancelButton: true,
        confirmButtonText: 'Actualizar',
        showLoaderOnConfirm: true,
        preConfirm: (pass) => {
            if (!pass || pass.length < 4) {
                Swal.showValidationMessage('La contraseña es muy corta');
                return false;
            }
            return $.post('api_users.php', { action: 'reset_password', id: id, password: pass });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result && result.value && result.value.success) {
            Swal.fire('¡Éxito!', 'Contraseña actualizada correctamente', 'success');
        } else if (result && result.value) {
            Swal.fire('Error', result.value.error, 'error');
        }
    });
}

// Lógica de Permisos
function showPermsModal(id, name) {
    $('#permsUserId').val(id);
    $('#permsUser').text(name);
    
    // Resetear checkboxes
    $('.module-perm, .sheet-perm-view, .sheet-perm-edit, .sheet-perm-delete').prop('checked', false);

    $.post('api_users.php', { action: 'get_permissions', user_id: id }, function(res) {
        if (res.success) {
            // Aplicar módulos
            res.modules.forEach(m => {
                const cb = $(`#mod_${m.module_name}`);
                if (cb.length) cb.prop('checked', parseInt(m.can_view) === 1);
            });
            // Aplicar sheets
            res.sheets.forEach(s => {
                $(`.sheet-perm-view[data-sheet="${s.sheet_name}"]`).prop('checked', parseInt(s.can_view) === 1);
                $(`.sheet-perm-edit[data-sheet="${s.sheet_name}"]`).prop('checked', parseInt(s.can_edit) === 1);
                $(`.sheet-perm-delete[data-sheet="${s.sheet_name}"]`).prop('checked', parseInt(s.can_delete) === 1);
            });
            $('#permsModal').modal('show');
        }
    });
}

function savePermissions() {
    const id = $('#permsUserId').val();
    const modules = [];
    $('.module-perm').each(function() {
        modules.push({
            name: $(this).data('module'),
            view: $(this).is(':checked') ? 1 : 0
        });
    });

    const sheets = [];
    const sheetNames = [...new Set($('.sheet-perm-view').map(function() { return $(this).data('sheet'); }).get())];
    
    sheetNames.forEach(name => {
        sheets.push({
            name: name,
            view: $(`.sheet-perm-view[data-sheet="${name}"]`).is(':checked') ? 1 : 0,
            edit: $(`.sheet-perm-edit[data-sheet="${name}"]`).is(':checked') ? 1 : 0,
            delete: $(`.sheet-perm-delete[data-sheet="${name}"]`).is(':checked') ? 1 : 0
        });
    });

    $.post('api_users.php', {
        action: 'save_permissions',
        user_id: id,
        modules: JSON.stringify(modules),
        sheets: JSON.stringify(sheets)
    }, function(res) {
        if (res.success) {
            Swal.fire('Guardado', 'Permisos actualizados correctamente', 'success');
            $('#permsModal').modal('hide');
        } else {
            Swal.fire('Error', res.error, 'error');
        }
    });
}
</script>
