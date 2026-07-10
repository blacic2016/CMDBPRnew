<?php
/**
 * Módulo de Cotizaciones - CMDB VILASECA
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/permissions_helper.php';
require_once __DIR__ . '/../../src/helpers.php';

require_login();
if (!has_module_access('cotizador')) {
    header("Location: ../dashboard.php");
    exit();
}

$page_title = "Cotizador de Servicios";
$hide_content_header = true;
include '../partials/header.php';

$pdo = getPDO();
// Load clients from ci_instances
$clients = $pdo->query("SELECT id, hostname, ci_unique FROM ci_instances ORDER BY hostname ASC")->fetchAll(PDO::FETCH_ASSOC);

$active_tab = $_GET['tab'] ?? 'configurador';
if (!in_array($active_tab, ['configurador', 'editor', 'list'])) {
    $active_tab = 'configurador';
}
?>

<!-- Custom Premium CSS for Cotizador -->
<style>
  :root {
    --primary-gradient: linear-gradient(135deg, #101b31 0%, #1a305c 100%);
    --accent-color: #ff5c05;
    --success-color: #28a745;
    --info-color: #17a2b8;
    --border-radius: 10px;
  }
  
  .card-premium {
    border-radius: var(--border-radius);
    border: none;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    background: var(--card-bg);
    margin-bottom: 25px;
    overflow: hidden;
  }
  
  .card-premium .card-header {
    background: var(--primary-gradient);
    color: #fff;
    border-bottom: none;
    padding: 15px 20px;
  }
  
  .nav-tabs-premium {
    border-bottom: 2px solid #e9ecef;
  }
  
  .nav-tabs-premium .nav-link {
    border: none;
    color: #495057;
    font-weight: 600;
    padding: 12px 20px;
    transition: all 0.3s ease;
    border-bottom: 3px solid transparent;
  }
  
  .nav-tabs-premium .nav-link:hover {
    color: var(--accent-color);
  }
  
  .nav-tabs-premium .nav-link.active {
    color: var(--accent-color);
    background: transparent;
    border-bottom: 3px solid var(--accent-color);
  }
  
  .table-premium th {
    background-color: rgba(16, 27, 49, 0.05) !important;
    color: #101b31 !important;
    font-size: 0.85rem;
    text-transform: uppercase;
    font-weight: 700;
    border-bottom: 2px solid #dee2e6;
  }
  
  body.dark-mode .table-premium th {
    background-color: rgba(255, 255, 255, 0.05) !important;
    color: #fff !important;
  }
  
  .badge-status {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
  }
  
  .badge-borrador { background-color: #ffc107; color: #1f2d3d; }
  .badge-enviada { background-color: #28a745; color: #fff; }
  
  .calc-block {
    background-color: rgba(0,0,0,0.02);
    border-radius: var(--border-radius);
    padding: 15px;
    border: 1px solid #dee2e6;
  }
  body.dark-mode .calc-block {
    background-color: rgba(255,255,255,0.02);
    border-color: #444;
  }
  
  .total-row {
    font-weight: bold;
    background-color: rgba(40, 167, 69, 0.1);
  }
  
  .sub-total-section {
    font-size: 1.1rem;
    font-weight: bold;
    color: var(--accent-color);
    border-bottom: 2px solid var(--accent-color);
    margin-top: 20px;
    margin-bottom: 15px;
    padding-bottom: 5px;
  }
  
  .version-tree-row {
    background-color: rgba(0,0,0,0.01);
  }
  body.dark-mode .version-tree-row {
    background-color: rgba(255,255,255,0.01);
  }
  
  .comparison-diff-plus {
    color: #28a745;
    font-weight: bold;
  }
  .comparison-diff-minus {
    color: #dc3545;
    font-weight: bold;
  }
  
  /* Compact layout rules for Cotizador */
  .card-premium {
    margin-bottom: 15px;
  }
  .card-premium .card-body.p-4 {
    padding: 15px !important;
  }
  .card-premium .table th, 
  .card-premium .table td {
    padding: 5px 8px !important;
    font-size: 0.83rem !important;
    vertical-align: middle !important;
  }
  .card-premium .form-control, 
  .card-premium .btn {
    padding: 4px 8px !important;
    font-size: 0.85rem !important;
    height: auto !important;
  }
  .card-premium .btn-xs {
    padding: 1px 5px !important;
    font-size: 0.75rem !important;
  }
  .card-premium h5 {
    font-size: 1.05rem !important;
  }
  .card-premium p {
    font-size: 0.82rem !important;
    margin-bottom: 8px !important;
  }
  .brand-separator-row td {
    padding: 6px 12px !important;
  }
</style>

