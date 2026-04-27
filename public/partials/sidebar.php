<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../src/auth.php';
$user = current_user();
$cur = basename($_SERVER['SCRIPT_NAME']);
$current_sheet = $_GET['name'] ?? '';
?>
<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-light-primary elevation-4">
  <!-- Brand Logo -->
  <a href="<?php echo PUBLIC_URL_PREFIX; ?>/dashboard.php" class="brand-link">
    <span class="brand-text font-weight-light">CMDB Vilaseca</span>
  </a>

  <!-- Sidebar -->
  <div class="sidebar">
    <!-- Sidebar user panel (optional) -->
    <div class="user-panel mt-3 pb-3 mb-3 d-flex">
      <div class="info">
        <a href="#" class="d-block"><?php echo htmlspecialchars($user['username'] ?? 'Usuario'); ?></a>
      </div>
    </div>

    <!-- Sidebar Menu -->
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
        <?php
          require_once __DIR__ . '/../../src/helpers.php';
          require_once __DIR__ . '/../../src/permissions_helper.php';
          $sheet_tables = listSheetTables();
          $is_cmdb_page = ($cur === 'cmdb.php' || $cur === 'item_detail.php' || $cur === 'history.php');
        ?>
        
        <?php if (has_role('SUPER_ADMIN') || has_module_access('dashboard')): ?>
        <li class="nav-item">
          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/dashboard.php" class="nav-link <?php echo $cur === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <p>Dashboard</p>
          </a>
        </li>
        <?php endif; ?>
        
        <li class="nav-item <?php echo $is_cmdb_page ? 'menu-is-opening menu-open' : ''; ?>">
          <a href="#" class="nav-link <?php echo $is_cmdb_page ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-database"></i>
            <p>
              CMDB
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <?php foreach ($sheet_tables as $table): ?>
              <?php if (has_sheet_access($table)): ?>
                <?php $sheet_name_clean = preg_replace('/^sheet_/', '', $table); ?>
                <li class="nav-item">
                  <a href="<?php echo PUBLIC_URL_PREFIX; ?>/cmdb.php?name=<?php echo urlencode($table); ?>" class="nav-link <?php echo $current_sheet === $table ? 'active' : ''; ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p><?php echo htmlspecialchars(ucfirst($sheet_name_clean)); ?></p>
                  </a>
                </li>
              <?php endif; ?>
            <?php endforeach; ?>
          </ul>
        </li>

        <?php if (has_module_access('import')): ?>
        <li class="nav-item">
            <a href="<?php echo PUBLIC_URL_PREFIX; ?>/import.php" class="nav-link <?php echo $cur === 'import.php' ? 'active' : ''; ?>">
                <i class="nav-icon fas fa-file-excel"></i>
                <p>Importar Excel</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if (has_module_access('distribrack')): ?>
        <li class="nav-item">
          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/distribrack.php" class="nav-link <?php echo $cur === 'distribrack.php' ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-images"></i>
            <p>Imagenes</p>
          </a>
        </li>
        <?php endif; ?>

        <?php if (has_module_access('topology')): ?>
        <li class="nav-item">
          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/topology.php" class="nav-link <?php echo $cur === 'topology.php' ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-project-diagram"></i>
            <p>Topología</p>
          </a>
        </li>
        <?php endif; ?>

        <li class="nav-item <?php echo in_array($cur, ['snmp_management.php', 'snmp_builder.php', 'snmp_mibs.php']) ? 'menu-open' : ''; ?>">
          <a href="#" class="nav-link <?php echo in_array($cur, ['snmp_management.php', 'snmp_builder.php', 'snmp_mibs.php']) ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-network-wired text-info"></i>
            <p>
              Módulo SNMP
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/snmp_management.php" class="nav-link <?php echo $cur === 'snmp_management.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-primary"></i>
                <p>Gestión / Escaneo</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/snmp_builder.php" class="nav-link <?php echo $cur === 'snmp_builder.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-info"></i>
                <p>SNMP Builder</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/snmp_mibs.php" class="nav-link <?php echo $cur === 'snmp_mibs.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-success"></i>
                <p>Repositorio MIBs</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/snmp_mibs_analysis.php" class="nav-link <?php echo $cur === 'snmp_mibs_analysis.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-warning"></i>
                <p>Análisis MIB</p>
              </a>
            </li>
          </ul>
        </li>

        <?php
          $is_zabbix_page = in_array($cur, ['zabbix_dashboard.php', 'zabbix_hosts.php', 'reports_zabbix.php', 'monitoreo.php', 'crear_monitoreo.php', 'actualizar_monitoreo.php', 'problems.php', 'interfaces_manager.php']) || 
                            strpos($_SERVER['SCRIPT_NAME'], '/costos/') !== false || 
                            strpos($_SERVER['SCRIPT_NAME'], '/storage/') !== false ||
                            strpos($_SERVER['SCRIPT_NAME'], '/kanbanzabbix/') !== false;
        ?>
        <?php if (has_module_access('monitoreo')): ?>
        <li class="nav-item <?php echo $is_zabbix_page ? 'menu-is-opening menu-open' : ''; ?>">
          <a href="#" class="nav-link <?php echo $is_zabbix_page ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-server"></i>
            <p>
              Zabbix
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/kanbanzabbix/index.php" class="nav-link <?php echo strpos($_SERVER['SCRIPT_NAME'], '/kanbanzabbix/') !== false ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-danger"></i>
                <p>Kanban Zabbix</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/monitoreo.php" class="nav-link <?php echo in_array($cur, ['monitoreo.php', 'problems.php']) ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Dashboard</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/zabbix_hosts.php" class="nav-link <?php echo $cur === 'zabbix_hosts.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Equipos</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/reports_zabbix.php" class="nav-link <?php echo $cur === 'reports_zabbix.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-success"></i>
                <p>Informes</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/interfaces_manager.php" class="nav-link <?php echo $cur === 'interfaces_manager.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-primary"></i>
                <p>Gestión Interfaces</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/costos/dashboard.php" class="nav-link <?php echo strpos($_SERVER['SCRIPT_NAME'], '/costos/') !== false ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-warning"></i>
                <p>Costos ZBX</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/storage/dashboard.php" class="nav-link <?php echo strpos($_SERVER['SCRIPT_NAME'], '/storage/') !== false ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-info"></i>
                <p>Análisis Storage</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/crear_monitoreo.php" class="nav-link <?php echo $cur === 'crear_monitoreo.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-muted"></i>
                <p><small>Asistente Creación</small></p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/actualizar_monitoreo.php" class="nav-link <?php echo $cur === 'actualizar_monitoreo.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-muted"></i>
                <p><small>Asistente Actualización</small></p>
              </a>
            </li>
          </ul>
        </li>
        <?php endif; ?>

        <?php if (has_role(['SUPER_ADMIN'])): ?>
        <?php 
          $admin_pages = ['sheet_configs.php', 'snmp_management.php', 'system_health.php', 'user_management.php'];
          $is_admin_open = in_array($cur, $admin_pages);
        ?>
        <li class="nav-item <?php echo $is_admin_open ? 'menu-open' : ''; ?>">
          <a href="#" class="nav-link <?php echo $is_admin_open ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-cogs"></i>
            <p>
              Administración
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/user_management.php" class="nav-link <?php echo $cur === 'user_management.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-primary"></i>
                <p>Gestión Usuarios</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/sheet_configs.php" class="nav-link <?php echo $cur === 'sheet_configs.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-info"></i>
                <p>Config. Claves</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/system_health.php" class="nav-link <?php echo $cur === 'system_health.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-danger"></i>
                <p>Salud del Sistema</p>
              </a>
            </li>
          </ul>
        </li>
        <?php endif; ?>
      </ul>
    </nav>
    <!-- /.sidebar-menu -->
  </div>
  <!-- /.sidebar -->
</aside>
