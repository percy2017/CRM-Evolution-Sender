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
    // //crm_log( 'Valor de get_option(crm_evolution_api_url): ' . var_export( get_option( 'crm_evolution_api_url' ), true ), 'DEBUG' );

    $api_url_base = get_option( 'crm_evolution_api_url', '' );
    $global_api_token = get_option( 'crm_evolution_api_token', '' );

    // //crm_log( 'Valor de $api_url_base ANTES del if: ' . var_export( $api_url_base, true ), 'DEBUG' );

    if ( empty( $api_url_base ) ) {
        //crm_log( 'Error: La URL de la API no está configurada.', 'ERROR' );
        return new WP_Error( 'api_config_error', 'La URL de la API no está configurada en los ajustes.' );
    }

    // Determinar qué API Key usar
    $api_key_to_use = $instance_api_key ? $instance_api_key : $global_api_token;

    if ( empty( $api_key_to_use ) ) {
         //crm_log( 'Error: No se encontró API Key (ni global ni específica) para la petición.', 'ERROR' );
        return new WP_Error( 'api_config_error', 'Se requiere una API Key (Global o específica) para realizar la petición.' );
    }

    $request_url = trailingslashit( $api_url_base ) . ltrim( $endpoint, '/' );
    // //crm_log( "Realizando petición API: [{$method}] {$request_url}", 'DEBUG' );

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
         //crm_log( "Cuerpo de la petición: " . wp_json_encode( $body ), 'DEBUG' );
    }

    $response = wp_remote_request( $request_url, $args );

    if ( is_wp_error( $response ) ) {
        //crm_log( 'Error en wp_remote_request: ' . $response->get_error_message(), 'ERROR' );
        return $response; // Devuelve el WP_Error
    }

    $response_code = wp_remote_retrieve_response_code( $response );
    $response_body = wp_remote_retrieve_body( $response );
    $decoded_body = json_decode( $response_body, true );

    // //crm_log( "Respuesta API recibida. Código: {$response_code}", 'DEBUG' );
    // //crm_log( "Cuerpo respuesta API: " . $response_body, 'DEBUG' );


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

        //crm_log( "Error en la API ({$response_code}): {$error_message}", 'ERROR' );
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
        //crm_log( 'Error AJAX: Permiso denegado para crm_get_instances.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ), 403 );
    }

    // --- INICIO DEBUG DIRECTO ---
    $test_url = get_option( 'crm_evolution_api_url', 'FALLO_GET_OPTION' );
    // //crm_log( '[DEBUG DIRECTO] Valor leído por get_option: ' . var_export( $test_url, true ), 'DEBUG' );

    if ( empty( $test_url ) || $test_url === 'FALLO_GET_OPTION' ) {
         //crm_log( '[DEBUG DIRECTO] ERROR: La variable $test_url está vacía o no se leyó la opción.', 'ERROR' );
         wp_send_json_error( array( 'message' => 'Error interno leyendo la opción URL (Debug Directo).' ) );
         return; // Salir si hay error
    } else {
        //  //crm_log( '[DEBUG DIRECTO] ÉXITO: La variable $test_url contiene: ' . $test_url, 'INFO' );
    }
    // --- FIN DEBUG DIRECTO ---

    // 2. Llamar a la función auxiliar para hacer la petición a la API
    //    El endpoint correcto según la documentación de Evolution API v1.8 es /instance/fetchInstances
    $api_response = crm_evolution_api_request( 'GET', '/instance/fetchInstances' );

    // 3. Procesar la respuesta de la API
    if ( is_wp_error( $api_response ) ) {
        // Si hubo un error en la petición (configuración, conexión, error API)
        //crm_log( 'Error al obtener instancias de la API: ' . $api_response->get_error_message(), 'ERROR' );
        wp_send_json_error( array( 'message' => $api_response->get_error_message() ) );

    } elseif ( is_array( $api_response ) ) {

        // //crm_log( 'Instancias obtenidas correctamente de la API.', 'INFO' );

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
         //crm_log( 'Respuesta inesperada de la API al obtener instancias.', 'ERROR', $api_response );
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
    //crm_log( 'Recibida petición AJAX: crm_create_instance' );

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

        //crm_log( "Instancia {$instance_name} creada. Respuesta API:", 'INFO', $api_response );
        wp_send_json_success( array( 'message' => $message, 'status' => $status ) );
    }
}

// La acción add_action ya está registrada, no necesita cambios.
add_action( 'wp_ajax_crm_create_instance', 'crm_create_instance_callback' ); // La acción ya está registrada


/**
 * AJAX Handler para eliminar una instancia.
 */
