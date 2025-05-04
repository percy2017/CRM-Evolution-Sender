<?php
/**
 * Archivo para registrar los endpoints de la API REST para CRM Evolution Sender.
 * Incluye el manejador de webhooks completo, procesando imágenes/videos desde Base64,
 * creando usuarios ('subscriber') si no existen, guardando avatares usando credenciales globales,
 * y asociando los chats al agente/dueño de la instancia.
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Registra los endpoints de la API REST del plugin.
 *
 * Se engancha a 'rest_api_init'.
 */
function crm_evolution_register_rest_routes() {

    $namespace = 'crm-evolution-api/v1';
    $route     = '/webhook'; 

    register_rest_route( $namespace, $route, array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'crm_evolution_webhook_handler_callback',
        'permission_callback' => '__return_true',
    ) );
}
add_action( 'rest_api_init', 'crm_evolution_register_rest_routes' );




/**
 * Función Callback para manejar los datos recibidos en el endpoint del webhook.
 *
 * @param WP_REST_Request $request Objeto de la petición REST. Contiene los datos enviados.
 * @return WP_REST_Response Respuesta que se enviará de vuelta a la API Evolution.
 */
function crm_evolution_webhook_handler_callback( WP_REST_Request $request ) {

    // Obtener los datos JSON enviados por Evolution API
    $data = $request->get_json_params();
    error_log('Webhook recibido - '. print_r( $data, true ));
   
    // Extraer Evento e Instancia
    $event = isset($data['event']) ? $data['event'] : 'evento_desconocido';
    $instance = isset($data['instance']) ? $data['instance'] : 'instancia_desconocida';
    crm_log( "Webhook recibido - Instancia: [{$instance}], Evento: [{$event}]", 'INFO' );

    // Manejar eventos con switch
    switch ($event) {
        case 'messages.upsert':
            crm_log( "Webhook [{$instance}]: Procesando evento 'messages.upsert'.", 'INFO' );
            // --- INICIO: Extracción Detallada de Datos del Mensaje ---
            $message_payload = $data['data'] ?? null;
            $instance_name = sanitize_text_field($data['instance'] ?? 'desconocida');
            $api_sender_jid = sanitize_text_field($data['sender'] ?? null); // JID de la instancia que envía el webhook

            if (!$message_payload || !isset($message_payload['key'], $message_payload['messageTimestamp'])) {
                crm_log("Error: Payload 'data' inválido o incompleto en 'messages.upsert'.", 'ERROR', $data);
                break; // Salir del case si faltan datos esenciales
            }

            $key_info = $message_payload['key'];
            $message_info = $message_payload['message'] ?? null; // El objeto 'message' puede ser null (ej: notificaciones de estado)

            // Datos clave de identificación
            $remote_jid = sanitize_text_field($key_info['remoteJid'] ?? '');
            $from_me = (bool) ($key_info['fromMe'] ?? false);
            $message_id_wa = sanitize_text_field($key_info['id'] ?? '');
            $participant_jid = isset($key_info['participant']) ? sanitize_text_field($key_info['participant']) : null; // Para grupos
            $is_group_message = (strpos($remote_jid, '@g.us') !== false);

            // Datos del usuario/contacto
            $push_name = isset($message_payload['pushName']) ? sanitize_text_field($message_payload['pushName']) : null;

            // Timestamp
            $timestamp_wa = (int) $message_payload['messageTimestamp'];

            // Determinar JID del remitente y destinatario REALES
            $sender_real_jid = $from_me ? $api_sender_jid : ($is_group_message ? $participant_jid : $remote_jid);
            $recipient_real_jid = $from_me ? $remote_jid : $api_sender_jid;

            // --- FIN: Extracción Detallada ---

            // --- INICIO: Procesar Usuario (Contacto) ---
            // El JID a buscar/crear es siempre el 'remote_jid' si es mensaje individual,
            // o el 'participant_jid' si es un mensaje de grupo entrante.
            // Si es un mensaje saliente a grupo, usamos 'remote_jid' (el grupo) como referencia, aunque no creará usuario.
            $jid_for_user_processing = $is_group_message ? ($from_me ? $remote_jid : $participant_jid) : $remote_jid;

            if (empty($jid_for_user_processing)) {
                 crm_log("Error: No se pudo determinar el JID para procesar el usuario.", 'ERROR', $key_info);
                 break;
            }

            // Usamos crm_process_single_jid directamente para el contacto.
            // Pasamos pushName solo si es un mensaje entrante.
            $contact_user_id = crm_process_single_jid($jid_for_user_processing, $instance_name, $push_name, !$from_me); // <--- AQUÍ SE PROCESA EL USUARIO

            if ($contact_user_id === 0 && !$is_group_message) { // Si falla y NO es grupo, no podemos continuar
                crm_log("Error: No se pudo encontrar o crear el usuario para JID '{$jid_for_user_processing}'. No se guardará el mensaje.", 'ERROR');
                break;
            }
             // Si es grupo y falla, $contact_user_id será 0, pero podemos continuar asociando al admin o un usuario genérico si quisiéramos.
             // Por ahora, si es grupo y falla, el post_author será 0 (Admin).
            crm_log("Usuario contacto procesado. JID: '{$jid_for_user_processing}', User ID: {$contact_user_id}", 'INFO');
            // --- FIN: Procesar Usuario ---

            // --- INICIO: Extraer Contenido del Mensaje ---
            $message_type = 'unknown';
            $message_text = null;
            $media_caption = null;
            $media_mimetype = null;
            $base64_data = $data['data']['message']['base64'] ?? null; // Base64 está DENTRO de data.message

            // Extraer tipo y contenido específico (simplificado, necesita más casos)
            if (isset($message_info['conversation'])) {
                $message_type = 'text';
                $message_text = $message_info['conversation'];
            } elseif (isset($message_info['extendedTextMessage']['text'])) {
                $message_type = 'text';
                $message_text = $message_info['extendedTextMessage']['text'];
            } elseif (isset($message_info['imageMessage'])) {
                $message_type = 'image';
                $media_caption = $message_info['imageMessage']['caption'] ?? null;
                $media_mimetype = $message_info['imageMessage']['mimetype'] ?? 'image/jpeg'; // Default
            } elseif (isset($message_info['videoMessage'])) {
                 $message_type = 'video';
                 $media_caption = $message_info['videoMessage']['caption'] ?? null;
                 $media_mimetype = $message_info['videoMessage']['mimetype'] ?? 'video/mp4'; // Default
            } elseif (isset($message_info['audioMessage'])) {
                $message_type = 'audio';
                $media_caption = null; 
                // Primero obtén el mimetype completo
                $raw_mimetype = $message_info['audioMessage']['mimetype'] ?? 'audio/ogg'; // Obtener y poner default
                // Ahora sí, divídelo
                $mime_parts = explode(';', $raw_mimetype);
                $media_mimetype = trim($mime_parts[0]); // Tomar solo la parte principal 'audio/ogg'
            } 
            // ... añadir más tipos: audio, document, location, etc.

            // Si hay base64 pero no se detectó tipo media, intentar deducir (o marcar como 'file')
            if ($base64_data && $message_type === 'unknown') {
                 $message_type = 'file'; // Tipo genérico si hay base64 no identificado
                 // Podríamos intentar obtener mimetype de $message_info si existe alguna clave media no manejada
            }
            // --- FIN: Extraer Contenido ---

            // --- INICIO: Preparar y Guardar Mensaje ---
            if ($message_type !== 'unknown' || $base64_data) { // Solo guardar si es un tipo conocido o tiene adjunto
                $message_data_to_save = [
                    'contact_user_id'     => $contact_user_id, // ID del usuario contacto (o 0 si es grupo y falló)
                    'instance_name'       => $instance_name,
                    'sender_jid'          => $sender_real_jid,
                    'recipient_jid'       => $recipient_real_jid,
                    'is_outgoing'         => $from_me,
                    'message_id_wa'       => $message_id_wa,
                    'timestamp_wa'        => $timestamp_wa,
                    'message_type'        => $message_type,
                    'message_text'        => $message_text,
                    'base64_data'         => $base64_data,
                    'media_mimetype'      => $media_mimetype,
                    'media_caption'       => $media_caption,
                    'is_group_message'    => $is_group_message,
                    'participant_jid'     => $participant_jid,
                ];

                $save_result = crm_save_chat_message($message_data_to_save); // <--- AQUÍ SE GUARDA EL MENSAJE

                if (is_wp_error($save_result)) {
                    crm_log("Error al guardar el mensaje para WA ID {$message_id_wa}: " . $save_result->get_error_message(), 'ERROR');
                } else {
                    crm_log("Mensaje para WA ID {$message_id_wa} guardado con éxito. Post ID: {$save_result}", 'INFO');
                }
            } else {
                 crm_log("Mensaje WA ID {$message_id_wa} ignorado (tipo desconocido y sin adjunto).", 'WARN', $message_info);
            }
            // --- FIN: Preparar y Guardar Mensaje ---
        
            break;

        default:
            crm_log( "Webhook [{$instance}]: Evento '{$event}' recibido pero no manejado actualmente.", 'WARN' );
            break;
    }


    // Siempre responder OK a Evolution API para que no reintente
    return new WP_REST_Response( array( 'status' => 'success', 'message' => 'Webhook received and processed.' ), 200 );

}

