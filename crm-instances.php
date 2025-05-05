<?php
/**
 * Muestra la página principal de gestión de instancias (vista de tarjetas)
 * y contiene los manejadores AJAX específicos para esta página.
 *
 * @package CRM Evolution Sender
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
    die;
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
                <# if ( data.status === 'open' ) { #>
                    <button class="button button-secondary btn-disconnect" title="<?php esc_attr_e( 'Desconectar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                <# } #>
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

    $api_response = crm_evolution_api_request( 'GET', '/instance/fetchInstances' ); // Asume que crm_evolution_api_request está disponible

    if ( is_wp_error( $api_response ) ) {
        error_log( 'Error al obtener instancias de la API (cards): ' . $api_response->get_error_message() );
        wp_send_json_error( array( 'message' => $api_response->get_error_message() ) );
    } elseif ( is_array( $api_response ) ) {
        $instances_data = array();
        foreach ( $api_response as $instance ) {
            $profile_pic_url = isset($instance['instance']['profilePictureUrl']) ? $instance['instance']['profilePictureUrl'] : null;
            $instances_data[] = array(
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

    $api_response = crm_evolution_api_request( 'POST', '/instance/create', $body ); // Asume que crm_evolution_api_request está disponible

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
    $api_response = crm_evolution_api_request( 'GET', $endpoint );
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
    $logout_response = crm_evolution_api_request( 'DELETE', $logout_endpoint ); // Asume que crm_evolution_api_request está disponible
    if ( is_wp_error( $logout_response ) ) {
        error_log( "Aviso (cards): Fallo al desconectar {$instance_name} (puede que ya estuviera desconectada): " . $logout_response->get_error_message() );
    } else {
        error_log( "Instancia {$instance_name} desconectada (o ya lo estaba) (cards)." );
    }

    // Proceder a eliminar (delete)
    error_log( "Intentando eliminar instancia {$instance_name} (cards)..." );
    $delete_endpoint = '/instance/delete/' . $instance_name;
    $delete_response = crm_evolution_api_request( 'DELETE', $delete_endpoint ); // Asume que crm_evolution_api_request está disponible

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
 * y devuelve si hay actualizaciones de estado o QR.
 *
 * @param array $response La respuesta del Heartbeat a modificar.
 * @param array $data     Los datos enviados desde el cliente.
 * @param string $screen_id El ID de la pantalla actual.
 * @return array La respuesta modificada.
 */
function crm_handle_instance_heartbeat_request( $response, $data, $screen_id ) {
    // Solo actuar si estamos en la página de instancias y recibimos el dato esperado
    if ( $screen_id === 'toplevel_page_crm-evolution-sender-main' && isset( $data['crm_waiting_instance'] ) ) {
        $instance_name = sanitize_key( $data['crm_waiting_instance'] );
        error_log( "Heartbeat Instancias: Recibido pulso esperando por '{$instance_name}'.");

        // 1. Comprobar transient de estado
        $status_transient_key = 'crm_instance_status_' . $instance_name;
        $new_status = get_transient( $status_transient_key );
        if ( $new_status !== false ) {
            error_log( "Heartbeat Instancias: Nuevo estado '{$new_status}' encontrado para '{$instance_name}'.");
            $response['crm_instance_status_update'] = ['instance' => $instance_name, 'status' => $new_status];
            delete_transient( $status_transient_key );
        }

        // 2. Comprobar transient de QR
        $qr_transient_key = 'crm_instance_qr_' . $instance_name;
        $new_qr = get_transient( $qr_transient_key );
        if ( $new_qr !== false ) {
            error_log( "Heartbeat Instancias: Nuevo QR encontrado para '{$instance_name}'.");
            $response['crm_instance_qr_update'] = ['instance' => $instance_name, 'qrCode' => $new_qr];
            delete_transient( $qr_transient_key );
        }
    }

    return $response;
}
add_filter( 'heartbeat_received', 'crm_handle_instance_heartbeat_request', 10, 3 );
?>