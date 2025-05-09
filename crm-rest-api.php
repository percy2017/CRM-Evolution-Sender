<?php

require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Registra los endpoints de la API REST del plugin
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
 */
function crm_evolution_webhook_handler_callback( WP_REST_Request $request ) {

    // Obtener los datos JSON enviados por Evolution API
    $data = $request->get_json_params();
    // error_log('[Webhook] Datos recibidos: ' . json_encode($data));

    // Extraer Evento e Instancia
    $event = isset($data['event']) ? $data['event'] : 'evento_desconocido';
    $instance = isset($data['instance']) ? $data['instance'] : 'instancia_desconocida';
    error_log( "[Webhook][{$instance}] Evento: [{$event}]" ); // Formato consistente

    // Manejar eventos con switch
    switch ($event) {
        case 'messages.upsert':
            $message_payload = $data['data'] ?? null;
            $instance_name = sanitize_text_field($data['instance'] ?? 'desconocida');
            // $api_sender_jid = sanitize_text_field($data['sender'] ?? null);

            if (!$message_payload || !isset($message_payload['key'], $message_payload['messageTimestamp'])) {
                error_log("Error: Payload 'data' inválido o incompleto en 'messages.upsert'.");
                break;
            }

            $key_info = $message_payload['key'];
            $message_info = $message_payload['message'] ?? null;

            // Datos clave de identificación
            $remote_jid = sanitize_text_field($key_info['remoteJid'] ?? '');

            // Usamos crm_process_single_jid directamente para el contacto.
            $contact_user_id = crm_process_single_jid($remote_jid, $instance_name);
            error_log("[Webhook] author: ".$contact_user_id);
            if ($contact_user_id === 0) {
                // salir por que no hay usuario a quien asociar
                break;
            }
            // --- FIN: Procesar Usuario ---

            error_log("[Webhook] Inicio de registrando con el author: ".$contact_user_id);

            // --- INICIO: Extraer Contenido del Mensaje ---
            $message_id_wa = sanitize_text_field($key_info['id'] ?? '');
            $timestamp_wa = (int) $message_payload['messageTimestamp'];
            $from_me = (bool) ($key_info['fromMe'] ?? false);
            $message_type = 'unknown';
            $message_text = null;
            $media_caption = null;
            $media_mimetype = null; 
            $base64_data = $data['data']['message']['base64'] ?? null;
            
            // para grupos
            $participant =  sanitize_text_field($key_info['participant'] ?? '');
            $pushname = sanitize_text_field($message_payload['pushName'] ?? 'Desconocido');
            // error_log("PushName ---------- ".$message_payload['pushName']);
            // error_log("participant ---------- ".sanitize_text_field($key_info['participant']));
            // Extraer tipo y contenido específico
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
                $raw_mimetype = $message_info['audioMessage']['mimetype'] ?? 'audio/ogg';
                $mime_parts = explode(';', $raw_mimetype);
                $media_mimetype = trim($mime_parts[0]);
            } elseif (isset($message_info['documentWithCaptionMessage'])) {
                $message_type = 'document';
                $doc_msg = $message_info['documentWithCaptionMessage']['message']['documentMessage'] ?? null;
                if ($doc_msg) {
                    $media_caption = $doc_msg['caption'] ?? null;
                    $media_mimetype = $doc_msg['mimetype'] ?? 'application/octet-stream';
                    $filename = $doc_msg['fileName'] ?? null;
                    $message_text = $media_caption ?: $filename;
                }
            } elseif (isset($message_info['documentMessage'])) {
                $message_type = 'document';
                $media_caption = $message_info['documentMessage']['caption'] ?? null;
                $media_mimetype = $message_info['documentMessage']['mimetype'] ?? 'application/octet-stream'; // Default genérico
                $message_text = $media_caption ?: ($message_info['documentMessage']['fileName'] ?? null);
            }

            // Si hay base64 pero no se detectó tipo media, intentar deducir (o marcar como 'file')
            if ($base64_data && $message_type === 'unknown') {
                 $message_type = 'file';
            }
            // --- FIN: Extraer Contenido ---

            // --- INICIO: Preparar y Guardar Mensaje ---
            if ($message_type !== 'unknown' || $base64_data) { 
                error_log("[Webhook][{$instance}][messages.upsert] Preparando datos para guardar mensaje ID: {$message_id_wa}");
                $message_data_to_save = [
                    'contact_user_id'     => $contact_user_id,
                    'instance_name'       => $instance_name,
                    'is_outgoing'         => $from_me,
                    'message_id_wa'       => $message_id_wa,
                    'timestamp_wa'        => $timestamp_wa,
                    'message_type'        => $message_type,
                    'message_text'        => $message_text,
                    'base64_data'         => $base64_data,
                    'media_mimetype'      => $media_mimetype,
                    'media_caption'       => $media_caption,
                    'participant'     => $participant,
                    'pushname'     => $pushname
                ];

                $save_result = crm_save_chat_message($message_data_to_save);

                if (is_wp_error($save_result)) {
                    error_log("[Webhook][{$instance}][messages.upsert] Error al guardar mensaje WA ID {$message_id_wa}: " . $save_result->get_error_message());
                } else {
                    error_log("[Webhook][{$instance}][messages.upsert] Mensaje WA ID {$message_id_wa} guardado con éxito. Post ID: {$save_result}");
                }
            } else {
                 error_log("[Webhook][{$instance}][messages.upsert] Mensaje WA ID {$message_id_wa} ignorado (tipo desconocido y sin adjunto).");
            }
            // --- FIN: Preparar y Guardar Mensaje ---
        
            break;

        case 'connection.update':
            $connection_data = $data['data'] ?? null;
            $new_status = isset($connection_data['state']) ? sanitize_text_field($connection_data['state']) : null;

            if ($instance && $new_status) {
                // Guardar el nuevo estado en un transient que expira en 60 segundos
                set_transient('crm_instance_status_' . $instance, $new_status, 60);
                error_log( "[Webhook][{$instance}][connection.update] Estado actualizado a '{$new_status}'. Transient guardado." );
            } else {
                error_log( "[Webhook][{$instance}][connection.update] Evento recibido pero sin datos de estado válidos." );
            }
            break;

        case 'qrcode.updated': // <-- NUEVO CASE para manejar actualizaciones del QR
            error_log( "[Webhook][{$instance}][qrcode.updated] Procesando evento..." );
            $qr_data = $data['data'] ?? null;
            // La estructura exacta puede variar, ajusta según la respuesta real de tu API
            $new_qr_base64 = $qr_data['qrcode']['base64'] ?? ($qr_data['base64'] ?? null);

            if ($instance && $new_qr_base64) {
                // Asegurar prefijo data URI
                if (strpos($new_qr_base64, 'data:image') === false) {
                    $new_qr_base64 = 'data:image/png;base64,' . $new_qr_base64;
                }
                // Guardar el nuevo QR en un transient que expira en 60 segundos
                set_transient('crm_instance_qr_' . $instance, $new_qr_base64, 60);
                error_log( "[Webhook][{$instance}][qrcode.updated] Nuevo QR recibido. Transient guardado.");
            } else {
                error_log( "[Webhook][{$instance}][qrcode.updated] Evento recibido pero sin datos de QR válidos." );
            }
            break;
        default:
            error_log( "[Webhook][{$instance}] Evento '{$event}' recibido pero no manejado actualmente." );
            break;
    }


    // Siempre responder OK a Evolution API para que no reintente
    return new WP_REST_Response( array( 'status' => 'success', 'message' => 'Webhook received and processed.' ), 200 );

}

