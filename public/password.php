<?php
/**
 * PASSWORD Module - CMDB VILASECA
 * Modern & Secure Password Manager with Master Key encryption and auto-screenshot capability
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/permissions_helper.php';
require_once __DIR__ . '/../src/password_vault_helper.php';

// Validar login
require_login();

// Validar permisos
if (!has_role(['SUPER_ADMIN', 'ADMIN']) && !has_module_access('password')) {
    http_response_code(403);
    die("Acceso denegado. No tienes permisos para el módulo PASSWORD.");
}

$page_title = "PASSWORD - Gestor de Accesos y Claves";
$hide_content_header = true;
include 'partials/header.php';
?>

<style>
/* Theme Custom Variables */
:root {
    --vault-primary: #0a2540;
    --vault-accent: #f57c00;
    --vault-cyan: #00b8d2;
    --card-shadow: 0 6px 18px rgba(10, 37, 64, 0.05);
    --card-hover-shadow: 0 12px 28px rgba(0, 184, 212, 0.12);
    --bg-vault-card: #ffffff;
    --bg-vault-muted: #f8f9fa;
    --text-muted-vault: #6c757d;
    --border-vault: #e9ecef;
}

body.dark-mode {
    --vault-primary: #0f1c2c;
    --vault-accent: #f57c00;
    --vault-cyan: #00b8d2;
    --card-shadow: 0 6px 18px rgba(0, 0, 0, 0.35);
    --card-hover-shadow: 0 12px 28px rgba(0, 184, 212, 0.18);
    --bg-vault-card: #182232;
    --bg-vault-muted: #111a24;
    --text-muted-vault: #a0aec0;
    --border-vault: #2d3748;
}

/* Base Styles */
.vault-container {
    padding: 1.5rem;
    font-family: 'Kumbh Sans', sans-serif;
}

/* Glassmorphism Screens (Setup / Lock) */
.glass-screen-container {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: calc(100vh - 200px);
}

.glass-card {
    background: var(--bg-vault-card);
    border: 1px solid var(--border-vault);
    box-shadow: var(--card-shadow);
    border-radius: 16px;
    padding: 2.5rem;
    width: 100%;
    max-width: 480px;
    transition: all 0.3s;
    text-align: center;
}

.glass-card:hover {
    box-shadow: var(--card-hover-shadow);
}

.vault-lock-icon {
    font-size: 3.5rem;
    color: var(--vault-accent);
    margin-bottom: 1.5rem;
    animation: pulseLock 2s infinite ease-in-out;
}

@keyframes pulseLock {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

/* Workspace Layout */
.vault-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 1.5rem;
}

@media (max-width: 992px) {
    .vault-layout {
        grid-template-columns: 1fr;
    }
}

.vault-sidebar {
    background: var(--bg-vault-card);
    border: 1px solid var(--border-vault);
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: var(--card-shadow);
    height: fit-content;
}

.tag-badge-pill {
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.8rem;
    margin: 3px;
    padding: 6px 12px;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background-color: var(--bg-vault-muted);
    border: 1px solid var(--border-vault);
    color: var(--text-color);
}

.tag-badge-pill:hover, .tag-badge-pill.active {
    background-color: var(--vault-cyan);
    color: #fff !important;
    border-color: var(--vault-cyan);
}

.vault-search-wrapper {
    position: relative;
}

.vault-search-wrapper .fa-search {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted-vault);
}

.vault-search-input {
    padding-left: 42px;
    border-radius: 50px;
    border: 1px solid var(--border-vault);
    background-color: var(--bg-vault-card);
    height: 48px;
    font-size: 0.95rem;
    width: 100%;
    transition: all 0.3s;
}

.vault-search-input:focus {
    box-shadow: 0 0 0 3px rgba(0, 184, 212, 0.2);
    border-color: var(--vault-cyan);
    outline: none;
}

/* Card Grid Styles */
.pw-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.25rem;
    margin-top: 1.25rem;
}

.pw-card {
    background: var(--bg-vault-card);
    border: 1px solid var(--border-vault);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: var(--card-shadow);
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    display: flex;
    flex-direction: column;
    position: relative;
}

.pw-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--card-hover-shadow);
    border-color: var(--vault-cyan);
}

.pw-card-img-wrapper {
    height: 160px;
    position: relative;
    overflow: hidden;
    background-color: var(--bg-vault-muted);
    border-bottom: 1px solid var(--border-vault);
}

.pw-card-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s;
}

.pw-card:hover .pw-card-img {
    transform: scale(1.04);
}

.pw-card-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 2;
    background: rgba(0, 42, 84, 0.8);
    backdrop-filter: blur(4px);
    color: #fff;
    font-size: 0.75rem;
    padding: 4px 8px;
    border-radius: 4px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.pw-card-body {
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
}

.pw-card-title {
    font-size: 1.15rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    color: var(--text-color);
}

.pw-card-url {
    font-size: 0.82rem;
    color: var(--vault-cyan);
    margin-bottom: 0.75rem;
    text-overflow: ellipsis;
    overflow: hidden;
    white-space: nowrap;
    display: block;
}

.pw-card-obs {
    font-size: 0.88rem;
    color: var(--text-muted-vault);
    margin-bottom: 1rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.6rem;
}

.pw-card-tags {
    margin-bottom: 1rem;
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.pw-card-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid var(--border-vault);
    padding-top: 0.75rem;
    margin-top: auto;
}

/* Password details drop down or drawer inside card */
.pw-reveal-panel {
    background-color: var(--bg-vault-muted);
    border-radius: 6px;
    padding: 8px 12px;
    margin-bottom: 1rem;
    font-family: monospace;
    font-size: 0.85rem;
    border: 1px dashed var(--border-vault);
}

.btn-icon-only {
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 0.85rem;
}

/* History Timeline inside Modal */
.timeline-vault {
    position: relative;
    padding-left: 20px;
    border-left: 2px solid var(--border-vault);
    margin-left: 10px;
}

.timeline-vault-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-vault-item::before {
    content: '';
    position: absolute;
    left: -27px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background-color: var(--vault-cyan);
    border: 2px solid var(--bg-vault-card);
}

.timeline-vault-item.creation::before {
    background-color: #28a745;
}

.timeline-vault-item.modification::before {
    background-color: #ffc107;
}

/* Custom Alert floating banner style */
.floating-overlay-banner {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: var(--vault-primary);
    color: #fff;
    padding: 15px 25px;
    border-radius: 8px;
    z-index: 9999;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    border: 1px solid var(--vault-cyan);
    display: none;
    animation: slideInUp 0.3s;
}

