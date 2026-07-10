<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../src/auth.php';
$user = current_user();
$cur = basename($_SERVER['SCRIPT_NAME']);
$current_sheet = $_GET['name'] ?? '';
?>
<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4" style="background-color: var(--sonda-navy);">
  <!-- Brand Logo -->
  <a href="<?php echo PUBLIC_URL_PREFIX; ?>/dashboard.php" class="brand-link" style="border-bottom: 1px solid rgba(255,255,255,0.1);">
    <span class="brand-text font-weight-bolder" style="color: #ffffff; letter-spacing: 1px;">
      SONDA <span style="color: var(--sonda-orange);">PRECMDB</span>
    </span>
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
          $activos_list = ['sheet_routers', 'sheet_switches', 'sheet_aps', 'sheet_laptops', 'sheet_servers', 'sheet_datastores', 'sheet_vms'];
          $is_activos_page = ($is_cmdb_page && in_array($current_sheet, $activos_list));
          $is_pasivos_page = ($is_cmdb_page && $current_sheet === 'sheet_pasivos');
          $is_equipos_page = ($is_activos_page || $is_pasivos_page);
        ?>
        
        <?php if (has_role('SUPER_ADMIN') || has_module_access('dashboard')): ?>
        <li class="nav-item">
          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/dashboard.php" class="nav-link <?php echo $cur === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <p>Dashboard</p>
          </a>
        </li>
        <?php endif; ?>

        <!-- CMDB (Graph-Based List) -->
        <?php
          require_once __DIR__ . '/../../src/db.php';
          $pdo = getPDO();
          $cat_stmt = $pdo->query("SELECT id, name, parent_id, icon FROM ci_categories ORDER BY parent_id, name");
          $all_categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
          
          if (!function_exists('buildSidebarTree')) {
              function buildSidebarTree(array $elements, $parentId = 0) {
                  $branch = array();
                  foreach ($elements as $element) {
                      $elementParent = $element['parent_id'] ? $element['parent_id'] : 0;
                      if ($elementParent == $parentId) {
                          $children = buildSidebarTree($elements, $element['id']);
                          if ($children) {
                              $element['children'] = $children;
                          } else {
                              $element['children'] = [];
                          }
                          $branch[$element['id']] = $element;
                      }
                  }
                  return $branch;
              }
          }
          
          if (!function_exists('renderSidebarTreeHTML')) {
              function renderSidebarTreeHTML($nodes, $active_cat_id) {
                  $html = '';
                  foreach ($nodes as $node) {
                      $has_children = !empty($node['children']);
                      
                      $check_active = function($n, $active_id) use (&$check_active) {
                          if ($n['id'] == $active_id) return true;
                          if (!empty($n['children'])) {
                              foreach ($n['children'] as $child) {
                                  if ($check_active($child, $active_id)) return true;
                              }
                          }
                          return false;
                      };
                      $is_node_open = $check_active($node, $active_cat_id);
                      $is_active = ($active_cat_id == $node['id']);
                      
                      $open_class = $is_node_open ? 'menu-is-opening menu-open' : '';
                      $active_class = $is_active ? 'active' : '';
                      $icon = !empty($node['icon']) ? $node['icon'] : ($has_children ? 'fa-folder' : 'fa-cube');
                      if (strpos($icon, 'fa-') === false) $icon = 'fa-' . $icon;
                      
                      $html .= '<li class="nav-item ' . $open_class . '">';
                      $html .= '<a href="' . PUBLIC_URL_PREFIX . '/ci_list.php?category_id=' . $node['id'] . '" class="nav-link ' . $active_class . '" onclick="window.location.href=this.href;">';
                      $html .= '<i class="fas ' . $icon . ' nav-icon ' . ($has_children ? 'text-success' : 'text-warning') . '"></i>';
                      $html .= '<p>' . htmlspecialchars($node['name']);
                      if ($has_children) {
                          $html .= '<i class="right fas fa-angle-left"></i>';
                      }
                      $html .= '</p></a>';
                      
                      if ($has_children) {
                          $html .= '<ul class="nav nav-treeview" style="margin-left: 10px;">';
                          $html .= renderSidebarTreeHTML($node['children'], $active_cat_id);
                          $html .= '</ul>';
                      }
                      $html .= '</li>';
                  }
                  return $html;
              }
          }

          $cat_tree = buildSidebarTree($all_categories);
          
          $is_cmdb_nuevo_active = ($cur === 'ci_list.php');
          $active_cat_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
        ?>
        <?php if (has_module_access('ci_list')): ?>
        <li class="nav-item <?php echo $is_cmdb_nuevo_active ? 'menu-is-opening menu-open' : ''; ?>">
          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/ci_list.php" class="nav-link <?php echo ($is_cmdb_nuevo_active && !$active_cat_id) ? 'active' : ''; ?>" onclick="window.location.href=this.href;">
            <i class="nav-icon fas fa-project-diagram text-primary"></i>
            <p>
              CMDB
              <i class="right fas fa-angle-left"></i>
              <span class="badge badge-info right mr-4">Nuevo</span>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/ci_list.php" class="nav-link <?php echo ($is_cmdb_nuevo_active && !$active_cat_id) ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Todos los CIs</p>
              </a>
            </li>
            <?php echo renderSidebarTreeHTML($cat_tree, $active_cat_id); ?>
          </ul>
        </li>
        <?php endif; ?>

        <?php
          $has_any_sheet_access = false;
          foreach ($sheet_tables as $table) {
              if (has_sheet_access($table)) {
                  $has_any_sheet_access = true;
                  break;
              }
          }

          $has_any_activo_access = false;
          foreach ($activos_list as $activo_type) {
              if (has_sheet_access($activo_type)) {
                  $has_any_activo_access = true;
                  break;
              }
          }

          $has_pasivos_access = has_sheet_access('sheet_pasivos');
          $has_equipos_access = $has_any_activo_access || $has_pasivos_access;
        ?>

        <?php if ($has_any_sheet_access): ?>
        <li class="nav-item <?php echo $is_cmdb_page ? 'menu-is-opening menu-open' : ''; ?>">
          <a href="#" class="nav-link <?php echo $is_cmdb_page ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-database"></i>
            <p>
              PRECMDB
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <!-- Equipos Menu -->
            <?php if ($has_equipos_access): ?>
            <li class="nav-item <?php echo $is_equipos_page ? 'menu-is-opening menu-open' : ''; ?>">
              <a href="#" class="nav-link <?php echo $is_equipos_page ? 'active' : ''; ?>">
                <i class="nav-icon fas fa-desktop"></i>
                <p>
                  Equipos
                  <i class="right fas fa-angle-left"></i>
                </p>
              </a>
              <ul class="nav nav-treeview" style="margin-left: 10px;">
                <?php if ($has_any_activo_access): ?>
                <li class="nav-item <?php echo $is_activos_page ? 'menu-is-opening menu-open' : ''; ?>">
                  <a href="#" class="nav-link <?php echo $is_activos_page ? 'active' : ''; ?>">
                    <i class="nav-icon fas fa-hdd"></i>
                    <p>
                      Activos
                      <i class="right fas fa-angle-left"></i>
                    </p>
                  </a>
                  <ul class="nav nav-treeview" style="margin-left: 10px;">
                    <?php foreach ($activos_list as $activo_type): ?>
                      <?php if (has_sheet_access($activo_type)): ?>
                        <?php $sheet_name_clean = ucfirst(str_replace('sheet_', '', $activo_type)); ?>
                        <li class="nav-item">
                          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/cmdb.php?name=<?php echo urlencode($activo_type); ?>" class="nav-link <?php echo $current_sheet === $activo_type ? 'active' : ''; ?>">
                            <i class="far fa-circle nav-icon text-success"></i>
                            <p><?php echo $sheet_name_clean; ?></p>
                          </a>
                        </li>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </ul>
                </li>
                <?php endif; ?>
                
                <?php if ($has_pasivos_access): ?>
                <li class="nav-item">
                  <a href="<?php echo PUBLIC_URL_PREFIX; ?>/cmdb.php?name=sheet_pasivos" class="nav-link <?php echo $is_pasivos_page ? 'active' : ''; ?>">
                    <i class="nav-icon fas fa-plug"></i>
                    <p>Pasivos</p>
                  </a>
                </li>
                <?php endif; ?>
              </ul>
            </li>
            <?php endif; ?>

            <!-- Original Sheets -->
            <?php foreach ($sheet_tables as $table): ?>
              <?php 
                $sheet_name_clean = preg_replace('/^sheet_/', '', $table);
                // Evitar duplicados si las tablas dinámicas coinciden con 'equipos'
                if (in_array($table, $activos_list) || $table === 'sheet_pasivos') continue; 
              ?>
              <?php if (has_sheet_access($table)): ?>
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
        <?php endif; ?>

        <?php
          $is_datacenter_open = in_array($cur, ['rooms.php', 'racks.php', 'rack_builder.php', 'analisis.php']);
        ?>
        <?php if (has_module_access('datacenter')): ?>
        <li class="nav-item <?php echo $is_datacenter_open ? 'menu-open' : ''; ?>">
          <a href="#" class="nav-link <?php echo $is_datacenter_open ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-building text-warning"></i>
            <p>
              Datacenter (DCIM)
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/datacenter/rooms.php" class="nav-link <?php echo $cur === 'rooms.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Cuartos / Rooms</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/datacenter/racks.php" class="nav-link <?php echo $cur === 'racks.php' || $cur === 'rack_builder.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Racks (Gabinetes)</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/datacenter/analisis.php" class="nav-link <?php echo $cur === 'analisis.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-info"></i>
                <p>Análisis Datacenter</p>
              </a>
            </li>
          </ul>
        </li>
        <?php endif; ?>

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

        <!-- Módulo Proyectos -->
        <?php if (has_module_access('project')): ?>
        <li class="nav-item">
          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/project.php" class="nav-link <?php echo $cur === 'project.php' ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-tasks text-success"></i>
            <p>Proyectos</p>
          </a>
        </li>
        <?php endif; ?>

        <!-- Módulo Diagramas (Grupo de diagramación y topologías) -->
        <?php if (has_module_access('diagrams') || has_module_access('topology') || has_module_access('portmapping')): ?>
        <?php 
          $diag_active = in_array($cur, ['flujos.php', 'bpmn.php', 'visio.php', 'topology.php', 'topology_3d.php', 'portmapping.php']);
        ?>
        <li class="nav-item <?php echo $diag_active ? 'menu-open' : ''; ?>">
          <a href="#" class="nav-link <?php echo $diag_active ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-project-diagram text-primary"></i>
            <p>
              Diagramas
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <?php if (has_module_access('diagrams')): ?>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/flujos.php" class="nav-link <?php echo $cur === 'flujos.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-info"></i>
                <p>Flujos (Mermaid)</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/bpmn.php" class="nav-link <?php echo $cur === 'bpmn.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-warning"></i>
                <p>Procesos (BPMN)</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/visio.php" class="nav-link <?php echo $cur === 'visio.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-success"></i>
                <p>Modelos Visio (VSDX)</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (has_module_access('topology')): ?>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/topology.php" class="nav-link <?php echo $cur === 'topology.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-primary"></i>
                <p>Topología (2D)</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/topology_3d.php" class="nav-link <?php echo $cur === 'topology_3d.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-danger"></i>
                <p>Topología (3D)</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (has_module_access('portmapping')): ?>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/portmapping.php" class="nav-link <?php echo $cur === 'portmapping.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-warning"></i>
                <p>Portmapping</p>
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <!-- Módulo PASSWORD -->
        <?php if (has_module_access('password')): ?>
        <li class="nav-item">
          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/password.php" class="nav-link <?php echo $cur === 'password.php' ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-key text-warning"></i>
            <p>PASSWORD</p>
          </a>
        </li>
        <?php endif; ?>

        <!-- Módulo Cotizador -->
        <?php if (has_module_access('cotizador')): ?>
        <?php 
          $is_cotizador = (strpos($_SERVER['SCRIPT_NAME'], '/cotizador/') !== false);
          $active_sub = $is_cotizador ? ($_GET['tab'] ?? 'configurador') : '';
        ?>
        <li class="nav-item <?php echo $is_cotizador ? 'menu-open' : ''; ?>">
          <a href="#" class="nav-link <?php echo $is_cotizador ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-calculator text-info"></i>
            <p>
              Cotizador
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/cotizador/index.php?tab=configurador" class="nav-link <?php echo ($is_cotizador && $active_sub === 'configurador') ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-primary"></i>
                <p>Configurador</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/cotizador/index.php?tab=editor" class="nav-link <?php echo ($is_cotizador && $active_sub === 'editor') ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-info"></i>
                <p>Diseño Cotización</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/cotizador/index.php?tab=list" class="nav-link <?php echo ($is_cotizador && $active_sub === 'list') ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-success"></i>
                <p>Historial Cotizaciones</p>
              </a>
            </li>
          </ul>
        </li>
        <?php endif; ?>

        <?php if (has_module_access('snmp')): ?>
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
        <?php endif; ?>

        <?php if (has_module_access('import')): ?>
        <li class="nav-item">
            <a href="<?php echo PUBLIC_URL_PREFIX; ?>/import.php" class="nav-link <?php echo $cur === 'import.php' ? 'active' : ''; ?>">
                <i class="nav-icon fas fa-file-excel"></i>
                <p>Importar Excel</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if (has_module_access('reports')): ?>
        <li class="nav-item">
          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/reports_list.php" class="nav-link <?php echo ($cur === 'reports_list.php' || strpos($_SERVER['SCRIPT_NAME'], '/informes/') !== false) ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-file-invoice text-teal"></i>
            <p>Informes</p>
          </a>
        </li>
        <?php endif; ?>

        <!-- Módulo de Análisis de Logs -->
        <?php if (has_module_access('log_analysis')): ?>
        <li class="nav-item">
          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/log_analysis.php" class="nav-link <?php echo $cur === 'log_analysis.php' ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-terminal text-warning"></i>
            <p>Análisis de Logs</p>
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

        <?php if (has_role(['SUPER_ADMIN'])): ?>
        <?php 
          $admin_pages = ['sheet_configs.php', 'snmp_management.php', 'system_health.php', 'user_management.php', 'ci_builder.php', 'ci_categories.php', 'ci_relationships.php'];
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
            <!-- Sub-pestaña CMDB Admin -->
            <li class="nav-item <?php echo in_array($cur, ['ci_builder.php', 'ci_categories.php', 'ci_attributes.php', 'ci_relationships.php']) ? 'menu-is-opening menu-open' : ''; ?>">
              <a href="#" class="nav-link <?php echo in_array($cur, ['ci_builder.php', 'ci_categories.php', 'ci_attributes.php', 'ci_relationships.php']) ? 'active' : ''; ?>">
                <i class="nav-icon fas fa-layer-group text-primary"></i>
                <p>
                  CMDB Admin
                  <i class="right fas fa-angle-left"></i>
                </p>
              </a>
              <ul class="nav nav-treeview" style="margin-left: 10px;">
                <li class="nav-item">
                  <a href="<?php echo PUBLIC_URL_PREFIX; ?>/ci_categories.php" class="nav-link <?php echo $cur === 'ci_categories.php' ? 'active' : ''; ?>">
                    <i class="far fa-circle nav-icon text-warning"></i>
                    <p>CATEGORÍAS</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="<?php echo PUBLIC_URL_PREFIX; ?>/ci_relationships.php" class="nav-link <?php echo $cur === 'ci_relationships.php' ? 'active' : ''; ?>">
                    <i class="far fa-circle nav-icon text-danger"></i>
                    <p>Tipos de Relación</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="<?php echo PUBLIC_URL_PREFIX; ?>/ci_attributes.php" class="nav-link <?php echo $cur === 'ci_attributes.php' ? 'active' : ''; ?>">
                    <i class="far fa-circle nav-icon text-info"></i>
                    <p>Atributos Globales</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="<?php echo PUBLIC_URL_PREFIX; ?>/ci_builder.php" class="nav-link <?php echo $cur === 'ci_builder.php' ? 'active' : ''; ?>">
                    <i class="far fa-circle nav-icon text-success"></i>
                    <p>Crear CI</p>
                  </a>
                </li>
              </ul>
            </li>
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
