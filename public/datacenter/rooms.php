<?php
/**
 * Datacenter Rooms Management
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
$page_title = 'Datacenter - Cuartos';

// Manejo de formulario (Crear/Eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = $_POST['name'] ?? '';
        $location = $_POST['location_detail'] ?? '';
        $width = (float)($_POST['width_meters'] ?? 6.0);
        $length = (float)($_POST['length_meters'] ?? 6.0);
        $floor_height = !empty($_POST['floor_height_meters']) ? (float)$_POST['floor_height_meters'] : null;
        
        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO dc_rooms (name, location_detail, width_meters, length_meters, floor_height_meters) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $location, $width, $length, $floor_height]);
            $_SESSION['flash_msg'] = "Cuarto creado exitosamente.";
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = $_POST['name'] ?? '';
        $location = $_POST['location_detail'] ?? '';
        $width = (float)($_POST['width_meters'] ?? 6.0);
        $length = (float)($_POST['length_meters'] ?? 6.0);
        $floor_height = !empty($_POST['floor_height_meters']) ? (float)$_POST['floor_height_meters'] : null;
        
        if ($id > 0 && $name) {
            $stmt = $pdo->prepare("UPDATE dc_rooms SET name = ?, location_detail = ?, width_meters = ?, length_meters = ?, floor_height_meters = ? WHERE id = ?");
            $stmt->execute([$name, $location, $width, $length, $floor_height, $id]);
            $_SESSION['flash_msg'] = "Cuarto actualizado exitosamente.";
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM dc_rooms WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['flash_msg'] = "Cuarto eliminado.";
            } catch (Exception $e) {
                $_SESSION['flash_msg'] = "Error al eliminar: " . $e->getMessage();
            }
        }
    }
    
    header("Location: rooms.php");
    exit;
}

// Cargar cuartos
$rooms = $pdo->query("SELECT * FROM dc_rooms ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../partials/header.php';
?>

<div class="container-fluid pt-4">
    <?php if (isset($_SESSION['flash_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($_SESSION['flash_msg']); unset($_SESSION['flash_msg']); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Modal Crear Cuarto -->
    <div class="modal fade" id="createRoomModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus mr-2"></i> Crear Nuevo Cuarto (Data Center Room)</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Nombre del Cuarto / Sala <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required placeholder="Ej. Sala A, Piso 2">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Detalles de Ubicación</label>
                                <input type="text" name="location_detail" class="form-control" placeholder="Sede Principal, Edificio Central...">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Ancho (m) <span class="text-danger">*</span></label>
                                <input type="number" step="0.1" name="width_meters" id="form_width" class="form-control" required value="30.0">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Largo (m) <span class="text-danger">*</span></label>
                                <input type="number" step="0.1" name="length_meters" id="form_length" class="form-control" required value="50.0">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Altura del Piso / Baldosa (m)</label>
                                <input type="number" step="0.01" name="floor_height_meters" class="form-control" placeholder="Ej. 0.40 para 40cm">
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12">
                                <div class="alert alert-info mb-0">
                                    <i class="fas fa-info-circle mr-2"></i> Las medidas se dividirán en baldosas de piso elevado de 0.6m x 0.6m.<br>
                                    <strong>Cálculo:</strong> <span id="calc_tiles" class="font-weight-bold"></span> baldosas en total.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success font-weight-bold"><i class="fas fa-save mr-1"></i> Crear Cuarto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Cuarto -->
    <div class="modal fade" id="editRoomModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-edit mr-2"></i> Editar Cuarto (Data Center Room)</h5>
                    <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Nombre del Cuarto / Sala <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="edit_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Detalles de Ubicación</label>
                                <input type="text" name="location_detail" id="edit_location" class="form-control">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Ancho (m) <span class="text-danger">*</span></label>
                                <input type="number" step="0.1" name="width_meters" id="edit_width" class="form-control" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Largo (m) <span class="text-danger">*</span></label>
                                <input type="number" step="0.1" name="length_meters" id="edit_length" class="form-control" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Altura del Piso / Baldosa (m)</label>
                                <input type="number" step="0.01" name="floor_height_meters" id="edit_floor_height" class="form-control" placeholder="Ej. 0.40 para 40cm">
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12">
                                <div class="alert alert-info mb-0">
                                    <i class="fas fa-info-circle mr-2"></i> Las medidas se dividirán en baldosas de piso elevado de 0.6m x 0.6m.<br>
                                    <strong>Cálculo:</strong> <span id="edit_calc_tiles" class="font-weight-bold"></span> baldosas en total.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning font-weight-bold"><i class="fas fa-save mr-1"></i> Actualizar Cuarto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Lista de Cuartos (ABAJO) -->
    <div class="card card-outline card-warning shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title"><i class="fas fa-building mr-2"></i> Lista de Cuartos / Ubicaciones</h3>
            <button class="btn btn-sm btn-primary ml-auto" data-toggle="modal" data-target="#createRoomModal"><i class="fas fa-plus mr-1"></i> Crear Cuarto</button>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped table-hover m-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Ubicación</th>
                        <th>Dimensiones Físicas</th>
                        <th>Altura Piso</th>
                        <th>Cálculo de Área (Baldosas 0.6x0.6)</th>
                        <th>Fecha Act.</th>
                        <th class="text-right pr-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($rooms) > 0): ?>
                        <?php foreach ($rooms as $room): 
                            $w = $room['width_meters'] ?? 6;
                            $l = $room['length_meters'] ?? 6;
                            $ts = $room['tile_size'] ?? 0.6;
                            
                            $tiles_x = floor($w / $ts);
                            $tiles_y = floor($l / $ts);
                            $total_tiles = $tiles_x * $tiles_y;
                        ?>
                            <tr>
                                <td><?php echo $room['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($room['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($room['location_detail']); ?></td>
                                <td><span class="badge badge-light border"><?php echo htmlspecialchars($w); ?>m x <?php echo htmlspecialchars($l); ?>m</span></td>
                                <td>
                                    <?php if (!empty($room['floor_height_meters'])): ?>
                                        <span class="badge badge-info"><?php echo htmlspecialchars($room['floor_height_meters']); ?> m</span>
                                    <?php else: ?>
                                        <span class="text-muted small">No definido</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo $total_tiles; ?></strong> baldosas 
                                    <small class="text-muted">(<?php echo $tiles_x; ?> ancho x <?php echo $tiles_y; ?> largo)</small>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php 
                                            $dateToShow = !empty($room['updated_at']) ? $room['updated_at'] : ($room['created_at'] ?? 'N/A');
                                            echo htmlspecialchars($dateToShow); 
                                        ?>
                                    </small>
                                </td>
                                <td class="text-right pr-4">
                                    <button type="button" class="btn btn-sm btn-warning" title="Editar" onclick='openEditModal(<?php echo htmlspecialchars(json_encode($room), ENT_QUOTES, "UTF-8"); ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" title="Eliminar" onclick="deleteRoom(<?php echo $room['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <a href="racks.php?room_id=<?php echo $room['id']; ?>" class="btn btn-sm btn-info" title="Lista de Racks"><i class="fas fa-list"></i> Racks</a>
                                    <a href="floor_plan.php?id=<?php echo $room['id']; ?>" class="btn btn-sm btn-success font-weight-bold" title="Floor Plan"><i class="fas fa-th"></i> Ver Plano de Planta</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No hay cuartos creados aún. Utiliza el formulario superior para crear uno.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function updateCalc() {
        let w = parseFloat(document.getElementById('form_width').value) || 0;
        let l = parseFloat(document.getElementById('form_length').value) || 0;
        let tX = Math.floor(w / 0.6);
        let tY = Math.floor(l / 0.6);
        let total = tX * tY;
        document.getElementById('calc_tiles').innerText = `${tX} x ${tY} = ${total}`;
    }
    document.getElementById('form_width').addEventListener('input', updateCalc);
    document.getElementById('form_length').addEventListener('input', updateCalc);
    updateCalc();

    function updateEditCalc() {
        let w = parseFloat(document.getElementById('edit_width').value) || 0;
        let l = parseFloat(document.getElementById('edit_length').value) || 0;
        let tX = Math.floor(w / 0.6);
        let tY = Math.floor(l / 0.6);
        let total = tX * tY;
        document.getElementById('edit_calc_tiles').innerText = `${tX} x ${tY} = ${total}`;
    }
    if(document.getElementById('edit_width')) document.getElementById('edit_width').addEventListener('input', updateEditCalc);
    if(document.getElementById('edit_length')) document.getElementById('edit_length').addEventListener('input', updateEditCalc);

    function openEditModal(room) {
        document.getElementById('edit_id').value = room.id;
        document.getElementById('edit_name').value = room.name;
        document.getElementById('edit_location').value = room.location_detail;
        document.getElementById('edit_width').value = room.width_meters;
        document.getElementById('edit_length').value = room.length_meters;
        document.getElementById('edit_floor_height').value = room.floor_height_meters || '';
        updateEditCalc();
        $('#editRoomModal').modal('show');
    }

    function deleteRoom(id) {
        if(confirm('¿Seguro que deseas eliminar este cuarto? Se eliminarán también los racks dentro de él.')) {
            let form = document.createElement('form');
            form.method = 'POST';
            form.action = 'rooms.php';
            
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
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
