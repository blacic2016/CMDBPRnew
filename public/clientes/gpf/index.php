<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../src/auth.php';
require_once __DIR__ . '/../../../src/permissions_helper.php';

require_login();

if (!has_module_access('clientes')) {
    header("Location: " . PUBLIC_URL_PREFIX . "/dashboard.php");
    exit;
}

$page_title = "GPF - Dashboard de Control";
require_once __DIR__ . '/../../partials/header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark"><i class="fas fa-industry mr-2 text-success"></i>GPF</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?php echo PUBLIC_URL_PREFIX; ?>/dashboard.php">Inicio</a></li>
                    <li class="breadcrumb-item active">Clientes</li>
                    <li class="breadcrumb-item active">GPF</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card card-outline card-success shadow-sm">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-info-circle mr-2"></i>Información del Cliente GPF</h3>
            </div>
            <div class="card-body text-center py-5">
                <div class="mb-4">
                    <i class="fas fa-project-diagram fa-4x text-success animate__animated animate__pulse animate__infinite"></i>
                </div>
                <h4 class="font-weight-bold text-secondary">Módulo en Desarrollo</h4>
                <p class="text-muted max-w-md mx-auto">
                    Los indicadores, integraciones de monitoreo y reportes correspondientes al cliente GPF se encuentran actualmente en fase de integración. Próximamente se habilitarán aquí.
                </p>
                <div class="mt-4">
                    <a href="<?php echo PUBLIC_URL_PREFIX; ?>/dashboard.php" class="btn btn-success px-4"><i class="fas fa-arrow-left mr-2"></i>Volver al Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../partials/footer.php';
?>
