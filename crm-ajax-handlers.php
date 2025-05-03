<?php
// =========================================================================
// == MANEJADORES AJAX (AJAX HANDLERS) ==
// =========================================================================

/**
 * Función auxiliar para realizar peticiones a la API Evolution.
 *
 * @param string $method Método HTTP (GET, POST, DELETE, etc.).
 * @param string $endpoint El endpoint de la API (ej: '/instance/fetchInstances').
 * @param array $body Datos para enviar en el cuerpo (para POST/PUT).
 * @param string|null $instance_api_key API Key específica de la instancia (opcional).
 * @return array|WP_Error Respuesta decodificada de la API o WP_Error en caso de fallo.
 */
function crm_evolution_api_request( $method, $endpoint, $body = [], $instance_api_key = null ) {
    // crm_log( 'Valor de get_option(crm_evolution_api_url): ' . var_export( get_option( 'crm_evolution_api_url' ), true ), 'DEBUG' );

    $api_url_base = get_option( 'crm_evolution_api_url', '' );
    $global_api_token = get_option( 'crm_evolution_api_token', '' );

    // crm_log( 'Valor de $api_url_base ANTES del if: ' . var_export( $api_url_base, true ), 'DEBUG' );

    if ( empty( $api_url_base ) ) {
        crm_log( 'Error: La URL de la API no está configurada.', 'ERROR' );
        return new WP_Error( 'api_config_error', 'La URL de la API no está configurada en los ajustes.' );
    }

    // Determinar qué API Key usar
    $api_key_to_use = $instance_api_key ? $instance_api_key : $global_api_token;

    if ( empty( $api_key_to_use ) ) {
         crm_log( 'Error: No se encontró API Key (ni global ni específica) para la petición.', 'ERROR' );
        return new WP_Error( 'api_config_error', 'Se requiere una API Key (Global o específica) para realizar la petición.' );
    }

    $request_url = trailingslashit( $api_url_base ) . ltrim( $endpoint, '/' );
    // crm_log( "Realizando petición API: [{$method}] {$request_url}", 'DEBUG' );

    $args = array(
        'method'  => strtoupper( $method ),
        'headers' => array(
            'Content-Type' => 'application/json',
            'apikey'       => $api_key_to_use,
        ),
        'timeout' => 30, // Aumentar timeout si es necesario
        'redirection' => 5,
        'httpversion' => '1.1', // Usar 1.1 explícitamente
        'sslverify' => false, // Cambiar a false SOLO si tienes problemas de SSL y sabes lo que haces
    );

    if ( ! empty( $body ) && ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') ) {
        $args['body'] = wp_json_encode( $body );
         crm_log( "Cuerpo de la petición: " . wp_json_encode( $body ), 'DEBUG' );
    }

    $response = wp_remote_request( $request_url, $args );

    if ( is_wp_error( $response ) ) {
        crm_log( 'Error en wp_remote_request: ' . $response->get_error_message(), 'ERROR' );
        return $response; // Devuelve el WP_Error
    }

    $response_code = wp_remote_retrieve_response_code( $response );
    $response_body = wp_remote_retrieve_body( $response );
    $decoded_body = json_decode( $response_body, true );

    // crm_log( "Respuesta API recibida. Código: {$response_code}", 'DEBUG' );
    // crm_log( "Cuerpo respuesta API: " . $response_body, 'DEBUG' );


    if ( $response_code >= 200 && $response_code < 300 ) {
        // Éxito (códigos 2xx)
        return $decoded_body !== null ? $decoded_body : []; // Devolver array vacío si el JSON es inválido o vacío
    } else {
        // Error (códigos 4xx, 5xx)
        $error_message = 'Error desconocido en la API.';
        if ( $decoded_body && isset( $decoded_body['message'] ) ) {
            $error_message = $decoded_body['message'];
        } elseif ( $decoded_body && isset( $decoded_body['error'] ) ) {
             $error_message = $decoded_body['error'];
        } elseif (!empty($response_body)) {
            $error_message = $response_body; // Usar cuerpo crudo si no hay mensaje específico
        }

        crm_log( "Error en la API ({$response_code}): {$error_message}", 'ERROR' );
        return new WP_Error( 'api_error', $error_message, array( 'status' => $response_code ) );
    }
}


/**
 * AJAX Handler para obtener la lista de instancias desde la API Evolution.
 */
function crm_get_instances_callback() {
    // 1. Verificar Nonce y Permisos
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        crm_log( 'Error AJAX: Permiso denegado para crm_get_instances.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ), 403 );
    }

    // --- INICIO DEBUG DIRECTO ---
    $test_url = get_option( 'crm_evolution_api_url', 'FALLO_GET_OPTION' );
    // crm_log( '[DEBUG DIRECTO] Valor leído por get_option: ' . var_export( $test_url, true ), 'DEBUG' );

    if ( empty( $test_url ) || $test_url === 'FALLO_GET_OPTION' ) {
         crm_log( '[DEBUG DIRECTO] ERROR: La variable $test_url está vacía o no se leyó la opción.', 'ERROR' );
         wp_send_json_error( array( 'message' => 'Error interno leyendo la opción URL (Debug Directo).' ) );
         return; // Salir si hay error
    } else {
        //  crm_log( '[DEBUG DIRECTO] ÉXITO: La variable $test_url contiene: ' . $test_url, 'INFO' );
    }
    // --- FIN DEBUG DIRECTO ---

    // 2. Llamar a la función auxiliar para hacer la petición a la API
    //    El endpoint correcto según la documentación de Evolution API v1.8 es /instance/fetchInstances
    $api_response = crm_evolution_api_request( 'GET', '/instance/fetchInstances' );

    // 3. Procesar la respuesta de la API
    if ( is_wp_error( $api_response ) ) {
        // Si hubo un error en la petición (configuración, conexión, error API)
        crm_log( 'Error al obtener instancias de la API: ' . $api_response->get_error_message(), 'ERROR' );
        wp_send_json_error( array( 'message' => $api_response->get_error_message() ) );

    } elseif ( is_array( $api_response ) ) {

        // crm_log( 'Instancias obtenidas correctamente de la API.', 'INFO' );

        // Mapear los datos si es necesario para que coincidan con lo que espera DataTables
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
        // La API devolvió algo inesperado (no es error ni array)
         crm_log( 'Respuesta inesperada de la API al obtener instancias.', 'ERROR', $api_response );
        wp_send_json_error( array( 'message' => 'Respuesta inesperada de la API.' ) );
    }

    // wp_die() es llamado automáticamente por wp_send_json_success/error
}
add_action( 'wp_ajax_crm_get_instances', 'crm_get_instances_callback' );


