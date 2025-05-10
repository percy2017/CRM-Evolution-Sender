// Encapsular en una función anónima para evitar conflictos de variables globales
(function($) {
    'use strict';


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
        // crm_js_log(`Notificación mostrada: [${icon}] ${title} ${text}`);
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

    /**
     * Realiza una llamada AJAX genérica al backend de WordPress.
     * @param {string} action La acción de WordPress AJAX a ejecutar.
     * @param {object} data Datos adicionales a enviar.
     * @param {function} onSuccess Callback en caso de éxito (recibe la respuesta).
     * @param {function} onError Callback en caso de error (opcional).
     */
    function performAjaxRequest(action, data, onSuccess, onError) {
        // crm_js_log(`Iniciando AJAX action: ${action}`, 'DEBUG', data);

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
                // crm_js_log(`Respuesta AJAX para ${action}:`, 'DEBUG', response);
                if (response && response.success) {
                    if (onSuccess && typeof onSuccess === 'function') {
                        onSuccess(response.data); // Pasar solo los datos al callback
                    }
                } else {
                    const errorMessage = response && response.data && response.data.message ? response.data.message : 'Ocurrió un error desconocido.';
                    // crm_js_log(`Error en la respuesta AJAX para ${action}: ${errorMessage}`, 'ERROR', response);
                    showNotification('Error', 'error', errorMessage);
                    if (onError && typeof onError === 'function') {
                        onError(response); // Pasar la respuesta completa al callback de error
                    }
                }
            },
            error: function(xhr, status, error) {
                 Swal.close(); // Ocultar loading
                const errorMsg = `Error de conexión o del servidor (${status}): ${error}`;
                // crm_js_log(`Error AJAX en ${action}: ${errorMsg}`, 'ERROR', { status: status, error: error, response: xhr.responseText });
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


    /**
     * Cambia la vista de detalles del contacto a modo edición.
    */
    function switchToEditMode(userId) {
        const $ = jQuery;
        const $detailsContainer = $('#contact-details-content');
        // crm_js_log(`Cambiando a modo edición para User ID: ${userId}`);

        // Guardar datos actuales antes de reemplazar
        const currentData = {
            name: $detailsContainer.find('label:contains("Nombre:")').next('span').text(),
            firstName: $detailsContainer.find('label:contains("Nombres:")').next('span').text(), // Nuevo
            lastName: $detailsContainer.find('label:contains("Apellidos:")').next('span').text(),   // Nuevo
            phone: $detailsContainer.find('label:contains("Teléfono:")').next('span').text(),
            email: $detailsContainer.find('label:contains("Email:")').next('span').text(),
            roleKey: $detailsContainer.find('label:contains("Rol:")').next('span').data('role-key'), // Leer la clave del rol
            tagKey: $detailsContainer.find('label:contains("Etiqueta:")').next('span.tag-badge').data('tag-key'),
            notes: $detailsContainer.find('.contact-notes-content').html().replace(/<br\s*\/?>/gi, '\n').replace(/<i>Sin notas<\/i>/i, '').trim() // Convertir <br> a \n y quitar placeholder
            // Fecha de Registro no es editable aquí
            // Historial de WooCommerce no es editable
        };
        // crm_js_log('Datos actuales para edición:', 'DEBUG', currentData);

        // Reemplazar spans con inputs/textarea/select
        $detailsContainer.find('label:contains("Nombre:")').next('span').replaceWith(`<input type="text" id="edit-contact-name" class="regular-text" value="${escapeHtml(currentData.name)}">`);
        $detailsContainer.find('label:contains("Teléfono:")').nextAll().remove(); // Quitar span y small(JID)
        $detailsContainer.find('label:contains("Teléfono:")').after(`<input type="text" id="edit-contact-phone" class="regular-text" value="${escapeHtml(currentData.phone)}" readonly title="El teléfono se sincroniza desde WhatsApp, no se puede editar aquí.">`); // Teléfono no editable
        $detailsContainer.find('label:contains("Nombres:")').next('span').replaceWith(`<input type="text" id="edit-contact-first-name" class="regular-text" value="${escapeHtml(currentData.firstName)}">`);
        $detailsContainer.find('label:contains("Apellidos:")').next('span').replaceWith(`<input type="text" id="edit-contact-last-name" class="regular-text" value="${escapeHtml(currentData.lastName)}">`);
        $detailsContainer.find('label:contains("Email:")').next('span').replaceWith(`<input type="email" id="edit-contact-email" class="regular-text" value="${escapeHtml(currentData.email)}">`);

        // Reemplazar span de Rol con un select
        const $roleSpan = $detailsContainer.find('label:contains("Rol:")').next('span');
        const $roleSelect = $('<select id="edit-contact-role" class="regular-text"></select>');
        $roleSpan.replaceWith($roleSelect);
        loadWordPressRolesIntoSelect($roleSelect, currentData.roleKey); // Nueva función auxiliar

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
     * Carga los roles de WordPress disponibles en un elemento select.
     * @param {jQuery} $selectElement El elemento select jQuery.
     * @param {string} currentRoleKey La clave del rol actualmente seleccionado para el usuario.
     */
    function loadWordPressRolesIntoSelect($selectElement, currentRoleKey) {
        $selectElement.html('<option value="">Cargando roles...</option>').prop('disabled', true); // Placeholder y deshabilitar
        performAjaxRequest(
            'crm_get_wordpress_roles', // Acción AJAX definida en PHP
            {}, // Sin datos adicionales necesarios
            function(roles) { // onSuccess
                $selectElement.empty(); // Limpiar placeholder
                if (roles && roles.length > 0) {
                    roles.forEach(role => {
                        const $option = $('<option>', { value: role.key, text: role.name });
                        if (role.key === currentRoleKey) {
                            $option.prop('selected', true);
                        }
                        $selectElement.append($option);
                    });
                } else {
                    $selectElement.append('<option value="">No hay roles disponibles</option>');
                }
                $selectElement.prop('disabled', false); // Habilitar select
            },
            function() { // onError (además del manejador global en performAjaxRequest)
                $selectElement.html('<option value="">Error al cargar roles</option>').prop('disabled', true);
            }
        );
    }

    /**
     * Guarda los detalles modificados del contacto vía AJAX.
     */
    function saveContactDetails(userId) {
        const $ = jQuery;
        const $detailsContainer = $('#contact-details-content');
        // crm_js_log(`Intentando guardar detalles para User ID: ${userId}`);

        // Recoger datos del formulario de edición
        const updatedData = {
            user_id: userId,
            name: $('#edit-contact-name').val(),
            first_name: $('#edit-contact-first-name').val(), // Nuevo
            last_name: $('#edit-contact-last-name').val(),   // Nuevo
            email: $('#edit-contact-email').val(),
            role_key: $('#edit-contact-role').val(), // Nuevo: enviar la clave del rol
            tag_key: $('#edit-contact-tag').val(),
            notes: $('#edit-contact-notes').val()
        };
        // crm_js_log('Datos a enviar para guardar:', 'DEBUG', updatedData);

        // Deshabilitar botones mientras guarda
        $('#save-contact-button, #cancel-edit-button').prop('disabled', true);

        performAjaxRequest(
            'crm_save_contact_details', // Nueva acción AJAX
            updatedData,
            function(response) { // onSuccess
                // crm_js_log('Detalles guardados correctamente. Respuesta:', 'INFO', response);
                showNotification('Contacto actualizado', 'success');
                // Volver a cargar los detalles para mostrar la vista normal actualizada
                loadContactDetails(userId);
            }
            // onError es manejado por performAjaxRequest y muestra notificación
        ).always(function() { $('#save-contact-button, #cancel-edit-button').prop('disabled', false); }); // Reactivar botones
    }

    // --- Ejecución Principal (DOM Ready) ---
    $(document).ready(function() {
        // crm_js_log('DOM listo. Iniciando script del plugin.');

        // --- INICIO: Lógica para Historial de Chats ---
        // Solo ejecutar en la página de historial de chats
        if ($('#crm-chat-container').length) {
            // 1. Asegurar estado inicial oculto del sidebar
            $('#contact-details-column').removeClass('is-visible'); // Quitar clase al inicio por si acaso

            // 2. Log y cargar conversaciones iniciales
            // crm_js_log('Página de Historial de Chats detectada. Cargando conversaciones...');
            loadRecentConversations();

            // 3. Inicializar Heartbeat
            // crm_js_log('Inicializando Heartbeat para chat...');
            initChatHeartbeat();

            // 4. Inicializar Buscador y botón cerrar sidebar
            // crm_js_log('Inicializando Buscador de Chats y handler de cierre de sidebar...');
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

            
            // --- INICIO: Listener para maximizar imágenes del chat ---
            $('#chat-messages-area').on('click', '.message-media img', function(event) {
                event.preventDefault(); // Prevenir comportamiento por defecto si la imagen está en un enlace
                const imageUrl = $(this).attr('src');
                const imageAlt = $(this).attr('alt') || 'Imagen del chat';
        
                if (imageUrl) {
                    // crm_js_log(`Maximizando imagen: ${imageUrl}`, 'DEBUG');
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
            // crm_js_log('Listener para maximizar imágenes del chat inicializado.', 'INFO');
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
                    // crm_js_log('No se pudo determinar el User ID para mostrar el avatar ampliado (currentOpenChatUserId nulo).', 'ERROR');
                    showNotification('Error', 'error', 'No se pudo identificar al usuario.');
                    return;
                }
                currentEditingAvatarUserId = currentOpenChatUserId;

                $('#expanded-avatar-image').attr('src', avatarUrl);
                $('#contact-avatar-expanded-view').fadeIn(200);
                // crm_js_log(`Mostrando vista ampliada de avatar para User ID: ${currentEditingAvatarUserId}`);
            });

            // 2. Listener para cerrar la vista ampliada del avatar
            // Usar delegación en document por si el botón está dentro de un div que se muestra/oculta
            $(document).on('click', '#close-avatar-expanded-view', function() {
                $('#contact-avatar-expanded-view').fadeOut(200);
                currentEditingAvatarUserId = null; // Limpiar el ID del usuario
                // crm_js_log('Vista ampliada de avatar cerrada.');
            });

            // 3. Listener para el botón "Actualizar foto" en la vista ampliada
            $(document).on('click', '#trigger-update-avatar-button', function() {
                if (!currentEditingAvatarUserId) {
                    // crm_js_log('Error: No hay User ID (currentEditingAvatarUserId) para actualizar avatar.', 'ERROR');
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
                    // crm_js_log('Nueva imagen de avatar seleccionada:', 'DEBUG', attachment);

                    if (!attachment.id || !attachment.url) {
                        // crm_js_log('Error: El adjunto seleccionado no tiene ID o URL.', 'ERROR');
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
                            // crm_js_log('Avatar actualizado con éxito. Respuesta:', 'INFO', response);
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
            // crm_js_log('Listeners para la vista ampliada y actualización de avatar inicializados.');
            // --- FIN: Lógica para Vista Ampliada y Actualización de Avatar ---

            // --- INICIO: Cerrar sidebar de detalles al enfocar input de mensaje (MODIFICADO: YA NO CIERRA) ---
            // $('#chat-message-input').on('focus', function() {
            //     // crm_js_log('Input de mensaje ha obtenido el foco. Intentando cerrar sidebar de detalles.');
            //     closeContactDetailsSidebar(); 
            // });
            // crm_js_log('Listener para cerrar sidebar al enfocar input de mensaje YA NO ESTÁ ACTIVO.');
            // --- FIN: Cerrar sidebar de detalles al enfocar input de mensaje ---

            // --- INICIO: Listener para la nueva cabecera del chat activo ---
            $('#chat-view-column').on('click', '#active-chat-header', function() {
                // crm_js_log('Clic en #active-chat-header detectado.');
                if (currentOpenChatUserId) {
                    // crm_js_log(`Mostrando detalles para User ID: ${currentOpenChatUserId} desde clic en cabecera.`);
                    $('#contact-details-column').css('display', 'flex'); // Mostrar el sidebar
                    loadContactDetails(currentOpenChatUserId); // Cargar los detalles del contacto activo
                } else {
                    // crm_js_log('Clic en #active-chat-header pero no hay currentOpenChatUserId.', 'WARN');
                }
            });
            // crm_js_log('Listener para #active-chat-header inicializado.');
            // --- FIN: Listener para la nueva cabecera del chat activo ---

            // --- INICIO: Listener para el botón "Nuevo Chat" (#add-new-chat-button) ---
            $(document).on('click', '#add-new-chat-button', function() {
                // crm_js_log('Botón #add-new-chat-button clickeado.');
                Swal.fire({
                    title: 'Iniciar Nuevo Chat',
                    text: 'Ingresa el número de teléfono (con código de país, ej: 59171234567):',
                    input: 'text',
                    inputPlaceholder: 'Ej: 59171234567',
                    showCancelButton: true,
                    confirmButtonText: 'Buscar Contacto',
                    cancelButtonText: 'Cancelar',
                    inputValidator: (value) => {
                        if (!value || value.trim() === '') {
                            return 'Por favor, ingresa un número de teléfono.';
                        }
                        // Validación simple: permite un + opcional al inicio y luego entre 7 y 15 dígitos.
                        if (!/^\+?\d{7,15}$/.test(value.replace(/\s+/g, ''))) {
                            return 'Ingresa un número de teléfono válido (ej: +59171234567 o 59171234567).';
                        }
                        return null;
                    },
                    showLoaderOnConfirm: true,
                    preConfirm: (phoneNumber) => {
                        return new Promise((resolve, reject) => {
                            performAjaxRequest(
                                'crm_fetch_whatsapp_profile_ajax',
                                { phone_number: phoneNumber.trim().replace(/\s+/g, '') }, // Limpiar espacios y enviar
                                function(responseData) { // onSuccess, recibe response.data de performAjaxRequest
                                    resolve(responseData); // Resuelve la promesa con los datos del perfil/usuario
                                },
                                function(errorResponse) { // onError, recibe la respuesta completa del servidor si success:false
                                    Swal.showValidationMessage(`${errorResponse.data.message || 'Error al buscar perfil.'}`);
                                    reject(errorResponse.data.message); // Rechaza la promesa para que Swal maneje el error
                                }
                            ).catch(networkError => { // Captura errores de red de la promesa de performAjaxRequest
                                Swal.showValidationMessage(`Error de red: ${networkError.data ? networkError.data.message : 'No se pudo conectar.'}`);
                                reject(networkError);
                            });
                        });
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then((result) => {
                    if (result.isConfirmed && result.value) {
                        const userData = result.value; // Esto es response.data de performAjaxRequest, que ahora contiene user_id, display_name, etc.
                        // crm_js_log('Respuesta de búsqueda de perfil:', 'INFO', responseData);

                        if (userData && userData.user_id && userData.jid) {
                            showNotification(`Contacto "${userData.display_name}" procesado.`, 'success', 'Iniciando chat...');
                            // crm_js_log('Contacto procesado, abriendo chat:', 'INFO', userData);
                            
                            loadRecentConversations(); // Refrescar la lista de chats en segundo plano
                            openChatView(userData.user_id, userData.jid, userData.display_name, userData.avatar_url);

                        } else {
                            // Esto no debería ocurrir si el backend devuelve error correctamente, pero por si acaso.
                            showNotification(userData.message || 'Respuesta inesperada del servidor al procesar contacto.', 'warning');
                        }
                    } else if (result.dismiss === Swal.DismissReason.cancel) {
                        // crm_js_log('Búsqueda de nuevo contacto cancelada por el usuario.');
                    }
                });
            });
            // --- FIN: Listener para el botón "Nuevo Chat" ---

            // --- INICIO: Lógica para el Menú de Filtro de Instancias (Solo Listar) ---
            const $instanceFilterButton = $('#instance-filter-button');
            const $instanceFilterDropdown = $('#instance-filter-dropdown');
            let instancesLoadedForMenu = false; // Flag para cargar instancias solo una vez

            if ($instanceFilterButton.length && $instanceFilterDropdown.length) {
                $instanceFilterButton.on('click', function(event) {
                    event.stopPropagation(); // Evitar que el click se propague al document
                    // console.log('Botón de filtro de instancia clickeado.');
                    if (!instancesLoadedForMenu) {
                        loadEvolutionInstancesIntoMenu();
                    }
                    $instanceFilterDropdown.toggle();
                });

                // Cargar instancias en el dropdown
                function loadEvolutionInstancesIntoMenu() {
                    // console.log('Cargando instancias en el menú...');
                    $instanceFilterDropdown.html('<div class="dropdown-item-loading" style="padding: 8px 15px;">Cargando instancias...</div>'); // Placeholder

                    performAjaxRequest(
                        'crm_get_all_evolution_instances_ajax', // Acción AJAX existente
                        {},
                        function(instances) { // onSuccess
                            // console.log('Instancias recibidas para menú:', instances);
                            $instanceFilterDropdown.empty(); // Limpiar placeholder o items anteriores
                            
                            // Añadir opción "Todas las instancias" (aunque no filtrará aún)
                            $instanceFilterDropdown.append('<a href="#" class="instance-filter-item active" data-instance="all" style="display: block; padding: 8px 15px; text-decoration: none; color: #333;">Todas las instancias</a>');

                            if (instances && instances.length > 0) {
                                instances.forEach(instanceData => {
                                    // La respuesta de /instance/fetchInstances es un array de objetos,
                                    // cada objeto tiene una clave 'instance' con los detalles.
                                    const instanceName = instanceData.instance?.instanceName;
                                    const instanceStatus = instanceData.instance?.status;

                                    if (instanceName) {
                                        let statusIndicator = '';
                                        if (instanceStatus === 'open') {
                                            statusIndicator = '<span class="status-dot open" title="Conectada" style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: green; margin-right: 5px;"></span>';
                                        } else if (instanceStatus === 'close' || instanceStatus === 'closed') {
                                            statusIndicator = '<span class="status-dot close" title="Desconectada" style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: red; margin-right: 5px;"></span>';
                                        } else {
                                            statusIndicator = '<span class="status-dot unknown" title="Estado desconocido" style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: grey; margin-right: 5px;"></span>';
                                        }
                                        $instanceFilterDropdown.append(`<a href="#" class="instance-filter-item" data-instance="${escapeHtml(instanceName)}" style="display: block; padding: 8px 15px; text-decoration: none; color: #333;">${statusIndicator} ${escapeHtml(instanceName)}</a>`);
                                    }
                                });
                                instancesLoadedForMenu = true;
                            } else {
                                $instanceFilterDropdown.append('<div class="dropdown-item-info" style="padding: 8px 15px; color: #666;">No hay instancias configuradas.</div>');
                            }
                        },
                        function(errorResponse) { // onError
                            console.error('[APP.JS] Error al cargar instancias para el menú:', errorResponse);
                            $instanceFilterDropdown.html('<div class="dropdown-item-error" style="padding: 8px 15px; color: red;">Error al cargar instancias.</div>');
                        }
                    );
                }

                // Cerrar dropdown si se hace clic fuera
                $(document).on('click', function(event) {
                    if (!$instanceFilterButton.is(event.target) && $instanceFilterButton.has(event.target).length === 0 &&
                        !$instanceFilterDropdown.is(event.target) && $instanceFilterDropdown.has(event.target).length === 0) {
                        $instanceFilterDropdown.hide();
                    }
                });
            }
            // --- FIN: Lógica para el Menú de Filtro de Instancias (Solo Listar) ---
        } else {
            // crm_js_log('Contenedor #crm-chat-container NO encontrado. Lógica de chat no se ejecutará.', 'WARN');
        }
        /**
         * Cierra (oculta) el sidebar de detalles del contacto.
         */
        function closeContactDetailsSidebar() {
            const $ = jQuery;
            const $contactDetailsColumn = $('#contact-details-column');

            if ($contactDetailsColumn.css('display') !== 'none') { // Solo actuar si está visible
                // crm_js_log('Cerrando sidebar de detalles del contacto.');
                $contactDetailsColumn.css('display', 'none');

                // --- MODIFICACIÓN: No desmarcar chat activo ni resetear currentOpenChatUserId al cerrar sidebar ---
                // $('#chat-list-items .chat-list-item.active').removeClass('active');
                // currentOpenChatUserId = null; // Indicar que no hay chat abierto (si usas esta variable) <-- MODIFICACIÓN
                // --- FIN MODIFICACIÓN ---
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
                // crm_js_log('Input de búsqueda #chat-search-input o contenedor #chat-list-items no encontrado.', 'ERROR');
                return;
            }
            $searchInput.on('input', function() {
                // crm_js_log('[BUSCADOR] Evento input detectado.'); // <-- LOG 1

                clearTimeout(searchDebounceTimer); // Cancelar timer anterior

                searchDebounceTimer = setTimeout(() => {
                    const searchTerm = $(this).val().trim().toLowerCase();
                    // crm_js_log(`Buscando chats/usuarios: "${searchTerm}"`);

                    // Ocultar columna de detalles al iniciar/modificar búsqueda
                    $('#contact-details-column').css('display', 'none'); // <-- Volver a display: none

                    // 1. Limpiar resultados de búsqueda de WP anteriores
                    $chatListContainer.find('.wp-user-search-result, .wp-user-search-loading, .wp-user-search-no-results, .wp-user-search-error').remove(); // Limpiar todo lo relacionado a búsqueda WP

                    // 2. Decidir si buscar vía AJAX o mostrar lista original
                    if (searchTerm.length >= MIN_SEARCH_LENGTH) { // Buscar usuarios WP
                        // Ocultar la lista original y buscar en WP
                        // crm_js_log(`[BUSCADOR] Término >= ${MIN_SEARCH_LENGTH}. Ocultando lista original y buscando usuarios WP...`);
                        $chatListContainer.find('.chat-list-item:not(.wp-user-search-result)').hide(); // Ocultar originales
                        searchWpUsersForChat(searchTerm, $chatListContainer);
                    } else if (searchTerm === '') {
                        // Si se borró la búsqueda, mostrar todos los chats originales
                        // crm_js_log('[BUSCADOR] Término vacío. Mostrando lista original.');
                        $chatListContainer.find('.chat-list-item:not(.wp-user-search-result)').show();
                    } else {
                        // Si el término es corto, mostrar la lista original
                        // crm_js_log(`[BUSCADOR] Término corto (< ${MIN_SEARCH_LENGTH}). Mostrando lista original.`);
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
                        // crm_js_log(`Usuarios WP encontrados: ${users.length}`, 'INFO', users);
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
                        // crm_js_log('[searchWpUsersForChat] Resultados de búsqueda WP añadidos. Re-aplicando manejador de clics.', 'DEBUG');
                        addChatListItemClickHandler(); 
                        // TODO: Añadir handler para el botón "Iniciar Chat" si se implementa
                    } else {
                        // crm_js_log('No se encontraron usuarios WP para iniciar chat.', 'INFO');
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
                // crm_js_log('Botón Cerrar Detalles clickeado.');
                closeContactDetailsSidebar(); // Usar la función centralizada
            });
        }
             
    }); // Fin de $(document).ready
   
    /**
     * =========================================================================
     * == HISTORIAL DE CHATS (Funciones) ==
     * =========================================================================
     */

    function openChatView(userId, jid, displayName, avatarUrl) {
        console.log('FUNCIÓN openChatView INVOCADA con UserID:', userId, 'DisplayName:', displayName);
        // crm_js_log(`Abriendo vista de chat para User ID: ${userId}, JID: ${jid}`);

        // Marcar como activo en la lista (si ya existe, si no, se marcará cuando se cargue)
        $('#chat-list-items .chat-list-item.active').removeClass('active');
        const $listItem = $(`#chat-list-items .chat-list-item[data-user-id="${userId}"]`);
        if ($listItem.length) {
            $listItem.addClass('active');
        }

        // Ocultar placeholder
        $('#chat-messages-area').removeClass('chat-placeholder-active');

        // --- MODIFICACIÓN: No mostrar sidebar de detalles automáticamente ---
        // $('#contact-details-column').css('display', 'flex');
        // loadContactDetails(userId); 
        // --- FIN MODIFICACIÓN ---

        // Poblar y mostrar la nueva cabecera del chat
        $('#active-chat-header .chat-header-avatar').attr('src', avatarUrl || crm_evolution_sender_params.default_avatar_url).attr('alt', escapeHtml(displayName));
        $('#active-chat-header .chat-header-name').text(displayName);
        console.log('INTENTANDO MOSTRAR #active-chat-header');
        $('#active-chat-header').show();
        // crm_js_log(`Cabecera de chat activo poblada para: ${displayName}`);
        
        currentOpenChatUserId = userId; // Establecer el chat activo globalmente
        lastDisplayedMessageTimestamp = 0; // Resetear timestamp para cargar todos los mensajes
        loadConversationMessages(userId); // Cargar mensajes
        $('#chat-input-area').show(); // Mostrar área de input
    }

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
                    // console.log('Conversaciones recibidas:', response.data);
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
                    // crm_js_log('Error al cargar conversaciones:', 'ERROR', response.data.message);
                    chatListContainer.html('<p class="error-message">Error al cargar conversaciones. ' + escapeHtml(response.data.message || '') + '</p>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // crm_js_log('Error AJAX al cargar conversaciones:', 'ERROR', { status: textStatus, error: errorThrown });
                chatListContainer.empty(); // Limpiar
                chatListContainer.html('<p class="error-message">Error de conexión al cargar conversaciones.</p>');
            }
        });
    }

    /**
     * Añade el manejador de clicks a los items de la lista de chat usando delegación.
    */
    function addChatListItemClickHandler() {
        console.log('FUNCIÓN addChatListItemClickHandler INVOCADA');
        const $ = jQuery;
        $('#chat-list-items').off('click', '.chat-list-item').on('click', '.chat-list-item', function() {
        console.log('[DEBUG] Delegated .chat-list-item click detected on #chat-list-items!');
            const $clickedItem = $(this); // Guardar referencia al item clickeado
            let userId = $clickedItem.data('user-id');
            // Obtener displayName y avatarUrl del elemento clickeado
            const displayName = $clickedItem.find('.chat-item-name').text();
            const avatarUrl = $clickedItem.find('.chat-avatar').attr('src');
            const jid = null; // Temporalmente null. Para una solución completa, el JID debería obtenerse.

            // Si no se encontró en data-user-id, intentar con data-wp-user-id (resultados búsqueda WP)
            if (typeof userId === 'undefined' || userId === null) {
                userId = $clickedItem.data('wp-user-id');
            }

            if (!userId) {
                console.error('Error: No se pudo obtener el User ID del chat seleccionado.');
                return; // Salir si no hay userId
            }


            // Marcar como activo
            $('#chat-list-items .chat-list-item.active').removeClass('active'); // Quitar clase solo al activo
            $(this).addClass('active');
            // Ocultar placeholder al seleccionar chat
            $('#chat-messages-area').removeClass('chat-placeholder-active');

            // --- MODIFICACIÓN: No mostrar sidebar de detalles automáticamente al hacer clic en un chat de la lista ---
            // $('#contact-details-column').css('display', 'flex');
            // $('#contact-details-content').html('<p><span class="spinner is-active" style="float: none; vertical-align: middle;"></span> Cargando detalles...</p>');
            // loadContactDetails(userId);
            // --- FIN MODIFICACIÓN ---

            // Llamar a openChatView para configurar la vista del chat, incluida la cabecera
            openChatView(userId, jid, displayName, avatarUrl);

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

        // crm_js_log(`Solicitando mensajes para User ID: ${userId}`);

        // crm_js_log(`[DEBUG] loadConversationMessages - ANTES de iniciar $.ajax para User ID ${userId}.`, 'DEBUG'); // <-- LOG NUEVO
        $.ajax({
            url: crm_evolution_sender_params.ajax_url,
            type: 'POST',
            data: {
                action: 'crm_get_conversation_messages', // Nueva acción PHP
                _ajax_nonce: crm_evolution_sender_params.nonce,
                user_id: userId
            },
            success: function(response) {
                // crm_js_log(`[DEBUG] loadConversationMessages - Callback SUCCESS ejecutado para User ID ${userId}. Procediendo a procesar respuesta.`, 'DEBUG'); // <-- LOG NUEVO
                // crm_js_log(`Respuesta recibida para mensajes de User ID ${userId}:`, 'DEBUG', response);
                messagesContainer.empty(); // Limpiar contenedor

                let latestTimestampInBatch = 0; // Para guardar el timestamp del último mensaje de este lote
                if (response.success && Array.isArray(response.data) && response.data.length > 0) {
                    response.data.forEach(function(msg) {
                        const messageDate = new Date(msg.timestamp * 1000);
                        const timeString = messageDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                        const messageClass = msg.is_outgoing ? 'chat-message outgoing' : 'chat-message incoming';
                        let participantNameHtml = '';

                        // Mostrar nombre del participante para mensajes entrantes de grupo
                        if (!msg.is_outgoing && msg.participant_pushname) {
                            participantNameHtml = `<div class="message-participant-name">${escapeHtml(msg.participant_pushname)}</div><hr />`;
                        }

                        let messageContentHtml = '';

                        // Manejar diferentes tipos de mensajes
                        if (msg.type === 'image' && msg.attachment_url) {
                            messageContentHtml = `
                                <div class="message-media">
                                    <a href="${escapeHtml(msg.attachment_url)}" target="_blank" title="Ver imagen completa">
                                        <img src="${escapeHtml(msg.attachment_url)}" alt="Imagen adjunta" loading="lazy">
                                    </a>
                                </div>
                                ${msg.caption ? `<div class="message-caption">${msg.caption}</div>` : ''}
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
                                ${msg.caption ? `<div class="message-caption">${msg.caption}</div>` : ''}
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
                                ${msg.caption ? `<div class="message-caption">${msg.caption}</div>` : ''} <!-- Aunque raro, soportar caption -->`;
                        } else { // Texto o tipo desconocido
                            messageContentHtml = `<div class="message-text">${(msg.text || msg.caption || '')}</div>`;
                        }

                        const messageHtml = `
                            <div class="${messageClass}" data-msg-id="${msg.id}">
                                <div class="message-bubble">
                                    ${participantNameHtml}
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
                    // crm_js_log(`Error en la respuesta AJAX al cargar mensajes para User ID ${userId}:`, 'ERROR', response.data.message);
                    messagesContainer.html('<p class="error-message">Error al cargar los mensajes. ' + escapeHtml(response.data.message || '') + '</p>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // crm_js_log(`[ERROR] loadConversationMessages - Callback ERROR ejecutado para User ID ${userId}. Status: ${textStatus}, Error: ${errorThrown}`, 'ERROR', jqXHR); // <-- LOG NUEVO
                // crm_js_log(`Error AJAX al cargar mensajes para User ID ${userId}:`, 'ERROR', { status: textStatus, error: errorThrown });
                messagesContainer.html('<p class="error-message">Error de conexión al cargar mensajes.</p>');
            },
            complete: function(jqXHR, textStatus) {
                // Este callback se ejecuta después de success o error
                // crm_js_log(`[DEBUG] loadConversationMessages - Callback COMPLETE ejecutado para User ID ${userId}. Status de la petición: ${textStatus}`, 'DEBUG', jqXHR); // <-- LOG NUEVO

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
                    // crm_js_log('[loadConversationMessages - complete] La carga de mensajes fue exitosa (o no había mensajes). Adjuntando listeners del input.', 'DEBUG');
                    
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
                            // crm_js_log('[ENVIO] Botón Enviar Mensaje clickeado (directo).');
                            const messageText = $messageInput.val().trim();
                            const recipientUserId = currentOpenChatUserId;
                            // crm_js_log(`[ENVIO] Texto: "${messageText}", UserID: ${recipientUserId}, Adjunto:`, 'DEBUG', currentAttachment);

                            if (!recipientUserId) {
                                // crm_js_log('[ENVIO] Validación fallida: No hay chat activo.', 'ERROR');
                                showNotification('Error', 'error', 'No hay una conversación seleccionada.');
                                return;
                            }

                            if (currentAttachment) {
                                // crm_js_log(`[ENVIO] Intentando enviar adjunto a User ID: ${recipientUserId}`, 'INFO', currentAttachment);
                                $sendButton.prop('disabled', true);
                                performAjaxRequest('crm_send_media_message_ajax', { user_id: recipientUserId, attachment_url: currentAttachment.url, mime_type: currentAttachment.mime, filename: currentAttachment.filename, caption: messageText },
                                    function(response) { // onSuccess para envío de media
                                        console.log('[ENVIO-MEDIA] AJAX onSuccess ejecutado.', response);
                                        // Verificar si la respuesta del backend incluye el mensaje enviado
                                        if (response && response.sent_message) {
                                            // crm_js_log('[ENVIO-MEDIA] Renderizando mensaje enviado (optimista).', 'DEBUG', response.sent_message);
                                            renderSingleMessage(response.sent_message, messagesContainer);
                                            messagesContainer.scrollTop(messagesContainer[0].scrollHeight); // Scroll al nuevo mensaje
                                        }
                                        currentAttachment = null; $previewContainer.empty().hide(); $messageInput.val('');
                                        loadRecentConversations(); // Actualizar lista de chats
                                    }
                                ).always(function() { $sendButton.prop('disabled', false); });
                            } else if (messageText) {
                                // crm_js_log(`[ENVIO] Intentando enviar texto a User ID: ${recipientUserId}`, 'INFO', { text: messageText });
                                $sendButton.prop('disabled', true);
                                performAjaxRequest('crm_send_message_ajax', { user_id: recipientUserId, message_text: messageText },
                                    function(response) { // onSuccess para envío de texto
                                        console.log('[ENVIO-TEXTO] AJAX onSuccess ejecutado.', response);
                                        // Verificar si la respuesta del backend incluye el mensaje enviado
                                        if (response && response.sent_message) {
                                            // crm_js_log('[ENVIO-TEXTO] Renderizando mensaje enviado (optimista).', 'DEBUG', response.sent_message);
                                            renderSingleMessage(response.sent_message, messagesContainer);
                                            messagesContainer.scrollTop(messagesContainer[0].scrollHeight); // Scroll al nuevo mensaje
                                        }
                                        $messageInput.val('');
                                        loadRecentConversations(); // Actualizar lista de chats
                                    }
                                ).always(function() { $sendButton.prop('disabled', false); });
                            } else {
                                // crm_js_log('[ENVIO] Validación fallida: Mensaje vacío y sin adjunto.', 'WARN');
                                showNotification('Mensaje Vacío', 'warning', 'Escribe un mensaje o adjunta un archivo.');
                            }
                        });

                        $messageInput.off('keydown.sendMessage').on('keydown.sendMessage', function(event) { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); $sendButton.click(); } });
                        
                        if ($emojiButton.length && $emojiContainer.length) {
                            $emojiButton.off('click.toggleEmoji').on('click.toggleEmoji', function(event) { /* ... lógica emoji ... */ $emojiContainer.slideToggle(150); });
                            $emojiContainer.off('click.selectEmoji').on('click.selectEmoji', '.emoji-option', function(event) { /* ... lógica seleccionar emoji ... */ });
                        } else {
                            // crm_js_log('[loadConversationMessages - complete] Error: No se encontró #emoji-picker-button o #emoji-picker-container.', 'WARN');
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
                            // crm_js_log('[loadConversationMessages - complete] Error: No se encontró .btn-attach.', 'WARN');
                        }

                        if ($previewContainer.length) {
                            $previewContainer.off('click.removeAttachment').on('click.removeAttachment', '.remove-attachment', function(event) { event.preventDefault(); currentAttachment = null; $previewContainer.empty().hide(); });
                        }

                        // crm_js_log('[loadConversationMessages - complete] Listeners del input adjuntados.');
                    } else {
                        // crm_js_log('[loadConversationMessages - complete] Error: No se encontró #send-chat-message o #chat-message-input.', 'ERROR');
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

        // crm_js_log(`Solicitando detalles del contacto para User ID: ${userId}`);

        // Mostrar indicador de carga
        $detailsContainer.html('<p><span class="spinner is-active" style="float: none; vertical-align: middle;"></span> Cargando detalles...</p>');

        performAjaxRequest(
            'crm_get_contact_details', // Acción AJAX definida en PHP
            { user_id: userId },       // Datos a enviar
            function(details) { // onSuccess (recibe el objeto 'data' de la respuesta JSON)
                // crm_js_log('Detalles del contacto recibidos:', 'DEBUG', details);
                let detailsHtml = '';

                if (details.is_group) {
                    // --- HTML para GRUPOS ---
                    detailsHtml = `
                        <div class="contact-avatar-area" style="text-align: center; margin-bottom: 15px;">
                            <img src="${escapeHtml(details.avatar_url)}" alt="Avatar de ${escapeHtml(details.display_name)}" class="contact-details-avatar" style="width: 80px; height: 80px; border-radius: 50%; margin: 0 auto; display: block;">
                        </div>

                        <h4 class="contact-details-section-title">Información del Grupo</h4>
                        <div class="form-field">
                            <label>Nombre del Grupo:</label>
                            <span>${escapeHtml(details.display_name)}</span>
                        </div>
                        <div class="form-field">
                            <label>JID del Grupo:</label>
                            <span>${escapeHtml(details.jid)}</span>
                        </div>
                        <div class="form-field">
                            <label>Instancia:</label>
                            <span>${escapeHtml(details.instance_name || 'N/D')}</span>
                        </div>
                        <div class="form-field">
                            <label>Favorito:</label>
                            <span id="toggle-favorite-button" class="dashicons ${details.is_favorite ? 'dashicons-star-filled' : 'dashicons-star-empty'}" 
                                  data-user-id="${details.user_id}" 
                                  data-is-favorite="${details.is_favorite ? '1' : '0'}" 
                                  style="cursor: pointer; color: #FFD700; font-size: 20px; vertical-align: middle;" 
                                  title="${details.is_favorite ? 'Quitar de favoritos' : 'Marcar como favorito'}"></span>
                        </div>
                        
                        <hr>
                        <div style="text-align: right; margin-top: 20px;">
                            <button id="delete-group-button" class="button button-danger" data-user-id="${details.user_id}"><span class="dashicons dashicons-trash" style="vertical-align: middle; margin-top: -2px;"></span> Eliminar Grupo</button>
                        </div>
                    `;
                } else {
                    // --- HTML para CONTACTOS INDIVIDUALES ---
                    const favoriteIcon = details.is_favorite ? 'dashicons-star-filled' : 'dashicons-star-empty';
                    const isBusinessText = details.is_business ? 'Sí' : 'No';

                    detailsHtml = `
                        <div class="contact-avatar-area" style="text-align: center; margin-bottom: 15px;">
                            <img src="${escapeHtml(details.avatar_url)}" alt="Avatar de ${escapeHtml(details.display_name)}" class="contact-details-avatar" style="width: 80px; height: 80px; border-radius: 50%; margin: 0 auto; display: block; cursor: pointer;" title="Ver/Cambiar avatar">
                        </div>

                        <h4 class="contact-details-section-title">Datos de WordPress</h4>
                        <div class="form-field">
                            <label>Nombre:</label>
                            <span>${escapeHtml(details.display_name)}</span>
                        </div>
                        <div class="form-field">
                            <label>Nombres:</label>
                            <span>${escapeHtml(details.first_name || 'N/D')}</span>
                        </div>
                        <div class="form-field">
                            <label>Apellidos:</label>
                            <span>${escapeHtml(details.last_name || 'N/D')}</span>
                        </div>
                        <div class="form-field">
                            <label>Email:</label>
                            <span>${escapeHtml(details.email)}</span>
                        </div>
                        <div class="form-field">
                            <label>Rol:</label>
                            <span data-role-key="${escapeHtml(details.role_key || '')}">${escapeHtml(details.role || 'N/D')}</span>
                        </div>
                        <div class="form-field">
                            <label>Registrado:</label>
                            <span>${escapeHtml(details.registration_date || 'N/D')}</span>
                        </div>

                        <h4 class="contact-details-section-title">Datos del CRM y WhatsApp</h4>
                        <div class="form-field">
                            <label>Teléfono:</label>
                            <span>${escapeHtml(details.phone)}</span>
                            ${details.jid ? `<small style="display: block; color: #666;">(JID: ${escapeHtml(details.jid)})</small>` : ''}
                        </div>
                        <div class="form-field">
                            <label>Instancia:</label>
                            <span>${escapeHtml(details.instance_name || 'N/D')}</span>
                        </div>
                        <div class="form-field">
                            <label>Etiqueta:</label>
                            <span class="tag-badge" data-tag-key="${escapeHtml(details.tag_key || '')}">${escapeHtml(details.tag_name || 'Sin etiqueta')}</span>
                        </div>
                        <div class="form-field">
                            <label>Favorito:</label>
                            <span id="toggle-favorite-button" class="dashicons ${favoriteIcon}" data-user-id="${details.user_id}" data-is-favorite="${details.is_favorite ? '1' : '0'}" style="cursor: pointer; color: #FFD700; font-size: 20px; vertical-align: middle;" title="Marcar/Desmarcar como favorito"></span>
                        </div>
                        <div class="form-field">
                            <label>Cuenta de Empresa:</label>
                            <span>${isBusinessText}</span>
                        </div>
                        ${details.is_business ? `
                            <div class="form-field">
                                <label>Descripción Empresa:</label>
                                <span>${escapeHtml(details.description || 'N/D')}</span>
                            </div>
                            <div class="form-field">
                                <label>Sitio Web Empresa:</label>
                                <span>${details.website ? `<a href="${escapeHtml(details.website)}" target="_blank" rel="noopener noreferrer">${escapeHtml(details.website)}</a>` : 'N/D'}</span>
                            </div>
                        ` : ''}
                        <div class="form-field">
                            <label>Notas:</label>
                            <div class="contact-notes-content" style="background-color: #f9f9f9; border: 1px solid #eee; padding: 10px; min-height: 50px; border-radius: 3px;">
                                ${details.notes ? escapeHtml(details.notes).replace(/\n/g, '<br>') : '<i>Sin notas</i>'}
                            </div>
                        </div>

                        <h4 class="contact-details-section-title">Datos WooCommerce</h4>
                        ${details.last_purchase && details.last_purchase.id ? `
                        <div class="form-field">
                            <label style="font-weight: bold; margin-bottom: 8px; display: block; color: #333;">Última Compra:</label>
                            <div class="last-purchase-details" style="font-size: 0.9em; line-height: 1.6;">
                                <p style="margin: 0 0 5px 0;"><strong>ID Orden:</strong> 
                                    ${details.last_purchase.url ? `<a href="${escapeHtml(details.last_purchase.url)}" target="_blank" rel="noopener noreferrer" title="Ver orden en WooCommerce">#${escapeHtml(details.last_purchase.id)}</a>` : `#${escapeHtml(details.last_purchase.id)}`}
                                </p>
                                <p style="margin: 0 0 5px 0;"><strong>Fecha:</strong> ${escapeHtml(details.last_purchase.date || 'N/D')}</p>
                                <p style="margin: 0 0 5px 0;"><strong>Total:</strong> ${details.last_purchase.total || 'N/D'}</p>
                                <p style="margin: 0 0 5px 0;"><strong>Estado:</strong> <span class="order-status-badge" style="padding: 2px 6px; border-radius: 3px; background-color: #eee; color: #333;">${escapeHtml(details.last_purchase.status || 'N/D')}</span></p>
                            </div>
                        </div>
                        ` : `
                        <div class="form-field">
                            <label style="font-weight: bold; margin-bottom: 8px; display: block; color: #333;">Última Compra:</label>
                            <p style="margin: 0; font-size: 0.9em; color: #666;"><em>No se encontraron compras recientes.</em></p>
                        </div>
                        `}
                        ${details.customer_history && details.customer_history.total_orders !== null ? `
                        <div class="customer-history-panel">
                            <h4 class="customer-history-title" style="cursor: pointer; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                                Historial de Cliente (WooCommerce)
                                <span class="toggle-arrow dashicons dashicons-arrow-down-alt2"></span>
                            </h4>
                            <div class="customer-history-content" style="font-size: 0.9em; line-height: 1.6; padding-left: 10px; border-left: 2px solid #eee; margin-left: 5px; display: none;">
                                <p style="margin: 0 0 5px 0;"><strong>Total Pedidos:</strong> ${escapeHtml(details.customer_history.total_orders)}</p>
                                <p style="margin: 0 0 5px 0;"><strong>Ingresos Totales:</strong> ${details.customer_history.total_revenue || 'N/D'}</p>
                                <p style="margin: 0 0 5px 0;"><strong>Valor Promedio Pedido:</strong> ${details.customer_history.average_order_value || 'N/D'}</p>
                            </div>
                        </div>
                        ` : `
                        <p style="margin: 10px 0 5px 0; font-size: 0.9em; color: #666;"><em>No hay historial de cliente disponible.</em></p>
                        `
                        }

                        <hr>
                        <div style="text-align: right; margin-top: 20px;">
                                <button id="edit-contact-button" class="button" data-user-id="${details.user_id}"><span class="dashicons dashicons-edit" style="vertical-align: middle; margin-top: -2px;"></span> Editar</button>
                                <button id="delete-contact-button" class="button button-danger" data-user-id="${details.user_id}" style="margin-left: 5px;"><span class="dashicons dashicons-trash" style="vertical-align: middle; margin-top: -2px;"></span> Eliminar Contacto</button>
                        </div>
                    `;
                }

                // Insertar el HTML en el contenedor
                $detailsContainer.html(detailsHtml);

                // Añadir listener para el panel colapsable de Historial de Cliente
                $detailsContainer.off('click.toggleHistory').on('click.toggleHistory', '.customer-history-title', function() {
                    const $content = $(this).siblings('.customer-history-content');
                    const $arrow = $(this).find('.toggle-arrow');
                    $content.slideToggle(200);
                    if ($arrow.hasClass('dashicons-arrow-down-alt2')) {
                        $arrow.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
                    } else {
                        $arrow.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
                    }
                });

                // Listener para el avatar (para abrir la vista ampliada)
                // (Este listener ya estaba en el $(document).ready(), pero lo ponemos aquí para asegurar que se aplique
                // al contenido recién cargado si la estructura del avatar cambia)
                $detailsContainer.off('click.viewAvatar').on('click.viewAvatar', '.contact-details-avatar', function() {
                    const avatarUrl = $(this).attr('src');
                    if (!currentOpenChatUserId) {
                        showNotification('Error', 'error', 'No se pudo identificar al usuario.');
                        return;
                    }
                    currentEditingAvatarUserId = currentOpenChatUserId;
                    $('#expanded-avatar-image').attr('src', avatarUrl);
                    $('#contact-avatar-expanded-view').fadeIn(200);
                });

                // Listeners para los botones de eliminar (provisionalmente con alerts)
                // Usamos delegación en $detailsContainer porque los botones se crean dinámicamente
                $detailsContainer.off('click.deleteContact').on('click.deleteContact', '#delete-contact-button', function() {
                    const $button = $(this);
                    const userIdToDelete = $(this).data('user-id');
                    const contactName = $detailsContainer.find('label:contains("Nombre:")').next('span').text() || 'este contacto';

                    Swal.fire({
                        title: '¿Estás seguro?',
                        text: `Estás a punto de eliminar a "${escapeHtml(contactName)}". Esta acción es irreversible y eliminará su historial de chat y datos del CRM. Si tiene ventas, estas se desvincularán.`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Sí, ¡Eliminar!',
                        cancelButtonText: 'Cancelar',
                        showLoaderOnConfirm: true,
                        preConfirm: () => {
                            return performAjaxRequest(
                                'crm_delete_contact_or_group',
                                { user_id: userIdToDelete },
                                function(response) { // onSuccess
                                    return response; // Devolver la respuesta para que Swal la maneje
                                }
                                // onError es manejado por performAjaxRequest y muestra notificación
                            ).catch(error => {
                                Swal.showValidationMessage(`La solicitud falló: ${error.data?.message || 'Error desconocido'}`);
                            });
                        },
                        allowOutsideClick: () => !Swal.isLoading()
                    }).then((result) => {
                        if (result.isConfirmed) {
                            showNotification(`"${escapeHtml(contactName)}" ha sido eliminado.`, 'success');
                            $('#contact-details-column').css('display', 'none');

                            if (currentOpenChatUserId && parseInt(currentOpenChatUserId) === parseInt(userIdToDelete)) {
                                $('#chat-messages-area').html('').addClass('chat-placeholder-active');
                                $('#chat-input-area').hide();
                                $('#active-chat-header').hide();
                                currentOpenChatUserId = null;
                            }
                            loadRecentConversations();
                        }
                    });
                });

                $detailsContainer.off('click.deleteGroup').on('click.deleteGroup', '#delete-group-button', function() {
                    const $button = $(this);
                    const groupIdToDelete = $(this).data('user-id');
                    const groupName = $detailsContainer.find('label:contains("Nombre del Grupo:")').next('span').text() || 'este grupo';

                    Swal.fire({
                        title: '¿Estás seguro?',
                        text: `Estás a punto de eliminar el grupo "${escapeHtml(groupName)}". Esta acción es irreversible y eliminará su historial de chat y datos del CRM.`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Sí, ¡Eliminar!',
                        cancelButtonText: 'Cancelar',
                        showLoaderOnConfirm: true,
                        preConfirm: () => {
                            return performAjaxRequest(
                                'crm_delete_contact_or_group',
                                { user_id: groupIdToDelete, is_group: true }, // Indicamos que es un grupo
                                function(response) { // onSuccess
                                    return response;
                                }
                            ).catch(error => {
                                Swal.showValidationMessage(`La solicitud falló: ${error.data?.message || 'Error desconocido'}`);
                            });
                        },
                        allowOutsideClick: () => !Swal.isLoading()
                    }).then((result) => {
                        if (result.isConfirmed) {
                            showNotification(`El grupo "${escapeHtml(groupName)}" ha sido eliminado.`, 'success');
                            $('#contact-details-column').css('display', 'none');
                            // La lógica para limpiar el chat activo y recargar la lista es la misma que para contactos
                            if (currentOpenChatUserId && parseInt(currentOpenChatUserId) === parseInt(groupIdToDelete)) {
                                $('#chat-messages-area').html('').addClass('chat-placeholder-active');
                                $('#chat-input-area').hide();
                                $('#active-chat-header').hide();
                                currentOpenChatUserId = null;
                            }
                            loadRecentConversations();
                        }
                    });
                });

                // Listener para el botón de favorito (provisionalmente con alert)
                $detailsContainer.off('click.toggleFavorite').on('click.toggleFavorite', '#toggle-favorite-button', function() {
                    const $button = $(this);
                    const userId = $button.data('user-id');
                    const isCurrentlyFavorite = $button.data('is-favorite') == '1' || $button.data('is-favorite') === true; // Acepta '1' o true
                    const newFavoriteStatus = !isCurrentlyFavorite;

                    // crm_js_log(`Cambiando estado de favorito para User ID: ${userId}. Nuevo estado: ${newFavoriteStatus}`);

                    performAjaxRequest(
                        'crm_toggle_favorite_contact',
                        {
                            user_id: userId,
                            is_favorite: newFavoriteStatus
                        },
                        function(response) { // onSuccess
                            // crm_js_log('Estado de favorito actualizado con éxito.', 'INFO', response);
                            showNotification(newFavoriteStatus ? 'Contacto añadido a favoritos' : 'Contacto quitado de favoritos', 'success');

                            // Actualizar el botón en la UI
                            $button.data('is-favorite', newFavoriteStatus ? '1' : '0');
                            if (newFavoriteStatus) {
                                $button.removeClass('dashicons-star-empty').addClass('dashicons-star-filled');
                                $button.attr('title', 'Quitar de favoritos');
                            } else {
                                $button.removeClass('dashicons-star-filled').addClass('dashicons-star-empty');
                                $button.attr('title', 'Marcar como favorito');
                            }
                            // Opcional: Si la lista de chats se ve afectada por el estado de favorito, recargarla.
                            // loadRecentConversations(); 
                        }
                        // onError es manejado por performAjaxRequest
                    );
                });

            },
            function(errorResponse) { // onError (manejado parcialmente por performAjaxRequest)
                // crm_js_log('Error al cargar detalles del contacto.', 'ERROR', errorResponse);
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

        // crm_js_log(`[renderSingleMessage] Intentando renderizar msg ID: ${msg.id}`, 'DEBUG'); // <-- Log 1: Inicio

        // --- INICIO: Comprobación de duplicados visuales ---
        const existingElementCount = container.find(`.chat-message[data-msg-id="${msg.id}"]`).length; // <-- Guardar resultado
        // crm_js_log(`[renderSingleMessage] Comprobando duplicado para msg ID: ${msg.id}. Elementos existentes: ${existingElementCount}`, 'DEBUG'); // <-- Log 2: Resultado de la búsqueda
        if (existingElementCount > 0) {
            // crm_js_log(`Mensaje ${msg.id} ya existe en la UI (renderSingleMessage), omitiendo.`);
            return msg.timestamp; // Devolver timestamp aunque se omita para no romper lógica del heartbeat
        }
        // --- FIN: Comprobación de duplicados visuales ---
        const messageDate = new Date(msg.timestamp * 1000);
        const timeString = messageDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const messageClass = msg.is_outgoing ? 'chat-message outgoing' : 'chat-message incoming';
        let participantNameHtml = '';

        // Mostrar nombre del participante para mensajes entrantes de grupo
        if (!msg.is_outgoing && msg.participant_pushname) {
            participantNameHtml = `<div class="message-participant-name">${escapeHtml(msg.participant_pushname)}</div>`;
        }

        let messageContentHtml = '';
        // Reutilizar la lógica de renderizado de tipos de mensaje
        if (msg.type === 'image' && msg.attachment_url) {
            messageContentHtml = `<div class="message-media"><a href="${escapeHtml(msg.attachment_url)}" target="_blank" title="Ver imagen completa"><img src="${escapeHtml(msg.attachment_url)}" alt="Imagen adjunta" loading="lazy"></a></div>${msg.caption ? `<div class="message-caption">${msg.caption}</div>` : ''}`;
        } else if (msg.type === 'video' && msg.attachment_url) {
            messageContentHtml = `<div class="message-media"><video controls preload="metadata" src="${escapeHtml(msg.attachment_url)}"></video></div>${msg.caption ? `<div class="message-caption">${msg.caption}</div>` : ''}`;
        } else if (msg.type === 'audio' && msg.attachment_url) { // <-- Añadido para audio (también en renderSingleMessage)
            messageContentHtml = `<div class="message-media message-audio"><audio controls preload="metadata" src="${escapeHtml(msg.attachment_url)}"></audio></div>${msg.caption ? `<div class="message-caption">${msg.caption}</div>` : ''}`;
        } else if (msg.type === 'document' && msg.attachment_url) {
            messageContentHtml = `<div class="message-document"><a href="${escapeHtml(msg.attachment_url)}" target="_blank" download><span class="dashicons dashicons-media-default"></span> ${escapeHtml(msg.caption || msg.attachment_url.split('/').pop())}</a></div>`;
        } else {
            messageContentHtml = `<div class="message-text">${(msg.text || msg.caption || '')}</div>`;
        }

        const messageHtml = `
            <div class="${messageClass}" data-msg-id="${msg.id}">
                <div class="message-bubble">
                    ${participantNameHtml}
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
        // crm_js_log('Inicializando Heartbeat para actualizaciones de chat...');

        $(document).on('heartbeat-send.crmChat', function(event, data) {
            // Añadir nuestro timestamp al pulso saliente
            data['crm_last_chat_check'] = lastChatCheckTimestamp; // Para refrescar lista general
            if (currentOpenChatUserId) {
                data['crm_current_open_chat_id'] = currentOpenChatUserId;
                data['crm_last_message_timestamp'] = lastDisplayedMessageTimestamp; // Timestamp del último mensaje VISIBLE
            }
           
        });

        $(document).on('heartbeat-tick.crmChat', function(event, data) {
            // crm_js_log('Heartbeat Tick: Respuesta recibida', 'DEBUG', data);

            let listNeedsRefresh = false;
            let openChatUpdated = false;

            // 1. Comprobar si hay mensajes nuevos para el chat ABIERTO
            if (data.hasOwnProperty('crm_new_messages_for_open_chat') && Array.isArray(data.crm_new_messages_for_open_chat) && data.crm_new_messages_for_open_chat.length > 0) {
                // crm_js_log('Heartbeat Tick: Nuevos mensajes recibidos para el chat abierto.', 'INFO');
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
                // crm_js_log('Heartbeat Tick: Se necesita refrescar la lista de conversaciones.');
                lastChatCheckTimestamp = Math.floor(Date.now() / 1000);
                // Solo recargar la lista si el chat abierto NO fue actualizado (para evitar recarga innecesaria)
                // O si *siempre* quieres que la lista se reordene. Por ahora, evitamos recarga si ya actualizamos el chat abierto.
                if (!openChatUpdated) {
                    loadRecentConversations();
                } else {
                    // Opcional: Podríamos solo actualizar el snippet/hora del chat activo en la lista izquierda
                    // sin recargar toda la lista. Por ahora, no hacemos nada extra aquí.
                    // crm_js_log('Heartbeat Tick: Lista no refrescada porque el chat abierto ya fue actualizado.', 'DEBUG');
                }
            }
        });
    }

})(jQuery); // Fin de la encapsulación