/**
 * Procesa un JID individual: busca un usuario WP existente o crea uno nuevo si no existe.
 * Si lo crea, guarda metadatos (JID, billing_phone, primera etiqueta) y llama a guardar avatar.
 * Si ya existe, no hace nada más.
 *
 * @param string $jid El JID a procesar (ej: "59171146267@s.whatsapp.net").
 * @param string $instanceName Nombre de la instancia API (necesario para obtener avatar).
 * @param string|null $pushNameToUse El pushName recibido en el webhook.
 * @param bool $usePushName Indica si se debe usar $pushNameToUse para el display_name (solo para mensajes entrantes del remoteJid).
 * @return int El ID del usuario WP encontrado o creado, o 0 si falla o no es un JID de usuario.
 */
function crm_process_single_jid($jid, $instanceName, $pushNameToUse = null, $usePushName = false) {
    // Validar que sea un JID de usuario individual
    if (strpos($jid, '@s.whatsapp.net') === false) {
        crm_log("JID '{$jid}' no es un usuario individual (@s.whatsapp.net). Ignorando.", 'DEBUG');
        return 0;
    }

    // Buscar usuario existente por JID en user_meta
    $user_query = new WP_User_Query(array(
        'meta_key'   => '_crm_whatsapp_jid',
        'meta_value' => $jid,
        'number'     => 1, // Solo necesitamos uno
        'fields'     => 'ID', // Solo obtener el ID
    ));

    $users = $user_query->get_results();
    $user_id = !empty($users) ? $users[0] : 0;

    if ($user_id) {
        // Si el usuario ya existe, no hacemos NADA más. Ni avatar, ni actualizar datos.
        crm_log("Usuario encontrado para JID '{$jid}' con User ID: {$user_id}. No se realizarán más acciones.", 'INFO');
        return $user_id; // Salir inmediatamente

    } else {
        crm_log("Usuario NO encontrado para JID '{$jid}'. Intentando crear uno nuevo.", 'INFO');

        // Extraer número del JID para username/email placeholder
        $number = strstr($jid, '@', true); // Obtiene la parte antes del @
        if (!$number) {
            crm_log("Error: No se pudo extraer el número del JID '{$jid}'", 'ERROR');
            return 0;
        }

        // Generar datos para el nuevo usuario
        $username = 'wa_' . $number;
        // Asegurar que el username sea único (WordPress lo hace, pero podemos añadir un sufijo si falla)
        $email = $number . '@whatsapp.placeholder'; // Email placeholder
        $password = wp_generate_password(12, true); // Contraseña segura

        // Determinar el nombre a mostrar
        $display_name = ($usePushName && !empty($pushNameToUse)) ? $pushNameToUse : $username;

        $user_data = array(
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => $password,
            'display_name' => $display_name,
            'role'         => 'subscriber', // Rol por defecto
        );

        // Intentar crear el usuario
        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            crm_log("Error al crear usuario para JID '{$jid}': " . $user_id->get_error_message(), 'ERROR');
            // Intentar con un username ligeramente diferente si el error es por duplicado
            if ('existing_user_login' === $user_id->get_error_code()) {
                 $username .= '_' . time(); // Añadir timestamp para unicidad
                 $user_data['user_login'] = $username;
                 $user_id = wp_insert_user($user_data);
                 if (is_wp_error($user_id)) {
                     crm_log("Error al RE-intentar crear usuario para JID '{$jid}': " . $user_id->get_error_message(), 'ERROR');
                     return 0;
                 }
            } else {
                return 0; // Otro error al crear
            }
        }

        // Si se creó correctamente, guardar metadatos
        update_user_meta($user_id, '_crm_whatsapp_jid', $jid);
        // Guardar también el número para compatibilidad con WooCommerce
        update_user_meta($user_id, 'billing_phone', $number);

        // Asignar la PRIMERA etiqueta definida como defecto al crear desde webhook
        $defined_tags = crm_get_lifecycle_tags(); // Obtener etiquetas definidas en ajustes
        if (!empty($defined_tags)) {
            $default_tag_key = array_key_first($defined_tags); // Obtener la clave de la primera etiqueta
            update_user_meta($user_id, '_crm_lifecycle_tag', $default_tag_key);
            crm_log("Nuevo usuario creado con User ID: {$user_id} para JID '{$jid}'. Username: {$username}, Display Name: {$display_name}. Etiqueta por defecto asignada: '{$default_tag_key}'", 'INFO');
        } else {
            crm_log("Nuevo usuario creado con User ID: {$user_id} para JID '{$jid}'. Username: {$username}, Display Name: {$display_name}. No se asignó etiqueta por defecto (ninguna definida).", 'INFO');
        }

        // Si llegamos aquí, significa que el usuario se acaba de CREAR.
        // Solo en este caso intentamos obtener/guardar el avatar por primera vez.
        if ($user_id) { // Asegurarse de que la creación fue exitosa
            crm_fetch_and_save_avatar($user_id, $jid, $instanceName);
        }

    } // Fin del else (usuario no encontrado)

    return $user_id;
}

