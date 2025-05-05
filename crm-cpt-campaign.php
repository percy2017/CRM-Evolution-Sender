<?php
/**
 * Registra el Custom Post Type 'crm_sender_campaign' para las campañas de marketing.
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
    die;
}

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
        'public'             => true,        // Hacerlo público para que aparezca en el admin
        'publicly_queryable' => true,        // Permitir consultas (útil para REST API)
        'show_ui'            => true,        // Mostrar interfaz de usuario en el admin
        'show_in_menu'       => false, // Mostrar como submenú de nuestro menú principal
        'query_var'          => true,        // Permitir query por nombre de post type
        'rewrite'            => array( 'slug' => 'marketing-campaigns' ), // Slug amigable para URLs (si se hiciera público)
        'capability_type'    => 'post',      // Usar capacidades estándar de post
        'has_archive'        => false,       // No necesitamos una página de archivo pública por ahora
        'hierarchical'       => false,       // No es jerárquico
        'menu_position'      => null,        // Posición dentro del submenú (null lo pone al final)
        'menu_icon'          => 'dashicons-megaphone', // Icono para el menú
        'supports'           => array(
                                    'title',          // Para el nombre de la campaña
                                    // 'editor',         // Para el mensaje (texto enriquecido)
                                    'author',         // Quién creó la campaña
                                    'custom-fields',  // Necesario para los meta boxes
                                    // 'thumbnail'    // Podríamos añadirlo si quisiéramos una imagen destacada
                                ),
        'show_in_rest'       => true,        // Exponer en la API REST (útil para futuras integraciones o JS avanzado)
        'rest_base'          => 'crm-campaigns', // Base para la ruta REST API
        'rest_controller_class' => 'WP_REST_Posts_Controller',
    );

    register_post_type( 'crm_sender_campaign', $args );
    //crm_log('Custom Post Type "crm_sender_campaign" registrado.');

}
add_action( 'init', 'crm_register_campaign_cpt' );

/**
 * Añade manualmente los submenús para el CPT 'crm_sender_campaign'.
 * Se engancha a 'admin_menu' para asegurar que el menú principal ya exista.
 */
function crm_add_campaign_submenus() {
    // Submenú para "Todas las Campañas"
    add_submenu_page(
        'crm-evolution-sender-main',                     // Slug del menú padre
        __( 'Todas las Campañas', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), // Título de la página
        __( 'Campañas', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),          // Título del submenú (más corto)
        'edit_posts',                                    // Capacidad requerida (la misma que para ver posts)
        'edit.php?post_type=crm_sender_campaign',        // Slug: URL directa a la lista del CPT
        null,                                            // No necesita función de callback, WP maneja edit.php
        30 // Posición relativa dentro del submenú (opcional)
    );
    // Submenú para "Añadir Nueva Campaña"
    // add_submenu_page(
    //     'crm-evolution-sender-main',                     // Slug del menú padre (puede ser null si no queremos que aparezca como otro item)
    //     __( 'Añadir Nueva Campaña', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), // Título de la página
    //     __( 'Añadir Nueva', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),      // Título del submenú
    //     'edit_posts',                                    // Capacidad requerida (la misma que para crear posts)
    //     'post-new.php?post_type=crm_sender_campaign'     // Slug: URL directa para añadir nuevo CPT
    // );
}
add_action( 'admin_menu', 'crm_add_campaign_submenus', 30 ); // Prioridad 30 para ejecutar después del menú principal (prioridad por defecto 10)


// =========================================================================
// == META BOXES PARA CAMPAÑAS ==
// =========================================================================

/**
 * Obtiene las instancias activas formateadas para un select.
 * @return array [ 'instance_name' => 'instance_name', ... ]
 */