<div class="row">
  <div class="col-12">
    <!-- Premium Card Wrapper -->
    <div class="card card-premium">
      <!-- Navigation Tabs (Hidden from UI to prevent double tab bars/routing, kept in DOM for programmatic compatibility) -->
      <div class="card-body p-0 border-bottom d-none">
        <ul class="nav nav-tabs nav-tabs-premium px-3 pt-2" id="cotizador-tabs" role="tablist">
          <li class="nav-item">
            <a class="nav-link <?php echo $active_tab === 'configurador' ? 'active' : ''; ?>" id="tab-configurador-link" data-toggle="tab" href="#tab-configurador" role="tab"><i class="fas fa-sliders-h mr-1"></i> Configurador</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo $active_tab === 'editor' ? 'active' : ''; ?>" id="tab-editor-link" data-toggle="tab" href="#tab-editor" role="tab"><i class="fas fa-edit mr-1"></i> Diseño Cotización</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo $active_tab === 'list' ? 'active' : ''; ?>" id="tab-list-link" data-toggle="tab" href="#tab-list" role="tab"><i class="fas fa-history mr-1"></i> Historial Cotizaciones</a>
          </li>
        </ul>
      </div>
      
      <!-- Tab Contents -->
      <div class="card-body p-4">
        <div class="tab-content" id="cotizador-tab-content">
          
          <!-- MAIN TAB 1: CONFIGURADOR (NESTED TABS) -->
          <div class="tab-pane fade <?php echo $active_tab === 'configurador' ? 'show active' : ''; ?>" id="tab-configurador" role="tabpanel">
            <div class="card card-outline card-secondary shadow-none border mb-0">
              <div class="card-header p-0 pt-1 border-bottom-0 bg-light">
                <ul class="nav nav-tabs px-3" id="configurador-tabs" role="tablist">
                  <li class="nav-item">
                    <a class="nav-link active" id="tab-specialists-link" data-toggle="tab" href="#tab-specialists" role="tab"><i class="fas fa-user-tie mr-1"></i> Especialistas / Tarifas</a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" id="tab-pool-link" data-toggle="tab" href="#tab-pool" role="tab"><i class="fas fa-layer-group mr-1"></i> Pool de Servicios</a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" id="tab-levels-link" data-toggle="tab" href="#tab-levels" role="tab"><i class="fas fa-cogs mr-1"></i> Configuración de Especialistas</a>
                  </li>
                </ul>
              </div>
              <div class="card-body p-3">
                <div class="tab-content" id="configurador-tab-content">
                  <!-- TAB 4: SPECIALISTS & RATES -->
                  <?php include 'partials/specialists_tab.php'; ?>
                  
                  <!-- TAB 3: POOL OF SERVICES (CRUD) -->
                  <?php include 'partials/pool_tab.php'; ?>
                  
                  <!-- TAB 5: SPECIALIST LEVELS CONFIGURATION -->
                  <?php include 'partials/levels_tab.php'; ?>
                </div>
              </div>
            </div>
          </div>
          
          <!-- MAIN TAB 2: QUOTE WORKBOOK DESIGNER -->
          <?php include 'partials/editor_tab.php'; ?>
          
          <!-- MAIN TAB 3: LIST & HISTORY OF QUOTES -->
          <?php include 'partials/list_tab.php'; ?>
          
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Sync sidebar links and URL on tab switch -->
<script>
document.addEventListener("DOMContentLoaded", function() {
  $('#cotizador-tabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
    const targetId = $(e.target).attr('href');
    let tabName = '';
    if (targetId === '#tab-configurador') {
      tabName = 'configurador';
    } else if (targetId === '#tab-editor') {
      tabName = 'editor';
    } else if (targetId === '#tab-list') {
      tabName = 'list';
    }
    
    if (tabName) {
      const newUrl = window.location.pathname + '?tab=' + tabName;
      window.history.pushState({ path: newUrl }, '', newUrl);
      
      // Sync sidebar active highlight
      $('.nav-sidebar a[href*="/cotizador/index.php"]').removeClass('active');
      $(`.nav-sidebar a[href*="tab=${tabName}"]`).addClass('active');
    }
  });

  // Intercept sidebar clicks to prevent page reload if already on the cotizador page
  $('.nav-sidebar a[href*="/cotizador/index.php"]').on('click', function(e) {
    const href = $(this).attr('href');
    if (!href) return;
    
    const match = href.match(/[?&]tab=([^&#]*)/);
    const tabName = match ? match[1] : '';
    
    if (tabName) {
      e.preventDefault(); // Prevent full page reload
      
      // Activate the tab
      $(`#tab-${tabName}-link`).tab('show');
    }
  });
});
</script>

<!-- Modals -->
<?php include 'partials/modals.php'; ?>

<!-- Javascript Client-side logic for Cotizador -->
<script src="js/cotizador.js?v=<?= time() ?>"></script>

<?php
include '../partials/footer.php';
?>
