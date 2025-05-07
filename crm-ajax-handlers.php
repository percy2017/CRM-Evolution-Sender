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
        //  crm_log_to_file( "[crm_evolution_api_request] Cuerpo de la petición (args['body']): " . $args['body'], 'DEBUG-BODY' ); // Log más directo
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
   error_log( 'Recibida petición AJAX: crm_get_active_instances' );

   // 1. Seguridad: Verificar Nonce y Permisos.
   // El nonce debe ser generado con la acción 'wp_rest' y enviado como 'security' desde el frontend.
   check_ajax_referer( 'wp_rest', 'security' );

   if ( ! current_user_can( 'manage_options' ) ) {
       wp_send_json_error( array( 'message' => __( 'Permiso denegado.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) ), 403 );
   }

   $api_response = crm_evolution_api_request( 'GET', '/instance/fetchInstances' );

   if ( is_wp_error( $api_response ) ) {
       wp_send_json_error( array( 'message' => 'Error API: ' . $api_response->get_error_message() ) );
   } elseif ( is_array( $api_response ) ) {
       $active_instances = array();
       foreach ( $api_response as $instance ) {
           $status = isset($instance['instance']['status']) ? $instance['instance']['status'] : 'unknown';
           $instance_name_from_api = isset($instance['instance']['instanceName']) ? $instance['instance']['instanceName'] : null;

           // Considerar activas las que están conectadas o esperando QR
           // Añadimos 'open' como estado conectado también.
           if ( $instance_name_from_api && in_array($status, ['open', 'connected', 'connection', 'qrcode', 'connecting']) ) {
                // Formatear el texto del estado para ser más legible
                $status_text = ucfirst($status);
                if ($status === 'qrcode') $status_text = 'Esperando QR';
                if ($status === 'connection' || $status === 'connected') $status_text = 'Conectada';
                if ($status === 'connecting') $status_text = 'Conectando';

                $active_instances[] = array(
                   'value' => $instance_name_from_api,
                   'text'  => $instance_name_from_api . ' (' . $status_text . ')',
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
        // Verificar si $result contiene los datos del mensaje para el frontend
        if ( is_array( $result ) && isset( $result['sent_message_data'] ) ) {
            wp_send_json_success( array(
                'message' => 'Mensaje enviado (o en proceso).',
                'api_response' => $result['api_response'],
                'sent_message' => $result['sent_message_data'] // Enviar el objeto del mensaje al frontend
            ) );
        } else {
            // Fallback por si algo no devuelve la estructura esperada (solo respuesta API)
            wp_send_json_success( array( 'message' => 'Mensaje enviado (o en proceso).', 'api_response' => $result ) );
        }
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
            // Construir el objeto del mensaje para el frontend
            $message_for_frontend = array(
                'id'            => $post_id,
                'text'          => $message_text,
                'timestamp'     => $message_data['timestamp'], // Usar el timestamp que se guardó
                'is_outgoing'   => true,
                'type'          => 'text',
                'caption'       => null,
                'attachment_id' => null,
                'attachment_url'=> null,
            );
            //crm_log( "Mensaje saliente guardado en BD con Post ID: {$post_id}", 'INFO' );
            // Devolver tanto la respuesta de la API como los datos del mensaje guardado
            return array(
                'api_response' => $api_response,
                'sent_message_data' => $message_for_frontend
            );
        }
    } else {
         //crm_log( "Llamada API para enviar mensaje a User ID {$user_id} falló.", 'WARN', $api_response->get_error_message() );
    }
    // Si la API falló o el guardado en BD falló (y no retornamos antes), devolver solo la respuesta de la API (o error)
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
        if ( is_array( $result ) && isset( $result['sent_message_data'] ) ) {
            wp_send_json_success( array(
                'message' => 'Archivo multimedia enviado (o en proceso).',
                'api_response' => $result['api_response'],
                'sent_message' => $result['sent_message_data']
            ) );
        } else {
            wp_send_json_success( array( 'message' => 'Archivo multimedia enviado (o en proceso).', 'api_response' => $result ) );
        }
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
        'options'       => array( 'delay' => 1200, 'presence' => 'composing' ), // Simular subida
        'mediaMessage'  => array(
            'mediatype' => $media_type, // Corregido a minúsculas
            'media'     => $attachment_url, // Corregido de 'url' a 'media'
            'caption'   => $caption, // Puede ser vacío
            'fileName'  => $filename ?: basename( $attachment_url ), // 'fileName' con 'N' mayúscula
        ),
    );
    error_log( 'Api'.$endpoint);
    error_log( 'Datos'.wp_json_encode($body) );
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
            // Construir el objeto del mensaje para el frontend
            // Para multimedia saliente, el 'attachment_id' local podría no ser relevante si solo se envía la URL.
            // El frontend usará 'attachment_url' directamente para la vista previa optimista.
            $message_for_frontend = array(
                'id'            => $post_id,
                'text'          => null, // El texto principal es el caption para media
                'timestamp'     => $message_data['timestamp'], // Usar el timestamp que se guardó
                'is_outgoing'   => true,
                'type'          => $media_type, // El tipo de media detectado (image, video, etc.)
                'caption'       => $caption,
                'attachment_id' => null, // No tenemos un ID de adjunto local en este flujo
                'attachment_url'=> $attachment_url, // La URL que se envió a la API
            );
            //crm_log( "Mensaje multimedia saliente guardado en BD con Post ID: {$post_id}", 'INFO' );
            // Devolver tanto la respuesta de la API como los datos del mensaje guardado
            return array(
                'api_response' => $api_response,
                'sent_message_data' => $message_for_frontend
            );
        }
        // --- FIN: Guardar mensaje multimedia en BD ---
    }
    // Si la API falló o el guardado en BD falló (y no retornamos antes), devolver solo la respuesta de la API (o error)
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

    // Fecha de registro
    $registration_date = date_i18n( get_option( 'date_format' ), strtotime( $user_data->user_registered ) );

    // Obtener nombre legible de la etiqueta
    $all_tags = crm_get_lifecycle_tags();
    $tag_name = isset( $all_tags[$tag_key] ) ? $all_tags[$tag_key] : $tag_key; // Mostrar clave si no se encuentra nombre

    // 5. Preparar datos para la respuesta
    $contact_details = array(
        // Datos existentes
        'user_id'      => $user_id,
        'display_name' => $user_data->display_name,
        'email'        => $user_data->user_email,
        'phone'        => $phone ?: 'N/D',
        'jid'          => $jid ?: 'N/D',
        'tag_key'      => $tag_key ?: '', // Enviar clave para posible edición
        'tag_name'     => $tag_name ?: 'Sin etiqueta', // Nombre legible
        'notes'        => $notes ?: '', // Notas
        'avatar_url'   => get_avatar_url( $user_id, ['size' => 96] ), // Avatar
        // Nuevos datos
        'first_name'        => $user_data->first_name,
        'last_name'         => $user_data->last_name,
        'role'              => !empty($user_data->roles) ? translate_user_role( $user_data->roles[0] ) : 'N/A',
        'registration_date' => $registration_date,
    );

    // --- INICIO: Obtener última compra de WooCommerce ---
    if ( class_exists( 'WooCommerce' ) ) {
        $last_order_details = array(
            'id'     => null,
            'date'   => null,
            'total'  => null,
            'status' => null,
            'url'    => null,
        );
        $customer_history = array(
            'total_orders' => 0,
            'total_revenue' => wc_price(0),
            'average_order_value' => wc_price(0),
        );

        $orders = wc_get_orders( array(
            'customer_id' => $user_id,
            'limit'       => 1, // Solo la última orden
            'orderby'     => 'date',
            'order'       => 'DESC',
            'status'      => array_keys( wc_get_order_statuses() ), // Considerar todos los estados
        ) );

        if ( ! empty( $orders ) ) {
            $last_order = $orders[0]; // La primera es la más reciente
            $last_order_details['id']     = $last_order->get_id();
            $last_order_details['date']   = $last_order->get_date_created() ? $last_order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : 'N/A';
            $last_order_details['total']  = $last_order->get_formatted_order_total();
            $last_order_details['status'] = wc_get_order_status_name( $last_order->get_status() );
            $last_order_details['url']    = $last_order->get_edit_order_url(); // URL para ver/editar la orden en el admin

            // Historial del cliente
            $total_orders_count = wc_get_customer_order_count($user_id);
            $total_revenue_spent = wc_get_customer_total_spent($user_id);
            $average_order_value_calc = ($total_orders_count > 0) ? ($total_revenue_spent / $total_orders_count) : 0;

            $customer_history['total_orders'] = $total_orders_count;
            $customer_history['total_revenue'] = wc_price($total_revenue_spent);
            $customer_history['average_order_value'] = wc_price($average_order_value_calc);
        }
        $contact_details['last_purchase'] = $last_order_details;
        $contact_details['customer_history'] = $customer_history;
    } else {
        $contact_details['last_purchase'] = null; // WooCommerce no está activo
        $contact_details['customer_history'] = null;
    }
    // --- FIN: Obtener última compra de WooCommerce ---

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
    $first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
    $last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
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
        'first_name'   => $first_name,
        'last_name'    => $last_name,
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

// =========================================================================
// == MANEJADOR AJAX - ACTUALIZAR AVATAR DEL CONTACTO ==
// =========================================================================

/**
 * AJAX Handler para actualizar la foto de perfil (avatar) de un contacto.
 * Recibe el user_id y el attachment_id de la nueva imagen.
 */
function crm_update_contact_avatar_callback() {
    //crm_log( 'Recibida petición AJAX: crm_update_contact_avatar' );

    // 1. Verificar Nonce y Permisos
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'edit_users' ) ) { // Capacidad para editar usuarios
        //crm_log( 'Error AJAX: Permiso denegado para crm_update_contact_avatar.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes para actualizar el avatar.' ), 403 );
    }

    // 2. Obtener y sanitizar datos
    $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
    $new_attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;

    // 3. Validar datos
    if ( $user_id <= 0 ) {
        wp_send_json_error( array( 'message' => 'ID de usuario inválido.' ), 400 );
    }
    if ( $new_attachment_id <= 0 ) {
        wp_send_json_error( array( 'message' => 'ID de adjunto inválido.' ), 400 );
    }

    // 4. Obtener el ID del avatar anterior
    $old_attachment_id = get_user_meta( $user_id, '_crm_avatar_attachment_id', true );

    // 5. Actualizar el metadato del usuario con el nuevo ID del adjunto
    update_user_meta( $user_id, '_crm_avatar_attachment_id', $new_attachment_id );

    // 6. Si había un avatar anterior y es diferente al nuevo, eliminar el adjunto antiguo
    if ( $old_attachment_id && is_numeric($old_attachment_id) && (int)$old_attachment_id !== $new_attachment_id ) {
        $deleted = wp_delete_attachment( (int)$old_attachment_id, true ); // true para forzar la eliminación del archivo
        if ($deleted) {
            //crm_log( "Avatar anterior (Attachment ID: {$old_attachment_id}) eliminado para User ID: {$user_id}.", 'INFO' );
        } else {
            //crm_log( "Error al intentar eliminar el avatar anterior (Attachment ID: {$old_attachment_id}) para User ID: {$user_id}.", 'WARN' );
        }
    }

    // 7. Obtener la URL del nuevo avatar para devolverla
    $new_avatar_url = wp_get_attachment_url( $new_attachment_id );

    if ( ! $new_avatar_url ) {
        wp_send_json_error( array( 'message' => 'No se pudo obtener la URL del nuevo avatar.' ), 404 );
    }

    //crm_log( "Avatar actualizado para User ID: {$user_id}. Nuevo Attachment ID: {$new_attachment_id}. URL: {$new_avatar_url}", 'INFO' );
    wp_send_json_success( array( 'message' => 'Avatar actualizado correctamente.', 'new_avatar_url' => $new_avatar_url ) );
}
add_action( 'wp_ajax_crm_update_contact_avatar', 'crm_update_contact_avatar_callback' );