/**
 * AJAX Handler para crear una nueva instancia.
 * Recibe datos del formulario Thickbox, incluyendo opciones adicionales y configuración de webhook.
 */
function crm_create_instance_callback() {
    crm_log( 'Recibida petición AJAX: crm_create_instance' );

    // 1. Verificar Nonce específico del formulario y Permisos
    check_ajax_referer( 'crm_create_instance_action', 'crm_create_instance_nonce' ); // Correcto nonce del form
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
    }

    // 2. Sanitizar datos de entrada (incluyendo los nuevos campos)
    $instance_name = isset( $_POST['instance_name'] ) ? sanitize_key( $_POST['instance_name'] ) : '';
    $webhook_url   = isset( $_POST['webhook_url'] ) ? esc_url_raw( wp_unslash( $_POST['webhook_url'] ) ) : ''; // Sanitizar URL


    // 3. Validar datos obligatorios
    if ( empty( $instance_name ) ) {
        wp_send_json_error( array( 'message' => 'El nombre de la instancia es obligatorio.' ) );
    }
    // Validar formato de nombre (opcional, pero bueno)
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $instance_name)) {
        wp_send_json_error( array( 'message' => 'Nombre de instancia inválido. Solo letras, números, guiones y guiones bajos.' ) );
    }


    // 4. Preparar datos para la API Evolution (/instance/create)
    $body = array(
        'instanceName' => $instance_name,
        'qrcode'       => true,
        'sync_full_history' => true,
        'groups_ignore' => true,
        'always_online' => true,
        'webhook' => $webhook_url,
        'webhook_base64' => true,
        'events' => [
            'MESSAGES_UPSERT',
        ]
    );
 
    // 5. Llamar a la API para crear
    $api_response = crm_evolution_api_request( 'POST', '/instance/create', $body );

    // 6. Procesar respuesta
    if ( is_wp_error( $api_response ) ) {
        // Comprobar si el error es específicamente 400 Bad Request
        $error_data = $api_response->get_error_data();
        $status_code = isset($error_data['status']) ? $error_data['status'] : null;
        $error_message = 'Error API: ' . $api_response->get_error_message();
        wp_send_json_error( array( 'message' => $error_message ), $status_code ?: 500 ); // Devolver código de error si está disponible
    } else {
        // Éxito
        $message = isset($api_response['message']) ? $api_response['message'] : 'Instancia creada iniciada.';
        // Intentar obtener el estado de la respuesta, puede variar la estructura
        $status = null;
        if (isset($api_response['instance']) && isset($api_response['instance']['status'])) {
            $status = $api_response['instance']['status'];
        } elseif (isset($api_response['status'])) { // A veces la API lo devuelve en el nivel raíz
             $status = $api_response['status'];
        }

        crm_log( "Instancia {$instance_name} creada. Respuesta API:", 'INFO', $api_response );
        wp_send_json_success( array( 'message' => $message, 'status' => $status ) );
    }
}

// La acción add_action ya está registrada, no necesita cambios.
add_action( 'wp_ajax_crm_create_instance', 'crm_create_instance_callback' ); // La acción ya está registrada


/**
 * AJAX Handler para eliminar una instancia.
 */
function crm_delete_instance_callback() {
     crm_log( 'Recibida petición AJAX: crm_delete_instance' );
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
    }

    $instance_name = isset( $_POST['instance_name'] ) ? sanitize_key( $_POST['instance_name'] ) : '';

    if ( empty( $instance_name ) ) {
        wp_send_json_error( array( 'message' => 'Nombre de instancia no proporcionado.' ) );
    }

    // Llamar a la API para eliminar (Endpoint /instance/delete/{instanceName})
    $endpoint = '/instance/delete/' . $instance_name;
    $api_response = crm_evolution_api_request( 'DELETE', $endpoint );

     if ( is_wp_error( $api_response ) ) {
        // Manejar posible error 404 si la instancia ya no existe como éxito parcial
        if ($api_response->get_error_data() && isset($api_response->get_error_data()['status']) && $api_response->get_error_data()['status'] == 404) {
             crm_log( "Intento de eliminar instancia {$instance_name} que no existe (404). Considerado éxito.", 'WARN' );
             wp_send_json_success( array( 'message' => "La instancia {$instance_name} no se encontró o ya fue eliminada." ) );
        } else {
            wp_send_json_error( array( 'message' => 'Error API: ' . $api_response->get_error_message() ) );
        }
    } else {
        crm_log( "Instancia {$instance_name} eliminada. Respuesta API:", 'INFO', $api_response );
        wp_send_json_success( array( 'message' => "Instancia {$instance_name} eliminada correctamente." ) );
    }
}
add_action( 'wp_ajax_crm_delete_instance', 'crm_delete_instance_callback' );


/**
 * AJAX Handler para obtener el QR Code de una instancia.
 */
function crm_get_instance_qr_callback() {
    // crm_log( 'Recibida petición AJAX: crm_get_instance_qr' );
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
    }

    $instance_name = isset( $_POST['instance_name'] ) ? sanitize_key( $_POST['instance_name'] ) : '';
    if ( empty( $instance_name ) ) {
        wp_send_json_error( array( 'message' => 'Nombre de instancia no proporcionado.' ) );
    }

    // Llamar a la API para conectar y obtener QR (Endpoint /instance/connect/{instanceName})
    // Este endpoint inicia la conexión y devuelve el QR si es necesario.
    $endpoint = '/instance/connect/' . $instance_name;
    $api_response = crm_evolution_api_request( 'GET', $endpoint );

    if ( is_wp_error( $api_response ) ) {
        wp_send_json_error( array( 'message' => 'Error API: ' . $api_response->get_error_message() ) );
    } else {
        // Buscar el QR code en la respuesta (puede estar en 'qrcode.base64' o similar)
        $qr_code_base64 = null;
        if (isset($api_response['qrcode']) && isset($api_response['qrcode']['base64'])) {
            $qr_code_base64 = $api_response['qrcode']['base64'];
        } elseif (isset($api_response['base64'])) { // A veces la API lo devuelve directamente
             $qr_code_base64 = $api_response['base64'];
        }


        if ($qr_code_base64) {
            //  crm_log( "QR Code obtenido para {$instance_name}.", 'INFO' );
             // Asegurarse de que el base64 tenga el prefijo correcto para imagen
             if (strpos($qr_code_base64, 'data:image') === false) {
                 $qr_code_base64 = 'data:image/png;base64,' . $qr_code_base64;
             }
            wp_send_json_success( array( 'qrCode' => $qr_code_base64 ) );
        } else {
            //  crm_log( "No se encontró QR Code en la respuesta para {$instance_name}. Estado puede ser otro.", 'WARN', $api_response );
             $status = isset($api_response['instance']['status']) ? $api_response['instance']['status'] : 'desconocido';
             $message = "No se necesita QR Code. Estado actual: {$status}.";
             if ($status === 'connection') $message = "La instancia ya está conectada.";
            wp_send_json_error( array( 'message' => $message ) );
        }
    }
}
add_action( 'wp_ajax_crm_get_instance_qr', 'crm_get_instance_qr_callback' );