function crm_delete_instance_callback() {
     //crm_log( 'Recibida petición AJAX: crm_delete_instance' );
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
             //crm_log( "Intento de eliminar instancia {$instance_name} que no existe (404). Considerado éxito.", 'WARN' );
             wp_send_json_success( array( 'message' => "La instancia {$instance_name} no se encontró o ya fue eliminada." ) );
        } else {
            wp_send_json_error( array( 'message' => 'Error API: ' . $api_response->get_error_message() ) );
        }
    } else {
        //crm_log( "Instancia {$instance_name} eliminada. Respuesta API:", 'INFO', $api_response );
        wp_send_json_success( array( 'message' => "Instancia {$instance_name} eliminada correctamente." ) );
    }
}
add_action( 'wp_ajax_crm_delete_instance', 'crm_delete_instance_callback' );


/**
 * AJAX Handler para obtener el QR Code de una instancia.
 */
function crm_get_instance_qr_callback() {
    // //crm_log( 'Recibida petición AJAX: crm_get_instance_qr' );
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
            //  //crm_log( "QR Code obtenido para {$instance_name}.", 'INFO' );
             // Asegurarse de que el base64 tenga el prefijo correcto para imagen
             if (strpos($qr_code_base64, 'data:image') === false) {
                 $qr_code_base64 = 'data:image/png;base64,' . $qr_code_base64;
             }
            wp_send_json_success( array( 'qrCode' => $qr_code_base64 ) );
        } else {
            //  //crm_log( "No se encontró QR Code en la respuesta para {$instance_name}. Estado puede ser otro.", 'WARN', $api_response );
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
    // //crm_log( 'Recibida petición AJAX: crm_connect_instance' );
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

        // //crm_log( $message, 'INFO', $api_response );
        wp_send_json_success( array( 'message' => $message, 'status' => $status ) );
    }
}
add_action( 'wp_ajax_crm_connect_instance', 'crm_connect_instance_callback' );


/**
 * AJAX Handler para desconectar una instancia.
 */
function crm_disconnect_instance_callback() {
    // //crm_log( 'Recibida petición AJAX: crm_disconnect_instance' );
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
        //  //crm_log( "Error API al desconectar {$instance_name}: " . $api_response->get_error_message(), 'ERROR' );
        wp_send_json_error( array( 'message' => 'Error API: ' . $api_response->get_error_message() ) );
    } else {
        // //crm_log( "Instancia {$instance_name} desconectada.", 'INFO', $api_response );
        wp_send_json_success( array( 'message' => "Instancia {$instance_name} desconectada." ) );
    }
}
add_action( 'wp_ajax_crm_disconnect_instance', 'crm_disconnect_instance_callback' );

/**
 * AJAX Handler para obtener instancias activas (conectadas o esperando QR) para selects.
 */
function crm_get_active_instances_callback() {
    // //crm_log( 'Recibida petición AJAX: crm_get_active_instances' );
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
        // //crm_log( 'Instancias activas obtenidas: ' . count($active_instances), 'INFO' );
       wp_send_json_success( $active_instances );
   } else {
       wp_send_json_error( array( 'message' => 'Respuesta inesperada de la API.' ) );
   }
}
add_action( 'wp_ajax_crm_get_active_instances', 'crm_get_active_instances_callback' );


// =========================================================================
// == MANEJADORES AJAX - HISTORIAL DE CHATS ==
// =========================================================================

/**
 * AJAX Handler para obtener la lista de conversaciones recientes para la interfaz de chat.
 */
