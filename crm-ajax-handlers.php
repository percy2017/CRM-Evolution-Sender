<?php
require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');

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
       wp_send_json_success( $active_instances );
   } else {
       wp_send_json_error( array( 'message' => 'Respuesta inesperada de la API.' ) );
   }
}
add_action( 'wp_ajax_crm_get_active_instances', 'crm_get_active_instances_callback' );

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
    // $url = rtrim($server_url, '/') . '/' . ltrim($endpoint, '/');


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
 * Realiza una solicitud a la API de Evolution (Versión 2).
 * Maneja parámetros GET en la URL y datos en el cuerpo para POST/PUT/DELETE.
 *
 * @param string $endpoint El endpoint de la API a consultar.
 * @param array  $data_params Los datos a enviar (como query para GET, como body para otros métodos).
 * @param string $method   El método HTTP (POST, GET, etc.). Por defecto GET.
 * @param string|null $instanceName El nombre de la instancia a utilizar para la API Key.
 * @return array|WP_Error Los datos de la respuesta decodificados o un WP_Error en caso de fallo.
 */
function crm_evolution_api_request_v2( $endpoint, $data_params = array(), $method = 'GET', $instanceName = null ) {
    $api_url_base = get_option( 'crm_evolution_api_url', '' );
    $global_api_token = get_option( 'crm_evolution_api_token', '' );

    if ( empty( $api_url_base ) ) {
        //crm_log( 'Error V2: La URL de la API no está configurada.', 'ERROR' );
        return new WP_Error( 'api_config_error_v2', 'La URL de la API no está configurada en los ajustes.' );
    }

    // Determinar qué API Key usar basado en $instanceName
    $api_key_to_use = $global_api_token; // Por defecto, la global
    if ( ! empty( $instanceName ) ) {
        $instances_options = get_option('crm_evolution_instances', array());
        if ( isset( $instances_options[$instanceName]['apikey'] ) && ! empty( $instances_options[$instanceName]['apikey'] ) ) {
            $api_key_to_use = $instances_options[$instanceName]['apikey'];
            //crm_log( "V2: Usando API Key específica para la instancia '{$instanceName}'.", 'DEBUG' );
        } else {
            //crm_log( "V2: No se encontró API Key específica para '{$instanceName}', usando API Key global.", 'DEBUG' );
        }
    }

    if ( empty( $api_key_to_use ) ) {
         //crm_log( 'Error V2: No se encontró API Key (ni global ni específica) para la petición.', 'ERROR' );
        return new WP_Error( 'api_config_error_v2', 'La API Key no está configurada (ni global ni para la instancia).' );
    }

    $request_url = trailingslashit( $api_url_base ) . ltrim( $endpoint, '/' );
    $method = strtoupper($method); // Asegurar que el método esté en mayúsculas

    $args = array(
        'method'    => $method,
        'timeout'   => 45,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking'  => true,
        'headers'   => array(
            'apikey' => $api_key_to_use,
            // 'Content-Type' se añade abajo si hay body
        ),
        'cookies'   => array(),
        'sslverify' => false, // Manteniendo consistencia con la función original
    );

    if ( $method === 'GET' && ! empty( $data_params ) ) {
        $request_url = add_query_arg( $data_params, $request_url );
    } elseif ( in_array($method, array('POST', 'PUT', 'DELETE')) && ! empty( $data_params ) ) {
        $args['body'] = wp_json_encode( $data_params );
        $args['headers']['Content-Type'] = 'application/json'; // Añadir Content-Type solo si hay body
    }

    //crm_log( "API Request V2 - Method: {$method}, URL: {$request_url}, Args: " . print_r($args, true), 'DEBUG' );
    $response = wp_remote_request( $request_url, $args );

    if ( is_wp_error( $response ) ) {
        //crm_log( 'Error V2 en wp_remote_request: ' . $response->get_error_message(), 'ERROR' );
        return $response;
    }

    $body = wp_remote_retrieve_body( $response );
    $http_code = wp_remote_retrieve_response_code( $response );
    //crm_log( "API Response V2 - HTTP Code: {$http_code}, Body: {$body}", 'DEBUG' );

    $decoded_body = json_decode( $body, true );

    if ( $http_code >= 400 ) {
        $error_message = 'Error en la API (V2): ' . $http_code;
        if ( isset( $decoded_body['message'] ) ) {
            $error_message .= ' - ' . $decoded_body['message'];
        } elseif (isset($decoded_body['error'])) {
            $error_message .= ' - ' . $decoded_body['error'];
        }
        //crm_log( $error_message, 'ERROR' );
        return new WP_Error( 'api_error_v2', $error_message, array( 'status' => $http_code, 'response' => $decoded_body ) );
    }
    
    if ( json_last_error() !== JSON_ERROR_NONE && !empty($body) ) { // Solo error si el body no estaba vacío
        //crm_log( 'Error V2 al decodificar JSON de la respuesta: ' . json_last_error_msg() . ' - Body: ' . $body, 'ERROR' );
        return new WP_Error( 'json_decode_error_v2', 'Error al decodificar la respuesta JSON de la API (V2).', array( 'body' => $body ) );
    }

    return $decoded_body;
}

// =========================================================================
// == MANEJADORES AJAX - HISTORIAL DE CHATS ==
// =========================================================================

/**
 * AJAX Handler para obtener la lista de conversaciones recientes para la interfaz de chat.
 */