/**
 * Obtiene la URL del avatar desde Evolution API, lo descarga y lo guarda en la Media Library,
 * asociándolo al usuario WP, **solo si el usuario no tiene ya un avatar guardado**.
 *
 * @param int    $user_id      ID del usuario de WordPress.
 * @param string $jid          JID del contacto de WhatsApp.
 * @param string $instanceName Nombre de la instancia de Evolution API.
 * @return bool True si el usuario ya tenía avatar o si se guardó uno nuevo correctamente, False en caso de error.
 */
function crm_fetch_and_save_avatar($user_id, $jid, $instanceName) {
    // *** INICIO: Verificar si ya existe un avatar para este usuario ***
    $existing_attachment_id = get_user_meta($user_id, '_crm_avatar_attachment_id', true);
    if ($existing_attachment_id) {
        crm_log("El usuario User ID: {$user_id} ya tiene un avatar asociado (Attachment ID: {$existing_attachment_id}). No se buscará ni actualizará.", 'INFO');
        return true; // Consideramos éxito ya que el objetivo (tener un avatar) se cumple.
    }

    crm_log("El usuario User ID: {$user_id} NO tiene avatar guardado. Procediendo a buscar uno.", 'INFO');

    // 1. Llamar a la API de Evolution para obtener la URL de la imagen de perfil
    $endpoint = "/chat/fetchProfilePictureUrl/{$instanceName}";
    $body = ['number' => $jid];

    $api_response = crm_evolution_api_request('POST', $endpoint, $body, null); // Usar POST y pasar el body

    if (is_wp_error($api_response)) {
        crm_log("Error API al obtener URL del avatar para JID {$jid}: " . $api_response->get_error_message(), 'ERROR');
        return false;
    }

    if (empty($api_response['profilePictureUrl'])) {
        crm_log("La API no devolvió una URL de avatar para JID {$jid}. Respuesta: " . json_encode($api_response), 'WARN');
        // No hacemos nada más si no hay URL, ya que no había avatar previo.
        return false;
    }

    $avatar_url = $api_response['profilePictureUrl'];
    crm_log("URL del avatar obtenida para JID {$jid}: {$avatar_url}", 'DEBUG');

    // 2. Descargar la imagen desde la URL obtenida
    // Necesitamos incluir los archivos necesarios para media_handle_sideload
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    // Descargar el archivo a una ubicación temporal
    $tmp = download_url($avatar_url);

    if (is_wp_error($tmp)) {
        crm_log("Error al descargar el avatar desde {$avatar_url}: " . $tmp->get_error_message(), 'ERROR');
        @unlink($tmp); // Eliminar archivo temporal si existe
        return false;
    }

    // 3. Preparar y guardar la imagen en la Media Library
    $file_array = array();
    // Extraer nombre de archivo de la URL o generar uno
    preg_match('/[^\/\&\?]+\.\w{3,4}(?=([\?&].*$|$))/i', $avatar_url, $matches);
    $file_name = !empty($matches[0]) ? sanitize_file_name($matches[0]) : sanitize_file_name("avatar_{$user_id}.jpg");

    $file_array['name'] = $file_name;
    $file_array['tmp_name'] = $tmp;

    // Descripción para el adjunto (opcional)
    $desc = sprintf(__('Avatar para %s', CRM_EVOLUTION_SENDER_TEXT_DOMAIN), get_userdata($user_id)->display_name);

    // Subir el archivo a la biblioteca de medios
    $attachment_id = media_handle_sideload($file_array, 0, $desc); // 0 significa que no está asociado a ningún post específico

    // Limpiar archivo temporal
    @unlink($tmp);

    if (is_wp_error($attachment_id)) {
        crm_log("Error al guardar el avatar en la Media Library: " . $attachment_id->get_error_message(), 'ERROR');
        return false;
    }

    // 4. Guardar el ID del nuevo adjunto en el metadato del usuario.
    // No necesitamos eliminar el anterior porque la verificación inicial asegura que no había uno.
    update_user_meta($user_id, '_crm_avatar_attachment_id', $attachment_id);
    crm_log("Avatar guardado con éxito en Media Library (Attachment ID: {$attachment_id}) y asociado al User ID: {$user_id}", 'INFO');

    return true;
}