/**
 * AJAX Handler para conectar una instancia (si ya existe y está desconectada).
 */
function crm_connect_instance_callback() {
    // crm_log( 'Recibida petición AJAX: crm_connect_instance' );
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
    }

    $instance_name = isset( $_POST['instance_name'] ) ? sanitize_key( $_POST['instance_name'] ) : '';
    if ( empty( $instance_name ) ) {
        wp_send_json_error( array( 'message' => 'Nombre de instancia no proporcionado.' ) );
    }

    // Endpoint /instance/connect/{instanceName}
    $endpoint = '/instance/connect/' . $instance_name;
    $api_response = crm_evolution_api_request( 'GET', $endpoint );

     if ( is_wp_error( $api_response ) ) {
        wp_send_json_error( array( 'message' => 'Error API: ' . $api_response->get_error_message() ) );
    } else {
        $status = isset($api_response['instance']['status']) ? $api_response['instance']['status'] : null;
        $message = "Intentando conectar instancia {$instance_name}.";
        if ($status === 'qrcode') $message .= " Se requiere escanear QR.";
        if ($status === 'connection') $message = "La instancia {$instance_name} ya está conectada.";

        // crm_log( $message, 'INFO', $api_response );
        wp_send_json_success( array( 'message' => $message, 'status' => $status ) );
    }
}
add_action( 'wp_ajax_crm_connect_instance', 'crm_connect_instance_callback' );


/**
 * AJAX Handler para desconectar una instancia.
 */
function crm_disconnect_instance_callback() {
    // crm_log( 'Recibida petición AJAX: crm_disconnect_instance' );
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
    }

    $instance_name = isset( $_POST['instance_name'] ) ? sanitize_key( $_POST['instance_name'] ) : '';
    if ( empty( $instance_name ) ) {
        wp_send_json_error( array( 'message' => 'Nombre de instancia no proporcionado.' ) );
    }

    // Endpoint /instance/logout/{instanceName}
    $endpoint = '/instance/logout/' . $instance_name;
    $api_response = crm_evolution_api_request( 'DELETE', $endpoint );

     if ( is_wp_error( $api_response ) ) {
         // Podría dar error si ya está desconectada, tratar como éxito?
        //  crm_log( "Error API al desconectar {$instance_name}: " . $api_response->get_error_message(), 'ERROR' );
        wp_send_json_error( array( 'message' => 'Error API: ' . $api_response->get_error_message() ) );
    } else {
        // crm_log( "Instancia {$instance_name} desconectada.", 'INFO', $api_response );
        wp_send_json_success( array( 'message' => "Instancia {$instance_name} desconectada." ) );
    }
}
add_action( 'wp_ajax_crm_disconnect_instance', 'crm_disconnect_instance_callback' );

/**
 * AJAX Handler para obtener instancias activas (conectadas o esperando QR) para selects.
 */
function crm_get_active_instances_callback() {
    // crm_log( 'Recibida petición AJAX: crm_get_active_instances' );
   check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
   if ( ! current_user_can( 'manage_options' ) ) {
       wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
   }

   $api_response = crm_evolution_api_request( 'GET', '/instance/fetchInstances' );

   if ( is_wp_error( $api_response ) ) {
       wp_send_json_error( array( 'message' => 'Error API: ' . $api_response->get_error_message() ) );
   } elseif ( is_array( $api_response ) ) {
       $active_instances = array();
       foreach ( $api_response as $instance ) {
           $status = isset($instance['instance']['status']) ? $instance['instance']['status'] : 'unknown';
           // Considerar activas las que están conectadas o esperando QR
           if ( $status === 'connection' || $status === 'qrcode' || $status === 'connecting' ) { // Ajusta según estados reales
                $active_instances[] = array(
                   'instance_name' => isset($instance['instance']['instanceName']) ? $instance['instance']['instanceName'] : 'N/D',
                   'status'        => $status,
               );
           }
       }
        // crm_log( 'Instancias activas obtenidas: ' . count($active_instances), 'INFO' );
       wp_send_json_success( $active_instances );
   } else {
       wp_send_json_error( array( 'message' => 'Respuesta inesperada de la API.' ) );
   }
}
add_action( 'wp_ajax_crm_get_active_instances', 'crm_get_active_instances_callback' );

// =========================================================================
// == MANEJADORES AJAX - USUARIOS ==
// =========================================================================

/**
 * AJAX Handler para obtener la lista de usuarios de WordPress.
 */
function crm_get_wp_users_callback() {
    // crm_log( 'Recibida petición AJAX: crm_get_wp_users' );
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) { // O una capacidad más específica si la creas
        wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
    }

    $users_data = array();
    // Argumentos para obtener usuarios. Podrías añadir paginación aquí si usas serverSide=true en JS.
    $args = array(
        'orderby' => 'ID',
        'order'   => 'ASC',
        // 'number' => 100, // Limitar si hay muchos usuarios y no usas server-side
        // 'paged' => $page_number, // Para paginación server-side
    );
    $wp_users = get_users( $args );

    foreach ( $wp_users as $user ) {
        // Obtener el teléfono (ajusta 'billing_phone' si usas otra meta key)
        $phone = get_user_meta( $user->ID, 'billing_phone', true );
        $etiqueta = get_user_meta( $user->ID, 'crm_lifecycle_tag', true );

        
        if (empty($phone)) {
            $phone = get_user_meta( $user->ID, 'phone', true ); // Intentar con otra meta key común
        }

        $users_data[] = array(
            'id'           => $user->ID,
            'user_login'   => $user->user_login,
            'display_name' => $user->display_name,
            'user_email'   => $user->user_email,
            'roles'        => ! empty( $user->roles ) ? implode( ', ', $user->roles ) : 'N/A',
            'phone'        => ! empty( $phone ) ? $phone : null, // Enviar null si no hay teléfono
            'etiqueta'     => ! empty( $etiqueta ) ? $etiqueta : null, // Enviar null si no hay etiqueta

            
        );
    }

    // crm_log( 'Usuarios WP obtenidos para AJAX: ' . count($users_data) . ' encontrados.', 'INFO' );
    wp_send_json_success( $users_data );
}
add_action( 'wp_ajax_crm_get_wp_users', 'crm_get_wp_users_callback' );