function crm_get_recent_conversations_ajax() {
    //crm_log( 'Recibida petición AJAX: crm_get_recent_conversations' );

    // 1. Verificar Nonce y Permisos
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    // Usar 'edit_posts' ya que es la capacidad que dimos al menú de chats
    if ( ! current_user_can( 'edit_posts' ) ) {
        //crm_log( 'Error AJAX: Permiso denegado para crm_get_recent_conversations.', 'ERROR' );
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
        //crm_log( "Procesando {$query->post_count} mensajes recientes para obtener conversaciones." );
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

    //crm_log( "Enviando " . count($conversations) . " conversaciones únicas al frontend.", 'INFO' );
    wp_send_json_success( $conversations );
}
add_action( 'wp_ajax_crm_get_recent_conversations', 'crm_get_recent_conversations_ajax' );

/**
 * AJAX Handler para obtener los mensajes de una conversación específica.
 */
function crm_get_conversation_messages_ajax() {
    //crm_log( 'Recibida petición AJAX: crm_get_conversation_messages' );

    // 1. Verificar Nonce y Permisos
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) {
        //crm_log( 'Error AJAX: Permiso denegado para crm_get_conversation_messages.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ), 403 );
    }

    // 2. Obtener y validar User ID
    $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
    if ( $user_id <= 0 ) {
        //crm_log( 'Error AJAX: User ID inválido o no proporcionado para crm_get_conversation_messages.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'ID de usuario inválido.' ), 400 );
    }

    //crm_log( "Obteniendo mensajes para User ID: {$user_id}" );

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
        //crm_log( "Procesando {$query->post_count} mensajes para User ID: {$user_id}." );
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();
            $attachment_id = get_post_meta( $post_id, '_crm_media_attachment_id', true );
            $message_content = get_the_content(); // Obtener contenido
            //crm_log("Mensaje ID {$post_id}: Contenido obtenido por get_the_content(): " . $message_content, 'DEBUG'); // <-- Log añadido

            $messages[] = array(
                'id'            => $post_id,
                'text'          => $message_content, // Usar variable
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

    //crm_log( "Enviando " . count($messages) . " mensajes para User ID: {$user_id} al frontend.", 'INFO' );
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
    //crm_log( "Heartbeat: Recibido pulso en pantalla '{$screen_id}'. Datos recibidos:", 'DEBUG', $data ); // Log inicial

    $needs_list_refresh = false;
    $new_messages_for_open_chat = [];

    // --- 1. Comprobar si hay mensajes nuevos para el chat ABIERTO ---
    if ( isset( $data['crm_current_open_chat_id'], $data['crm_last_message_timestamp'] ) ) {
        $open_chat_user_id = absint( $data['crm_current_open_chat_id'] );
        $last_message_timestamp = absint( $data['crm_last_message_timestamp'] );

        if ( $open_chat_user_id > 0 ) {
            // //crm_log( "Heartbeat: Chat abierto User ID: {$open_chat_user_id}. Buscando mensajes desde: {$last_message_timestamp}", 'DEBUG' );
            //crm_log( "Heartbeat: Chat abierto User ID: {$open_chat_user_id}. Buscando mensajes con timestamp > {$last_message_timestamp}", 'DEBUG' );

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
                //crm_log( "Heartbeat: {$query_open_chat->post_count} mensajes nuevos encontrados para el chat abierto (User ID: {$open_chat_user_id}).", 'INFO' );
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
        //crm_log( "Heartbeat: Chequeo general. Buscando mensajes desde: {$last_check_timestamp}", 'DEBUG' );
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
            //crm_log( "Heartbeat: Detectados mensajes nuevos generales desde {$last_check_timestamp}.", 'INFO' );
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

/**
 * AJAX Handler para buscar usuarios de WP por nombre/email/login que tengan teléfono,
 * para iniciar un nuevo chat.
 */
function crm_search_wp_users_for_chat_callback() {
    //crm_log( 'Recibida petición AJAX: crm_search_wp_users_for_chat' );

    // 1. Verificar Nonce y Permisos
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) { // Capacidad para ver chats
        //crm_log( 'Error AJAX: Permiso denegado para crm_search_wp_users_for_chat.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ), 403 );
    }

    // 2. Obtener y sanitizar término de búsqueda
    $search_term = isset( $_POST['search_term'] ) ? sanitize_text_field( wp_unslash( $_POST['search_term'] ) ) : '';

    // 3. Validar longitud mínima
    if ( strlen( $search_term ) < 3 ) {
        //crm_log( 'Término de búsqueda demasiado corto: ' . $search_term, 'DEBUG' );
        wp_send_json_success( array() ); // Devolver array vacío si es corto
    }

    //crm_log( "Buscando usuarios WP con teléfono que coincidan con: '{$search_term}'" );

    // 4. Preparar WP_User_Query
    $args = array(
        'search'         => '*' . esc_attr( $search_term ) . '*', // Buscar coincidencias parciales
        'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
        'number'         => 10, // Limitar resultados
        'fields'         => 'all_with_meta', // Obtener todos los datos y metadatos
        'meta_query'     => array(
            array(
                'key'     => 'billing_phone', // Asegurar que tengan teléfono
                'value'   => '',
                'compare' => '!=',
            ),
        ),
    );

    $user_query = new WP_User_Query( $args );
    $results = array();

    // 5. Formatear resultados
    foreach ( $user_query->get_results() as $user ) {
        $results[] = array(
            'user_id'      => $user->ID,
            'display_name' => $user->display_name,
            'avatar_url'   => get_avatar_url( $user->ID, ['size' => 96] ),
        );
    }

    //crm_log( "Encontrados " . count($results) . " usuarios WP con teléfono para '{$search_term}'.", 'INFO' );
    wp_send_json_success( $results );
}
add_action( 'wp_ajax_crm_search_wp_users_for_chat', 'crm_search_wp_users_for_chat_callback' );

// =========================================================================
// == MANEJADORES AJAX - ENVÍO DE MENSAJES ==
// =========================================================================

/**
 * AJAX Handler para enviar un mensaje de texto.
 */
function crm_send_message_ajax() {
    //crm_log( 'Recibida petición AJAX: crm_send_message_ajax' );

    // 1. Verificar Nonce y Permisos
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) { // Capacidad para ver/usar chats
        //crm_log( 'Error AJAX: Permiso denegado para crm_send_message_ajax.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ), 403 );
    }

    // 2. Obtener y sanitizar datos
    $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
    $message_text = isset( $_POST['message_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message_text'] ) ) : '';

    // 3. Validar datos
    if ( $user_id <= 0 ) {
        //crm_log( 'Error AJAX: User ID inválido para enviar mensaje.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'ID de usuario inválido.' ), 400 );
    }
    if ( empty( $message_text ) ) {
        //crm_log( 'Error AJAX: Texto del mensaje vacío.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'El mensaje no puede estar vacío.' ), 400 );
    }

    // 4. Llamar a la función que realmente envía el mensaje
    $result = crm_send_whatsapp_message( $user_id, $message_text );

    // 5. Enviar respuesta
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    } else {
        // Podríamos devolver más info de la API si fuera útil
        wp_send_json_success( array( 'message' => 'Mensaje enviado (o en proceso).', 'api_response' => $result ) );
    }
}
add_action( 'wp_ajax_crm_send_message_ajax', 'crm_send_message_ajax' );

