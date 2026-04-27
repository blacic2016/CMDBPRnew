<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="card shadow-sm mb-4 border-0" style="border-radius: 12px; overflow: hidden;">
    <div class="card-body p-0">
        <ul class="nav nav-pills nav-fill bg-light p-2" id="snmpTabs" style="border-radius: 12px;">
            <li class="nav-item">
                <a class="nav-link <?= ($current_page == 'snmp_management.php') ? 'active bg-primary font-weight-bold shadow-sm' : 'text-dark' ?> py-3" href="snmp_management.php">
                    <i class="fas fa-search-location mr-2"></i> GESTIÓN Y ESCANEO
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($current_page == 'snmp_builder.php') ? 'active bg-info font-weight-bold shadow-sm' : 'text-dark' ?> py-3" href="snmp_builder.php">
                    <i class="fas fa-tools mr-2"></i> SNMP BUILDER (OIDs)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($current_page == 'snmp_mibs.php') ? 'active bg-success font-weight-bold shadow-sm' : 'text-dark' ?> py-3" href="snmp_mibs.php">
                    <i class="fas fa-file-upload mr-2"></i> SUBIR / GESTIONAR MIBs
                </a>
            </li>
        </ul>
    </div>
</div>

<style>
    #snmpTabs .nav-link {
        transition: all 0.3s ease;
        border-radius: 8px;
        margin: 0 5px;
    }
    #snmpTabs .nav-link:not(.active):hover {
        background-color: rgba(0,0,0,0.05);
        color: #007bff !important;
    }
    #snmpTabs .nav-link.active {
        color: #fff !important;
    }
    /* Asegurar que las pestañas sean visibles en modo oscuro */
    .dark-mode #snmpTabs {
        background-color: #343a40 !important;
    }
    .dark-mode #snmpTabs .nav-link:not(.active) {
        color: #ced4da !important;
    }
</style>