function crm_add_wp_user_callback() {
    // crm_log( 'Recibida petición AJAX: crm_add_wp_user' );

    // 1. Verificar Nonce y Permisos (¡MUY IMPORTANTE!)
    // Usa el nonce general 'crm_evolution_sender_nonce' que se pasa en crm_evolution_sender_params
    check_ajax_referer('crm_evolution_sender_nonce', '_ajax_nonce');
    if (!current_user_can('create_users')) { // O 'manage_options' o el capability adecuado
        // crm_log( 'Error AJAX: Permiso denegado para crm_add_wp_user.', 'ERROR' );
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción.'], 403);
        return;
    }

    // 2. Recoger y Sanitizar Datos del POST
    $email = isset($_POST['user_email']) ? sanitize_email($_POST['user_email']) : '';
    $first_name = isset($_POST['user_first_name']) ? sanitize_text_field($_POST['user_first_name']) : '';
    $last_name = isset($_POST['user_last_name']) ? sanitize_text_field($_POST['user_last_name']) : '';
    $role = isset($_POST['user_role']) ? sanitize_key($_POST['user_role']) : get_option('default_role');
    $etiqueta = isset($_POST['user_etiqueta']) ? sanitize_key($_POST['user_etiqueta']) : '';

    // *** INICIO: Recoger y sanitizar el número de teléfono internacional ***
    // Recibe el campo 'user_phone_full' enviado desde app.js
    // Sanitiza eliminando cualquier cosa que no sea dígito o el signo '+' inicial
    $full_phone = isset($_POST['user_phone_full']) ? sanitize_text_field( preg_replace('/[^+0-9]/', '', $_POST['user_phone_full']) ) : '';
    // crm_log( 'Teléfono internacional recibido y sanitizado: ' . $full_phone, 'DEBUG' );
    // *** FIN: Recoger y sanitizar el número de teléfono internacional ***

    // 3. Validar Datos (Ejemplos)
    if (empty($email) || !is_email($email)) {
        // crm_log( 'Error validación: Correo inválido o vacío.', 'WARN' );
        wp_send_json_error(['message' => 'Correo electrónico inválido o vacío.']);
        return; // Salir si hay error
    }
    if (username_exists($email) || email_exists($email)) {
        //  crm_log( 'Error validación: Correo ya existe.', 'WARN' );
        wp_send_json_error(['message' => 'El correo electrónico ya está registrado.']);
        return;
    }
    // *** INICIO: Validar que el teléfono no esté vacío (ya que es requerido en el frontend) ***
    if (empty($full_phone)) {
        // crm_log( 'Error validación: Teléfono vacío.', 'WARN' );
        wp_send_json_error(['message' => 'El número de teléfono es obligatorio.']);
        return;
    }
    // Opcional: Validación más estricta del formato E.164 (ej: empieza con +, seguido de dígitos)
    // if (!preg_match('/^\+[1-9]\d{6,14}$/', $full_phone)) {
    //     crm_log( 'Error validación: Formato de teléfono inválido.', 'WARN' );
    //     wp_send_json_error(['message' => 'El formato del número de teléfono no parece válido.']);
    //     return;
    // }
    // *** FIN: Validar que el teléfono no esté vacío ***

    // 4. Crear el Usuario
    $user_data = array(
        'user_login'   => $email, // Usar email como login es común
        'user_email'   => $email,
        'user_pass'    => wp_generate_password(), // Generar contraseña segura
        'first_name'   => $first_name,
        'last_name'    => $last_name,
        'display_name' => trim($first_name . ' ' . $last_name),
        'role'         => $role,
    );

    crm_log( 'Preparando datos para wp_insert_user:', 'DEBUG', $user_data );
    $user_id = wp_insert_user($user_data);

    // 5. Manejar Resultado de la Creación
    if (is_wp_error($user_id)) {
        crm_log( 'Error al crear usuario con wp_insert_user: ' . $user_id->get_error_message(), 'ERROR' );
        wp_send_json_error(['message' => 'Error al crear usuario: ' . $user_id->get_error_message()]);
    } else {
        crm_log( 'Usuario creado con éxito. ID: ' . $user_id, 'INFO' );
        // 6. Guardar Metadatos Adicionales (¡Usando el teléfono internacional!)
        // *** INICIO: Usar $full_phone para guardar el metadato ***
        // 'billing_phone' es la meta_key estándar usada por WooCommerce y muchos otros plugins.
        // Si no usas WooCommerce, puedes usar otra key como 'phone_number', pero 'billing_phone' es más compatible.
        update_user_meta($user_id, 'billing_phone', $full_phone);
        crm_log( 'Metadato billing_phone guardado para user ID ' . $user_id . ': ' . $full_phone, 'INFO' );
        // *** FIN: Usar $full_phone para guardar el metadato ***

        if (!empty($etiqueta)) {
            update_user_meta( $user_id, 'crm_lifecycle_tag', $etiqueta ); // Guarda la etiqueta si se seleccionó
            crm_log( 'Metadato crm_lifecycle_tag guardado para user ID ' . $user_id . ': ' . $etiqueta, 'INFO' );
        }

        // Opcional: Enviar notificación al nuevo usuario
        // wp_new_user_notification($user_id, null, 'both');

        // 7. Enviar Respuesta de Éxito
        wp_send_json_success(['message' => 'Usuario añadido correctamente.', 'user_id' => $user_id]);
    }
}
// Asegúrate de que el hook sigue existiendo (ya estaba en tu archivo)
add_action('wp_ajax_crm_add_wp_user', 'crm_add_wp_user_callback');

/**
 * AJAX Handler para obtener las etiquetas para el modal de usuario.
 */
