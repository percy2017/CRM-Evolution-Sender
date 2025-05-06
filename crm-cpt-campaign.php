<?php
/**
 * Registra el Custom Post Type 'crm_sender_campaign' para las campañas de marketing.
 * Incluye la lógica de envío y llamadas API necesarias para las campañas.
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// =========================================================================
// == FUNCIONES LOCALES DE API Y ENVÍO (COPIADAS Y ADAPTADAS) ==
// =========================================================================

/**
 * Función auxiliar LOCAL para realizar peticiones a la API Evolution.
 * (Adaptada de crm-ajax-handlers.php y modificada para usar cURL)
 *
 * @param string $method Método HTTP (GET, POST, DELETE, etc.).
 * @param string $endpoint El endpoint de la API (ej: '/instance/fetchInstances').
 * @param array $body Datos para enviar en el cuerpo (para POST/PUT).
 * @param string|null $instance_api_key API Key específica de la instancia (opcional).
 * @return array|WP_Error Respuesta decodificada de la API o WP_Error en caso de fallo.
 */
function crm_campaign_api_request( $method, $endpoint, $body = [], $instance_api_key = null ) {
    $api_url_base = get_option( 'crm_evolution_api_url', '' );
    $global_api_token = get_option( 'crm_evolution_api_token', '' );

    if ( empty( $api_url_base ) ) {
        error_log( '[crm_campaign_api_request] Error: La URL de la API no está configurada.');
        return new WP_Error( 'api_config_error', 'La URL de la API no está configurada en los ajustes.' );
    }

    $api_key_to_use = $instance_api_key ? $instance_api_key : $global_api_token;

    if ( empty( $api_key_to_use ) ) {
         error_log( '[crm_campaign_api_request] Error: No se encontró API Key (ni global ni específica) para la petición.' );
        return new WP_Error( 'api_config_error', 'Se requiere una API Key (Global o específica) para realizar la petición.' );
    }

    $request_url = trailingslashit( $api_url_base ) . ltrim( $endpoint, '/' );
    error_log( "[crm_campaign_api_request] Realizando petición API: [{$method}] {$request_url}" );

    // --- INICIO: Implementación con cURL ---
    $ch = curl_init();
    $curl_opts = array(
        CURLOPT_URL => $request_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json",
            "apikey: " . $api_key_to_use,
        ),
        CURLOPT_SSL_VERIFYPEER => false, // Coincide con 'sslverify' => false
        CURLOPT_SSL_VERIFYHOST => false, // Añadido por seguridad con sslverify false
    );

    if ( ! empty( $body ) && (strtoupper($method) === 'POST' || strtoupper($method) === 'PUT' || strtoupper($method) === 'DELETE') ) {
        // *** ESTA ES LA LÍNEA CLAVE CON EL ÚLTIMO CAMBIO ***
        $json_body = json_encode( $body, JSON_UNESCAPED_SLASHES ); // <-- Añadimos JSON_UNESCAPED_SLASHES
        $curl_opts[CURLOPT_POSTFIELDS] = $json_body;
        error_log( "[crm_campaign_api_request] Cuerpo de la petición cURL (json_encode con UNESCAPED_SLASHES): " . $json_body ); // Log actualizado
    }

    curl_setopt_array($ch, $curl_opts);
    $curl_response = curl_exec($ch);
    $curl_errno = curl_errno($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    // --- FIN: Implementación con cURL ---

    // --- INICIO: Manejo de respuesta cURL ---
    if ( $curl_errno ) {
        error_log( "[crm_campaign_api_request] Error en cURL (#{$curl_errno}): " . $curl_error );
        return new WP_Error( 'curl_error', "Error de cURL: " . $curl_error, array( 'errno' => $curl_errno ) );
    }

    // Usar las variables de cURL consistentemente
    $response_code = $http_code;
    $response_body = $curl_response;
    $decoded_body = json_decode( $response_body, true );

    if ( $response_code >= 200 && $response_code < 300 ) {
        return $decoded_body !== null ? $decoded_body : [];
    } else {
        $error_message = isset( $decoded_body['message'] ) ? $decoded_body['message'] : (isset($decoded_body['error']) ? $decoded_body['error'] : $response_body);
        error_log( "[crm_campaign_api_request] Error en la API ({$response_code}): {$error_message}" );
        return new WP_Error( 'api_error', $error_message, array( 'status' => $response_code ) );
    }
    // --- FIN: Manejo de respuesta cURL ---
}


/**
 * Prepara el array de metadatos LOCAL para guardar un post 'crm_chat'.
 * (Adaptada de crm-ajax-handlers.php)
 *
 * @param array $data Array con los datos del mensaje (user_id, instance_name, etc.).
 * @return array Array formateado para el parámetro 'meta_input' de wp_insert_post.
 */
function crm_campaign_prepare_chat_message_meta( $data ) {
    $meta_input = array();

    if ( isset( $data['user_id'] ) ) $meta_input['_crm_contact_user_id'] = absint( $data['user_id'] );
    if ( isset( $data['instance_name'] ) ) $meta_input['_crm_instance_name'] = sanitize_text_field( $data['instance_name'] );
    if ( isset( $data['is_outgoing'] ) ) $meta_input['_crm_is_outgoing'] = (bool) $data['is_outgoing'];
    if ( isset( $data['whatsapp_message_id'] ) ) $meta_input['_crm_message_id_wa'] = sanitize_text_field( $data['whatsapp_message_id'] );
    if ( isset( $data['timestamp'] ) ) $meta_input['_crm_timestamp_wa'] = absint( $data['timestamp'] );
    if ( isset( $data['message_type'] ) ) $meta_input['_crm_message_type'] = sanitize_text_field( $data['message_type'] );
    if ( isset( $data['attachment_url'] ) ) $meta_input['_crm_attachment_url'] = esc_url_raw( $data['attachment_url'] );
    if ( isset( $data['caption'] ) ) $meta_input['_crm_caption'] = sanitize_textarea_field( $data['caption'] );
    // Añadir más campos si son necesarios

    return $meta_input;
}

