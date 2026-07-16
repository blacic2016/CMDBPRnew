<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../src/auth.php';
require_once __DIR__ . '/../../../../src/permissions_helper.php';

require_login();

if (!has_module_access('clientes')) {
    header("Location: " . PUBLIC_URL_PREFIX . "/dashboard.php");
    exit;
}

require_once 'veeam_db_connection.php';

// Fetch all tasks
$sql = "SELECT jobs_id, TIPO, VM, JOB_NAME, FRECUENCIA, HORA FROM TareasBackup ORDER BY jobs_id DESC";
$result = $conn->query($sql);

$page_title = "Gestionar Tareas de Backup - Veeam";
require_once __DIR__ . '/../../../partials/header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark"><i class="fas fa-tasks mr-2 text-info"></i>Gestionar Tareas de Backup</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?php echo PUBLIC_URL_PREFIX; ?>/dashboard.php">Inicio</a></li>
                    <li class="breadcrumb-item active">Clientes</li>
                    <li class="breadcrumb-item active">SONDA</li>
                    <li class="breadcrumb-item active">iin</li>
                    <li class="breadcrumb-item active"><a href="respaldos_veeam.php">Respaldos Veeam</a></li>
                    <li class="breadcrumb-item active">Gestionar Tareas</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card card-info card-outline shadow-sm">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-edit mr-2"></i>Añadir / Editar Tarea</h3>
                <div class="card-tools">
                    <a href="respaldos_veeam.php" class="btn btn-tool text-primary"><i class="fas fa-arrow-left mr-1"></i>Volver a Respaldos Veeam</a>
                </div>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['msg_type']; ?> alert-dismissible fade show" role="alert">
                        <?php 
                            echo $_SESSION['message']; 
                            unset($_SESSION['message']);
                        ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif ?>

                <form action="veeam_tasks_crud.php" method="POST" class="mb-4 p-4 border rounded bg-light">
                    <input type="hidden" name="jobs_id" id="jobs_id">
                    <div class="row">
                        <div class="col-md-2 form-group">
                            <label for="TIPO" class="font-weight-bold">Tipo:</label>
                            <input type="text" id="TIPO" name="TIPO" class="form-control" required placeholder="Ej: VM o JOB">
                        </div>
                        <div class="col-md-3 form-group">
                            <label for="VM" class="font-weight-bold">Nombre VM (o similar):</label>
                            <input type="text" id="VM" name="VM" class="form-control" placeholder="Nombre de la VM">
                        </div>
                        <div class="col-md-3 form-group">
                            <label for="JOB_NAME" class="font-weight-bold">Nombre del Job:</label>
                            <input type="text" id="JOB_NAME" name="JOB_NAME" class="form-control" placeholder="Nombre del Job en Veeam">
                        </div>
                        <div class="col-md-2 form-group">
                            <label for="FRECUENCIA" class="font-weight-bold">Frecuencia:</label>
                            <input type="text" id="FRECUENCIA" name="FRECUENCIA" class="form-control" placeholder="Ej: Diaria, Semanal">
                        </div>
                        <div class="col-md-2 form-group">
                            <label for="HORA" class="font-weight-bold">Hora Programada:</label>
                            <input type="text" id="HORA" name="HORA" class="form-control" placeholder="Ej: 22:00">
                        </div>
                    </div>
                    <div class="text-right">
                        <button type="submit" name="save_task" class="btn btn-success px-4 font-weight-bold shadow-sm"><i class="fas fa-save mr-2"></i>Guardar Tarea</button>
                    </div>
                </form>

                <h3 class="font-weight-bold text-secondary mb-3"><i class="fas fa-list mr-2"></i>Tareas Existentes</h3>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead class="bg-secondary text-white">
                            <tr>
                                <th style="width: 80px;">ID</th>
                                <th>Tipo</th>
                                <th>VM</th>
                                <th>Nombre Job</th>
                                <th>Frecuencia</th>
                                <th>Hora</th>
                                <th style="width: 200px;" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['jobs_id']; ?></td>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($row['TIPO']); ?></span></td>
                                <td><?php echo htmlspecialchars($row['VM']); ?></td>
                                <td><?php echo htmlspecialchars($row['JOB_NAME']); ?></td>
                                <td><?php echo htmlspecialchars($row['FRECUENCIA']); ?></td>
                                <td><?php echo htmlspecialchars($row['HORA']); ?></td>
                                <td class="text-center">
                                    <button class="btn btn-warning btn-sm font-weight-bold mr-1" onclick='editTask(<?php echo json_encode($row); ?>)'><i class="fas fa-edit mr-1"></i>Editar</button>
                                    <a href="veeam_tasks_crud.php?delete=<?php echo $row['jobs_id']; ?>" class="btn btn-danger btn-sm font-weight-bold" onclick="return confirm('¿Estás seguro de que quieres eliminar esta tarea?');"><i class="fas fa-trash-alt mr-1"></i>Eliminar</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function editTask(task) {
    document.getElementById('jobs_id').value = task.jobs_id;
    document.getElementById('TIPO').value = task.TIPO;
    document.getElementById('VM').value = task.VM;
    document.getElementById('JOB_NAME').value = task.JOB_NAME;
    document.getElementById('FRECUENCIA').value = task.FRECUENCIA;
    document.getElementById('HORA').value = task.HORA;
    window.scrollTo({top: 0, behavior: 'smooth'});
}
</script>

<?php
$conn->close();
require_once __DIR__ . '/../../../partials/footer.php';
?>