function crm_get_active_instances_options() {
    // Reutilizamos la lógica de la función AJAX, pero la llamamos directamente
    $instances_data = [];
    $api_response = crm_evolution_api_request( 'GET', '/instance/fetchInstances' ); // Asume que crm_evolution_api_request está disponible

    if ( !is_wp_error( $api_response ) && is_array( $api_response ) ) {
        foreach ( $api_response as $instance ) {
            $status = isset($instance['instance']['status']) ? $instance['instance']['status'] : 'unknown';
            // Considerar activas las que están conectadas o esperando QR
            if ( ($status === 'open' || $status === 'connected' || $status === 'connection') && isset($instance['instance']['instanceName']) ) {
                 $name = $instance['instance']['instanceName'];
                 $instances_data[ esc_attr( $name ) ] = esc_html( $name ); // Usar nombre como clave y valor
            }
        }
    } else {
        //crm_log("Error al obtener instancias para select en CPT: " . (is_wp_error($api_response) ? $api_response->get_error_message() : 'Respuesta inválida'), 'ERROR');
    }
    //crm_log("Instancias activas para select CPT: " . print_r($instances_data, true), 'DEBUG');
    return $instances_data;
}

/**
 * Obtiene las etiquetas de ciclo de vida formateadas para un select.
 * @return array [ 'tag_slug' => 'Tag Label', ... ]
 */
function crm_get_etiquetas_options() {
    // Reutilizamos la función de crm-setting.php
    $tags = function_exists('crm_get_lifecycle_tags') ? crm_get_lifecycle_tags() : get_option( 'crm_evolution_lifecycle_tags', array() );
    $options = array();
    foreach ($tags as $slug => $name) {
        $options[esc_attr($slug)] = esc_html($name);
    }
    //crm_log("Etiquetas para select CPT: " . print_r($options, true), 'DEBUG');
    return $options;
}

/**
 * Añade los meta boxes a la pantalla de edición de campañas.
 */
function crm_campaign_add_meta_boxes() {
    // Meta Box Principal (Configuración)
    add_meta_box(
        'crm_campaign_settings',                     // ID único del meta box
        __( 'Configuración de la Campaña', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), // Título visible del meta box
        'crm_campaign_settings_meta_box_html',       // Función callback que mostrará el HTML
        'crm_sender_campaign',                       // El CPT donde se mostrará
        'normal',                                    // Contexto (normal, side, advanced)
        'high'                                       // Prioridad
    );
    // Meta Box Lateral para Instancias
    add_meta_box(
        'crm_campaign_instances_metabox',            // ID único
        __( 'Instancias de Envío', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), // Título
        'crm_campaign_instances_metabox_html',       // Callback HTML
        'crm_sender_campaign',                       // CPT
        'side',                                      // Contexto lateral
        'high'                                       // Prioridad
    );
    // Meta Box Lateral para Segmentación (Etiquetas)
    add_meta_box(
        'crm_campaign_segmentation_metabox',         // ID único
        __( 'Segmentación', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), // Título
        'crm_campaign_segmentation_metabox_html',    // Callback HTML
        'crm_sender_campaign',                       // CPT
        'side',                                      // Contexto lateral
        'high'                                       // Prioridad
    );
    // Aquí podríamos añadir más meta boxes si quisiéramos separar campos
}
add_action( 'add_meta_boxes_crm_sender_campaign', 'crm_campaign_add_meta_boxes' );

/**
 * Muestra el HTML para el meta box de configuración de la campaña.
 * @param WP_Post $post El objeto del post actual.
 */