/**
 * Función principal llamada desde el webhook para encontrar o crear usuarios para remoteJid y senderJid.
 *
 * @param string $remoteJid JID del contacto.
 * @param string $senderJid JID de nuestra instancia.
 * @param string|null $pushName Nombre del contacto (si es mensaje entrante).
 * @param bool $fromMe Indica si el mensaje original era saliente.
 * @param string $instanceName Nombre de la instancia que recibió el webhook.
 * @return array Array con ['remote_user_id' => ID, 'sender_user_id' => ID]. IDs pueden ser 0 si fallan.
 */
function crm_find_or_create_user_with_avatar($remoteJid, $senderJid, $pushName, $fromMe, $instanceName) {
    $user_ids = [
        'remote_user_id' => crm_process_single_jid($remoteJid, $instanceName, $pushName, !$fromMe), // Usar pushName si NO es fromMe (entrante)
        'sender_user_id' => crm_process_single_jid($senderJid, $instanceName, null, false), // Nunca usar pushName para el sender
    ];

    return $user_ids;
}

/**
 * Guarda un mensaje de chat como un CPT 'crm_chat'.
 * Maneja texto y archivos adjuntos (Base64).
 *
 * @param array $message_data Datos del mensaje extraídos del webhook. Debe incluir:
 *   'contact_user_id'     => (int) ID del usuario WP del contacto.
 *   'instance_name'       => (string) Nombre de la instancia.
 *   'sender_jid'          => (string) JID del remitente real (puede ser el contacto o la instancia).
 *   'recipient_jid'       => (string) JID del destinatario real (puede ser la instancia o el contacto/grupo).
 *   'is_outgoing'         => (bool) True si el mensaje fue enviado desde la instancia.
 *   'message_id_wa'       => (string) ID único del mensaje de WhatsApp.
 *   'timestamp_wa'        => (int) Timestamp UNIX del mensaje.
 *   'message_type'        => (string) Tipo de mensaje ('text', 'image', 'video', etc.).
 *   'message_text'        => (string|null) Contenido del mensaje de texto.
 *   'base64_data'         => (string|null) Datos del archivo adjunto en Base64.
 *   'media_mimetype'      => (string|null) MIME type del archivo adjunto.
 *   'media_caption'       => (string|null) Leyenda del archivo adjunto.
 *   'is_group_message'    => (bool) True si es un mensaje de grupo.
 *   'participant_jid'     => (string|null) JID del participante que envió el mensaje en un grupo.
 *
 * @return int|WP_Error El ID del post creado o WP_Error en caso de fallo.
 */