function crm_get_etiquetas_for_modal_callback() {
    // crm_log( 'Recibida petición AJAX: crm_get_etiquetas_for_modal' ); // Descomentar para debug si es necesario

    // 1. Verificar Nonce y Permisos    
    // Usamos el nonce general 'crm_evolution_sender_nonce' que se pasa en crm_evolution_sender_params
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) { // O la capacidad que uses para gestionar usuarios/etiquetas
        crm_log( 'Error AJAX: Permiso denegado para crm_get_etiquetas_for_modal.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
    }

    // 2. Obtener las etiquetas
    // Esta función crm_get_lifecycle_tags() está definida en crm-setting.php
    // y debería estar disponible porque crm-setting.php se incluye en el archivo principal del plugin.
    $tags = crm_get_lifecycle_tags();

    // 3. Formatear la respuesta como un array de objetos [{key: '...', name: '...'}]
    //    Esto coincide con cómo lo procesa el JavaScript en app.js
    $formatted_tags = [];
    if ( is_array($tags) && !empty($tags) ) {
        foreach ($tags as $key => $name) {
            $formatted_tags[] = [
                'key' => $key,
                'name' => $name // El JS se encargará de escapar HTML si es necesario al mostrar
            ];
        }
        // crm_log( 'Etiquetas formateadas para enviar a AJAX:', 'DEBUG', $formatted_tags );
    } else {
        crm_log( 'No se encontraron etiquetas o crm_get_lifecycle_tags() no devolvió un array.', 'WARN' );
    }


    // 4. Enviar respuesta JSON
    wp_send_json_success( $formatted_tags );

    // wp_die() es llamado automáticamente por wp_send_json_success/error
}
// Registrar la acción AJAX para que WordPress la reconozca
add_action( 'wp_ajax_crm_get_etiquetas_for_modal', 'crm_get_etiquetas_for_modal_callback' );


// =========================================================================
// == MANEJADORES AJAX - MARKETING (CAMPAÑAS) ==
// =========================================================================

/**
 * AJAX Handler para obtener la lista de campañas (posts tipo 'crm_sender_campaign').
 * Lee desde wp_posts y wp_postmeta.
 */
function crm_get_campaigns_callback() {
    crm_log( 'Recibida petición AJAX: crm_get_campaigns (usando Posts)' );

    // 1. Verificar Nonce y Permisos
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        crm_log( 'Error AJAX: Permiso denegado para crm_get_campaigns.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ), 403 );
    }

    // 2. Argumentos para WP_Query
    $args = array(
        'post_type'      => 'crm_sender_campaign', // <-- ¡CAMBIO IMPORTANTE! Usar nuestro post_type no registrado
        'post_status'    => array('publish', 'draft', 'pending'), // Incluir diferentes estados si es necesario
        'posts_per_page' => -1, // Obtener todas las campañas
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    $campaigns_query = new WP_Query( $args );
    $campaigns_data = array();

    // 3. Procesar los resultados de WP_Query
    if ( $campaigns_query->have_posts() ) {
        crm_log( "Encontradas {$campaigns_query->post_count} campañas (Posts) del tipo: {$args['post_type']}" );
        while ( $campaigns_query->have_posts() ) {
            $campaigns_query->the_post();
            $post_id = get_the_ID();

            // Obtener los metadatos usando las claves correctas
            $instance_name    = get_post_meta( $post_id, '_crm_instance_name', true );
            $target_tag       = get_post_meta( $post_id, '_crm_target_tag', true );
            $interval_minutes = get_post_meta( $post_id, '_crm_interval_minutes', true );
            $media_url        = get_post_meta( $post_id, '_crm_media_url', true );
            $message          = get_post_meta( $post_id, '_crm_message_content', true );
            // Podríamos obtener un estado personalizado si lo guardamos en meta
            // $custom_status = get_post_meta( $post_id, '_crm_campaign_status', true );

            $campaigns_data[] = array(
                // Nombres de clave que coincidan con 'data' en DataTables (app.js)
                'id'               => $post_id, // Enviar el ID para botones de acción
                'name'             => get_the_title(), // El título del post
                'instance_name'    => $instance_name ?: 'N/D', // Usar operador ternario corto para default
                'message'          => $message ?: '',
                'media_url'        => $media_url ?: '',
                'target_tag'       => $target_tag ?: 'N/A',
                'interval_minutes' => $interval_minutes ?: 'N/A',
                'status'           => get_post_status(), // Estado del post ('publish', 'draft', etc.)
                // 'custom_status' => $custom_status ?: '', // Si tuviéramos estado personalizado
                // Añadir más datos si son necesarios para la tabla
            );
        }
        wp_reset_postdata(); // Restaurar datos originales del post global
        wp_send_json_success( $campaigns_data );

    } else {
        crm_log( "No se encontraron campañas (Posts) del tipo: {$args['post_type']}" );
        // Enviar éxito con array vacío si no hay campañas, DataTables lo maneja bien
        wp_send_json_success( array() );
    }
}
// La acción add_action ya existe, no se necesita modificar.
add_action( 'wp_ajax_crm_get_campaigns', 'crm_get_campaigns_callback' );


/**
 * AJAX Handler para obtener la lista de instancias ACTIVAS para el SELECT del modal de campañas.
 * Devuelve un array de nombres de instancia (strings).
 */
function crm_get_active_instances_for_select_callback() {
    crm_log( 'Recibida petición AJAX: crm_get_active_instances_for_select' );
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
    }

    try {
        // Llama a tu función API para obtener todas las instancias
        $api_response = crm_evolution_api_request( 'GET', '/instance/fetchInstances' );

        $activeInstanceNames = []; // Array para guardar solo los nombres

        if ( is_wp_error( $api_response ) ) {
             wp_send_json_error( array( 'message' => 'Error API: ' . $api_response->get_error_message() ) );
             return;
        }

        if ( is_array( $api_response ) ) {
            foreach ( $api_response as $instance ) {
                // Ajusta la condición según los estados que consideres "activos" para enviar
                $status = isset($instance['instance']['status']) ? $instance['instance']['status'] : 'unknown';
                // Estados activos para poder enviar campañas (ajusta si es necesario)
                if ( ($status === 'open' || $status === 'connected' || $status === 'connection') ) {
                     if (isset($instance['instance']['instanceName'])) {
                        $activeInstanceNames[] = $instance['instance']['instanceName']; // Añadir SOLO el nombre
                     }
                }
            }
        }

        if ( empty( $activeInstanceNames ) ) {
            crm_log( 'No se encontraron instancias activas para el select.', 'INFO' );
        } else {
             crm_log( 'Instancias activas (nombres) encontradas para select: ' . implode(', ', $activeInstanceNames), 'INFO' );
        }
        wp_send_json_success( $activeInstanceNames ); // Enviar el array de NOMBRES

    } catch ( Exception $e ) {
        crm_log( 'Error al obtener estados de instancias para select: ' . $e->getMessage(), 'ERROR' );
        wp_send_json_error( array( 'message' => 'Error al obtener datos de instancias.' ) );
    }
}
add_action( 'wp_ajax_crm_get_active_instances_for_select', 'crm_get_active_instances_for_select_callback' );


