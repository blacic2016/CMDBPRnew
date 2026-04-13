<?php
/**
 * CMDB VILASECA - Detalle de Activo (Modernized)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$user = current_user();
$table = $_GET['table'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!isValidTableName($table) || $id <= 0) {
    http_response_code(400);
    exit('Parámetros inválidos.');
}

$row = getRowById($table, $id);
if (!$row) {
    http_response_code(404);
    exit('Registro no encontrado.');
}

$cols = getTableColumns($table);
$page_title = (isset($row['nombre']) ? $row['nombre'] : (isset($row['hostname']) ? $row['hostname'] : 'Detalle de Activo'));

require_once __DIR__ . '/partials/header.php'; 

// Cargar imágenes
$pdo = getPDO();
$stmt_images = $pdo->prepare("SELECT id, filepath FROM images WHERE entity_type = :table AND entity_id = :id ORDER BY uploaded_at DESC");
$stmt_images->execute([':table' => $table, ':id' => $id]);
$images = $stmt_images->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .premium-card { border-radius: 12px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.05) !important; }
    .detail-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; color: #6c757d; margin-bottom: 2px; }
    .detail-value { font-size: 0.95rem; color: #212529; font-weight: 500; }
    .dark-mode .detail-value { color: #dee2e6; }
    
    .gallery-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
    .gallery-item { position: relative; border-radius: 10px; overflow: hidden; height: 120px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 2px solid #fff; transition: transform 0.2s; }
    .gallery-item:hover { transform: scale(1.03); z-index: 10; cursor: pointer; }
    .gallery-item img { width: 100%; height: 100%; object-fit: cover; }
    
    .btn-floating-delete { position: absolute; top: 5px; right: 5px; opacity: 0; transition: opacity 0.2s; }
    .gallery-item:hover .btn-floating-delete { opacity: 1; }
    
    .map-wrapper { height: 450px; border-radius: 12px; overflow: hidden; background: #e9ecef; }
</style>

<div class="container-fluid py-4">
    <div class="row align-items-center mb-4">
        <div class="col">
            <h1 class="h3 mb-0 font-weight-bold text-primary"><i class="fas fa-microchip mr-2"></i> Expediente Técnico</h1>
            <p class="text-muted small mb-0">Detalles profundos del activo en la tabla <strong><?= htmlspecialchars(str_replace('sheet_', '', $table)) ?></strong></p>
        </div>
        <div class="col-auto">
            <div class="btn-group shadow-sm">
                <a href="history.php?table=<?= urlencode($table) ?>&id=<?= $id ?>" class="btn btn-white bg-white"><i class="fas fa-history mr-1"></i> Historial</a>
                <?php if (has_role(['ADMIN','SUPER_ADMIN'])): ?>
                    <button id="btnToggleEdit" class="btn btn-primary"><i class="fas fa-edit mr-1"></i> Editar</button>
                    <button id="btnDeleteRecord" class="btn btn-danger"><i class="fas fa-trash-alt"></i></button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8" id="main-content-column">
            <!-- Cargado dinámicamente -->
        </div>

        <div class="col-lg-4">
            <div class="card premium-card mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 font-weight-bold"><i class="fas fa-camera-retro mr-2"></i> Galería Fotográfica</h5>
                    <?php if (has_role(['ADMIN', 'SUPER_ADMIN'])): ?>
                        <button class="btn btn-xs btn-outline-primary" onclick="document.getElementById('image-input').click()"><i class="fas fa-plus"></i></button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-4">
                    <div id="image-gallery" class="gallery-container">
                        <?php if (empty($images)): ?>
                            <div class="text-center py-5 w-100 opacity-50">
                                <i class="fas fa-image fa-3x mb-2"></i>
                                <p class="small mb-0">Sin fografías adjuntas</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($images as $img): ?>
                                <div class="gallery-item" onclick="window.open('<?= htmlspecialchars($img['filepath']) ?>', '_blank')">
                                    <img src="<?= htmlspecialchars($img['filepath']) ?>" alt="Activo">
                                    <?php if (has_role(['ADMIN', 'SUPER_ADMIN'])): ?>
                                        <button class="btn btn-danger btn-xs btn-floating-delete delete-image-btn" data-image-id="<?= $img['id'] ?>" onclick="event.stopPropagation()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <form id="image-upload-form" class="d-none">
                        <input type="file" id="image-input" name="image" accept="image/*">
                    </form>
                </div>
            </div>

            <div id="map-section" style="display:none;">
                <div class="card premium-card">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="mb-0 font-weight-bold"><i class="fas fa-map-marker-alt mr-2 text-danger"></i> Geo-Localización</h5>
                    </div>
                    <div class="card-body p-0">
                        <div id="map-container" class="map-wrapper"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '<?php echo get_csrf_token(); ?>';
    const mainContentColumn = document.getElementById('main-content-column');
    let isEditMode = false;

    const rowData = <?= json_encode($row) ?>;
    const colsData = <?= json_encode($cols) ?>;
    const mainFields = ['nombre', 'hostname', 'direcci_n_ip_gesti_n', 'serial_number', 'marca_modelo', 'sucursal', 'tipo', 'googlemaps'];

    function render() {
        if (isEditMode) renderEditView(); else renderDetailView();
    }

    function renderDetailView() {
        let fieldsHtml = mainFields.filter(f => rowData[f] !== undefined).map(f => `
            <div class="col-md-4 mb-4">
                <div class="detail-label">${f.replace(/_/g, ' ')}</div>
                <div class="detail-value text-break">${rowData[f] || '<span class="text-muted">--</span>'}</div>
            </div>
        `).join('');

        let otherFields = colsData.filter(c => !mainFields.includes(c) && !['id', '_row_hash', 'created_at', 'updated_at', 'zabbix_host_id'].includes(c));
        let othersHtml = otherFields.map(c => `
            <div class="col-md-4 mb-3 pb-2 border-bottom">
                <div class="detail-label">${c.replace(/_/g, ' ')}</div>
                <div class="detail-value small text-truncate" title="${rowData[c] || ''}">${rowData[c] || '--'}</div>
            </div>
        `).join('');

        mainContentColumn.innerHTML = `
            <div class="card premium-card mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="mb-0 font-weight-bold"><i class="fas fa-list mr-2 text-primary"></i> Atributos Base</h5>
                </div>
                <div class="card-body px-4"><div class="row">${fieldsHtml}</div></div>
            </div>
            <div class="card premium-card mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="mb-0 font-weight-bold"><i class="fas fa-tags mr-2 text-info"></i> Metadatos Adicionales</h5>
                </div>
                <div class="card-body px-4"><div class="row">${othersHtml}</div></div>
            </div>
        `;

        updateGoogleMapsView(rowData['googlemaps']);
        attachGlobalListeners();
    }

    function renderEditView() {
        let inputsHtml = colsData.filter(c => !['id', '_row_hash', 'created_at', 'updated_at', 'zabbix_host_id'].includes(c)).map(c => {
            if (c === 'estado_actual') {
                const options = ['USADO', 'ENTREGADO', 'NO_APARECE', 'DANADO'];
                return `
                    <div class="col-md-6 mb-3">
                        <label class="detail-label">ESTADO ACTUAL</label>
                        <select class="form-control" name="estado_actual" id="input-estado_actual">
                            ${options.map(o => `<option value="${o}" ${rowData[c] === o ? 'selected' : ''}>${o}</option>`).join('')}
                        </select>
                    </div>`;
            }
            return `
                <div class="col-md-6 mb-3">
                    <label class="detail-label">${c.replace(/_/g, ' ')}</label>
                    <input type="text" class="form-control" name="${c}" id="input-${c}" value="${rowData[c] ?? ''}">
                </div>`;
        }).join('');

        mainContentColumn.innerHTML = `
            <form id="edit-form">
                <div class="card premium-card border-warning">
                    <div class="card-header bg-warning py-3 px-4">
                        <h5 class="mb-0 font-weight-bold text-white"><i class="fas fa-edit mr-2"></i> Modo Edición</h5>
                    </div>
                    <div class="card-body px-4 pt-4">
                        <div class="row">${inputsHtml}</div>
                    </div>
                    <div class="card-footer bg-white border-0 px-4 pb-4 text-right">
                        <button type="button" class="btn btn-light px-4 mr-2" onclick="isEditMode=false; render()">Cancelar</button>
                        <button type="submit" class="btn btn-success px-4 shadow-sm"><i class="fas fa-save mr-1"></i> Guardar Cambios</button>
                    </div>
                </div>
            </form>`;
        
        const mapInput = document.getElementById('input-googlemaps');
        if(mapInput) mapInput.addEventListener('input', (e) => updateGoogleMapsView(e.target.value));
        attachEditListeners();
    }

    function updateGoogleMapsView(link) {
        const sec = document.getElementById('map-section');
        const cnt = document.getElementById('map-container');
        if (!link || link.trim() === "") { sec.style.display = 'none'; return; }
        sec.style.display = 'block';
        if (link.includes('<iframe')) {
            cnt.innerHTML = link.replace(/width="\d+"/, 'width="100%"').replace(/height="\d+"/, 'height="450"');
        } else if (link.includes('maps.app.goo.gl')) {
            cnt.innerHTML = `<div class="p-5 text-center"><i class="fas fa-external-link-alt fa-3x text-muted mb-3"></i><h5>Enlace Externo</h5><a href="${link}" target="_blank" class="btn btn-primary btn-sm">Abrir Mapa</a></div>`;
        } else {
            cnt.innerHTML = `<iframe width="100%" height="450" frameborder="0" src="${link}" allowfullscreen></iframe>`;
        }
    }

    function attachGlobalListeners() {
        document.getElementById('btnToggleEdit')?.onclick = () => { isEditMode = true; render(); };
        document.getElementById('btnDeleteRecord')?.onclick = () => {
            Swal.fire({
                title: '¿Confirmar eliminación?',
                text: "Esta acción no se puede deshacer.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const fd = new FormData();
                    fd.append('action', 'delete');
                    fd.append('table', '<?= addslashes($table) ?>');
                    fd.append('id', '<?= $id ?>');
                    fd.append('csrf_token', csrfToken);
                    fetch('api_action.php', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(js => js.success ? window.location.href = 'db.php?name=<?= urlencode($table) ?>' : Swal.fire('Error', js.error, 'error'));
                }
            });
        };
    }

    function attachEditListeners() {
        document.getElementById('edit-form').onsubmit = function(e) {
            e.preventDefault();
            Swal.fire({ title: 'Guardando...', didOpen: () => Swal.showLoading() });
            const fd = new FormData(this);
            fd.append('action', 'update');
            fd.append('table', '<?= addslashes($table) ?>');
            fd.append('id', '<?= $id ?>');
            fd.append('csrf_token', csrfToken);
            fetch('api_action.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(js => {
                    if (js.success) {
                        Swal.fire('Éxito', 'Registro actualizado correctamente', 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error de Guardado', js.error || js.message || 'Error desconocido', 'error');
                        console.error('Update failed:', js);
                    }
                })
                .catch(err => {
                    Swal.fire('Error de Red', 'No se pudo comunicar con el servidor', 'error');
                    console.error('Fetch error:', err);
                });
        };
    }

    // Lógica de carga de imagen
    document.getElementById('image-input')?.onchange = function() {
        if (!this.files.length) return;
        const fd = new FormData(document.getElementById('image-upload-form'));
        fd.append('table', '<?= addslashes($table) ?>');
        fd.append('id', '<?= $id ?>');
        fd.append('csrf_token', csrfToken);
        
        Swal.fire({ title: 'Subiendo imagen...', didOpen: () => Swal.showLoading() });
        fetch('api_upload_image.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) location.reload();
                else Swal.fire('Error', data.error, 'error');
            });
    };

    // Borrar imagen
    document.querySelectorAll('.delete-image-btn').forEach(btn => {
        btn.onclick = function(e) {
            e.stopPropagation();
            Swal.fire({
                title: '¿Eliminar fotografía?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Eliminar'
            }).then(res => {
                if (res.isConfirmed) {
                    const fd = new FormData();
                    fd.append('action', 'delete_image');
                    fd.append('id', this.dataset.imageId);
                    fd.append('csrf_token', csrfToken);
                    fetch('api_action.php', { method: 'POST', body: fd }).then(r => r.json()).then(js => {
                        if (js.success) location.reload();
                    });
                }
            });
        };
    });

    render();
});
</script>