function crm_save_chat_message( $message_data ) {
    crm_log( 'Iniciando crm_save_chat_message con datos:', 'DEBUG', $message_data );

    // --- INICIO: Comprobación de Duplicados ---
    if ( ! empty( $message_data['whatsapp_message_id'] ) ) {
        $args = array(
            'post_type' => 'crm_chat',
            'post_status' => 'any', // Buscar en cualquier estado
            'posts_per_page' => 1,
            'meta_query' => array(
                array(
                    'key' => '_crm_message_id_wa',
                    'value' => sanitize_text_field( $message_data['whatsapp_message_id'] ),
                ),
            ),
            'fields' => 'ids', // Solo necesitamos saber si existe
        );
        $existing_posts = get_posts( $args );
        if ( ! empty( $existing_posts ) ) {
            crm_log( "Mensaje duplicado detectado para WA ID {$message_data['whatsapp_message_id']}. No se guardará.", 'INFO' );
            return $existing_posts[0]; // Devolver el ID del post existente podría ser útil
        }
    }
    // --- FIN: Comprobación de Duplicados ---

    $attachment_id = null;

    // 1. Procesar archivo adjunto si existe
    if ( ! empty( $message_data['base64_data'] ) && ! empty( $message_data['media_mimetype'] ) ) {
        crm_log( "Intentando procesar archivo adjunto Base64 (MIME: {$message_data['media_mimetype']}).", 'DEBUG' );
        $attachment_id = crm_process_base64_media(
            $message_data['base64_data'],
            $message_data['media_mimetype'],
            $message_data['media_caption'], // Pasar caption si existe
            $message_data['contact_user_id'] // Asociar media al usuario contacto
        );

        if ( is_wp_error( $attachment_id ) ) {
            crm_log( 'Error al procesar media Base64: ' . $attachment_id->get_error_message(), 'ERROR' );
            // Decidir si continuar sin el adjunto o devolver error. Por ahora, continuamos.
            $attachment_id = null;
        } else {
            crm_log( "Archivo adjunto procesado con éxito. Attachment ID: {$attachment_id}", 'INFO' );
        }
    }

    // 2. Preparar datos para wp_insert_post
    $post_title = sprintf(
        '%s %s %s (%s)',
        $message_data['is_outgoing'] ? 'Enviado a:' : 'Recibido de:',
        $message_data['is_group_message'] ? ($message_data['participant_jid'] ?? $message_data['sender_jid']) : $message_data['sender_jid'],
        $message_data['is_group_message'] ? "en {$message_data['recipient_jid']}" : '',
        wp_date( 'Y-m-d H:i', $message_data['timestamp_wa'] )
    );

    $post_content = $message_data['message_text'] ?? ($message_data['media_caption'] ?? ''); // Usar caption si no hay texto

    $post_data = array(
        'post_type'     => 'crm_chat',
        'post_status'   => 'publish',
        'post_author'   => $message_data['contact_user_id'], // Asociar al usuario contacto
        'post_title'    => sanitize_text_field( $post_title ),
        'post_content'  => wp_kses_post( $post_content ), // Permitir HTML seguro básico
        'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $message_data['timestamp_wa'] ),
    );

    // 3. Insertar el post
    $post_id = wp_insert_post( $post_data, true ); // true para devolver WP_Error si falla

    if ( is_wp_error( $post_id ) ) {
        crm_log( 'Error al insertar post crm_chat: ' . $post_id->get_error_message(), 'ERROR' );
        return $post_id;
    }

    crm_log( "Post crm_chat creado con ID: {$post_id}", 'INFO' );

    // 4. Guardar metadatos
    update_post_meta( $post_id, '_crm_contact_user_id', $message_data['contact_user_id'] );
    update_post_meta( $post_id, '_crm_instance_name', $message_data['instance_name'] );
    update_post_meta( $post_id, '_crm_sender_jid', $message_data['sender_jid'] );
    update_post_meta( $post_id, '_crm_recipient_jid', $message_data['recipient_jid'] );
    update_post_meta( $post_id, '_crm_is_outgoing', $message_data['is_outgoing'] );
    update_post_meta( $post_id, '_crm_message_id_wa', $message_data['message_id_wa'] );
    update_post_meta( $post_id, '_crm_timestamp_wa', $message_data['timestamp_wa'] );
    update_post_meta( $post_id, '_crm_message_type', $message_data['message_type'] );
    update_post_meta( $post_id, '_crm_is_group_message', $message_data['is_group_message'] );

    if ( $message_data['is_group_message'] && ! empty( $message_data['participant_jid'] ) ) {
        update_post_meta( $post_id, '_crm_participant_jid', $message_data['participant_jid'] );
    }
    if ( $attachment_id ) {
        update_post_meta( $post_id, '_crm_media_attachment_id', $attachment_id );
        // Asociar el adjunto al post de chat (además de al usuario)
        wp_update_post( array( 'ID' => $attachment_id, 'post_parent' => $post_id ) );
    }
    if ( ! empty( $message_data['media_mimetype'] ) ) {
        update_post_meta( $post_id, '_crm_media_mimetype', $message_data['media_mimetype'] );
    }
    if ( ! empty( $message_data['media_caption'] ) ) {
        update_post_meta( $post_id, '_crm_media_caption', $message_data['media_caption'] );
    }

    crm_log( "Metadatos guardados para el post crm_chat ID: {$post_id}", 'INFO' );

    return $post_id;
}

