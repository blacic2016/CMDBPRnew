/**
 * Lógica para la tabla de Gestión de Inventario (js/modal_gestion.js)
 */
$(document).ready(function() {
    let tablaInventario;
    let currentIdToModify = null;
    const INVENTORY_API_URL = 'wireless_inventario_api.php';
    
    /**
     * Validación básica de contraseña en cliente (solo UX, la validación real está en el servidor)
     * Mejora la experiencia de usuario proporcionando feedback inmediato
     */
    function is_authorized(password) {
        if (!password || password.length < 6) {
            return false;
        }
        // Validación básica: debe tener al menos 6 caracteres y contener al menos una letra y un número
        const hasLetter = /[a-zA-Z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        return hasLetter && hasNumber;
    }
    
    /**
     * Muestra feedback visual de validación de contraseña
     */
    function validatePasswordField($field) {
        const password = $field.val();
        const $feedback = $field.next('.password-feedback');
        
        if (password.length === 0) {
            $feedback.remove();
            return;
        }
        
        if (!$feedback.length) {
            $field.after('<span class="password-feedback" style="font-size: 0.85em; color: var(--text-secondary); display: block; margin-top: 5px;"></span>');
        }
        
        const feedback = $field.next('.password-feedback');
        if (is_authorized(password)) {
            feedback.text('✓ Contraseña válida').css('color', 'var(--success-color)');
        } else {
            feedback.text('⚠ Debe tener al menos 6 caracteres, una letra y un número').css('color', 'var(--warning-color)');
        }
    }

    // 1. Inicializar DataTables
    function initializeDataTable(data) {
        if ($.fn.DataTable.isDataTable('#tablaInventario')) {
            tablaInventario.destroy();
            $('#tablaInventario tbody').empty();
        }

        tablaInventario = $('#tablaInventario').DataTable({
            data: data,
            responsive: true,
            scrollX: true,
            pageLength: 25,
            order: [[0, 'asc']],
            dom: 'Bflrtip', 
            language: {
                url: 'wireless_js/Spanish.json' 
            },
            columns: [
                { data: 'id', title: 'ID' }, // Usamos 'id'
                { data: 'nro', title: 'NRO' }, // Usamos 'nro' (secundario)
                { data: 'ip', title: 'IP' },
                { data: 'ciudad', title: 'CIUDAD' },
                { data: 'unidad', title: 'UNIDAD' },
                { data: 'sub_unidad', title: 'SUB UNIDAD' },
                { data: 'nombre_equipo', title: 'NOMBRE EQUIPO' },
                { data: 'hostname', title: 'HOSTNAME' },
                { data: 'observaciones', title: 'OBSERVACIONES' },
                { 
                    data: 'estado_actual', 
                    title: 'ESTADO',
                    createdCell: function (td, cellData) {
                        if (cellData === 'ACTIVO') {
                            $(td).css('background-color', 'var(--success-color)').css('color', '#000');
                        } else if (cellData === 'DESACTIVADO') {
                            $(td).css('background-color', 'var(--danger-color)').css('color', '#fff');
                        }
                    }
                },
                { data: 'fecha_creacion', title: 'F. CREACIÓN' },
                { data: 'fecha_actualizacion', title: 'F. ACTUALIZACIÓN' },
                {
                    data: null,
                    title: 'ACCIONES',
                    orderable: false,
                    render: function (data, type, row) {
                        const deleteBtn = row.estado_actual === 'ACTIVO' 
                            ? `<button class="action-btn btn-delete" onclick="openConfirmDelete(${row.id}, '${row.estado_actual}')">&#10006; Borrar</button>`
                            : `<button class="action-btn btn-delete" disabled title="Ya Desactivado">&#10006; Borrar</button>`;
                            
                        return `
                            <button class="action-btn btn-edit" onclick="openGestionModal('edit', ${row.id})">&#9998; Editar</button>
                            ${deleteBtn}
                        `;
                    }
                }
            ],
            initComplete: function () {
                tablaInventario.columns.adjust().draw();
            }
        });
        
        $('#filterUnidad, #filterSubUnidad, #filterEstado').on('change', function() {
            loadInventoryData(); 
        });
    }

    // 2. Cargar datos desde la API
    function loadInventoryData() {
        const unidad = $('#filterUnidad').val();
        const sub_unidad = $('#filterSubUnidad').val();
        const estado_actual = $('#filterEstado').val();

        $.ajax({
            url: INVENTORY_API_URL,
            type: 'GET',
            dataType: 'json',
            data: { unidad: unidad, sub_unidad: sub_unidad, estado_actual: estado_actual },
            success: function(response) {
                if (response.success) {
                    initializeDataTable(response.data);
                    populateFilterSelects(response.filters);
                } else {
                    alert('Error al cargar datos: ' + response.message);
                    initializeDataTable([]);
                }
            },
            error: function() {
                alert('Error de conexión con el script de API.');
                initializeDataTable([]);
            }
        });
    }

    function populateFilterSelects(filters) {
        const $unidadFilter = $('#filterUnidad');
        const $subUnidadFilter = $('#filterSubUnidad');
        
        const currentUnidad = $unidadFilter.val();
        $unidadFilter.find('option:gt(0)').remove();
        filters.unidad.forEach(item => {
            $unidadFilter.append(new Option(item, item));
        });
        $unidadFilter.val(currentUnidad); 

        const currentSubUnidad = $subUnidadFilter.val();
        $subUnidadFilter.find('option:gt(0)').remove();
        filters.sub_unidad.forEach(item => {
            $subUnidadFilter.append(new Option(item, item));
        });
        $subUnidadFilter.val(currentSubUnidad); 
    }

    // 4. Modal de Gestión (Crear/Editar)
    window.openGestionModal = function(mode, id = null) {
        const $modal = $('#modalGestion');
        const $form = $('#formGestion');
        const $title = $('#modalTitle');
        const $btnGuardar = $('#btnGuardar');
        
        $form[0].reset();
        $('#id').val(''); 
        $('#password_admin').val('');
        currentIdToModify = id;

        $('.read-only-field').prop('readonly', false).prop('required', false);

        if (mode === 'create') {
            $title.text('Crear Nuevo Equipo (El ID será asignado automáticamente)');
            $btnGuardar.text('Crear').removeClass('btn-edit').addClass('btn-create');
            
            $('#ip, #unidad_modal, #sub_unidad_modal, #nombre_equipo').prop('required', true); 
            $('.read-only-field').prop('readonly', false);

        } else if (mode === 'edit') {
            $title.text('Actualizar Equipo ID: ' + id + ' (Campos Limitados)');
            $btnGuardar.text('Actualizar').removeClass('btn-create').addClass('btn-edit');
            
            $('.read-only-field').prop('readonly', true);
            
            const rowData = tablaInventario.rows().data().toArray().find(row => row.id == id);
            if (rowData) {
                // CLAVE PARA UPDATE: Se establece el ID en el input oculto
                $('#id').val(rowData.id); 
                
                // Campos EDITABLES
                $('#ip').val(rowData.ip);
                $('#unidad_modal').val(rowData.unidad);
                $('#sub_unidad_modal').val(rowData.sub_unidad);
                $('#nombre_equipo').val(rowData.nombre_equipo);
                $('#observaciones').val(rowData.observaciones);
                
                // Campos NO EDITABLES 
                $('#hostname').val(rowData.hostname);
                $('#nro').val(rowData.nro); // Cargar nro
                $('#fecha').val(rowData.fecha);
                $('#ciudad').val(rowData.ciudad);
                $('#app_seguridad').val(rowData.app_seguridad);
                $('#nessus').val(rowData.nessus);
                $('#aranda').val(rowData.aranda);
                $('#hx_v35_31_28').val(rowData.hx_v35_31_28);
                $('#dominio').val(rowData.dominio);
            }
        }
        
        $modal.css('display', 'block');
    };

    // 5. Enviar formulario de Crear/Actualizar
    $('#formGestion').on('submit', function(e) {
        e.preventDefault();
        
        const mode = $('#id').val() ? 'PUT' : 'POST'; 

        let formData = $(this).serializeArray().reduce((obj, item) => {
            // Incluimos todos los campos en POST. En PUT, solo los no-readonly.
            if (mode === 'POST' || !$('#' + item.name).is('[readonly]')) {
                obj[item.name] = item.value;
            }
            return obj;
        }, {});
        
        // Validación mejorada en cliente
        if (!formData.password_admin || formData.password_admin.trim() === '') {
            alert('Error: Por favor, ingrese el código de autorización.');
            $('#password_admin').focus();
            return;
        }
        
        if (!is_authorized(formData.password_admin)) {
            alert('Error de Autorización: El código secreto debe tener al menos 6 caracteres, una letra y un número.');
            $('#password_admin').focus();
            return;
        }

        // Si es PUT, solo enviamos campos editables + ID + Password
        if (mode === 'PUT') {
            const editableFields = ['id', 'ip', 'unidad', 'sub_unidad', 'nombre_equipo', 'observaciones', 'password_admin'];
            const filteredData = {};
            editableFields.forEach(key => {
                if (formData.hasOwnProperty(key)) {
                    filteredData[key] = formData[key];
                }
            });
            formData = filteredData;
            formData.id = $('#id').val(); 
        }
        
        // Si es POST, quitamos el ID (y nro si no fue llenado) para que la DB lo asigne
        if (mode === 'POST') {
             delete formData.id; 
        }

        $.ajax({
            url: INVENTORY_API_URL,
            type: mode,
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    $('#modalGestion').css('display', 'none');
                    loadInventoryData(); 
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                if (xhr.status === 401) {
                    alert('Error de Autorización: El código secreto es incorrecto.');
                } else {
                    alert('Error de comunicación con el servidor. Código de estado: ' + xhr.status + (xhr.responseJSON ? ' - ' + xhr.responseJSON.message : ''));
                }
            }
        });
    });

    // 6. Modal de Confirmación para Borrado (Desactivar)
    window.openConfirmDelete = function(id, estado) {
        if (estado === 'DESACTIVADO') {
            alert('El equipo ya está DESACTIVADO.');
            return;
        }
        currentIdToModify = id;
        $('#confirmId').text(id); 
        $('#password_delete').val('');
        $('#modalConfirmacion').css('display', 'block');
    };

    // 7. Acción de Desactivar
    $('#btnConfirmarBorrado').on('click', function() {
        const password_admin = $('#password_delete').val();
        
        // Validación mejorada en cliente
        if (!password_admin || password_admin.trim() === '') {
            alert('Error: Por favor, ingrese el código de autorización.');
            $('#password_delete').focus();
            return;
        }
        
        if (!is_authorized(password_admin)) {
            alert('Error de Autorización: El código secreto debe tener al menos 6 caracteres, una letra y un número.');
            $('#password_delete').focus();
            return;
        }
        
        if (currentIdToModify) {
            $.ajax({
                url: INVENTORY_API_URL,
                type: 'DELETE',
                contentType: 'application/json',
                data: JSON.stringify({ id: currentIdToModify, password_admin: password_admin }),
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        $('#modalConfirmacion').css('display', 'none');
                        loadInventoryData(); 
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 401) {
                        alert('Error de Autorización: El código secreto es incorrecto.');
                    } else {
                        alert('Error de comunicación con el servidor. Código de estado: ' + xhr.status);
                    }
                }
            });
        }
    });

    // 8. Evento para abrir el modal de creación
    $('#btnCrearEquipo').on('click', function() {
        openGestionModal('create');
    });
    
    // 9. Validación en tiempo real de campos de contraseña
    $(document).on('input', '#password_admin, #password_delete', function() {
        validatePasswordField($(this));
    });
    
    // 10. Validación de campos requeridos antes de enviar
    $('#formGestion').on('submit', function(e) {
        const requiredFields = ['ip', 'unidad_modal', 'sub_unidad_modal', 'nombre_equipo', 'password_admin'];
        let hasErrors = false;
        
        requiredFields.forEach(function(fieldId) {
            const $field = $('#' + fieldId);
            if (!$field.val() || $field.val().trim() === '') {
                $field.css('border-color', 'var(--danger-color)');
                hasErrors = true;
            } else {
                $field.css('border-color', '');
            }
        });
        
        if (hasErrors) {
            e.preventDefault();
            alert('Por favor, complete todos los campos requeridos.');
            return false;
        }
    });
    
    loadInventoryData();
});