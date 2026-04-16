<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';

// Asegurar que el usuario esté autenticado
require_login();

$page_title = 'SNMP Builder';

// header.php ya incluye sidebar.php y abre el content-wrapper + section + container
include __DIR__ . '/partials/header.php';
?>

<!-- Ajustamos el estilo para que el iframe ocupe todo el espacio disponible -->
<style>
    .snmp-container {
        height: calc(100vh - 200px);
        min-height: 600px;
    }
</style>

<div class="row">
    <div class="col-12">
        <div class="card card-primary card-outline snmp-container">
            <div class="card-body p-0" style="height: 100%;">
                <iframe src="snmpbuilder/332.html" style="width: 100%; height: 100%; border: none;"></iframe>
            </div>
        </div>
    </div>
</div>

<?php 
// footer.php cierra el container, section, y content-wrapper
include __DIR__ . '/partials/footer.php'; 
?>