/**
 * Envía un mensaje de texto a un usuario WP usando la API Evolution.
 *
 * @param int $user_id ID del usuario WP destinatario.
 * @param string $message_text Texto del mensaje a enviar.
 * @return array|WP_Error Respuesta de la API o WP_Error.
 */
function crm_send_whatsapp_message( $user_id, $message_text ) {
    //crm_log( "Intentando enviar mensaje a User ID: {$user_id}", 'INFO' );

    // 1. Obtener JID del destinatario
    $recipient_jid = get_user_meta( $user_id, '_crm_whatsapp_jid', true );
    if ( empty( $recipient_jid ) ) {
        //crm_log( "Error: No se encontró JID para User ID {$user_id}.", 'ERROR' );
        return new WP_Error( 'jid_not_found', 'No se encontró el número de WhatsApp (JID) para este usuario.' );
    }

    // 2. Encontrar una instancia activa para enviar
    $active_instance_name = null;
    $instances_response = crm_evolution_api_request( 'GET', '/instance/fetchInstances' );
    if ( !is_wp_error( $instances_response ) && is_array( $instances_response ) ) {
        foreach ( $instances_response as $instance ) {
            $status = isset($instance['instance']['status']) ? $instance['instance']['status'] : 'unknown';
            // Usar la primera instancia conectada que encuentre
            if ( ($status === 'open' || $status === 'connected' || $status === 'connection') && isset($instance['instance']['instanceName']) ) {
                $active_instance_name = $instance['instance']['instanceName'];
                //crm_log( "Usando instancia activa '{$active_instance_name}' para enviar.", 'INFO' );
                break;
            }
        }
    }

    if ( empty( $active_instance_name ) ) {
        //crm_log( "Error: No se encontró ninguna instancia activa para enviar el mensaje.", 'ERROR' );
        return new WP_Error( 'no_active_instance', 'No hay ninguna instancia de WhatsApp conectada para enviar el mensaje.' );
    }

    // 3. Preparar datos para la API Evolution (/message/sendText)
    $endpoint = "/message/sendText/{$active_instance_name}";
    $body = array(
        'number'        => $recipient_jid,
        'options'       => array( 'delay' => 1200, 'presence' => 'composing' ), // Delay y simular escritura
        'textMessage'   => array( 'text' => $message_text ),
    );

    // 4. Llamar a la API
    $api_response = crm_evolution_api_request( 'POST', $endpoint, $body );

    // 5. Si la llamada a la API fue exitosa, guardar el mensaje saliente en la BD
    if ( ! is_wp_error( $api_response ) ) {
        //crm_log( "Guardado DB: API OK. Preparando datos para guardar mensaje saliente User ID {$user_id}.", 'DEBUG' ); // <-- Log 1
        //crm_log( "Llamada API para enviar mensaje a User ID {$user_id} exitosa (a nivel conexión). Guardando mensaje saliente...", 'INFO', $api_response );

        // Intentar obtener el ID del mensaje de la respuesta de la API (esto depende de la estructura de respuesta de Evolution API)
        // Ajusta ['key', 'id'] según la respuesta real. Si no existe, será null.
        $whatsapp_message_id = isset( $api_response['key']['id'] ) ? sanitize_text_field( $api_response['key']['id'] ) : null;

        $message_data = array(
            'user_id'             => $user_id,
            'instance_name'       => $active_instance_name,
            'message_text'        => $message_text,
            'timestamp'           => time(), // Usar timestamp actual del servidor
            'is_outgoing'         => true,
            'message_type'        => 'text',
            'whatsapp_message_id' => $whatsapp_message_id,
            'attachment_url'      => null, // No hay adjunto para texto
            'caption'             => null,
        );

        //crm_log( "Guardado DB: Datos preparados (\$message_data): " . print_r($message_data, true), 'DEBUG' ); // <-- Log 2
        $meta_input_prepared = crm_prepare_chat_message_meta( $message_data ); // Usar función helper si existe, o mapear aquí
        //crm_log( "Guardado DB: Meta Input preparado: " . print_r($meta_input_prepared, true), 'DEBUG' ); // <-- Log 3
        // Llamar a la función que guarda el post (podríamos crear una función helper crm_save_chat_message)
        // Por ahora, insertamos directamente adaptando la lógica del webhook
        $post_id = wp_insert_post( array(
            'post_type'   => 'crm_chat',
            'post_status' => 'publish', // Publicar inmediatamente
            'post_title'  => 'Mensaje Saliente - ' . $user_id . ' - ' . time(), // Título simple
            'post_content'=> $message_text, // <-- AÑADIR EL CONTENIDO DEL MENSAJE
            'meta_input'  => $meta_input_prepared,
        ) );

        if ( is_wp_error( $post_id ) ) {
            //crm_log( "Error al guardar el mensaje saliente en la BD para User ID {$user_id}.", 'ERROR', $post_id->get_error_message() );
            // No devolvemos error al frontend necesariamente, el mensaje se envió. Solo logueamos.
        } else {
            //crm_log( "Mensaje saliente guardado en BD con Post ID: {$post_id}", 'INFO' );
        }
    } else {
         //crm_log( "Llamada API para enviar mensaje a User ID {$user_id} falló.", 'WARN', $api_response->get_error_message() );
    }

    return $api_response;
}


