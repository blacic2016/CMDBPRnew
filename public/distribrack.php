<?php
/**
 * CMDB VILASECA - Galería Distribrack (Premium)
 * Ubicación: /var/www/html/VILASECA/CMDBPRnew/public/distribrack.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';

require_login();

$user = current_user();
$pdo = getPDO();
$page_title = 'Galería de Activos';

// 1. Procesamiento de Filtros
$entity_type = $_GET['entity_type'] ?? '';
$entity_id = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;
$q = trim($_GET['q'] ?? '');

$where = [];
$params = [];

if ($entity_type !== '') { 
    $where[] = 'entity_type = :et'; 
    $params[':et'] = $entity_type; 
}
if ($entity_id > 0) { 
    $where[] = 'entity_id = :eid'; 
    $params[':eid'] = $entity_id; 
}
if ($q !== '') { 
    $where[] = '(filename LIKE :q OR filepath LIKE :q)'; 
    $params[':q'] = '%' . $q . '%'; 
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$types = $pdo->query("SELECT DISTINCT entity_type FROM images ORDER BY entity_type")->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("SELECT id, entity_type, entity_id, filepath, filename, uploaded_at FROM images " . $whereSql . " ORDER BY uploaded_at DESC LIMIT 300");
$stmt->execute($params);
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/partials/header.php'; 
?>

<!-- Modern Styling -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<style>
    .gallery-card { border: none; border-radius: 12px; overflow: hidden; transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1); box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .gallery-card:hover { transform: translateY(-8px); box-shadow: 0 12px 30px rgba(0,0,0,0.12); }
    .img-container { position: relative; height: 180px; overflow: hidden; background: #f8f9fa; }
    .img-container img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
    .gallery-card:hover .img-container img { transform: scale(1.1); }
    .img-overlay { position: absolute; top: 10px; right: 10px; }
    .badge-entity { background: rgba(255,255,255,0.9); backdrop-filter: blur(4px); color: #333; font-weight: bold; padding: 4px 10px; border-radius: 20px; font-size: 11px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    .card-footer-custom { padding: 12px; background: white; border-top: 1px solid #f0f0f0; }
    .search-bar-premium { background: white; border-radius: 50px; padding: 10px 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: none; }
    .btn-upload-fab { position: fixed; bottom: 30px; right: 30px; width: 60px; height: 60px; border-radius: 30px; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3); z-index: 1000; transition: transform 0.2s; }
    .btn-upload-fab:hover { transform: scale(1.1) rotate(90deg); }
</style>

<div class="container-fluid pt-4 pb-5">
    
    <!-- Premium Header -->
    <div class="row mb-5 animate__animated animate__fadeInDown">
        <div class="col-md-7">
            <h1 class="display-5 font-weight-bold text-dark"><i class="fas fa-images text-success mr-3"></i>Galería de Imágenes</h1>
            <p class="text-muted lead">Visualización y gestión de evidencia fotográfica de activos.</p>
        </div>
        <div class="col-md-5">
            <div class="search-bar-premium d-flex align-items-center">
                <form method="get" class="d-flex w-100 align-items-center">
                    <input type="text" name="q" class="form-control border-0 bg-transparent" placeholder="Buscar archivo..." value="<?php echo htmlspecialchars($q); ?>">
                    <button class="btn btn-primary rounded-circle p-2 ml-2" style="width: 40px; height: 40px;">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Filtros Secundarios -->
    <div class="row mb-4 animate__animated animate__fadeIn">
        <div class="col-md-12">
            <div class="d-flex flex-wrap align-items-center">
                <span class="text-muted small font-weight-bold text-uppercase mr-3">Filtrar por:</span>
                <a href="distribrack.php" class="badge badge-light p-2 px-3 mr-2 border <?php echo !$entity_type ? 'bg-primary text-white border-primary' : ''; ?>">Todos</a>
                <?php foreach ($types as $t): ?>
                    <a href="?entity_type=<?php echo urlencode($t); ?>" class="badge badge-light p-2 px-3 mr-2 border <?php echo $t === $entity_type ? 'bg-primary text-white border-primary' : ''; ?>">
                        <?php echo htmlspecialchars($t); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Gallery Grid -->
    <div class="row animate__animated animate__fadeInUp">
        <?php if (empty($images)): ?>
            <div class="col-12 text-center py-5">
                <div class="p-5 bg-white rounded-lg shadow-sm">
                    <i class="fas fa-layer-group fa-4x text-muted opacity-2 mb-3"></i>
                    <h3>No hay imágenes</h3>
                    <p class="text-muted">No se encontraron registros para los criterios seleccionados.</p>
                </div>
            </div>
        <?php else: foreach ($images as $img): ?>
            <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-4">
                <div class="gallery-card bg-white h-100">
                    <div class="img-container">
                        <img src="<?php echo PUBLIC_URL_PREFIX . '/../' . ltrim($img['filepath'], '/'); ?>" alt="<?php echo htmlspecialchars($img['filename']); ?>" loading="lazy">
                        <div class="img-overlay">
                            <span class="badge-entity shadow-sm"><?php echo htmlspecialchars($img['entity_type']); ?></span>
                        </div>
                    </div>
                    <div class="card-footer-custom">
                        <div class="text-truncate font-weight-bold small mb-1" title="<?php echo htmlspecialchars($img['filename']); ?>">
                            <?php echo htmlspecialchars($img['filename']); ?>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-xs text-muted">ID: #<?php echo $img['entity_id']; ?></span>
                            <?php if (has_role(['ADMIN','SUPER_ADMIN'])): ?>
                                <button class="btn btn-link text-danger p-0 btnDeleteImage" data-id="<?php echo $img['id']; ?>" title="Eliminar">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs text-muted mt-1 opacity-7">
                            <i class="far fa-calendar-alt mr-1"></i> <?php echo date("d/m/Y", strtotime($img['uploaded_at'])); ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- Floating Action Button -->
<button class="btn btn-success btn-upload-fab" id="btnUpload" title="Subir nueva imagen">
    <i class="fas fa-plus fa-lg"></i>
</button>

<!-- Modal Premium -->
<div class="modal fade animate__animated animate__zoomIn" id="uploadModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
      <form id="uploadForm" enctype="multipart/form-data">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title font-weight-bold">Subir Evidencia</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body p-4">
            <div class="form-group mb-4">
                <label class="font-weight-bold small text-muted text-uppercase">Archivo de Imagen</label>
                <div class="custom-file shadow-sm">
                    <input type="file" name="image" class="custom-file-input" required>
                    <label class="custom-file-label">Seleccionar...</label>
                </div>
            </div>
            <div class="row">
                <div class="col-md-7">
                    <div class="form-group">
                        <label class="font-weight-bold small text-muted text-uppercase">Tipo de Activo</label>
                        <input type="text" name="entity_type" class="form-control" placeholder="Ej: Rack, Servidor" required>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-group">
                        <label class="font-weight-bold small text-muted text-uppercase">ID Activo</label>
                        <input type="number" name="entity_id" class="form-control" placeholder="ID" required>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light px-4" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary px-4 shadow-sm">Confirmar Subida</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const csrfToken = '<?php echo get_csrf_token(); ?>';
  
  // Custom File Label
  $('.custom-file-input').on('change', function() {
    let fileName = $(this).val().split('\\').pop();
    $(this).next('.custom-file-label').addClass("selected").html(fileName);
  });

  // Eliminar Imagen con Swal
  document.querySelectorAll('.btnDeleteImage').forEach(function(b){
    b.addEventListener('click', function(e){
      e.stopPropagation();
      const id = this.dataset.id;
      
      Swal.fire({
        title: '¿Eliminar imagen?',
        text: "Esta acción borrará permanentemente la evidencia fotográfica.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
      }).then((result) => {
        if (result.isConfirmed) {
            const formData = new URLSearchParams();
            formData.append('id', id);
            formData.append('action', 'delete_image');
            formData.append('csrf_token', csrfToken);

            fetch('api_action.php', { 
                method: 'POST', 
                headers: {'Content-Type':'application/x-www-form-urlencoded'}, 
                body: formData.toString() 
            })
            .then(r => r.json())
            .then(js => { 
                if (js.success) location.reload(); 
                else Swal.fire('Error', js.error, 'error');
            });
        }
      });
    });
  });

  // Abrir Modal
  const btnUpload = document.getElementById('btnUpload');
  if(btnUpload) {
      btnUpload.addEventListener('click', () => $('#uploadModal').modal('show'));
  }

  // Manejar Formulario de Subida
  document.getElementById('uploadForm')?.addEventListener('submit', function(e){
    e.preventDefault();
    Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    const f = new FormData(this);
    fetch('upload_image.php', { method: 'POST', body: f })
    .then(r => r.json())
    .then(js => { 
        if (js.success) {
            Swal.fire('¡Éxito!', 'Imagen subida correctamente', 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', js.error, 'error');
        }
    })
    .catch(() => Swal.fire('Error', 'Fallo en la conexión', 'error'));
  });
});
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>