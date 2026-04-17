<?php
/**
 * CMDB VILASECA - Vista de Diagnóstico Exterior (Pública)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/system_audit_helper.php';

// Verificación de acceso autorizado (Token de seguridad)
$is_authorized = (isset($_GET['key']) && $_GET['key'] === SECURITY_TOKEN);

// Lógica de remediación (Solo si está autorizado)
$log = [];
if ($is_authorized && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_issues'])) {
    $log = fixSystemIssues();
}

$audit = runSystemAudit();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Check | CMDB Vilaseca</title>
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #4f46e5;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text: #1e293b;
        }

        [data-theme="dark"] {
            --bg: #0f172a;
            --card-bg: #1e293b;
            --text: #f1f5f9;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Outfit', sans-serif; 
            background: var(--bg); 
            color: var(--text); 
            transition: all 0.3s ease;
            min-height: 100vh;
            padding: 40px 20px;
        }

        .container { max-width: 1000px; margin: auto; }
        
        header { 
            text-align: center; 
            margin-bottom: 50px; 
            animation: fadeInDown 0.8s ease;
        }
        
        .logo-area i { font-size: 3rem; color: var(--primary); margin-bottom: 10px; }
        h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 8px; }
        header p { opacity: 0.7; font-size: 1.1rem; }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }
        .card:hover { transform: translateY(-5px); }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            gap: 12px;
        }
        .card-header i { font-size: 1.5rem; color: var(--primary); }
        .card-header h3 { font-size: 1.25rem; font-weight: 600; }

        .status-list { list-style: none; }
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .status-item:last-child { border-bottom: none; }
        .item-label { font-size: 0.95rem; }
        .item-label small { display: block; opacity: 0.6; }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef9c3; color: #854d0e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }

        .verdict-banner {
            background: linear-gradient(135deg, var(--primary), #818cf8);
            border-radius: 24px;
            padding: 40px;
            color: white;
            display: flex;
            align-items: center;
            gap: 30px;
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.2);
            margin-top: 40px;
            animation: fadeIn 1s ease;
        }
        .verdict-icon { font-size: 4rem; opacity: 0.9; }
        .verdict-content h2 { margin-bottom: 10px; font-size: 1.8rem; }
        .verdict-content p { font-size: 1.1rem; opacity: 0.9; }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--card-bg);
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            z-index: 1000;
        }

        footer { text-align: center; margin-top: 50px; opacity: 0.5; font-size: 0.9rem; }
    </style>
</head>
<body data-theme="light">
    <button class="theme-toggle" onclick="toggleTheme()" title="Cambiar Tema">
        <i class="fas fa-moon"></i>
    </button>

    <div class="container">
        <header>
            <div class="logo-area"><i class="fas fa-shield-virus"></i></div>
            <h1>Salud de la Infraestructura</h1>
            <p>Diagnóstico en tiempo real del ecosistema CMDB</p>
        </header>

        <div class="grid">
            <!-- PHP Environment -->
            <div class="card" style="animation-delay: 0.1s">
                <div class="card-header"><i class="fab fa-php"></i><h3>Motor PHP</h3></div>
                <ul class="status-list">
                    <li class="status-item">
                        <div class="item-label">Runtime Version <small>Versión del servidor</small></div>
                        <span class="badge badge-<?php echo $audit['php']['status']; ?>"><?php echo $audit['php']['message']; ?></span>
                    </li>
                    <?php 
                    $exts = ['pdo_mysql', 'curl', 'snmp', 'zip'];
                    foreach($exts as $e): 
                        $info = $audit['extensions'][$e];
                    ?>
                    <li class="status-item">
                        <div class="item-label"><?php echo strtoupper($e); ?> <small><?php echo $info['description']; ?></small></div>
                        <span class="badge badge-<?php echo $info['status']; ?>"><?php echo $info['loaded'] ? 'Activo' : 'Cerrado'; ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Databases -->
            <div class="card" style="animation-delay: 0.2s">
                <div class="card-header"><i class="fas fa-database"></i><h3>Almacenamiento</h3></div>
                <ul class="status-list">
                    <li class="status-item">
                        <div class="item-label">MariaDB Engine <small><?php echo $audit['database']['host']; ?></small></div>
                        <span class="badge badge-<?php echo $audit['database']['status']; ?>"><?php echo $audit['database']['connected'] ? 'Online' : 'Offline'; ?></span>
                    </li>
                    <li class="status-item">
                        <div class="item-label">Estructura Tablas <small>Esquema de aplicación</small></div>
                        <?php $missing = count($audit['database']['missing_tables'] ?? []); ?>
                        <span class="badge badge-<?php echo $missing == 0 ? 'success' : 'danger'; ?>">
                            <?php echo $missing == 0 ? 'Íntegra' : "$missing Faltan"; ?>
                        </span>
                    </li>
                    <li class="status-item">
                        <div class="item-label">Zabbix API <small>Conectividad externa</small></div>
                        <span class="badge badge-<?php echo $audit['zabbix']['status']; ?>">Verificado</span>
                    </li>
                </ul>
            </div>

            <!-- Storage & Perms -->
            <div class="card" style="animation-delay: 0.3s">
                <div class="card-header"><i class="fas fa-folder-tree"></i><h3>Sistema de Archivos</h3></div>
                <ul class="status-list">
                    <?php foreach($audit['directories'] as $name => $info): if($name == 'vendor') continue; ?>
                    <li class="status-item">
                        <div class="item-label"><?php echo ucfirst($name); ?> <small><?php echo $info['path']; ?></small></div>
                        <span class="badge badge-<?php echo $info['status']; ?>">
                            <?php echo $info['writable'] ? 'Escritura OK' : 'Bloqueado'; ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <?php 
        $errors = count(array_filter($audit['extensions'], function($e){return $e['status']=='error';})) + 
                 count(array_filter($audit['directories'], function($d){return $d['status']=='error';}));
        $db_ok = ($audit['database']['connected'] && empty($audit['database']['missing_tables']));
        ?>

        <div class="verdict-banner">
            <div class="verdict-icon">
                <?php if ($errors === 0 && $db_ok): ?>
                    <i class="fas fa-check-circle"></i>
                <?php else: ?>
                    <i class="fas fa-exclamation-triangle"></i>
                <?php endif; ?>
            </div>
            <div class="verdict-content">
                <?php if ($errors === 0 && $db_ok): ?>
                    <h2>Sistema Operativo</h2>
                    <p>Todos los servicios internos y externos están funcionando bajo los parámetros óptimos.</p>
                <?php else: ?>
                    <h2>Intervención Requerida</h2>
                    <p>Se han detectado <?php echo $errors; ?> inconsistencias que afectan la portabilidad del sistema.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($is_authorized): ?>
            <!-- REMEDIATION LOGS -->
            <?php if (!empty($log)): ?>
            <div class="card" style="margin-top: 20px; border-left: 5px solid var(--success); animation-delay: 0.4s">
                <div class="card-header"><i class="fas fa-terminal"></i><h3>Log de Ejecución</h3></div>
                <ul class="status-list">
                    <?php foreach($log as $line): ?>
                    <li class="status-item"><small><?php echo $line; ?></small></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- REPAIR ACTION -->
            <div class="card" style="margin-top: 20px; text-align: center; border: 2px dashed var(--warning); animation-delay: 0.5s">
                <div class="card-header" style="justify-content: center;"><i class="fas fa-tools"></i><h3>Acciones de Reparación</h3></div>
                <p style="margin-bottom: 20px; opacity: 0.8">Se intentarán corregir permisos e inicializar tablas faltantes.</p>
                <form method="POST">
                    <button type="submit" name="fix_issues" class="badge" style="background: var(--warning); color: white; border: none; padding: 12px 25px; cursor: pointer; font-size: 1rem;">
                        <i class="fas fa-magic mr-2"></i> Ejecutar Reparación General
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <?php 
        $termCmds = getTerminalSuggestions($audit);
        if (!empty($termCmds)): 
        ?>
        <div class="card" style="margin-top: 20px; background: #000; color: #0f0; border: 1px solid #333; animation-delay: 0.6s">
            <div class="card-header" style="border-bottom: 1px solid #333">
                <i class="fas fa-terminal" style="color: #0f0"></i>
                <h3 style="color: #0f0">Comandos Manuales Recomendados</h3>
            </div>
            <p style="padding: 15px 0 5px 0; font-size: 0.85rem; opacity: 0.7">Ejecuta estos comandos en tu servidor para una corrección definitiva:</p>
            <pre style="white-space: pre-wrap; word-break: break-all; font-family: monospace; font-size: 0.85rem; padding-bottom: 20px;"><code><?php echo implode("\n", $termCmds); ?></code></pre>
        </div>
        <?php endif; ?>

        <footer>
            CMDB Vilaseca Diagnostic Console &bull; <?php echo date('d M Y | H:i'); ?> &bull; v1.2
        </footer>
    </div>

    <script>
        function toggleTheme() {
            const body = document.body;
            const icon = document.querySelector('.theme-toggle i');
            if (body.getAttribute('data-theme') === 'light') {
                body.setAttribute('data-theme', 'dark');
                icon.className = 'fas fa-sun';
                localStorage.setItem('theme', 'dark');
            } else {
                body.setAttribute('data-theme', 'light');
                icon.className = 'fas fa-moon';
                localStorage.setItem('theme', 'light');
            }
        }

        // Init theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.body.setAttribute('data-theme', savedTheme);
        document.querySelector('.theme-toggle i').className = savedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    </script>
</body>
</html>