/**
 * Envía un mensaje de WhatsApp (texto o multimedia) a través de una instancia específica de Evolution API.
 * Obtiene el perfil del contacto, procesa/crea el usuario en WP si el número existe en WA,
 * y luego guarda el mensaje enviado en el CPT.
 *
 * @param string $recipient_identifier Número de teléfono del destinatario con prefijo de país (ej: "591711XXXXX").
 * @param string $message_content Texto del mensaje. Si es multimedia, este será el caption.
 * @param string $target_instance_name Nombre de la instancia de Evolution API a utilizar (obligatorio).
 * @param string|null $media_url URL completa del archivo multimedia a enviar (opcional).
 * @param string|null $media_filename Nombre del archivo multimedia para mostrar al destinatario (opcional, se extrae de la URL si no se provee).
 * @return array|WP_Error Respuesta de la API Evolution o WP_Error en caso de fallo.
 */
function crm_external_send_whatsapp_message( $recipient_identifier, $message_content, $target_instance_name, $media_url = null, $media_filename = null ) {
    //crm_log( "[External Send V5 - FetchProfile & NumberExists] Iniciando. Destinatario: {$recipient_identifier}, Instancia: {$target_instance_name}", 'INFO' );

    // 1. Validar nombre de instancia
    if ( empty( $target_instance_name ) ) {
        //crm_log( "[External Send V5] Error: Nombre de instancia objetivo no proporcionado.", 'ERROR' );
        return new WP_Error( 'missing_instance_name', __( 'El nombre de la instancia de envío es obligatorio.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) );
    }

    // 2. Validar número de teléfono del destinatario.
    if ( ! is_string( $recipient_identifier ) || ! preg_match( '/^\+?[0-9]{5,}$/', $recipient_identifier ) ) {
        //crm_log( "[External Send V5] Error: Identificador de destinatario '{$recipient_identifier}' NO ES UN NÚMERO DE TELÉFONO VÁLIDO.", 'ERROR' );
        return new WP_Error( 'invalid_recipient_phone_format', __( 'El número de teléfono del destinatario no es válido. Debe incluir prefijo de país y tener al menos 5 dígitos.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) );
    }
    $recipient_phone_number = sanitize_text_field( $recipient_identifier );

    // 3. Obtener perfil del contacto desde Evolution API
    //crm_log( "[External Send V5] Obteniendo perfil para número: {$recipient_phone_number} desde instancia {$target_instance_name}", 'INFO' );
    $profile_endpoint = "/chat/fetchProfile/{$target_instance_name}";
    $profile_body = ['number' => $recipient_phone_number];
    $profile_response = crm_evolution_api_request( 'POST', $profile_endpoint, $profile_body );

    $contact_user_id = 0;
    // JID por defecto si fetchProfile falla o el número no existe en WA. Se construye a partir del número original.
    $contact_jid_to_process = preg_replace( '/[^0-9]/', '', $recipient_phone_number ) . '@s.whatsapp.net';
    $contact_push_name = null;
    $contact_avatar_url = null;
    $process_wp_user = false; // Flag para decidir si procesar el usuario en WP

    if ( !is_wp_error( $profile_response ) && isset( $profile_response['numberExists'] ) && $profile_response['numberExists'] === true ) {
        // El número existe en WhatsApp y tenemos datos de perfil
        $contact_jid_to_process = isset($profile_response['wuid']) ? sanitize_text_field($profile_response['wuid']) : $contact_jid_to_process;
        $contact_push_name = isset($profile_response['name']) ? sanitize_text_field($profile_response['name']) : null;
        $contact_avatar_url = isset($profile_response['picture']) ? esc_url_raw($profile_response['picture']) : null;
        $process_wp_user = true; // Marcar para procesar usuario WP
        //crm_log( "[External Send V5] Perfil obtenido y número existe: JID='{$contact_jid_to_process}', Name='{$contact_push_name}'", 'INFO' );
    } elseif (is_wp_error( $profile_response )) {
        //crm_log( "[External Send V5] Error al obtener perfil para {$recipient_phone_number}: " . $profile_response->get_error_message(), 'WARN' );
        // Se usará $contact_jid_to_process por defecto. No se procesará usuario WP.
    } else {
        //crm_log( "[External Send V5] El número {$recipient_phone_number} no existe en WhatsApp según fetchProfile o respuesta inválida.", 'WARN', $profile_response );
        // Se usará $contact_jid_to_process por defecto. No se procesará usuario WP.
    }

    // 4. Procesar/Crear Usuario WP (solo si el número existe en WA y tenemos perfil)
    if ( $process_wp_user ) {
        // La función crm_instances_process_single_jid está en crm-instances.php
        if ( function_exists( 'crm_instances_process_single_jid' ) ) {
            //crm_log( "[External Send V5] Procesando usuario WP para JID: {$contact_jid_to_process}, PushName: {$contact_push_name}", 'INFO' );
            // El cuarto parámetro de crm_instances_process_single_jid ($usePushName) es true si queremos usar el pushName.
            // El quinto es la URL del avatar.
            $contact_user_id = crm_instances_process_single_jid( $contact_jid_to_process, $target_instance_name, $contact_push_name, true, $contact_avatar_url );
        } else {
            //crm_log( "[External Send V5] ADVERTENCIA: La función crm_instances_process_single_jid no está disponible. No se procesará/creará usuario WP.", 'WARN' );
        }
    }

    // 5. Preparar datos para la API Evolution (Envío de mensaje)
    $body = array(
        'number'  => $recipient_phone_number, // Usar el número de teléfono original para el envío
        'options' => array( 'delay' => 1200, 'presence' => 'composing' ),
    );
    $endpoint_suffix = '';
    $api_media_type = 'document'; // Por defecto para la API, se actualiza si hay media
    $mime_type = null; // Para CPT

    if ( ! empty( $media_url ) ) {
        // Es un mensaje multimedia
        $filetype_data = wp_check_filetype( basename( $media_url ) );
        $mime_type = $filetype_data['type'] ?? null; // Guardar para CPT

        if ( $mime_type ) {
            if ( strpos( $mime_type, 'image/' ) === 0 ) $api_media_type = 'image';
            elseif ( strpos( $mime_type, 'video/' ) === 0 ) $api_media_type = 'video';
            elseif ( strpos( $mime_type, 'audio/' ) === 0 ) $api_media_type = 'audio';
        }
        // $api_media_type se queda como 'document' si no es image/video/audio

        $body['mediaMessage'] = array(
            'mediatype' => $api_media_type,
            'media'     => esc_url_raw( $media_url ),
            'caption'   => $message_content, // El contenido del mensaje es el caption
            'fileName'  => $media_filename ?: basename( $media_url ),
        );
        $endpoint_suffix = "/message/sendMedia/{$target_instance_name}";
        //crm_log( "[External Send V5] Preparando mensaje multimedia. Tipo API: {$api_media_type}", 'INFO' );
    } else {
        // Es un mensaje de texto
        if ( empty( $message_content ) ) {
            //crm_log( "[External Send V5] Error: El contenido del mensaje de texto no puede estar vacío.", 'ERROR' );
            return new WP_Error( 'empty_message_content', __( 'El contenido del mensaje no puede estar vacío.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) );
        }
        $body['textMessage'] = array( 'text' => $message_content );
        $endpoint_suffix = "/message/sendText/{$target_instance_name}";
        $api_media_type = 'text'; // Para el CPT
        //crm_log( "[External Send V5] Preparando mensaje de texto.", 'INFO' );
    }

    // 6. Llamar a la API para enviar el mensaje
    $api_response = crm_evolution_api_request( 'POST', $endpoint_suffix, $body );

    // 7. Si la llamada a la API fue exitosa, GUARDAR el mensaje saliente en la BD
    if ( ! is_wp_error( $api_response ) && isset($api_response['key']['id']) ) {
        //crm_log( "[External Send V5] API de envío OK. Respuesta: ", 'INFO', $api_response );

        $whatsapp_message_id = sanitize_text_field( $api_response['key']['id'] );
        $message_type_for_cpt = $api_media_type; // Usar el tipo determinado (text, image, video, audio, document)

        $message_data_for_cpt = array(
            'contact_user_id'     => $contact_user_id, // ID del usuario WP (puede ser 0)
            'instance_name'       => $target_instance_name,
            'sender_jid'          => null, // Para un mensaje saliente desde API, el sender es la instancia.
            'recipient_jid'       => $contact_jid_to_process, // JID del destinatario (obtenido de fetchProfile o construido)
            'is_outgoing'         => true,
            'message_id_wa'       => $whatsapp_message_id,
            'timestamp_wa'        => time(), // Usar el tiempo actual del servidor WP
            'message_type'        => $message_type_for_cpt,
            'message_text'        => ( $media_url ? null : $message_content ), // Texto solo si no es media
            'base64_data'         => null, // No tenemos base64 en este flujo de envío
            'media_mimetype'      => $media_url ? $mime_type : null, // Mime type original si es media
            'media_caption'       => $media_url ? $message_content : null, // Caption si es media
            'is_group_message'    => false, // Asumimos que no se envían a grupos desde aquí
            'participant_jid'     => null,
        );

        // La función crm_save_chat_message está en crm-rest-api.php
        if ( function_exists( 'crm_save_chat_message' ) ) {
            $post_id = crm_save_chat_message( $message_data_for_cpt );
            if ( is_wp_error( $post_id ) ) {
                //crm_log( "[External Send V5] Error al guardar mensaje en BD para JID {$contact_jid_to_process} (User ID {$contact_user_id}): " . $post_id->get_error_message(), 'ERROR' );
            } elseif ( $post_id === 0 || (is_numeric($post_id) && get_post_type($post_id) !== 'crm_chat' && $post_id !== false) ) { // Condición de duplicado o no guardado
                //crm_log( "[External Send V5] Mensaje WA ID {$whatsapp_message_id} ya existe en BD o no se guardó. Resultado: " . print_r($post_id, true), 'INFO' );
            } else {
                //crm_log( "[External Send V5] Mensaje WA ID {$whatsapp_message_id} guardado en BD con Post ID: {$post_id}", 'INFO' );
            }
        } else {
            //crm_log( "[External Send V5] ADVERTENCIA: La función crm_save_chat_message no está disponible. Mensaje no guardado en CPT.", 'WARN' );
        }
    }

    if (is_wp_error($api_response)) {
        //crm_log( "[External Send V5] API de envío Falló. Destinatario: {$recipient_phone_number}, Instancia: {$target_instance_name}. Error: " . $api_response->get_error_message(), 'ERROR' );
    }

    return $api_response;
}
