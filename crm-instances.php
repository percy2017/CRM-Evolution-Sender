<?php
// Incluir archivos necesarios para media_sideload_image
require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// == AJAX HANDLERS ESPECÍFICOS PARA LA PÁGINA DE INSTANCIAS (CARDS) ==
// =========================================================================

/**
 * Función auxiliar LOCAL para realizar peticiones a la API Evolution (copiada de crm-ajax-handlers.php).
 *
 * @param string $method Método HTTP (GET, POST, DELETE, etc.).
 * @param string $endpoint El endpoint de la API (ej: '/instance/fetchInstances').
 * @param array $body Datos para enviar en el cuerpo (para POST/PUT).
 * @param string|null $instance_api_key API Key específica de la instancia (opcional).
 * @return array|WP_Error Respuesta decodificada de la API o WP_Error en caso de fallo.
 */
function crm_instances_api_request( $method, $endpoint, $body = [], $instance_api_key = null ) {
    $api_url_base = get_option( 'crm_evolution_api_url', '' );
    $global_api_token = get_option( 'crm_evolution_api_token', '' );

    if ( empty( $api_url_base ) ) {
        error_log( '[crm_instances_api_request] Error: La URL de la API no está configurada.');
        return new WP_Error( 'api_config_error', 'La URL de la API no está configurada en los ajustes.' );
    }

    // Determinar qué API Key usar
    $api_key_to_use = $instance_api_key ? $instance_api_key : $global_api_token;

    if ( empty( $api_key_to_use ) ) {
         error_log( '[crm_instances_api_request] Error: No se encontró API Key (ni global ni específica) para la petición.' );
        return new WP_Error( 'api_config_error', 'Se requiere una API Key (Global o específica) para realizar la petición.' );
    }

    $request_url = trailingslashit( $api_url_base ) . ltrim( $endpoint, '/' );
    error_log( "[crm_instances_api_request] Realizando petición API: [{$method}] {$request_url}" );

    $args = array(
        'method'  => strtoupper( $method ),
        'headers' => array(
            'Content-Type' => 'application/json',
            'apikey'       => $api_key_to_use,
        ),
        'timeout' => 90,
        'redirection' => 5,
        'httpversion' => '1.1',
        'sslverify' => false, // Considera cambiar esto en producción
    );

    if ( ! empty( $body ) && ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') ) {
        $args['body'] = wp_json_encode( $body );
         error_log( "[crm_instances_api_request] Cuerpo de la petición: " . wp_json_encode( $body ));
    }

    $response = wp_remote_request( $request_url, $args );

    if ( is_wp_error( $response ) ) {
        error_log( '[crm_instances_api_request] Error en wp_remote_request: ' . $response->get_error_message() );
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code( $response );
    $response_body = wp_remote_retrieve_body( $response );
    $decoded_body = json_decode( $response_body, true );

    if ( $response_code >= 200 && $response_code < 300 ) {
        return $decoded_body !== null ? $decoded_body : [];
    } else {
        $error_message = isset( $decoded_body['message'] ) ? $decoded_body['message'] : (isset($decoded_body['error']) ? $decoded_body['error'] : $response_body);
        error_log( "[crm_instances_api_request] Error en la API ({$response_code}): {$error_message}" );
        return new WP_Error( 'api_error', $error_message, array( 'status' => $response_code ) );
    }
}


/**
 * Genera el HTML para la página de administración de instancias.
 */
function crm_render_instances_page_html() {
    // Verificar capacidad del usuario
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'No tienes permisos suficientes para acceder a esta página.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) );
    }
    ?>
    <div class="wrap crm-evolution-sender-wrap">
        <h1><?php esc_html_e( 'Gestión de Instancias Evolution API', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></h1>

        <div class="crm-actions-bar">
            <a href="#TB_inline?width=600&height=450&inlineId=add-instance-modal-content"
               class="thickbox button button-primary"
               id="add-instance-button"
               title="<?php esc_attr_e( 'Añadir Nueva Instancia', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>">
                <?php esc_html_e( 'Añadir Nueva Instancia', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>
            </a>
        </div>

        <!-- Contenedor para las tarjetas de instancias -->
        <div id="instances-cards-container" class="crm-cards-container">
            <!-- Las tarjetas se generarán dinámicamente con JavaScript -->
            <p class="loading-message"><?php _e( 'Cargando instancias...', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
        </div>

        <!-- === INICIO: Contenido del Modal para Añadir Instancia (para Thickbox) === -->
        <div id="add-instance-modal-content" style="display:none;">
            <?php // --- INICIO: Formulario Añadir Instancia --- ?>
            <form id="add-instance-form" class="crm-modal-form">
                <?php wp_nonce_field( 'crm_create_instance_action', 'crm_create_instance_nonce' ); ?>

                <h2><?php esc_html_e( 'Añadir Nueva Instancia', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></h2>

                <p>
                    <label for="instance_name_modal"><?php esc_html_e( 'Nombre de la Instancia:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label><br>
                    <input type="text" name="instance_name" id="instance_name_modal" class="regular-text" required pattern="[a-zA-Z0-9_-]+" title="<?php esc_attr_e('Solo letras, números, guiones bajos y guiones medios.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?>"> <?php // <-- CORRECCIÓN: Guion movido al final ?>
                    <p class="description"><?php esc_html_e( 'Identificador único. Sin espacios ni caracteres especiales.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
                </p>
                <p>
                    <label for="webhook_url_modal"><?php esc_html_e( 'Webhook URL:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label><br>
                    <input type="url" name="webhook_url" id="webhook_url_modal" class="regular-text" value="<?php echo esc_url( rest_url( 'crm-evolution-api/v1/webhook' ) ); ?>" required>
                    <p class="description"><?php esc_html_e( 'URL para recibir eventos de esta instancia. Normalmente no necesita cambiarse.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
                </p>

                <?php submit_button( __( 'Crear Instancia', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), 'primary', 'submit-add-instance' ); ?>
            </form>
             <?php // --- FIN: Formulario Añadir Instancia --- ?>
        </div>
        <!-- === FIN: Contenido del Modal para Añadir Instancia === -->

        <!-- Modal para mostrar el QR -->
        <div id="qr-code-modal" style="display:none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center;">
            <div style="background-color: white; padding: 20px; border-radius: 5px; text-align: center; position: relative;">
                <button id="close-qr-modal" style="position: absolute; top: 5px; right: 10px; background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
                <h3 id="qr-modal-title"><?php _e( 'Escanea el código QR', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></h3>
                <div id="qr-code-container" style="margin-top: 15px;">
                    <!-- El QR se insertará aquí -->
                </div>
                <p id="qr-modal-instructions" style="margin-top: 15px;"><?php _e( 'Abre WhatsApp en tu teléfono y escanea el código.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
                <div id="qr-loading-spinner" style="display: none; margin-top: 20px;">
                    <span class="spinner is-active" style="float: none;"></span>
                    <?php echo 'Cargando QR...'; // Usar string directo para evitar problemas con constante ?>
                </div>
            </div>
        </div>

    </div><!-- .wrap -->
    <?php
    // --- Plantilla Underscore.js para las tarjetas de instancia ---
    ?>
    <script type="text/html" id="tmpl-instance-card">
        <div class="instance-card card status-{{ data.statusClass }}" data-instance-name="{{ data.instanceName }}">
            <div class="card-header">
                <img src="{{ data.profilePicUrl || '<?php echo esc_url(includes_url('images/blank.gif')); ?>' }}" alt="Avatar" class="instance-avatar">
                <h3 class="instance-name">{{ data.instanceName }}</h3>
                <span class="instance-status status-badge status-{{ data.statusClass }}">{{ data.statusText }}</span>
            </div>
            <div class="card-body">
                <p class="instance-owner-number">
                    <span class="dashicons dashicons-whatsapp" style="vertical-align: middle; color: #25D366;"></span> {{ data.ownerNumber }}
                </p>
            </div>
            <div class="card-actions">
                <# if ( data.status === 'close' || data.status === 'connecting' || data.status === 'qrcode' ) { #>
                    <button class="button button-secondary btn-get-qr" title="<?php esc_attr_e( 'Obtener QR / Conectar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>">
                        <span class="dashicons dashicons-camera"></span>
                    </button>
                <# } #>
                <button class="button button-secondary btn-toggle-groups" data-groups-ignore="{{ data.groupsIgnore }}" title="<?php esc_attr_e( 'Activar/Desactivar Ignorar Grupos', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>">
                    <# if ( data.settings && data.settings.groups_ignore ) { #>
                        <span class="dashicons dashicons-groups" style="color:red;" title="<?php esc_attr_e( 'Grupos Ignorados', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>"></span>
                    <# } else { #>
                        <span class="dashicons dashicons-networking" style="color:green;" title="<?php esc_attr_e( 'Procesando Grupos', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>"></span>
                    <# } #>
                </button>
                <button class="button button-secondary btn-sync-contacts" title="<?php esc_attr_e( 'Sincronizar Contactos', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>">
                    <span class="dashicons dashicons-admin-users"></span>
                </button>
                <button class="button button-danger btn-delete" title="<?php esc_attr_e( 'Eliminar Instancia', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
        </div>
    </script>
    <?php
    // --- Fin Plantilla ---
}

// =========================================================================
// == AJAX HANDLERS ESPECÍFICOS PARA LA PÁGINA DE INSTANCIAS (CARDS) ==
// =========================================================================


/**
 * AJAX Handler NUEVO para obtener la lista de instancias (llamado por admin-instances.js).
 */
function crm_get_instances_cards_callback() {
    error_log( 'Recibida petición AJAX: crm_get_instances_cards (desde crm-instances.php)' );
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ), 403 );
    }

    $api_response = crm_instances_api_request( 'GET', '/instance/fetchInstances' ); // Usa la función local

    if ( is_wp_error( $api_response ) ) {
        error_log( 'Error al obtener instancias de la API (cards): ' . $api_response->get_error_message() );
        wp_send_json_error( array( 'message' => $api_response->get_error_message() ) );
    } elseif ( is_array( $api_response ) ) {
        $instances_data = array();
        foreach ( $api_response as $instance ) {
            $profile_pic_url = isset($instance['instance']['profilePictureUrl']) ? $instance['instance']['profilePictureUrl'] : null;
            $instances_data[] = array(
                'settings'      => isset($instance['instance']['settings']) && is_array($instance['instance']['settings']) ? $instance['instance']['settings'] : array(),
                'instance_name' => isset($instance['instance']['instanceName']) ? $instance['instance']['instanceName'] : 'N/D',
                'status'        => isset($instance['instance']['status']) ? $instance['instance']['status'] : 'unknown',
                'api_key'       => isset($instance['instance']['apiKey']) ? $instance['instance']['apiKey'] : null,
                'owner'         => isset($instance['instance']['owner']) ? $instance['instance']['owner'] : null,
                'profile_pic_url' => $profile_pic_url,
            );
        }
        wp_send_json_success( $instances_data );
    } else {
        error_log( 'Respuesta inesperada de la API al obtener instancias (cards).' );
        wp_send_json_error( array( 'message' => 'Respuesta inesperada de la API.' ) );
    }
}
add_action( 'wp_ajax_crm_get_instances_cards', 'crm_get_instances_cards_callback' );

/**
 * AJAX Handler NUEVO para crear una instancia (llamado por admin-instances.js).
 */
function crm_create_instance_cards_callback() {
    error_log( 'Recibida petición AJAX: crm_create_instance_cards (desde crm-instances.php)' );
    check_ajax_referer( 'crm_create_instance_action', 'crm_create_instance_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
    }

    $instance_name = isset( $_POST['instance_name'] ) ? sanitize_key( $_POST['instance_name'] ) : '';
    $webhook_url   = isset( $_POST['webhook_url'] ) ? esc_url_raw( wp_unslash( $_POST['webhook_url'] ) ) : '';

    if ( empty( $instance_name ) ) {
        wp_send_json_error( array( 'message' => 'El nombre de la instancia es obligatorio.' ) );
    }
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $instance_name)) {
        wp_send_json_error( array( 'message' => 'Nombre de instancia inválido. Solo letras, números, guiones y guiones bajos.' ) );
    }

    $body = array(
        'instanceName' => $instance_name,
        'qrcode'       => true,
        'sync_full_history' => true,
        'groups_ignore' => true,
        'always_online' => true,
        'webhook' => $webhook_url,
        'webhook_base64' => true,
        'events' => [ 'MESSAGES_UPSERT', 'QRCODE_UPDATED', 'CONNECTION_UPDATE' ]
    );

    $api_response = crm_instances_api_request( 'POST', '/instance/create', $body ); // Usa la función local

    if ( is_wp_error( $api_response ) ) {
        $error_data = $api_response->get_error_data();
        $status_code = isset($error_data['status']) ? $error_data['status'] : null;
        $error_message = 'Error API: ' . $api_response->get_error_message();
        wp_send_json_error( array( 'message' => $error_message ), $status_code ?: 500 );
    } else {
        $message = isset($api_response['message']) ? $api_response['message'] : 'Instancia creada iniciada.';
        $status = null;
        if (isset($api_response['instance']) && isset($api_response['instance']['status'])) {
            $status = $api_response['instance']['status'];
        } elseif (isset($api_response['status'])) {
             $status = $api_response['status'];
        }
        error_log( "Instancia {$instance_name} creada (cards). Respuesta API:" );
        wp_send_json_success( array( 'message' => $message, 'status' => $status ) );
    }
}
add_action( 'wp_ajax_crm_create_instance_cards', 'crm_create_instance_cards_callback' );

/**
 * AJAX Handler NUEVO para obtener el QR Code (llamado por admin-instances.js).
 */
function crm_get_instance_qr_cards_callback() {
    error_log( 'Recibida petición AJAX: crm_get_instance_qr_cards (desde crm-instances.php)' );
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
    }

    $instance_name = isset( $_POST['instance_name'] ) ? sanitize_key( $_POST['instance_name'] ) : '';
    if ( empty( $instance_name ) ) {
        wp_send_json_error( array( 'message' => 'Nombre de instancia no proporcionado.' ) );
    }

    $endpoint = '/instance/connect/' . $instance_name;
    $api_response = crm_instances_api_request( 'GET', $endpoint ); // Usa la función local
    if ( is_wp_error( $api_response ) ) {
        wp_send_json_error( array( 'message' => 'Error API: ' . $api_response->get_error_message() ) );
    } else {
        $qr_code_base64 = null;
        if (isset($api_response['qrcode']) && isset($api_response['qrcode']['base64'])) {
            $qr_code_base64 = $api_response['qrcode']['base64'];
        } elseif (isset($api_response['base64'])) {
             $qr_code_base64 = $api_response['base64'];
        }

        if ($qr_code_base64) {
             if (strpos($qr_code_base64, 'data:image') === false) {
                 $qr_code_base64 = 'data:image/png;base64,' . $qr_code_base64;
             }
            wp_send_json_success( array( 'qrCode' => $qr_code_base64 ) );
        } else {
             $status = isset($api_response['instance']['status']) ? $api_response['instance']['status'] : 'desconocido';
             $message = "No se necesita QR Code. Estado actual: {$status}.";
             if ($status === 'connection' || $status === 'open' || $status === 'connected') $message = "La instancia ya está conectada.";
            wp_send_json_error( array( 'message' => $message ) );
        }
    }
}
add_action( 'wp_ajax_crm_get_instance_qr_cards', 'crm_get_instance_qr_cards_callback' );

/**
 * AJAX Handler NUEVO para eliminar una instancia (llamado por admin-instances.js).
 * Intenta desconectar primero y luego elimina.
*/
function crm_delete_instance_cards_callback() {
    error_log( 'Recibida petición AJAX: crm_delete_instance_cards (desde crm-instances.php)' );
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
    }

    $instance_name = isset( $_POST['instance_name'] ) ? sanitize_key( $_POST['instance_name'] ) : '';
    if ( empty( $instance_name ) ) {
        wp_send_json_error( array( 'message' => 'Nombre de instancia no proporcionado.' ) );
    }

    // Intentar desconectar primero (logout)
    error_log( "Intentando desconectar instancia {$instance_name} antes de eliminar (cards)...");
    $logout_endpoint = '/instance/logout/' . $instance_name;
    $logout_response = crm_instances_api_request( 'DELETE', $logout_endpoint ); // Usa la función local
    if ( is_wp_error( $logout_response ) ) {
        error_log( "Aviso (cards): Fallo al desconectar {$instance_name} (puede que ya estuviera desconectada): " . $logout_response->get_error_message() );
    } else {
        error_log( "Instancia {$instance_name} desconectada (o ya lo estaba) (cards)." );
    }

    // Proceder a eliminar (delete)
    error_log( "Intentando eliminar instancia {$instance_name} (cards)..." );
    $delete_endpoint = '/instance/delete/' . $instance_name;
    $delete_response = crm_instances_api_request( 'DELETE', $delete_endpoint ); // Usa la función local

    if ( is_wp_error( $delete_response ) ) {
        // Manejar 404 como éxito parcial
        if ($delete_response->get_error_data() && isset($delete_response->get_error_data()['status']) && $delete_response->get_error_data()['status'] == 404) {
             error_log( "Intento de eliminar instancia {$instance_name} (cards) que no existe (404). Considerado éxito." );
             wp_send_json_success( array( 'message' => "La instancia {$instance_name} no se encontró o ya fue eliminada." ) );
        } else {
            wp_send_json_error( array( 'message' => 'Error API al eliminar: ' . $delete_response->get_error_message() ) );
        }
    } else {
        error_log( "Instancia {$instance_name} eliminada con éxito (cards). Respuesta API:");
        wp_send_json_success( array( 'message' => "Instancia {$instance_name} eliminada correctamente." ) );
    }
}
add_action( 'wp_ajax_crm_delete_instance_cards', 'crm_delete_instance_cards_callback' );


// =========================================================================
// == API HEARTBEAT PARA ACTUALIZACIONES DE ESTADO/QR DE INSTANCIAS ==
// =========================================================================

/**
 * Procesa los datos enviados por el Heartbeat desde la página de instancias
 */
function crm_handle_instance_heartbeat_request( $response, $data, $screen_id ) {
    error_log("[Heartbeat PHP - ENTRY] Función llamada. Screen ID: '{$screen_id}'. Datos recibidos: " . print_r($data, true));

    // Solo actuar si estamos en la página de instancias y recibimos el dato esperado
    if ( $screen_id === 'toplevel_page_crm-evolution-sender-main' && isset( $data['crm_waiting_instance'] ) ) {
        error_log("[Heartbeat PHP] Recibido pulso para screen '{$screen_id}'."); // DEBUG
        $instance_name = sanitize_key( $data['crm_waiting_instance'] );
        error_log( "Heartbeat Instancias: Recibido pulso esperando por '{$instance_name}'.");

        // 1. Comprobar transient de estado
        $status_transient_key = 'crm_instance_status_' . $instance_name;
        error_log("[Heartbeat PHP] Comprobando transient: {$status_transient_key}"); // DEBUG
        $new_status = get_transient( $status_transient_key );
        error_log("[Heartbeat PHP] Valor del transient '{$status_transient_key}': " . ($new_status === false ? 'false' : $new_status)); // DEBUG

        if ( $new_status !== false ) {
            error_log( "Heartbeat Instancias: Nuevo estado '{$new_status}' encontrado para '{$instance_name}'.");
            $response['crm_instance_status_update'] = ['instance' => $instance_name, 'status' => $new_status];
            delete_transient( $status_transient_key );
        }

        // 2. Comprobar transient de QR
        $qr_transient_key = 'crm_instance_qr_' . $instance_name;
        error_log("[Heartbeat PHP] Comprobando transient: {$qr_transient_key}"); // DEBUG
        $new_qr = get_transient( $qr_transient_key );
        error_log("[Heartbeat PHP] Valor del transient '{$qr_transient_key}': " . ($new_qr === false ? 'false' : '(QR data)')); // DEBUG (No loguear el base64 completo)

        if ( $new_qr !== false ) {
            error_log( "Heartbeat Instancias: Nuevo QR encontrado para '{$instance_name}'.");
            $response['crm_instance_qr_update'] = ['instance' => $instance_name, 'qrCode' => $new_qr];
            delete_transient( $qr_transient_key );
        }
    }
    // error_log("[Heartbeat PHP] Respuesta final: " . print_r($response, true)); // DEBUG (Descomentar si es necesario, puede ser muy verboso)

    return $response;
}
add_filter( 'heartbeat_received', 'crm_handle_instance_heartbeat_request', 10, 3 );

// =========================================================================
// == MANEJADORES AJAX - SINCRONIZACIÓN DE CONTACTOS (INSTANCES PAGE) ==
// =========================================================================

/**
 * AJAX Handler para sincronizar (importar/actualizar) contactos desde una instancia Evolution.
 * Llamado desde admin-instances.js
 */
function crm_sync_instance_contacts_callback() {
    error_log( 'Recibida petición AJAX: crm_sync_instance_contacts (desde crm-instances.php)' );

    // 1. Verificar Nonce y Permisos
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) { // Capacidad para gestionar instancias
        error_log( 'Error AJAX: Permiso denegado para crm_sync_instance_contacts.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ), 403 );
    }

    // 2. Obtener y sanitizar nombre de instancia
    $instance_name = isset( $_POST['instance_name'] ) ? sanitize_key( $_POST['instance_name'] ) : '';
    if ( empty( $instance_name ) ) {
        error_log( 'Error AJAX: Nombre de instancia no proporcionado para sincronizar contactos.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'Nombre de instancia no proporcionado.' ), 400 );
    }

    error_log( "Iniciando sincronización de contactos para instancia: {$instance_name}" );

    // 3. Llamar a la API para obtener contactos
    // Endpoint: POST /chat/findContacts/{instance}
    // Enviamos {} intentando obtener todos. Ajustar si es necesario.
    $endpoint = "/chat/findContacts/{$instance_name}";
    // Usar la función local renombrada
    $api_response = crm_instances_api_request( 'POST', $endpoint, array() ); // <-- Usa la función local

    // Log para depurar la respuesta CRUDA de la API
    error_log("[crm_sync_instance_contacts] Respuesta API CRUDA (/chat/findContacts): " . print_r($api_response, true));

    if ( is_wp_error( $api_response ) ) {
        error_log( "Error API al obtener contactos para {$instance_name}: " . $api_response->get_error_message(), 'ERROR' );
        wp_send_json_error( array( 'message' => 'Error API al obtener contactos: ' . $api_response->get_error_message() ) );
    }

    if ( ! is_array( $api_response ) ) {
        error_log( "Respuesta inesperada de la API al obtener contactos para {$instance_name}. Se esperaba un array.", 'ERROR', $api_response );
        wp_send_json_error( array( 'message' => 'Respuesta inesperada de la API al obtener contactos.' ) );
    }

    // 4. Procesar cada contacto
    $processed_count = 0;
    $created_count = 0; // <-- Contador para usuarios nuevos
    $failed_count = 0;

    foreach ( $api_response as $contact ) {
        $jid = isset( $contact['id'] ) ? sanitize_text_field( $contact['id'] ) : null;
        // *** CORRECCIÓN: Usar pushName con 'N' mayúscula ***
        $push_name = isset( $contact['pushName'] ) ? sanitize_text_field( $contact['pushName'] ) : (isset($contact['name']) ? sanitize_text_field($contact['name']) : null);
        // *** NUEVO: Extraer URL del avatar ***
        $avatar_url = isset( $contact['profilePictureUrl'] ) ? esc_url_raw( $contact['profilePictureUrl'] ) : null;

        if ( $jid && strpos($jid, '@s.whatsapp.net') !== false ) { // Solo procesar JIDs de usuario válidos
            // Comprobar si el usuario existía ANTES de procesarlo
            $user_id_before = get_users(['meta_key' => '_crm_whatsapp_jid', 'meta_value' => $jid, 'fields' => 'ID', 'number' => 1]); // Optimizar query

            // *** Usar la función local crm_instances_process_single_jid y pasar la URL del avatar ***
            // Pasamos el $push_name corregido
            $result_user_id = crm_instances_process_single_jid( $jid, $instance_name, $push_name, true, $avatar_url ); // <-- Pasar $avatar_url

            if ( $result_user_id > 0 ) {
                $processed_count++;
                // Si no existía antes, incrementar contador de creados
                if (empty($user_id_before)) {
                    $created_count++;
                }
            } else {
                $failed_count++;
            }
        }
    }

    // *** Programar el Cron si se crearon usuarios nuevos ***
    if ($created_count > 0) {
        error_log("[crm_sync_instance_contacts] Se crearon {$created_count} usuarios nuevos. Programando tarea Cron 'crm_instances_process_avatar_batch'.");
        // Solo programar si no está ya programada para evitar duplicados
        if (!wp_next_scheduled('crm_instances_process_avatar_batch')) {
            wp_schedule_single_event(time() + 10, 'crm_instances_process_avatar_batch'); // Ejecutar en 10 segundos
        }
    }

    $message = sprintf( __( 'Sincronización completada para %s. Contactos procesados: %d. Nuevos creados: %d. Fallidos: %d.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), $instance_name, $processed_count, $created_count, $failed_count );
    error_log( $message );
    wp_send_json_success( array( 'message' => $message ) );
}


add_action( 'wp_ajax_crm_sync_instance_contacts', 'crm_sync_instance_contacts_callback' );

/**
 * Obtiene las etiquetas de ciclo de vida guardadas (copia local).
 *
 * @return array Array asociativo de etiquetas [key => name].
 */
function crm_instances_get_lifecycle_tags() {
    return get_option( 'crm_evolution_lifecycle_tags', array() );
}

/**
 * Procesa un JID individual para la sincronización desde la página de instancias.
 * Busca un usuario WP existente o crea uno nuevo si no existe.
 * Si lo crea, guarda metadatos (JID, billing_phone, primera etiqueta, nombre instancia),
 * intenta descargar y guardar el avatar inmediatamente si se proporciona la URL.
 * Si ya existe, actualiza el display_name si es necesario.
 *
 * @param string $jid El JID a procesar (ej: "59171146267@s.whatsapp.net").
 * @param string $instanceName Nombre de la instancia API (para guardar en meta).
 * @param string|null $pushNameToUse El pushName recibido en el webhook.
 * @param bool $usePushName Indica si se debe usar $pushNameToUse para el display_name.
 * @param string|null $avatar_url URL del avatar obtenida de /findContacts (opcional).
 * @return int El ID del usuario WP encontrado o creado, o 0 si falla o no es un JID de usuario.
 */
function crm_instances_process_single_jid($jid, $instanceName, $pushNameToUse = null, $usePushName = false, $avatar_url = null) { // <-- Aceptar $avatar_url
    // Validar que sea un JID de usuario individual
    if (strpos($jid, '@s.whatsapp.net') === false) {
        error_log("[crm_instances_process_single_jid] JID '{$jid}' no es un usuario individual. Ignorando.");
        return 0;
    }

    // Buscar usuario existente por JID en user_meta
    $user_query = new WP_User_Query(array(
        'meta_key'   => '_crm_whatsapp_jid',
        'meta_value' => $jid,
        'number'     => 1,
        'fields'     => 'ID',
    ));

    $users = $user_query->get_results();
    $user_id = !empty($users) ? $users[0] : 0;

    if ($user_id) { // Usuario encontrado
        error_log("[crm_instances_process_single_jid] Usuario encontrado para JID '{$jid}' con User ID: {$user_id}.");
        // El usuario ya existe, actualizar display_name si es necesario
        $update_data = ['ID' => $user_id];
        // *** Usar $pushNameToUse para actualizar display_name si $usePushName es true ***
        if ($usePushName && !empty($pushNameToUse)) {
            $update_data['display_name'] = $pushNameToUse;
        }
        // Solo actualizar si hay algo que cambiar (en este caso, display_name)
        if (count($update_data) > 1) {
            error_log("[crm_instances_process_single_jid] Datos para actualizar (wp_update_user): " . print_r($update_data, true));
            wp_update_user( $update_data );
        }

    } else { // Usuario NO encontrado, crear uno nuevo
        error_log("[crm_instances_process_single_jid] Usuario NO encontrado para JID '{$jid}'. Intentando crear uno nuevo.");

        $number = strstr($jid, '@', true);
        if (!$number) {
            error_log("[crm_instances_process_single_jid] Error: No se pudo extraer el número del JID '{$jid}'");
            return 0;
        }

        $username = 'wa_' . $number;
        $email = $number . '@whatsapp.placeholder';
        $password = wp_generate_password(12, true);
        // *** Usar $pushNameToUse para display_name si $usePushName es true ***
        $display_name = ($usePushName && !empty($pushNameToUse)) ? $pushNameToUse : $username;

        // Dividir display_name en first_name y last_name (simple)
        $first_name = ''; // <-- Inicializar
        $last_name = '';  // <-- Inicializar
        $name_parts = explode(' ', $display_name, 2); // Dividir en máximo 2 partes por el primer espacio
        if (count($name_parts) > 1) {
            $first_name = $name_parts[0];
            $last_name = $name_parts[1];
        } else {
            $first_name = $display_name; // Si no hay espacio, usar todo como first_name
        }

        $user_data = array(
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => $password,
            'display_name' => $display_name,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'role'         => 'subscriber',
        );

        error_log("[crm_instances_process_single_jid] Datos para NUEVO usuario (wp_insert_user): " . print_r($user_data, true));
        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            error_log("[crm_instances_process_single_jid] Error al crear usuario para JID '{$jid}': " . $user_id->get_error_message());
            // Podrías añadir lógica para reintentar con username único si el error es 'existing_user_login'
            return 0;
        }

        // Guardar metadatos básicos
        update_user_meta($user_id, '_crm_whatsapp_jid', $jid);
        update_user_meta($user_id, '_crm_is_group', false);
        update_user_meta($user_id, '_crm_is_favorite', false);
        update_user_meta($user_id, '_crm_instance_name', $instanceName);
        update_user_meta($user_id, '_crm_isBusiness', isset($profile_response['isBusiness']) ? (bool)$profile_response['isBusiness'] : false);
        update_user_meta($user_id, '_crm_description', $profile_response['description'] ?? null);
        update_user_meta($user_id, '_crm_website', $profile_response['website'] ?? null);
        update_user_meta($user_id, 'billing_phone', $number);
        update_user_meta($user_id, 'billing_first_name', $first_name);
        update_user_meta($user_id, 'billing_last_name', $last_name);

        // Asignar la primera etiqueta definida
        $defined_tags = crm_instances_get_lifecycle_tags(); // Usar la función local
        if (!empty($defined_tags)) {
            $default_tag_key = array_key_first($defined_tags);
            update_user_meta($user_id, '_crm_lifecycle_tag', $default_tag_key);
        }

        // *** Descargar y guardar avatar INMEDIATAMENTE si hay URL ***
        if (!empty($avatar_url)) {
            error_log("[crm_instances_process_single_jid] Nuevo usuario creado con User ID: {$user_id}. Intentando descargar avatar desde: {$avatar_url}");

            // Descargar la imagen, añadirla a la biblioteca de medios y obtener su ID
            // El tercer argumento es la descripción (usamos display_name), 'id' devuelve el ID del adjunto
            $attachment_id = media_sideload_image($avatar_url, 0, $display_name, 'id');

            if (!is_wp_error($attachment_id)) {
                // Si la descarga fue exitosa y obtuvimos un ID numérico
                update_user_meta($user_id, '_crm_avatar_attachment_id', $attachment_id);
                error_log("[crm_instances_process_single_jid] Avatar descargado y guardado para User ID {$user_id}. Attachment ID: {$attachment_id}");

                // Opcional: Asociar el adjunto al usuario (aunque no es estrictamente necesario si usamos el meta)
                // wp_update_post(array('ID' => $attachment_id, 'post_author' => $user_id));

            } else {
                // Si hubo un error al descargar o guardar
                error_log("[crm_instances_process_single_jid] Error al descargar/guardar avatar para User ID {$user_id} desde {$avatar_url}: " . $attachment_id->get_error_message());
            }

        } else {
            error_log("[crm_instances_process_single_jid] Nuevo usuario creado con User ID: {$user_id}. No se encontró URL de avatar para descargar.");
        }
        // --- FIN Descarga Avatar ---

    }
    return $user_id; // Devolver el ID del usuario (encontrado o creado)
}


// =========================================================================
// == WP-CRON PARA PROCESAMIENTO DE AVATARES EN SEGUNDO PLANO ==
// =========================================================================

// /**
//  * Función callback para la tarea Cron que procesa avatares pendientes.
//  * Busca usuarios con el metadato '_crm_avatar_pending' = true,
//  * procesa un lote, y se reprograma si quedan más.
//  */

/**
 * Nueva función callback para el Cron que descarga y guarda un avatar específico.
 * Recibe el user_id y la URL del avatar directamente.
 *
 * @param int    $user_id    ID del usuario de WordPress.
 * @param string $avatar_url URL de la imagen de perfil a descargar.
 * @return bool|int ID del adjunto si se guardó correctamente, False en caso de error.
 */
function crm_instances_download_avatar_callback($user_id, $avatar_url) {
    error_log("[CRON_AVATAR_SIMPLE] Iniciando descarga para User ID: {$user_id} desde URL: {$avatar_url}");

    // Verificar si el usuario todavía existe
    if (!get_userdata($user_id)) {
        error_log("[CRON_AVATAR_SIMPLE] Error: Usuario ID {$user_id} no encontrado. Abortando descarga.");
        return false;
    }

    // Incluir archivos necesarios de WP
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    // Descargar el archivo a una ubicación temporal
    $tmp = download_url($avatar_url);
    if (is_wp_error($tmp)) {
        error_log("[CRON_AVATAR_SIMPLE] Error al descargar avatar para User ID {$user_id} desde {$avatar_url}: " . $tmp->get_error_message());
        @unlink($tmp);
        return false;
    }

    // Preparar y guardar la imagen en la Media Library
    $file_array = array();
    preg_match('/[^\/\&\?]+\.\w{3,4}(?=([\?&].*$|$))/i', $avatar_url, $matches);
    $file_name = !empty($matches[0]) ? sanitize_file_name($matches[0]) : sanitize_file_name("avatar_{$user_id}.jpg");
    $file_array['name'] = $file_name;
    $file_array['tmp_name'] = $tmp;
    $desc = sprintf(__('Avatar para %s', CRM_EVOLUTION_SENDER_TEXT_DOMAIN), get_userdata($user_id)->display_name);
    $attachment_id = media_handle_sideload($file_array, 0, $desc); // 0 = no asociado a post
    @unlink($tmp); // Limpiar archivo temporal

    if (is_wp_error($attachment_id)) {
        error_log("[CRON_AVATAR_SIMPLE] Error al guardar avatar para User ID {$user_id} en Media Library: " . $attachment_id->get_error_message());
        return false;
    }

    // Guardar el ID del adjunto en el metadato del usuario
    update_user_meta($user_id, '_crm_avatar_attachment_id', $attachment_id);
    error_log("[CRON_AVATAR_SIMPLE] Avatar guardado con éxito para User ID {$user_id}. Attachment ID: {$attachment_id}");

    return $attachment_id;
}
// Registrar la acción del nuevo Cron simple
add_action('crm_instances_download_avatar', 'crm_instances_download_avatar_callback', 10, 2);


/**
 * AJAX Handler para actualizar la configuración de una instancia de Evolution API.
 * Actualmente enfocado en 'groups_ignore', pero diseñado para ser extensible.
 */
function crm_update_instance_settings_callback() {
    error_log( 'Recibida petición AJAX: crm_update_instance_settings' );

    // 1. Verificar Nonce y Permisos
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ), 403 );
    }

    // 2. Obtener y sanitizar datos
    $instance_name = isset( $_POST['instance_name'] ) ? sanitize_key( $_POST['instance_name'] ) : '';
    
    // Por ahora, solo manejamos 'groups_ignore' explícitamente desde el frontend.
    // Si en el futuro se envían más settings, se podrían recibir como un array.
    $new_groups_ignore_status_raw = isset( $_POST['new_groups_ignore_status'] ) ? $_POST['new_groups_ignore_status'] : null;

    if ( empty( $instance_name ) ) {
        wp_send_json_error( array( 'message' => 'Nombre de instancia no proporcionado.' ), 400 );
    }

    // Si no se envía 'new_groups_ignore_status', no hay nada que hacer para esta implementación específica.
    if ($new_groups_ignore_status_raw === null) {
        wp_send_json_error( array( 'message' => 'No se especificó la configuración a cambiar.' ), 400 );
    }
    
    $new_groups_ignore_status = filter_var( $new_groups_ignore_status_raw, FILTER_VALIDATE_BOOLEAN );

    error_log( "Intentando cambiar configuración para instancia '{$instance_name}'. groups_ignore a: " . ($new_groups_ignore_status ? 'true' : 'false') );

    // 3. Obtener la configuración actual de la instancia
    $all_instances_response = crm_instances_api_request( 'GET', '/instance/fetchInstances' );
    $current_settings = null;

    if ( !is_wp_error( $all_instances_response ) && is_array( $all_instances_response ) ) {
        foreach ( $all_instances_response as $instance_data ) {
            if ( isset( $instance_data['instance']['instanceName'] ) && $instance_data['instance']['instanceName'] === $instance_name ) {
                if ( isset( $instance_data['instance']['settings'] ) && is_array( $instance_data['instance']['settings'] ) ) {
                    $current_settings = $instance_data['instance']['settings'];
                    error_log( "Configuración actual encontrada para '{$instance_name}': " . print_r($current_settings, true) );
                }
                break;
            }
        }
    }

    // Si no se encontraron settings, usar valores por defecto para asegurar que la API reciba un objeto completo.
    if ( $current_settings === null ) {
        error_log( "No se encontró configuración actual para '{$instance_name}'. Usando valores por defecto." );
        // Estos son los valores por defecto que habíamos discutido.
        // La API /settings/set espera el objeto completo.
        $current_settings = array(
            "reject_call"       => true,
            "msg_call"          => "Esta línea no está disponible en este momento.",
            "groups_ignore"     => false, // Este valor se sobrescribirá con el nuevo estado.
            "always_online"     => true,
            "read_messages"     => true,
            "read_status"       => true,
            "sync_full_history" => true
            // Añadir otros settings que la API espere si es necesario.
        );
    }

    // 4. Actualizar el valor específico (groups_ignore en este caso)
    // Si en el futuro recibes un array de settings a actualizar, aquí harías un array_merge o similar.
    $current_settings['groups_ignore'] = $new_groups_ignore_status;

    // 5. Enviar la configuración completa y actualizada a la API
    $set_settings_endpoint = "/settings/set/{$instance_name}";
    $api_response = crm_instances_api_request( 'POST', $set_settings_endpoint, $current_settings );

    if ( is_wp_error( $api_response ) ) {
        error_log( "Error API al actualizar settings para '{$instance_name}': " . $api_response->get_error_message() );
        wp_send_json_error( array( 'message' => 'Error API: ' . $api_response->get_error_message() ) );
    } else {
        // La respuesta de /settings/set puede variar, a veces es solo un mensaje, a veces el objeto de settings.
        // Lo importante es que la llamada fue exitosa (código 2xx).
        error_log( "Configuración actualizada con éxito para '{$instance_name}'. Respuesta API: " . print_r($api_response, true) );
        wp_send_json_success( array( 
            'message' => 'Configuración de instancia actualizada correctamente.', 
            'new_status_groups_ignore' => $new_groups_ignore_status // Devolver el estado aplicado para groups_ignore
        ) );
    }
}
// Cambiamos el nombre de la acción para reflejar que puede actualizar más settings.
add_action( 'wp_ajax_crm_update_instance_settings', 'crm_update_instance_settings_callback' );

?>