// creacion de nuevo usuario si noexiste
function crm_process_single_jid($remote_jid, $instanceName) {

    error_log("[Webhook] iniciendo  el registro para: ".$remote_jid." - ".$instanceName);
    // Buscar usuario existente por JID en user_meta
    $user_query = new WP_User_Query(array(
        'meta_key'   => '_crm_whatsapp_jid',
        'meta_value' => $remote_jid,
        'number'     => 1, // Solo necesitamos uno
        'fields'     => 'ID', // Solo obtener el ID
    ));

    $users = $user_query->get_results();
    $user_id = !empty($users) ? $users[0] : 0;

    if ($user_id) {
        error_log("[Webhook] retornando el usuario encontrado: ".$user_id);
        return $user_id; // Salir inmediatamente

    } else {

        if (strpos($remote_jid, '@s.whatsapp.net') === false) {
            error_log("[Webhook] Entro a Grupo: ".strpos($remote_jid, '@s.whatsapp.net'));
            if (strpos($remote_jid, '@g.us') !== false) {
                error_log("[Webhook] Procesando JID de grupo: " . $remote_jid);
        
                $group_info_api_data = array('groupJid' => $remote_jid);
                $group_info_response = crm_evolution_api_request_v2("/group/findGroupInfos/{$instanceName}", $group_info_api_data, 'GET', $instanceName);
        
                $group_name = $remote_jid; // Nombre por defecto si no se puede obtener de la API
                $group_picture_url = null;
        
                if (!is_wp_error($group_info_response) && isset($group_info_response['id']) && $group_info_response['id'] === $remote_jid) {
                    if (!empty($group_info_response['subject'])) {
                        $group_name = sanitize_text_field($group_info_response['subject']);
                        error_log("[Webhook] Nombre del grupo obtenido de API: " . $group_name);
                    } else {
                        error_log("[Webhook] Nombre del grupo (subject) no encontrado en la respuesta de la API para: " . $remote_jid);
                    }
                    if (!empty($group_info_response['pictureUrl'])) {
                        $group_picture_url = esc_url_raw($group_info_response['pictureUrl']);
                        error_log("[Webhook] Picture URL del grupo obtenida de API: " . $group_picture_url);
                    }
                } else {
                    $error_message = is_wp_error($group_info_response) ? $group_info_response->get_error_message() : 'Respuesta inesperada o JID no coincide.';
                    error_log("[Webhook] Error al obtener información del grupo {$remote_jid} desde la API: " . $error_message);
                }
                      
                // El grupo no existe, lo creamos
                $group_login = 'group_' . preg_replace('/[^0-9]/', '', $remote_jid);
                $group_email = preg_replace('/[^0-9]/', '', $remote_jid) . '@g.whatsapp.placeholder'; // Email único
                
                $user_data = array(
                    'user_login'    => $group_login,
                    'user_pass'     => wp_generate_password(12, true),
                    'user_email'    => $group_email,
                    'display_name'  => $group_name,
                    'first_name'    => $group_name, // Usar el nombre del grupo
                    'role'          => 'subscriber' // O el rol que consideres apropiado
                );
                $group_user_id = wp_insert_user($user_data);
    
                if (is_wp_error($group_user_id)) {
                    error_log('[Webhook] Error al crear usuario para el grupo ' . $remote_jid . ': ' . $group_user_id->get_error_message());
                    return 0; // Falló la creación del usuario
                }
                error_log("[Webhook] Usuario WP creado para el grupo {$remote_jid} con ID: {$group_user_id} y nombre: {$group_name}");
                update_user_meta($group_user_id, '_crm_is_group', true);
                update_user_meta($user_id, '_crm_is_favorite', false);
                update_user_meta($group_user_id, '_crm_whatsapp_jid', $remote_jid);
                update_user_meta($group_user_id, '_crm_instance_name', $instanceName);

                // Asignar la PRIMERA etiqueta definida como defecto al crear desde webhook
                $defined_tags = crm_get_lifecycle_tags();
                if (!empty($defined_tags)) {
                    $default_tag_key = array_key_first($defined_tags);
                    update_user_meta($group_user_id, '_crm_lifecycle_tag', $default_tag_key);
                }
        
                // Manejar la descarga y asignación del avatar del grupo si $group_picture_url no es null
                if ($group_picture_url && $group_user_id && !is_wp_error($group_user_id)) {
                    error_log("[Webhook] Intentando descargar avatar para grupo {$remote_jid} desde: {$group_picture_url}");
                    $tmp = download_url($group_picture_url);

                    if (is_wp_error($tmp)) {
                        error_log("[Webhook] Error al descargar avatar para grupo {$remote_jid}: " . $tmp->get_error_message());
                        @unlink($tmp); // Limpiar si se creó un archivo temporal parcial
                    } else {
                        $file_array = array();
                        preg_match('/[^\/\&\?]+\.\w{3,4}(?=([\?&].*$|$))/i', $group_picture_url, $matches);
                        $file_name = !empty($matches[0]) ? sanitize_file_name($matches[0]) : sanitize_file_name("group_avatar_" . preg_replace('/[^0-9]/', '', $remote_jid) . ".jpg");

                        $file_array['name'] = $file_name;
                        $file_array['tmp_name'] = $tmp;

                        $group_display_name = get_userdata($group_user_id)->display_name;
                        $desc = sprintf(__('Avatar para el grupo %s', CRM_EVOLUTION_SENDER_TEXT_DOMAIN), $group_display_name);

                        $attachment_id = media_handle_sideload($file_array, 0, $desc); // 0 = no asociado a un post específico

                        @unlink($tmp); // Limpiar archivo temporal después de sideload

                        if (is_wp_error($attachment_id)) {
                            error_log("[Webhook] Error al guardar avatar para grupo {$remote_jid} en Media Library: " . $attachment_id->get_error_message());
                        } else {
                            update_user_meta($group_user_id, '_crm_avatar_attachment_id', $attachment_id);
                            error_log("[Webhook] Avatar para grupo {$remote_jid} guardado con éxito. Attachment ID: {$attachment_id}");
                        }
                    }
                } elseif ($group_picture_url && (is_wp_error($group_user_id) || !$group_user_id)) {
                    error_log("[Webhook] No se intentó descargar avatar para grupo {$remote_jid} porque group_user_id no es válido.");
                }
        
                return $group_user_id; // Retornamos el ID del usuario WP del grupo
        
            } else {
                // No es @s.whatsapp.net ni @g.us, es un JID desconocido o no manejado
                error_log("[Webhook] JID no reconocido como individual ni como grupo: " . $remote_jid);
                return 0; // O manejar como error de otra forma
            }
        }else{
            // si es contacto
            $profile_response = crm_evolution_api_request('POST', "/chat/fetchProfile/{$instanceName}", ['number' => $remote_jid]); // Usa API Key global
            $number = strstr($remote_jid, '@', true);

            // Generar datos para el nuevo usuario
            $username = 'wa_' . $number;
            $email = $number . '@whatsapp.placeholder';
            $password = wp_generate_password(12, true);

            $first_name = '';
            $last_name = '';
            $name_parts = explode(' ', $profile_response['name'], 2);
            if (count($name_parts) > 1) {
                $first_name = $name_parts[0];
                $last_name = $name_parts[1];
            } else {
                $first_name = $profile_response['name'] ?? 'Desconocido';
            }

            $user_data = array(
                'user_login'   => $username,
                'user_email'   => $email,
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'user_pass'    => $password,
                'display_name' => $username,
                'role'         => 'subscriber',
            );

            // Intentar crear el usuario
            $user_id = wp_insert_user($user_data);

            if (is_wp_error($user_id)) {
                error_log("[Webhook] error: ".$user_id->get_error_code());
                if ('existing_user_login' === $user_id->get_error_code()) {
                    $username .= '_' . time();
                    $user_data['user_login'] = $username;
                    $user_id = wp_insert_user($user_data);
                    if (is_wp_error($user_id)) {
                        return 0;
                    }
                } else {
                    return 0; // Otro error al crear
                }
            }

        // Si se creó correctamente, guardar metadatos
        update_user_meta($user_id, '_crm_whatsapp_jid', $remote_jid);
        update_user_meta($user_id, '_crm_is_group', false);
        update_user_meta($user_id, '_crm_is_favorite', false);
        update_user_meta($user_id, '_crm_instance_name', $instanceName);
        update_user_meta($user_id, '_crm_isBusiness', isset($profile_response['isBusiness']) ? (bool)$profile_response['isBusiness'] : false);
        update_user_meta($user_id, '_crm_description', $profile_response['description'] ?? null);
        update_user_meta($user_id, '_crm_website', $profile_response['website'] ?? null);
        update_user_meta($user_id, 'billing_phone', $number);
        update_user_meta($user_id, 'billing_first_name', $first_name);
        update_user_meta($user_id, 'billing_last_name', $last_name);
        update_user_meta($user_id, 'billing_email', $email);

        
        // Asignar la PRIMERA etiqueta definida como defecto al crear desde webhook
        $defined_tags = crm_get_lifecycle_tags();
        if (!empty($defined_tags)) {
            $default_tag_key = array_key_first($defined_tags);
            update_user_meta($user_id, '_crm_lifecycle_tag', $default_tag_key);
        }

        // Descargar el archivo a una ubicación temporal
        $avatar_url = $profile_response['picture'];
        $tmp = download_url($avatar_url);
        if (is_wp_error($tmp)) {
            @unlink($tmp);
            return false;
        }

        $file_array = array();
        // Extraer nombre de archivo de la URL o generar uno
        preg_match('/[^\/\&\?]+\.\w{3,4}(?=([\?&].*$|$))/i', $avatar_url, $matches);
        $file_name = !empty($matches[0]) ? sanitize_file_name($matches[0]) : sanitize_file_name("avatar_{$user_id}.jpg");

        $file_array['name'] = $file_name;
        $file_array['tmp_name'] = $tmp;

        // Descripción para el adjunto (opcional)
        $desc = sprintf(__('Avatar para %s', CRM_EVOLUTION_SENDER_TEXT_DOMAIN), get_userdata($user_id)->display_name);

        // Subir el archivo a la biblioteca de medios
        $attachment_id = media_handle_sideload($file_array, 0, $desc);

        // Limpiar archivo temporal
        @unlink($tmp);

        if (is_wp_error($attachment_id)) {
            return false;
        }
        update_user_meta($user_id, '_crm_avatar_attachment_id', $attachment_id);
        }
        return $user_id;
    }
}


