<?php
session_start();
include 'db_connection.php';

// Fetch all tasks
$sql = "SELECT jobs_id, TIPO, VM, JOB_NAME, FRECUENCIA, HORA FROM TareasBackup ORDER BY jobs_id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Tareas de Backup</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
            color: #333;
            margin: 0;
            font-family: 'Roboto', Arial, sans-serif;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #007bff;
        }
        .btn {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            background-color: #007bff;
            color: white;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-edit {
            background-color: #ffc107;
            color: #212529;
            padding: 5px 10px;
            text-decoration: none;
            border-radius: 5px;
        }
        a.btn-danger {
            padding: 5px 10px;
            color: white !important;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
        }
        form {
            margin-bottom: 20px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #fdfdfd;
        }
        form label {
            font-weight: bold;
            margin-top: 10px;
            display: block;
        }
        form input[type="text"], form select {
            width: 100%;
            padding: 8px;
            margin: 6px 0;
            display: inline-block;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        form button {
            background-color: #28a745;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
    </style>
</head>
<body>

<div class="container">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['msg_type']; ?>">
            <?php 
                echo $_SESSION['message']; 
                unset($_SESSION['message']);
            ?>
        </div>
    <?php endif ?>

    <a href="index.html" class="btn">Volver a la Página Principal</a>
    
    <h1>Gestionar Tareas de Backup</h1>

    <h2>Añadir/Editar Tarea</h2>
    <form action="tasks_crud.php" method="POST">
        <input type="hidden" name="jobs_id" id="jobs_id">
        <label for="TIPO">Tipo:</label>
        <input type="text" id="TIPO" name="TIPO" required>
        
        <label for="VM">VM:</label>
        <input type="text" id="VM" name="VM">
        
        <label for="JOB_NAME">Nombre del Job:</label>
        <input type="text" id="JOB_NAME" name="JOB_NAME">

        <label for="FRECUENCIA">Frecuencia:</label>
        <input type="text" id="FRECUENCIA" name="FRECUENCIA">

        <label for="HORA">Hora:</label>
        <input type="text" id="HORA" name="HORA">

        <button type="submit" name="save_task">Guardar Tarea</button>
    </form>

    <h2>Tareas Existentes</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Tipo</th>
                <th>VM</th>
                <th>Nombre Job</th>
                <th>Frecuencia</th>
                <th>Hora</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['jobs_id']; ?></td>
                <td><?php echo htmlspecialchars($row['TIPO']); ?></td>
                <td><?php echo htmlspecialchars($row['VM']); ?></td>
                <td><?php echo htmlspecialchars($row['JOB_NAME']); ?></td>
                <td><?php echo htmlspecialchars($row['FRECUENCIA']); ?></td>
                <td><?php echo htmlspecialchars($row['HORA']); ?></td>
                <td>
                    <button class="btn-edit" onclick='editTask(<?php echo json_encode($row); ?>)'>Editar</button>
                    <a href="tasks_crud.php?delete=<?php echo $row['jobs_id']; ?>" class="btn-danger" onclick="return confirm('¿Estás seguro de que quieres eliminar esta tarea?');">Eliminar</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
function editTask(task) {
    document.getElementById('jobs_id').value = task.jobs_id;
    document.getElementById('TIPO').value = task.TIPO;
    document.getElementById('VM').value = task.VM;
    document.getElementById('JOB_NAME').value = task.JOB_NAME;
    document.getElementById('FRECUENCIA').value = task.FRECUENCIA;
    document.getElementById('HORA').value = task.HORA;
    window.scrollTo(0, 0);
}
</script>

</body>
</html>
<?php
$conn->close();
?>