/**
 * Envía un mensaje de texto LOCALMENTE a un usuario WP usando la API Evolution.
 * (Adaptada de crm-ajax-handlers.php)
 *
 * @param int $user_id ID del usuario WP destinatario.
 * @param string $message_text Texto del mensaje a enviar.
 * @param array $instance_names_available Nombres de las instancias seleccionadas para la campaña.
 * @return array|WP_Error Respuesta de la API o WP_Error.
 */
function crm_campaign_send_whatsapp_message( $user_id, $message_text, $instance_names_available ) {
    error_log( "[crm_campaign_send_whatsapp_message] Intentando enviar mensaje a User ID: {$user_id}" );

    $recipient_jid = get_user_meta( $user_id, '_crm_whatsapp_jid', true );
    if ( empty( $recipient_jid ) ) {
        error_log( "[crm_campaign_send_whatsapp_message] Error: No se encontró JID para User ID {$user_id}." );
        return new WP_Error( 'jid_not_found', 'No se encontró el número de WhatsApp (JID) para este usuario.' );
    }

    // --- INICIO: Lógica de Selección de Instancia ---
    $active_instance_name = null;
    if (empty($instance_names_available)) {
        error_log( "[crm_campaign_send_whatsapp_message] Error: No hay instancias seleccionadas para la campaña." );
        return new WP_Error( 'no_instance_selected', 'No se seleccionó ninguna instancia de envío para la campaña.' );
    }

    // Obtener estado actual de las instancias seleccionadas
    $instances_response = crm_campaign_api_request( 'GET', '/instance/fetchInstances' ); // Usa la función local
    $connected_instances = [];
    if ( !is_wp_error( $instances_response ) && is_array( $instances_response ) ) {
        foreach ( $instances_response as $instance ) {
            $name = isset($instance['instance']['instanceName']) ? $instance['instance']['instanceName'] : null;
            $status = isset($instance['instance']['status']) ? $instance['instance']['status'] : 'unknown';
            // Verificar si está en la lista de disponibles Y conectada
            if (in_array($name, $instance_names_available) && ($status === 'open' || $status === 'connected' || $status === 'connection')) {
                $connected_instances[] = $name;
            }
        }
    }

    if (empty($connected_instances)) {
        error_log( "[crm_campaign_send_whatsapp_message] Error: Ninguna de las instancias seleccionadas está conectada." );
        return new WP_Error( 'no_active_instance', 'Ninguna de las instancias seleccionadas está conectada para enviar el mensaje.' );
    }

    // Seleccionar una instancia aleatoria de las conectadas
    $active_instance_name = $connected_instances[array_rand($connected_instances)];
    error_log( "[crm_campaign_send_whatsapp_message] Usando instancia activa '{$active_instance_name}' para enviar." );
    // --- FIN: Lógica de Selección de Instancia ---


    $endpoint = "/message/sendText/{$active_instance_name}";
    $body = array(
        'number'        => $recipient_jid,
        'options'       => array( 'delay' => 1200, 'presence' => 'composing' ),
        'textMessage'   => array( 'text' => $message_text ),
    );

    $api_response = crm_campaign_api_request( 'POST', $endpoint, $body ); // Usa la función local

    if ( ! is_wp_error( $api_response ) ) {
        error_log( "[crm_campaign_send_whatsapp_message] Guardado DB: API OK. Preparando datos para guardar mensaje saliente User ID {$user_id}." );
        $whatsapp_message_id = isset( $api_response['key']['id'] ) ? sanitize_text_field( $api_response['key']['id'] ) : null;
        $message_data = array(
            'user_id'             => $user_id,
            'instance_name'       => $active_instance_name,
            'message_text'        => $message_text,
            'timestamp'           => time(),
            'is_outgoing'         => true,
            'message_type'        => 'text',
            'whatsapp_message_id' => $whatsapp_message_id,
            'attachment_url'      => null,
            'caption'             => null,
        );

        $meta_input_prepared = crm_campaign_prepare_chat_message_meta( $message_data ); // Usa la función local
        $post_id = wp_insert_post( array(
            'post_type'   => 'crm_chat',
            'post_status' => 'publish',
            'post_title'  => 'Mensaje Campaña - ' . $user_id . ' - ' . time(),
            'post_content'=> $message_text,
            'meta_input'  => $meta_input_prepared,
        ) );

        if ( is_wp_error( $post_id ) ) {
            error_log( "[crm_campaign_send_whatsapp_message] Error al guardar el mensaje saliente en la BD para User ID {$user_id}." );
        } else {
            error_log( "[crm_campaign_send_whatsapp_message] Mensaje saliente guardado en BD con Post ID: {$post_id}" );
        }
    } else {
         error_log( "[crm_campaign_send_whatsapp_message] Llamada API para enviar mensaje a User ID {$user_id} falló." );
    }

    return $api_response;
}

/**
 * Envía un mensaje multimedia LOCALMENTE a un usuario WP usando la API Evolution.
 * (Adaptada de crm-ajax-handlers.php)
 *
 * @param int    $user_id                  ID del usuario WP destinatario.
 * @param string $attachment_url           URL del archivo adjunto.
 * @param string $filename                 Nombre original del archivo (opcional).
 * @param string $caption                  Texto que acompaña al archivo (opcional).
 * @param array $instance_names_available Nombres de las instancias seleccionadas para la campaña.
 * @return array|WP_Error Respuesta de la API o WP_Error.
 */ // <-- Eliminado $mime_type de los parámetros, se obtendrá de la descarga
