// Encapsular en una función anónima para evitar conflictos de variables globales
(function($) {
    'use strict';

    
    // --- Logging Básico (JS) ---
    /**
     * Función simple de logging en la consola del navegador.
     * @param {any} message Mensaje o dato a registrar.
     * @param {string} level Nivel de log (INFO, DEBUG, WARN, ERROR).
     * @param {any} [extraData=null] Datos adicionales para registrar (opcional).
     */
    function crm_js_log(message, level = 'INFO', extraData = null) { // Añadir extraData
        // Añadir verificación de tipo para level por seguridad
        if (typeof level !== 'string') {
            // console.error('[CRM JS LOG INTERNAL ERROR] El parámetro "level" no es un string!', { message: message, level: level, extraData: extraData });
            level = ''; // Forzar a string para evitar el crash, pero registrar el problema
        }

        const prefix = `[CRM Evolution Sender ${level}]`;
        const upperLevel = level.toUpperCase(); // Usar variable temporal
        switch (upperLevel) {
            case 'ERROR':
                console.error(prefix, message);
                break;
            case 'WARN':
                console.warn(prefix, message);
                break;
            case 'DEBUG':
                console.debug(prefix, message);
                break;
            case 'INFO':
                console.info(prefix, message);
                break;
            default:
                console.log(prefix, message);
                break;
        }

        // Si es un error y se pasaron datos extra, mostrarlos
        if (upperLevel === 'ERROR' && extraData !== null) {
            console.error("Detalle del error:", extraData); // Usar extraData
        }
        // Mantener el fallback por si acaso, aunque es menos útil
        else if (upperLevel === 'ERROR' && typeof message === 'object') {
            console.error("Detalle del error (desde mensaje):", message);
        }
    }


    // --- Variables Globales del Módulo ---
    let instancesTable = null; // Referencia a la DataTable de Instancias
    let usersTable = null;     // Referencia a la DataTable de Usuarios
    let campaignsTable = null; // Referencia a la DataTable de Campañas
    let phoneInputInstance = null; //instancia de intl-tel-inpu

    /**
    * Inicializa la DataTable para la tabla de Instancias API.
    */
    function initInstancesTable() {
        // crm_js_log('Inicializando DataTable para Instancias.');
        if ($('#instances-table').length === 0) {
            crm_js_log('Tabla #instances-table no encontrada en el DOM.', 'WARN');
            return;
        }
        if (instancesTable) {
            crm_js_log('DataTable de Instancias ya inicializada. Refrescando datos...');
            instancesTable.ajax.reload();
            return;
        }

        instancesTable = $('#instances-table').DataTable({
            processing: true, // Indicador de carga
            serverSide: false, // Cambiar a true si usas server-side processing con AJAX
            ajax: {
                url: crm_evolution_sender_params.ajax_url, // URL de admin-ajax.php
                type: 'POST',
                data: {
                    action: 'crm_get_instances', // Acción AJAX que definimos en PHP
                    _ajax_nonce: crm_evolution_sender_params.nonce // Nonce de seguridad
                },
                dataSrc: function(json) {
                    // Manejar la respuesta AJAX
                    console.log(json) // Mantener para depuración si es necesario
                    if (!json || !json.success || !Array.isArray(json.data)) {
                        crm_js_log('Error o formato inesperado en la respuesta AJAX para get_instances.', 'ERROR', json);
                        showNotification('Error al cargar instancias.', 'error');
                        return []; // Devuelve array vacío en caso de error
                    }
                    crm_js_log('Datos de instancias recibidos:', json.data);
                    return json.data; // Los datos para DataTables están en json.data
                },
                error: function(xhr, status, error) {
                    crm_js_log('Error AJAX al obtener instancias.', 'ERROR', { status: status, error: error, response: xhr.responseText });
                    showNotification('Error de conexión al cargar instancias.', 'error');
                }
            },
            columns: [
                // *** INICIO: Nueva Columna Avatar ***
                {
                    data: 'profile_pic_url', // Coincide con la clave enviada desde PHP (corregida a profilePictureUrl en PHP)
                    title: 'Avatar',         // Título (aunque se toma del TH)
                    orderable: false,        // No ordenar por imagen
                    searchable: false,       // No buscar por imagen
                    render: function(data, type, row) {
                        // 'type' es 'display' cuando se renderiza para mostrar en la tabla
                        if (type === 'display') {
                            if (data) { // Si hay una URL de imagen
                                // Añadir clase CSS para poder darle estilo (ej: redondear)
                                return '<img src="' + escapeHtml(data) + '" alt="Avatar de ' + escapeHtml(row.instance_name) + '" class="instance-avatar" width="40" height="40" loading="lazy">'; // lazy loading es buena idea
                            } else { // Si no hay URL (data es null o vacío)
                                // Mostrar un placeholder (icono de WordPress)
                                return '<span class="avatar-placeholder dashicons dashicons-admin-users"></span>';
                            }
                        }
                        // Para otros tipos ('filter', 'sort', etc.), devolver el dato crudo o vacío
                        return data;
                    },
                    className: 'column-avatar' // Clase CSS para la celda (<td>)
                },
                // *** FIN: Nueva Columna Avatar ***

                // Columnas existentes
                { data: 'instance_name' }, // Nombre de la instancia
                { data: 'status', render: function(data, type, row) {
                    // Renderizar el estado con colores/clases
                    let statusClass = 'badge-secondary'; // Clase por defecto
                    let statusText = data ? data.charAt(0).toUpperCase() + data.slice(1) : 'Desconocido';

                    // Mapeo mejorado de estados comunes de Evolution API
                    switch (data ? data.toLowerCase() : 'unknown') {
                        case 'open':
                        case 'connected': // Tratar 'connected' como 'open'
                        case 'connection': // Tratar 'connection' como 'open'
                            statusClass = 'badge-success';
                            statusText = 'Conectado';
                            break;
                        case 'qrcode':
                            statusClass = 'badge-warning';
                            statusText = 'Esperando QR';
                            break;
                        case 'connecting':
                            statusClass = 'badge-info';
                            statusText = 'Conectando...';
                            break;
                        case 'close':
                        case 'disconnected': // Tratar 'disconnected' como 'close'
                            statusClass = 'badge-danger';
                            statusText = 'Desconectado';
                            break;
                        default:
                        statusText = data ? escapeHtml(data) : 'Desconocido'; // Mostrar estado desconocido si no coincide
                    }
                    // Usar las clases de badge que definimos en style.css
                    return `<span class="badge ${statusClass}">${escapeHtml(statusText)}</span>`;
                }},
                { data: 'owner', render: function(data, type, row){ // Extraer solo el número
                    return data ? escapeHtml(data.split('@')[0]) : 'N/D';
                }},
                { data: null, orderable: false, searchable: false, render: function(data, type, row) {
                    // Botones de acción (Editar, Eliminar, Conectar/Desconectar, etc.)
                    let instanceNameEsc = escapeHtml(row.instance_name);
                    let status = row.status ? row.status.toLowerCase() : 'unknown';
                    let actions = '';

                    // Botón QR: Solo si el estado es 'qrcode' o 'connecting' (a veces 'connecting' pide QR)
                    if (status === 'qrcode' || status === 'connecting') {
                        actions += `<button class="button button-small btn-show-qr" data-instance="${instanceNameEsc}" title="Mostrar QR Code"><span class="dashicons dashicons-camera"></span></button> `;
                    }
                    // Botón Conectar: Solo si el estado es 'close' o 'disconnected'
                    if (status === 'close' || status === 'disconnected') {
                        actions += `<button class="button button-small btn-connect-instance" data-instance="${instanceNameEsc}" title="Conectar Instancia"><span class="dashicons dashicons-admin-plugins"></span></button> `;
                    }
                    // Botón Desconectar: Solo si está conectado ('open', 'connected', 'connection')
                    if (status === 'open' || status === 'connected' || status === 'connection') {
                        actions += `<button class="button button-small btn-disconnect-instance" data-instance="${instanceNameEsc}" title="Desconectar Instancia"><span class="dashicons dashicons-exit"></span></button> `;
                    }
                    // Botón Eliminar: Siempre visible (o según tu lógica)
                    actions += `<button class="button button-small button-danger btn-delete-instance" data-instance="${instanceNameEsc}" title="Eliminar Instancia"><span class="dashicons dashicons-trash"></span></button>`;

                    return actions;
                }, className: 'column-actions' // Añadir clase para posible estilo
            }
            ],
            language: dataTablesLang, // Usar traducción al español
            order: [[ 1, 'asc' ]] // Ordenar por nombre de instancia (ahora índice 1) por defecto
        });
    }


    /**
    * Inicializa la DataTable para la tabla de Usuarios WP.
    */
    function initUsersTable() {
        crm_js_log('Inicializando DataTable para Usuarios WP.');
         if ($('#users-table').length === 0) {
            crm_js_log('Tabla #users-table no encontrada en el DOM.', 'WARN');
            return;
        }
        if (usersTable) {
             crm_js_log('DataTable de Usuarios ya inicializada. Refrescando datos...');
            usersTable.ajax.reload();
            return;
        }

        usersTable = $('#users-table').DataTable({
            processing: true,
            serverSide: false, // Podría ser true si hay muchos usuarios
             ajax: {
                url: crm_evolution_sender_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'crm_get_wp_users',
                    _ajax_nonce: crm_evolution_sender_params.nonce
                },
                 dataSrc: function(json) {
                    console.log(json)
                    if (!json || !json.success || !Array.isArray(json.data)) {
                        crm_js_log('Error o formato inesperado en la respuesta AJAX para get_wp_users.', 'ERROR', json);
                        showNotification('Error al cargar usuarios.', 'error');
                        return [];
                    }
                    crm_js_log('Datos de usuarios WP recibidos:', json.data);
                    return json.data;
                },
                error: function(xhr, status, error) {
                    crm_js_log('Error AJAX al obtener usuarios WP.', 'ERROR', { status: status, error: error, response: xhr.responseText });
                    showNotification('Error de conexión al cargar usuarios.', 'error');
                }
            },
            columns: [
                { data: 'id' },
                { data: 'user_login' },
                { data: 'etiqueta', defaultContent: '<i>N/A</i>' },
                { data: 'display_name' },
                { data: 'user_email' },
                { data: 'roles', render: function(data, type, row){ return Array.isArray(data) ? data.join(', ') : data; } }, // Mostrar roles
                { data: 'phone', defaultContent: '<i>N/D</i>' } // Asumiendo que 'phone' viene del backend
            ],
             language: dataTablesLang,
             // Habilitar selección de filas con checkboxes
             select: {
                style: 'multi', // Permitir selección múltiple
                selector: 'td:first-child input[type="checkbox"]' // Selector para activar selección
             },
             order: [[1, 'asc']] // Ordenar por ID por defecto
        });

        // Evento para el checkbox "seleccionar todos"
        $('#select-all-users').on('change', function() {
            let isChecked = $(this).prop('checked');
            $('.user-select-checkbox').prop('checked', isChecked).trigger('change'); // Marcar/desmarcar todos y disparar change
             if (isChecked) {
                usersTable.rows().select();
            } else {
                usersTable.rows().deselect();
            }
        });

        // Actualizar estado del botón de acción al cambiar selección
        usersTable.on('select deselect', function () {
            updateUserActionButtonState();
        });
         // También manejar el cambio directo en checkboxes individuales
        $('#users-table tbody').on('change', '.user-select-checkbox', function() {
             // Sincronizar la selección de DataTables con el estado del checkbox
            let row = usersTable.row($(this).closest('tr'));
            if ($(this).prop('checked')) {
                row.select();
            } else {
                row.deselect();
            }
            // Actualizar el checkbox "seleccionar todos" si es necesario
            let allChecked = $('.user-select-checkbox:checked').length === usersTable.rows().count();
            let someChecked = $('.user-select-checkbox:checked').length > 0;
            $('#select-all-users').prop('checked', allChecked);
            $('#select-all-users').prop('indeterminate', !allChecked && someChecked); // Estado intermedio

            updateUserActionButtonState();
        });
    }


    function initCampaignsTable() {
        crm_js_log('Inicializando DataTable para Campañas.');
        if ($('#campaigns-table').length === 0) {
            crm_js_log('Tabla #campaigns-table no encontrada en el DOM.', 'WARN');
            return;
        }
        if (campaignsTable) {
            crm_js_log('DataTable de Campañas ya inicializada. Refrescando datos...');
            campaignsTable.ajax.reload();
            return;
        }

        campaignsTable = $('#campaigns-table').DataTable({
            processing: true,
            serverSide: false, // Cambiar si es necesario
            ajax: {
                url: crm_evolution_sender_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'crm_get_campaigns',
                    _ajax_nonce: crm_evolution_sender_params.nonce
                },
                dataSrc: function(json) {
                    console.log(json) 
                    if (!json || !json.success || !Array.isArray(json.data)) {
                        crm_js_log('Error o formato inesperado en la respuesta AJAX para get_campaigns.', 'ERROR', json);
                        showNotification('Error al cargar campañas.', 'error');
                        return [];
                    }
                    crm_js_log('Datos de campañas recibidos:', json.data);
                    return json.data;
                },
                error: function(xhr, status, error) {
                    crm_js_log('Error AJAX al obtener campañas.', 'ERROR', { status: status, error: error, response: xhr.responseText });
                    showNotification('Error de conexión al cargar campañas.', 'error');
                }
            },
            columns: [
                { data: 'name' }, // <--- CORREGIDO (coincide con get_the_title() en PHP)
                { data: 'instance_name' }, // OK (asumiendo meta key _crm_instance_name)
                { data: 'message', render: function(data, type, row){ return data ? escapeHtml(data).substring(0, 50) + '...' : ''; } }, // OK (asumiendo meta key _crm_message_content) - Añadido chequeo por si está vacío
                { data: 'media_url', render: function(data, type, row){ return data ? `<a href="${escapeHtml(data)}" target="_blank">Ver Media</a>` : 'No'; } }, // OK (asumiendo meta key _crm_media_url)
                { data: 'target_tag', defaultContent: '<i>N/A</i>' }, // OK (asumiendo meta key _crm_target_tag) - Cambiado defaultContent
                // { data: 'recipients_count', defaultContent: '0' }, // <-- Dato no provisto por PHP actualmente
                // { data: 'scheduled_date', defaultContent: 'N/A' }, // <-- Dato no provisto por PHP actualmente
                { data: 'interval_minutes', defaultContent: '<i>N/A</i>' }, // OK (asumiendo meta key _crm_interval_minutes) - Cambiado defaultContent
                { data: 'status', render: function(data, type, row) { // OK (usa get_post_status() en PHP)
                    let statusClass = data ? data.toLowerCase() : 'unknown';
                    let statusText = data || 'Desconocido';
                    // Mapeo simple (ajustar según estados reales de WP o meta)
                    if (statusText === 'publish') statusText = 'Publicada'; // Mapeo de ejemplo
                    if (statusText === 'draft') statusText = 'Borrador';
                    if (statusText === 'pending') statusText = 'Pendiente';
                    // Añadir más mapeos si usas estados personalizados o meta fields
                    // Ejemplo si usaras un meta field _crm_campaign_status:
                    // if (row._crm_campaign_status === 'paused') statusText = 'Pausada';

                    return `<span class="campaign-status ${statusClass}">${escapeHtml(statusText)}</span>`;
                }},
                { data: null, orderable: false, searchable: false, render: function(data, type, row) { // OK (usa row.id que viene de get_the_ID())
                    // Botones de acción (Editar, Eliminar, Pausar/Reanudar, Ver Detalles)
                    let campaignId = escapeHtml(row.id); // Asumiendo que hay un ID
                    let actions = `<button class="button button-small btn-edit-campaign" data-id="${campaignId}" title="Editar"><span class="dashicons dashicons-edit"></span></button> `;
                    actions += `<button class="button button-small btn-delete-campaign" data-id="${campaignId}" title="Eliminar"><span class="dashicons dashicons-trash"></span></button> `;
                    // Añadir más acciones según estado (ej: pausar si está enviando/pendiente)
                    // Necesitarías ajustar la lógica de estado aquí también
                    // if (row.status === 'publish' /* o tu estado 'activo' */) {
                    //      actions += `<button class="button button-small btn-pause-campaign" data-id="${campaignId}" title="Pausar"><span class="dashicons dashicons-controls-pause"></span></button> `;
                    // } else if (row.status === 'draft' /* o tu estado 'pausado' */) {
                    //      actions += `<button class="button button-small btn-resume-campaign" data-id="${campaignId}" title="Reanudar/Publicar"><span class="dashicons dashicons-controls-play"></span></button> `;
                    // }
                    // actions += `<button class="button button-small btn-view-campaign" data-id="${campaignId}" title="Ver Detalles"><span class="dashicons dashicons-visibility"></span></button>`;
                    return actions;
                }}
            ],
            language: dataTablesLang
        });
    }
   
    /**
     * Muestra una notificación usando SweetAlert2.
     * @param {string} title Título de la notificación.
     * @param {string} icon Tipo de icono ('success', 'error', 'warning', 'info', 'question').
     * @param {string} text Texto adicional (opcional).
     */
    function showNotification(title, icon = 'info', text = '') {
        Swal.fire({
            title: title,
            text: text,
            icon: icon,
            toast: true, // Mostrar como notificación pequeña
            position: 'top-end', // Posición en la esquina superior derecha
            showConfirmButton: false, // No mostrar botón de confirmación
            timer: 3500, // Duración en milisegundos
            timerProgressBar: true, // Mostrar barra de progreso
             didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
        crm_js_log(`Notificación mostrada: [${icon}] ${title} ${text}`);
    }

     /**
     * Escapa caracteres HTML para prevenir XSS.
     * @param {string} str La cadena a escapar.
     * @returns {string} La cadena escapada.
     */
    function escapeHtml(str) {
        if (typeof str !== 'string') return str;
        return str.replace(/[&<>"']/g, function (match) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[match];
        });
    }

    // Traducción básica de DataTables al español
    const dataTablesLang = {
        "decimal": "",
        "emptyTable": "No hay datos disponibles en la tabla",
        "info": "Mostrando _START_ a _END_ de _TOTAL_ entradas",
        "infoEmpty": "Mostrando 0 a 0 de 0 entradas",
        "infoFiltered": "(filtrado de _MAX_ entradas totales)",
        "infoPostFix": "",
        "thousands": ",",
        "lengthMenu": "Mostrar _MENU_ entradas",
        "loadingRecords": "Cargando...",
        "processing": "Procesando...",
        "search": "Buscar:",
        "zeroRecords": "No se encontraron registros coincidentes",
        "paginate": {
            "first": "Primero",
            "last": "Último",
            "next": "Siguiente",
            "previous": "Anterior"
        },
        "aria": {
            "sortAscending": ": activar para ordenar la columna ascendente",
            "sortDescending": ": activar para ordenar la columna descendente"
        }
    };

    // --- Funciones AJAX ---

    /**
     * Realiza una llamada AJAX genérica al backend de WordPress.
     * @param {string} action La acción de WordPress AJAX a ejecutar.
     * @param {object} data Datos adicionales a enviar.
     * @param {function} onSuccess Callback en caso de éxito (recibe la respuesta).
     * @param {function} onError Callback en caso de error (opcional).
     */
    function performAjaxRequest(action, data, onSuccess, onError) {
        crm_js_log(`Iniciando AJAX action: ${action}`, 'DEBUG', data);

        const requestData = {
            action: action,
            _ajax_nonce: crm_evolution_sender_params.nonce,
            ...data // Combina los datos adicionales
        };

        return $.ajax({
            url: crm_evolution_sender_params.ajax_url,
            type: 'POST',
            data: requestData,
            dataType: 'json', // Esperamos una respuesta JSON del servidor
            beforeSend: function() {
                // Mostrar algún indicador de carga si es necesario
                 Swal.showLoading(); // Usar el loading de SweetAlert
            },
            success: function(response) {
                 Swal.close(); // Ocultar loading
                crm_js_log(`Respuesta AJAX para ${action}:`, 'DEBUG', response);
                if (response && response.success) {
                    if (onSuccess && typeof onSuccess === 'function') {
                        onSuccess(response.data); // Pasar solo los datos al callback
                    }
                } else {
                    const errorMessage = response && response.data && response.data.message ? response.data.message : 'Ocurrió un error desconocido.';
                    crm_js_log(`Error en la respuesta AJAX para ${action}: ${errorMessage}`, 'ERROR', response);
                    showNotification('Error', 'error', errorMessage);
                    if (onError && typeof onError === 'function') {
                        onError(response); // Pasar la respuesta completa al callback de error
                    }
                }
            },
            error: function(xhr, status, error) {
                 Swal.close(); // Ocultar loading
                const errorMsg = `Error de conexión o del servidor (${status}): ${error}`;
                crm_js_log(`Error AJAX en ${action}: ${errorMsg}`, 'ERROR', { status: status, error: error, response: xhr.responseText });
                showNotification('Error de Red', 'error', 'No se pudo comunicar con el servidor. Revisa tu conexión o contacta al administrador.');
                 if (onError && typeof onError === 'function') {
                    onError({ success: false, data: { message: errorMsg } }); // Simular estructura de respuesta
                }
            },
            complete: function() {
                // Ocultar indicador de carga si se usó uno diferente a Swal
            }
        });
    }

    // --- Funciones CRUD y de Interacción ---
    // ** Instancias **

    /** Elimina una instancia */
    function deleteInstance(instanceName) {
        crm_js_log(`Solicitando confirmación para eliminar instancia: ${instanceName}`);
        Swal.fire({
            title: '¿Estás seguro?',
            text: `Estás a punto de eliminar la instancia "${escapeHtml(instanceName)}". ¡Esta acción no se puede deshacer!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminarla',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                crm_js_log(`Confirmada eliminación de instancia: ${instanceName}`);
                performAjaxRequest('crm_delete_instance', { instance_name: instanceName },
                    (response) => {
                        showNotification('Instancia eliminada', 'success', response.message || `La instancia ${instanceName} ha sido eliminada.`);
                        if (instancesTable) instancesTable.ajax.reload();
                    }
                    // onError es manejado por performAjaxRequest
                );
            } else {
                 crm_js_log(`Eliminación de instancia ${instanceName} cancelada.`);
            }
        });
    }

     /** Muestra el código QR para una instancia */
    function showQrCode(instanceName) {
        crm_js_log(`Solicitando QR Code para instancia: ${instanceName}`);
         // Usar performAjaxRequest para obtener el QR (base64) o la URL del QR
         performAjaxRequest('crm_get_instance_qr', { instance_name: instanceName },
            (response) => {
                if (response.qrCode) {
                     crm_js_log(`QR Code recibido para ${instanceName}`);
                     Swal.fire({
                        title: `Conectar Instancia: ${escapeHtml(instanceName)}`,
                        html: `
                            <p>Escanea este código QR con la aplicación WhatsApp en tu teléfono:</p>
                            <div id="qrcode-container" style="margin: 20px 0;">
                                <img src="${response.qrCode}" alt="Código QR" style="display: block; margin: 0 auto; max-width: 250px; height: auto;">
                            </div>
                            <p><small>El código QR se actualiza periódicamente. Si expira, cierra esta ventana y vuelve a intentarlo.</small></p>
                            <p><strong>Estado actual:</strong> <span id="qr-status">Esperando escaneo...</span></p>
                        `,
                        showConfirmButton: false, // No necesitamos botón de confirmar
                        showCancelButton: true,
                        cancelButtonText: 'Cerrar',
                        // Podríamos añadir lógica para refrescar el QR o comprobar estado aquí si la API lo permite
                    });
                    // Aquí podrías iniciar un polling o WebSocket para verificar el estado de conexión si la API lo soporta
                } else {
                    crm_js_log(`No se recibió QR Code para ${instanceName}. Respuesta:`, 'WARN', response);
                    showNotification('Error al obtener QR', 'error', response.message || 'No se pudo obtener el código QR.');
                }
            }
            // onError es manejado por performAjaxRequest
         );
    }

     /** Conecta/Reconecta una instancia */
    function connectInstance(instanceName) {
        crm_js_log(`Intentando conectar instancia: ${instanceName}`);
        performAjaxRequest('crm_connect_instance', { instance_name: instanceName },
            (response) => {
                showNotification('Conexión iniciada', 'info', response.message || `Intentando conectar la instancia ${instanceName}.`);
                if (instancesTable) instancesTable.ajax.reload(null, false); // Recargar sin resetear paginación
                 // Si la respuesta indica que ahora necesita QR, mostrarlo
                 if (response.status === 'qrcode') {
                    showQrCode(instanceName);
                 }
            }
        );
    }

    /** Desconecta una instancia */
    function disconnectInstance(instanceName) {
        crm_js_log(`Intentando desconectar instancia: ${instanceName}`);
         Swal.fire({
            title: '¿Desconectar Instancia?',
            text: `¿Seguro que quieres desconectar la instancia "${escapeHtml(instanceName)}"?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, desconectar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                performAjaxRequest('crm_disconnect_instance', { instance_name: instanceName },
                    (response) => {
                        showNotification('Instancia desconectada', 'success', response.message || `La instancia ${instanceName} ha sido desconectada.`);
                        if (instancesTable) instancesTable.ajax.reload(null, false);
                    }
                );
            }
        });
    }

    // --- INICIO: Manejador para el formulario de Añadir Instancia (Thickbox) ---
    $(document).on('submit', '#add-instance-form', function(event) {
        event.preventDefault(); // Prevenir envío normal del formulario

        crm_js_log('Formulario #add-instance-form enviado.');

        const $form = $(this);
        const $submitButton = $('#submit-add-instance', $form); // Seleccionar el botón de submit
        const originalButtonText = $submitButton.val(); // Guardar texto original del botón

        const instanceName = $('#instance_name', $form).val().trim();
        const nonce = $('#crm_create_instance_nonce', $form).val();
        const webhookUrl = $('#webhook_url', $form).val();

        // Validación básica (como antes)
        if (!instanceName) {
            showNotification('Error', 'error', 'El nombre de la instancia es obligatorio.');
            return;
        }
        const pattern = /^[a-zA-Z0-9_-]+$/;
        if (!pattern.test(instanceName)) {
            showNotification('Nombre Inválido', 'error', 'El nombre solo puede contener letras, números, guiones bajos (_) y guiones medios (-).');
            return;
        }

        // --- Deshabilitar botón y cambiar texto ANTES de AJAX ---
        $submitButton.prop('disabled', true).val(crm_evolution_sender_params.i18n.creatingText);

        // Preparar datos para AJAX (como antes)
        const data = {
            action: 'crm_create_instance',
            _ajax_nonce: nonce,
            instance_name: instanceName,
            webhook_url: webhookUrl,
        };

        crm_js_log('Enviando datos AJAX para crear instancia:', 'DEBUG', data);

        // Llamada AJAX
        $.post(crm_evolution_sender_params.ajax_url, data)
            .done(function(response) {
                crm_js_log('Respuesta AJAX (crear instancia) recibida:', 'DEBUG', response);
                if (response.success) {
                    console.log(response)
                    showNotification('¡Éxito!', 'success', response.data.message || 'Instancia creada correctamente.');
                    tb_remove();
                    if (instancesTable) {
                        instancesTable.ajax.reload();
                    } else {
                        crm_js_log('Variable instancesTable no definida, no se puede recargar.', 'WARN');
                    }
                    showQrCode(instanceName)
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error al Crear',
                        text: response.data.message || 'Ocurrió un error desconocido.'
                    });
                    crm_js_log('Error devuelto por el servidor al crear instancia:', 'ERROR', response.data);
                }
            })
            .fail(function(jqXHR, textStatus, errorThrown) {
                crm_js_log('Error en la llamada AJAX (crear instancia):', 'ERROR', { status: textStatus, error: errorThrown });
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Comunicación',
                    text: 'No se pudo conectar con el servidor. Detalles: ' + textStatus
                });
            })
            .always(function() {
            $submitButton.prop('disabled', false).val(originalButtonText);
            });
    });
    // --- FIN: Manejador para el formulario de Añadir Instancia ---

    // ** usuarios **
 

    // ** Marketing (Campañas) **
     
    // Función para cargar los selects del modal de Campañas
    function loadCampaignModalSelects() {
        crm_js_log('Cargando selects para modal de campaña...');

        const instanceSelect = $('#campaign_instance');
        const tagSelect = $('#campaign_target_tag');

        // 1. Cargar Instancias Activas
        if (instanceSelect.length) { // Solo si el select existe
            instanceSelect.empty().append($('<option>', { value: '', text: 'Cargando Instancias...' })); // Placeholder mientras carga

            $.ajax({
                url: crm_evolution_sender_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'crm_get_active_instances_for_select', // Acción PHP correcta
                    _ajax_nonce: crm_evolution_sender_params.nonce
                },
                success: function(response) {
                    instanceSelect.empty(); // Limpiar antes de añadir nuevas
                    if (response.success && Array.isArray(response.data) && response.data.length > 0) {
                        instanceSelect.append($('<option>', { value: '', text: '-- Seleccionar Instancia --' }));
                        $.each(response.data, function(index, instanceName) {
                            instanceSelect.append($('<option>', {
                                value: instanceName, // El valor es el nombre de la instancia
                                text: instanceName
                            }));
                        });
                            crm_js_log('Instancias cargadas en select.', 'INFO', response.data);
                    } else {
                        instanceSelect.append($('<option>', { value: '', text: 'No hay instancias activas', disabled: true }));
                        crm_js_log('No se encontraron instancias activas o hubo un error.', 'WARN', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    instanceSelect.empty().append($('<option>', { value: '', text: 'Error al cargar instancias', disabled: true }));
                    crm_js_log('Error AJAX cargando instancias para campaña:', 'ERROR', { status: status, error: error });
                }
            });
        } else {
                crm_js_log('Select #campaign_instance no encontrado.', 'WARN');
        }

        // 2. Cargar Etiquetas
        if (tagSelect.length) { // Solo si el select existe
            tagSelect.empty().append($('<option>', { value: '', text: 'Cargando Etiquetas...' })); // Placeholder

            $.ajax({
                url: crm_evolution_sender_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'crm_get_etiquetas_for_select', // Acción PHP correcta
                    _ajax_nonce: crm_evolution_sender_params.nonce
                },
                success: function(response) {
                    tagSelect.empty(); // Limpiar
                    if (response.success && Array.isArray(response.data) && response.data.length > 0) {
                        tagSelect.append($('<option>', { value: '', text: '-- Seleccionar Etiqueta --' }));
                        $.each(response.data, function(index, tag) {
                            tagSelect.append($('<option>', {
                                value: tag.value, // 'value' viene del PHP
                                text: tag.text    // 'text' viene del PHP
                            }));
                        });
                            crm_js_log('Etiquetas cargadas en select.', 'INFO', response.data);
                    } else {
                        tagSelect.append($('<option>', { value: '', text: 'No hay etiquetas definidas', disabled: true }));
                            crm_js_log('No se encontraron etiquetas o hubo un error.', 'WARN', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    tagSelect.empty().append($('<option>', { value: '', text: 'Error al cargar etiquetas', disabled: true }));
                    crm_js_log('Error AJAX cargando etiquetas para campaña:', 'ERROR', { status: status, error: error });
                }
            });
        } else {
                crm_js_log('Select #campaign_target_tag no encontrado.', 'WARN');
        }
    }

    // Llamar a la función para cargar los selects cuando el DOM esté listo
    // Esto asegura que los selects se pueblen antes de que el usuario abra el modal.
    if ($('#marketing-modal-content').length) { // Solo ejecutar si el modal existe en la página
        loadCampaignModalSelects();
    }
    
    
  
     /** Elimina una campaña */
    function deleteCampaign(campaignId) {
        crm_js_log(`Solicitando confirmación para eliminar campaña ID: ${campaignId}`);
         Swal.fire({
            title: '¿Estás seguro?',
            text: `Estás a punto de eliminar esta campaña. Los datos asociados podrían perderse.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminarla',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                 crm_js_log(`Confirmada eliminación de campaña ID: ${campaignId}`);
                performAjaxRequest('crm_delete_campaign', { id: campaignId },
                    (response) => {
                        showNotification('Campaña eliminada', 'success', response.message || `La campaña ha sido eliminada.`);
                        if (campaignsTable) campaignsTable.ajax.reload();
                    }
                );
            } else {
                 crm_js_log(`Eliminación de campaña ${campaignId} cancelada.`);
            }
        });
    }

    // --- Event Handlers ---

    /**
     * Inicializa los manejadores de eventos generales.
     */
    function initEventHandlers() {
        crm_js_log('Inicializando manejadores de eventos.');    

        $('#instances-table tbody').on('click', '.btn-delete-instance', function() {
            const instanceName = $(this).data('instance');
            deleteInstance(instanceName);
        });

         $('#instances-table tbody').on('click', '.btn-show-qr', function() {
            const instanceName = $(this).data('instance');
            showQrCode(instanceName);
        });

         $('#instances-table tbody').on('click', '.btn-connect-instance', function() {
            const instanceName = $(this).data('instance');
            connectInstance(instanceName);
        });

         $('#instances-table tbody').on('click', '.btn-disconnect-instance', function() {
            const instanceName = $(this).data('instance');
            disconnectInstance(instanceName);
        });

    
        // ** Pestaña Usuarios **
        $(document).on('submit', '#add-user-form', function(e) {
            e.preventDefault();
            crm_js_log('Formulario Añadir Usuario enviado.');

            if (!phoneInputInstance) {
                crm_js_log('Error: instancia de intl-tel-input no encontrada.', 'ERROR');
                showNotification('Error', 'error', 'El campo de teléfono no se inicializó correctamente. Intenta recargar.');
                return; // Detener el envío
            }
    
            if (!phoneInputInstance.isValidNumber()) {
                crm_js_log('Número de teléfono inválido según intl-tel-input.', 'WARN');
                showNotification('Número Inválido', 'warning', 'Por favor, introduce un número de teléfono válido para el país seleccionado.');
                return; // Detener el envío
            }
    
            const fullPhoneNumber = phoneInputInstance.getNumber(); // Obtiene formato E.164 (+51...)
            crm_js_log(`Número de teléfono internacional obtenido: ${fullPhoneNumber}`, 'DEBUG');
            

            const formData = $(this).serializeArray(); // Obtener datos del formulario
            let dataToSend = {
                action: 'crm_add_wp_user', // Acción AJAX para añadir usuario
                user_phone_full: fullPhoneNumber
                // _ajax_nonce: crm_evolution_sender_params.nonce // performAjaxRequest lo añade
            };

            // Convertir formData a objeto simple y añadir a dataToSend
            $.each(formData, function(index, field) {
                if (field.name !== 'user_phone_input') {
                    dataToSend[field.name] = field.value;
                }
                // dataToSend[field.name] = field.value;
            });

             crm_js_log('Datos a enviar para añadir usuario:', 'DEBUG', dataToSend);

            // Mostrar indicador de carga
            const submitButton = $(this).find('input[type="submit"]');
            submitButton.prop('disabled', true).val('Añadiendo...'); // Cambiar texto y deshabilitar

            performAjaxRequest(
                'crm_add_wp_user', // Acción AJAX
                dataToSend,        // Datos (incluye user_phone_full)
                function(response) { // onSuccess
                    // showNotification ya no es necesaria aquí si performAjaxRequest la maneja
                    // showNotification('Éxito', 'success', response.message || 'Usuario añadido correctamente.');
                    tb_remove(); // Cerrar el modal Thickbox
                    if (usersTable) { // Recargar la tabla de usuarios
                        usersTable.ajax.reload();
                    }
                },
                function(errorResponse) { // onError
                    // performAjaxRequest ya muestra un error genérico
                    crm_js_log('Error específico al añadir usuario.', 'ERROR', errorResponse);
                }
                // Elimina el callback onComplete si lo tenías como argumento separado
            ).always(function() { // <--- Usar always para restaurar el botón
                 // Reactivar el botón de envío y restaurar texto
                 submitButton.prop('disabled', false).val(originalButtonText);
                 crm_js_log('Botón de añadir usuario restaurado.');
                 // Swal.close() ya debería haber sido llamado por success/error de performAjaxRequest
            });
        });


        // ** Pestaña Marketing **
         $('#campaigns-table tbody').on('click', '.btn-edit-campaign', function() {
             const campaignId = $(this).data('id');
             crm_js_log(`Clic en editar campaña: ${campaignId} (funcionalidad pendiente de datos completos)`);
             showNotification('Función Pendiente', 'info', 'La edición de campañas requiere cargar datos adicionales.');
             // Idealmente:
             // performAjaxRequest('crm_get_campaign_details', { id: campaignId }, (data) => { openCampaignModal(data); });
         });

         $('#campaigns-table tbody').on('click', '.btn-delete-campaign', function() {
             const campaignId = $(this).data('id');
             deleteCampaign(campaignId);
         });
         // TODO: Añadir handlers para Pausar, Reanudar, Ver Detalles de campaña


        crm_js_log('Manejadores de eventos inicializados.');
    }


    // --- Ejecución Principal (DOM Ready) ---
    $(document).ready(function() {
        crm_js_log('DOM listo. Iniciando script del plugin.');

        // Verificar en qué pestaña estamos (leyendo la URL o un elemento específico)
        const urlParams = new URLSearchParams(window.location.search);
        const currentPage = urlParams.get('page');
        const currentTab = urlParams.get('tab');

        crm_js_log(`Página actual: ${currentPage}, Pestaña: ${currentTab}`);

        // Inicializar DataTables y otros elementos específicos de la pestaña activa
        if (currentPage === 'crm-evolution-sender-main') {
            if (!currentTab || currentTab === 'instancias') {
                if ($('#instances-table').length) {
                    initInstancesTable();
                } else {
                    crm_js_log('Contenedor de tabla de instancias no encontrado al cargar.', 'WARN');
                }
            } else if (currentTab === 'usuarios') {
                if ($('#users-table').length) {
                    initUsersTable();
                    // Inicializar intl-tel-input (si existe el campo)
                    const phoneInputField = document.querySelector("#user_phone_input");
                    if (phoneInputField) {
                        crm_js_log('Inicializando intl-tel-input en #user_phone_input');
                        try {
                            phoneInputInstance = window.intlTelInput(phoneInputField, {
                                utilsScript: crm_evolution_sender_params.utils_script_path,
                                initialCountry: "auto",
                                geoIpLookup: function(callback) {
                                    $.get("https://ipinfo.io", function() {}, "jsonp").always(function(resp) {
                                        var countryCode = (resp && resp.country) ? resp.country : "pe";
                                        callback(countryCode);
                                    });
                                },
                                separateDialCode: true,
                                preferredCountries: ['pe', 'co', 'mx', 'es', 'ar', 'us'],
                                nationalMode: false,
                                autoPlaceholder: "polite"
                            });
                            crm_js_log('Instancia de intl-tel-input creada.');
                        } catch (error) {
                            crm_js_log('Error al inicializar intl-tel-input.', 'ERROR', error);
                            showNotification('Error', 'error', 'No se pudo inicializar el campo de teléfono.');
                        }
                    } else {
                        crm_js_log('#user_phone_input no encontrado en el DOM al cargar la pestaña de usuarios.', 'WARN');
                    }
                } else {
                    crm_js_log('Contenedor de tabla de usuarios no encontrado al cargar.', 'WARN');
                }
            } else if (currentTab === 'marketing') {
                if ($('#campaigns-table').length) {
                    initCampaignsTable();
                } else {
                    crm_js_log('Contenedor de tabla de campañas no encontrado al cargar.', 'WARN');
                }
                // Cargar selects del modal de campañas (ya lo tienes fuera, pero aquí también es válido si solo se usa en esta pestaña)
                // if ($('#marketing-modal-content').length) {
                //     loadCampaignModalSelects();
                // }
            }
        }

        // --- INICIO: Lógica para Pestaña Marketing (Formulario y Media) ---

        const campaignForm = $('#campaign-form');
        const campaignModalContent = $('#marketing-modal-content'); // Contenedor del modal

        // Manejar el envío del formulario de Campaña (Crear/Actualizar)
        if (campaignForm.length) {
            // Usar .off().on() para evitar múltiples bindings si este código se ejecuta más de una vez
            campaignForm.off('submit.crmCampaign').on('submit.crmCampaign', function(event) {
                event.preventDefault(); // Evitar envío normal del formulario
                crm_js_log('Formulario de campaña enviado.');

                const submitButton = $(this).find('input[type="submit"]');
                // Guardar el texto original ANTES de deshabilitar
                const originalButtonText = submitButton.val();
                submitButton.val('Guardando...').prop('disabled', true);

                // Recoger datos del formulario
                const formData = {
                    action: 'crm_save_campaign', // La acción AJAX que creamos en PHP
                    _ajax_nonce: crm_evolution_sender_params.nonce,
                    campaign_id: $('#campaign_id').val(), // ID para saber si es edición
                    campaign_name: $('#campaign_name').val(),
                    campaign_instance: $('#campaign_instance').val(),
                    campaign_target_tag: $('#campaign_target_tag').val(),
                    campaign_interval: $('#campaign_interval').val() || 5, // Valor por defecto si está vacío
                    campaign_media_url: $('#campaign_media_url').val(),
                    campaign_message: $('#campaign_message').val()
                };

                crm_js_log('Datos a enviar para guardar campaña:', 'DEBUG', formData);

                // Realizar la petición AJAX
                $.ajax({
                    url: crm_evolution_sender_params.ajax_url,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            crm_js_log('Respuesta AJAX éxito (guardar campaña):', 'INFO', response);
                            showNotification(response.data.message || 'Campaña guardada correctamente.', 'success');
                            tb_remove(); // Cerrar el modal Thickbox

                            // Resetear el formulario
                            campaignForm[0].reset();
                            $('#campaign_id').val(''); // Limpiar ID oculto
                            $('#media-filename').text('').hide(); // Limpiar nombre de archivo multimedia
                            $('#clear-media-button').hide(); // Ocultar botón de limpiar media

                            // Recargar la tabla de campañas si ya está inicializada
                            if (typeof campaignsTable !== 'undefined' && campaignsTable) {
                                campaignsTable.ajax.reload();
                                crm_js_log('Tabla de campañas recargada.');
                            } else {
                                crm_js_log('Variable campaignsTable no definida, no se puede recargar.', 'WARN');
                                // Si la tabla no estaba inicializada (ej: primera campaña), inicializarla ahora
                                if ($('#campaigns-table').length) {
                                    initCampaignsTable();
                                }
                            }
                        } else {
                            crm_js_log('Respuesta AJAX error (guardar campaña):', 'ERROR', response);
                            showNotification(response.data.message || 'Error desconocido al guardar.', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        crm_js_log('Error AJAX (guardar campaña):', 'ERROR', { status: status, error: error, response: xhr.responseText });
                        showNotification('Error de conexión al guardar la campaña.', 'error');
                    },
                    complete: function() {
                        // Restaurar el botón de envío usando la variable guardada
                        submitButton.val(originalButtonText).prop('disabled', false);
                    }
                });
            });
        } else {
            crm_js_log('Formulario #campaign-form no encontrado.', 'WARN');
        }

        // --- Lógica para los botones de seleccionar/limpiar multimedia ---
        const mediaInput = $('#campaign_media_url');
        const mediaFilename = $('#media-filename');
        const selectMediaButton = $('#select-media-button');
        const clearMediaButton = $('#clear-media-button');
        let mediaFrame; // Variable para guardar la instancia del media uploader

        if (selectMediaButton.length && mediaInput.length) {
            // Usar delegación de eventos con $(document) para asegurar que funcione incluso si el modal se carga dinámicamente
            // Usar .off().on() para evitar bindings múltiples
            $(document).off('click.crmMedia', '#select-media-button').on('click.crmMedia', '#select-media-button', function(event) {
                event.preventDefault();
                crm_js_log('Botón Seleccionar Media clickeado.');

                // Si ya existe un frame, simplemente ábrelo
                if (mediaFrame) {
                    mediaFrame.open();
                    return;
                }

                // Crear un nuevo media frame
                mediaFrame = wp.media({
                    title: 'Seleccionar o Subir Multimedia',
                    button: {
                        text: 'Usar este archivo'
                    },
                    multiple: false // No permitir selección múltiple
                });

                // Cuando se selecciona un archivo
                mediaFrame.on('select', function() {
                    const attachment = mediaFrame.state().get('selection').first().toJSON();
                    mediaInput.val(attachment.url); // Poner la URL en el input
                    // Mostrar solo el nombre del archivo, no la ruta completa
                    mediaFilename.text('Archivo: ' + (attachment.filename || attachment.url.split('/').pop())).show();
                    clearMediaButton.show(); // Mostrar botón de limpiar
                    crm_js_log('Archivo multimedia seleccionado:', 'DEBUG', attachment);
                });

                // Abrir el media frame
                mediaFrame.open();
            });

            // Lógica para el botón de limpiar multimedia
            if (clearMediaButton.length) {
                $(document).off('click.crmMedia', '#clear-media-button').on('click.crmMedia', '#clear-media-button', function(event) {
                    event.preventDefault();
                    mediaInput.val(''); // Limpiar input
                    mediaFilename.text('').hide(); // Ocultar nombre de archivo
                    $(this).hide(); // Ocultar este botón
                    crm_js_log('Campo multimedia limpiado.');
                });

                // Opcional: Mostrar botón limpiar si el campo ya tiene valor al abrir modal (para editar)
                // Esto se manejaría mejor al *abrir* el modal para editar, no aquí globalmente.
            }

        } else {
            crm_js_log('Botón #select-media-button o input #campaign_media_url no encontrado.', 'WARN');
        }

        // --- FIN: Lógica para Pestaña Marketing ---


        // Inicializar los manejadores de eventos generales (YA EXISTENTE)
        // Asegúrate de que initEventHandlers() no duplique los manejadores que acabamos de poner aquí.
        // Si initEventHandlers() ya maneja los botones de media o el submit del form, quita el código duplicado de allí.
        initEventHandlers();

        crm_js_log('Script del plugin CRM Evolution Sender inicializado completamente.');
    }); // Fin de $(document).ready
   

})(jQuery); // Fin de la encapsulación