function crm_get_recent_conversations_ajax() {
    // 1. Verificar Nonce y Permisos
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ), 403 );
    }

    global $wpdb;
    $conversations = array();

    // Consulta SQL para obtener el último mensaje de cada conversación
    // Esta consulta asume que _crm_timestamp_wa es numérico o se puede castear a numérico para MAX()
    // y que _crm_contact_user_id es el ID del usuario WP.
    $query_sql = $wpdb->prepare( "
        SELECT
            p.ID as post_id,
            p.post_content as last_message_content,
            pm_user.meta_value as contact_user_id,
            pm_timestamp.meta_value as last_message_timestamp,
            pm_instance.meta_value as instance_name,
            pm_type.meta_value as message_type,
            pm_caption.meta_value as media_caption,
            pm_outgoing.meta_value as is_outgoing,
            pm_group.meta_value as is_group,
            pm_pushname.meta_value as participant_pushname
        FROM
            {$wpdb->posts} p
        INNER JOIN
            {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id AND pm_user.meta_key = '_crm_contact_user_id'
        INNER JOIN
            {$wpdb->postmeta} pm_timestamp ON p.ID = pm_timestamp.post_id AND pm_timestamp.meta_key = '_crm_timestamp_wa'
        LEFT JOIN
            {$wpdb->postmeta} pm_instance ON p.ID = pm_instance.post_id AND pm_instance.meta_key = '_crm_instance_name'
        LEFT JOIN
            {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = '_crm_message_type'
        LEFT JOIN
            {$wpdb->postmeta} pm_caption ON p.ID = pm_caption.post_id AND pm_caption.meta_key = '_crm_media_caption'
        LEFT JOIN
            {$wpdb->postmeta} pm_outgoing ON p.ID = pm_outgoing.post_id AND pm_outgoing.meta_key = '_crm_is_outgoing'
        LEFT JOIN
            {$wpdb->users} u ON pm_user.meta_value = u.ID
        LEFT JOIN 
            {$wpdb->usermeta} pm_group ON u.ID = pm_group.user_id AND pm_group.meta_key = '_crm_is_group'
        LEFT JOIN
            {$wpdb->postmeta} pm_pushname ON p.ID = pm_pushname.post_id AND pm_pushname.meta_key = '_crm_pushName'
        INNER JOIN (
            SELECT
                pm_user_inner.meta_value as contact_user_id_inner,
                MAX(CAST(pm_timestamp_inner.meta_value AS UNSIGNED)) as max_timestamp_inner
            FROM
                {$wpdb->postmeta} pm_user_inner
            INNER JOIN
                {$wpdb->postmeta} pm_timestamp_inner ON pm_user_inner.post_id = pm_timestamp_inner.post_id
                                                   AND pm_timestamp_inner.meta_key = '_crm_timestamp_wa'
            WHERE
                pm_user_inner.meta_key = '_crm_contact_user_id' AND CAST(pm_user_inner.meta_value AS UNSIGNED) > 0
            GROUP BY
                pm_user_inner.meta_value
        ) as latest_messages
            ON pm_user.meta_value = latest_messages.contact_user_id_inner
            AND CAST(pm_timestamp.meta_value AS UNSIGNED) = latest_messages.max_timestamp_inner
        WHERE
            p.post_type = %s
            AND p.post_status = 'publish'
        ORDER BY
            latest_messages.max_timestamp_inner DESC
    ", 'crm_chat' );

    $results = $wpdb->get_results( $query_sql );

    if ( $results ) {
        foreach ( $results as $row ) {
            $contact_user_id = (int) $row->contact_user_id;
            $user_data = get_userdata( $contact_user_id );

            if ( $user_data ) {
                $snippet = '';
                if ( in_array($row->message_type, ['image', 'video', 'audio', 'document', 'file']) ) {
                    $snippet = '[' . ucfirst($row->message_type) . '] ';
                    $snippet .= $row->media_caption ?: '';
                } else {
                    $snippet = $row->last_message_content;
                }

                // Si es un mensaje saliente o no es un grupo, el display_name es el del contacto.
                // Si es un mensaje entrante de un grupo y tenemos pushName, lo usamos para el snippet.
                $display_name_for_snippet = $user_data->display_name;
                if ($row->is_group && !(bool)$row->is_outgoing && !empty($row->participant_pushname)) {
                    // Para mensajes entrantes de grupo, el snippet podría ser "Participante: mensaje"
                    // Pero para la lista de chats, el nombre principal es el del grupo.
                    // El snippet del mensaje sí puede reflejar al participante.
                }

                $conversations[] = array(
                    'user_id'      => $contact_user_id,
                    'display_name' => $display_name_for_snippet,
                    'avatar_url'   => get_avatar_url( $contact_user_id, ['size' => 106] ), // 106px para sidebar
                    'last_message_snippet' => wp_trim_words( $snippet, 10, '...' ),
                    'last_message_timestamp' => (int) $row->last_message_timestamp,
                    'instance_name' => $row->instance_name ?: 'N/D',
                    'is_group' => (bool) $row->is_group,
                );
            }
        }
    }

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
    // Determinar si el chat actual es un grupo
    $is_group_chat = get_user_meta( $user_id, '_crm_is_group', true );

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
            $raw_message_content = get_the_content();
            $raw_media_caption = get_post_meta( $post_id, '_crm_media_caption', true );
            $is_outgoing = (bool) get_post_meta( $post_id, '_crm_is_outgoing', true );
            $participant_pushname = null;
            if ($is_group_chat && !$is_outgoing) {
                $participant_pushname = get_post_meta( $post_id, '_crm_pushName', true );
            }
            $messages[] = array(
                'id'            => $post_id, // ID del post del mensaje
                'text'          => crm_format_chat_message_text_to_html( $raw_message_content ),
                'timestamp'     => (int) get_post_meta( $post_id, '_crm_timestamp_wa', true ), // Timestamp original de WA
                'is_outgoing'   => $is_outgoing, // Booleano
                'type'          => get_post_meta( $post_id, '_crm_message_type', true ), // 'text', 'image', 'video', etc.
                'caption'       => crm_format_chat_message_text_to_html( $raw_media_caption ), // Caption para multimedia
                'attachment_id' => $attachment_id ? (int) $attachment_id : null,
                'attachment_url'=> $attachment_id ? wp_get_attachment_url( $attachment_id ) : null, // URL del adjunto
                'participant_pushname' => $participant_pushname,
            );
        }
        wp_reset_postdata();
    }

    //crm_log( "Enviando " . count($messages) . " mensajes para User ID: {$user_id} al frontend.", 'INFO' );
    wp_send_json_success( $messages );
}
add_action( 'wp_ajax_crm_get_conversation_messages', 'crm_get_conversation_messages_ajax' );

/**
 * Formatea el texto de un mensaje de chat a HTML.
 * Convierte marcadores de negrita, cursiva, tachado y saltos de línea.
 *
 * @param string|null $text El texto a formatear.
 * @return string El texto formateado como HTML.
 */
function crm_format_chat_message_text_to_html( $text ) {
    if ( empty( $text ) ) {
        return '';
    }

    // 1. Escapar HTML para seguridad, excepto para nuestras etiquetas permitidas después.
    // Los reemplazos de abajo generarán HTML seguro.
    $text = esc_html( $text );

    // 2. Convertir marcadores a HTML
    // Negrita: *texto* -> <strong>texto</strong>
    $text = preg_replace( '/\*(.*?)\*/s', '<strong>$1</strong>', $text );
    // Cursiva: _texto_ -> <em>texto</em>
    $text = preg_replace( '/_(.*?)_/s', '<em>$1</em>', $text );
    // Tachado: ~texto~ -> <s>texto</s>
    $text = preg_replace( '/~(.*?)~/s', '<s>$1</s>', $text );

    // 3. Convertir URLs a enlaces clickeables
    // Expresión regular para encontrar URLs (http, https, www)
    // Se asegura de no estar dentro de un atributo href o después de un >
    $url_pattern = '#(?<!href=["\'])(?<!">)(?<!\w)(https?://[^\s<>"\'()]+|www\.[^\s<>"\'()]+)#i';

    $text = preg_replace_callback( $url_pattern, function( $matches ) {
        $url_original_escaped = $matches[0]; // Ya está escapada por esc_html()
        $url_for_href = html_entity_decode( $url_original_escaped ); // Decodificar para el href

        // Añadir http:// a las URLs que empiezan con www. para el href
        if ( stripos( $url_for_href, 'www.' ) === 0 ) {
            $url_for_href = 'http://' . $url_for_href;
        }

        // Crear el enlace
        return '<a href="' . esc_url( $url_for_href ) . '" target="_blank" rel="noopener noreferrer">' . $url_original_escaped . '</a>';
    }, $text );


    // 4. Convertir saltos de línea a <br>
    $text = nl2br( $text );

    return $text;
}

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
    // Solo actuar si estamos en la página de historial de chats
    if ( strpos( $screen_id, 'crm-evolution-chat-history' ) === false ) {
        return $response; // Salir si no es la página de chat
    }

    // Ya no procesamos crm_current_open_chat_id, crm_last_message_timestamp, ni crm_last_chat_check
    // para las actualizaciones del chat, ya que Socket.IO se encarga de esto.
    // error_log( "[Heartbeat PHP - CRM Chat] Recibido pulso en '{$screen_id}'. Ya no se procesan datos de chat aquí debido a Socket.IO." );

    return $response;
}
add_filter( 'heartbeat_received', 'crm_handle_heartbeat_request', 10, 3 );
add_filter( 'heartbeat_nopriv_received', 'crm_handle_heartbeat_request', 10, 3 );