/**
 * Prepara el array de metadatos para guardar un post 'crm_chat'.
 *
 * @param array $data Array con los datos del mensaje (user_id, instance_name, etc.).
 * @return array Array formateado para el parámetro 'meta_input' de wp_insert_post.
 */
function crm_prepare_chat_message_meta( $data ) {
    $meta_input = array();

    // Mapear los datos a las claves meta correctas
    if ( isset( $data['user_id'] ) ) $meta_input['_crm_contact_user_id'] = absint( $data['user_id'] );
    if ( isset( $data['instance_name'] ) ) $meta_input['_crm_instance_name'] = sanitize_text_field( $data['instance_name'] );
    // Asumiendo que sender/recipient JIDs también son relevantes para mensajes salientes desde el plugin
    // Para un mensaje saliente, el sender es la instancia y el recipient es el contacto
    // Necesitamos obtener el JID de la instancia (no lo tenemos en $data ahora mismo)
    // y el JID del contacto (sí lo tenemos). Ajustaremos esto si es necesario.
    // Por ahora, guardamos lo que tenemos:
    // if ( isset( $data['sender_jid'] ) ) $meta_input['_crm_sender_jid'] = sanitize_text_field( $data['sender_jid'] );
    // if ( isset( $data['recipient_jid'] ) ) $meta_input['_crm_recipient_jid'] = sanitize_text_field( $data['recipient_jid'] );
    if ( isset( $data['is_outgoing'] ) ) $meta_input['_crm_is_outgoing'] = (bool) $data['is_outgoing'];
    if ( isset( $data['whatsapp_message_id'] ) ) $meta_input['_crm_message_id_wa'] = sanitize_text_field( $data['whatsapp_message_id'] );
    if ( isset( $data['timestamp'] ) ) $meta_input['_crm_timestamp_wa'] = absint( $data['timestamp'] );
    if ( isset( $data['message_type'] ) ) $meta_input['_crm_message_type'] = sanitize_text_field( $data['message_type'] );
    if ( isset( $data['attachment_url'] ) ) $meta_input['_crm_attachment_url'] = esc_url_raw( $data['attachment_url'] ); // Guardar URL adjunto
    if ( isset( $data['caption'] ) ) $meta_input['_crm_caption'] = sanitize_textarea_field( $data['caption'] );       // Guardar caption
    // Añadir más campos si son necesarios (ej: _crm_is_group_message, _crm_participant_jid)

    return $meta_input;
}


// =========================================================================
// == MANEJADORES AJAX - ENVÍO DE MULTIMEDIA ==
// =========================================================================

/**
 * AJAX Handler para enviar un mensaje multimedia.
 */