function crm_campaign_send_whatsapp_media_message( $user_id, $attachment_url, $filename, $caption, $instance_names_available ) { // <-- $mime_type eliminado
    error_log( "[crm_campaign_send_whatsapp_media_message] Intentando enviar multimedia a User ID: {$user_id}" );

    $recipient_jid = get_user_meta( $user_id, '_crm_whatsapp_jid', true );
    if ( empty( $recipient_jid ) ) {
        return new WP_Error( 'jid_not_found', 'No se encontró el número de WhatsApp (JID) para este usuario.' );
    }

    // --- INICIO: Lógica de Selección de Instancia (Reutilizada) ---
    $active_instance_name = null;
    if (empty($instance_names_available)) {
        error_log( "[crm_campaign_send_whatsapp_media_message] Error: No hay instancias seleccionadas para la campaña." );
        return new WP_Error( 'no_instance_selected', 'No se seleccionó ninguna instancia de envío para la campaña.' );
    }
    $instances_response = crm_campaign_api_request( 'GET', '/instance/fetchInstances' ); // Usa la función local
    $connected_instances = [];
    if ( !is_wp_error( $instances_response ) && is_array( $instances_response ) ) {
        foreach ( $instances_response as $instance ) {
            $name = isset($instance['instance']['instanceName']) ? $instance['instance']['instanceName'] : null;
            $status = isset($instance['instance']['status']) ? $instance['instance']['status'] : 'unknown';
            if (in_array($name, $instance_names_available) && ($status === 'open' || $status === 'connected' || $status === 'connection')) {
                $connected_instances[] = $name;
            }
        }
    }
    if (empty($connected_instances)) {
        error_log( "[crm_campaign_send_whatsapp_media_message] Error: Ninguna de las instancias seleccionadas está conectada.");
        return new WP_Error( 'no_active_instance', 'Ninguna de las instancias seleccionadas está conectada para enviar el mensaje.' );
    }
    $active_instance_name = $connected_instances[array_rand($connected_instances)];
    error_log( "[crm_campaign_send_whatsapp_media_message] Usando instancia activa '{$active_instance_name}' para enviar." );
    // --- FIN: Lógica de Selección de Instancia ---

    // --- INICIO: Determinar mediaType basado en la URL ---
    // Extraer MIME type principal (ignorar parámetros como charset)
    // Usamos wp_check_filetype para obtener el tipo basado en la extensión de la URL
    $filetype = wp_check_filetype( basename( $attachment_url ) );
    $mime_type = $filetype['type'] ?? null;

    if (empty($mime_type)) {
        error_log("[crm_campaign_send_whatsapp_media_message] No se pudo determinar el tipo MIME de la URL: {$attachment_url}. Usando 'document' por defecto.");
        $mime_type = 'application/octet-stream'; // O un tipo por defecto
    }

    // Determinar $media_type basado en el $mime_type obtenido
    $media_type = 'document';
    if ( strpos( $mime_type, 'image/' ) === 0 ) $media_type = 'image';
    elseif ( strpos( $mime_type, 'video/' ) === 0 ) $media_type = 'video';
    elseif ( strpos( $mime_type, 'audio/' ) === 0 ) $media_type = 'audio';
    error_log("[crm_campaign_send_whatsapp_media_message] Tipo MIME detectado: {$mime_type}. Media Type para API: {$media_type}");
    // --- FIN: Determinar mediaType ---

    // *** INICIO: Definir Endpoint y Body para Media (Base64) ***
    $endpoint = "/message/sendMedia/{$active_instance_name}";
    $body = array(
        'number'        => $recipient_jid,
        'options'       => array( 'delay' => 1200, 'composing' => 'uploading' ), // Simular subida
        'mediaMessage'  => array(
            'mediatype' => $media_type,
            'media'     => $attachment_url, // <-- Usar la URL directamente
            'caption'   => $caption,
            'fileName'  => $filename ?: basename( $attachment_url ), // <-- Corregido a fileName
        ),
    );
    error_log("[crm_campaign_send_whatsapp_media_message] Endpoint y Body definidos para API: Endpoint='{$endpoint}', Body Keys: " . implode(', ', array_keys($body)));
    // *** FIN: Definir Endpoint y Body para Media (Base64) ***

    // Llamar a la API ANTES de verificar la respuesta y guardar en BD
    $api_response = crm_campaign_api_request( 'POST', $endpoint, $body ); // Usa la función local

    if ( ! is_wp_error( $api_response ) ) {
        error_log( "[crm_campaign_send_whatsapp_media_message] Llamada API para enviar multimedia a User ID {$user_id} realizada (Código: " . wp_remote_retrieve_response_code($api_response) . "). Guardando mensaje en BD..." ); // Ajuste log
        $whatsapp_message_id = isset( $api_response['key']['id'] ) ? sanitize_text_field( $api_response['key']['id'] ) : null;
        $message_data = array(
            'user_id'             => $user_id,
            'instance_name'       => $active_instance_name,
            'message_text'        => null,
            'timestamp'           => time(),
            'is_outgoing'         => true,
            'message_type'        => $media_type,
            'whatsapp_message_id' => $whatsapp_message_id,
            'attachment_url'      => $attachment_url, // Guardamos la URL original por referencia
            'caption'             => $caption,
        );
        $post_id = wp_insert_post( array(
            'post_type'   => 'crm_chat',
            'post_status' => 'publish',
            'post_title'  => 'Mensaje Multimedia Campaña - ' . $user_id . ' - ' . time(),
            'meta_input'  => crm_campaign_prepare_chat_message_meta( $message_data ), // Usa la función local
        ) );
        if ( is_wp_error( $post_id ) ) {
            error_log( "[crm_campaign_send_whatsapp_media_message] Error al guardar el mensaje multimedia saliente en la BD para User ID {$user_id}." );
        } else {
            error_log( "[crm_campaign_send_whatsapp_media_message] Mensaje multimedia saliente guardado en BD con Post ID: {$post_id}" );
        }
    }

    return $api_response;
}

