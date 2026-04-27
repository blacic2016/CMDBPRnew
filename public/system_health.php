<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/system_audit_helper.php';

// Asegurar que solo administradores vean esto dentro de la plataforma
require_login();
if (!has_role(['SUPER_ADMIN'])) {
    header("Location: dashboard.php");
    exit();
}

// Lógica de remediación
$log = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_issues'])) {
    $log = fixSystemIssues();
}

$audit = runSystemAudit();
$page_title = "Salud del Sistema"; // Corregido: Variable esperada por header.php
include 'partials/header.php';
// Nota: header.php ya incluye sidebar.php y abre Content Wrapper / Container Fluid
?>

<div class="row">
    <!-- PHP & EXTENSIONS -->
    <div class="col-md-6">
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-server mr-1"></i> Entorno PHP</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Requisito</th>
                            <th style="width: 100px">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Versión de PHP (<?php echo $audit['php']['message']; ?>)</td>
                            <td><span class="badge bg-<?php echo $audit['php']['status']; ?>"><?php echo $audit['php']['status'] == 'success' ? 'OK' : 'Baja'; ?></span></td>
                        </tr>
                        <?php foreach ($audit['extensions'] as $ext => $info): ?>
                        <tr>
                            <td>Extensión <b><?php echo $ext; ?></b> <small class="text-muted">(<?php echo $info['description']; ?>)</small></td>
                            <td>
                                <?php if ($info['loaded']): ?>
                                    <span class="badge bg-success">Cargada</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">FALTA</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- DIRECTORIES & CONNECTION -->
    <div class="col-md-6">
            <div class="card card-info card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-folder-open mr-1"></i> Permisos y Archivos</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Ruta de Carpeta</th>
                            <th style="width: 100px">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($audit['directories'] as $name => $info): ?>
                        <tr>
                            <td>
                                <code><?php echo $name; ?>/</code><br>
                                <small class="text-muted"><?php echo $info['path']; ?></small>
                            </td>
                            <td>
                                <?php if ($info['writable']): ?>
                                    <span class="badge bg-success">Escritura OK</span>
                                <?php elseif($info['exists']): ?>
                                    <span class="badge bg-danger">Sin Permisos</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">No Existe</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-warning card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-database mr-1"></i> Conexiones Externas</h3>
            </div>
            <div class="card-body">
                <div class="callout callout-<?php echo $audit['database']['status']; ?>">
                    <h5>Base de Datos (<?php echo $audit['database']['host']; ?>)</h5>
                    <p><?php echo $audit['database']['message']; ?></p>
                    <?php if (!empty($audit['database']['missing_tables'])): ?>
                        <div class="mt-2">
                            <span class="badge badge-danger">Tablas Faltantes:</span>
                            <ul class="mb-0">
                                <?php foreach($audit['database']['missing_tables'] as $t): ?>
                                    <li><code><?php echo $t; ?></code></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="mt-2">
                            <span class="badge badge-success"><i class="fas fa-check-double mr-1"></i> Estructura de Tablas OK</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="callout callout-<?php echo $audit['zabbix']['status']; ?>">
                    <h5>Zabbix API</h5>
                    <p>Endpoint: <code><?php echo $audit['zabbix']['url']; ?></code></p>
                    <?php if($audit['zabbix']['status'] == 'warning'): ?>
                        <small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Atención: Apunta a una IP fija interna.</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DETAILED TABLE ANALYSIS -->
<div class="row">
    <div class="col-12">
        <div class="card card-outline card-teal">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-table mr-2"></i> Análisis Detallado de Tablas Maestras</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Nombre de Tabla</th>
                            <th>Propósito / Descripción</th>
                            <th class="text-center" style="width: 150px">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($audit['database']['table_analysis'])): ?>
                            <?php foreach ($audit['database']['table_analysis'] as $tableName => $info): ?>
                            <tr>
                                <td class="align-middle"><code><?php echo $tableName; ?></code></td>
                                <td class="align-middle text-muted small"><?php echo $info['description']; ?></td>
                                <td class="text-center align-middle">
                                    <?php if ($info['exists']): ?>
                                        <?php if ($info['columns_ok']): ?>
                                            <span class="badge badge-success px-3"><i class="fas fa-check mr-1"></i> CREADA</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning px-3"><i class="fas fa-columns mr-1"></i> COLUMNAS FALTANTES</span>
                                            <div class="small text-danger mt-1">Falta: <?php echo implode(', ', $info['missing_cols']); ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-danger px-3"><i class="fas fa-exclamation-circle mr-1"></i> FALTANTE</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center p-4">
                                    <i class="fas fa-database text-muted mb-2 fa-2x"></i><br>
                                    No se pudo realizar el análisis de tablas (Sin conexión a BD).
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MIGRATION VERDICT -->
<div class="row">
    <div class="col-12">
            <?php if (!empty($log)): ?>
            <div class="alert alert-info alert-dismissible shadow-sm">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                <h5><i class="icon fas fa-info-circle"></i> Resultado de la ejecución:</h5>
                <ul class="mb-0">
                    <?php foreach($log as $line): ?>
                        <li><?php echo $line; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="info-box bg-light">
            <div class="info-box-content">
                <span class="info-box-text text-center text-muted">Veredicto de Portabilidad</span>
                <span class="info-box-number text-center text-muted mb-0">
                    <?php 
                    $errors = count(array_filter($audit['extensions'], function($e){return $e['status']=='error';})) + 
                                count(array_filter($audit['directories'], function($d){return $d['status']=='error';}));
                    if ($errors > 0 || !$audit['database']['connected']) {
                        echo "<h3 class='text-danger'>⚠️ PORTABILIDAD LIMITADA</h3>";
                        echo "<p>El sistema requiere intervención manual ($errors puntos rojos).</p>";
                        
                        // Botón de remediación automática (solo carpetas)
                        echo '<form method="POST" class="mt-3">
                                <button type="submit" name="fix_issues" class="btn btn-warning">
                                    <i class="fas fa-magic mr-1"></i> Intentar Corregir Permisos de Carpetas
                                </button>
                                </form>';
                    } else {
                        echo "<h3 class='text-success'>✅ LISTO PARA MIGRAR</h3>";
                        echo "<p>Todos los requisitos se cumplen para una migración limpia.</p>";
                    }
                    ?>
                </span>
            </div>
        </div>

        <?php 
        $termCmds = getTerminalSuggestions($audit);
        if (!empty($termCmds)): 
        ?>
        <div class="card card-dark bg-dark">
            <div class="card-header">
                <h3 class="card-title text-warning"><i class="fas fa-terminal mr-2"></i> Comandos de Consola Requeridos</h3>
            </div>
            <div class="card-body">
                <p class="small text-muted">Copia y pega estos comandos en tu terminal de servidor para corregir los problemas que PHP no puede resolver automáticamente:</p>
                <pre class="bg-black p-3 rounded" style="color: #00ff00; font-family: 'Courier New', Courier, monospace;"><code><?php echo implode("\n", $termCmds); ?></code></pre>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'partials/footer.php'; ?>