function crm_send_media_message_ajax() {
    //crm_log( 'Recibida petición AJAX: crm_send_media_message_ajax' );

    // 1. Verificar Nonce y Permisos
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) {
        //crm_log( 'Error AJAX: Permiso denegado para crm_send_media_message_ajax.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ), 403 );
    }

    // 2. Obtener y sanitizar datos
    $user_id        = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
    $attachment_url = isset( $_POST['attachment_url'] ) ? esc_url_raw( wp_unslash( $_POST['attachment_url'] ) ) : '';
    $mime_type      = isset( $_POST['mime_type'] ) ? sanitize_mime_type( wp_unslash( $_POST['mime_type'] ) ) : '';
    $filename       = isset( $_POST['filename'] ) ? sanitize_file_name( wp_unslash( $_POST['filename'] ) ) : '';
    $caption        = isset( $_POST['caption'] ) ? sanitize_textarea_field( wp_unslash( $_POST['caption'] ) ) : ''; // El texto del input es el caption

    // 3. Validar datos
    if ( $user_id <= 0 || empty( $attachment_url ) || empty( $mime_type ) ) {
        //crm_log( 'Error AJAX: Datos inválidos para enviar multimedia.', 'ERROR', $_POST );
        wp_send_json_error( array( 'message' => 'Faltan datos necesarios para enviar el archivo (ID usuario, URL o tipo MIME).' ), 400 );
    }

    // 4. Llamar a la función que realmente envía el mensaje multimedia
    $result = crm_send_whatsapp_media_message( $user_id, $attachment_url, $mime_type, $filename, $caption );

    // 5. Enviar respuesta
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    } else {
        wp_send_json_success( array( 'message' => 'Archivo multimedia enviado (o en proceso).', 'api_response' => $result ) );
    }
}
add_action( 'wp_ajax_crm_send_media_message_ajax', 'crm_send_media_message_ajax' ); // <-- Registrar la acción

/**
 * Envía un mensaje multimedia a un usuario WP usando la API Evolution.
 *
 * @param int    $user_id        ID del usuario WP destinatario.
 * @param string $attachment_url URL del archivo adjunto.
 * @param string $mime_type      Tipo MIME del archivo.
 * @param string $filename       Nombre original del archivo (opcional).
 * @param string $caption        Texto que acompaña al archivo (opcional).
 * @return array|WP_Error Respuesta de la API o WP_Error.
 */
function crm_send_whatsapp_media_message( $user_id, $attachment_url, $mime_type, $filename, $caption ) {
    //crm_log( "Intentando enviar multimedia a User ID: {$user_id}", 'INFO', compact('attachment_url', 'mime_type', 'filename', 'caption') );

    // 1. Obtener JID del destinatario (igual que para texto)
    $recipient_jid = get_user_meta( $user_id, '_crm_whatsapp_jid', true );
    if ( empty( $recipient_jid ) ) {
        return new WP_Error( 'jid_not_found', 'No se encontró el número de WhatsApp (JID) para este usuario.' );
    }

    // 2. Encontrar una instancia activa (igual que para texto)
    $active_instance_name = null;
    // (Reutilizar la lógica de crm_send_whatsapp_message o extraerla a una función helper si prefieres)
    $instances_response = crm_evolution_api_request( 'GET', '/instance/fetchInstances' );
    if ( !is_wp_error( $instances_response ) && is_array( $instances_response ) ) {
        foreach ( $instances_response as $instance ) {
            $status = isset($instance['instance']['status']) ? $instance['instance']['status'] : 'unknown';
            if ( ($status === 'open' || $status === 'connected' || $status === 'connection') && isset($instance['instance']['instanceName']) ) {
                $active_instance_name = $instance['instance']['instanceName'];
                break;
            }
        }
    }
    if ( empty( $active_instance_name ) ) {
        return new WP_Error( 'no_active_instance', 'No hay ninguna instancia de WhatsApp conectada para enviar el mensaje.' );
    }

    // 3. Determinar el tipo de media para la API Evolution
    $media_type = 'document'; // Tipo por defecto
    if ( strpos( $mime_type, 'image/' ) === 0 ) $media_type = 'image';
    elseif ( strpos( $mime_type, 'video/' ) === 0 ) $media_type = 'video';
    elseif ( strpos( $mime_type, 'audio/' ) === 0 ) $media_type = 'audio';

    // 4. Preparar datos para la API Evolution (/message/sendMedia)
    $endpoint = "/message/sendMedia/{$active_instance_name}";
    $body = array(
        'number'        => $recipient_jid,
        'options'       => array( 'delay' => 1200, 'presence' => 'uploading' ), // Simular subida
        'mediaMessage'  => array(
            'mediaType' => $media_type,
            'url'       => $attachment_url,
            'caption'   => $caption, // Puede ser vacío
            'filename'  => $filename ?: basename( $attachment_url ), // Usar filename o extraer de URL
        ),
    );

    // 5. Llamar a la API
    $api_response = crm_evolution_api_request( 'POST', $endpoint, $body );

    // 6. Si la llamada a la API fue exitosa, guardar el mensaje saliente en la BD (¡IMPLEMENTACIÓN PENDIENTE!)
    if ( ! is_wp_error( $api_response ) ) {
        //crm_log( "Llamada API para enviar multimedia a User ID {$user_id} exitosa. Guardando mensaje...", 'INFO' );
        // --- INICIO: Guardar mensaje multimedia en BD (Adaptar de crm_send_whatsapp_message) ---
        $whatsapp_message_id = isset( $api_response['key']['id'] ) ? sanitize_text_field( $api_response['key']['id'] ) : null;
        $message_data = array(
            'user_id'             => $user_id,
            'instance_name'       => $active_instance_name,
            'message_text'        => null, // No hay texto principal, solo caption
            'timestamp'           => time(),
            'is_outgoing'         => true,
            'message_type'        => $media_type, // Usar el tipo detectado
            'whatsapp_message_id' => $whatsapp_message_id,
            'attachment_url'      => $attachment_url, // Guardar URL
            'caption'             => $caption,        // Guardar caption
        );
        $post_id = wp_insert_post( array(
            'post_type'   => 'crm_chat',
            'post_status' => 'publish',
            'post_title'  => 'Mensaje Multimedia Saliente - ' . $user_id . ' - ' . time(),
            'meta_input'  => crm_prepare_chat_message_meta( $message_data ), // Asegúrate que esta función maneje attachment_url y caption
        ) );
        if ( is_wp_error( $post_id ) ) {
            //crm_log( "Error al guardar el mensaje multimedia saliente en la BD para User ID {$user_id}.", 'ERROR', $post_id->get_error_message() );
        } else {
            //crm_log( "Mensaje multimedia saliente guardado en BD con Post ID: {$post_id}", 'INFO' );
        }
        // --- FIN: Guardar mensaje multimedia en BD ---
    }

    // 7. Devolver la respuesta
    return $api_response;
}

