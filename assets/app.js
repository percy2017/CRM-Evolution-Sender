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
                //  Swal.showLoading(); // Usar el loading de SweetAlert
            },
            success: function(response) {
                //  Swal.close(); // Ocultar loading
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

    /**
     * Cambia la vista de detalles del contacto a modo edición.
     */
    function switchToEditMode(userId) {
        const $ = jQuery;
        const $detailsContainer = $('#contact-details-content');
        crm_js_log(`Cambiando a modo edición para User ID: ${userId}`);

        // Guardar datos actuales antes de reemplazar
        const currentData = {
            name: $detailsContainer.find('label:contains("Nombre:")').next('span').text(),
            firstName: $detailsContainer.find('label:contains("Nombres:")').next('span').text(), // Nuevo
            lastName: $detailsContainer.find('label:contains("Apellidos:")').next('span').text(), // Nuevo
            phone: $detailsContainer.find('label:contains("Teléfono:")').next('span').text(),
            email: $detailsContainer.find('label:contains("Email:")').next('span').text(),
            tagKey: $detailsContainer.find('label:contains("Etiqueta:")').next('span.tag-badge').data('tag-key'),
            notes: $detailsContainer.find('.contact-notes-content').html().replace(/<br\s*\/?>/gi, '\n').replace(/<i>Sin notas<\/i>/i, '').trim() // Convertir <br> a \n y quitar placeholder
            // Rol y Fecha de Registro no son editables aquí
            // Historial de WooCommerce no es editable
        };
        crm_js_log('Datos actuales para edición:', 'DEBUG', currentData);

        // Reemplazar spans con inputs/textarea/select
        $detailsContainer.find('label:contains("Nombre:")').next('span').replaceWith(`<input type="text" id="edit-contact-name" class="regular-text" value="${escapeHtml(currentData.name)}">`);
        $detailsContainer.find('label:contains("Teléfono:")').nextAll().remove(); // Quitar span y small(JID)
        $detailsContainer.find('label:contains("Teléfono:")').after(`<input type="text" id="edit-contact-phone" class="regular-text" value="${escapeHtml(currentData.phone)}" readonly title="El teléfono se sincroniza desde WhatsApp, no se puede editar aquí.">`); // Teléfono no editable
        $detailsContainer.find('label:contains("Nombres:")').next('span').replaceWith(`<input type="text" id="edit-contact-first-name" class="regular-text" value="${escapeHtml(currentData.firstName)}">`);
        $detailsContainer.find('label:contains("Apellidos:")').next('span').replaceWith(`<input type="text" id="edit-contact-last-name" class="regular-text" value="${escapeHtml(currentData.lastName)}">`);
        $detailsContainer.find('label:contains("Email:")').next('span').replaceWith(`<input type="email" id="edit-contact-email" class="regular-text" value="${escapeHtml(currentData.email)}">`);

        // Reemplazar span de etiqueta con un select (requiere cargar etiquetas)
        const $tagSpan = $detailsContainer.find('label:contains("Etiqueta:")').next('span.tag-badge');
        const $tagSelect = $('<select id="edit-contact-tag" class="regular-text"></select>');
        $tagSpan.replaceWith($tagSelect);
        // Cargar opciones de etiquetas (similar a como se hizo para campañas)
        loadTagsIntoSelect($tagSelect, currentData.tagKey); // Nueva función auxiliar

        // Reemplazar div de notas con textarea
        $detailsContainer.find('.contact-notes-content').replaceWith(`<textarea id="edit-contact-notes" class="large-text" rows="4">${escapeHtml(currentData.notes)}</textarea>`);

        // Cambiar botones
        const $buttonContainer = $detailsContainer.find('div[style*="text-align: right"]');
        $buttonContainer.html(`
            <button id="save-contact-button" class="button button-primary" data-user-id="${userId}"><span class="dashicons dashicons-saved" style="vertical-align: middle; margin-top: -2px;"></span> Guardar</button>
            <button id="cancel-edit-button" class="button button-secondary" data-user-id="${userId}">Cancelar</button>
        `);
    }

    /**
     * Carga las etiquetas disponibles en un elemento select.
     * @param {jQuery} $selectElement El elemento select jQuery.
     * @param {string} selectedKey La clave de la etiqueta actualmente seleccionada.
     */
    function loadTagsIntoSelect($selectElement, selectedKey) {
        $selectElement.html('<option value="">Cargando...</option>'); // Placeholder
        performAjaxRequest(
            'crm_get_etiquetas_for_select', // Reutilizar acción AJAX
            {},
            function(tags) { // onSuccess
                $selectElement.empty().append('<option value="">-- Sin Etiqueta --</option>'); // Opción por defecto
                if (tags && tags.length > 0) {
                    tags.forEach(tag => {
                        const $option = $('<option>', { value: tag.key, text: tag.name }); // <-- Usar key y name
                        if (tag.key === selectedKey) {
                            $option.prop('selected', true);
                        }
                        $selectElement.append($option);
                    });
                }
            }
            // onError es manejado por performAjaxRequest
        );
    }

    /**
     * Guarda los detalles modificados del contacto vía AJAX.
     */
    function saveContactDetails(userId) {
        const $ = jQuery;
        const $detailsContainer = $('#contact-details-content');
        crm_js_log(`Intentando guardar detalles para User ID: ${userId}`);

        // Recoger datos del formulario de edición
        const updatedData = {
            user_id: userId,
            name: $('#edit-contact-name').val(),
            first_name: $('#edit-contact-first-name').val(), // Nuevo
            last_name: $('#edit-contact-last-name').val(),   // Nuevo
            email: $('#edit-contact-email').val(),
            tag_key: $('#edit-contact-tag').val(),
            notes: $('#edit-contact-notes').val()
        };
        crm_js_log('Datos a enviar para guardar:', 'DEBUG', updatedData);

        // Deshabilitar botones mientras guarda
        $('#save-contact-button, #cancel-edit-button').prop('disabled', true);

        performAjaxRequest(
            'crm_save_contact_details', // Nueva acción AJAX
            updatedData,
            function(response) { // onSuccess
                crm_js_log('Detalles guardados correctamente. Respuesta:', 'INFO', response);
                showNotification('Contacto actualizado', 'success');
                // Volver a cargar los detalles para mostrar la vista normal actualizada
                loadContactDetails(userId);
            }
            // onError es manejado por performAjaxRequest y muestra notificación
        ).always(function() { $('#save-contact-button, #cancel-edit-button').prop('disabled', false); }); // Reactivar botones
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

        // --- INICIO: Lógica para Historial de Chats ---
        // Solo ejecutar en la página de historial de chats
        if ($('#crm-chat-container').length) {
            // 1. Asegurar estado inicial oculto del sidebar
            $('#contact-details-column').removeClass('is-visible'); // Quitar clase al inicio por si acaso

            // 2. Log y cargar conversaciones iniciales
            crm_js_log('Página de Historial de Chats detectada. Cargando conversaciones...');
            loadRecentConversations();

            // 3. Inicializar Heartbeat
            crm_js_log('Inicializando Heartbeat para chat...');
            initChatHeartbeat();

            // 4. Inicializar Buscador y botón cerrar sidebar
            crm_js_log('Inicializando Buscador de Chats y handler de cierre de sidebar...');
            initChatSearchHandler();
            initCloseContactDetailsHandler(); // <-- Añadir la llamada aquí

            // --- INICIO: Listener para el botón Editar Contacto ---
            // Usar delegación en un contenedor estático superior si #contact-details-content se recarga
            $(document).on('click', '#edit-contact-button', function() {
                const userId = $(this).data('user-id');
                switchToEditMode(userId);
            });
            // --- FIN: Listener para el botón Editar Contacto ---

            // --- INICIO: Listener para el botón Guardar Contacto ---
            $(document).on('click', '#save-contact-button', function() {
                const userId = $(this).data('user-id');
                saveContactDetails(userId);
            });
            // --- FIN: Listener para el botón Guardar Contacto ---

            // --- INICIO: Listener para el botón Cancelar Edición ---
            $(document).on('click', '#cancel-edit-button', function() {
                const userId = $(this).data('user-id');
                loadContactDetails(userId); // Simplemente recargar los detalles originales
            });
            // --- FIN: Listener para el botón Editar Contacto ---
            // DENTRO de $(document).ready(function() { ... })
            // Y DENTRO de if ($('#crm-chat-container').length) { ... }
            
                // --- INICIO: Listener para maximizar imágenes del chat ---
                $('#chat-messages-area').on('click', '.message-media img', function(event) {
                    event.preventDefault(); // Prevenir comportamiento por defecto si la imagen está en un enlace
                    const imageUrl = $(this).attr('src');
                    const imageAlt = $(this).attr('alt') || 'Imagen del chat';
            
                    if (imageUrl) {
                        crm_js_log(`Maximizando imagen: ${imageUrl}`, 'DEBUG');
                        Swal.fire({
                            imageUrl: imageUrl,
                            imageAlt: imageAlt,
                            imageWidth: '90%', // O un tamaño específico como 800
                            imageHeight: 'auto',
                            animation: false, // Opcional: quitar animación de SweetAlert
                            showConfirmButton: false, // No necesitamos botón de confirmar
                            showCloseButton: true, // Mostrar botón de cerrar
                            backdrop: `
                                rgba(0,0,0,0.6)
                            `,
                            customClass: { // Clases personalizadas para más estilo si es necesario
                                popup: 'swal2-image-popup', // Para el contenedor del modal
                                image: 'swal2-maximized-image' // Para la imagen misma
                            }
                        });
                    }
                });
                crm_js_log('Listener para maximizar imágenes del chat inicializado.', 'INFO');
                // --- FIN: Listener para maximizar imágenes del chat ---
            
            // --- INICIO: Lógica para Vista Ampliada y Actualización de Avatar ---
            let avatarMediaFrame; // Para el selector de medios del avatar
            let currentEditingAvatarUserId = null; // Para saber a qué usuario pertenece el avatar que se está editando/viendo

            // 1. Listener para click en el avatar pequeño (delegado desde #contact-details-content)
            $('#contact-details-content').on('click', '.contact-details-avatar', function() {
                const $avatar = $(this);
                const avatarUrl = $avatar.attr('src');

                // El avatar en el que se hace clic pertenece al chat actualmente abierto
                if (!currentOpenChatUserId) {
                    crm_js_log('No se pudo determinar el User ID para mostrar el avatar ampliado (currentOpenChatUserId nulo).', 'ERROR');
                    showNotification('Error', 'error', 'No se pudo identificar al usuario.');
                    return;
                }
                currentEditingAvatarUserId = currentOpenChatUserId;

                $('#expanded-avatar-image').attr('src', avatarUrl);
                $('#contact-avatar-expanded-view').fadeIn(200);
                crm_js_log(`Mostrando vista ampliada de avatar para User ID: ${currentEditingAvatarUserId}`);
            });

            // 2. Listener para cerrar la vista ampliada del avatar
            // Usar delegación en document por si el botón está dentro de un div que se muestra/oculta
            $(document).on('click', '#close-avatar-expanded-view', function() {
                $('#contact-avatar-expanded-view').fadeOut(200);
                currentEditingAvatarUserId = null; // Limpiar el ID del usuario
                crm_js_log('Vista ampliada de avatar cerrada.');
            });

            // 3. Listener para el botón "Actualizar foto" en la vista ampliada
            $(document).on('click', '#trigger-update-avatar-button', function() {
                if (!currentEditingAvatarUserId) {
                    crm_js_log('Error: No hay User ID (currentEditingAvatarUserId) para actualizar avatar.', 'ERROR');
                    showNotification('Error', 'error', 'No se pudo identificar al usuario para actualizar la foto.');
                    return;
                }

                // Si el frame ya existe, ábrelo.
                if (avatarMediaFrame) {
                    avatarMediaFrame.open();
                    return;
                }

                // Crear el frame de medios.
                avatarMediaFrame = wp.media({
                    title: 'Seleccionar o Subir Nueva Foto de Perfil',
                    button: {
                        text: 'Usar esta imagen'
                    },
                    multiple: false,
                    library: {
                        type: 'image' // Solo permitir seleccionar imágenes
                    }
                });

                // Cuando se selecciona un archivo.
                avatarMediaFrame.on('select', function() {
                    const attachment = avatarMediaFrame.state().get('selection').first().toJSON();
                    crm_js_log('Nueva imagen de avatar seleccionada:', 'DEBUG', attachment);

                    if (!attachment.id || !attachment.url) {
                        crm_js_log('Error: El adjunto seleccionado no tiene ID o URL.', 'ERROR');
                        showNotification('Error', 'error', 'El archivo seleccionado no es válido.');
                        return;
                    }

                    // Llamar a AJAX para actualizar el avatar
                    performAjaxRequest(
                        'crm_update_contact_avatar', // Acción PHP que creamos
                        {
                            user_id: currentEditingAvatarUserId,
                            attachment_id: attachment.id // Enviar el ID del adjunto
                        },
                        function(response) { // onSuccess
                            crm_js_log('Avatar actualizado con éxito. Respuesta:', 'INFO', response);
                            showNotification('Foto de perfil actualizada', 'success');

                            // Actualizar la imagen en la vista ampliada
                            $('#expanded-avatar-image').attr('src', response.new_avatar_url);

                            // Actualizar el avatar pequeño en el sidebar de detalles del contacto
                            $('#contact-details-content .contact-details-avatar').attr('src', response.new_avatar_url);

                            // Actualizar el avatar en la lista de chats si el usuario está allí
                            const $chatListItem = $(`.chat-list-item[data-user-id="${currentEditingAvatarUserId}"]`);
                            if ($chatListItem.length) {
                                $chatListItem.find('.chat-avatar').attr('src', response.new_avatar_url);
                            }
                            // No cerramos la vista ampliada automáticamente, el usuario puede querer verla o cerrarla manualmente.
                        }
                        // onError es manejado por performAjaxRequest
                    );
                });
                // Abrir el frame.
                avatarMediaFrame.open();
            });
            crm_js_log('Listeners para la vista ampliada y actualización de avatar inicializados.');
            // --- FIN: Lógica para Vista Ampliada y Actualización de Avatar ---

            // --- INICIO: Cerrar sidebar de detalles al enfocar input de mensaje ---
            $('#chat-message-input').on('focus', function() {
                crm_js_log('Input de mensaje ha obtenido el foco. Intentando cerrar sidebar de detalles.');
                closeContactDetailsSidebar(); // Esta función ya existe y maneja la lógica de cierre
            });
            crm_js_log('Listener para cerrar sidebar al enfocar input de mensaje inicializado.');
            // --- FIN: Cerrar sidebar de detalles al enfocar input de mensaje ---

        } else {
            crm_js_log('Contenedor #crm-chat-container NO encontrado. Lógica de chat no se ejecutará.', 'WARN');
        }
        // *** FIN PRUEBA DEPURACIÓN GLOBAL DE CLICS ***

        /**
         * =========================================================================
         * == SIDEBAR DE DETALLES DEL CONTACTO (Funciones) ==
         * =========================================================================
         */

        /**
         * Cierra (oculta) el sidebar de detalles del contacto.
         */
        function closeContactDetailsSidebar() {
            const $ = jQuery;
            const $contactDetailsColumn = $('#contact-details-column');

            if ($contactDetailsColumn.css('display') !== 'none') { // Solo actuar si está visible
                crm_js_log('Cerrando sidebar de detalles del contacto.');
                $contactDetailsColumn.css('display', 'none');

                // Opcional: Desmarcar el chat activo en la lista izquierda
                $('#chat-list-items .chat-list-item.active').removeClass('active');
                currentOpenChatUserId = null; // Indicar que no hay chat abierto (si usas esta variable)
            }
        }

        /**
         * =========================================================================
         * == BUSCADOR DE CHATS (Funciones) ==
         * =========================================================================
        */

        let searchDebounceTimer; // Timer para debounce
        const SEARCH_DEBOUNCE_DELAY = 300; // Milisegundos de espera antes de buscar
        const MIN_SEARCH_LENGTH = 3; // Longitud mínima para buscar usuarios WP

        /**
         * Inicializa el manejador de eventos para el input de búsqueda de chats.
         */
        function initChatSearchHandler() {
            const $ = jQuery;
            const $searchInput = $('#chat-search-input');
            const $chatListContainer = $('#chat-list-items');

            if (!$searchInput.length || !$chatListContainer.length) {
                crm_js_log('Input de búsqueda #chat-search-input o contenedor #chat-list-items no encontrado.', 'ERROR');
                return;
            }
            $searchInput.on('input', function() {
                crm_js_log('[BUSCADOR] Evento input detectado.'); // <-- LOG 1

                clearTimeout(searchDebounceTimer); // Cancelar timer anterior

                searchDebounceTimer = setTimeout(() => {
                    const searchTerm = $(this).val().trim().toLowerCase();
                    crm_js_log(`Buscando chats/usuarios: "${searchTerm}"`);

                    // Ocultar columna de detalles al iniciar/modificar búsqueda
                    $('#contact-details-column').css('display', 'none'); // <-- Volver a display: none

                    // 1. Limpiar resultados de búsqueda de WP anteriores
                    $chatListContainer.find('.wp-user-search-result, .wp-user-search-loading, .wp-user-search-no-results, .wp-user-search-error').remove(); // Limpiar todo lo relacionado a búsqueda WP

                    // 2. Decidir si buscar vía AJAX o mostrar lista original
                    if (searchTerm.length >= MIN_SEARCH_LENGTH) { // Buscar usuarios WP
                        // Ocultar la lista original y buscar en WP
                        crm_js_log(`[BUSCADOR] Término >= ${MIN_SEARCH_LENGTH}. Ocultando lista original y buscando usuarios WP...`);
                        $chatListContainer.find('.chat-list-item:not(.wp-user-search-result)').hide(); // Ocultar originales
                        searchWpUsersForChat(searchTerm, $chatListContainer);
                    } else if (searchTerm === '') {
                        // Si se borró la búsqueda, mostrar todos los chats originales
                        crm_js_log('[BUSCADOR] Término vacío. Mostrando lista original.');
                        $chatListContainer.find('.chat-list-item:not(.wp-user-search-result)').show();
                    } else {
                        // Si el término es corto, mostrar la lista original
                        crm_js_log(`[BUSCADOR] Término corto (< ${MIN_SEARCH_LENGTH}). Mostrando lista original.`);
                        $chatListContainer.find('.chat-list-item:not(.wp-user-search-result)').show();

                    }

                }, SEARCH_DEBOUNCE_DELAY);
            });
        }

        /**
         * Realiza la búsqueda AJAX de usuarios WP y muestra los resultados.
         * @param {string} searchTerm El término de búsqueda.
         * @param {jQuery} container El contenedor donde mostrar los resultados.
         */
        function searchWpUsersForChat(searchTerm, container) {
            const $ = jQuery;
            // Mostrar un indicador de carga específico para la búsqueda de usuarios
            container.append('<p class="wp-user-search-loading"><span class="spinner is-active" style="float: none; vertical-align: middle;"></span> Buscando usuarios...</p>');

            performAjaxRequest(
                'crm_search_wp_users_for_chat',
                { search_term: searchTerm },
                function(users) { // onSuccess
                    container.find('.wp-user-search-loading').remove(); // Quitar indicador
                    if (users && users.length > 0) {
                        crm_js_log(`Usuarios WP encontrados: ${users.length}`, 'INFO', users);
                        users.forEach(user => {
                            // Crear HTML para el resultado de usuario WP
                            // TODO: Añadir un botón/enlace para "Iniciar Chat" que llame a una función futura
                            const userHtml = `
                                <div class="chat-list-item wp-user-search-result" data-wp-user-id="${user.user_id}">
                                    <img src="${user.avatar_url}" alt="${escapeHtml(user.display_name)}" class="chat-avatar">
                                    <div class="chat-item-details">
                                        <span class="chat-item-name">${escapeHtml(user.display_name)}</span>
                                        <span class="chat-item-snippet wp-user-label">Usuario de WordPress</span>
                                        <!-- <button class="button button-small start-chat-wp-user">Iniciar Chat</button> -->
                                    </div>
                                </div>`;
                            container.append(userHtml);
                        });
                        // Forzar la re-aplicación del manejador de clics para los nuevos elementos
                        crm_js_log('[searchWpUsersForChat] Resultados de búsqueda WP añadidos. Re-aplicando manejador de clics.', 'DEBUG');
                        addChatListItemClickHandler(); 
                        // TODO: Añadir handler para el botón "Iniciar Chat" si se implementa
                    } else {
                        crm_js_log('No se encontraron usuarios WP para iniciar chat.', 'INFO');
                        container.append('<p class="wp-user-search-no-results">No se encontraron usuarios de WordPress con teléfono que coincidan.</p>');
                    }
                },
                function(errorResponse) { // onError
                    container.find('.wp-user-search-loading').remove(); // Quitar indicador
                    container.append('<p class="wp-user-search-error error-message">Error al buscar usuarios.</p>');
                    // El error ya se muestra como notificación por performAjaxRequest
                }
            );
        }

           
        /**
         * Inicializa el manejador para el botón de cerrar el sidebar de detalles.
         */
        function initCloseContactDetailsHandler() {
            const $ = jQuery;
            // Usar delegación por si el sidebar se añade/quita dinámicamente (aunque ahora es fijo)
            $(document).on('click', '#close-contact-details', function() {
                crm_js_log('Botón Cerrar Detalles clickeado.');
                closeContactDetailsSidebar(); // Usar la función centralizada
            });
            crm_js_log('Handler para #close-contact-details inicializado.'); // Log para confirmar
        }
        
             
         
        crm_js_log('Script del plugin CRM Evolution Sender inicializado completamente.');
    }); // Fin de $(document).ready
   
    /**
     * =========================================================================
     * == HISTORIAL DE CHATS (Funciones) ==
     * =========================================================================
     */

    /**
     * Carga la lista de conversaciones recientes vía AJAX.
     * (Esta función ahora está fuera del document.ready, pero se llama desde dentro)
     */
    function loadRecentConversations() {
        const $ = jQuery; // Asegurar que $ es jQuery dentro de esta función
        const chatListContainer = $('#chat-list-items');
        // Usar el texto directamente aquí o pasarlo como parámetro si se necesita traducción compleja
        chatListContainer.html('<p><span class="spinner is-active" style="float: none; vertical-align: middle;"></span> Cargando conversaciones...</p>'); // Mostrar spinner

        $.ajax({
            url: crm_evolution_sender_params.ajax_url,
            type: 'POST',
            data: {
                action: 'crm_get_recent_conversations', // Acción registrada en PHP
                _ajax_nonce: crm_evolution_sender_params.nonce // Nonce de seguridad
            },
            success: function(response) {
                chatListContainer.empty(); // Limpiar el contenedor

                if (response.success && response.data.length > 0) {
                    crm_js_log('Conversaciones recibidas:', response.data);
                    response.data.forEach(function(convo) {
                        const lastMessageDate = new Date(convo.last_message_timestamp * 1000);
                        const timeString = lastMessageDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                        const chatItemHtml = `
                            <div class="chat-list-item" data-user-id="${convo.user_id}">
                                <img src="${convo.avatar_url}" alt="${escapeHtml(convo.display_name)}" class="chat-avatar">
                                <div class="chat-item-details">
                                    <div class="chat-item-header">
                                        <span class="chat-item-instance">[${escapeHtml(convo.instance_name)}]</span> <!-- Movido aquí -->
                                        <span class="chat-item-name">${escapeHtml(convo.display_name)}</span>
                                        <span class="chat-item-time">${timeString}</span>
                                    </div>
                                    <div class="chat-item-snippet">
                                        ${escapeHtml(convo.last_message_snippet)}
                                    </div>
                                </div>
                            </div>
                        `;
                        chatListContainer.append(chatItemHtml);
                    });

                    // Aquí añadiremos el manejador de click a los nuevos items
                    addChatListItemClickHandler();

                } else if (response.success && response.data.length === 0) { // Corregido: usar ===
                    chatListContainer.html('<p>No se encontraron conversaciones recientes.</p>');
                } else {
                    crm_js_log('Error al cargar conversaciones:', 'ERROR', response.data.message);
                    chatListContainer.html('<p class="error-message">Error al cargar conversaciones. ' + escapeHtml(response.data.message || '') + '</p>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                crm_js_log('Error AJAX al cargar conversaciones:', 'ERROR', { status: textStatus, error: errorThrown });
                chatListContainer.empty(); // Limpiar
                chatListContainer.html('<p class="error-message">Error de conexión al cargar conversaciones.</p>');
            }
        });
    }

     /**
      * Añade el manejador de clicks a los items de la lista de chat usando delegación.
      */
     function addChatListItemClickHandler() {
         const $ = jQuery;
         // Usar .off().on() para evitar múltiples bindings si se llama varias veces
         $('#chat-list-items').off('click', '.chat-list-item').on('click', '.chat-list-item', function() {
            crm_js_log('[DEBUG] Delegated .chat-list-item click detected on #chat-list-items!', 'DEBUG'); // <-- NUEVO LOG
             const $clickedItem = $(this); // Guardar referencia al item clickeado
             let userId = $clickedItem.data('user-id'); // Intentar obtener de data-user-id (chats existentes)
 
             // Si no se encontró en data-user-id, intentar con data-wp-user-id (resultados búsqueda WP)
             if (typeof userId === 'undefined' || userId === null) {
                 userId = $clickedItem.data('wp-user-id');
             }
 
             crm_js_log(`Click en chat list item. Intentando obtener User ID. Encontrado: ${userId} (Tipo: ${typeof userId})`);
 
             // Marcar como activo
             $('#chat-list-items .chat-list-item.active').removeClass('active'); // Quitar clase solo al activo
             $(this).addClass('active');
             // Ocultar placeholder al seleccionar chat
             $('#chat-messages-area').removeClass('chat-placeholder-active');
 
             $('#contact-details-column').css('display', 'flex');
             $('#contact-details-content').html('<p><span class="spinner is-active" style="float: none; vertical-align: middle;"></span> Cargando detalles...</p>');
             loadContactDetails(userId);
             currentOpenChatUserId = userId;
             lastDisplayedMessageTimestamp = 0;
             loadConversationMessages(userId);
             $('#chat-input-area').show();
         });
     }
 

    /**
     * Carga los mensajes para una conversación específica vía AJAX.
     * @param {number} userId El ID del usuario WP cuya conversación cargar.
     */
    function loadConversationMessages(userId) {
        // currentOpenChatUserId = userId; // <-- Ya se asigna en el click handler
        const $ = jQuery;
        const messagesContainer = $('#chat-messages-area'); // <-- CORREGIDO ID
        messagesContainer.html('<p><span class="spinner is-active" style="float: none; vertical-align: middle;"></span> Cargando mensajes...</p>'); // Mostrar spinner

        crm_js_log(`Solicitando mensajes para User ID: ${userId}`);

        crm_js_log(`[DEBUG] loadConversationMessages - ANTES de iniciar $.ajax para User ID ${userId}.`, 'DEBUG'); // <-- LOG NUEVO
        $.ajax({
            url: crm_evolution_sender_params.ajax_url,
            type: 'POST',
            data: {
                action: 'crm_get_conversation_messages', // Nueva acción PHP
                _ajax_nonce: crm_evolution_sender_params.nonce,
                user_id: userId
            },
            success: function(response) {
                crm_js_log(`[DEBUG] loadConversationMessages - Callback SUCCESS ejecutado para User ID ${userId}. Procediendo a procesar respuesta.`, 'DEBUG'); // <-- LOG NUEVO
                crm_js_log(`Respuesta recibida para mensajes de User ID ${userId}:`, 'DEBUG', response);
                messagesContainer.empty(); // Limpiar contenedor

                let latestTimestampInBatch = 0; // Para guardar el timestamp del último mensaje de este lote
                if (response.success && Array.isArray(response.data) && response.data.length > 0) {
                    response.data.forEach(function(msg) {
                        const messageDate = new Date(msg.timestamp * 1000);
                        const timeString = messageDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                        const messageClass = msg.is_outgoing ? 'chat-message outgoing' : 'chat-message incoming';

                        let messageContentHtml = '';

                        // Manejar diferentes tipos de mensajes
                        if (msg.type === 'image' && msg.attachment_url) {
                            messageContentHtml = `
                                <div class="message-media">
                                    <a href="${escapeHtml(msg.attachment_url)}" target="_blank" title="Ver imagen completa">
                                        <img src="${escapeHtml(msg.attachment_url)}" alt="Imagen adjunta" loading="lazy">
                                    </a>
                                </div>
                                ${msg.caption ? `<div class="message-caption">${escapeHtml(msg.caption)}</div>` : ''}
                            `;
                        } else if (msg.type === 'document' && msg.attachment_url) { // <-- NUEVO BLOQUE
                            // Extraer nombre de archivo como fallback si no hay caption
                            const filename = msg.caption || msg.attachment_url.split('/').pop();
                            messageContentHtml = `
                                <div class="message-document">
                                    <a href="${escapeHtml(msg.attachment_url)}" target="_blank" download="${escapeHtml(filename)}">
                                        <span class="dashicons dashicons-media-default" style="vertical-align: middle; margin-right: 5px;"></span> ${escapeHtml(filename)}
                                    </a>
                                </div>`;
                        } else if (msg.type === 'video' && msg.attachment_url) {
                            messageContentHtml = `
                                <div class="message-media">
                                    <video controls preload="metadata" src="${escapeHtml(msg.attachment_url)}"></video>
                                </div>
                                ${msg.caption ? `<div class="message-caption">${escapeHtml(msg.caption)}</div>` : ''}
                            `;
                        } else if (msg.type === 'document' && msg.attachment_url) {
                            messageContentHtml = `
                                <div class="message-document">
                                    <a href="${escapeHtml(msg.attachment_url)}" target="_blank" download>
                                        <span class="dashicons dashicons-media-default"></span> ${escapeHtml(msg.caption || msg.attachment_url.split('/').pop())}
                                    </a>
                                </div>`;
                        } else if (msg.type === 'audio' && msg.attachment_url) { // <-- Añadido para audio
                            messageContentHtml = `
                                <div class="message-media message-audio">
                                    <audio controls preload="metadata" src="${escapeHtml(msg.attachment_url)}"></audio>
                                </div>
                                ${msg.caption ? `<div class="message-caption">${escapeHtml(msg.caption)}</div>` : ''} <!-- Aunque raro, soportar caption -->`;
                        } else { // Texto o tipo desconocido
                            messageContentHtml = `<div class="message-text">${escapeHtml(msg.text || msg.caption || '')}</div>`;
                        }

                        const messageHtml = `
                            <div class="${messageClass}" data-msg-id="${msg.id}">
                                <div class="message-bubble">
                                    ${messageContentHtml}
                                    <div class="message-time">${timeString}</div>
                                </div>
                            </div>
                        `;
                        messagesContainer.append(messageHtml);

                        // Actualizar el timestamp más reciente de este lote
                        if (msg.timestamp > latestTimestampInBatch) {
                            latestTimestampInBatch = msg.timestamp;
                        }
                    });

                    // Hacer scroll hasta el último mensaje
                    lastDisplayedMessageTimestamp = latestTimestampInBatch; // <-- Guardar timestamp del último mensaje mostrado
                    messagesContainer.scrollTop(messagesContainer[0].scrollHeight);

                } else if (response.success && response.data.length === 0) {
                    messagesContainer.html('<p class="no-messages">No hay mensajes en esta conversación.</p>');
                } else {
                    crm_js_log(`Error en la respuesta AJAX al cargar mensajes para User ID ${userId}:`, 'ERROR', response.data.message);
                    messagesContainer.html('<p class="error-message">Error al cargar los mensajes. ' + escapeHtml(response.data.message || '') + '</p>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                crm_js_log(`[ERROR] loadConversationMessages - Callback ERROR ejecutado para User ID ${userId}. Status: ${textStatus}, Error: ${errorThrown}`, 'ERROR', jqXHR); // <-- LOG NUEVO
                crm_js_log(`Error AJAX al cargar mensajes para User ID ${userId}:`, 'ERROR', { status: textStatus, error: errorThrown });
                messagesContainer.html('<p class="error-message">Error de conexión al cargar mensajes.</p>');
            },
            complete: function(jqXHR, textStatus) {
                // Este callback se ejecuta después de success o error
                crm_js_log(`[DEBUG] loadConversationMessages - Callback COMPLETE ejecutado para User ID ${userId}. Status de la petición: ${textStatus}`, 'DEBUG', jqXHR); // <-- LOG NUEVO

                // Solo adjuntar listeners si la petición fue exitosa (aunque no haya mensajes)
                // El objeto jqXHR en complete es el XHR, no la respuesta parseada directamente como en success.
                // Necesitamos verificar el estado de la respuesta.
                let wasSuccessful = false;
                if (jqXHR.responseJSON && typeof jqXHR.responseJSON.success !== 'undefined') {
                    wasSuccessful = jqXHR.responseJSON.success;
                } else if (textStatus === 'success' && jqXHR.status === 200) { // Fallback si no es nuestro formato JSON
                    // Si dataType no es 'json' o la respuesta no es JSON, responseJSON será undefined.
                    // En ese caso, confiamos en textStatus y jqXHR.status.
                    // Para que esto funcione bien, el servidor DEBE devolver un JSON válido si dataType es 'json'.
                    wasSuccessful = true; 
                }

                if (wasSuccessful) {
                    crm_js_log('[loadConversationMessages - complete] La carga de mensajes fue exitosa (o no había mensajes). Adjuntando listeners del input.', 'DEBUG');
                    
                    // *** INICIO: LÓGICA DE ADJUNTAR LISTENERS (MOVIDA AQUÍ) ***
                    const $sendButton = $('#send-chat-message');
                    const $messageInput = $('#chat-message-input');
                    const $emojiButton = $('#emoji-picker-button');
                    const $emojiContainer = $('#emoji-picker-container');
                    const $attachButton = $('.btn-attach'); // Asegúrate que esta clase sea la correcta para tu botón de adjuntar
                    const $previewContainer = $('#chat-attachment-preview');
                    let currentAttachment = null; // Variable local para el adjunto actual

                    if ($sendButton.length && $messageInput.length) {
                        // Listener para el botón de enviar
                        $sendButton.off('click.sendMessage').on('click.sendMessage', function() {
                            // alert('¡Clic en Enviar Mensaje detectado!'); // Descomenta si necesitas el alert
                            crm_js_log('[ENVIO] Botón Enviar Mensaje clickeado (directo).');
                            const messageText = $messageInput.val().trim();
                            const recipientUserId = currentOpenChatUserId;
                            crm_js_log(`[ENVIO] Texto: "${messageText}", UserID: ${recipientUserId}, Adjunto:`, 'DEBUG', currentAttachment);

                            if (!recipientUserId) {
                                crm_js_log('[ENVIO] Validación fallida: No hay chat activo.', 'ERROR');
                                showNotification('Error', 'error', 'No hay una conversación seleccionada.');
                                return;
                            }

                            if (currentAttachment) {
                                crm_js_log(`[ENVIO] Intentando enviar adjunto a User ID: ${recipientUserId}`, 'INFO', currentAttachment);
                                $sendButton.prop('disabled', true);
                                performAjaxRequest('crm_send_media_message_ajax', { user_id: recipientUserId, attachment_url: currentAttachment.url, mime_type: currentAttachment.mime, filename: currentAttachment.filename, caption: messageText },
                                    function(response) { // onSuccess para envío de media
                                        crm_js_log('[ENVIO-MEDIA] AJAX onSuccess ejecutado.', 'INFO', response);
                                        // Verificar si la respuesta del backend incluye el mensaje enviado
                                        if (response && response.sent_message) {
                                            crm_js_log('[ENVIO-MEDIA] Renderizando mensaje enviado (optimista).', 'DEBUG', response.sent_message);
                                            renderSingleMessage(response.sent_message, messagesContainer);
                                            messagesContainer.scrollTop(messagesContainer[0].scrollHeight); // Scroll al nuevo mensaje
                                        }
                                        currentAttachment = null; $previewContainer.empty().hide(); $messageInput.val(''); 
                                    }
                                ).always(function() { $sendButton.prop('disabled', false); });
                            } else if (messageText) {
                                crm_js_log(`[ENVIO] Intentando enviar texto a User ID: ${recipientUserId}`, 'INFO', { text: messageText });
                                $sendButton.prop('disabled', true);
                                performAjaxRequest('crm_send_message_ajax', { user_id: recipientUserId, message_text: messageText },
                                    function(response) { // onSuccess para envío de texto
                                        crm_js_log('[ENVIO-TEXTO] AJAX onSuccess ejecutado.', 'INFO', response);
                                        // Verificar si la respuesta del backend incluye el mensaje enviado
                                        if (response && response.sent_message) {
                                            crm_js_log('[ENVIO-TEXTO] Renderizando mensaje enviado (optimista).', 'DEBUG', response.sent_message);
                                            renderSingleMessage(response.sent_message, messagesContainer);
                                            messagesContainer.scrollTop(messagesContainer[0].scrollHeight); // Scroll al nuevo mensaje
                                        }
                                        $messageInput.val(''); 
                                    }
                                ).always(function() { $sendButton.prop('disabled', false); });
                            } else {
                                crm_js_log('[ENVIO] Validación fallida: Mensaje vacío y sin adjunto.', 'WARN');
                                showNotification('Mensaje Vacío', 'warning', 'Escribe un mensaje o adjunta un archivo.');
                            }
                        });

                        $messageInput.off('keydown.sendMessage').on('keydown.sendMessage', function(event) { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); $sendButton.click(); } });
                        
                        if ($emojiButton.length && $emojiContainer.length) {
                            $emojiButton.off('click.toggleEmoji').on('click.toggleEmoji', function(event) { /* ... lógica emoji ... */ $emojiContainer.slideToggle(150); });
                            $emojiContainer.off('click.selectEmoji').on('click.selectEmoji', '.emoji-option', function(event) { /* ... lógica seleccionar emoji ... */ });
                        } else {
                            crm_js_log('[loadConversationMessages - complete] Error: No se encontró #emoji-picker-button o #emoji-picker-container.', 'WARN');
                        }

                        if ($attachButton.length) {
                            let mediaFrameChat; // Usar un nombre diferente para el frame de media del chat
                            $attachButton.off('click.attachMediaChat').on('click.attachMediaChat', function(event) {
                                event.preventDefault();
                                if (mediaFrameChat) { mediaFrameChat.open(); return; }
                                mediaFrameChat = wp.media({ title: 'Seleccionar Multimedia para Chat', button: { text: 'Adjuntar' }, multiple: false });
                                mediaFrameChat.on('select', function() {
                                    const attachment = mediaFrameChat.state().get('selection').first().toJSON();
                                    currentAttachment = { url: attachment.url, filename: attachment.filename || attachment.url.split('/').pop(), mime: attachment.mime, id: attachment.id };
                                    renderAttachmentPreview(currentAttachment, $previewContainer);
                                    $messageInput.focus();
                                });
                                mediaFrameChat.open();
                            });
                        } else {
                            crm_js_log('[loadConversationMessages - complete] Error: No se encontró .btn-attach.', 'WARN');
                        }

                        if ($previewContainer.length) {
                            $previewContainer.off('click.removeAttachment').on('click.removeAttachment', '.remove-attachment', function(event) { event.preventDefault(); currentAttachment = null; $previewContainer.empty().hide(); });
                        }

                        crm_js_log('[loadConversationMessages - complete] Listeners del input adjuntados.');
                    } else {
                        crm_js_log('[loadConversationMessages - complete] Error: No se encontró #send-chat-message o #chat-message-input.', 'ERROR');
                    }
                }
            }
        });
    }

    /**
     * Carga los detalles de un contacto específico vía AJAX y los muestra en el sidebar.
     * @param {number} userId El ID del usuario WP cuyos detalles cargar.
     */
    function loadContactDetails(userId) {
        const $ = jQuery;
        const $detailsContainer = $('#contact-details-content');

        crm_js_log(`Solicitando detalles del contacto para User ID: ${userId}`);

        // Mostrar indicador de carga (ya lo hacemos en el click handler, pero podemos asegurarlo aquí)
        $detailsContainer.html('<p><span class="spinner is-active" style="float: none; vertical-align: middle;"></span> Cargando detalles...</p>');

        performAjaxRequest(
            'crm_get_contact_details', // Acción AJAX definida en PHP
            { user_id: userId },       // Datos a enviar
            function(details) { // onSuccess (recibe el objeto 'data' de la respuesta JSON)
                crm_js_log('Detalles del contacto recibidos:', 'DEBUG', details);

                // Construir el HTML con los detalles
                // Usar escapeHtml para seguridad
                const detailsHtml = `
                    <div class="contact-avatar-area" style="text-align: center; margin-bottom: 15px;">
                        <img src="${escapeHtml(details.avatar_url)}" alt="Avatar de ${escapeHtml(details.display_name)}" class="contact-details-avatar" style="width: 80px; height: 80px; border-radius: 50%; margin: 0 auto; display: block;">
                    </div>
                    <div class="form-field">
                        <label>Nombre:</label>
                        <span>${escapeHtml(details.display_name)}</span>
                    </div>
                    <div class="form-field">
                        <label>Nombres:</label>
                        <span>${escapeHtml(details.first_name || '')}</span>
                    </div>
                    <div class="form-field">
                        <label>Apellidos:</label>
                        <span>${escapeHtml(details.last_name || '')}</span>
                    </div>
                    <div class="form-field">
                        <label>Teléfono:</label>
                        <span>${escapeHtml(details.phone)}</span>
                        ${details.jid ? `<small style="display: block; color: #666;">(JID: ${escapeHtml(details.jid)})</small>` : ''}
                    </div>
                    <div class="form-field">
                        <label>Email:</label>
                        <span>${escapeHtml(details.email)}</span>
                    </div>
                    <div class="form-field">
                        <label>Rol:</label>
                        <span>${escapeHtml(details.role)}</span>
                    </div>
                    <div class="form-field">
                        <label>Registrado:</label>
                        <span>${escapeHtml(details.registration_date)}</span>
                    </div>
                    <div class="form-field">
                        <label>Etiqueta:</label>
                        <span class="tag-badge" data-tag-key="${escapeHtml(details.tag_key)}">${escapeHtml(details.tag_name)}</span>
                        <!-- TODO: Añadir botón/icono para editar etiqueta -->
                    </div>
                    <div class="form-field">
                        <label>Notas:</label>
                        <div class="contact-notes-content" style="background-color: #f9f9f9; border: 1px solid #eee; padding: 10px; min-height: 50px; border-radius: 3px;">
                            ${details.notes ? escapeHtml(details.notes).replace(/\n/g, '<br>') : '<i>Sin notas</i>'}
                        </div>
                    </div>
                    ${details.last_purchase && details.last_purchase.id ? `
                    <hr>
                    <div class="form-field">
                        <label style="font-weight: bold; margin-bottom: 8px; display: block; color: #333;">Última Compra:</label>
                        <div class="last-purchase-details" style="font-size: 0.9em; line-height: 1.6;">
                            <p style="margin: 0 0 5px 0;"><strong>ID Orden:</strong> 
                                ${details.last_purchase.url ? `<a href="${escapeHtml(details.last_purchase.url)}" target="_blank" title="Ver orden en WooCommerce">#${escapeHtml(details.last_purchase.id)}</a>` : `#${escapeHtml(details.last_purchase.id)}`}
                            </p>
                            <p style="margin: 0 0 5px 0;"><strong>Fecha:</strong> ${escapeHtml(details.last_purchase.date)}</p>
                            <p style="margin: 0 0 5px 0;"><strong>Total:</strong> ${details.last_purchase.total}</p>
                            <p style="margin: 0 0 5px 0;"><strong>Estado:</strong> <span class="order-status-badge" style="padding: 2px 6px; border-radius: 3px; background-color: #eee; color: #333;">${escapeHtml(details.last_purchase.status)}</span></p>
                        </div>
                    </div>
                    ` : `
                    <hr>
                    <div class="form-field">
                        <label style="font-weight: bold; margin-bottom: 8px; display: block; color: #333;">Última Compra:</label>
                        <p style="margin: 0; font-size: 0.9em; color: #666;"><em>No se encontraron compras recientes.</em></p>
                    </div>
                    `}
                    ${details.customer_history && details.customer_history.total_orders !== null ? ` <!-- Verifica si existe el historial -->
                    <hr>
                    <div class="customer-history-panel">
                        <h4 class="customer-history-title" style="cursor: pointer; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                            Historial de Cliente (WooCommerce)
                            <span class="toggle-arrow dashicons dashicons-arrow-down-alt2"></span>
                        </h4>
                        <div class="customer-history-content" style="font-size: 0.9em; line-height: 1.6; padding-left: 10px; border-left: 2px solid #eee; margin-left: 5px;">
                            <p style="margin: 0 0 5px 0;"><strong>Total Pedidos:</strong> ${escapeHtml(details.customer_history.total_orders)}</p>
                            <p style="margin: 0 0 5px 0;"><strong>Ingresos Totales:</strong> ${details.customer_history.total_revenue}</p> <!-- Ya viene formateado con wc_price -->
                            <p style="margin: 0 0 5px 0;"><strong>Valor Promedio Pedido:</strong> ${details.customer_history.average_order_value}</p> <!-- Ya viene formateado con wc_price -->
                        </div>
                    </div>
                    ` : ''
                    }

                    <hr>
                    <div style="text-align: right;">
                            <button id="edit-contact-button" class="button" data-user-id="${details.user_id}"><span class="dashicons dashicons-edit" style="vertical-align: middle; margin-top: -2px;"></span> Editar</button>
                    </div>
                `;

                // Insertar el HTML en el contenedor
                $detailsContainer.html(detailsHtml);

                // Añadir listener para el panel colapsable de Historial de Cliente
                // Usar delegación en $detailsContainer ya que el contenido se acaba de añadir
                $detailsContainer.off('click.toggleHistory').on('click.toggleHistory', '.customer-history-title', function() {
                    const $content = $(this).next('.customer-history-content');
                    const $arrow = $(this).find('.toggle-arrow');
                    $content.slideToggle(200);
                    if ($arrow.hasClass('dashicons-arrow-down-alt2')) {
                        $arrow.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
                    } else {
                        $arrow.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
                    }
                });

            },
            function(errorResponse) { // onError (manejado parcialmente por performAjaxRequest)
                crm_js_log('Error al cargar detalles del contacto.', 'ERROR', errorResponse);
                $detailsContainer.html('<p class="error-message">No se pudieron cargar los detalles del contacto. ' + escapeHtml(errorResponse?.data?.message || '') + '</p>');
            }
        );
    }
    
    /**
     * Renderiza la vista previa de un archivo adjunto.
     * @param {object|null} attachment Objeto con datos del adjunto (url, filename, mime) o null para limpiar.
     * @param {jQuery} $previewContainer Contenedor jQuery donde mostrar la preview.
     */
    function renderAttachmentPreview(attachment, $previewContainer) {
        let previewHtml = '';

        if (!attachment) {
            $previewContainer.empty().hide();
            return;
        }

        // Crear HTML basado en el tipo MIME
        if (attachment.mime.startsWith('image/')) {
            previewHtml = `
                <img src="${escapeHtml(attachment.url)}" alt="Vista previa">
                <span class="attachment-info">${escapeHtml(attachment.filename)}</span>
            `;
        } else if (attachment.mime.startsWith('video/')) {
            previewHtml = `
                <span class="dashicons dashicons-format-video"></span>
                <span class="attachment-info">${escapeHtml(attachment.filename)}</span>
            `;
        } else if (attachment.mime.startsWith('audio/')) {
             previewHtml = `
                <span class="dashicons dashicons-format-audio"></span>
                <span class="attachment-info">${escapeHtml(attachment.filename)}</span>
            `;
        } else { // Documento u otro
            previewHtml = `
                <span class="dashicons dashicons-media-default"></span>
                <span class="attachment-info">${escapeHtml(attachment.filename)}</span>
            `;
        }

        // Añadir botón de quitar
        previewHtml += `<button class="remove-attachment" title="Quitar adjunto">&times;</button>`;

        $previewContainer.html(previewHtml).show();
    }

    /**
     * Renderiza un único mensaje en el contenedor especificado.
     * @param {object} msg Objeto del mensaje (como el devuelto por AJAX).
     * @param {jQuery} container El contenedor jQuery donde añadir el mensaje.
     * @returns {number} El timestamp del mensaje renderizado.
     */
    function renderSingleMessage(msg, container) {
        const $ = jQuery;

        crm_js_log(`[renderSingleMessage] Intentando renderizar msg ID: ${msg.id}`, 'DEBUG'); // <-- Log 1: Inicio

        // --- INICIO: Comprobación de duplicados visuales ---
        const existingElementCount = container.find(`.chat-message[data-msg-id="${msg.id}"]`).length; // <-- Guardar resultado
        crm_js_log(`[renderSingleMessage] Comprobando duplicado para msg ID: ${msg.id}. Elementos existentes: ${existingElementCount}`, 'DEBUG'); // <-- Log 2: Resultado de la búsqueda
        if (existingElementCount > 0) {
            crm_js_log(`Mensaje ${msg.id} ya existe en la UI (renderSingleMessage), omitiendo.`);
            return msg.timestamp; // Devolver timestamp aunque se omita para no romper lógica del heartbeat
        }
        // --- FIN: Comprobación de duplicados visuales ---
        const messageDate = new Date(msg.timestamp * 1000);
        const timeString = messageDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const messageClass = msg.is_outgoing ? 'chat-message outgoing' : 'chat-message incoming';

        let messageContentHtml = '';
        // Reutilizar la lógica de renderizado de tipos de mensaje
        if (msg.type === 'image' && msg.attachment_url) {
            messageContentHtml = `<div class="message-media"><a href="${escapeHtml(msg.attachment_url)}" target="_blank" title="Ver imagen completa"><img src="${escapeHtml(msg.attachment_url)}" alt="Imagen adjunta" loading="lazy"></a></div>${msg.caption ? `<div class="message-caption">${escapeHtml(msg.caption)}</div>` : ''}`;
        } else if (msg.type === 'video' && msg.attachment_url) {
            messageContentHtml = `<div class="message-media"><video controls preload="metadata" src="${escapeHtml(msg.attachment_url)}"></video></div>${msg.caption ? `<div class="message-caption">${escapeHtml(msg.caption)}</div>` : ''}`;
        } else if (msg.type === 'audio' && msg.attachment_url) { // <-- Añadido para audio (también en renderSingleMessage)
            messageContentHtml = `<div class="message-media message-audio"><audio controls preload="metadata" src="${escapeHtml(msg.attachment_url)}"></audio></div>${msg.caption ? `<div class="message-caption">${escapeHtml(msg.caption)}</div>` : ''}`;
        } else if (msg.type === 'document' && msg.attachment_url) {
            messageContentHtml = `<div class="message-document"><a href="${escapeHtml(msg.attachment_url)}" target="_blank" download><span class="dashicons dashicons-media-default"></span> ${escapeHtml(msg.caption || msg.attachment_url.split('/').pop())}</a></div>`;
        } else {
            messageContentHtml = `<div class="message-text">${escapeHtml(msg.text || msg.caption || '')}</div>`;
        }

        const messageHtml = `
            <div class="${messageClass}" data-msg-id="${msg.id}">
                <div class="message-bubble">
                    ${messageContentHtml}
                    <div class="message-time">${timeString}</div>
                </div>
            </div>`;

        container.append(messageHtml);
        return msg.timestamp;
    }


    // =========================================================================
    // == API HEARTBEAT PARA ACTUALIZACIONES DE CHAT ==
    // =========================================================================

    let currentOpenChatUserId = null; // ID del usuario cuya conversación está abierta
    let lastDisplayedMessageTimestamp = 0; // Timestamp del último mensaje mostrado en el chat abierto

    let lastChatCheckTimestamp = Math.floor(Date.now() / 1000); // Timestamp inicial al cargar la página

    /**
     * Inicializa los listeners de la API Heartbeat para el chat.
     */
    function initChatHeartbeat() {
        const $ = jQuery;
        crm_js_log('Inicializando Heartbeat para actualizaciones de chat...');

        $(document).on('heartbeat-send.crmChat', function(event, data) {
            // Añadir nuestro timestamp al pulso saliente
            data['crm_last_chat_check'] = lastChatCheckTimestamp; // Para refrescar lista general
            if (currentOpenChatUserId) {
                data['crm_current_open_chat_id'] = currentOpenChatUserId;
                data['crm_last_message_timestamp'] = lastDisplayedMessageTimestamp; // Timestamp del último mensaje VISIBLE
            }
            crm_js_log('Heartbeat Send:', 'DEBUG', {
                lastCheck: lastChatCheckTimestamp,
                openChat: currentOpenChatUserId,
                lastMsgTs: lastDisplayedMessageTimestamp
            });
        });

        $(document).on('heartbeat-tick.crmChat', function(event, data) {
            crm_js_log('Heartbeat Tick: Respuesta recibida', 'DEBUG', data);

            let listNeedsRefresh = false;
            let openChatUpdated = false;

            // 1. Comprobar si hay mensajes nuevos para el chat ABIERTO
            if (data.hasOwnProperty('crm_new_messages_for_open_chat') && Array.isArray(data.crm_new_messages_for_open_chat) && data.crm_new_messages_for_open_chat.length > 0) {
                crm_js_log('Heartbeat Tick: Nuevos mensajes recibidos para el chat abierto.', 'INFO');
                const messagesContainer = $('#chat-messages-area'); // <-- CORREGIDO ID
                let latestTimestampInBatch = lastDisplayedMessageTimestamp;

                data.crm_new_messages_for_open_chat.forEach(function(msg) {
                    // Evitar duplicados (si el heartbeat fuera muy rápido)
                    if (messagesContainer.find(`[data-msg-id="${msg.id}"]`).length === 0) {
                        const renderedTimestamp = renderSingleMessage(msg, messagesContainer); // Usar función reutilizable
                        if (renderedTimestamp > latestTimestampInBatch) {
                            latestTimestampInBatch = renderedTimestamp;
                        }
                    }
                });
                lastDisplayedMessageTimestamp = latestTimestampInBatch; // Actualizar el timestamp del último mensaje
                messagesContainer.scrollTop(messagesContainer[0].scrollHeight); // Scroll al final
                openChatUpdated = true;
            }

            // 2. Comprobar si la lista general necesita refrescarse
            if (data.hasOwnProperty('crm_needs_list_refresh') && data.crm_needs_list_refresh === true) {
                listNeedsRefresh = true;
            }

            // 3. Actualizar timestamp de chequeo general y refrescar lista si es necesario
            if (listNeedsRefresh) {
                crm_js_log('Heartbeat Tick: Se necesita refrescar la lista de conversaciones.');
                lastChatCheckTimestamp = Math.floor(Date.now() / 1000);
                // Solo recargar la lista si el chat abierto NO fue actualizado (para evitar recarga innecesaria)
                // O si *siempre* quieres que la lista se reordene. Por ahora, evitamos recarga si ya actualizamos el chat abierto.
                if (!openChatUpdated) {
                    loadRecentConversations();
                } else {
                    // Opcional: Podríamos solo actualizar el snippet/hora del chat activo en la lista izquierda
                    // sin recargar toda la lista. Por ahora, no hacemos nada extra aquí.
                    crm_js_log('Heartbeat Tick: Lista no refrescada porque el chat abierto ya fue actualizado.', 'DEBUG');
                }
            }
        });
    }

})(jQuery); // Fin de la encapsulación
