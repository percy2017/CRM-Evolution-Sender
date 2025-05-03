<?php

/**
 * Registra el Custom Post Type 'crm_chat' para almacenar los mensajes.
 */
function crm_evolution_register_chat_cpt() {

    $labels = array(
        'name'                  => _x( 'Chats CRM', 'Post type general name', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'singular_name'         => _x( 'Chat CRM', 'Post type singular name', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'menu_name'             => _x( 'Chats CRM', 'Admin Menu text', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'name_admin_bar'        => _x( 'Chat CRM', 'Add New on Toolbar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'add_new'               => __( 'Añadir Nuevo', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'add_new_item'          => __( 'Añadir Nuevo Chat', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'new_item'              => __( 'Nuevo Chat', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'edit_item'             => __( 'Editar Chat', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'view_item'             => __( 'Ver Chat', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'all_items'             => __( 'Todos los Chats', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'search_items'          => __( 'Buscar Chats', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'parent_item_colon'     => __( 'Chat Padre:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'not_found'             => __( 'No se encontraron chats.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'not_found_in_trash'    => __( 'No se encontraron chats en la papelera.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'featured_image'        => _x( 'Imagen Destacada del Chat', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'set_featured_image'    => _x( 'Establecer imagen destacada', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'remove_featured_image' => _x( 'Quitar imagen destacada', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'use_featured_image'    => _x( 'Usar como imagen destacada', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'archives'              => _x( 'Archivos de Chats', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'insert_into_item'      => _x( 'Insertar en chat', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'uploaded_to_this_item' => _x( 'Subido a este chat', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'filter_items_list'     => _x( 'Filtrar lista de chats', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'items_list_navigation' => _x( 'Navegación de lista de chats', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'items_list'            => _x( 'Lista de chats', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false,  // No accesible públicamente en el frontend
        'publicly_queryable' => false,  // No se puede consultar directamente por URL
        'show_ui'            => true,   // Mostrar en el panel de administración
        'show_in_menu'       => false, // Mostrar como submenú de 'CRM Evolution'
        'query_var'          => false,  // No necesita query var
        'rewrite'            => false,  // No necesita reglas de reescritura
        'capability_type'    => 'post', // Capacidades basadas en 'post' (edit_posts, etc.)
        'has_archive'        => false,  // No necesita página de archivo
        'hierarchical'       => false,  // No es jerárquico (como las páginas)
        'menu_position'      => null,   // Posición dentro del submenú
        'menu_icon'          => 'dashicons-format-chat', // Icono de chat
        'supports'           => array( 'title', 'editor', 'author', 'custom-fields' ), // Funcionalidades soportadas
        'show_in_rest'       => false, // No exponer en la API REST por ahora
    );

    register_post_type( 'crm_chat', $args );
    // crm_log('Custom Post Type "crm_chat" registrado.'); // Log para confirmar
}
add_action( 'init', 'crm_evolution_register_chat_cpt' );



/**
 * Registra el Meta Box para mostrar detalles del chat en la pantalla de edición.
 *
 * @param string $post_type El tipo de post actual.
 * @param WP_Post $post El objeto del post actual.
 */
function crm_add_chat_details_meta_box( $post_type, $post ) {
    // Solo añadir el meta box para nuestro CPT 'crm_chat'
    if ( 'crm_chat' === $post_type ) {
        add_meta_box(
            'crm_chat_details_metabox', // ID único del meta box
            __( 'Detalles del Chat', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), // Título visible del meta box
            'crm_render_chat_details_metabox', // Función callback que renderiza el contenido
            'crm_chat', // El CPT donde se mostrará
            'side', // Contexto: 'side', 'normal', 'advanced'
            'high' // Prioridad: 'high', 'low', 'default'
        );
    }
}
add_action( 'add_meta_boxes', 'crm_add_chat_details_meta_box', 10, 2 );

/**
 * Renderiza el contenido HTML del Meta Box "Detalles del Chat".
 *
 * @param WP_Post $post El objeto del post actual.
 */
function crm_render_chat_details_metabox( $post ) {
    // Obtener los metadatos guardados para este post de chat
    $is_outgoing   = get_post_meta( $post->ID, '_crm_is_outgoing', true );
    $message_id_wa = get_post_meta( $post->ID, '_crm_message_id_wa', true );
    $sender_jid    = get_post_meta( $post->ID, '_crm_sender_jid', true );
    $recipient_jid = get_post_meta( $post->ID, '_crm_recipient_jid', true ); // Aún no lo guardamos, pero lo preparamos
    $timestamp_wa  = get_post_meta( $post->ID, '_crm_timestamp_wa', true );
    $message_type  = get_post_meta( $post->ID, '_crm_message_type', true );
    $instance_name = get_post_meta( $post->ID, '_crm_instance_name', true );
    $is_group      = get_post_meta( $post->ID, '_crm_is_group_message', true );
    $participant_jid = get_post_meta( $post->ID, '_crm_participant_jid', true ); // Para grupos

    // Mostrar la información
    ?>
    <div class="crm-chat-details">
        <p>
            <strong><?php esc_html_e( 'Dirección:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></strong><br>
            <?php
            if ( $is_outgoing === true ) { // Comparación estricta
                echo '<span style="color: blue;">' . esc_html__( 'Mensaje Enviado', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) . '</span>';
            } elseif ( $is_outgoing === false ) { // Comparación estricta
                 echo '<span style="color: green;">' . esc_html__( 'Mensaje Recibido', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) . '</span>';
            } else {
                esc_html_e( 'Desconocida', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); // Si el meta no está o es inválido
            }
            ?>
        </p>

        <?php if ( ! empty( $sender_jid ) ) : ?>
            <p>
                <strong><?php echo $is_group ? esc_html__( 'Participante:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) : esc_html__( 'Remitente:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></strong><br>
                <small><?php echo esc_html( $sender_jid ); ?></small>
            </p>
        <?php endif; ?>

        <?php if ( ! empty( $recipient_jid ) ) : // Mostrar solo si tenemos destinatario ?>
             <p>
                <strong><?php echo $is_group ? esc_html__( 'Grupo:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) : esc_html__( 'Destinatario:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></strong><br>
                <small><?php echo esc_html( $recipient_jid ); ?></small>
            </p>
        <?php endif; ?>

        <?php if ( ! empty( $timestamp_wa ) ) : ?>
            <p>
                <strong><?php esc_html_e( 'Fecha (WhatsApp):', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></strong><br>
                <?php
                // Convertir timestamp a formato legible según la configuración de WP
                echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp_wa ) );
                ?>
            </p>
        <?php endif; ?>

         <?php if ( ! empty( $message_type ) ) : ?>
            <p>
                <strong><?php esc_html_e( 'Tipo:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></strong><br>
                <?php echo esc_html( $message_type ); ?>
            </p>
        <?php endif; ?>

         <?php if ( ! empty( $instance_name ) ) : ?>
            <p>
                <strong><?php esc_html_e( 'Instancia:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></strong><br>
                <?php echo esc_html( $instance_name ); ?>
            </p>
        <?php endif; ?>

        <?php if ( ! empty( $message_id_wa ) ) : ?>
            <p>
                <strong><?php esc_html_e( 'ID Mensaje WA:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></strong><br>
                <small><?php echo esc_html( $message_id_wa ); ?></small>
            </p>
        <?php endif; ?>

    </div>
    <?php
}