/**
 * AJAX Handler para buscar usuarios de WP por nombre/email/login que tengan teléfono,
 * para iniciar un nuevo chat.
 */
function crm_search_wp_users_for_chat_callback() {
    // 1. Verificar Nonce y Permisos
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) { 
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ), 403 );
    }

    // 2. Obtener y sanitizar término de búsqueda
    $search_term = isset( $_POST['search_term'] ) ? sanitize_text_field( wp_unslash( $_POST['search_term'] ) ) : '';

    // 3. Validar longitud mínima
    if ( strlen( $search_term ) < 3 ) {
        //crm_log( 'Término de búsqueda demasiado corto: ' . $search_term, 'DEBUG' );
        wp_send_json_success( array() ); // Devolver array vacío si es corto
    }

    // 4. Preparar WP_User_Query
    $args = array(
        'search'         => '*' . esc_attr( $search_term ) . '*', // El filtro usará esto
        // 'search_columns' se elimina, el filtro lo maneja
        'number'         => 10, // Limitar resultados
        'fields'         => 'all_with_meta', // Obtener todos los datos y metadatos
        'meta_query'     => array(
            array( // Asegurar que tengan teléfono
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
 */
function crm_send_whatsapp_message( $user_id, $message_text ) {
    //crm_log( "Intentando enviar mensaje a User ID: {$user_id}", 'INFO' );

    // 1. Obtener JID del destinatario
    $recipient_jid = get_user_meta( $user_id, '_crm_whatsapp_jid', true );
    if ( empty( $recipient_jid ) ) {
        //crm_log( "Error: No se encontró JID para User ID {$user_id}.", 'ERROR' );
        return new WP_Error( 'jid_not_found', 'No se encontró el número de WhatsApp (JID) para este usuario.' );
    }

    // 2. Obtener la instancia específica del contacto
    $instance_name_contact = get_user_meta( $user_id, '_crm_instance_name', true );

    if ( empty( $instance_name_contact ) ) {
        return new WP_Error( 'instance_not_defined_for_contact', 'Este contacto no tiene una instancia de envío configurada.' );
    }

    // 3. Verificar el estado de la instancia específica del contacto
    $is_contact_instance_active = false;
    $instances_response = crm_evolution_api_request( 'GET', '/instance/fetchInstances' );

    if ( !is_wp_error( $instances_response ) && is_array( $instances_response ) ) {
        foreach ( $instances_response as $instance ) {
            if ( isset($instance['instance']['instanceName']) && $instance['instance']['instanceName'] === $instance_name_contact ) {
                $status = isset($instance['instance']['status']) ? $instance['instance']['status'] : 'unknown';
                if ( in_array($status, ['open', 'connected', 'connection']) ) {
                    $is_contact_instance_active = true;
                } else { 

                }
                break;
            }
        }
    } elseif (is_wp_error( $instances_response )) {
        return new WP_Error( 'fetch_instances_failed', 'Error al obtener el estado de las instancias: ' . $instances_response->get_error_message() );
    }

    if ( !$is_contact_instance_active ) {
        return new WP_Error( 'contact_instance_not_active', "La instancia '{$instance_name_contact}' asociada a este contacto no está conectada o no se encuentra." );
    }

    // 4. Preparar datos para la API Evolution (/message/sendText) usando la instancia del contacto
    $endpoint = "/message/sendText/{$instance_name_contact}";
    $body = array(
        'number'        => $recipient_jid,
        'options'       => array( 'delay' => 1200, 'presence' => 'composing' ), // Delay y simular escritura
        'textMessage'   => array( 'text' => $message_text ),
    );

    // 4. Llamar a la API
    $api_response = crm_evolution_api_request( 'POST', $endpoint, $body );

    // 5. Si la llamada a la API fue exitosa, guardar el mensaje saliente en la BD
    if ( ! is_wp_error( $api_response ) ) {
        $whatsapp_message_id = isset( $api_response['key']['id'] ) ? sanitize_text_field( $api_response['key']['id'] ) : null;

        $message_data = array(
            'user_id'             => $user_id,
            'instance_name'       => $instance_name_contact, // Usar la instancia del contacto
            'message_text'        => $message_text,
            'timestamp'           => time(), // Usar timestamp actual del servidor
            'is_outgoing'         => true,
            'message_type'        => 'text',
            'whatsapp_message_id' => $whatsapp_message_id,
            'attachment_url'      => null, // No hay adjunto para texto
            'caption'             => null,
        );

        $meta_input_prepared = crm_prepare_chat_message_meta( $message_data );
        $post_id = wp_insert_post( array(
            'post_type'   => 'crm_chat',
            'post_status' => 'publish', // Publicar inmediatamente
            'post_title'  => 'Mensaje Saliente - ' . $user_id . ' - ' . time(), // Título simple
            'post_content'=> $message_text, // <-- AÑADIR EL CONTENIDO DEL MENSAJE
            'meta_input'  => $meta_input_prepared,
        ) );

        if ( is_wp_error( $post_id ) ) {

        } else {
            // Construir el objeto del mensaje para el frontend
            $message_for_frontend = array(
                'id'            => $post_id,
                'text'          => crm_format_chat_message_text_to_html( $message_text ),
                'timestamp'     => $message_data['timestamp'], // Usar el timestamp que se guardó
                'is_outgoing'   => true,
                'type'          => 'text',
                'caption'       => crm_format_chat_message_text_to_html( null ), // Formatear aunque sea null
                'attachment_id' => null,
                'attachment_url'=> null,
            );

            return array(
                'api_response' => $api_response,
                'sent_message_data' => $message_for_frontend
            );
        }
    } else {

    }

    return $api_response;
}

/**
 * Envía un mensaje multimedia a un usuario WP usando la API Evolution.
 */
function crm_send_whatsapp_media_message( $user_id, $attachment_url, $mime_type, $filename, $caption, $wp_attachment_id = null ) {

    // 1. Obtener JID del destinatario (igual que para texto)
    $recipient_jid = get_user_meta( $user_id, '_crm_whatsapp_jid', true );
    if ( empty( $recipient_jid ) ) {
        return new WP_Error( 'jid_not_found', 'No se encontró el número de WhatsApp (JID) para este usuario.' );
    }

    // 2. Obtener la instancia específica del contacto
    $instance_name_contact = get_user_meta( $user_id, '_crm_instance_name', true );

    if ( empty( $instance_name_contact ) ) {
        return new WP_Error( 'instance_not_defined_for_contact_media', 'Este contacto no tiene una instancia de envío configurada para multimedia.' );
    }

    // 3. Verificar el estado de la instancia específica del contacto
    $is_contact_instance_active = false;
    $instances_response = crm_evolution_api_request( 'GET', '/instance/fetchInstances' );

    if ( !is_wp_error( $instances_response ) && is_array( $instances_response ) ) {
        foreach ( $instances_response as $instance ) {
            if ( isset($instance['instance']['instanceName']) && $instance['instance']['instanceName'] === $instance_name_contact ) {
                $status = isset($instance['instance']['status']) ? $instance['instance']['status'] : 'unknown';
                if ( in_array($status, ['open', 'connected', 'connection']) ) {
                    $is_contact_instance_active = true;
                 
                } else {
                   
                }
                break;
            }
        }
    } elseif (is_wp_error( $instances_response )) {
        return new WP_Error( 'fetch_instances_failed_media', 'Error al obtener el estado de las instancias para multimedia: ' . $instances_response->get_error_message() );
    }

    if ( !$is_contact_instance_active ) {
        return new WP_Error( 'contact_instance_not_active_media', "La instancia '{$instance_name_contact}' asociada a este contacto no está conectada o no se encuentra para enviar multimedia." );
    }

    // 4. Determinar el tipo de media para la API Evolution
    $media_type = 'document'; // Tipo por defecto
    if ( strpos( $mime_type, 'image/' ) === 0 ) $media_type = 'image';
    elseif ( strpos( $mime_type, 'video/' ) === 0 ) $media_type = 'video';
    elseif ( strpos( $mime_type, 'audio/' ) === 0 ) $media_type = 'audio';

    // 4. Preparar datos para la API Evolution (/message/sendMedia)
    // Usar la instancia específica del contacto
    $endpoint = "/message/sendMedia/{$instance_name_contact}";
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

    // 6. Si la llamada a la API fue exitosa
    if ( ! is_wp_error( $api_response ) ) {
        $whatsapp_message_id = isset( $api_response['key']['id'] ) ? sanitize_text_field( $api_response['key']['id'] ) : null;
        $message_data = array(
            'user_id'             => $user_id,
            'instance_name'       => $instance_name_contact, // Usar la instancia del contacto
            'message_text'        => null, // No hay texto principal, solo caption
            'timestamp'           => time(),
            'is_outgoing'         => true,
            'message_type'        => $media_type, // Usar el tipo detectado
            'whatsapp_message_id' => $whatsapp_message_id,
            'attachment_url'      => $attachment_url, // Guardar URL
            'caption'             => $caption,        // Guardar caption
            'wp_attachment_id'    => $wp_attachment_id, // Guardar el ID del adjunto de WP
        );
        $post_id = wp_insert_post( array(
            'post_type'   => 'crm_chat',
            'post_status' => 'publish',
            'post_title'  => 'Mensaje Multimedia Saliente - ' . $user_id . ' - ' . time(),
            'meta_input'  => crm_prepare_chat_message_meta( $message_data ),
        ) );
        if ( is_wp_error( $post_id ) ) {
            
        } else {
            // Construir el objeto del mensaje para el frontend
            // Para multimedia saliente, el 'attachment_id' local podría no ser relevante si solo se envía la URL.
            // El frontend usará 'attachment_url' directamente para la vista previa optimista.
            $message_for_frontend = array(
                'id'            => $post_id,
                'text'          => crm_format_chat_message_text_to_html( null ), // Formatear aunque sea null
                'timestamp'     => $message_data['timestamp'], // Usar el timestamp que se guardó
                'is_outgoing'   => true,
                'type'          => $media_type, // El tipo de media detectado (image, video, etc.)
                'caption'       => crm_format_chat_message_text_to_html( $caption ),
                'attachment_id' => $wp_attachment_id ? (int) $wp_attachment_id : null, // Usar el ID de WP si está disponible
                'attachment_url'=> $attachment_url, // La URL que se envió a la API
            );
            return array(
                'api_response' => $api_response,
                'sent_message_data' => $message_for_frontend
            );
        }
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
    if ( isset( $data['is_outgoing'] ) ) $meta_input['_crm_is_outgoing'] = (bool) $data['is_outgoing'];
    if ( isset( $data['whatsapp_message_id'] ) ) $meta_input['_crm_message_id_wa'] = sanitize_text_field( $data['whatsapp_message_id'] );
    if ( isset( $data['timestamp'] ) ) $meta_input['_crm_timestamp_wa'] = absint( $data['timestamp'] );
    if ( isset( $data['message_type'] ) ) $meta_input['_crm_message_type'] = sanitize_text_field( $data['message_type'] );
    if ( isset( $data['attachment_url'] ) ) $meta_input['_crm_attachment_url'] = esc_url_raw( $data['attachment_url'] ); // Guardar URL adjunto
    if ( isset( $data['caption'] ) ) $meta_input['_crm_caption'] = sanitize_textarea_field( $data['caption'] );
    if ( isset( $data['wp_attachment_id'] ) && !empty( $data['wp_attachment_id'] ) ) {
        $meta_input['_crm_media_attachment_id'] = absint( $data['wp_attachment_id'] );
    }
    return $meta_input;
}

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
    $wp_attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : null; // Obtener el ID del adjunto de WP

    // 3. Validar datos
    if ( $user_id <= 0 || empty( $attachment_url ) || empty( $mime_type ) ) {
        //crm_log( 'Error AJAX: Datos inválidos para enviar multimedia.', 'ERROR', $_POST );
        wp_send_json_error( array( 'message' => 'Faltan datos necesarios para enviar el archivo (ID usuario, URL o tipo MIME).' ), 400 );
    }

    // 4. Llamar a la función que realmente envía el mensaje multimedia
    $result = crm_send_whatsapp_media_message( $user_id, $attachment_url, $mime_type, $filename, $caption, $wp_attachment_id );

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
add_action( 'wp_ajax_crm_send_media_message_ajax', 'crm_send_media_message_ajax' );

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
    // --- INICIO: Obtener metadatos adicionales ---
    $is_group = (bool) get_user_meta( $user_id, '_crm_is_group', true );
    $is_favorite = (bool) get_user_meta( $user_id, '_crm_is_favorite', true );
    $instance_name = get_user_meta( $user_id, '_crm_instance_name', true );
    $is_business = (bool) get_user_meta( $user_id, '_crm_isBusiness', true );
    $description = get_user_meta( $user_id, '_crm_description', true );
    $website = get_user_meta( $user_id, '_crm_website', true );
    // --- FIN: Obtener metadatos adicionales ---

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
        'is_group'     => $is_group, // <-- LÍNEA AÑADIDA AQUÍ
        'jid'          => $jid ?: 'N/D',
        'tag_key'      => $tag_key ?: '', // Enviar clave para posible edición
        'tag_name'     => $tag_name ?: 'Sin etiqueta', // Nombre legible
        'notes'        => $notes ?: '', // Notas
        'avatar_url'   => get_avatar_url( $user_id, ['size' => 96] ), // Avatar
        // Nuevos datos
        'first_name'        => $user_data->first_name,
        'last_name'         => $user_data->last_name,
        'role'              => !empty($user_data->roles) ? translate_user_role( $user_data->roles[0] ) : 'N/A',
        'role_key'          => !empty($user_data->roles) ? $user_data->roles[0] : '', // Clave del rol
        'registration_date' => $registration_date,
        // --- INICIO: Añadir nuevos metadatos a la respuesta ---
        'is_favorite'       => $is_favorite,
        'instance_name'     => $instance_name ?: 'N/D',
        'is_business'       => $is_business,
        'description'       => $description ?: '',
        'website'           => $website ?: '',
        // --- FIN: Añadir nuevos metadatos a la respuesta ---
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

    // --- INICIO: Manejo de Actualización de Rol ---
    $new_role_key = isset( $_POST['role_key'] ) ? sanitize_key( $_POST['role_key'] ) : null;
    if ( $new_role_key && current_user_can( 'promote_user', $user_id ) ) { // Verificar capacidad para promover
        $user_to_update = get_user_by( 'ID', $user_id );
        if ( $user_to_update && in_array( $new_role_key, array_keys( get_editable_roles() ) ) ) {
            $user_to_update->set_role( $new_role_key );
        }
    }
    // --- FIN: Manejo de Actualización de Rol ---

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

/**
 * AJAX Handler para obtener la lista de roles de WordPress editables.

 * Llamado desde app.js para el sidebar de detalles del contacto, en modo edición.
 */
function crm_get_wordpress_roles_callback() {
    //crm_log( 'Recibida petición AJAX: crm_get_wordpress_roles' );

    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'edit_users' ) ) { // Capacidad para editar usuarios/roles
        //crm_log( 'Error AJAX: Permiso denegado para crm_get_wordpress_roles.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ), 403 );
    }

    $editable_roles = get_editable_roles();
    $roles_for_select = array();

    if ( ! empty( $editable_roles ) ) {
        foreach ( $editable_roles as $role_key => $role_details ) {
            $roles_for_select[] = array(
                'key'  => $role_key,
                'name' => translate_user_role( $role_details['name'] ), // Usar translate_user_role para el nombre
            );
        }
    }

    //crm_log( "Enviando " . count($roles_for_select) . " roles de WordPress para select.", 'INFO' );
    wp_send_json_success( $roles_for_select );
}
add_action( 'wp_ajax_crm_get_wordpress_roles', 'crm_get_wordpress_roles_callback' );

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
    error_log( "[External Send V5 - FetchProfile & NumberExists] Iniciando. Destinatario: {$recipient_identifier}, Instancia: {$target_instance_name}" );

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

/**
 * Procesa un número de teléfono: busca en todas las instancias activas,
 * obtiene el perfil de WhatsApp, y luego crea o actualiza el usuario en WordPress.
 */
function crm_process_whatsapp_contact_by_phone($phone_number_from_ui) {
    if (empty($phone_number_from_ui)) {
        return new WP_Error('missing_phone_number_main', 'Número de teléfono no proporcionado.');
    }
    error_log("[CRM Process Contact] Iniciando para número: {$phone_number_from_ui}");

    // 1. Obtener la URL base y el token global de la API de Evolution
    $api_url_base = get_option('crm_evolution_api_url');
    $api_token = get_option('crm_evolution_api_token');
    error_log("[CRM Process Contact] DEBUG: API URL Base (get_option): " . $api_url_base);
    error_log("[CRM Process Contact] DEBUG: API Token Global (get_option): " . ($api_token ? 'Token Presente' : 'Token VACÍO'));

    if (empty($api_url_base) || empty($api_token)) {
        return new WP_Error('api_config_error_main', 'La configuración de la API de Evolution no está completa.');
    }

    // 2. Realizar la llamada directa a /instance/fetchInstances usando wp_remote_request
    $instances_endpoint = rtrim($api_url_base, '/') . '/instance/fetchInstances';
    $args_instances = array(
        'method'    => 'GET',
        'timeout'   => 30,
        'redirection' => 5,
        'httpversion' => '1.1',
        'blocking'  => true,
        'headers'   => array(
            'apikey' => $api_token, // Usar el token global
            'Content-Type' => 'application/json',
        ),
        'cookies'   => array(),
        'sslverify' => false,
    );

    error_log("[CRM Process Contact] Request directa a /instance/fetchInstances. URL: {$instances_endpoint}, Headers: " . print_r($args_instances['headers'], true));
    $instances_response_raw = wp_remote_request($instances_endpoint, $args_instances);

    error_log("[CRM Process Contact] Respuesta CRUDA directa para /instance/fetchInstances: " . print_r($instances_response_raw, true));

    // 3. Manejar la respuesta de /instance/fetchInstances
    if (is_wp_error($instances_response_raw)) {
        error_log("[CRM Process Contact] WP_Error en llamada directa a /instance/fetchInstances: " . $instances_response_raw->get_error_message());
        return new WP_Error('fetch_instances_failed_wp_error', 'Error al conectar con la API para obtener instancias: ' . $instances_response_raw->get_error_message());
    }

    $instances_body = wp_remote_retrieve_body($instances_response_raw);
    $instances_http_code = wp_remote_retrieve_response_code($instances_response_raw);
    $instances_decoded_body = json_decode($instances_body, true);

    error_log("[CRM Process Contact] Respuesta /instance/fetchInstances - HTTP Code: {$instances_http_code}, Body: {$instances_body}");

    if ($instances_http_code < 200 || $instances_http_code >= 300 || !is_array($instances_decoded_body) || empty($instances_decoded_body)) {
        $error_message_detail = "HTTP Code: {$instances_http_code}. ";
        if (!empty($instances_body)) {
            $error_message_detail .= "Body: " . esc_html(substr($instances_body, 0, 200));
        } else {
            $error_message_detail .= "Cuerpo de respuesta vacío o no es un array.";
        }
        error_log("[CRM Process Contact] Error al obtener instancias: Respuesta inesperada. " . $error_message_detail);
        return new WP_Error('fetch_instances_failed_format', 'Respuesta inesperada de la API al obtener instancias: ' . $error_message_detail);
    }

    // Si llegamos aquí, la respuesta es válida y $instances_decoded_body contiene el array de instancias.
    $all_instances = $instances_decoded_body;
    $found_profile_api_data = null;
    $instance_that_found_profile = null;

    // 4. Bucle: Iterar sobre cada instancia activa, llamando a fetchProfile
    foreach ($all_instances as $instance_data_loop) {
        if (isset($instance_data_loop['instance']['status']) && $instance_data_loop['instance']['status'] === 'open') {
            $current_instance_name = $instance_data_loop['instance']['instanceName'];
            $profile_endpoint_loop = '/chat/fetchProfile/' . $current_instance_name;
            $payload_loop = ['number' => $phone_number_from_ui];
            
            error_log("[CRM Process Contact] Intentando fetchProfile para {$phone_number_from_ui} en instancia {$current_instance_name}");
            // Usamos crm_evolution_api_request_v2 para esta llamada, asumiendo que maneja bien POST y la API Key de instancia si es necesario
            $profile_response_loop = crm_evolution_api_request_v2($profile_endpoint_loop, $payload_loop, 'POST', $current_instance_name);

            // Verificar la respuesta de fetchProfile directamente por 'numberExists'
            if (!is_wp_error($profile_response_loop) && isset($profile_response_loop['numberExists']) && $profile_response_loop['numberExists'] === true) {
                $found_profile_api_data = $profile_response_loop; // La respuesta es el perfil directamente
                $instance_that_found_profile = $current_instance_name;
                error_log("[CRM Process Contact] Perfil encontrado para {$phone_number_from_ui} en instancia {$instance_that_found_profile}.");
                break; 
            } else {
                $error_detail = 'Respuesta inesperada o número no existe.';
                if (is_wp_error($profile_response_loop)) {
                    $error_detail = 'WP_Error: ' . $profile_response_loop->get_error_message();
                } elseif (isset($profile_response_loop['numberExists']) && $profile_response_loop['numberExists'] === false) {
                    $error_detail = 'API indica que el número no existe (numberExists: false).';
                } elseif (isset($profile_response_loop['message'])) {
                    $error_detail = 'API Message: ' . $profile_response_loop['message'];
                } elseif (isset($profile_response_loop['error'])) {
                    $error_detail = 'API Error: ' . $profile_response_loop['error'];
                }
                error_log("[CRM Process Contact] No se encontró perfil para {$phone_number_from_ui} en instancia {$current_instance_name}. Detalle: {$error_detail}");
            }
        }
    }

    // 5. Si ninguna instancia devuelve numberExists: true: Envía un error.
    if (!$found_profile_api_data) {
        error_log("[CRM Process Contact] El número {$phone_number_from_ui} no tiene WhatsApp o no se encontró en las instancias activas.");
        return new WP_Error('profile_not_found_in_any_instance_main', 'El número no tiene WhatsApp o no se pudo verificar en las instancias activas.');
    }

    // 6. Perfil encontrado en WhatsApp. Obtener datos.
    $api_profile_data = $found_profile_api_data; 

    if (empty($api_profile_data) || !isset($api_profile_data['wuid'])) {
        error_log('[CRM Process Contact] Error: Datos del perfil de API incompletos o inválidos (falta wuid). Data: ' . print_r($api_profile_data, true));
        return new WP_Error('invalid_profile_data_main_wuid', 'Datos del perfil de API incompletos o inválidos (falta wuid).');
    }

    $jid_from_api = sanitize_text_field($api_profile_data['wuid']);
    $name_from_api = isset($api_profile_data['name']) ? sanitize_text_field($api_profile_data['name']) : ($api_profile_data['verifiedName'] ?? '');
    $profile_pic_url = isset($api_profile_data['picture']) ? esc_url_raw($api_profile_data['picture']) : '';
    $is_business = isset($api_profile_data['isBusiness']) ? (bool) $api_profile_data['isBusiness'] : null;
    $description = isset($api_profile_data['description']) ? sanitize_text_field($api_profile_data['description']) : null;
    
    $phone_number_parts = explode('@', $jid_from_api);
    $phone_number_clean = $phone_number_parts[0];

    // 7. Buscar si el usuario YA EXISTE en WordPress por el JID de la API
    $user_id = 0;
    $users_by_jid = get_users([
        'meta_key'   => '_crm_whatsapp_jid',
        'meta_value' => $jid_from_api,
        'number'     => 1,
        'fields'     => 'ID'
    ]);

    if (!empty($users_by_jid)) {
        $user_id = $users_by_jid[0];
    }

    $first_name_to_set = '';
    $last_name_to_set = '';
    if (!empty($name_from_api)) {
        $name_parts = explode(' ', $name_from_api, 2);
        $first_name_to_set = $name_parts[0];
        $last_name_to_set = isset($name_parts[1]) ? $name_parts[1] : '';
    }
    $user_id_to_return = 0;

    if ($user_id) {
        // 8.A. Usuario WP EXISTE. Actualizar sus datos.
        error_log("[CRM Process Contact] Usuario YA EXISTE en WP para JID {$jid_from_api}. User ID: {$user_id}. Actualizando datos.");
        
        $update_data_wp = [
            'ID'           => $user_id,
            'first_name'   => $first_name_to_set,
            'last_name'    => $last_name_to_set,
            'display_name' => !empty($name_from_api) ? $name_from_api : ('wa_' . $phone_number_clean),
        ];
        wp_update_user($update_data_wp);

        update_user_meta($user_id, '_crm_instance_name', $instance_that_found_profile);
        if ($is_business !== null) update_user_meta($user_id, '_crm_isBusiness', $is_business);
        if ($description !== null) update_user_meta($user_id, '_crm_description', $description);
        update_user_meta($user_id, 'billing_phone', $phone_number_clean);
        update_user_meta($user_id, '_crm_whatsapp_jid', $jid_from_api);

        $user_id_to_return = (int) $user_id;

    } else {
        // 8.B. Usuario WP NO EXISTE. Proceder a CREARLO.
        error_log("[CRM Process Contact] Usuario NO existe en WP para JID {$jid_from_api}. Creando nuevo usuario.");

        $username = 'wa_' . $phone_number_clean;
        if (username_exists($username)) {
            $username .= '_' . wp_rand(100, 999); 
        }
        $email = $phone_number_clean . '@whatsapp.placeholder'; 
        if (email_exists($email)) {
             $email = $phone_number_clean . '_' . wp_rand(100,999) . '@whatsapp.placeholder';
        }

        $user_data_to_create = [
            'user_login'    => $username,
            'user_email'    => $email,
            'user_pass'     => wp_generate_password(),
            'role'          => 'subscriber', 
            'first_name'    => $first_name_to_set,
            'last_name'     => $last_name_to_set,
            'display_name'  => !empty($name_from_api) ? $name_from_api : $username,
        ];

        $new_user_id = wp_insert_user($user_data_to_create);

        if (is_wp_error($new_user_id)) {
            error_log('[CRM Process Contact] Error al crear usuario WP: ' . $new_user_id->get_error_message());
            return $new_user_id; 
        }

        update_user_meta($new_user_id, '_crm_whatsapp_jid', $jid_from_api);
        update_user_meta($new_user_id, 'billing_phone', $phone_number_clean); 
        update_user_meta($new_user_id, 'billing_first_name', $first_name_to_set);
        update_user_meta($new_user_id, 'billing_last_name', $last_name_to_set);
        update_user_meta($new_user_id, '_crm_is_group', false);
        update_user_meta($new_user_id, '_crm_is_favorite', false);
        update_user_meta($new_user_id, '_crm_instance_name', $instance_that_found_profile);
        if ($is_business !== null) update_user_meta($new_user_id, '_crm_isBusiness', $is_business);
        if ($description !== null) update_user_meta($new_user_id, '_crm_description', $description);
        
        if (function_exists('crm_get_lifecycle_tags')) {
            $lifecycle_tags = crm_get_lifecycle_tags(); 
            if (!empty($lifecycle_tags)) {
                $first_tag_key = array_key_first($lifecycle_tags);
                if ($first_tag_key) {
                    update_user_meta($new_user_id, '_crm_lifecycle_tag', $first_tag_key);
                }
            }
        }
        $user_id_to_return = (int) $new_user_id;
        error_log("[CRM Process Contact] Nuevo usuario creado con ID: {$user_id_to_return} para JID {$jid_from_api}");
    }

    // 9. Descargar y asignar/actualizar avatar
    if ($user_id_to_return > 0 && !empty($profile_pic_url)) {
        $user_for_avatar = get_userdata($user_id_to_return);
        $display_name_for_avatar = $user_for_avatar ? $user_for_avatar->display_name : 'Avatar Contacto';
        $attachment_id = media_sideload_image($profile_pic_url, 0, $display_name_for_avatar, 'id');

        if (!is_wp_error($attachment_id)) {
            $old_avatar_id = get_user_meta($user_id_to_return, '_crm_avatar_attachment_id', true);
            if ($old_avatar_id && $old_avatar_id != $attachment_id) {
                wp_delete_attachment(intval($old_avatar_id), true);
                error_log("[CRM Process Contact] Avatar anterior (ID: {$old_avatar_id}) eliminado para User ID {$user_id_to_return}.");
            }
            update_user_meta($user_id_to_return, '_crm_avatar_attachment_id', $attachment_id);
            error_log("[CRM Process Contact] Avatar actualizado/asignado para User ID {$user_id_to_return}. Nuevo Attachment ID: {$attachment_id}");
        } else {
            error_log("[CRM Process Contact] Error al descargar/guardar avatar para User ID {$user_id_to_return} desde {$profile_pic_url}: " . $attachment_id->get_error_message());
        }
    }
    return $user_id_to_return;
}

/**
 * AJAX Handler para el proceso de "Nuevo Chat".
 * Recibe un número de teléfono, llama a crm_process_whatsapp_contact_by_phone
 * y devuelve los datos del usuario WP al frontend.
 */
function crm_fetch_whatsapp_profile_ajax_callback() {
    check_ajax_referer('crm_evolution_sender_nonce', '_ajax_nonce'); 

    $phone_number = isset($_POST['phone_number']) ? sanitize_text_field(wp_unslash($_POST['phone_number'])) : '';

    if (empty($phone_number)) {
        wp_send_json_error(['message' => 'Número de teléfono no proporcionado.'], 400);
        return;
    }

    // Llamar a la función principal que maneja todo el flujo
    $user_id_or_error = crm_process_whatsapp_contact_by_phone($phone_number);

    if (is_wp_error($user_id_or_error)) {
        error_log("[CRM Fetch AJAX Callback] Error desde crm_process_whatsapp_contact_by_phone: " . $user_id_or_error->get_error_message());
        wp_send_json_error(['message' => 'Error al procesar el contacto: ' . $user_id_or_error->get_error_message()], 500);
    } else {
        $user_id = (int) $user_id_or_error;
        $wp_user = get_userdata($user_id);
        $avatar_url = get_avatar_url($user_id); 
        $contact_jid = $wp_user ? get_user_meta($user_id, '_crm_whatsapp_jid', true) : null;

        error_log("[CRM Fetch AJAX Callback] Éxito. User ID: {$user_id}, JID: {$contact_jid}");
        wp_send_json_success([
            'message'       => 'Contacto procesado correctamente.',
            'user_id'       => $user_id,
            'display_name'  => $wp_user ? $wp_user->display_name : 'N/A',
            'avatar_url'    => $avatar_url,
            'jid'           => $contact_jid, 
        ]);
    }
}
add_action('wp_ajax_crm_fetch_whatsapp_profile_ajax', 'crm_fetch_whatsapp_profile_ajax_callback');

/**
 * AJAX Handler para obtener todas las instancias de Evolution API.
 * Llamado desde app.js para poblar el menú de filtro de instancias.
 */
function crm_get_all_evolution_instances_ajax_callback() {
    check_ajax_referer('crm_evolution_sender_nonce', '_ajax_nonce');
    error_log("[CRM Get Instances AJAX] Petición recibida.");

    // Usar una capacidad apropiada, por ejemplo, la misma que para ver la página de chats.
    if (!current_user_can('edit_posts')) { 
        error_log("[CRM Get Instances AJAX] Error: Permiso denegado.");
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción.'], 403);
        return;
    }

    // Obtener la URL base y el token global de la API de Evolution
    $api_url_base = get_option('crm_evolution_api_url');
    $api_token = get_option('crm_evolution_api_token');

    if (empty($api_url_base) || empty($api_token)) {
        error_log("[CRM Get Instances AJAX] Error: Configuración API incompleta.");
        wp_send_json_error(['message' => 'La configuración de la API de Evolution no está completa.'], 500);
        return;
    }

    // Realizar la llamada directa a /instance/fetchInstances usando wp_remote_request
    // (similar a como lo hicimos en crm_process_whatsapp_contact_by_phone)
    $instances_endpoint = rtrim($api_url_base, '/') . '/instance/fetchInstances';
    $args_instances = array(
        'method'    => 'GET',
        'timeout'   => 30,
        'headers'   => array('apikey' => $api_token),
        'sslverify' => false, // Ajustar según tu entorno
    );

    $instances_response_raw = wp_remote_request($instances_endpoint, $args_instances);

    if (is_wp_error($instances_response_raw)) {
        error_log("[CRM Get Instances AJAX] WP_Error al llamar a API: " . $instances_response_raw->get_error_message());
        wp_send_json_error(['message' => 'Error al conectar con la API para obtener instancias: ' . $instances_response_raw->get_error_message()], 500);
        return;
    }

    $instances_body = wp_remote_retrieve_body($instances_response_raw);
    $instances_http_code = wp_remote_retrieve_response_code($instances_response_raw);
    $instances_decoded_body = json_decode($instances_body, true);

    if ($instances_http_code < 200 || $instances_http_code >= 300 || !is_array($instances_decoded_body)) {
        error_log("[CRM Get Instances AJAX] Respuesta API inesperada. Código: {$instances_http_code}, Body: " . substr($instances_body, 0, 200));
        wp_send_json_error(['message' => 'Respuesta inesperada de la API al obtener instancias. Código: ' . $instances_http_code], 500);
        return;
    }

    // La API de Evolution para /instance/fetchInstances devuelve directamente el array de instancias
    error_log("[CRM Get Instances AJAX] Instancias obtenidas con éxito: " . count($instances_decoded_body) . " encontradas.");
    wp_send_json_success($instances_decoded_body);
}
add_action('wp_ajax_crm_get_all_evolution_instances_ajax', 'crm_get_all_evolution_instances_ajax_callback');
/**
 * AJAX Handler para marcar o desmarcar un contacto como favorito.
 */
function crm_toggle_favorite_contact_ajax_callback() {
    //crm_log( 'Recibida petición AJAX: crm_toggle_favorite_contact' );

    // 1. Verificar Nonce y Permisos
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    // Usar 'edit_posts' ya que es la capacidad para interactuar con la interfaz de chats
    if ( ! current_user_can( 'edit_posts' ) ) {
        //crm_log( 'Error AJAX: Permiso denegado para crm_toggle_favorite_contact.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ), 403 );
    }

    // 2. Obtener y sanitizar datos
    $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
    // Convertir el string 'true'/'false' o 1/0 a booleano
    $is_favorite_raw = isset( $_POST['is_favorite'] ) ? $_POST['is_favorite'] : 'false';
    $is_favorite = filter_var( $is_favorite_raw, FILTER_VALIDATE_BOOLEAN );

    // 3. Validar User ID
    if ( $user_id <= 0 ) {
        //crm_log( 'Error AJAX: User ID inválido para marcar como favorito.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'ID de usuario inválido.' ), 400 );
    }

    // 4. Actualizar el metadato del usuario
    $meta_key = '_crm_is_favorite';
    if ( update_user_meta( $user_id, $meta_key, $is_favorite ) ) {
        //crm_log( "Estado de favorito para User ID {$user_id} actualizado a: " . ($is_favorite ? 'true' : 'false'), 'INFO' );
        wp_send_json_success( array( 'message' => 'Estado de favorito actualizado correctamente.' ) );
    } else {
        //crm_log( "Error al actualizar el metadato de favorito para User ID {$user_id}.", 'ERROR' );
        wp_send_json_error( array( 'message' => 'Error al actualizar el estado de favorito.' ) );
    }
}
add_action( 'wp_ajax_crm_toggle_favorite_contact', 'crm_toggle_favorite_contact_ajax_callback' );

/**
 * AJAX Handler para eliminar un contacto (usuario WP) o un grupo (representado como usuario WP).
 * Si el contacto tiene órdenes de WooCommerce, estas se desvinculan (se convierten a invitado).
 */
function crm_delete_contact_or_group_ajax_callback() {
    //crm_log( 'Recibida petición AJAX: crm_delete_contact_or_group' );

    // 1. Verificar Nonce y Permisos
    check_ajax_referer( 'crm_evolution_sender_nonce', '_ajax_nonce' );
    // Se necesita una capacidad que permita eliminar usuarios. 'delete_users' es la más apropiada.
    if ( ! current_user_can( 'delete_users' ) ) {
        //crm_log( 'Error AJAX: Permiso denegado para crm_delete_contact_or_group.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes para eliminar usuarios/contactos.' ), 403 );
    }

    // 2. Obtener y validar User ID
    $user_id_to_delete = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
    if ( $user_id_to_delete <= 0 ) {
        //crm_log( 'Error AJAX: User ID inválido para eliminar.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'ID de usuario inválido para eliminar.' ), 400 );
    }

    // No permitir eliminar al usuario actual
    if ( get_current_user_id() == $user_id_to_delete ) {
        //crm_log( 'Error AJAX: Intento de autoeliminación.', 'ERROR' );
        wp_send_json_error( array( 'message' => 'No puedes eliminar tu propia cuenta desde aquí.' ), 400 );
    }
    
    // No permitir eliminar al usuario con ID 1 (generalmente el admin principal)
    if ( $user_id_to_delete === 1 ) {
        //crm_log( 'Error AJAX: Intento de eliminar usuario administrador principal (ID 1).', 'ERROR' );
        wp_send_json_error( array( 'message' => 'No se puede eliminar el usuario administrador principal.' ), 400 );
    }


    // 3. Si es un contacto (no un grupo) y WooCommerce está activo, desvincular órdenes
    $is_group = (bool) get_user_meta( $user_id_to_delete, '_crm_is_group', true );

    if ( ! $is_group && class_exists( 'WooCommerce' ) ) {
        //crm_log( "Contacto ID {$user_id_to_delete} no es grupo. Verificando órdenes de WooCommerce." );
        $orders = wc_get_orders( array(
            'customer_id' => $user_id_to_delete,
            'limit'       => -1, // Todas las órdenes
        ) );

        if ( ! empty( $orders ) ) {
            //crm_log( "Contacto ID {$user_id_to_delete} tiene " . count($orders) . " órdenes. Desvinculando..." );
            foreach ( $orders as $order ) {
                if ( $order instanceof WC_Order ) {
                    $order->set_customer_id( 0 ); // Establecer como orden de invitado
                    $order->save();
                    //crm_log( "Orden ID {$order->get_id()} desvinculada del User ID {$user_id_to_delete}." );
                }
            }
        }
    }

    // 4. Eliminar el usuario de WordPress
    // wp_delete_user() se encarga de eliminar los metadatos del usuario.
    // El hook 'delete_user' (crm_delete_user_avatar_on_user_delete) se encargará del avatar.
    // Los CPT 'crm_chat' cuyo post_author sea este user_id se eliminarán por defecto si no se reasignan.
    $deleted = wp_delete_user( $user_id_to_delete );

    if ( $deleted ) {
        $entity_type = $is_group ? 'Grupo' : 'Contacto';
        //crm_log( "{$entity_type} (User ID: {$user_id_to_delete}) eliminado correctamente de WordPress.", 'INFO' );
        wp_send_json_success( array( 'message' => $entity_type . ' eliminado correctamente.' ) );
    } else {
        //crm_log( "Error al eliminar el usuario/grupo (User ID: {$user_id_to_delete}) de WordPress.", 'ERROR' );
        wp_send_json_error( array( 'message' => 'Error al eliminar el usuario/grupo de WordPress.' ) );
    }
}
add_action( 'wp_ajax_crm_delete_contact_or_group', 'crm_delete_contact_or_group_ajax_callback' );