function crm_campaign_settings_meta_box_html( $post ) {
    // Añadir un nonce field para verificación de seguridad
    wp_nonce_field( 'crm_save_campaign_meta_box_data', 'crm_campaign_meta_box_nonce' );

    // Obtener valores guardados
    $interval       = get_post_meta( $post->ID, '_crm_campaign_interval_minutes', true );
    $media_url      = get_post_meta( $post->ID, '_crm_campaign_media_url', true );
    $message_text   = get_post_meta( $post->ID, '_crm_campaign_message_text', true ); // Nuevo meta para el mensaje

    ?>
    <table class="form-table">
        <tbody>
            <!-- Los campos de Instancias y Etiquetas se han movido a sus propios meta boxes -->

            <!-- Los campos restantes permanecen aquí -->

             <!-- Intervalo -->
            <tr>
                <th><label for="crm_campaign_interval_minutes"><?php _e( 'Intervalo entre mensajes (minutos)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label></th>
                <td>
                    <input type="number" id="crm_campaign_interval_minutes" name="crm_campaign_interval_minutes" value="<?php echo esc_attr( $interval ? $interval : 5 ); ?>" min="1" step="1" class="small-text">
                    <p class="description"><?php _e( 'Tiempo mínimo en minutos entre el envío de cada mensaje.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
                </td>
            </tr>

            <!-- Mensaje -->
            <tr>
                <th><label for="crm_campaign_message_text"><?php _e( 'Mensaje de la Campaña', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label></th>
                <td colspan="1"> <?php // Usamos colspan para que ocupe el espacio ?>
                    <?php
                    // Usamos wp_editor para tener texto enriquecido
                    wp_editor( $message_text, 'crm_campaign_message_text_editor', array(
                        'textarea_name' => 'crm_campaign_message_text', // IMPORTANTE: El nombre que se enviará y guardará
                        'textarea_rows' => 10, // Altura del editor
                        'media_buttons' => false, // Opcional: quitar botones de medios si no se necesitan aquí
                        'tinymce'       => true, // Usar TinyMCE
                    ) );
                    ?>
                    <p class="description"><?php _e( 'Escribe aquí el mensaje que se enviará. Puedes usar {nombre}, {apellido}, {email} como placeholders.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
                </td>
            </tr>

             <!-- URL de Medios -->
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
    // El nonce ya está en el meta box principal, no es necesario repetirlo aquí
    // si todos los campos se guardan en la misma función 'save_post'.

    $instance_names = get_post_meta( $post->ID, '_crm_campaign_instance_names', true );
    if ( ! is_array( $instance_names ) ) $instance_names = array();
    $active_instances = crm_get_active_instances_options();
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
    $available_tags = crm_get_etiquetas_options();
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
 * Guarda los datos personalizados enviados desde el meta box de configuración de la campaña.
 *
 * @param int $post_id El ID del post que se está guardando.
 */
function crm_save_campaign_meta_box_data( $post_id ) {

    // 1. Verificar Nonce
    if ( ! isset( $_POST['crm_campaign_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['crm_campaign_meta_box_nonce'], 'crm_save_campaign_meta_box_data' ) ) {
        //crm_log("Error al guardar meta de campaña {$post_id}: Nonce inválido.", 'ERROR');
        return;
    }

    // 2. Ignorar Autosave
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // 3. Verificar Permisos del Usuario
    // Asegurarse de que el post_type sea el correcto antes de verificar permisos
    if ( ! isset($_POST['post_type']) || 'crm_sender_campaign' !== $_POST['post_type'] ) {
        return; // Salir si no es nuestro CPT (importante para evitar ejecutar en otros post types)
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
         //crm_log("Error al guardar meta de campaña {$post_id}: Permiso denegado.", 'ERROR');
        return;
    }

    // 4. Sanitizar y Guardar cada campo meta

    // Instancias (array de strings)
    $instance_names = isset( $_POST['crm_campaign_instance_names'] ) && is_array( $_POST['crm_campaign_instance_names'] )
                      ? array_map( 'sanitize_key', $_POST['crm_campaign_instance_names'] )
                      : array();
    update_post_meta( $post_id, '_crm_campaign_instance_names', $instance_names );

    // Etiquetas (array de strings)
    $target_tags = isset( $_POST['crm_campaign_target_tags'] ) && is_array( $_POST['crm_campaign_target_tags'] )
                   ? array_map( 'sanitize_key', $_POST['crm_campaign_target_tags'] )
                   : array();
    update_post_meta( $post_id, '_crm_campaign_target_tags', $target_tags );

    // Intervalo (número entero)
    $interval = isset( $_POST['crm_campaign_interval_minutes'] ) ? absint( $_POST['crm_campaign_interval_minutes'] ) : 5;
    update_post_meta( $post_id, '_crm_campaign_interval_minutes', $interval );

    // Mensaje (texto enriquecido - usar wp_kses_post para seguridad)
    $message = isset( $_POST['crm_campaign_message_text'] ) ? wp_kses_post( wp_unslash( $_POST['crm_campaign_message_text'] ) ) : '';
    update_post_meta( $post_id, '_crm_campaign_message_text', $message );

    // URL de Medios (URL)
    $media_url = isset( $_POST['crm_campaign_media_url'] ) ? esc_url_raw( wp_unslash( $_POST['crm_campaign_media_url'] ) ) : '';
    update_post_meta( $post_id, '_crm_campaign_media_url', $media_url );

    //crm_log("Metadatos de campaña guardados para Post ID: {$post_id}", 'INFO');
}
add_action( 'save_post_crm_sender_campaign', 'crm_save_campaign_meta_box_data' );

// =========================================================================
// == PERSONALIZAR MENSAJES DE ACTUALIZACIÓN ==
// =========================================================================

/**
 * Personaliza los mensajes de actualización para el CPT 'crm_sender_campaign'.
 * Reemplaza el enlace "Ver entrada" por "Volver al listado".
 *
 * @param array $messages Array de mensajes de actualización por tipo de post.
 * @return array Array de mensajes modificado.
 */
function crm_campaign_updated_messages( $messages ) {
    global $post, $post_ID;

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
 *
 * @param string  $new_status Nuevo estado del post.
 * @param string  $old_status Antiguo estado del post.
 * @param WP_Post $post       Objeto del post.
 */
function crm_handle_campaign_scheduling_on_status_change( $new_status, $old_status, $post ) {
    // Solo actuar si es nuestro CPT
    if ( $post->post_type !== 'crm_sender_campaign' ) {
        return;
    }

    $post_id = $post->ID;
    //crm_log( "[CRON_SCHEDULER] transition_post_status para Campaña ID: {$post_id}. De '{$old_status}' a '{$new_status}'", 'DEBUG' );

    // Limpiar siempre cualquier programación previa al cambiar estado relevante
    $timestamp = wp_next_scheduled( 'crm_process_campaign_batch', array( $post_id ) );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'crm_process_campaign_batch', array( $post_id ) );
        //crm_log( "[CRON_SCHEDULER][{$post_id}] Tarea cron previa desprogramada.", 'INFO' );
    }

    // Si el NUEVO estado es 'publish', programar la tarea
    if ( $new_status === 'publish' ) {
        wp_schedule_single_event( time() + 5, 'crm_process_campaign_batch', array( $post_id ) ); // +5 segundos para asegurar que el guardado termine
        //crm_log( "[CRON_SCHEDULER][{$post_id}] Estado es 'publish'. Primera ejecución de 'crm_process_campaign_batch' programada.", 'INFO' );

        // Opcional: Resetear contadores al iniciar/reiniciar si viene de un estado no publicado
        if ($old_status !== 'publish') {
             update_post_meta( $post_id, '_crm_campaign_sent_count', 0 );
             update_post_meta( $post_id, '_crm_campaign_failed_count', 0 );
             update_post_meta( $post_id, '_crm_campaign_last_processed_user_id', 0 );
             //crm_log( "[CRON_SCHEDULER][{$post_id}] Contadores reseteados.", 'INFO' );
        }
    }
}
add_action( 'transition_post_status', 'crm_handle_campaign_scheduling_on_status_change', 10, 3 );

/**
 * Función que se ejecutará vía WP-Cron para procesar un lote (o un mensaje) de la campaña.
 * (Placeholder - La lógica real irá aquí)
 *
 * @param int $campaign_id ID del post de la campaña a procesar.
 */
function crm_process_campaign_batch_callback( $campaign_id ) {
    //crm_log( "[CRON] Iniciando procesamiento para Campaña ID: {$campaign_id}", 'INFO' );

    // 1. Obtener datos de la campaña y verificar estado
    $campaign_post = get_post( $campaign_id );
    if ( ! $campaign_post || $campaign_post->post_type !== 'crm_sender_campaign' ) {
        //crm_log( "[CRON][{$campaign_id}] Error: Post no encontrado o no es una campaña.", 'ERROR' );
        return;
    }
    if ( $campaign_post->post_status !== 'publish' ) {
        //crm_log( "[CRON][{$campaign_id}] Campaña no está publicada (estado: {$campaign_post->post_status}). Deteniendo envío.", 'INFO' );
        // No reprogramar
        return;
    }

    // Obtener metadatos
    $target_tags      = get_post_meta( $campaign_id, '_crm_campaign_target_tags', true );
    $interval_minutes = get_post_meta( $campaign_id, '_crm_campaign_interval_minutes', true );
    $message_text     = get_post_meta( $campaign_id, '_crm_campaign_message_text', true );
    $media_url        = get_post_meta( $campaign_id, '_crm_campaign_media_url', true );
    $last_processed_user_id = get_post_meta( $campaign_id, '_crm_campaign_last_processed_user_id', true ) ?: 0; // Default 0
    // $instance_names = get_post_meta( $campaign_id, '_crm_campaign_instance_names', true ); // Para rotación futura

    if ( empty( $target_tags ) || ! is_array( $target_tags ) ) {
        //crm_log( "[CRON][{$campaign_id}] Error: No hay etiquetas objetivo definidas o el formato es incorrecto.", 'ERROR' );
        return; // No podemos continuar sin etiquetas
    }
    $interval_seconds = absint( $interval_minutes ?: 5 ) * 60; // Default 5 min

    // 2. Buscar el siguiente usuario
    $user_meta_key_sent = '_crm_campaign_sent_' . $campaign_id; // Clave única para esta campaña
    $user_query_args = array(
        'number' => 1, // Solo queremos el siguiente usuario
        'orderby' => 'ID',
        'order' => 'ASC',
        'fields' => 'ID', // Solo necesitamos el ID
        'meta_query' => array(
            'relation' => 'AND',
            // Condición 1: Debe tener JID
            array(
                'key' => '_crm_whatsapp_jid',
                'compare' => 'EXISTS',
            ),
             array(
                'key' => '_crm_whatsapp_jid',
                'value' => '',
                'compare' => '!=',
            ),
            // Condición 2: Su ID debe ser mayor que el último procesado (Manejado por pre_get_users)
            // array( // Dejar comentado, se maneja con el hook
            //     'key' => 'ID',
            //     'value' => $last_processed_user_id,
            //     'compare' => '>',
            //     'type' => 'NUMERIC',
            // ),
            // Condición 3: No debe tener la marca de 'enviado' para esta campaña
            array(
                'key' => $user_meta_key_sent,
                'compare' => 'NOT EXISTS', // Más eficiente que comparar con 'false' o ''
            ),
            // Condición 4: Debe tener al menos una de las etiquetas objetivo
            array(
                'relation' => 'OR', // El usuario debe tener CUALQUIERA de las etiquetas
            )
        ),
        // Añadir las etiquetas objetivo a la sub-query 'OR'
    );
    foreach ($target_tags as $tag_slug) {
        $user_query_args['meta_query'][4][] = array(
            'key' => '_crm_lifecycle_tag',
            'value' => $tag_slug,
            'compare' => '=',
        );
    }

    //crm_log( "[CRON][{$campaign_id}] WP_User_Query Args: " . print_r($user_query_args, true), 'DEBUG' ); // <-- Log Args

    // --- Ajuste para 'ID > last_processed_user_id' ---
    // WP_User_Query no soporta 'ID' en meta_query. Usamos el filtro 'pre_get_users'.
    add_action( 'pre_get_users', function( $query ) use ( $last_processed_user_id ) {
        // Comprobar si es nuestra consulta (usando una clave específica como indicador)
        $is_our_query = false;
        if (isset($query->query_vars['meta_query'])) {
            foreach($query->query_vars['meta_query'] as $mq_clause) {
                // Usar una clave que sepamos que solo está en nuestra consulta
                if (is_array($mq_clause) && isset($mq_clause['key']) && $mq_clause['key'] === '_crm_whatsapp_jid') {
                    $is_our_query = true;
                    break;
                }
            }
        }

        if ($is_our_query) { // Aplicar solo si es nuestra consulta
            global $wpdb;
            // Aplicar la condición ID > last_processed_user_id (funciona incluso si last_processed_user_id es 0)
            $query->query_where .= $wpdb->prepare( " AND {$wpdb->users}.ID > %d", $last_processed_user_id );
        }
        // Remover la acción para no afectar otras queries
        remove_action( 'pre_get_users', __FUNCTION__ );
    });

    $user_query = new WP_User_Query( $user_query_args );
    $next_user = $user_query->get_results();
    //crm_log( "[CRON][{$campaign_id}] WP_User_Query Results: " . print_r($next_user, true), 'DEBUG' ); // <-- Log Results

    // 3. Procesar resultado de la búsqueda
    if ( empty( $next_user ) ) {
        // No hay más usuarios que cumplan los criterios
        //crm_log( "[CRON][{$campaign_id}] No se encontraron más usuarios pendientes (después de ID: {$last_processed_user_id}). Campaña completada.", 'INFO' );
        // Opcional: Marcar campaña como completada en un meta
        // update_post_meta( $campaign_id, '_crm_campaign_status', 'completed' );
        // NO reprogramar
        return;
    }

    // 4. Tenemos un usuario para procesar
    $user_id_to_process = $next_user[0];
    //crm_log( "[CRON][{$campaign_id}] Procesando siguiente usuario ID: {$user_id_to_process}", 'INFO' );

    // --- Lógica de envío (simplificada por ahora, usa la primera instancia activa) ---
    $send_result = null;
    if ( ! empty( $media_url ) ) {
        // Enviar mensaje multimedia
        // Necesitamos mime_type y filename si es posible
        $path = wp_parse_url( $media_url, PHP_URL_PATH );
        $filename = basename( $path );
        // Intentar obtener mime type (puede requerir más lógica si no está en la URL)
        $filetype = wp_check_filetype( $filename );
        $mime_type = $filetype['type'] ?? null;

        if ($mime_type) {
             //crm_log( "[CRON][{$campaign_id}] Intentando enviar MEDIA a User ID {$user_id_to_process}. URL: {$media_url}", 'DEBUG' );
             $send_result = crm_send_whatsapp_media_message( $user_id_to_process, $media_url, $mime_type, $filename, $message_text ); // El texto va como caption
        } else {
             //crm_log( "[CRON][{$campaign_id}] Error: No se pudo determinar el tipo MIME para {$media_url}. No se enviará media.", 'ERROR' );
             $send_result = new WP_Error('mime_error', 'No se pudo determinar el tipo MIME del archivo.');
        }
    } elseif ( ! empty( $message_text ) ) {
        // --- INICIO: Reemplazar Placeholders ---
        $user_data = get_userdata( $user_id_to_process );
        $processed_message = $message_text; // Copia para modificar
        if ($user_data) {
            $replacements = array(
                '{nombre}'   => $user_data->first_name ?: $user_data->display_name, // Usar display_name si first_name está vacío
                '{apellido}' => $user_data->last_name ?: '',
                '{email}'    => $user_data->user_email ?: '',
            );
            $processed_message = str_replace( array_keys($replacements), array_values($replacements), $message_text );
        }
        // --- FIN: Reemplazar Placeholders ---
        // Enviar mensaje de texto
        //crm_log( "[CRON][{$campaign_id}] Intentando enviar TEXTO procesado a User ID {$user_id_to_process}.", 'DEBUG' );
        $send_result = crm_send_whatsapp_message( $user_id_to_process, $processed_message ); // <-- Usar mensaje procesado
    } else {
         //crm_log( "[CRON][{$campaign_id}] Error: No hay ni mensaje ni media URL para enviar.", 'ERROR' );
         $send_result = new WP_Error('no_content', 'No hay contenido para enviar.');
    }

    // 5. Actualizar estado y contadores
    if ( is_wp_error( $send_result ) ) {
        //crm_log( "[CRON][{$campaign_id}] Fallo al enviar a User ID {$user_id_to_process}: " . $send_result->get_error_message(), 'ERROR' );
        // Incrementar contador de fallos
        $failed_count = (int) get_post_meta( $campaign_id, '_crm_campaign_failed_count', true );
        update_post_meta( $campaign_id, '_crm_campaign_failed_count', $failed_count + 1 );
    } else {
        //crm_log( "[CRON][{$campaign_id}] Envío exitoso (o API aceptó) a User ID {$user_id_to_process}.", 'INFO' );
        // Incrementar contador de éxitos
        $sent_count = (int) get_post_meta( $campaign_id, '_crm_campaign_sent_count', true );
        update_post_meta( $campaign_id, '_crm_campaign_sent_count', $sent_count + 1 );
        // Marcar usuario como enviado para ESTA campaña
        update_user_meta( $user_id_to_process, $user_meta_key_sent, true );
    }

    // Actualizar el último ID procesado (incluso si falló, para no reintentar indefinidamente)
    update_post_meta( $campaign_id, '_crm_campaign_last_processed_user_id', $user_id_to_process );

    // 6. Reprogramar el siguiente evento
    $next_schedule_time = time() + $interval_seconds;
    wp_schedule_single_event( $next_schedule_time, 'crm_process_campaign_batch', array( $campaign_id ) );
    //crm_log( "[CRON][{$campaign_id}] Siguiente ejecución programada para dentro de {$interval_minutes} minutos.", 'INFO' );

}
add_action( 'crm_process_campaign_batch', 'crm_process_campaign_batch_callback', 10, 1 );

// =========================================================================
// == PERSONALIZAR COLUMNAS EN EL LISTADO DE CAMPAÑAS ==
// =========================================================================

/**
 * Añade columnas personalizadas a la tabla de administración de campañas.
 *
 * @param array $columns Array existente de columnas.
 * @return array Array modificado de columnas.
 */
function crm_campaign_add_admin_columns( $columns ) {
    // Guardar la columna 'date' para reinsertarla al final
    $date_column = $columns['date'];
    unset( $columns['date'] );

    // Añadir nuevas columnas
    $columns['crm_status']    = __( 'Estado', CRM_EVOLUTION_SENDER_TEXT_DOMAIN );
    $columns['crm_tags']      = __( 'Etiquetas', CRM_EVOLUTION_SENDER_TEXT_DOMAIN );
    $columns['crm_instances'] = __( 'Instancias', CRM_EVOLUTION_SENDER_TEXT_DOMAIN );
    $columns['crm_progress']  = __( 'Progreso (Enviados/Fallidos)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN );
    $columns['crm_interval']  = __( 'Intervalo (min)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN );

    // Reinsertar la columna 'date'
    $columns['date'] = $date_column;

    return $columns;
}
add_filter( 'manage_crm_sender_campaign_posts_columns', 'crm_campaign_add_admin_columns' );

/**
 * Muestra el contenido de las columnas personalizadas en la tabla de administración.
 *
 * @param string $column_name El nombre de la columna actual.
 * @param int    $post_id     El ID del post actual.
 */
function crm_campaign_render_admin_columns( $column_name, $post_id ) {
    switch ( $column_name ) {
        case 'crm_status':
            $post_status = get_post_status( $post_id );
            $campaign_status = get_post_meta( $post_id, '_crm_campaign_status', true ); // Podríamos usar este meta en el futuro
            $next_scheduled = wp_next_scheduled( 'crm_process_campaign_batch', array( $post_id ) );

            if ( $post_status === 'publish' ) {
                if ( $next_scheduled ) {
                    echo '<span style="color: orange;">' . esc_html__( 'Enviando', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) . '</span>';
                } else {
                    // Si está publicada pero no hay próxima tarea, podría estar completada o pausada
                    // Necesitaríamos el meta '_crm_campaign_status' para diferenciar mejor
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
                // Opcional: Obtener nombres legibles de las etiquetas si es necesario
                echo esc_html( implode( ', ', $tags ) );
            } else {
                echo '—';
            }
            break;

        case 'crm_instances':
            $instances = get_post_meta( $post_id, '_crm_campaign_instance_names', true );
            if ( ! empty( $instances ) && is_array( $instances ) ) {
                echo esc_html( implode( ', ', $instances ) );
            } else {
                echo '—';
            }
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
 *
 * @param WP_Post $post El objeto del post actual.
 */
function crm_add_back_to_list_button_to_publish_box( $post ) {
    // Asegurarse de que estamos en el CPT correcto
    if ( 'crm_sender_campaign' !== $post->post_type ) {
        return;
    }

    // Obtener la URL del listado
    $list_url = admin_url( 'edit.php?post_type=crm_sender_campaign' );

    // Generar el botón/enlace dentro de una sección misc
    echo '<div class="misc-pub-section misc-pub-back-to-list" style="padding-top: 5px; padding-bottom: 5px;">'; // Añadir algo de padding
    echo '<span class="dashicons dashicons-arrow-left-alt" style="vertical-align:middle; margin-right: 3px;"></span>';
    echo '<a href="' . esc_url( $list_url ) . '" class="button button-secondary button-small">' . esc_html__( 'Volver al Listado', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) . '</a>';
    echo '</div>';
}
add_action( 'post_submitbox_misc_actions', 'crm_add_back_to_list_button_to_publish_box', 10, 1 );


?>