<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';

require_login();

$page_title = 'Análisis MIB - Explorador Interactivo';
include __DIR__ . '/partials/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/themes/default/style.min.css" />

<style>
    /* Estilos del Explorador */
    .mib-sidebar { background: #fff; border-right: 1px solid #eee; height: calc(100vh - 150px); overflow-y: auto; padding: 20px; }
    .badge-import { background: #f0f4f8; color: #4a5568; font-weight: 500; font-size: 0.75rem; margin: 2px; padding: 4px 10px; border-radius: 6px; border: 1px solid #e2e8f0; display: inline-block; }
    
    .tree-container { background: #fff; border-radius: 8px; min-height: 600px; padding: 15px; border: 1px solid #edf2f7; }
    .mib-code { background: #1a202c; color: #e2e8f0; padding: 20px; border-radius: 8px; font-family: 'Consolas', monospace; font-size: 0.85rem; overflow: auto; max-height: 700px; }

    /* Personalización jsTree para que parezca el de la imagen */
    .jstree-default .jstree-anchor { height: auto !important; padding: 5px 10px !important; border-radius: 4px; width: 100%; transition: background 0.2s; }
    .jstree-default .jstree-hovered { background: #f7fafc !important; box-shadow: none !important; }
    .jstree-default .jstree-clicked { background: #edf2f7 !important; box-shadow: none !important; font-weight: 600; }
    
    .type-pill { font-size: 10px; padding: 2px 8px; background: #edf2f7; color: #718096; border-radius: 12px; margin-left: 10px; font-family: 'Inter', sans-serif; text-transform: uppercase; letter-spacing: 0.5px; }
    .access-pill { font-size: 9px; padding: 2px 6px; background: #e2e8f0; color: #4a5568; border-radius: 4px; margin-left: 5px; font-weight: bold; }
</style>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="row align-items-center mb-4 px-3">
        <div class="col-lg-7">
            <h1 class="h4 font-weight-bold mb-0 text-dark">
                <i class="fas fa-network-wired text-primary mr-2"></i> Explorador Interactivo de MIBs
            </h1>
            <p class="text-muted small mb-0">Visualización jerárquica con tipos de datos y permisos (Estilo Observium).</p>
        </div>
        <div class="col-lg-5 text-right d-flex justify-content-end">
            <select id="mib-selector" class="form-control select2bs4 mr-2" style="width: 250px;"></select>
            <button id="btn-analyze" class="btn btn-primary px-4 shadow-sm">
                <i class="fas fa-sync-alt mr-2"></i> ANALIZAR
            </button>
        </div>
    </div>

    <div class="row d-none" id="main-panel">
        <!-- Sidebar Resumen -->
        <div class="col-xl-3 col-lg-4">
            <div class="mib-sidebar shadow-sm rounded border">
                <div class="mb-4">
                    <label class="small font-weight-bold text-uppercase text-muted letter-spacing-1">Nombre MIB</label>
                    <h5 class="text-dark font-weight-bold mb-0" id="info-module-name">-</h5>
                </div>
                
                <div class="mb-4">
                    <label class="small font-weight-bold text-uppercase text-muted">Total Objetos</label>
                    <div class="h3 font-weight-bold text-primary" id="info-oid-count">0</div>
                </div>

                <div class="mb-4 d-none" id="selection-box">
                    <hr>
                    <label class="small font-weight-bold text-uppercase text-muted">OID Seleccionado</label>
                    <div class="bg-dark text-success p-2 rounded small font-weight-bold mb-2" style="word-break: break-all;" id="info-full-oid">-</div>
                    <button class="btn btn-xs btn-outline-secondary btn-block" onclick="copyOid()">
                        <i class="far fa-copy mr-1"></i> Copiar OID
                    </button>
                </div>

                <div class="mb-4">
                    <label class="small font-weight-bold text-uppercase text-muted">Imports (Dependencias)</label>
                    <div id="info-imports" class="mt-2"></div>
                </div>

                <hr>
                <div class="alert alert-light border-0 bg-light small mb-0">
                    <i class="fas fa-question-circle text-info mr-2"></i> Usa los iconos <i class="fas fa-caret-right"></i> para expandir o contraer las jerarquías de la MIB.
                </div>
            </div>
        </div>

        <!-- Visor Principal -->
        <div class="col-xl-9 col-lg-8">
            <div class="card shadow-sm border-0 rounded overflow-hidden">
                <div class="card-header bg-white p-0">
                    <ul class="nav nav-tabs border-0" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active py-3 px-4 font-weight-bold border-0" data-toggle="tab" href="#v-tree">
                                <i class="fas fa-stream mr-2"></i> Vista Jerárquica
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-3 px-4 font-weight-bold border-0" data-toggle="tab" href="#v-code">
                                <i class="fas fa-file-code mr-2"></i> Código MIB
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body bg-light p-4">
                    <div class="tab-content">
                        <!-- Pestaña Jerárquica con jsTree -->
                        <div class="tab-pane fade show active" id="v-tree">
                            <div class="tree-container shadow-sm">
                                <div id="mib-tree-canvas"></div>
                            </div>
                        </div>

                        <!-- Pestaña Código Raw -->
                        <div class="tab-pane fade" id="v-code">
                            <div class="mib-code shadow-inner" id="content-code"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mensaje Inicial -->
    <div id="welcome" class="text-center py-5">
        <div class="py-5">
            <i class="fas fa-project-diagram fa-5x text-light mb-4"></i>
            <h4 class="text-muted">Selecciona una MIB para explorar su estructura</h4>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/jstree.min.js"></script>

<script>
$(function() {
    $('.select2bs4').select2({ theme: 'bootstrap4' });

    // Cargar MIBs
    $.get('api_snmp.php?action=list_mibs', function(r) {
        if (r.success) {
            $('#mib-selector').empty().append('<option value="">Seleccionar MIB...</option>');
            r.data.forEach(m => { $('#mib-selector').append(new Option(m.filename, m.filename)); });
        }
    });

    $('#btn-analyze').click(function() {
        const file = $('#mib-selector').val();
        if (!file) return Swal.fire('Atención', 'Elige un archivo', 'warning');

        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> PROCESANDO...');
        
        $.get('api_snmp.php', { action: 'analyze_mib', filename: file }, function(resp) {
            $('#btn-analyze').prop('disabled', false).html('<i class="fas fa-sync-alt mr-2"></i> ANALIZAR');
            
            if (resp.success) {
                $('#welcome').addClass('d-none');
                $('#main-panel').removeClass('d-none');

                // Llenar Sidebar
                $('#info-module-name').text(resp.module);
                $('#info-oid-count').text(resp.tree_data.length);
                const imp = $('#info-imports').empty();
                if (resp.imports.length) resp.imports.forEach(i => imp.append(`<span class="badge-import">${i}</span>`));
                else imp.html('<small class="text-muted">Ninguna</small>');

                // Transformar Data para jsTree
                const jsTreeData = [];
                const stack = [{ id: '#', level: -1 }];
                
                resp.tree_data.forEach((item, index) => {
                    const myId = 'node_' + index;
                    while (stack.length > 1 && stack[stack.length - 1].level >= item.level) stack.pop();
                    const parentId = stack[stack.length - 1].id;
                    
                    jsTreeData.push({
                        id: myId,
                        parent: parentId,
                        text: `${item.name} <span class="text-muted">(${item.oid})</span> <span class="type-pill">${item.type}</span> <span class="access-pill">${item.access}</span>`,
                        icon: item.icon + ' ' + item.color,
                        state: { opened: false },
                        data: { full_oid: item.full_oid }
                    });
                    stack.push({ id: myId, level: item.level });
                });

                // Inicializar jsTree
                if($('#mib-tree-canvas').jstree(true)) $('#mib-tree-canvas').jstree(true).destroy();
                
                $('#mib-tree-canvas').on('select_node.jstree', function(e, data) {
                    const fullOid = data.node.data.full_oid;
                    if(fullOid) {
                        $('#selection-box').removeClass('d-none');
                        $('#info-full-oid').text(fullOid);
                    }
                }).jstree({
                    'core': { 'data': jsTreeData, 'themes': { 'dots': true, 'icons': true } },
                    'plugins': ['search', 'wholerow']
                });

                $('#content-code').text(resp.raw);

            } else {
                Swal.fire('Error', resp.error, 'error');
            }
        });
    });
});

function copyOid() {
    const oid = $('#info-full-oid').text();
    navigator.clipboard.writeText(oid).then(() => {
        toastr.success('OID copiado al portapapeles');
    });
}
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
