<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

require_login();

$page_title = 'CMDB - Business View (Relaciones)';
require_once __DIR__ . '/partials/header.php';
?>

<div class="container-fluid pt-4">
    <div class="row">
        <div class="col-12">
            <div class="card card-outline card-primary shadow-lg">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title"><i class="fas fa-project-diagram mr-2"></i> Vista de Negocio (Jerarquía de CIs)</h3>
                    <div>
                        <a href="ci_list.php" class="btn btn-sm btn-secondary mr-2"><i class="fas fa-arrow-left"></i> Volver</a>
                        <button class="btn btn-sm btn-info" onclick="zoomToFit()"><i class="fas fa-compress-arrows-alt"></i> Ajustar</button>
                    </div>
                </div>
                <div class="card-body p-0 position-relative">
                    <div id="diagram-loader" class="position-absolute w-100 h-100 d-flex justify-content-center align-items-center bg-white" style="z-index: 10; opacity: 0.8;">
                        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                            <span class="sr-only">Cargando...</span>
                        </div>
                    </div>
                    <div id="myDiagramDiv" style="width: 100%; height: 75vh; background-color: #f8f9fa;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const FOCUS_CI_ID = <?php echo isset($_GET['ci_id']) ? (int)$_GET['ci_id'] : 0; ?>;
</script>
<script src="https://unpkg.com/gojs/release/go.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/ci_business_view.js"></script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