/**
 * AJAX Handler para obtener las etiquetas de ciclo de vida para el SELECT del modal de campañas.
 * Devuelve un array de objetos { value: slug, text: Nombre }.
 */
function crm_get_etiquetas_for_select_callback() {
    crm_log( 'Recibida petición AJAX: crm_get_etiquetas_for_select' );
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
    }

    // Obtener las etiquetas usando tu función existente
    $defined_tags = crm_get_lifecycle_tags();

    $formatted_tags = [];
    if ( is_array( $defined_tags ) ) {
        foreach ( $defined_tags as $slug => $name ) {
            $formatted_tags[] = array(
                'value' => $slug, // Clave 'value' para el JS
                'text'  => $name  // Clave 'text' para el JS
            );
        }
    }
    crm_log( 'Etiquetas formateadas para modal select: ' . var_export($formatted_tags, true), 'DEBUG' );

    // crm_log( 'Etiquetas formateadas para modal select: ', 'DEBUG', $formatted_tags );
    wp_send_json_success( $formatted_tags );
}
add_action( 'wp_ajax_crm_get_etiquetas_for_select', 'crm_get_etiquetas_for_select_callback' );

/**
 * AJAX Handler para guardar (crear o actualizar) una campaña de marketing.
 * Guarda los datos directamente en wp_posts y wp_postmeta usando un post_type no registrado.
 */
function crm_ajax_save_campaign_callback() {
    crm_log( 'Recibida petición AJAX: crm_save_campaign' );

    // 1. Verificar Nonce y Permisos
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) { // O una capacidad más específica si la creas
        crm_log( 'Error AJAX: Permiso denegado para crm_save_campaign.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ), 403 );
    }

    // 2. Recoger y Sanitizar Datos del POST
    $campaign_id       = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0; // ID para saber si es actualización
    $campaign_name     = isset( $_POST['campaign_name'] ) ? sanitize_text_field( wp_unslash( $_POST['campaign_name'] ) ) : '';
    $instance_name   = isset( $_POST['campaign_instance'] ) ? sanitize_key( $_POST['campaign_instance'] ) : '';
    $target_tag      = isset( $_POST['campaign_target_tag'] ) ? sanitize_key( $_POST['campaign_target_tag'] ) : '';
    $interval_minutes = isset( $_POST['campaign_interval'] ) ? absint( $_POST['campaign_interval'] ) : 5; // Valor por defecto 5 min
    $media_url       = isset( $_POST['campaign_media_url'] ) ? esc_url_raw( wp_unslash( $_POST['campaign_media_url'] ) ) : '';
    $message         = isset( $_POST['campaign_message'] ) ? wp_kses_post( wp_unslash( $_POST['campaign_message'] ) ) : ''; // Permite algo de HTML seguro

    // 3. Validar Datos Obligatorios
    if ( empty( $campaign_name ) ) {
        wp_send_json_error( array( 'message' => 'El nombre de la campaña es obligatorio.' ) );
    }
    if ( empty( $instance_name ) ) {
        wp_send_json_error( array( 'message' => 'Debes seleccionar una instancia API.' ) );
    }
    if ( empty( $target_tag ) ) {
        wp_send_json_error( array( 'message' => 'Debes seleccionar una etiqueta de destinatarios.' ) );
    }
    if ( empty( $message ) && empty( $media_url ) ) {
        wp_send_json_error( array( 'message' => 'Debes proporcionar al menos un mensaje o un archivo multimedia.' ) );
    }

    // 4. Definir Post Type y Meta Keys (usaremos un prefijo para evitar colisiones)
    $post_type = 'crm_sender_campaign'; // Nuestro post_type personalizado NO REGISTRADO
    $meta_keys = array(
        '_crm_instance_name'    => $instance_name,
        '_crm_target_tag'       => $target_tag,
        '_crm_interval_minutes' => $interval_minutes,
        '_crm_media_url'        => $media_url,
        '_crm_message_content'  => $message,
        // Podríamos añadir un meta para el estado si quisiéramos ('pending', 'sending', 'completed', 'paused')
        // '_crm_campaign_status' => 'pending' // Estado inicial
    );

    // 5. Preparar datos para wp_insert_post / wp_update_post
    $post_data = array(
        'post_title'  => $campaign_name,
        'post_type'   => $post_type,
        'post_status' => 'publish', // Guardar como publicado directamente
        // 'post_content' => $message, // Alternativa: guardar mensaje aquí en lugar de meta. Decidimos usar meta.
    );

    // 6. Determinar si es Crear o Actualizar
    if ( $campaign_id > 0 ) {
        // --- ACTUALIZAR CAMPAÑA EXISTENTE ---
        crm_log( "Intentando actualizar campaña (Post ID: {$campaign_id})", 'INFO' );
        $post_data['ID'] = $campaign_id; // Añadir ID para actualizar

        $updated_post_id = wp_update_post( $post_data, true ); // El segundo parámetro a true devuelve WP_Error si falla

        if ( is_wp_error( $updated_post_id ) ) {
            crm_log( "Error al actualizar post {$campaign_id}: " . $updated_post_id->get_error_message(), 'ERROR' );
            wp_send_json_error( array( 'message' => 'Error al actualizar la campaña: ' . $updated_post_id->get_error_message() ) );
        } else {
            // Actualizar todos los meta fields
            foreach ( $meta_keys as $key => $value ) {
                update_post_meta( $campaign_id, $key, $value );
            }
            crm_log( "Campaña actualizada con éxito (Post ID: {$campaign_id})", 'INFO' );
            wp_send_json_success( array( 'message' => 'Campaña actualizada correctamente.', 'campaign_id' => $campaign_id ) );
        }

    } else {
        // --- CREAR NUEVA CAMPAÑA ---
        crm_log( "Intentando crear nueva campaña con post_type: {$post_type}", 'INFO' );

        $new_post_id = wp_insert_post( $post_data, true ); // El segundo parámetro a true devuelve WP_Error si falla

        if ( is_wp_error( $new_post_id ) ) {
            crm_log( 'Error al insertar post: ' . $new_post_id->get_error_message(), 'ERROR' );
            wp_send_json_error( array( 'message' => 'Error al crear la campaña: ' . $new_post_id->get_error_message() ) );
        } elseif ( $new_post_id === 0 ) {
             crm_log( 'Error desconocido al insertar post (wp_insert_post devolvió 0).', 'ERROR' );
             wp_send_json_error( array( 'message' => 'Error desconocido al crear la campaña.' ) );
        } else {
            // Guardar todos los meta fields para el nuevo post
            foreach ( $meta_keys as $key => $value ) {
                add_post_meta( $new_post_id, $key, $value, true ); // El último true asegura que sea único si es la primera vez
            }
            crm_log( "Nueva campaña creada con éxito (Post ID: {$new_post_id})", 'INFO' );
            wp_send_json_success( array( 'message' => 'Campaña creada correctamente.', 'campaign_id' => $new_post_id ) );
        }
    }
}
add_action( 'wp_ajax_crm_save_campaign', 'crm_ajax_save_campaign_callback' );

