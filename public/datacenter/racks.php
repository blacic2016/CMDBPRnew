<?php
/**
 * Datacenter Racks Management
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/db.php';

require_login();
if (!has_role('SUPER_ADMIN')) {
    die("Acceso denegado.");
}

$pdo = getPDO();
$page_title = 'Datacenter - Racks / Gabinetes';

$room_filter = $_GET['room_id'] ?? '';

// Manejo de formulario (Crear/Editar/Eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = $_POST['name'] ?? '';
        $room_id = $_POST['room_id'] ?? 0;
        $total_u = $_POST['total_u'] ?? 42;
        $numbering_dir = $_POST['numbering_dir'] ?? 'DOWN';
        $description = $_POST['description'] ?? '';
        
        if ($name && $room_id) {
            $stmt = $pdo->prepare("INSERT INTO dc_racks (name, room_id, total_u, numbering_dir, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $room_id, $total_u, $numbering_dir, $description]);
            $_SESSION['flash_msg'] = "Rack creado exitosamente.";
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = $_POST['name'] ?? '';
        $room_id = $_POST['room_id'] ?? 0;
        $total_u = $_POST['total_u'] ?? 42;
        $numbering_dir = $_POST['numbering_dir'] ?? 'DOWN';
        $description = $_POST['description'] ?? '';
        
        if ($id > 0 && $name && $room_id) {
            $stmt = $pdo->prepare("UPDATE dc_racks SET name=?, room_id=?, total_u=?, numbering_dir=?, description=? WHERE id=?");
            $stmt->execute([$name, $room_id, $total_u, $numbering_dir, $description, $id]);
            $_SESSION['flash_msg'] = "Rack actualizado exitosamente.";
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM dc_racks WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['flash_msg'] = "Rack eliminado.";
            } catch (Exception $e) {
                $_SESSION['flash_msg'] = "Error al eliminar rack: " . $e->getMessage();
            }
        }
    }
    
    header("Location: racks.php" . ($room_filter ? "?room_id=$room_filter" : ""));
    exit;
}

// Cargar Cuartos para el Select
$rooms = $pdo->query("SELECT id, name FROM dc_rooms ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Cargar Racks
$query = "SELECT r.*, rm.name as room_name, (SELECT COUNT(*) FROM dc_rack_devices rd WHERE rd.rack_id = r.id) as device_count 
          FROM dc_racks r 
          LEFT JOIN dc_rooms rm ON r.room_id = rm.id";
$params = [];

if ($room_filter) {
    $query .= " WHERE r.room_id = ?";
    $params[] = $room_filter;
}
$query .= " ORDER BY rm.name ASC, r.name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$racks = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../partials/header.php';
?>

<div class="container-fluid pt-4">
    <?php if (isset($_SESSION['flash_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($_SESSION['flash_msg']); unset($_SESSION['flash_msg']); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Lista de Racks -->
        <div class="col-md-12">
            <div class="card card-outline card-warning shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title"><i class="fas fa-server mr-2"></i> Lista de Racks</h3>
                    <div class="ml-auto">
                        <?php if ($room_filter): ?>
                            <a href="racks.php" class="btn btn-sm btn-outline-secondary mr-2"><i class="fas fa-times"></i> Quitar Filtro de Cuarto</a>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#createRackModal"><i class="fas fa-plus mr-1"></i> Crear Rack</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped table-hover m-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cuarto</th>
                                <th>Nombre Rack</th>
                                <th>Tamaño (U)</th>
                                <th>Equipos</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($racks) > 0): ?>
                                <?php foreach ($racks as $rack): ?>
                                    <tr>
                                        <td><?php echo $rack['id']; ?></td>
                                        <td><span class="badge badge-info"><?php echo htmlspecialchars($rack['room_name'] ?? 'Desconocido'); ?></span></td>
                                        <td><strong><?php echo htmlspecialchars($rack['name']); ?></strong></td>
                                        <td>
                                            <?php echo htmlspecialchars($rack['total_u']); ?>U
                                            <small class="d-block text-muted">
                                                <i class="fas fa-arrow-<?php echo ($rack['numbering_dir'] ?? 'DOWN') === 'UP' ? 'down' : 'up'; ?>"></i>
                                                <?php echo ($rack['numbering_dir'] ?? 'DOWN') === 'UP' ? 'U1 Arriba' : 'U1 Abajo'; ?>
                                            </small>
                                        </td>
                                        <td><span class="badge badge-secondary"><?php echo $rack['device_count']; ?></span></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info" title="Editar" onclick="editRack(<?php echo htmlspecialchars(json_encode($rack), ENT_QUOTES, 'UTF-8'); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" title="Eliminar" onclick="deleteRack(<?php echo $rack['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <a href="rack_builder.php?id=<?php echo $rack['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-cubes"></i> Rack Builder</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No hay racks creados.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- Modal Crear Rack -->
    <div class="modal fade" id="createRackModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus mr-2"></i> Crear Nuevo Rack / Gabinete</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-body">
                        <?php if (count($rooms) === 0): ?>
                            <div class="alert alert-warning">
                                Debes <a href="rooms.php">crear un Cuarto</a> primero.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label>Cuarto (Room) <span class="text-danger">*</span></label>
                                    <select name="room_id" class="form-control" required>
                                        <option value="">Seleccione un cuarto...</option>
                                        <?php foreach ($rooms as $rm): ?>
                                            <option value="<?php echo $rm['id']; ?>" <?php echo $room_filter == $rm['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($rm['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>Nombre del Rack <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" required placeholder="Ej. Rack 1A, Gabinete Core">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>Tamaño (Unidades Rack - U)</label>
                                    <input type="number" name="total_u" class="form-control" value="42" min="1" max="100" required>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>Dirección de Numeración (U1)</label>
                                    <select name="numbering_dir" class="form-control">
                                        <option value="DOWN">Abajo hacia Arriba (U1 abajo)</option>
                                        <option value="UP">Arriba hacia Abajo (U1 arriba)</option>
                                    </select>
                                </div>
                                <div class="col-md-12 form-group">
                                    <label>Descripción / Propósito</label>
                                    <textarea name="description" class="form-control" rows="2" placeholder="Ej. Servidores de Base de Datos..."></textarea>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (count($rooms) > 0): ?>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success font-weight-bold"><i class="fas fa-save mr-1"></i> Guardar Rack</button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Rack -->
    <div class="modal fade" id="editRackModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-edit mr-2"></i> Editar Rack / Gabinete</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_rack_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Cuarto (Room) <span class="text-danger">*</span></label>
                                <select name="room_id" id="edit_room_id" class="form-control" required>
                                    <option value="">Seleccione un cuarto...</option>
                                    <?php foreach ($rooms as $rm): ?>
                                        <option value="<?php echo $rm['id']; ?>">
                                            <?php echo htmlspecialchars($rm['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Nombre del Rack <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="edit_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Tamaño (Unidades Rack - U)</label>
                                <input type="number" name="total_u" id="edit_total_u" class="form-control" min="1" max="100" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Dirección de Numeración (U1)</label>
                                <select name="numbering_dir" id="edit_numbering_dir" class="form-control">
                                    <option value="DOWN">Abajo hacia Arriba (U1 abajo)</option>
                                    <option value="UP">Arriba hacia Abajo (U1 arriba)</option>
                                </select>
                            </div>
                            <div class="col-md-12 form-group">
                                <label>Descripción / Propósito</label>
                                <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-info font-weight-bold"><i class="fas fa-save mr-1"></i> Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function deleteRack(id) {
        if(confirm('¿Seguro que deseas eliminar este rack? Se perderá la configuración de equipos.')) {
            let form = document.createElement('form');
            form.method = 'POST';
            form.action = 'racks.php<?php echo $room_filter ? "?room_id=$room_filter" : ""; ?>';
            
            let act = document.createElement('input');
            act.type = 'hidden';
            act.name = 'action';
            act.value = 'delete';
            form.appendChild(act);
            
            let idInp = document.createElement('input');
            idInp.type = 'hidden';
            idInp.name = 'id';
            idInp.value = id;
            form.appendChild(idInp);
            
            document.body.appendChild(form);
            form.submit();
        }
    }

    function editRack(rack) {
        $("#edit_rack_id").val(rack.id);
        $("#edit_room_id").val(rack.room_id);
        $("#edit_name").val(rack.name);
        $("#edit_total_u").val(rack.total_u);
        $("#edit_numbering_dir").val(rack.numbering_dir || 'DOWN');
        $("#edit_description").val(rack.description || '');
        $("#editRackModal").modal('show');
    }
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