/**
 * Permite la subida de archivos OGG (audio) a la biblioteca de medios.
 *
 * @param array $mime_types Array de tipos MIME permitidos.
 * @return array Array modificado.
 */
function crm_allow_ogg_upload( $mime_types ) {
    // Añadir ogg => audio/ogg
    // El tipo MIME completo puede ser 'audio/ogg; codecs=opus', pero WP generalmente solo necesita 'audio/ogg'
    $mime_types['ogg'] = 'audio/ogg';
    //crm_log('Filtro upload_mimes: Añadiendo soporte para audio/ogg.', 'DEBUG'); // Log para confirmar que se ejecuta
    return $mime_types;
}
add_filter( 'upload_mimes', 'crm_allow_ogg_upload', 10, 1 );

// =========================================================================
// == MANEJADORES AJAX - DETALLES DEL CONTACTO (SIDEBAR) ==
// =========================================================================

/**
 * AJAX Handler para obtener los detalles de un contacto para el sidebar.
 */
function crm_get_contact_details_callback() {
    //crm_log( 'Recibida petición AJAX: crm_get_contact_details' );

    // 1. Verificar Nonce y Permisos
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) { // Capacidad para ver chats/usuarios
        //crm_log( 'Error AJAX: Permiso denegado para crm_get_contact_details.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ), 403 );
    }

    // 2. Obtener y validar User ID
    $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
    if ( $user_id <= 0 ) {
        //crm_log( 'Error AJAX: User ID inválido para crm_get_contact_details.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'ID de usuario inválido.' ), 400 );
    }

    // 3. Obtener datos del usuario
    $user_data = get_userdata( $user_id );
    if ( ! $user_data ) {
        //crm_log( "Error AJAX: No se encontró usuario con ID {$user_id}.", 'ERROR' );
        wp_send_json_error( array( 'message' => 'Usuario no encontrado.' ), 404 );
    }

    // 4. Obtener metadatos relevantes
    $phone = get_user_meta( $user_id, 'billing_phone', true ); // Teléfono E.164
    $jid = get_user_meta( $user_id, '_crm_whatsapp_jid', true ); // JID
    $tag_key = get_user_meta( $user_id, '_crm_lifecycle_tag', true ); // Clave de la etiqueta
    $notes = get_user_meta( $user_id, '_crm_contact_notes', true ); // Notas (si las hubiera)

    // Obtener nombre legible de la etiqueta
    $all_tags = crm_get_lifecycle_tags();
    $tag_name = isset( $all_tags[$tag_key] ) ? $all_tags[$tag_key] : $tag_key; // Mostrar clave si no se encuentra nombre

    // 5. Preparar datos para la respuesta
    $contact_details = array(
        'user_id'      => $user_id,
        'display_name' => $user_data->display_name,
        'email'        => $user_data->user_email,
        'phone'        => $phone ?: 'N/D',
        'jid'          => $jid ?: 'N/D',
        'tag_key'      => $tag_key ?: '', // Enviar clave para posible edición
        'tag_name'     => $tag_name ?: 'Sin etiqueta', // Nombre legible
        'notes'        => $notes ?: '', // Notas
        'avatar_url'   => get_avatar_url( $user_id, ['size' => 96] ), // Avatar
    );

    //crm_log( "Enviando detalles del contacto User ID: {$user_id} al frontend.", 'INFO' );
    wp_send_json_success( $contact_details );
}
add_action( 'wp_ajax_crm_get_contact_details', 'crm_get_contact_details_callback' );

