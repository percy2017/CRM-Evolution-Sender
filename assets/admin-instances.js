/**
 * Lógica para la vista de tarjetas de instancias
 * CRM Evolution Sender
 */

(function($) {
    "use strict";

    // Contenedor donde se mostrarán las tarjetas
    const $cardsContainer = $('#instances-cards-container');
    const $loadingMessage = $cardsContainer.find('.loading-message');

    /**
     * Carga las instancias desde el servidor y las muestra como tarjetas.
     */
    function loadInstances() {
        if (!$cardsContainer.length) {
            console.error('Contenedor de tarjetas no encontrado.');
            return;
        }

        $loadingMessage.show(); // Mostrar mensaje de carga
        $cardsContainer.find('.instance-card').remove(); // Limpiar tarjetas anteriores (si las hubiera en recargas)
        $cardsContainer.find('.no-instances-message, .error-message').remove(); // Limpiar mensajes anteriores

        $.ajax({
            url: crmInstancesData.ajax_url, // URL de AJAX pasada desde PHP
            type: 'POST',
            data: {
                action: 'crm_get_instances_cards', // <-- CAMBIO: Nueva acción para obtener instancias
                _ajax_nonce: crmInstancesData.nonce // Nonce para seguridad
            },
            success: function(response) {
                console.log('Respuesta AJAX recibida:', response); // <-- LOG AÑADIDO
                $loadingMessage.hide(); // Ocultar mensaje de carga
                if (response.success && response.data) {
                    console.log('Instancias recibidas:', response.data); // Ver los datos en consola por ahora
                    // Llamar a la función para renderizar las tarjetas
                    console.log('Llamando a renderInstanceCards...'); // <-- LOG AÑADIDO
                    renderInstanceCards(response.data);
                    if (response.data.length === 0) {
                        $cardsContainer.append('<p class="no-instances-message">No se encontraron instancias.</p>');
                    }
                } else {
                    console.error('Error al obtener instancias:', response.data?.message || 'Respuesta no exitosa');
                    $cardsContainer.append('<p class="error-message">Error al cargar las instancias.</p>');
                    console.log('La respuesta no fue exitosa o no contenía datos válidos.'); // <-- LOG AÑADIDO
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $loadingMessage.hide();
                console.error('Error en la llamada AJAX para obtener instancias.'); // <-- LOG AÑADIDO
                console.error('Error AJAX:', textStatus, errorThrown);
                $cardsContainer.append('<p class="error-message">Error de conexión al cargar las instancias.</p>');
            }
        });
    }

    /**
     * Genera el HTML para cada tarjeta de instancia y lo añade al contenedor.
     * @param {Array} instances Array de objetos de instancia recibidos de la API.
     */
    function renderInstanceCards(instances) {
        if (!instances || instances.length === 0) {
            return; // No hacer nada si no hay instancias
        }

        // Plantilla HTML para una tarjeta de instancia usando wp.template
        // Asegúrate de que 'wp-util' esté encolado en PHP para que wp.template esté disponible.
        const cardTemplate = wp.template('instance-card');

        instances.forEach(instance => {
            // Preparar los datos para la plantilla
            // CORRECCIÓN: Acceder directamente a las propiedades del objeto 'instance'
            const status = instance.status || 'unknown';
            const instanceName = instance.instance_name || 'N/A'; // Corregido: instance_name
            const profilePicUrl = instance.profile_pic_url || ''; // Corregido: profile_pic_url
            const ownerNumber = instance.owner ? instance.owner.split('@')[0] : 'N/D'; // Extraer número del JID

            // Datos a pasar a la plantilla
            const templateData = {
                instanceName: instanceName,
                status: status,
                statusText: getStatusText(status), // Función auxiliar para texto legible
                statusClass: getStatusClass(status), // Función auxiliar para clase CSS
                ownerNumber: ownerNumber, // <-- Añadido el número
                profilePicUrl: profilePicUrl || crmInstancesData.defaultAvatarUrl || '', // Usar avatar o default (ya estaba bien, solo usa la variable corregida)
                // Añadir más datos si son necesarios para los botones
            };

            // Generar el HTML de la tarjeta usando la plantilla
            const cardHtml = cardTemplate(templateData);

            // Añadir la tarjeta al contenedor
            $cardsContainer.append(cardHtml);
        });
    }

    /**
     * Devuelve un texto legible para el estado de la instancia.
     * @param {string} status Estado recibido de la API.
     * @returns {string} Texto del estado.
     */
    function getStatusText(status) {
        switch (status) {
            case 'open': return 'Conectado';
            case 'connecting': return 'Conectando...';
            case 'close': return 'Desconectado';
            case 'qrcode': return 'Esperando QR';
            default: return 'Desconocido';
        }
    }

    /**
     * Devuelve una clase CSS simple basada en el estado de la instancia.
     * @param {string} status Estado recibido de la API.
     * @returns {string} Clase CSS.
     */
    function getStatusClass(status) {
        switch (status) {
            case 'open': return 'success'; // Verde
            case 'connecting': return 'info'; // Azul
            case 'close': return 'danger'; // Rojo
            case 'qrcode': return 'warning'; // Naranja
            default: return 'secondary'; // Gris
        }
    }

    // --- Variables para Heartbeat ---
    let waitingInstanceForHeartbeat = null; // Guarda el nombre de la instancia en el modal QR

    // --- Inicialización ---
    $(document).ready(function() {
        loadInstances(); // Cargar las instancias al iniciar la página
        initInstanceHeartbeat(); // Inicializar listeners de Heartbeat

        // --- Event Listener para Añadir Instancia (Formulario dentro de Thickbox) ---
        // Usamos 'body' como delegado porque el form está en un modal que puede no existir al inicio
        $('body').on('submit', '#add-instance-form', function(e) {
            e.preventDefault();
            const $form = $(this);
            const $submitButton = $form.find('input[type="submit"]');
            const originalButtonText = $submitButton.val();

            $submitButton.val(crmInstancesData.i18n.creatingText || 'Creando...').prop('disabled', true);

            $.ajax({
                url: crmInstancesData.ajax_url,
                type: 'POST',
                data: $form.serialize() + '&action=crm_create_instance_cards', // <-- Acción AJAX correcta
                success: function(response) {
                    if (response.success) {
                        const createdInstanceName = $form.find('#instance_name_modal').val(); // Obtener el nombre recién creado
                        Swal.fire({
                            icon: 'success',
                            title: crmInstancesData.i18n.instance_added || 'Instancia añadida',
                            text: response.data.message || '',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        tb_remove(); // Cerrar Thickbox
                        // loadInstances(); // No recargar inmediatamente

                        // Abrir modal QR para la nueva instancia
                        // Llamamos a la nueva función auxiliar
                        openQrModalForInstance(createdInstanceName);

                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.data.message || crmInstancesData.i18n.error_generic || 'Ocurrió un error.'
                        });
                    }
                },
                error: function(jqXHR) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de Conexión',
                        text: jqXHR.responseJSON?.data?.message || crmInstancesData.i18n.error_generic || 'Error al conectar con el servidor.'
                    });
                },
                complete: function() {
                    $submitButton.val(originalButtonText).prop('disabled', false);
                }
            });
        });

        /**
         * Abre el modal QR para una instancia específica.
         * @param {string} instanceName Nombre de la instancia.
         */
        function openQrModalForInstance(instanceName) {
            if (!instanceName) return;

            // Mostrar spinner en modal QR y abrirlo
            $('#qr-code-container').html(''); // Limpiar QR anterior
            $('#qr-loading-spinner').show();
            $('#qr-modal-title').text(crmInstancesData.i18n.generating_qr || 'Generando QR...');
            $('#qr-code-modal').css('display', 'flex'); // Mostrar modal
            waitingInstanceForHeartbeat = instanceName; // Indicar qué instancia estamos esperando

            $.ajax({
                url: crmInstancesData.ajax_url,
                type: 'POST',
                data: {
                    action: 'crm_get_instance_qr_cards', // <-- CAMBIO: Nueva acción para obtener QR
                    _ajax_nonce: crmInstancesData.nonce,
                    instance_name: instanceName
                },
                success: function(response) {
                    $('#qr-loading-spinner').hide();
                    if (response.success && response.data.qrCode) {
                        $('#qr-modal-title').text('Escanea el código QR');
                        $('#qr-code-container').html('<img src="' + response.data.qrCode + '" alt="QR Code">');
                        // No borramos waitingInstanceForHeartbeat aquí, esperamos conexión o cierre
                    } else {
                        $('#qr-modal-title').text('Información');
                        $('#qr-code-container').html('<p>' + (response.data?.message || crmInstancesData.i18n.error_generic) + '</p>');
                        loadInstances(); // Recargar si no hay QR (ya conectada o error)
                    }
                },
                error: function() {
                    $('#qr-loading-spinner').hide();
                    $('#qr-modal-title').text('Error');
                    $('#qr-code-container').html('<p>' + crmInstancesData.i18n.error_generic + '</p>');
                    waitingInstanceForHeartbeat = null; // Dejar de esperar si hay error inicial
                }
            });
        }

        // --- Event Listeners para botones dentro de las tarjetas (Delegación) ---
        $cardsContainer.on('click', '.btn-get-qr', function() {
            const $button = $(this);
            const $card = $button.closest('.instance-card');
            const instanceName = $card.data('instance-name');

            if (!instanceName) return;
            // Reutilizar la función auxiliar
            openQrModalForInstance(instanceName);
        });


        $cardsContainer.on('click', '.btn-delete', function() {
            // Lógica para eliminar (usar SweetAlert para confirmar)
            const $button = $(this);
            const $card = $button.closest('.instance-card');
            const instanceName = $card.data('instance-name');

            if (!instanceName) return;

            Swal.fire({
                title: '¿Estás seguro?',
                text: `Estás a punto de eliminar la instancia "${instanceName}". ¡Esta acción desconectará y eliminará la instancia permanentemente!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, ¡Eliminar!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    console.log(`Confirmada eliminación de: ${instanceName}`);
                    // Mostrar indicador de carga en el botón o tarjeta (opcional)
                    $button.prop('disabled', true).find('.dashicons').removeClass('dashicons-trash').addClass('dashicons-update spin');

                    // Llamar a la nueva acción AJAX que crearemos en PHP
                    $.ajax({
                        url: crmInstancesData.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'crm_delete_instance_cards', // <-- NUEVA ACCIÓN
                            _ajax_nonce: crmInstancesData.nonce, // Usar nonce general o crear uno específico
                            instance_name: instanceName
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire(
                                    '¡Eliminada!',
                                    response.data.message || `La instancia ${instanceName} ha sido eliminada.`,
                                    'success'
                                );
                                loadInstances(); // Recargar la lista
                            } else {
                                Swal.fire(
                                    'Error',
                                    response.data.message || crmInstancesData.i18n.error_generic,
                                    'error'
                                );
                                // Restaurar botón en caso de error
                                $button.prop('disabled', false).find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-trash');
                            }
                        },
                        error: function(jqXHR) {
                            Swal.fire(
                                'Error de Conexión',
                                jqXHR.responseJSON?.data?.message || crmInstancesData.i18n.error_generic,
                                'error'
                            );
                            // Restaurar botón en caso de error
                            $button.prop('disabled', false).find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-trash');
                        }
                        // No necesitamos 'complete' aquí si restauramos en success/error
                    });
                } else {
                    console.log(`Eliminación de ${instanceName} cancelada.`);
                }
            });
        });

        $cardsContainer.on('click', '.btn-sync-contacts', function() {
            const $button = $(this);
            const $card = $button.closest('.instance-card');
            const instanceName = $card.data('instance-name');
            const $icon = $button.find('.dashicons');

            if (!instanceName) return;

            console.log(`Solicitando confirmación para sincronizar contactos de: ${instanceName}`);

            // *** INICIO: Añadir SweetAlert de confirmación ***
            Swal.fire({
                title: '¿Iniciar Sincronización?',
                text: `Se buscarán contactos en la instancia "${instanceName}" y se crearán o actualizarán en WordPress. Esto puede tardar si hay muchos contactos nuevos (los avatares se procesarán en segundo plano).`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#aaa',
                confirmButtonText: 'Sí, ¡Sincronizar!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    console.log(`Confirmada sincronización de contactos para: ${instanceName}`);

                    // Mostrar indicador de carga
                    $button.prop('disabled', true);
                    $icon.removeClass('dashicons-admin-users').addClass('dashicons-update spin');

                    // Llamar a la acción AJAX
                    $.ajax({
                        url: crmInstancesData.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'crm_sync_instance_contacts',
                            _ajax_nonce: crmInstancesData.nonce,
                            instance_name: instanceName
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire(
                                    '¡Iniciado!', // Cambiado de 'Sincronizado' a 'Iniciado'
                                    response.data.message || `La sincronización de ${instanceName} ha comenzado.`,
                                    'success'
                                );
                            } else {
                                Swal.fire('Error', response.data.message || crmInstancesData.i18n.error_generic, 'error');
                            }
                        },
                        error: function(jqXHR) {
                            Swal.fire('Error de Conexión', jqXHR.responseJSON?.data?.message || crmInstancesData.i18n.error_generic, 'error');
                        },
                        complete: function() {
                            // Restaurar botón
                            $button.prop('disabled', false);
                            $icon.removeClass('dashicons-update spin').addClass('dashicons-admin-users');
                        }
                    });
                } else {
                    console.log(`Sincronización de ${instanceName} cancelada por el usuario.`);
                    // No es necesario restaurar el botón aquí porque no se deshabilitó
                }
            });
            // *** FIN: Añadir SweetAlert de confirmación ***
        });
        
        // --- Event Listener para cerrar el modal QR ---
        $('#close-qr-modal').on('click', function() {
            $('#qr-code-modal').hide();
            waitingInstanceForHeartbeat = null; // Dejar de esperar al cerrar manualmente
            loadInstances(); // Recargar instancias por si el estado cambió mientras el modal estaba abierto
        });

        /**
         * Inicializa los listeners de la API Heartbeat para la página de instancias.
         */
        function initInstanceHeartbeat() {
            console.log('Inicializando Heartbeat para instancias...');

            $(document).on('heartbeat-send.instanceUpdates', function(event, data) {
                // Si estamos esperando una instancia en el modal QR, añadirla al pulso
                if (waitingInstanceForHeartbeat) {
                    data['crm_waiting_instance'] = waitingInstanceForHeartbeat;
                    console.log('Heartbeat Send: Enviando crm_waiting_instance =', waitingInstanceForHeartbeat);
                }
            });

            $(document).on('heartbeat-tick.instanceUpdates', function(event, data) {
                console.log('Heartbeat Tick: Respuesta recibida', data);

                // 1. Comprobar actualización de estado
                if (data.hasOwnProperty('crm_instance_status_update')) {
                    const update = data.crm_instance_status_update;
                    console.log('Heartbeat Tick: Recibida actualización de estado:', update);
                    // Si la actualización es para la instancia que estamos esperando y el estado es 'open'
                    if (update.instance === waitingInstanceForHeartbeat && update.status === 'open') {
                        console.log(`Instancia ${update.instance} conectada! Cerrando modal QR y recargando.`);
                        $('#qr-code-modal').hide();
                        waitingInstanceForHeartbeat = null; // Dejar de esperar
                        loadInstances(); // Recargar la lista para mostrar el estado 'Conectado'
                    }
                    // Podríamos manejar otros estados si fuera necesario (ej: 'close')
                }

                // 2. Comprobar actualización de QR
                if (data.hasOwnProperty('crm_instance_qr_update')) {
                    const update = data.crm_instance_qr_update;
                    console.log('Heartbeat Tick: Recibida actualización de QR:', update);
                    // Si la actualización es para la instancia que estamos esperando en el modal
                    if (update.instance === waitingInstanceForHeartbeat && $('#qr-code-modal').is(':visible')) {
                        console.log(`Actualizando imagen QR para ${update.instance}.`);
                        $('#qr-code-container').html('<img src="' + update.qrCode + '" alt="QR Code Actualizado">');
                        $('#qr-modal-title').text('Escanea el nuevo código QR'); // Actualizar título por si acaso
                    }
                }
            });
        }
    });

})(jQuery);