/**
 * Guarda un mensaje de chat como un CPT 'crm_chat'.
 */
function crm_save_chat_message( $message_data ) {
    error_log( '[crm_save_chat_message] Iniciando guardado para WA ID: ' . ($message_data['message_id_wa'] ?? 'N/A') );

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
            error_log( "[crm_save_chat_message] Mensaje duplicado detectado para WA ID {$message_data['whatsapp_message_id']}. No se guardará. Post existente: " . $existing_posts[0] );
            return $existing_posts[0]; // Devolver el ID del post existente podría ser útil
        }
    }
    // --- FIN: Comprobación de Duplicados ---


    $attachment_id = null;
    // 1. Procesar archivo adjunto si existe
    if ( ! empty( $message_data['base64_data'] ) && ! empty( $message_data['media_mimetype'] ) ) {
        error_log( "[crm_save_chat_message] Intentando procesar archivo adjunto Base64 (MIME: {$message_data['media_mimetype']})." );
        $attachment_id = crm_process_base64_media(
            $message_data['base64_data'],
            $message_data['media_mimetype'],
            $message_data['media_caption'], // Pasar caption si existe
            $message_data['contact_user_id'] // Asociar media al usuario contacto
        );

        if ( is_wp_error( $attachment_id ) ) {
            error_log( '[crm_save_chat_message] Error al procesar media Base64: ' . $attachment_id->get_error_message() );
            // Decidir si continuar sin el adjunto o devolver error. Por ahora, continuamos.
            $attachment_id = null;
        } else {
            error_log( "[crm_save_chat_message] Archivo adjunto procesado con éxito. Attachment ID: {$attachment_id}" );
        }
    }

    $post_content = $message_data['message_text'] ?? ($message_data['media_caption'] ?? ''); // Usar caption si no hay texto

    $post_data = array(
        'post_type'     => 'crm_chat',
        'post_status'   => 'publish',
        'post_author'   => $message_data['contact_user_id'],
        'post_title'    => 'Mensaje de: '.$message_data['contact_user_id'],
        'post_content'  => wp_kses_post( $post_content ),
        'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $message_data['timestamp_wa'] ),
    );

    // Insertar el post
    $post_id = wp_insert_post( $post_data, true ); // true para devolver WP_Error si falla
    if ( is_wp_error( $post_id ) ) {
        error_log( '[crm_save_chat_message] Error al insertar post crm_chat: ' . $post_id->get_error_message() );
        return $post_id;
    }

    // Guardar metadatos
    update_post_meta( $post_id, '_crm_contact_user_id', $message_data['contact_user_id'] );
    update_post_meta( $post_id, '_crm_instance_name', $message_data['instance_name'] );
    update_post_meta( $post_id, '_crm_is_outgoing', $message_data['is_outgoing'] );
    update_post_meta( $post_id, '_crm_message_id_wa', $message_data['message_id_wa'] );
    update_post_meta( $post_id, '_crm_timestamp_wa', $message_data['timestamp_wa'] );
    update_post_meta( $post_id, '_crm_message_type', $message_data['message_type'] );
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

    // Verificar si el mensaje es de un grupo y si hay datos del participante
    $contact_wp_user_id = $message_data['contact_user_id'] ?? 0;
    // El metadato _crm_is_group se establece en crm_process_single_jid cuando se crea el usuario para un grupo
    $is_group_chat = get_user_meta($contact_wp_user_id, '_crm_is_group', true);

    // Solo guardar información del participante si es un chat de grupo,
    // el mensaje NO es saliente (es decir, es recibido), y tenemos un JID de participante.
    if ($is_group_chat && !empty($message_data['participant'])) {
        $participant_jid = sanitize_text_field($message_data['participant']);
        // pushname ya viene sanitizado desde crm_evolution_webhook_handler_callback
        $participant_pushname = $message_data['pushname'];

        error_log("[crm_save_chat_message] Mensaje de GRUPO (User ID: {$contact_wp_user_id}). Participante JID: {$participant_jid}, PushName: {$participant_pushname}");

        update_post_meta($post_id, '_crm_participant', $participant_jid);
        update_post_meta($post_id, '_crm_pushName', $participant_pushname);

        if (strpos($message_data['participant'], '@s.whatsapp.net')){
            // para guardar el usuario ?
            error_log("[crm_save_chat_message] es un contacto valido (User ID: {$contact_wp_user_id}). Participante JID: {$participant_jid}, PushName: {$participant_pushname}");
        }
    }
    error_log( "[crm_save_chat_message] Metadatos guardados para el post crm_chat ID: {$post_id}" );

    return $post_id;
}