// =========================================================================
// == MANEJADORES AJAX - HISTORIAL DE CHATS ==
// =========================================================================

/**
 * AJAX Handler para obtener la lista de conversaciones recientes para la interfaz de chat.
 */
function crm_get_recent_conversations_ajax() {
    crm_log( 'Recibida petición AJAX: crm_get_recent_conversations' );

    // 1. Verificar Nonce y Permisos
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    // Usar 'edit_posts' ya que es la capacidad que dimos al menú de chats
    if ( ! current_user_can( 'edit_posts' ) ) {
        crm_log( 'Error AJAX: Permiso denegado para crm_get_recent_conversations.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ), 403 );
    }

    // 2. Obtener los últimos mensajes de chat
    $args = array(
        'post_type'      => 'crm_chat',
        'posts_per_page' => 200, // Limitar inicialmente para rendimiento, ajustar si es necesario
        'orderby'        => 'date', // Ordenar por fecha de creación del post (que se basa en timestamp_wa)
        'order'          => 'DESC',
        'meta_query'     => array(
            // Asegurarnos de que solo consideramos chats asociados a un usuario WP
            // Opcional: podríamos manejar chats sin user_id (grupos?) más adelante
            array(
                'key'     => '_crm_contact_user_id',
                'value'   => 0,
                'compare' => '>', // Solo IDs mayores que 0
                'type'    => 'NUMERIC',
            ),
        ),
    );

    $query = new WP_Query( $args );

    $conversations = array();
    $processed_user_ids = array();

    // 3. Procesar los mensajes para obtener la última entrada de cada conversación
    if ( $query->have_posts() ) {
        crm_log( "Procesando {$query->post_count} mensajes recientes para obtener conversaciones." );
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();
            $contact_user_id = get_post_meta( $post_id, '_crm_contact_user_id', true );

            // Si es un ID válido y no hemos procesado ya esta conversación
            if ( $contact_user_id > 0 && ! in_array( $contact_user_id, $processed_user_ids ) ) {
                $processed_user_ids[] = $contact_user_id; // Marcar como procesado

                $user_data = get_userdata( $contact_user_id );
                if ( $user_data ) {
                    $last_message_text = get_the_content();
                    $media_caption = get_post_meta( $post_id, '_crm_media_caption', true );
                    $message_type = get_post_meta( $post_id, '_crm_message_type', true );
                    $instance_name = get_post_meta( $post_id, '_crm_instance_name', true ); // <-- Obtener nombre de instancia

                    // Snippet: Usar caption si es media, sino el texto. Añadir prefijo si es media.
                    $snippet = '';
                    if ( in_array($message_type, ['image', 'video', 'audio', 'document', 'file']) ) {
                        $snippet = '[' . ucfirst($message_type) . '] '; // Ej: [Image]
                        $snippet .= $media_caption ?: '';
                    } else {
                        $snippet = $last_message_text;
                    }

                    $conversations[] = array(
                        'user_id'      => $contact_user_id,
                        'display_name' => $user_data->display_name,
                        'avatar_url'   => get_avatar_url( $contact_user_id, ['size' => 96] ), // Obtener URL del avatar
                        'last_message_snippet' => wp_trim_words( $snippet, 10, '...' ), // Cortar snippet largo
                        'last_message_timestamp' => (int) get_post_meta( $post_id, '_crm_timestamp_wa', true ),
                        'instance_name' => $instance_name ?: 'N/D', // <-- Añadir al array de respuesta
                    );
                }
            }
        }
        wp_reset_postdata();
    }

    crm_log( "Enviando " . count($conversations) . " conversaciones únicas al frontend.", 'INFO' );
    wp_send_json_success( $conversations );
}
add_action( 'wp_ajax_crm_get_recent_conversations', 'crm_get_recent_conversations_ajax' );

/**
 * AJAX Handler para obtener los mensajes de una conversación específica.
 */
function crm_get_conversation_messages_ajax() {
    crm_log( 'Recibida petición AJAX: crm_get_conversation_messages' );

    // 1. Verificar Nonce y Permisos
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) {
        crm_log( 'Error AJAX: Permiso denegado para crm_get_conversation_messages.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ), 403 );
    }

    // 2. Obtener y validar User ID
    $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
    if ( $user_id <= 0 ) {
        crm_log( 'Error AJAX: User ID inválido o no proporcionado para crm_get_conversation_messages.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'ID de usuario inválido.' ), 400 );
    }

    crm_log( "Obteniendo mensajes para User ID: {$user_id}" );

    // 3. Obtener los mensajes de chat para ese usuario
    $args = array(
        'post_type'      => 'crm_chat',
        'posts_per_page' => -1, // Obtener todos los mensajes de esta conversación
        'orderby'        => 'meta_value_num', // Ordenar por el timestamp numérico
        'meta_key'       => '_crm_timestamp_wa', // La clave por la que ordenar
        'order'          => 'ASC', // Orden ascendente (más antiguo primero)
        'meta_query'     => array(
            array(
                'key'     => '_crm_contact_user_id',
                'value'   => $user_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ),
        ),
    );

    $query = new WP_Query( $args );
    $messages = array();

    // 4. Procesar los mensajes
    if ( $query->have_posts() ) {
        crm_log( "Procesando {$query->post_count} mensajes para User ID: {$user_id}." );
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();
            $attachment_id = get_post_meta( $post_id, '_crm_media_attachment_id', true );

            $messages[] = array(
                'id'            => $post_id,
                'text'          => get_the_content(), // Contenido principal (texto o caption si se guardó ahí)
                'timestamp'     => (int) get_post_meta( $post_id, '_crm_timestamp_wa', true ),
                'is_outgoing'   => (bool) get_post_meta( $post_id, '_crm_is_outgoing', true ),
                'type'          => get_post_meta( $post_id, '_crm_message_type', true ),
                'caption'       => get_post_meta( $post_id, '_crm_media_caption', true ), // Caption específico
                'attachment_id' => $attachment_id ? (int) $attachment_id : null,
                'attachment_url'=> $attachment_id ? wp_get_attachment_url( $attachment_id ) : null, // URL del adjunto
            );
        }
        wp_reset_postdata();
    }

    crm_log( "Enviando " . count($messages) . " mensajes para User ID: {$user_id} al frontend.", 'INFO' );
    wp_send_json_success( $messages );
}
add_action( 'wp_ajax_crm_get_conversation_messages', 'crm_get_conversation_messages_ajax' );

