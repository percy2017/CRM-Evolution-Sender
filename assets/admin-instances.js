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
                action: 'crm_get_instances_cards',
                _ajax_nonce: crmInstancesData.nonce
            },
            success: function(response) {
                // console.log('Respuesta AJAX recibida:', response);
                $loadingMessage.hide();
                if (response.success && response.data) {
                    // console.log('Instancias recibidas:', response.data);
                    // console.log('Llamando a renderInstanceCards...');
                    renderInstanceCards(response.data);
                    if (response.data.length === 0) {
                        $cardsContainer.append('<p class="no-instances-message">No se encontraron instancias.</p>');
                    }
                } else {
                    console.error('Error al obtener instancias:', response.data?.message || 'Respuesta no exitosa');
                    $cardsContainer.append('<p class="error-message">Error al cargar las instancias.</p>');
                    // console.log('La respuesta no fue exitosa o no contenía datos válidos.');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $loadingMessage.hide();
                console.error('Error en la llamada AJAX para obtener instancias.');
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
            return;
        }

        const cardTemplate = wp.template('instance-card');

        instances.forEach(instance => {
            const status = instance.status || 'unknown';
            const instanceName = instance.instance_name || 'N/A';
            const profilePicUrl = instance.profile_pic_url || '';
            const ownerNumber = instance.owner ? instance.owner.split('@')[0] : 'N/D';
            const settings = instance.settings || {}; // Obtener el objeto settings

            const templateData = {
                instanceName: instanceName,
                status: status,
                statusText: getStatusText(status),
                statusClass: getStatusClass(status),
                ownerNumber: ownerNumber,
                profilePicUrl: profilePicUrl || crmInstancesData.defaultAvatarUrl || '',
                settings: settings // Pasar el objeto settings completo a la plantilla
            };

            const cardHtmlString = cardTemplate(templateData);
            const $cardElement = $(cardHtmlString);

            // Almacenar todos los datos de la instancia en el elemento jQuery de la tarjeta
            $cardElement.data('instance-data', instance); 

            $cardsContainer.append($cardElement);
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
            case 'open': return 'success';
            case 'connecting': return 'info';
            case 'close': return 'danger';
            case 'qrcode': return 'warning';
            default: return 'secondary';
        }
    }

    let waitingInstanceForHeartbeat = null;

    $(document).ready(function() {
        loadInstances();
        initInstanceHeartbeat();

        $('body').on('submit', '#add-instance-form', function(e) {
            e.preventDefault();
            const $form = $(this);
            const $submitButton = $form.find('input[type="submit"]');
            const originalButtonText = $submitButton.val();

            $submitButton.val(crmInstancesData.i18n.creatingText || 'Creando...').prop('disabled', true);

            $.ajax({
                url: crmInstancesData.ajax_url,
                type: 'POST',
                data: $form.serialize() + '&action=crm_create_instance_cards',
                success: function(response) {
                    if (response.success) {
                        const createdInstanceName = $form.find('#instance_name_modal').val();
                        Swal.fire({
                            icon: 'success',
                            title: crmInstancesData.i18n.instance_added || 'Instancia añadida',
                            text: response.data.message || '',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        tb_remove();
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

        function openQrModalForInstance(instanceName) {
            if (!instanceName) return;

            $('#qr-code-container').html('');
            $('#qr-loading-spinner').show();
            $('#qr-modal-title').text(crmInstancesData.i18n.generating_qr || 'Generando QR...');
            $('#qr-code-modal').css('display', 'flex');
            waitingInstanceForHeartbeat = instanceName;

            $.ajax({
                url: crmInstancesData.ajax_url,
                type: 'POST',
                data: {
                    action: 'crm_get_instance_qr_cards',
                    _ajax_nonce: crmInstancesData.nonce,
                    instance_name: instanceName
                },
                success: function(response) {
                    $('#qr-loading-spinner').hide();
                    if (response.success && response.data.qrCode) {
                        $('#qr-modal-title').text('Escanea el código QR');
                        $('#qr-code-container').html('<img src="' + response.data.qrCode + '" alt="QR Code">');
                    } else {
                        $('#qr-modal-title').text('Información');
                        $('#qr-code-container').html('<p>' + (response.data?.message || crmInstancesData.i18n.error_generic) + '</p>');
                        loadInstances();
                    }
                },
                error: function() {
                    $('#qr-loading-spinner').hide();
                    $('#qr-modal-title').text('Error');
                    $('#qr-code-container').html('<p>' + crmInstancesData.i18n.error_generic + '</p>');
                    waitingInstanceForHeartbeat = null;
                }
            });
        }

        $cardsContainer.on('click', '.btn-get-qr', function() {
            const $card = $(this).closest('.instance-card');
            // Leer instanceName de los datos almacenados en la tarjeta
            const instanceData = $card.data('instance-data');
            if (instanceData && instanceData.instance_name) {
                openQrModalForInstance(instanceData.instance_name);
            }
        });

        $cardsContainer.on('click', '.btn-delete', function() {
            const $button = $(this);
            const $card = $button.closest('.instance-card');
            const instanceData = $card.data('instance-data');
            if (!instanceData || !instanceData.instance_name) return;
            const instanceName = instanceData.instance_name;

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
                    $button.prop('disabled', true).find('.dashicons').removeClass('dashicons-trash').addClass('dashicons-update spin');
                    $.ajax({
                        url: crmInstancesData.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'crm_delete_instance_cards',
                            _ajax_nonce: crmInstancesData.nonce,
                            instance_name: instanceName
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('¡Eliminada!', response.data.message || `La instancia ${instanceName} ha sido eliminada.`, 'success');
                                loadInstances();
                            } else {
                                Swal.fire('Error', response.data.message || crmInstancesData.i18n.error_generic, 'error');
                                $button.prop('disabled', false).find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-trash');
                            }
                        },
                        error: function(jqXHR) {
                            Swal.fire('Error de Conexión', jqXHR.responseJSON?.data?.message || crmInstancesData.i18n.error_generic, 'error');
                            $button.prop('disabled', false).find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-trash');
                        }
                    });
                }
            });
        });

        $cardsContainer.on('click', '.btn-sync-contacts', function() {
            const $button = $(this);
            const $card = $button.closest('.instance-card');
            const instanceData = $card.data('instance-data');
            if (!instanceData || !instanceData.instance_name) return;
            const instanceName = instanceData.instance_name;
            const $icon = $button.find('.dashicons');

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
                    $button.prop('disabled', true);
                    $icon.removeClass('dashicons-admin-users').addClass('dashicons-update spin');
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
                                Swal.fire('¡Iniciado!', response.data.message || `La sincronización de ${instanceName} ha comenzado.`, 'success');
                            } else {
                                Swal.fire('Error', response.data.message || crmInstancesData.i18n.error_generic, 'error');
                            }
                        },
                        error: function(jqXHR) {
                            Swal.fire('Error de Conexión', jqXHR.responseJSON?.data?.message || crmInstancesData.i18n.error_generic, 'error');
                        },
                        complete: function() {
                            $button.prop('disabled', false);
                            $icon.removeClass('dashicons-update spin').addClass('dashicons-admin-users');
                        }
                    });
                }
            });
        });

        // Delegar evento para el botón de Activar/Desactivar Ignorar Grupos
        $('#instances-cards-container').on('click', '.btn-toggle-groups', function() {
            const $button = $(this);
            const $card = $button.closest('.instance-card');
            const instanceData = $card.data('instance-data'); // Obtener los datos completos de la instancia

            if (!instanceData || !instanceData.instance_name) {
                console.error("Error: No se pudieron obtener los datos de la instancia para el botón de grupos.");
                return;
            }
            const instanceName = instanceData.instance_name;
            // Leer el estado actual desde los datos almacenados
            const currentGroupsIgnore = instanceData.settings && (instanceData.settings.groups_ignore === true || instanceData.settings.groups_ignore === 'true');
            const newGroupsIgnoreStatus = !currentGroupsIgnore;

            Swal.fire({
                title: `Confirmar cambio para "${instanceName}"`,
                text: `¿Deseas ${newGroupsIgnoreStatus ? 'ignorar' : 'procesar'} los mensajes de grupos para esta instancia?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, cambiar',
                cancelButtonText: 'Cancelar',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return $.ajax({
                        url: crmInstancesData.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'crm_update_instance_settings', // Acción PHP actualizada
                            _ajax_nonce: crmInstancesData.nonce,
                            instance_name: instanceName,
                            new_groups_ignore_status: newGroupsIgnoreStatus // Enviar el nuevo estado
                        }
                    }).fail(function(jqXHR, textStatus, errorThrown) {
                        Swal.showValidationMessage(`Error: ${jqXHR.responseJSON?.data?.message || errorThrown || 'Error desconocido'}`);
                    });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed && result.value.success) {
                    const newStatusFromResponse = result.value.data.new_status_groups_ignore; // Campo de respuesta actualizado
                    
                    // Actualizar los datos almacenados en la tarjeta para mantener la UI consistente
                    if (instanceData.settings) {
                        instanceData.settings.groups_ignore = newStatusFromResponse;
                    } else { // Si settings no existía, crearlo
                        instanceData.settings = { groups_ignore: newStatusFromResponse };
                    }
                    
                    const $icon = $button.find('.dashicons');
                    if (newStatusFromResponse) {
                        $icon.removeClass('dashicons-networking').addClass('dashicons-groups').css('color', 'red');
                    } else {
                        $icon.removeClass('dashicons-groups').addClass('dashicons-networking').css('color', 'green');
                    }
                    Swal.fire('¡Actualizado!', result.value.data.message || 'La configuración de grupos ha sido actualizada.', 'success');
                } else if (result.isConfirmed && !result.value.success) {
                    Swal.fire('Error', result.value.data?.message || 'No se pudo actualizar la configuración.', 'error');
                }
            });
        });
        
        $('#close-qr-modal').on('click', function() {
            $('#qr-code-modal').hide();
            waitingInstanceForHeartbeat = null;
            loadInstances();
        });

        function initInstanceHeartbeat() {
            // console.log('Inicializando Heartbeat para instancias...');
            $(document).on('heartbeat-send.instanceUpdates', function(event, data) {
                if (waitingInstanceForHeartbeat) {
                    data['crm_waiting_instance'] = waitingInstanceForHeartbeat;
                    // console.log('Heartbeat Send: Enviando crm_waiting_instance =', waitingInstanceForHeartbeat);
                }
            });

            $(document).on('heartbeat-tick.instanceUpdates', function(event, data) {
                // console.log('Heartbeat Tick: Respuesta recibida', data);
                if (data.hasOwnProperty('crm_instance_status_update')) {
                    const update = data.crm_instance_status_update;
                    // console.log('Heartbeat Tick: Recibida actualización de estado:', update);
                    if (update.instance === waitingInstanceForHeartbeat && update.status === 'open') {
                        // console.log(`Instancia ${update.instance} conectada! Cerrando modal QR y recargando.`);
                        $('#qr-code-modal').hide();
                        waitingInstanceForHeartbeat = null;
                        loadInstances();
                    }
                }

                if (data.hasOwnProperty('crm_instance_qr_update')) {
                    const update = data.crm_instance_qr_update;
                    // console.log('Heartbeat Tick: Recibida actualización de QR:', update);
                    if (update.instance === waitingInstanceForHeartbeat && $('#qr-code-modal').is(':visible')) {
                        // console.log(`Actualizando imagen QR para ${update.instance}.`);
                        $('#qr-code-container').html('<img src="' + update.qrCode + '" alt="QR Code Actualizado">');
                        $('#qr-modal-title').text('Escanea el nuevo código QR');
                    }
                }
            });
        }
    });

})(jQuery);