// =========================================================================
// == REGISTRO CPT Y SUBMENÚS ==
// =========================================================================

/**
 * Registra el CPT 'crm_sender_campaign'.
 */
function crm_register_campaign_cpt() {

    $labels = array(
        'name'                  => _x( 'Campañas Marketing', 'Post type general name', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'singular_name'         => _x( 'Campaña Marketing', 'Post type singular name', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'menu_name'             => _x( 'Campañas', 'Admin Menu text', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'name_admin_bar'        => _x( 'Campaña', 'Add New on Toolbar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'add_new'               => __( 'Añadir Nueva', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'add_new_item'          => __( 'Añadir Nueva Campaña', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'new_item'              => __( 'Nueva Campaña', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'edit_item'             => __( 'Editar Campaña', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'view_item'             => __( 'Ver Campaña', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'all_items'             => __( 'Todas las Campañas', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'search_items'          => __( 'Buscar Campañas', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'parent_item_colon'     => __( 'Campaña Padre:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'not_found'             => __( 'No se encontraron campañas.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'not_found_in_trash'    => __( 'No se encontraron campañas en la papelera.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'archives'              => _x( 'Archivo de Campañas', 'The post type archive label used in nav menus.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'insert_into_item'      => _x( 'Insertar en campaña', 'Overrides the “Insert into post”/”Insert into page” phrase.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'uploaded_to_this_item' => _x( 'Subido a esta campaña', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'filter_items_list'     => _x( 'Filtrar lista de campañas', 'Screen reader text for the filter links.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'items_list_navigation' => _x( 'Navegación de lista de campañas', 'Screen reader text for the pagination.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'items_list'            => _x( 'Lista de campañas', 'Screen reader text for the items list heading.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => false, // Se añade manualmente como submenú
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'marketing-campaigns' ),
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => null,
        'menu_icon'          => 'dashicons-megaphone',
        'supports'           => array( 'title', 'author', 'custom-fields' ),
        'show_in_rest'       => true,
        'rest_base'          => 'crm-campaigns',
        'rest_controller_class' => 'WP_REST_Posts_Controller',
    );

    register_post_type( 'crm_sender_campaign', $args );
    error_log('Custom Post Type "crm_sender_campaign" registrado.');

}
add_action( 'init', 'crm_register_campaign_cpt' );

/**
 * Añade manualmente los submenús para el CPT 'crm_sender_campaign'.
 */
function crm_add_campaign_submenus() {
    add_submenu_page(
        'crm-evolution-sender-main',
        __( 'Todas las Campañas', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        __( 'Campañas', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'edit_posts',
        'edit.php?post_type=crm_sender_campaign',
        null,
        30
    );
    // add_submenu_page(
    //     'crm-evolution-sender-main',
    //     __( 'Añadir Nueva Campaña', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
    //     __( 'Añadir Nueva', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
    //     'edit_posts',
    //     'post-new.php?post_type=crm_sender_campaign'
    // );
}
add_action( 'admin_menu', 'crm_add_campaign_submenus', 30 );


// =========================================================================
// == META BOXES PARA CAMPAÑAS ==
// =========================================================================

/**
 * Obtiene las instancias activas formateadas para un select.
 * @return array [ 'instance_name' => 'instance_name', ... ]
 */
function crm_get_active_instances_options() {
    $instances_data = [];
    // *** USA LA FUNCIÓN LOCAL ***
    $api_response = crm_campaign_api_request( 'GET', '/instance/fetchInstances' );

    if ( !is_wp_error( $api_response ) && is_array( $api_response ) ) {
        foreach ( $api_response as $instance ) {
            $status = isset($instance['instance']['status']) ? $instance['instance']['status'] : 'unknown';
            if ( ($status === 'open' || $status === 'connected' || $status === 'connection') && isset($instance['instance']['instanceName']) ) {
                 $name = $instance['instance']['instanceName'];
                 $instances_data[ esc_attr( $name ) ] = esc_html( $name );
            }
        }
    } else {
        error_log("[crm_get_active_instances_options] Error al obtener instancias para select en CPT: " . (is_wp_error($api_response) ? $api_response->get_error_message() : 'Respuesta inválida'));
    }
        error_log("[crm_get_active_instances_options] Instancias activas para select CPT: " . print_r($instances_data, true));
    return $instances_data;
}

/**
 * Obtiene las etiquetas de ciclo de vida formateadas para un select.
 * @return array [ 'tag_slug' => 'Tag Label', ... ]
 */
function crm_get_etiquetas_options() {
    $tags = function_exists('crm_get_lifecycle_tags') ? crm_get_lifecycle_tags() : get_option( 'crm_evolution_lifecycle_tags', array() );
    $options = array();
    foreach ($tags as $slug => $name) {
        $options[esc_attr($slug)] = esc_html($name);
    }
    error_log("[crm_get_etiquetas_options] Etiquetas para select CPT: " . print_r($options, true));
    return $options;
}

/**
 * Añade los meta boxes a la pantalla de edición de campañas.
 */
function crm_campaign_add_meta_boxes() {
    add_meta_box(
        'crm_campaign_settings',
        __( 'Configuración de la Campaña', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'crm_campaign_settings_meta_box_html',
        'crm_sender_campaign',
        'normal',
        'high'
    );
    add_meta_box(
        'crm_campaign_instances_metabox',
        __( 'Instancias de Envío', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'crm_campaign_instances_metabox_html',
        'crm_sender_campaign',
        'side',
        'high'
    );
    add_meta_box(
        'crm_campaign_segmentation_metabox',
        __( 'Segmentación', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'crm_campaign_segmentation_metabox_html',
        'crm_sender_campaign',
        'side',
        'high'
    );
}
add_action( 'add_meta_boxes_crm_sender_campaign', 'crm_campaign_add_meta_boxes' );

/**
 * Muestra el HTML para el meta box de configuración de la campaña.
 * @param WP_Post $post El objeto del post actual.
 */
function crm_campaign_settings_meta_box_html( $post ) {
    wp_nonce_field( 'crm_save_campaign_meta_box_data', 'crm_campaign_meta_box_nonce' );

    $interval       = get_post_meta( $post->ID, '_crm_campaign_interval_minutes', true );
    $media_url      = get_post_meta( $post->ID, '_crm_campaign_media_url', true );
    $message_text   = get_post_meta( $post->ID, '_crm_campaign_message_text', true );

    ?>
    <table class="form-table">
        <tbody>
            <tr>
                <th><label for="crm_campaign_interval_minutes"><?php _e( 'Intervalo entre mensajes (minutos)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label></th>
                <td>
                    <input type="number" id="crm_campaign_interval_minutes" name="crm_campaign_interval_minutes" value="<?php echo esc_attr( $interval ? $interval : 5 ); ?>" min="1" step="1" class="small-text">
                    <p class="description"><?php _e( 'Tiempo mínimo en minutos entre el envío de cada mensaje.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="crm_campaign_message_text"><?php _e( 'Mensaje de la Campaña', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label></th>
                <td colspan="1">
                    <?php
                    wp_editor( $message_text, 'crm_campaign_message_text_editor', array(
                        'textarea_name' => 'crm_campaign_message_text',
                        'textarea_rows' => 10,
                        'media_buttons' => false,
                        'tinymce'       => true,
                    ) );
                    ?>
                    <p class="description"><?php _e( 'Escribe aquí el mensaje que se enviará. Puedes usar {nombre}, {apellido}, {email} como placeholders.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="crm_campaign_media_url"><?php _e( 'URL de Archivo Multimedia (Opcional)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label></th>
                <td>
                    <input type="url" id="crm_campaign_media_url" name="crm_campaign_media_url" value="<?php echo esc_url( $media_url ); ?>" class="regular-text">
                    <button type="button" class="button crm-select-media-cpt"><?php _e( 'Seleccionar/Subir', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></button>
                    <button type="button" class="button crm-clear-media-cpt"><?php _e( 'Limpiar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></button>
                    <p class="description"><?php _e( 'URL completa del archivo (imagen, video, documento) a enviar junto al mensaje.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
                    <span id="media-filename-cpt" style="display: block; margin-top: 5px;"></span>
                </td>
            </tr>
        </tbody>
    </table>
    <?php
}

/**
 * Muestra el HTML para el meta box lateral de selección de instancias.
 * @param WP_Post $post El objeto del post actual.
 */
function crm_campaign_instances_metabox_html( $post ) {
    $instance_names = get_post_meta( $post->ID, '_crm_campaign_instance_names', true );
    if ( ! is_array( $instance_names ) ) $instance_names = array();
    $active_instances = crm_get_active_instances_options(); // Usa la función local
    ?>
    <div class="crm-side-metabox-field">
        <label for="crm_campaign_instance_names" class="screen-reader-text"><?php _e( 'Instancias a usar (Rotación)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label>
        <select name="crm_campaign_instance_names[]" id="crm_campaign_instance_names" multiple="multiple" style="width: 100%; min-height: 80px;">
            <?php if (empty($active_instances)): ?>
                <option value="" disabled><?php _e('No hay instancias activas', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></option>
            <?php else: ?>
                <?php foreach ( $active_instances as $name => $label ) : ?>
                    <option value="<?php echo esc_attr( $name ); ?>" <?php selected( in_array( $name, $instance_names ), true ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
        <p class="description"><?php _e( 'Selecciona una o más instancias. El sistema rotará entre ellas.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
    </div>
    <?php
}

/**
 * Muestra el HTML para el meta box lateral de selección de etiquetas.
 * @param WP_Post $post El objeto del post actual.
 */
function crm_campaign_segmentation_metabox_html( $post ) {
    $target_tags = get_post_meta( $post->ID, '_crm_campaign_target_tags', true );
    if ( ! is_array( $target_tags ) ) $target_tags = array();
    $available_tags = crm_get_etiquetas_options(); // Usa la función local
    ?>
    <div class="crm-side-metabox-field">
        <label for="crm_campaign_target_tags" class="screen-reader-text"><?php _e( 'Etiquetas de Destinatarios', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label>
        <select name="crm_campaign_target_tags[]" id="crm_campaign_target_tags" multiple="multiple" style="width: 100%; min-height: 80px;">
            <?php if (empty($available_tags)): ?>
                <option value="" disabled><?php _e('No hay etiquetas definidas', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></option>
            <?php else: ?>
                <?php foreach ( $available_tags as $slug => $label ) : ?>
                    <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( in_array( $slug, $target_tags ), true ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
        <p class="description"><?php _e( 'Enviar a usuarios con CUALQUIERA de estas etiquetas.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
    </div>
    <?php
}

// =========================================================================
// == GUARDAR DATOS DE META BOXES ==
// =========================================================================

/**
 * Guarda los datos personalizados enviados desde los meta boxes de la campaña.
 * @param int $post_id El ID del post que se está guardando.
 */
function crm_save_campaign_meta_box_data( $post_id ) {

    if ( ! isset( $_POST['crm_campaign_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['crm_campaign_meta_box_nonce'], 'crm_save_campaign_meta_box_data' ) ) {
        error_log("[crm_save_campaign_meta_box_data] Error al guardar meta de campaña {$post_id}: Nonce inválido.");
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! isset($_POST['post_type']) || 'crm_sender_campaign' !== $_POST['post_type'] ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
         error_log("[crm_save_campaign_meta_box_data] Error al guardar meta de campaña {$post_id}: Permiso denegado." );
        return;
    }

    $instance_names = isset( $_POST['crm_campaign_instance_names'] ) && is_array( $_POST['crm_campaign_instance_names'] )
                      ? array_map( 'sanitize_key', $_POST['crm_campaign_instance_names'] )
                      : array();
    update_post_meta( $post_id, '_crm_campaign_instance_names', $instance_names );

    $target_tags = isset( $_POST['crm_campaign_target_tags'] ) && is_array( $_POST['crm_campaign_target_tags'] )
                   ? array_map( 'sanitize_key', $_POST['crm_campaign_target_tags'] )
                   : array();
    update_post_meta( $post_id, '_crm_campaign_target_tags', $target_tags );

    $interval = isset( $_POST['crm_campaign_interval_minutes'] ) ? absint( $_POST['crm_campaign_interval_minutes'] ) : 5;
    update_post_meta( $post_id, '_crm_campaign_interval_minutes', $interval );

    $message = isset( $_POST['crm_campaign_message_text'] ) ? wp_kses_post( wp_unslash( $_POST['crm_campaign_message_text'] ) ) : '';
    update_post_meta( $post_id, '_crm_campaign_message_text', $message );

    $media_url = isset( $_POST['crm_campaign_media_url'] ) ? esc_url_raw( wp_unslash( $_POST['crm_campaign_media_url'] ) ) : '';
    update_post_meta( $post_id, '_crm_campaign_media_url', $media_url );

    error_log("[crm_save_campaign_meta_box_data] Metadatos de campaña guardados para Post ID: {$post_id}" );
}
add_action( 'save_post_crm_sender_campaign', 'crm_save_campaign_meta_box_data' );

// =========================================================================
// == PERSONALIZAR MENSAJES DE ACTUALIZACIÓN ==
// =========================================================================

/**
 * Personaliza los mensajes de actualización para el CPT 'crm_sender_campaign'.
 * @param array $messages Array de mensajes de actualización por tipo de post.
 * @return array Array de mensajes modificado.
 */
function crm_campaign_updated_messages( $messages ) {
    global $post;

    if ( isset($post->post_type) && 'crm_sender_campaign' === $post->post_type ) {
        $list_url = admin_url('edit.php?post_type=crm_sender_campaign');
        $back_to_list_link = sprintf(
            ' <a href="%s">%s</a>',
            esc_url( $list_url ),
            esc_html__( 'Volver al listado', CRM_EVOLUTION_SENDER_TEXT_DOMAIN )
        );

        $messages['post'][1] = __( 'Campaña actualizada.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) . $back_to_list_link;
        $messages['post'][6] = __( 'Campaña publicada.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) . $back_to_list_link;
    }
    return $messages;
}
add_filter( 'post_updated_messages', 'crm_campaign_updated_messages' );

// =========================================================================
// == LÓGICA DE ENVÍO CON WP-CRON ==
// =========================================================================

/**
 * Programa o desprograma el envío de una campaña basado en su cambio de estado.
 * @param string  $new_status Nuevo estado del post.
 * @param string  $old_status Antiguo estado del post.
 * @param WP_Post $post       Objeto del post.
 */
function crm_handle_campaign_scheduling_on_status_change( $new_status, $old_status, $post ) {
    if ( $post->post_type !== 'crm_sender_campaign' ) return;

    $post_id = $post->ID;
    error_log( "[CRON_SCHEDULER] transition_post_status para Campaña ID: {$post_id}. De '{$old_status}' a '{$new_status}'" );

    $timestamp = wp_next_scheduled( 'crm_process_campaign_batch', array( $post_id ) );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'crm_process_campaign_batch', array( $post_id ) );
        error_log( "[CRON_SCHEDULER][{$post_id}] Tarea cron previa desprogramada." );
    }

    if ( $new_status === 'publish' ) {
        wp_schedule_single_event( time() + 5, 'crm_process_campaign_batch', array( $post_id ) );
        error_log( "[CRON_SCHEDULER][{$post_id}] Estado es 'publish'. Primera ejecución de 'crm_process_campaign_batch' programada." );

        if ($old_status !== 'publish') {
             update_post_meta( $post_id, '_crm_campaign_sent_count', 0 );
             update_post_meta( $post_id, '_crm_campaign_failed_count', 0 );
             update_post_meta( $post_id, '_crm_campaign_last_processed_user_id', 0 );
             error_log( "[CRON_SCHEDULER][{$post_id}] Contadores reseteados." );
        }
    }
}
add_action( 'transition_post_status', 'crm_handle_campaign_scheduling_on_status_change', 10, 3 );

/**
 * Función que se ejecutará vía WP-Cron para procesar un lote (o un mensaje) de la campaña.
 * @param int $campaign_id ID del post de la campaña a procesar.
 */
function crm_process_campaign_batch_callback( $campaign_id ) {
    error_log( "[CRON] Iniciando procesamiento para Campaña ID: {$campaign_id}" );

    $campaign_post = get_post( $campaign_id );
    if ( ! $campaign_post || $campaign_post->post_type !== 'crm_sender_campaign' ) {
        error_log( "[CRON][{$campaign_id}] Error: Post no encontrado o no es una campaña." );
        return;
    }
    if ( $campaign_post->post_status !== 'publish' ) {
        error_log( "[CRON][{$campaign_id}] Campaña no está publicada (estado: {$campaign_post->post_status}). Deteniendo envío." );
        return;
    }

    $target_tags      = get_post_meta( $campaign_id, '_crm_campaign_target_tags', true );
    $interval_minutes = get_post_meta( $campaign_id, '_crm_campaign_interval_minutes', true );
    $message_text     = get_post_meta( $campaign_id, '_crm_campaign_message_text', true );
    $media_url        = get_post_meta( $campaign_id, '_crm_campaign_media_url', true );
    $last_processed_user_id = get_post_meta( $campaign_id, '_crm_campaign_last_processed_user_id', true ) ?: 0;
    $instance_names   = get_post_meta( $campaign_id, '_crm_campaign_instance_names', true ); // Instancias seleccionadas

    if ( empty( $target_tags ) || ! is_array( $target_tags ) ) {
        error_log( "[CRON][{$campaign_id}] Error: No hay etiquetas objetivo definidas o el formato es incorrecto." );
        return;
    }
    if ( empty( $instance_names ) || ! is_array( $instance_names ) ) {
        error_log( "[CRON][{$campaign_id}] Error: No hay instancias de envío seleccionadas." );
        return;
    }
    $interval_seconds = absint( $interval_minutes ?: 5 ) * 60;

    // Buscar el siguiente usuario
    $user_meta_key_sent = '_crm_campaign_sent_' . $campaign_id;
    $user_query_args = array(
        'number' => 1,
        'orderby' => 'ID',
        'order' => 'ASC',
        'fields' => 'ID',
        'meta_query' => array(
            'relation' => 'AND',
            array( 'key' => '_crm_whatsapp_jid', 'compare' => 'EXISTS' ),
            array( 'key' => '_crm_whatsapp_jid', 'value' => '', 'compare' => '!=' ),
            array( 'key' => $user_meta_key_sent, 'compare' => 'NOT EXISTS' ),
            array( 'relation' => 'OR' )
        ),
    );
    foreach ($target_tags as $tag_slug) {
        $user_query_args['meta_query'][4][] = array(
            'key' => '_crm_lifecycle_tag',
            'value' => $tag_slug,
            'compare' => '=',
        );
    }

    error_log( "[CRON][{$campaign_id}] WP_User_Query Args (antes de pre_get_users): " . print_r($user_query_args, true) );

    // Hook para añadir ID > last_processed_user_id
    $pre_get_users_callback = function( $query ) use ( $last_processed_user_id, &$pre_get_users_callback ) {
        $is_our_query = false;
        if (isset($query->query_vars['meta_query'])) {
            foreach($query->query_vars['meta_query'] as $mq_clause) {
                if (is_array($mq_clause) && isset($mq_clause['key']) && $mq_clause['key'] === '_crm_whatsapp_jid') {
                    $is_our_query = true;
                    break;
                }
            }
        }

        if ($is_our_query) {
            global $wpdb;
            $query->query_where .= $wpdb->prepare( " AND {$wpdb->users}.ID > %d", $last_processed_user_id );
            error_log("[CRON][pre_get_users] Condición ID > {$last_processed_user_id} añadida a la consulta.");
        }
        // Remover la acción para no afectar otras queries
        remove_action( 'pre_get_users', $pre_get_users_callback );
    };
    add_action( 'pre_get_users', $pre_get_users_callback );

    $user_query = new WP_User_Query( $user_query_args );
    $next_user = $user_query->get_results();
    error_log( "[CRON][{$campaign_id}] WP_User_Query Results: " . print_r($next_user, true) );

    if ( empty( $next_user ) ) {
        error_log( "[CRON][{$campaign_id}] No se encontraron más usuarios pendientes (después de ID: {$last_processed_user_id}). Campaña completada.");
        return;
    }

    $user_id_to_process = $next_user[0];
    error_log( "[CRON][{$campaign_id}] Procesando siguiente usuario ID: {$user_id_to_process}" );

    // Lógica de envío
    $send_result = null;
    if ( ! empty( $media_url ) ) {
        $path = wp_parse_url( $media_url, PHP_URL_PATH );
        $filename = basename( $path ); // Obtener nombre de archivo de la URL
        // $filetype = wp_check_filetype( $filename ); // Ya no necesitamos esto aquí
        // $mime_type = $filetype['type'] ?? null; // Ya no necesitamos esto aquí

        // if ($mime_type) { // Ya no necesitamos esta comprobación aquí
             error_log( "[CRON][{$campaign_id}] Intentando enviar MEDIA a User ID {$user_id_to_process}. URL: {$media_url}");
             // *** USA LA FUNCIÓN LOCAL ***
             $send_result = crm_campaign_send_whatsapp_media_message( $user_id_to_process, $media_url, $filename, $message_text, $instance_names ); // <-- $mime_type eliminado de la llamada
        // } else { // <-- Eliminado else huérfano
    // } // <-- Fin del if ($mime_type) eliminado
    } elseif ( ! empty( $message_text ) ) { // Solo enviar texto si NO hay media_url
        $user_data = get_userdata( $user_id_to_process );
        $processed_message = $message_text;
        if ($user_data) {
            $replacements = array(
                '{nombre}'   => $user_data->first_name ?: $user_data->display_name,
                '{apellido}' => $user_data->last_name ?: '',
                '{email}'    => $user_data->user_email ?: '',
            );
            $processed_message = str_replace( array_keys($replacements), array_values($replacements), $message_text );
        }
        error_log( "[CRON][{$campaign_id}] Intentando enviar TEXTO procesado a User ID {$user_id_to_process}." );
        // *** USA LA FUNCIÓN LOCAL ***
        $send_result = crm_campaign_send_whatsapp_message( $user_id_to_process, $processed_message, $instance_names );
    } else {
         error_log( "[CRON][{$campaign_id}] Error: No hay ni mensaje ni media URL para enviar.");
         $send_result = new WP_Error('no_content', 'No hay contenido para enviar.');
    }

    // Actualizar estado y contadores
    if ( is_wp_error( $send_result ) ) {
        error_log( "[CRON][{$campaign_id}] Fallo al enviar a User ID {$user_id_to_process}: " . $send_result->get_error_message());
        $failed_count = (int) get_post_meta( $campaign_id, '_crm_campaign_failed_count', true );
        update_post_meta( $campaign_id, '_crm_campaign_failed_count', $failed_count + 1 );
    } else {
        error_log( "[CRON][{$campaign_id}] Envío exitoso (o API aceptó) a User ID {$user_id_to_process}." );
        $sent_count = (int) get_post_meta( $campaign_id, '_crm_campaign_sent_count', true );
        update_post_meta( $campaign_id, '_crm_campaign_sent_count', $sent_count + 1 );
        update_user_meta( $user_id_to_process, $user_meta_key_sent, true );
    }

    update_post_meta( $campaign_id, '_crm_campaign_last_processed_user_id', $user_id_to_process );

    // Reprogramar
    $next_schedule_time = time() + $interval_seconds;
    wp_schedule_single_event( $next_schedule_time, 'crm_process_campaign_batch', array( $campaign_id ) );
    error_log( "[CRON][{$campaign_id}] Siguiente ejecución programada para dentro de {$interval_minutes} minutos." );

}
add_action( 'crm_process_campaign_batch', 'crm_process_campaign_batch_callback', 10, 1 );

// =========================================================================
// == PERSONALIZAR COLUMNAS EN EL LISTADO DE CAMPAÑAS ==
// =========================================================================

/**
 * Añade columnas personalizadas a la tabla de administración de campañas.
 * @param array $columns Array existente de columnas.
 * @return array Array modificado de columnas.
 */
function crm_campaign_add_admin_columns( $columns ) {
    $date_column = $columns['date'];
    unset( $columns['date'] );

    $columns['crm_status']    = __( 'Estado', CRM_EVOLUTION_SENDER_TEXT_DOMAIN );
    $columns['crm_tags']      = __( 'Etiquetas', CRM_EVOLUTION_SENDER_TEXT_DOMAIN );
    $columns['crm_instances'] = __( 'Instancias', CRM_EVOLUTION_SENDER_TEXT_DOMAIN );
    $columns['crm_progress']  = __( 'Progreso (Enviados/Fallidos)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN );
    $columns['crm_interval']  = __( 'Intervalo (min)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN );
    $columns['date'] = $date_column;

    return $columns;
}
add_filter( 'manage_crm_sender_campaign_posts_columns', 'crm_campaign_add_admin_columns' );

/**
 * Muestra el contenido de las columnas personalizadas en la tabla de administración.
 * @param string $column_name El nombre de la columna actual.
 * @param int    $post_id     El ID del post actual.
 */
function crm_campaign_render_admin_columns( $column_name, $post_id ) {
    switch ( $column_name ) {
        case 'crm_status':
            $post_status = get_post_status( $post_id );
            $next_scheduled = wp_next_scheduled( 'crm_process_campaign_batch', array( $post_id ) );

            if ( $post_status === 'publish' ) {
                if ( $next_scheduled ) {
                    echo '<span style="color: orange;">' . esc_html__( 'Enviando', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) . '</span>';
                } else {
                    echo '<span style="color: green;">' . esc_html__( 'Publicada (¿Completada?)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) . '</span>';
                }
            } elseif ( $post_status === 'draft' ) {
                echo '<span style="color: gray;">' . esc_html__( 'Borrador', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) . '</span>';
            } elseif ( $post_status === 'pending' ) {
                 echo '<span style="color: blue;">' . esc_html__( 'Pendiente', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) . '</span>';
            } else {
                echo esc_html( ucfirst( $post_status ) );
            }
            break;

        case 'crm_tags':
            $tags = get_post_meta( $post_id, '_crm_campaign_target_tags', true );
            if ( ! empty( $tags ) && is_array( $tags ) ) {
                echo esc_html( implode( ', ', $tags ) );
            } else { echo '—'; }
            break;

        case 'crm_instances':
            $instances = get_post_meta( $post_id, '_crm_campaign_instance_names', true );
            if ( ! empty( $instances ) && is_array( $instances ) ) {
                echo esc_html( implode( ', ', $instances ) );
            } else { echo '—'; }
            break;

        case 'crm_progress':
            $sent_count = (int) get_post_meta( $post_id, '_crm_campaign_sent_count', true );
            $failed_count = (int) get_post_meta( $post_id, '_crm_campaign_failed_count', true );
            echo sprintf( '%d / %d', $sent_count, $failed_count );
            break;

        case 'crm_interval':
            $interval = get_post_meta( $post_id, '_crm_campaign_interval_minutes', true );
            echo esc_html( $interval ?: 'N/A' );
            break;
    }
}
add_action( 'manage_crm_sender_campaign_posts_custom_column', 'crm_campaign_render_admin_columns', 10, 2 );

// =========================================================================
// == MEJORAS UI EN PANTALLA DE EDICIÓN ==
// =========================================================================

/**
 * Añade un botón "Volver al Listado" al meta box de Publicar.
 * @param WP_Post $post El objeto del post actual.
 */
function crm_add_back_to_list_button_to_publish_box( $post ) {
    if ( 'crm_sender_campaign' !== $post->post_type ) return;
    $list_url = admin_url( 'edit.php?post_type=crm_sender_campaign' );
    echo '<div class="misc-pub-section misc-pub-back-to-list" style="padding-top: 5px; padding-bottom: 5px;">';
    echo '<span class="dashicons dashicons-arrow-left-alt" style="vertical-align:middle; margin-right: 3px;"></span>';
    echo '<a href="' . esc_url( $list_url ) . '" class="button button-secondary button-small">' . esc_html__( 'Volver al Listado', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) . '</a>';
    echo '</div>';
}
add_action( 'post_submitbox_misc_actions', 'crm_add_back_to_list_button_to_publish_box', 10, 1 );



?>