/**
 * Procesa datos Base64, los guarda en la Media Library y devuelve el ID del adjunto.
 *
 * @param string $base64_data Datos del archivo en Base64.
 * @param string $mime_type   MIME type del archivo.
 * @param string|null $caption Leyenda para el archivo (usada como título/descripción).
 * @param int $post_author_id ID del usuario al que atribuir la subida.
 * @return int|WP_Error ID del adjunto creado o WP_Error en caso de fallo.
 */
function crm_process_base64_media( $base64_data, $mime_type, $caption = null, $post_author_id = 0 ) {
    // Incluir archivos necesarios de WP
    require_once( ABSPATH . 'wp-admin/includes/media.php' );
    require_once( ABSPATH . 'wp-admin/includes/file.php' );
    require_once( ABSPATH . 'wp-admin/includes/image.php' );

    // Decodificar Base64
    $decoded_data = base64_decode( $base64_data );
    if ( $decoded_data === false ) {
        return new WP_Error( 'base64_decode_failed', 'No se pudo decodificar los datos Base64.' );
    }

    // Generar un nombre de archivo único
    $upload_dir = wp_upload_dir();
    $extension = mime_content_type_to_extension( $mime_type ); // Necesita una función auxiliar o mapeo
    if(!$extension) $extension = 'bin'; // Extensión por defecto si no se mapea
    $filename = wp_unique_filename( $upload_dir['path'], 'wa_media_' . time() . '.' . $extension );

    // Guardar los datos decodificados en un archivo temporal (o directamente con wp_upload_bits)
    $upload = wp_upload_bits( $filename, null, $decoded_data );

    if ( ! empty( $upload['error'] ) ) {
        return new WP_Error( 'wp_upload_bits_failed', $upload['error'] );
    }

    // Preparar datos para wp_insert_attachment
    $attachment = array(
        'guid'           => $upload['url'],
        'post_mime_type' => $mime_type,
        'post_title'     => sanitize_file_name( $caption ?: $filename ), // Usar caption o filename como título
        'post_content'   => '', // Descripción si la hubiera
        'post_status'    => 'inherit',
        'post_author'    => $post_author_id, // Atribuir al usuario contacto
    );

    // Insertar el adjunto en la base de datos
    $attachment_id = wp_insert_attachment( $attachment, $upload['file'] );

    if ( is_wp_error( $attachment_id ) ) {
        @unlink( $upload['file'] ); // Eliminar archivo si falla la inserción
        return $attachment_id;
    }

    // Generar metadatos del adjunto (ej: miniaturas para imágenes)
    $attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
    wp_update_attachment_metadata( $attachment_id, $attachment_data );

    return $attachment_id;
}

/**
 * Convierte un MIME type a una extensión de archivo común.
 * (Función auxiliar simple, se puede expandir)
 *
 * @param string $mime_type El MIME type.
 * @return string|false La extensión o false si no se conoce.
 */
function mime_content_type_to_extension($mime_type) {
    $mime_map = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'video/mp4'  => 'mp4',
        'video/mpeg' => 'mpeg',
        'video/quicktime' => 'mov',
        'audio/mpeg' => 'mp3',
        'audio/ogg'  => 'ogg',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        // Añadir más mapeos según sea necesario
    ];
    return $mime_map[$mime_type] ?? false;
}


?>