/**
 * AJAX Handler para guardar los detalles modificados de un contacto.
 */
function crm_save_contact_details_callback() {
    //crm_log( 'Recibida petición AJAX: crm_save_contact_details' );

    // 1. Verificar Nonce y Permisos
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'edit_users' ) ) { // Capacidad para editar usuarios
        //crm_log( 'Error AJAX: Permiso denegado para crm_save_contact_details.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes para editar usuarios.' ), 403 );
    }

    // 2. Obtener y sanitizar datos
    $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
    $name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
    $email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    $tag_key = isset( $_POST['tag_key'] ) ? sanitize_key( $_POST['tag_key'] ) : '';
    $notes   = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

    // 3. Validar datos
    if ( $user_id <= 0 ) {
        wp_send_json_error( array( 'message' => 'ID de usuario inválido.' ), 400 );
    }
    if ( empty( $name ) ) {
        wp_send_json_error( array( 'message' => 'El nombre no puede estar vacío.' ), 400 );
    }
    if ( ! is_email( $email ) ) {
        wp_send_json_error( array( 'message' => 'El correo electrónico no es válido.' ), 400 );
    }
    // Verificar si el email ya existe para OTRO usuario
    $existing_user = email_exists( $email );
    if ( $existing_user && $existing_user != $user_id ) {
        wp_send_json_error( array( 'message' => 'Este correo electrónico ya está en uso por otro usuario.' ), 409 ); // 409 Conflict
    }

    // 4. Actualizar datos del usuario WP
    $user_data_update = array(
        'ID'           => $user_id,
        'display_name' => $name,
        'user_email'   => $email,
        // Podríamos actualizar first_name/last_name si los tuviéramos separados
    );
    $update_result = wp_update_user( $user_data_update );

    if ( is_wp_error( $update_result ) ) {
        //crm_log( "Error al actualizar datos básicos del usuario {$user_id}: " . $update_result->get_error_message(), 'ERROR' );
        wp_send_json_error( array( 'message' => 'Error al actualizar datos del usuario: ' . $update_result->get_error_message() ) );
    }

    // 5. Actualizar metadatos
    update_user_meta( $user_id, '_crm_lifecycle_tag', $tag_key );
    update_user_meta( $user_id, '_crm_contact_notes', $notes );

    //crm_log( "Detalles del contacto User ID: {$user_id} actualizados correctamente.", 'INFO' );
    wp_send_json_success( array( 'message' => 'Contacto actualizado correctamente.' ) );
}
add_action( 'wp_ajax_crm_save_contact_details', 'crm_save_contact_details_callback' );

/**
 * AJAX Handler para obtener las etiquetas de ciclo de vida formateadas para un select en JS.
 * Llamado desde app.js para el sidebar de detalles del contacto.
 */
function crm_get_etiquetas_for_select_callback() {
    //crm_log( 'Recibida petición AJAX: crm_get_etiquetas_for_select' );

    // 1. Verificar Nonce y Permisos
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' ); // <-- Volver al nonce original
    if ( ! current_user_can( 'edit_posts' ) ) { // Capacidad para ver chats/usuarios
        //crm_log( 'Error AJAX: Permiso denegado para crm_get_etiquetas_for_select.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ), 403 );
    }

    // 2. Obtener las etiquetas
    // Asegurarse de que la función exista (debería estar en crm-setting.php)
    if ( ! function_exists( 'crm_get_lifecycle_tags' ) ) {
        //crm_log( 'Error AJAX: La función crm_get_lifecycle_tags no existe.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'Error interno del servidor (función no encontrada).' ), 500 );
    }
    $tags_assoc = crm_get_lifecycle_tags(); // Devuelve [key => name]

    // 3. Formatear para el select de JS (array de objetos)
    $tags_formatted = array();
    foreach ( $tags_assoc as $key => $name ) {
        $tags_formatted[] = array(
            'key'  => $key,
            'name' => $name,
        );
    }

    //crm_log( "Enviando " . count($tags_formatted) . " etiquetas formateadas para select.", 'INFO' );
    wp_send_json_success( $tags_formatted );

}
add_action( 'wp_ajax_crm_get_etiquetas_for_select', 'crm_get_etiquetas_for_select_callback' );