// =========================================================================
// == API HEARTBEAT PARA ACTUALIZACIONES DE CHAT ==
// =========================================================================

/**
 * Procesa los datos enviados por el Heartbeat y devuelve si hay nuevos mensajes.
 *
 * @param array $response La respuesta del Heartbeat a modificar.
 * @param array $data     Los datos enviados desde el cliente.
 * @param string $screen_id El ID de la pantalla actual.
 * @return array La respuesta modificada.
 */
function crm_handle_heartbeat_request( $response, $data, $screen_id ) {
    // Solo actuar si estamos en la página correcta
    if ( strpos( $screen_id, 'crm-evolution-chat-history' ) === false ) {
        return $response; // Salir si no es la página de chat
    }
    crm_log( "Heartbeat: Recibido pulso en pantalla '{$screen_id}'. Datos recibidos:", 'DEBUG', $data ); // Log inicial

    $needs_list_refresh = false;
    $new_messages_for_open_chat = [];

    // --- 1. Comprobar si hay mensajes nuevos para el chat ABIERTO ---
    if ( isset( $data['crm_current_open_chat_id'], $data['crm_last_message_timestamp'] ) ) {
        $open_chat_user_id = absint( $data['crm_current_open_chat_id'] );
        $last_message_timestamp = absint( $data['crm_last_message_timestamp'] );

        if ( $open_chat_user_id > 0 ) {
            // crm_log( "Heartbeat: Chat abierto User ID: {$open_chat_user_id}. Buscando mensajes desde: {$last_message_timestamp}", 'DEBUG' );
            crm_log( "Heartbeat: Chat abierto User ID: {$open_chat_user_id}. Buscando mensajes con timestamp > {$last_message_timestamp}", 'DEBUG' );

            $args_open_chat = array(
                'post_type'      => 'crm_chat',
                'posts_per_page' => 50, // Limitar por si llegan muchos de golpe
                'orderby'        => 'meta_value_num',
                'meta_key'       => '_crm_timestamp_wa',
                'order'          => 'ASC', // Más antiguo primero para añadirlos en orden
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'     => '_crm_contact_user_id',
                        'value'   => $open_chat_user_id,
                        'compare' => '=',
                        'type'    => 'NUMERIC',
                    ),
                    array(
                        'key'     => '_crm_timestamp_wa',
                        'value'   => $last_message_timestamp,
                        'compare' => '>', // Más reciente que el último mostrado
                        'type'    => 'NUMERIC',
                    ),
                ),
            );
            $query_open_chat = new WP_Query( $args_open_chat );

            if ( $query_open_chat->have_posts() ) {
                crm_log( "Heartbeat: {$query_open_chat->post_count} mensajes nuevos encontrados para el chat abierto (User ID: {$open_chat_user_id}).", 'INFO' );
                $needs_list_refresh = true; // Si hay mensajes nuevos en el chat abierto, la lista también necesita refrescarse
                while ( $query_open_chat->have_posts() ) {
                    $query_open_chat->the_post();
                    $post_id = get_the_ID();
                    $attachment_id = get_post_meta( $post_id, '_crm_media_attachment_id', true );
                    // Formatear igual que en crm_get_conversation_messages_ajax
                    $new_messages_for_open_chat[] = array(
                        'id'            => $post_id,
                        'text'          => get_the_content(),
                        'timestamp'     => (int) get_post_meta( $post_id, '_crm_timestamp_wa', true ),
                        'is_outgoing'   => (bool) get_post_meta( $post_id, '_crm_is_outgoing', true ),
                        'type'          => get_post_meta( $post_id, '_crm_message_type', true ),
                        'caption'       => get_post_meta( $post_id, '_crm_media_caption', true ),
                        'attachment_id' => $attachment_id ? (int) $attachment_id : null,
                        'attachment_url'=> $attachment_id ? wp_get_attachment_url( $attachment_id ) : null,
                    );
                }
                wp_reset_postdata();
                $response['crm_new_messages_for_open_chat'] = $new_messages_for_open_chat;
            }
        }
    }

    // --- 2. Comprobar si hay CUALQUIER mensaje nuevo desde el último chequeo GENERAL ---
    //    (Hacer esto incluso si ya encontramos mensajes para el chat abierto, para asegurar que el flag general se ponga)
    if ( isset( $data['crm_last_chat_check'] ) && !$needs_list_refresh ) { // Solo si no lo marcamos ya
        $last_check_timestamp = absint( $data['crm_last_chat_check'] );
        crm_log( "Heartbeat: Chequeo general. Buscando mensajes desde: {$last_check_timestamp}", 'DEBUG' );
        $args_general = array(
            'post_type'      => 'crm_chat',
            'posts_per_page' => 1,
            'orderby'        => 'meta_value_num',
            'meta_key'       => '_crm_timestamp_wa',
            'order'          => 'DESC',
            'meta_query'     => array(
                array(
                    'key'     => '_crm_timestamp_wa',
                    'value'   => $last_check_timestamp,
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                ),
            ),
            'fields' => 'ids', // Solo necesitamos los IDs
        );
        $query_general = new WP_Query( $args_general );

        if ( $query_general->have_posts() ) {
            crm_log( "Heartbeat: Detectados mensajes nuevos generales desde {$last_check_timestamp}.", 'INFO' );
            $needs_list_refresh = true;
        }
    }

    // --- 3. Añadir el flag de refresco general si es necesario ---
    if ($needs_list_refresh) {
        $response['crm_needs_list_refresh'] = true;
    }

    return $response;
}
add_filter( 'heartbeat_received', 'crm_handle_heartbeat_request', 10, 3 );
add_filter( 'heartbeat_nopriv_received', 'crm_handle_heartbeat_request', 10, 3 ); // Opcional: si quieres que funcione para usuarios no logueados (improbable en admin)