/**
 * Procesa datos Base64, los guarda en la Media Library y devuelve el ID del adjunto.
 */
function crm_process_base64_media( $base64_data, $mime_type, $caption = null, $post_author_id = 0 ) {
    error_log( "[crm_process_base64_media] Iniciando procesamiento para MIME: {$mime_type}" );
    // Incluir archivos necesarios de WP
    require_once( ABSPATH . 'wp-admin/includes/media.php' );
    require_once( ABSPATH . 'wp-admin/includes/file.php' );
    require_once( ABSPATH . 'wp-admin/includes/image.php' );

    // Decodificar Base64
    $decoded_data = base64_decode( $base64_data );
    if ( $decoded_data === false ) {
        error_log( "[crm_process_base64_media] Error: No se pudo decodificar los datos Base64." );
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
        error_log( "[crm_process_base64_media] Error en wp_upload_bits: " . $upload['error'] );
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
        error_log( "[crm_process_base64_media] Error en wp_insert_attachment: " . $attachment_id->get_error_message() );
        @unlink( $upload['file'] ); // Eliminar archivo si falla la inserción
        return $attachment_id;
    }

    // Generar metadatos del adjunto (ej: miniaturas para imágenes)
    $attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
    wp_update_attachment_metadata( $attachment_id, $attachment_data );

    error_log( "[crm_process_base64_media] Adjunto creado con éxito. ID: {$attachment_id}" );
    return $attachment_id;
}

/**
 * Convierte un MIME type a una extensión de archivo común.
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
        'application/msword' => 'doc', // <-- Añadido
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx', // <-- Añadido
        'application/vnd.ms-excel' => 'xls', // <-- Añadido
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx', // <-- Añadido
        // Añadir más mapeos según sea necesario
    ];
    return $mime_map[$mime_type] ?? false;
}

?>
