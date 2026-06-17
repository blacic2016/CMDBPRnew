<?php
/**
 * Panel de Informes - CMDB VILASECA
 * Ubicación: /var/www/html/VILASECA/CMDBPRnew/public/reports_list.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';

require_login(); 

$page_title = 'Módulo de Informes';
require_once __DIR__ . '/partials/header.php'; 
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<style>
    .report-card {
        border-radius: 12px;
        border: 1px solid var(--border-color);
        background: var(--card-bg);
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        overflow: hidden;
    }
    .report-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.12) !important;
        border-color: var(--sonda-cyan);
    }
    body.dark-mode .report-card {
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }
    body.dark-mode .report-card:hover {
        box-shadow: 0 8px 30px rgba(0, 184, 212, 0.2) !important;
    }
    .icon-wrapper {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin-bottom: 20px;
        background: rgba(255, 92, 5, 0.1);
        color: var(--sonda-orange);
    }
    .icon-wrapper-cyan {
        background: rgba(0, 184, 212, 0.1);
        color: var(--sonda-cyan);
    }
    .icon-wrapper-green {
        background: rgba(192, 218, 32, 0.1);
        color: var(--sonda-green);
    }
    .report-title {
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 10px;
        color: var(--text-color);
    }
    .report-description {
        font-size: 0.9rem;
        color: var(--text-muted);
        line-height: 1.5;
        flex-grow: 1;
        margin-bottom: 20px;
    }
    .card-footer-action {
        border-top: 1px solid var(--border-color);
        padding-top: 15px;
        background: transparent;
    }
</style>

<div class="container-fluid pt-4">
    <!-- Header -->
    <div class="row mb-4 animate__animated animate__fadeIn">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h1 class="h3 font-weight-bold text-dark mb-1">Módulo de Informes de Monitoreo</h1>
                    <p class="text-muted mb-0">Seleccione un informe disponible para consultar la disponibilidad y rendimiento de la plataforma.</p>
                </div>
                <div>
                    <span class="badge badge-info p-2"><i class="fas fa-info-circle mr-1"></i> Conexión Zabbix Activa</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Reports Grid -->
    <div class="row">
        <!-- Report 1: Availability by Groups -->
        <div class="col-lg-3 col-md-6 mb-4 animate__animated animate__fadeInLeft">
            <div class="report-card p-4">
                <div>
                    <div class="icon-wrapper">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <h5 class="report-title">Disponibilidad-Ping (Por Grupos)</h5>
                    <p class="report-description">
                        Informe consolidado que calcula el porcentaje de disponibilidad promedio de los principales canales o grupos de hosts utilizando estados de trigger de ping ICMP. Permite exportar a PDF y visualizar comparaciones en gráficos.
                    </p>
                </div>
                <div class="card-footer-action">
                    <a href="informes/grupos.php" class="btn btn-block btn-primary">
                        <i class="fas fa-chart-bar mr-2"></i> Abrir Informe
                    </a>
                </div>
            </div>
        </div>

        <!-- Report 2: Availability by Hosts -->
        <div class="col-lg-3 col-md-6 mb-4 animate__animated animate__fadeInUp">
            <div class="report-card p-4">
                <div>
                    <div class="icon-wrapper icon-wrapper-cyan">
                        <i class="fas fa-desktop"></i>
                    </div>
                    <h5 class="report-title">Disponibilidad-Ping (Por Equipos)</h5>
                    <p class="report-description">
                        Análisis detallado día por día de cada host perteneciente a un grupo seleccionado. Permite identificar de manera delgada fallos en hosts específicos, tiempos caídos y lista completa de eventos de caída por ICMP ping.
                    </p>
                </div>
                <div class="card-footer-action">
                    <a href="informes/index.php" class="btn btn-block btn-info">
                        <i class="fas fa-search-plus mr-2"></i> Abrir Informe
                    </a>
                </div>
            </div>
        </div>

        <!-- Report 3: Alarm Distribution by Hosts -->
        <div class="col-lg-3 col-md-6 mb-4 animate__animated animate__fadeInUp">
            <div class="report-card p-4">
                <div>
                    <div class="icon-wrapper" style="background: rgba(220, 53, 69, 0.1); color: var(--danger);">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h5 class="report-title">Distribución de Alarmas</h5>
                    <p class="report-description">
                        Informe cuantitativo de alarmas por equipo y tipo. Visualice la cantidad de eventos y su severidad (Advertencia, Promedio, Alta, Desastre) en gráficos interactivos distribuidos.
                    </p>
                </div>
                <div class="card-footer-action">
                    <a href="informes/alarmas.php" class="btn btn-block btn-danger">
                        <i class="fas fa-chart-pie mr-2"></i> Abrir Informe
                    </a>
                </div>
            </div>
        </div>

        <!-- Report 4: Scope & Templates Monitoring -->
        <div class="col-lg-3 col-md-6 mb-4 animate__animated animate__fadeInUp">
            <div class="report-card p-4">
                <div>
                    <div class="icon-wrapper" style="background: rgba(156, 39, 176, 0.1); color: #9c27b0;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h5 class="report-title">Alcance y Plantillas</h5>
                    <p class="report-description">
                        Informe integral que clasifica los dispositivos por tipo (routers, switches, servidores, etc.) y estado. Incluye consulta de métricas por equipo y las alertas de la plantilla principal más utilizada.
                    </p>
                </div>
                <div class="card-footer-action">
                    <a href="informes/alcance.php" class="btn btn-block text-white" style="background-color: #9c27b0; border-color: #9c27b0;">
                        <i class="fas fa-tasks mr-2"></i> Abrir Informe
                    </a>
                </div>
            </div>
        </div>

        <!-- Report 5: Existing Customized Technical Reports -->
        <div class="col-lg-3 col-md-6 mb-4 animate__animated animate__fadeInRight">
            <div class="report-card p-4">
                <div>
                    <div class="icon-wrapper icon-wrapper-green">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <h5 class="report-title">Reportes Técnicos Personalizados</h5>
                    <p class="report-description">
                        Generador interactivo basado en consultas generales a Zabbix. Filtre por nombres, hostgroups, tags y tipos de verificaciones de estado (ICMP, SNMP, Agent) para generar reportes dinámicos.
                    </p>
                </div>
                <div class="card-footer-action">
                    <a href="reports_zabbix.php" class="btn btn-block btn-dark" style="background-color: var(--sonda-navy); border-color: var(--sonda-navy);">
                        <i class="fas fa-file-excel mr-2"></i> Abrir Generador
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