@keyframes slideInUp {
    from { transform: translateY(100px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.vault-dropzone {
    border: 2px dashed var(--border-vault);
    background-color: var(--bg-vault-muted);
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
    min-height: 140px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.vault-dropzone:hover, .vault-dropzone.dragover {
    border-color: var(--vault-cyan);
    background-color: rgba(0, 184, 212, 0.05);
}

.vault-dropzone-preview {
    max-width: 100%;
    max-height: 120px;
    object-fit: contain;
    border-radius: 4px;
    display: none;
    margin-top: 8px;
}

.vault-dropzone-placeholder {
    color: var(--text-muted-vault);
    font-size: 0.85rem;
}
</style>

<div class="vault-container">

    <!-- OVERLAYS: LOCK SCREEN / SETUP -->
    <div id="vault-screen-wrapper" class="d-none">
        
        <!-- Setup Screen (Not Initialized) -->
        <div id="screen-setup" class="glass-screen-container d-none">
            <div class="glass-card shadow">
                <div class="vault-lock-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 class="font-weight-bold mb-2">Inicializar Baúl</h3>
                <p class="text-muted mb-4">Define una contraseña maestra robusta para encriptar los accesos de la infraestructura. Esta contraseña nunca se guarda en texto claro.</p>
                <form id="form-setup-vault">
                    <div class="form-group text-left">
                        <label class="font-weight-bold">Nueva Contraseña Maestra *</label>
                        <input type="password" class="form-control" id="setup-master-password" required placeholder="Min. 6 caracteres">
                    </div>
                    <div class="form-group text-left">
                        <label class="font-weight-bold">Confirmar Contraseña *</label>
                        <input type="password" class="form-control" id="setup-master-password-confirm" required placeholder="Repite la contraseña">
                    </div>
                    <button type="submit" class="btn btn-success btn-block py-2 font-weight-bold">
                        <i class="fas fa-key mr-2"></i>Inicializar y Desbloquear
                    </button>
                </form>
            </div>
        </div>

        <!-- Lock Screen (Initialized but locked) -->
        <div id="screen-lock" class="glass-screen-container d-none">
            <div class="glass-card shadow">
                <div class="vault-lock-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <h3 class="font-weight-bold mb-2">Baúl Bloqueado</h3>
                <p class="text-muted mb-4">Ingresa la contraseña maestra para desencriptar y gestionar las claves de acceso.</p>
                <form id="form-unlock-vault">
                    <div class="form-group">
                        <input type="password" class="form-control text-center" id="unlock-master-password" required placeholder="Contraseña Maestra">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block py-2 font-weight-bold">
                        <i class="fas fa-unlock-alt mr-2"></i>Desbloquear Baúl
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- MAIN WORKSPACE -->
    <div id="vault-workspace" class="d-none">
        
        <!-- Workspace header toolbar -->
        <div class="row align-items-center mb-4">
            <div class="col-md-6">
                <div class="vault-search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="search-vault-entries" class="vault-search-input" placeholder="Buscar por nombre, URL, observaciones o tags...">
                </div>
            </div>
            <div class="col-md-6 text-md-right mt-3 mt-md-0 d-flex justify-content-md-end gap-2" style="gap: 8px;">
                <button class="btn btn-info font-weight-bold" onclick="showVaultConfigModal()" title="Configuración de Seguridad">
                    <i class="fas fa-cog mr-1"></i> Configuración
                </button>
                <button class="btn btn-success font-weight-bold" onclick="openAddEntryModal()">
                    <i class="fas fa-plus mr-1"></i> Agregar Acceso
                </button>
            </div>
        </div>

        <div class="vault-layout">
            <!-- Left Sidebar (Classification Tags) -->
            <div class="vault-sidebar">
                <h5 class="font-weight-bold border-bottom pb-2 mb-3">
                    <i class="fas fa-tags text-cyan mr-1"></i> Clasificación Tags
                </h5>
                <div id="tags-filter-container">
                    <span class="tag-badge-pill active" onclick="filterByTag('')">
                        <i class="fas fa-th-list"></i> Todos los accesos
                    </span>
                    <!-- Dynamic tags loaded via Ajax -->
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="vault-content">
                <div id="entries-loader" class="text-center py-5">
                    <div class="spinner-border text-info" role="status"></div>
                    <p class="text-muted mt-2">Cargando y desencriptando baúl...</p>
                </div>
                
                <div id="no-entries-card" class="card p-5 text-center d-none" style="border-radius: 12px; border: 1px dashed var(--border-vault);">
                    <i class="fas fa-key fa-3x text-muted mb-3"></i>
                    <h5>No hay accesos registrados en el baúl</h5>
                    <p class="text-muted">Empieza agregando un nuevo acceso/credencial a la plataforma.</p>
                    <button class="btn btn-success font-weight-bold" onclick="openAddEntryModal()">
                        <i class="fas fa-plus mr-1"></i>Agregar Primer Acceso
                    </button>
                </div>

                <div class="pw-grid" id="entries-grid">
                    <!-- Dynamic password cards -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FLOATING POPUP NOTIFICATION (Open/Execute Assist) -->
<div class="floating-overlay-banner" id="execute-assist-banner">
    <div class="d-flex align-items-center gap-3" style="gap: 12px;">
        <div style="font-size: 1.8rem; color: var(--vault-accent);">
            <i class="fas fa-rocket"></i>
        </div>
        <div>
            <h6 class="font-weight-bold mb-1">¡Acceso en Ejecución!</h6>
            <p class="mb-0 text-muted-vault" style="font-size: 0.82rem; color: #cbd5e0 !important;">Se ha abierto la URL seleccionada en una pestaña. Utiliza estos datos:</p>
            <div class="mt-2 d-flex gap-2" style="gap: 5px;">
                <button class="btn btn-xs btn-light font-weight-bold" id="btn-copy-user-assist"><i class="fas fa-user mr-1"></i>Copiar Usuario</button>
                <button class="btn btn-xs btn-warning font-weight-bold" id="btn-copy-pass-assist"><i class="fas fa-key mr-1"></i>Copiar Clave</button>
                <button class="btn btn-xs btn-outline-light" onclick="$('#execute-assist-banner').fadeOut()"><i class="fas fa-times"></i></button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: ADD / EDIT ENTRY -->
<div class="modal fade" id="entryModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="entryModalLabel">Agregar Nuevo Acceso</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="form-save-entry">
                <input type="hidden" id="entry-id" value="0">
                <div class="modal-body">
                    <div class="row">
                        <!-- Left inputs -->
                        <div class="col-md-7 border-right">
                            <div class="form-group">
                                <label class="font-weight-bold">Nombre del Sistema / Servidor *</label>
                                <input type="text" class="form-control" id="entry-name" required placeholder="Ej. Router Core - Sede Central">
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold">URL de Acceso *</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-link"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="entry-url" required placeholder="Ej. https://172.32.1.50/admin o google.com">
                                </div>
                                <small class="text-muted">Si ingresas una URL pública, se autotomará una captura. De lo contrario, se generará una tarjeta de red privada.</small>
                            </div>
                            <!-- Credencial Principal / Administrador -->
                            <div class="p-2 border mb-3" style="background-color: rgba(255,255,255,0.02) !important; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05) !important;">
                                <div class="font-weight-bold mb-2 text-info" style="font-size: 0.85rem;"><i class="fas fa-user-shield mr-1"></i> Credencial Principal / Administrador *</div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-2">
                                            <label class="small font-weight-bold mb-1">Usuario Principal</label>
                                            <input type="text" class="form-control form-control-sm" id="entry-username" required placeholder="Ej. admin o root">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-2">
                                            <label class="small font-weight-bold mb-1">Contraseña Principal</label>
                                            <div class="input-group input-group-sm">
                                                <input type="password" class="form-control form-control-sm" id="entry-password" required placeholder="Clave principal">
                                                <div class="input-group-append">
                                                    <button class="btn btn-outline-secondary" type="button" id="btn-toggle-modal-pass" onclick="toggleModalPassword()"><i class="fas fa-eye"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Credencial Secundaria / Usuario Estándar -->
                            <div class="p-2 border mb-3" style="background-color: rgba(255,255,255,0.02) !important; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05) !important;">
                                <div class="font-weight-bold mb-2 text-secondary" style="font-size: 0.85rem;"><i class="fas fa-user mr-1"></i> Credencial Secundaria / Usuario Estándar (Opcional)</div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-2">
                                            <label class="small font-weight-bold mb-1">Usuario Secundario</label>
                                            <input type="text" class="form-control form-control-sm" id="entry-username-sec" placeholder="Ej. user o read-only">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-2">
                                            <label class="small font-weight-bold mb-1">Contraseña Secundaria</label>
                                            <div class="input-group input-group-sm">
                                                <input type="password" class="form-control form-control-sm" id="entry-password-sec" placeholder="Clave secundaria">
                                                <div class="input-group-append">
                                                    <button class="btn btn-outline-secondary" type="button" id="btn-toggle-modal-pass-sec" onclick="toggleModalPasswordSec()"><i class="fas fa-eye"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Password Generator Widget -->
                            <div class="card bg-light p-3 border mb-3" style="background-color: var(--bg-vault-muted) !important; border-radius: 8px;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="font-weight-bold" style="font-size: 0.85rem;"><i class="fas fa-cogs mr-1 text-info"></i> Generador de Claves</span>
                                    <button type="button" class="btn btn-xs btn-outline-info" onclick="generateRandomPassword()">Generar</button>
                                </div>
                                <div class="row">
                                    <div class="col-5">
                                        <div class="form-group mb-0">
                                            <label class="small mb-0">Longitud</label>
                                            <input type="number" id="gen-length" class="form-control form-control-sm" value="16" min="6" max="32">
                                        </div>
                                    </div>
                                    <div class="col-7 d-flex align-items-end justify-content-between pb-1">
                                        <div class="custom-control custom-checkbox small">
                                            <input type="checkbox" class="custom-control-input" id="gen-special" checked>
                                            <label class="custom-control-label" for="gen-special">Símbolos</label>
                                        </div>
                                        <div class="custom-control custom-checkbox small">
                                            <input type="checkbox" class="custom-control-input" id="gen-numbers" checked>
                                            <label class="custom-control-label" for="gen-numbers">Números</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right inputs (Classification) -->
                        <div class="col-md-5">
                            <div class="form-group">
                                <label class="font-weight-bold">Tags / Categorías</label>
                                <input type="text" class="form-control" id="entry-tags" placeholder="Ej. firewall, core, prod, cisco">
                                <small class="text-muted">Separa las categorías con comas (,)</small>
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold">Observación / Descripción</label>
                                <textarea class="form-control" id="entry-observations" rows="3" placeholder="Instrucciones adicionales, IPs secundarias, etc."></textarea>
                            </div>
                            <div class="form-group mb-0">
                                <label class="font-weight-bold">Captura / Imagen de la URL</label>
                                <div class="vault-dropzone" id="screenshot-dropzone" title="Haz click, arrastra o pega (Ctrl+V) una imagen aquí">
                                    <div class="vault-dropzone-placeholder" id="dropzone-text">
                                        <i class="fas fa-image fa-2x mb-1 text-info"></i>
                                        <div style="font-size: 0.8rem;">Haz click para subir o pega con <b>Ctrl+V</b></div>
                                    </div>
                                    <img class="vault-dropzone-preview" id="dropzone-preview" src="" alt="Vista previa">
                                </div>
                                <input type="file" id="screenshot-file-input" class="d-none" accept="image/*">
                                <input type="hidden" id="screenshot-base64" value="">
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <small class="text-muted" style="font-size: 0.72rem;">Soporta pegar desde portapapeles</small>
                                    <button type="button" class="btn btn-xs btn-outline-danger d-none" id="btn-clear-screenshot" onclick="clearSelectedScreenshot()"><i class="fas fa-trash-alt mr-1"></i>Quitar</button>
                                </div>
                                <div class="custom-control custom-checkbox mt-1 d-none" id="delete-screenshot-wrapper">
                                    <input type="checkbox" class="custom-control-input" id="delete-screenshot-checkbox">
                                    <label class="custom-control-label text-danger small" for="delete-screenshot-checkbox">Eliminar captura actual</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success font-weight-bold" id="btn-save-entry-submit">
                        <i class="fas fa-save mr-1"></i> Guardar Acceso
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: REKEY / CONFIG -->
<div class="modal fade" id="configModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-shield-alt mr-2"></i>Configuración de Seguridad</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="form-rekey-vault">
                <div class="modal-body">
                    <p class="text-muted small">Puedes cambiar la contraseña maestra del baúl. La plataforma desencriptará la llave general (DEK) con la contraseña vieja y la re-encriptará con la nueva, de forma instantánea sin corromper tus accesos.</p>
                    <div class="form-group">
                        <label class="font-weight-bold">Contraseña Maestra Actual *</label>
                        <input type="password" class="form-control" id="rekey-old-password" required>
                    </div>
                    <hr>
                    <div class="form-group">
                        <label class="font-weight-bold">Nueva Contraseña Maestra *</label>
                        <input type="password" class="form-control" id="rekey-new-password" required placeholder="Min. 6 caracteres">
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Confirmar Nueva Contraseña *</label>
                        <input type="password" class="form-control" id="rekey-new-password-confirm" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-info font-weight-bold">
                        <i class="fas fa-key mr-1"></i> Cambiar Contraseña
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: HISTORY TIMELINE -->
<div class="modal fade" id="historyModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-history mr-2"></i>Historial de Cambios y Claves</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <h5 id="history-entry-title" class="font-weight-bold text-primary mb-3"></h5>
                <div id="history-loader" class="text-center py-4">
                    <div class="spinner-border text-secondary" role="status"></div>
                </div>
                <div id="history-timeline" class="timeline-vault d-none">
                    <!-- Dynamic history timeline items -->
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cerrar Historial</button>
            </div>
        </div>
    </div>
</div>

<input type="file" id="card-screenshot-file-input" class="d-none" accept="image/*">

<?php include 'partials/footer.php'; ?>

<script>
// App State
let vaultEntries = [];
let allUniqueTags = new Set();
let selectedTagFilter = '';
let activeAssistUser = '';
let activeAssistPass = '';
let activeUploadCardId = null;

$(function() {
    // Add page title next to hamburger button in navbar
    $('.navbar-nav').first().append('<li class="nav-item d-flex align-items-center ml-2"><span class="text-dark font-weight-bold" style="font-size: 1.1rem; font-family: \'Kumbh Sans\', sans-serif;">Gestor de Claves (PASSWORD)</span></li>');

    // Check initial vault status
    checkVaultStatus();
    
    // Setup Search Event
    $('#search-vault-entries').on('input', function() {
        renderEntries();
    });
    
    // Bind Submit Forms
    $('#form-setup-vault').on('submit', handleSetupVault);
    $('#form-unlock-vault').on('submit', handleUnlockVault);
    $('#form-save-entry').on('submit', handleSaveEntry);
    $('#form-rekey-vault').on('submit', handleRekeyVault);

    // Bind Copy Buttons in Assist Banner
    $('#btn-copy-user-assist').on('click', function() {
        copyToClipboard(activeAssistUser, 'Usuario copiado');
    });
    $('#btn-copy-pass-assist').on('click', function() {
        copyToClipboard(activeAssistPass, 'Clave copiada');
    });
    
    // Bind screenshot dropzone click
    $('#screenshot-dropzone').on('click', function() {
        $('#screenshot-file-input').click();
    });
    
    // File input change for dropzone
    $('#screenshot-file-input').on('change', function() {
        let file = this.files[0];
        if (file) {
            let reader = new FileReader();
            reader.onload = function(e) {
                setScreenshotPreview(e.target.result);
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Drag and drop events for dropzone
    $('#screenshot-dropzone').on('dragover dragenter', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('dragover');
    }).on('dragleave dragend drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
    }).on('drop', function(e) {
        let files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            let reader = new FileReader();
            reader.onload = function(evt) {
                setScreenshotPreview(evt.target.result);
            };
            reader.readAsDataURL(files[0]);
        }
    });

    // Window paste handler for screenshot pasting
    $(window).on('paste', function(e) {
        if (!$('#entryModal').hasClass('show')) return;
        
        let clipboardData = e.originalEvent.clipboardData;
        if (clipboardData && clipboardData.items) {
            for (let i = 0; i < clipboardData.items.length; i++) {
                let item = clipboardData.items[i];
                if (item.type.indexOf("image") !== -1) {
                    let blob = item.getAsFile();
                    let reader = new FileReader();
                    reader.onload = function(evt) {
                        setScreenshotPreview(evt.target.result);
                    };
                    reader.readAsDataURL(blob);
                    toastr.success('Captura de pantalla pegada desde portapapeles.');
                    e.preventDefault();
                    break;
                }
            }
        }
    });

    // Card screenshot direct upload handler
    $('#card-screenshot-file-input').on('change', function() {
        if (!activeUploadCardId) return;
        
        let file = this.files[0];
        if (!file) return;
        
        let reader = new FileReader();
        reader.onload = function(e) {
            let base64 = e.target.result;
            
            let syncIcon = $(`#sync-icon-${activeUploadCardId}`);
            let oldClass = syncIcon.attr('class');
            syncIcon.attr('class', 'fas fa-spinner fa-spin');
            
            let cardImg = $(`#pw-card-img-${activeUploadCardId}`);
            cardImg.css('opacity', '0.6');
            
            $.post('api_password.php', {
                action: 'upload_screenshot',
                id: activeUploadCardId,
                screenshot_base64: base64
            }, function(res) {
                syncIcon.attr('class', oldClass);
                cardImg.css('opacity', '1');
                if (res.success && res.screenshot_path) {
                    let newSrc = res.screenshot_path + '?t=' + Date.now();
                    cardImg.attr('src', newSrc);
                    toastr.success('Imagen de la tarjeta actualizada correctamente.');
                    
                    // Update cache
                    let entry = vaultEntries.find(ent => ent.id === activeUploadCardId);
                    if (entry) {
                        entry.screenshot_path = res.screenshot_path;
                    }
                } else {
                    toastr.error('Error al actualizar imagen: ' + (res.error || 'Fallo desconocido'));
                }
            }, 'json').fail(function() {
                syncIcon.attr('class', oldClass);
                cardImg.css('opacity', '1');
                toastr.error('Error al comunicarse con el servidor.');
            });
        };
        reader.readAsDataURL(file);
    });
});

// Check status of Vault
function checkVaultStatus() {
    $.getJSON('api_password.php', { action: 'vault_status' }, function(res) {
        if (res.success) {
            $('#vault-screen-wrapper').addClass('d-none');
            $('#screen-setup').addClass('d-none');
            $('#screen-lock').addClass('d-none');
            $('#vault-workspace').addClass('d-none');
            
            if (!res.initialized) {
                // Show Setup Screen
                $('#vault-screen-wrapper').removeClass('d-none');
                $('#screen-setup').removeClass('d-none');
            } else if (!res.unlocked) {
                // Show Unlock Screen
                $('#vault-screen-wrapper').removeClass('d-none');
                $('#screen-lock').removeClass('d-none');
                setTimeout(() => $('#unlock-master-password').focus(), 300);
            } else {
                // Show Workspace
                $('#vault-workspace').removeClass('d-none');
                loadEntries();
            }
        } else {
            Swal.fire('Error de Permisos', res.error, 'error');
        }
    });
}

// Handle Setup Vault
function handleSetupVault(e) {
    e.preventDefault();
    let pass = $('#setup-master-password').val();
    let confirm = $('#setup-master-password-confirm').val();
    
    if (pass.length < 6) {
        toastr.warning('La contraseña maestra debe tener al menos 6 caracteres.');
        return;
    }
    if (pass !== confirm) {
        toastr.error('Las contraseñas no coinciden.');
        return;
    }
    
    Swal.fire({
        title: '¿Inicializar Baúl?',
        text: 'Asegúrate de guardar la contraseña maestra en un lugar seguro. Si la pierdes, no podrás recuperar las contraseñas guardadas.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, crear baúl',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('api_password.php', {
                action: 'initialize',
                master_password: pass
            }, function(res) {
                if (res.success) {
                    Swal.fire('¡Éxito!', res.message, 'success');
                    checkVaultStatus();
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            }, 'json');
        }
    });
}

// Handle Unlock Vault
function handleUnlockVault(e) {
    e.preventDefault();
    let pass = $('#unlock-master-password').val();
    
    $.post('api_password.php', {
        action: 'unlock',
        master_password: pass
    }, function(res) {
        if (res.success) {
            toastr.success(res.message);
            checkVaultStatus();
        } else {
            toastr.error(res.error);
            $('#unlock-master-password').val('').focus();
        }
    }, 'json');
}

// Lock Vault
function lockVault() {
    $.post('api_password.php', { action: 'lock' }, function(res) {
        toastr.info('Baúl bloqueado.');
        checkVaultStatus();
    });
}

// Change Master Password
function handleRekeyVault(e) {
    e.preventDefault();
    let oldPass = $('#rekey-old-password').val();
    let newPass = $('#rekey-new-password').val();
    let newConfirm = $('#rekey-new-password-confirm').val();
    
    if (newPass.length < 6) {
        toastr.warning('La nueva contraseña debe tener al menos 6 caracteres.');
        return;
    }
    if (newPass !== newConfirm) {
        toastr.error('Las nuevas contraseñas no coinciden.');
        return;
    }
    
    $.post('api_password.php', {
        action: 'change_master',
        old_master_password: oldPass,
        new_master_password: newPass
    }, function(res) {
        if (res.success) {
            $('#configModal').modal('hide');
            Swal.fire('Contraseña Maestra Cambiada', res.message, 'success');
            $('#form-rekey-vault')[0].reset();
        } else {
            Swal.fire('Error', res.error, 'error');
        }
    }, 'json');
}

// Load password entries
function loadEntries() {
    $('#entries-loader').removeClass('d-none');
    $('#entries-grid').empty();
    $('#no-entries-card').addClass('d-none');
    
    $.getJSON('api_password.php', { action: 'list' }, function(res) {
        $('#entries-loader').addClass('d-none');
        if (res.success) {
            vaultEntries = res.entries;
            
            // Build unique tags
            allUniqueTags.clear();
            vaultEntries.forEach(entry => {
                if (entry.tags) {
                    entry.tags.split(',').forEach(tag => {
                        let t = tag.trim();
                        if (t) allUniqueTags.add(t);
                    });
                }
            });
            
            renderTagsSidebar();
            renderEntries();
        } else {
            if (res.locked) {
                checkVaultStatus();
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        }
    });
}

// Render unique tags in sidebar
function renderTagsSidebar() {
    let container = $('#tags-filter-container');
    container.find('.tag-badge-pill:not(:first)').remove(); // Keep the first (All)
    
    allUniqueTags.forEach(tag => {
        let activeClass = (selectedTagFilter === tag) ? 'active' : '';
        let badge = `
        <span class="tag-badge-pill ${activeClass}" onclick="filterByTag('${escapeHtml(tag)}')">
            <i class="fas fa-tag"></i> ${escapeHtml(tag)}
        </span>`;
        container.append(badge);
    });
}

// Filter entries by tag
function filterByTag(tag) {
    selectedTagFilter = tag;
    $('#tags-filter-container .tag-badge-pill').removeClass('active');
    
    if (tag === '') {
        $('#tags-filter-container .tag-badge-pill').first().addClass('active');
    } else {
        // Find badge elements and set active
        $('#tags-filter-container .tag-badge-pill').each(function() {
            if ($(this).text().trim() === tag) {
                $(this).addClass('active');
            }
        });
    }
    
    renderEntries();
}

// Render entries grid
function renderEntries() {
    let grid = $('#entries-grid');
    grid.empty();
    
    let search = $('#search-vault-entries').val().toLowerCase();
    
    let filtered = vaultEntries.filter(entry => {
        // Tag filter
        if (selectedTagFilter !== '') {
            let tags = entry.tags ? entry.tags.split(',').map(t => t.trim()) : [];
            if (!tags.includes(selectedTagFilter)) return false;
        }
        
        // Text Search
        if (search !== '') {
            let name = entry.name.toLowerCase();
            let url = entry.url.toLowerCase();
            let obs = entry.observations ? entry.observations.toLowerCase() : '';
            let tags = entry.tags ? entry.tags.toLowerCase() : '';
            
            return name.includes(search) || url.includes(search) || obs.includes(search) || tags.includes(search);
        }
        
        return true;
    });
    
    if (filtered.length === 0) {
        if (vaultEntries.length === 0) {
            $('#no-entries-card').removeClass('d-none');
        } else {
            grid.html('<div class="col-12 text-center text-muted py-5"><i class="fas fa-search fa-2x mb-2"></i><p>Ningún acceso coincide con el filtro.</p></div>');
        }
        return;
    }
    
    $('#no-entries-card').addClass('d-none');
    
    filtered.forEach(entry => {
        let screenshot = entry.screenshot_path ? entry.screenshot_path : 'assets/img/browser_fallback.png';
        
        // Tags array
        let tagHtml = '';
        if (entry.tags) {
            entry.tags.split(',').forEach(t => {
                let tag = t.trim();
                if (tag) {
                    tagHtml += `<span class="badge badge-info mr-1" style="font-size:0.75rem;">${escapeHtml(tag)}</span>`;
                }
            });
        }
        
        let card = `
        <div class="pw-card" id="pw-card-${entry.id}">
            <div class="pw-card-img-wrapper">
                <button class="btn btn-xs btn-light btn-recapture-screenshot" onclick="event.stopPropagation(); triggerCardImageUpload(${entry.id})" title="Subir Vista Previa" style="position: absolute; top: 10px; left: 10px; z-index: 10; border-radius: 4px; padding: 3px 6px; font-size: 0.7rem; opacity: 0.85; border: 1px solid var(--border-vault); background: rgba(255,255,255,0.85);">
                    <i class="fas fa-upload" id="sync-icon-${entry.id}"></i>
                </button>
                <span class="pw-card-badge"><i class="fas fa-lock text-warning"></i> Cifrado</span>
                <img class="pw-card-img" id="pw-card-img-${entry.id}" src="${screenshot}" onerror="this.src='https://images.unsplash.com/photo-1544256718-3bcf237f3974?w=500&auto=format&fit=crop&q=60'" alt="Previsualización Web">
            </div>
            
            <div class="pw-card-body">
                <div class="pw-card-title">${escapeHtml(entry.name)}</div>
                <a href="${escapeHtml(entry.url)}" target="_blank" class="pw-card-url" title="${escapeHtml(entry.url)}">
                    <i class="fas fa-external-link-alt mr-1"></i>${escapeHtml(entry.url)}
                </a>
                
                <div class="pw-card-obs" title="${escapeHtml(entry.observations)}">
                    ${entry.observations ? escapeHtml(entry.observations) : '<span class="text-muted italic">Sin observaciones</span>'}
                </div>
                
                <div class="pw-card-tags">
                    ${tagHtml ? tagHtml : '<span class="text-muted italic small">Sin tags</span>'}
                </div>
                
                <!-- Secret Panel (Revealed dynamically) -->
                <div class="pw-reveal-panel d-none" id="reveal-panel-${entry.id}">
                    <!-- Credencial Principal / Administrador -->
                    <div class="mb-2 p-2 rounded" style="background: var(--bg-vault-muted); border: 1px solid var(--border-vault);">
                        <div class="small font-weight-bold text-info mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px; text-transform: uppercase;">
                            <i class="fas fa-user-shield mr-1"></i> Principal / Admin
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small">Usuario: <strong id="val-user-${entry.id}">...</strong></span>
                            <button class="btn btn-xs btn-outline-secondary btn-icon-only" onclick="copyValue('val-user-${entry.id}', 'Usuario')"><i class="fas fa-copy"></i></button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small">Clave: <strong id="val-pass-${entry.id}">...</strong></span>
                            <button class="btn btn-xs btn-outline-secondary btn-icon-only" onclick="copyValue('val-pass-${entry.id}', 'Clave')"><i class="fas fa-copy"></i></button>
                        </div>
                    </div>
                    
                    <!-- Credencial Secundaria / Usuario (Will show dynamically if exists) -->
                    <div id="wrapper-sec-${entry.id}" class="p-2 rounded d-none" style="background: var(--bg-vault-muted); border: 1px solid var(--border-vault);">
                        <div class="small font-weight-bold text-secondary mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px; text-transform: uppercase;">
                            <i class="fas fa-user mr-1"></i> Secundario / Usuario
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small">Usuario: <strong id="val-user-sec-${entry.id}">...</strong></span>
                            <button class="btn btn-xs btn-outline-secondary btn-icon-only" onclick="copyValue('val-user-sec-${entry.id}', 'Usuario Secundario')"><i class="fas fa-copy"></i></button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small">Clave: <strong id="val-pass-sec-${entry.id}">...</strong></span>
                            <button class="btn btn-xs btn-outline-secondary btn-icon-only" onclick="copyValue('val-pass-sec-${entry.id}', 'Clave Secundaria')"><i class="fas fa-copy"></i></button>
                        </div>
                </div>
                </div>

                <!-- Date and Creator Info -->
                <div class="d-flex justify-content-between align-items-center mb-2 mt-2 pt-2 border-top text-muted small" style="font-size: 0.7rem; opacity: 0.85;">
                    <span><i class="fas fa-user-edit mr-1"></i>${escapeHtml(entry.creator || 'superadmin')}</span>
                    <span><i class="fas fa-calendar-alt mr-1"></i>${escapeHtml(entry.created_at)}</span>
                </div>

                <div class="pw-card-actions">
                    <button class="btn btn-sm btn-outline-primary" id="btn-reveal-${entry.id}" onclick="toggleRevealCredentials(${entry.id})" title="Ver Credenciales">
                        <i class="fas fa-eye mr-1"></i>Ver
                    </button>
                    <div>
                        <button class="btn btn-xs btn-outline-secondary btn-icon-only mr-1" onclick="loadHistory(${entry.id}, '${escapeHtml(entry.name)}')" title="Historial de Claves"><i class="fas fa-history"></i></button>
                        <button class="btn btn-xs btn-outline-info btn-icon-only mr-1" onclick="editEntry(${entry.id})" title="Editar"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-xs btn-outline-danger btn-icon-only" onclick="deleteEntry(${entry.id})" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
            </div>
        </div>`;
        
        grid.append(card);
    });
}

// Toggle Reveal credentials (Username / Password)
function toggleRevealCredentials(id) {
    let panel = $(`#reveal-panel-${id}`);
    let btn = $(`#btn-reveal-${id}`);
    
    if (panel.hasClass('d-none')) {
        // Load decrypted data first
        panel.removeClass('d-none');
        btn.html('<i class="fas fa-eye-slash mr-1"></i>Ocultar').removeClass('btn-outline-primary').addClass('btn-outline-secondary');
        
        // Fetch via AJAX
        $.getJSON('api_password.php', { action: 'get_decrypted', id: id }, function(res) {
            if (res.success) {
                $(`#val-user-${id}`).text(res.username);
                $(`#val-pass-${id}`).html(`<span style="-webkit-text-security: none;">${escapeHtml(res.password)}</span>`);
                
                // Keep references for edit fields
                $(`#pw-card-${id}`).data('dec-user', res.username);
                $(`#pw-card-${id}`).data('dec-pass', res.password);
                $(`#pw-card-${id}`).data('dec-user-sec', res.username_sec || '');
                $(`#pw-card-${id}`).data('dec-pass-sec', res.password_sec || '');
                
                // Show secondary credentials section if either username_sec or password_sec exists
                if (res.username_sec || res.password_sec) {
                    $(`#val-user-sec-${id}`).text(res.username_sec || '');
                    $(`#val-pass-sec-${id}`).html(`<span style="-webkit-text-security: none;">${escapeHtml(res.password_sec || '')}</span>`);
                    $(`#wrapper-sec-${id}`).removeClass('d-none');
                } else {
                    $(`#wrapper-sec-${id}`).addClass('d-none');
                }
            } else {
                panel.addClass('d-none');
                btn.html('<i class="fas fa-eye mr-1"></i>Ver').removeClass('btn-outline-secondary').addClass('btn-outline-primary');
                toastr.error(res.error);
            }
        });
    } else {
        panel.addClass('d-none');
        btn.html('<i class="fas fa-eye mr-1"></i>Ver').removeClass('btn-outline-secondary').addClass('btn-outline-primary');
        $(`#val-user-${id}`).text('...');
        $(`#val-pass-${id}`).text('...');
        $(`#val-user-sec-${id}`).text('...');
        $(`#val-pass-sec-${id}`).text('...');
        $(`#wrapper-sec-${id}`).addClass('d-none');
    }
}

// Open modal for new entry
function openAddEntryModal() {
    $('#form-save-entry')[0].reset();
    $('#entry-id').val('0');
    $('#entry-id').data('has-screenshot', false);
    clearSelectedScreenshot();
    $('#delete-screenshot-wrapper').addClass('d-none');
    $('#entryModalLabel').text('Agregar Nuevo Acceso');
    $('#entry-password').attr('type', 'password');
    $('#btn-toggle-modal-pass i').removeClass('fa-eye-slash').addClass('fa-eye');
    $('#entry-password-sec').attr('type', 'password');
    $('#btn-toggle-modal-pass-sec i').removeClass('fa-eye-slash').addClass('fa-eye');
    $('#entryModal').modal('show');
}

// Edit entry
function editEntry(id) {
    let card = $(`#pw-card-${id}`);
    let cachedUser = card.data('dec-user');
    let cachedPass = card.data('dec-pass');
    let cachedUserSec = card.data('dec-user-sec');
    let cachedPassSec = card.data('dec-pass-sec');
    
    let populateAndShow = function(username, password, username_sec, password_sec, name, url, tags, observations) {
        $('#entry-id').val(id);
        $('#entry-name').val(name);
        $('#entry-url').val(url);
        $('#entry-username').val(username);
        $('#entry-password').val(password);
        $('#entry-username-sec').val(username_sec);
        $('#entry-password-sec').val(password_sec);
        $('#entry-tags').val(tags);
        $('#entry-observations').val(observations);
        
        // Handle screenshot preview inside modal
        let entry = vaultEntries.find(e => e.id === id);
        if (entry && entry.screenshot_path) {
            $('#entry-id').data('has-screenshot', true);
            $('#screenshot-base64').val('');
            $('#dropzone-preview').attr('src', entry.screenshot_path).show();
            $('#dropzone-text').hide();
            $('#btn-clear-screenshot').removeClass('d-none');
            $('#delete-screenshot-checkbox').prop('checked', false);
            $('#delete-screenshot-wrapper').removeClass('d-none');
        } else {
            $('#entry-id').data('has-screenshot', false);
            clearSelectedScreenshot();
        }
        
        $('#entryModalLabel').text('Modificar Acceso');
        $('#entry-password').attr('type', 'password');
        $('#btn-toggle-modal-pass i').removeClass('fa-eye-slash').addClass('fa-eye');
        $('#entry-password-sec').attr('type', 'password');
        $('#btn-toggle-modal-pass-sec i').removeClass('fa-eye-slash').addClass('fa-eye');
        $('#entryModal').modal('show');
    };
    
    // Find matching entry metadata
    let entry = vaultEntries.find(e => e.id === id);
    if (!entry) return;
    
    if (cachedUser && cachedPass) {
        populateAndShow(cachedUser, cachedPass, cachedUserSec, cachedPassSec, entry.name, entry.url, entry.tags, entry.observations);
    } else {
        // Fetch decrypted
        $.getJSON('api_password.php', { action: 'get_decrypted', id: id }, function(res) {
            if (res.success) {
                populateAndShow(res.username, res.password, res.username_sec, res.password_sec, entry.name, entry.url, entry.tags, entry.observations);
            } else {
                toastr.error(res.error);
            }
        });
    }
}

// Handle Save Entry (Create / Update)
function handleSaveEntry(e) {
    e.preventDefault();
    
    let btnSubmit = $('#btn-save-entry-submit');
    btnSubmit.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Guardando...');
    
    $.post('api_password.php', {
        action: 'save',
        id: $('#entry-id').val(),
        name: $('#entry-name').val(),
        url: $('#entry-url').val(),
        username: $('#entry-username').val(),
        password: $('#entry-password').val(),
        username_sec: $('#entry-username-sec').val(),
        password_sec: $('#entry-password-sec').val(),
        observations: $('#entry-observations').val(),
        tags: $('#entry-tags').val(),
        screenshot_base64: $('#screenshot-base64').val(),
        delete_screenshot: $('#delete-screenshot-checkbox').is(':checked') ? '1' : '0'
    }, function(res) {
        if (res.success) {
            $('#entryModal').modal('hide');
            toastr.success(res.message);
            loadEntries();
        } else {
            Swal.fire('Error', res.error, 'error');
        }
    }, 'json').fail(function(xhr) {
        console.error(xhr.responseText);
        Swal.fire('Error', 'Error en el servidor al guardar el acceso o respuesta inválida.', 'error');
    }).always(function() {
        btnSubmit.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Guardar Acceso');
    });
}

// Screenshot preview helpers
function setScreenshotPreview(base64) {
    $('#screenshot-base64').val(base64);
    $('#dropzone-preview').attr('src', base64).show();
    $('#dropzone-text').hide();
    $('#btn-clear-screenshot').removeClass('d-none');
    $('#delete-screenshot-checkbox').prop('checked', false);
    $('#delete-screenshot-wrapper').addClass('d-none');
}

function clearSelectedScreenshot() {
    $('#screenshot-file-input').val('');
    $('#screenshot-base64').val('');
    $('#dropzone-preview').attr('src', '').hide();
    $('#dropzone-text').show();
    $('#btn-clear-screenshot').addClass('d-none');
    if ($('#entry-id').val() !== '0' && $('#entry-id').data('has-screenshot')) {
        $('#delete-screenshot-checkbox').prop('checked', true);
        $('#delete-screenshot-wrapper').removeClass('d-none');
    } else {
        $('#delete-screenshot-checkbox').prop('checked', false);
        $('#delete-screenshot-wrapper').addClass('d-none');
    }
}

function triggerCardImageUpload(id) {
    activeUploadCardId = id;
    $('#card-screenshot-file-input').click();
}

// Delete Entry
function deleteEntry(id) {
    let entry = vaultEntries.find(e => e.id === id);
    if (!entry) return;
    
    Swal.fire({
        title: '¿Eliminar Acceso?',
        text: `¿Seguro que deseas eliminar la credencial para "${entry.name}"? Esta acción borrará el historial de claves asociadas de forma permanente.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('api_password.php', {
                action: 'delete',
                id: id
            }, function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    loadEntries();
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            }, 'json');
        }
    });
}

// Execute Access (Opens URL + Assist Clipboard Overlay)
function executeAccess(id, url) {
    // Open the url in a new tab
    let cleanUrl = url;
    if (!cleanUrl.startsWith('http://') && !cleanUrl.startsWith('https://')) {
        cleanUrl = 'http://' + cleanUrl;
    }
    
    // Fetch credentials to copy to clipboard or support user
    $.getJSON('api_password.php', { action: 'get_decrypted', id: id }, function(res) {
        if (res.success) {
            activeAssistUser = res.username;
            activeAssistPass = res.password;
            
            // Open window
            window.open(cleanUrl, '_blank');
            
            // Auto copy password as a default nice helper gesture
            copyToClipboard(res.password, 'Contraseña copiada al portapapeles automáticamente.');
            
            // Show helper overlay
            $('#execute-assist-banner').fadeIn();
            setTimeout(() => {
                // Auto fade out after 20 seconds
                $('#execute-assist-banner').fadeOut();
            }, 20000);
        } else {
            toastr.error('No se pudieron desencriptar las credenciales para la ejecución.');
        }
    });
}

// Load password History timeline
function loadHistory(id, name) {
    $('#history-entry-title').text(`Credenciales para: ${name}`);
    $('#history-loader').removeClass('d-none');
    $('#history-timeline').addClass('d-none').empty();
    $('#historyModal').modal('show');
    
    $.getJSON('api_password.php', { action: 'history', entry_id: id }, function(res) {
        $('#history-loader').addClass('d-none');
        if (res.success) {
            let timeline = $('#history-timeline');
            timeline.removeClass('d-none');
            
            if (res.history.length === 0) {
                timeline.html('<p class="text-muted text-center py-3">No hay historial registrado aún para este acceso.</p>');
                return;
            }
            
            res.history.forEach((h, index) => {
                let typeText = (h.change_type === 'creation') ? 'Creación de Acceso' : 'Modificación de Credenciales';
                let typeClass = h.change_type;
                let dateStr = h.changed_at;
                
                let histItem = `
                <div class="timeline-vault-item ${typeClass}">
                    <div class="d-flex justify-content-between align-items-center">
                        <strong class="${typeClass === 'creation' ? 'text-success' : 'text-warning'}">${typeText}</strong>
                        <span class="badge badge-secondary small">${dateStr}</span>
                    </div>
                    <div class="text-muted small mb-2">Realizado por: <strong>${escapeHtml(h.changer)}</strong></div>
                    
                    <div class="bg-light p-2 rounded border mb-2" style="background-color: var(--bg-vault-muted) !important; font-family: monospace; font-size:0.8rem;">
                        <div class="row mb-1">
                            <div class="col-12 text-info small font-weight-bold" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px;">Principal / Admin</div>
                            <div class="col-sm-6">
                                <div>Usuario: <strong>${escapeHtml(h.username)}</strong></div>
                            </div>
                            <div class="col-sm-6 d-flex align-items-center justify-content-between">
                                <div>Clave: <strong id="hist-pass-text-${h.id}" style="-webkit-text-security: disc;">${escapeHtml(h.password)}</strong></div>
                                <div>
                                    <button class="btn btn-xs btn-outline-secondary" onclick="togglePassMask('hist-pass-text-${h.id}')" title="Ver Clave"><i class="fas fa-eye"></i></button>
                                    <button class="btn btn-xs btn-outline-info" onclick="copyToClipboard('${escapeHtml(h.password)}', 'Clave copiada')" title="Copiar"><i class="fas fa-copy"></i></button>
                                </div>
                            </div>
                        </div>
                        
                        ${(h.username_sec || h.password_sec) ? `
                        <div class="row border-top pt-1 mt-1">
                            <div class="col-12 text-secondary small font-weight-bold" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px;">Secundario / Usuario</div>
                            <div class="col-sm-6">
                                <div>Usuario: <strong>${escapeHtml(h.username_sec || '')}</strong></div>
                            </div>
                            <div class="col-sm-6 d-flex align-items-center justify-content-between">
                                <div>Clave: <strong id="hist-pass-sec-text-${h.id}" style="-webkit-text-security: disc;">${escapeHtml(h.password_sec || '')}</strong></div>
                                <div>
                                    <button class="btn btn-xs btn-outline-secondary" onclick="togglePassMask('hist-pass-sec-text-${h.id}')" title="Ver Clave"><i class="fas fa-eye"></i></button>
                                    <button class="btn btn-xs btn-outline-info" onclick="copyToClipboard('${escapeHtml(h.password_sec || '')}', 'Clave copiada')" title="Copiar"><i class="fas fa-copy"></i></button>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                        
                        ${h.observations ? `<div class="mt-1 border-top pt-1 small text-muted">Obs: ${escapeHtml(h.observations)}</div>` : ''}
                    </div>
                </div>`;
                
                timeline.append(histItem);
            });
        } else {
            toastr.error(res.error);
        }
    });
}

// Helpers
function showVaultConfigModal() {
    $('#configModal').modal('show');
}

function toggleModalPassword() {
    let inp = $('#entry-password');
    let icon = $('#btn-toggle-modal-pass i');
    if (inp.attr('type') === 'password') {
        inp.attr('type', 'text');
        icon.removeClass('fa-eye').addClass('fa-eye-slash');
    } else {
        inp.attr('type', 'password');
        icon.removeClass('fa-eye-slash').addClass('fa-eye');
    }
}

function toggleModalPasswordSec() {
    let inp = $('#entry-password-sec');
    let icon = $('#btn-toggle-modal-pass-sec i');
    if (inp.attr('type') === 'password') {
        inp.attr('type', 'text');
        icon.removeClass('fa-eye').addClass('fa-eye-slash');
    } else {
        inp.attr('type', 'password');
        icon.removeClass('fa-eye-slash').addClass('fa-eye');
    }
}

function togglePassMask(elementId) {
    let el = $(`#${elementId}`);
    if (el.css('-webkit-text-security') === 'none') {
        el.css('-webkit-text-security', 'disc');
    } else {
        el.css('-webkit-text-security', 'none');
    }
}

function generateRandomPassword() {
    let length = parseInt($('#gen-length').val()) || 16;
    let useSpecial = $('#gen-special').is(':checked');
    let useNumbers = $('#gen-numbers').is(':checked');
    
    let chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    if (useNumbers) chars += "0123456789";
    if (useSpecial) chars += "!@#$%^&*()_+-=[]{}|;:,.<>?";
    
    let pass = "";
    for (let i = 0; i < length; i++) {
        let randIndex = Math.floor(Math.random() * chars.length);
        pass += chars.charAt(randIndex);
    }
    
    $('#entry-password').val(pass).attr('type', 'text');
    $('#btn-toggle-modal-pass i').removeClass('fa-eye').addClass('fa-eye-slash');
    toastr.info('Clave aleatoria generada y expuesta.');
}

function copyValue(strongId, label) {
    let txt = $(`#${strongId}`).text();
    copyToClipboard(txt, `${label} copiado`);
}

function copyToClipboard(text, successMsg) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            toastr.success(successMsg || 'Copiado al portapapeles');
        }).catch(err => {
            toastr.error('Error al copiar: ' + err);
        });
    } else {
        // Fallback for non-secure HTTP environments
        let textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.top = "0";
        textArea.style.left = "0";
        textArea.style.width = "2em";
        textArea.style.height = "2em";
        textArea.style.padding = "0";
        textArea.style.border = "none";
        textArea.style.outline = "none";
        textArea.style.boxShadow = "none";
        textArea.style.background = "transparent";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            let successful = document.execCommand('copy');
            if (successful) {
                toastr.success(successMsg || 'Copiado al portapapeles');
            } else {
                toastr.error('No se pudo copiar el texto');
            }
        } catch (err) {
            toastr.error('Error de compatibilidad al copiar');
        }
        document.body.removeChild(textArea);
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return text
        .toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

</script>
