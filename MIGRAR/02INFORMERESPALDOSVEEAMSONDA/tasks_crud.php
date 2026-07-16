<?php
include 'db_connection.php';

session_start();

// Guardar (Crear/Actualizar) Tarea
if (isset($_POST['save_task'])) {
    $jobs_id = $_POST['jobs_id'];
    $tipo = $_POST['TIPO'];
    $vm = $_POST['VM'];
    $job_name = $_POST['JOB_NAME'];
    $frecuencia = $_POST['FRECUENCIA'];
    $hora = $_POST['HORA'];

    if (!empty($jobs_id)) {
        // Actualizar
        $stmt = $conn->prepare("UPDATE TareasBackup SET TIPO=?, VM=?, JOB_NAME=?, FRECUENCIA=?, HORA=? WHERE jobs_id=?");
        $stmt->bind_param("sssssi", $tipo, $vm, $job_name, $frecuencia, $hora, $jobs_id);
    } else {
        // Crear
        $stmt = $conn->prepare("INSERT INTO TareasBackup (TIPO, VM, JOB_NAME, FRECUENCIA, HORA) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $tipo, $vm, $job_name, $frecuencia, $hora);
    }

    if ($stmt->execute()) {
        $_SESSION['message'] = "Tarea guardada correctamente";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['message'] = "Error al guardar la tarea: " . $conn->error;
        $_SESSION['msg_type'] = "danger";
    }
    
    $stmt->close();
    header('Location: manage_tasks.php');
    exit();
}

// Eliminar Tarea
if (isset($_GET['delete'])) {
    $jobs_id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM TareasBackup WHERE jobs_id=?");
    $stmt->bind_param("i", $jobs_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Tarea eliminada correctamente";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['message'] = "Error al eliminar la tarea: " . $conn->error;
        $_SESSION['msg_type'] = "danger";
    }

    $stmt->close();
    header('Location: manage_tasks.php');
    exit();
}

$conn->close();
?>