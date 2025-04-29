<?php
/**
 * Archivo para la página de ajustes del plugin CRM Evolution Sender.
 * Permite configurar la URL/Token de la API y gestionar las Etiquetas de Ciclo de Vida.
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// =========================================================================
// == REGISTRO DE AJUSTES (API Settings API) ==
// =========================================================================

/**
 * Registra los ajustes del plugin usando la API de Ajustes de WordPress.
 * Solo registra los ajustes de la API (URL y Token). Las etiquetas se manejan por separado.
 */
function crm_evolution_sender_register_settings() {
    // Registrar el grupo de ajustes para API URL y Token
    register_setting(
        'crm_evolution_sender_options_group', // Nombre del grupo de opciones
        'crm_evolution_api_url',              // Nombre de la opción para la URL
        array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => 'https://api.example.com', // Cambia a tu URL por defecto si quieres
        )
    );

    register_setting(
        'crm_evolution_sender_options_group', // Mismo grupo de opciones
        'crm_evolution_api_token',            // Nombre de la opción para el Token
        array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        )
    );

    // Añadir la sección de ajustes para la API
    add_settings_section(
        'crm_evolution_sender_api_section', // ID de la sección API
        __( 'Configuración de la API Evolution', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'crm_evolution_sender_api_section_callback', // Descripción de la sección API
        'crm-evolution-sender-settings' // Slug de la página donde se mostrará
    );

    // Añadir campo para la URL de la API
    add_settings_field(
        'crm_evolution_api_url_field',
        __( 'URL de la API', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'crm_evolution_sender_api_url_field_render', // Renderiza el input URL
        'crm-evolution-sender-settings',
        'crm_evolution_sender_api_section'
    );

    // Añadir campo para el Token de la API
    add_settings_field(
        'crm_evolution_api_token_field',
        __( 'Token Global (API Key)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'crm_evolution_sender_api_token_field_render', // Renderiza el input Token
        'crm-evolution-sender-settings',
        'crm_evolution_sender_api_section'
    );

    // Añadir la sección para Gestión de Etiquetas (se renderizará con una función separada)
    add_settings_section(
        'crm_evolution_sender_tags_section', // ID de la sección de Etiquetas
        __( 'Gestionar Etiquetas (Ciclo de Vida)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
        'crm_evolution_sender_tags_section_render', // Función que renderizará el CRUD de etiquetas
        'crm-evolution-sender-settings' // Slug de la página donde se mostrará
    );

}
add_action( 'admin_init', 'crm_evolution_sender_register_settings' );

// =========================================================================
// == RENDERIZADO DE CAMPOS Y SECCIONES (API Settings API) ==
// =========================================================================

/**
 * Callback para la descripción de la sección de ajustes API.
 */
function crm_evolution_sender_api_section_callback() {
    // echo '<p>' . esc_html__( 'Introduce la URL base y el Token Global (si aplica) de tu instancia de Evolution API.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) . '</p>';
    // echo '<p><strong>' . esc_html__( 'Nota:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) . '</strong> ' . esc_html__( 'El Token Global aquí configurado se usará por defecto para operaciones generales. Las instancias individuales pueden usar su propia API Key.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) . '</p>';
    echo '<hr>';
}

/**
 * Renderiza el campo HTML para la URL de la API.
 */
function crm_evolution_sender_api_url_field_render() {
    $option = get_option( 'crm_evolution_api_url', '' );
    ?>
    <input type='url' name='crm_evolution_api_url' value='<?php echo esc_url( $option ); ?>' class='regular-text' placeholder="https://api.example.com">
    <p class="description"><?php esc_html_e( 'La URL base de tu servidor Evolution API.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
    <?php
}

/**
 * Renderiza el campo HTML para el Token de la API.
 */
function crm_evolution_sender_api_token_field_render() {
    $option = get_option( 'crm_evolution_api_token' );
    $type = ! empty( $option ) ? 'password' : 'text';
    ?>
    <input type='<?php echo $type; ?>' name='crm_evolution_api_token' value='<?php echo esc_attr( $option ); ?>' class='regular-text' placeholder="<?php esc_attr_e('Introduce tu API Key global si es necesaria', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?>">
     <?php if ( ! empty( $option ) ) : ?>
        <p class="description"><?php esc_html_e( 'Token guardado. Introduce uno nuevo para cambiarlo.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
        <button type="button" id="show-api-token" class="button button-secondary button-small"><?php esc_html_e('Mostrar/Ocultar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></button>
        <script>
            // Simple script para mostrar/ocultar token
            jQuery(document).ready(function($){
                $('#show-api-token').on('click', function(){
                    var tokenInput = $('input[name="crm_evolution_api_token"]');
                    if (tokenInput.attr('type') === 'password') {
                        tokenInput.attr('type', 'text');
                        $(this).text('<?php echo esc_js(__('Ocultar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN)); ?>');
                    } else {
                        tokenInput.attr('type', 'password');
                         $(this).text('<?php echo esc_js(__('Mostrar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN)); ?>');
                    }
                });
                 if ($('input[name="crm_evolution_api_token"]').val() !== '') {
                     $('input[name="crm_evolution_api_token"]').attr('type', 'password');
                 }
            });
        </script>
    <?php else : ?>
         <p class="description"><?php esc_html_e( 'Este token se usará para autenticar las peticiones globales a la API (ej: listar instancias).', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
    <?php endif; ?>
    <?php
}

// =========================================================================
// == GESTIÓN DE ETIQUETAS (CRUD) ==
// =========================================================================

/**
 * Obtiene las etiquetas de ciclo de vida guardadas.
 *
 * @return array Array asociativo de etiquetas [key => name].
 */
function crm_get_lifecycle_tags() {
    return get_option( 'crm_evolution_lifecycle_tags', array() );
}

/**
 * Maneja las acciones CRUD para las etiquetas (añadir, eliminar, actualizar).
 * Se ejecuta en admin_init para procesar los formularios ANTES de renderizar la página.
 */
function crm_handle_tag_actions() {
    // Solo procesar si estamos en nuestra página de ajustes y hay una acción POST relevante
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'crm-evolution-sender-settings' || empty( $_POST ) || !isset($_POST['action']) ) {
        return;
    }

    // Verificar permisos
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Permiso denegado.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) );
    }

    $tags = crm_get_lifecycle_tags();
    // URL a la que redirigir después de la acción (la misma página de ajustes)
    $redirect_url = admin_url( 'admin.php?page=crm-evolution-sender-settings' );
    $action = sanitize_key($_POST['action']);

    // --- Acción: Añadir Etiqueta ---
    if ( $action === 'crm_add_tag' ) {
        // Verificar nonce
        if ( ! isset( $_POST['crm_add_tag_nonce'] ) || ! wp_verify_nonce( $_POST['crm_add_tag_nonce'], 'crm_add_tag_action' ) ) {
            wp_die( __( 'Error de seguridad (nonce inválido).', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) );
        }

        $new_tag_name = isset( $_POST['new_tag_name'] ) ? sanitize_text_field( wp_unslash( $_POST['new_tag_name'] ) ) : '';
        // Generar clave simple: minúsculas, sin espacios (reemplazados por _), sanitizada
        $new_tag_key = sanitize_key( str_replace(' ', '_', strtolower($new_tag_name)) );

        if ( ! empty( $new_tag_name ) && ! empty( $new_tag_key ) ) {
            if ( ! array_key_exists( $new_tag_key, $tags ) ) {
                $tags[ $new_tag_key ] = $new_tag_name;
                update_option( 'crm_evolution_lifecycle_tags', $tags );
                // Añadir mensaje de éxito (se mostrará después de redirigir)
                add_settings_error( 'crm_tags_manager', 'tag_added', __( 'Etiqueta añadida correctamente.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), 'success' );
            } else {
                // Añadir mensaje de error (clave ya existe)
                add_settings_error( 'crm_tags_manager', 'tag_exists', __( 'Error: La clave para esta etiqueta ya existe. Elige un nombre ligeramente diferente.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), 'error' );
            }
        } else {
             // Añadir mensaje de error (nombre vacío)
             add_settings_error( 'crm_tags_manager', 'tag_empty', __( 'Error: El nombre de la etiqueta no puede estar vacío.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), 'error' );
        }

        // Redirigir para mostrar mensajes y limpiar POST
        wp_safe_redirect( add_query_arg( 'settings-updated', 'tags', $redirect_url ) ); // Añadir param para posible feedback
        exit;
    }

    // --- Acción: Eliminar Etiqueta ---
    if ( $action === 'crm_delete_tag' ) {
        // Verificar nonce
        if ( ! isset( $_POST['crm_delete_tag_nonce'] ) || ! wp_verify_nonce( $_POST['crm_delete_tag_nonce'], 'crm_delete_tag_action' ) ) {
            wp_die( __( 'Error de seguridad (nonce inválido).', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) );
        }

        $tag_key_to_delete = isset( $_POST['tag_key'] ) ? sanitize_key( $_POST['tag_key'] ) : '';

        if ( ! empty( $tag_key_to_delete ) && array_key_exists( $tag_key_to_delete, $tags ) ) {
            unset( $tags[ $tag_key_to_delete ] );
            update_option( 'crm_evolution_lifecycle_tags', $tags );
            add_settings_error( 'crm_tags_manager', 'tag_deleted', __( 'Etiqueta eliminada correctamente.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), 'success' );
        } else {
            add_settings_error( 'crm_tags_manager', 'tag_not_found', __( 'Error: No se pudo eliminar la etiqueta (no encontrada).', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), 'error' );
        }

        // Redirigir
        wp_safe_redirect( add_query_arg( 'settings-updated', 'tags', $redirect_url ) );
        exit;
    }

     // --- Acción: Editar Etiqueta (Solo Nombre) ---
    if ( $action === 'crm_update_tag' ) {
        // Verificar nonce
        if ( ! isset( $_POST['crm_update_tag_nonce'] ) || ! wp_verify_nonce( $_POST['crm_update_tag_nonce'], 'crm_update_tag_action' ) ) {
            wp_die( __( 'Error de seguridad (nonce inválido).', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) );
        }

        $tag_key_to_update = isset( $_POST['tag_key'] ) ? sanitize_key( $_POST['tag_key'] ) : '';
        $updated_tag_name = isset( $_POST['updated_tag_name'] ) ? sanitize_text_field( wp_unslash( $_POST['updated_tag_name'] ) ) : '';

        if ( ! empty( $tag_key_to_update ) && array_key_exists( $tag_key_to_update, $tags ) && ! empty( $updated_tag_name ) ) {
            $tags[ $tag_key_to_update ] = $updated_tag_name; // Actualizar solo el nombre, no la clave
            update_option( 'crm_evolution_lifecycle_tags', $tags );
            add_settings_error( 'crm_tags_manager', 'tag_updated', __( 'Etiqueta actualizada correctamente.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), 'success' );
        } else {
            add_settings_error( 'crm_tags_manager', 'tag_update_error', __( 'Error: No se pudo actualizar la etiqueta (datos inválidos o etiqueta no encontrada).', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), 'error' );
        }

        // Redirigir
        wp_safe_redirect( add_query_arg( 'settings-updated', 'tags', $redirect_url ) );
        exit;
    }
}
// Enganchar el manejador de acciones de etiquetas a admin_init
add_action( 'admin_init', 'crm_handle_tag_actions' );


/**
 * Renderiza el contenido CRUD para la sección de gestión de etiquetas.
 * Esta función es llamada como callback por add_settings_section().
 */
function crm_evolution_sender_tags_section_render() {
    // Verificar permisos (aunque ya se hace al cargar la página principal)
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $tags = crm_get_lifecycle_tags();
    // Usar 'edit_tag' como acción en la URL para evitar conflicto con 'edit' de WP
    $editing_tag_key = isset($_GET['action']) && $_GET['action'] === 'edit_tag' && isset($_GET['tag_key']) ? sanitize_key($_GET['tag_key']) : null;
    $edit_form_url = admin_url( 'admin.php?page=crm-evolution-sender-settings' ); // URL base para los formularios

    // Mostrar mensajes específicos de las etiquetas (registrados con 'crm_tags_manager')
    settings_errors( 'crm_tags_manager' );

    ?>
    <hr> <!-- Separador visual -->
    <div id="col-container" class="wp-clearfix" style="margin-top: 20px;">

        <div id="col-left" style="width: 48%; float: left; margin-right: 4%;">
            <div class="col-wrap">
                <h3><?php echo $editing_tag_key ? esc_html__( 'Editar Etiqueta', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) : esc_html__( 'Añadir Nueva Etiqueta', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></h3>
                <?php if ($editing_tag_key && !isset($tags[$editing_tag_key])): ?>
                    <div class="notice notice-error is-dismissible"><p><?php esc_html_e('Error: La etiqueta que intentas editar no existe.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></p></div>
                    <?php $editing_tag_key = null; // Resetear si no existe para mostrar el form de añadir ?>
                <?php endif; ?>

                <!-- Formulario para Añadir o Editar Etiquetas -->
                <form method="post" action="<?php echo esc_url( $edit_form_url ); ?>">
                    <?php if ($editing_tag_key): ?>
                        <!-- Campos para Editar -->
                        <input type="hidden" name="action" value="crm_update_tag">
                        <input type="hidden" name="tag_key" value="<?php echo esc_attr($editing_tag_key); ?>">
                        <?php wp_nonce_field( 'crm_update_tag_action', 'crm_update_tag_nonce' ); ?>
                        <div class="form-field term-name-wrap">
                            <label for="updated_tag_name"><?php esc_html_e( 'Nuevo Nombre', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label>
                            <input name="updated_tag_name" id="updated_tag_name" type="text" value="<?php echo esc_attr( $tags[$editing_tag_key] ); ?>" required class="regular-text">
                            <p><?php esc_html_e( 'Actualiza el nombre visible de la etiqueta. La clave interna no cambiará.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
                        </div>
                        <div class="form-field term-slug-wrap">
                             <label><?php esc_html_e( 'Clave (Key)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label>
                             <input type="text" value="<?php echo esc_attr($editing_tag_key); ?>" disabled class="regular-text">
                             <p><?php esc_html_e( 'La clave no se puede editar una vez creada.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
                        </div>
                         <p class="submit">
                            <?php submit_button( __( 'Actualizar Etiqueta', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), 'primary', 'submit_update_tag', false ); // Nombre único para el botón ?>
                            <a href="<?php echo esc_url( $edit_form_url ); ?>" class="button button-secondary"><?php esc_html_e('Cancelar Edición', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></a>
                        </p>
                    <?php else: ?>
                        <!-- Campos para Añadir -->
                        <input type="hidden" name="action" value="crm_add_tag">
                        <?php wp_nonce_field( 'crm_add_tag_action', 'crm_add_tag_nonce' ); ?>
                        <div class="form-field term-name-wrap">
                            <label for="new_tag_name"><?php esc_html_e( 'Nombre de la Etiqueta', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label>
                            <input name="new_tag_name" id="new_tag_name" type="text" value="" required class="regular-text">
                            <p><?php esc_html_e( 'El nombre es cómo aparecerá la etiqueta (ej: "Lead", "Cliente Potencial").', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
                        </div>
                         <div class="form-field term-slug-wrap">
                             <label><?php esc_html_e( 'Clave (Key)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label>
                             <span class="description"><?php esc_html_e( 'La clave se generará automáticamente a partir del nombre (ej: "cliente_potencial").', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></span>
                        </div>
                        <?php submit_button( __( 'Añadir Nueva Etiqueta', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), 'primary', 'submit_add_tag' ); // Nombre único para el botón ?>
                    <?php endif; ?>
                </form>
            </div>
        </div><!-- /col-left -->

        <div id="col-right" style="width: 48%; float: left;">
            <div class="col-wrap">
                <h3><?php esc_html_e( 'Etiquetas Actuales', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></h3>
                <!-- Tabla de Etiquetas Existentes -->
                <table class="wp-list-table widefat fixed striped tags">
                    <thead>
                    <tr>
                        <th scope="col" id="name" class="manage-column column-name column-primary"><?php esc_html_e( 'Nombre', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                        <th scope="col" id="slug" class="manage-column column-slug"><?php esc_html_e( 'Clave (Key)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                        <!-- <th scope="col" id="count" class="manage-column column-posts num"><?php // esc_html_e( 'Usuarios', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th> -->
                    </tr>
                    </thead>
                    <tbody id="the-list" data-wp-lists="list:tag">
                        <?php if ( ! empty( $tags ) ) : ?>
                            <?php foreach ( $tags as $key => $name ) : ?>
                                <tr id="tag-<?php echo esc_attr($key); ?>">
                                    <td class="name column-name column-primary has-row-actions">
                                        <strong><a href="<?php echo esc_url(add_query_arg(['action' => 'edit_tag', 'tag_key' => $key], $edit_form_url)); ?>"><?php echo esc_html( $name ); ?></a></strong>
                                        <br>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="<?php echo esc_url(add_query_arg(['action' => 'edit_tag', 'tag_key' => $key], $edit_form_url)); ?>"><?php esc_html_e('Editar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></a> |
                                            </span>
                                            <span class="delete">
                                                <a href="#" class="delete-tag" data-tag-key="<?php echo esc_attr($key); ?>" data-tag-name="<?php echo esc_attr($name); ?>"><?php esc_html_e('Eliminar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></a>
                                            </span>
                                        </div>
                                        <!-- Formulario oculto para eliminar -->
                                        <form id="delete-tag-form-<?php echo esc_attr($key); ?>" method="post" action="<?php echo esc_url( $edit_form_url ); ?>" style="display:none;">
                                            <input type="hidden" name="action" value="crm_delete_tag">
                                            <input type="hidden" name="tag_key" value="<?php echo esc_attr( $key ); ?>">
                                            <?php wp_nonce_field( 'crm_delete_tag_action', 'crm_delete_tag_nonce' ); ?>
                                        </form>
                                    </td>
                                    <td class="slug column-slug"><?php echo esc_html( $key ); ?></td>
                                    <!-- <td class="count column-posts num">0</td> --> <?php // Podrías contar usuarios con esta etiqueta ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr class="no-items">
                                <td class="colspanchange" colspan="2"><?php esc_html_e( 'No se encontraron etiquetas.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                    <tr>
                        <th scope="col" class="manage-column column-name column-primary"><?php esc_html_e( 'Nombre', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                        <th scope="col" class="manage-column column-slug"><?php esc_html_e( 'Clave (Key)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                        <!-- <th scope="col" class="manage-column column-posts num"><?php // esc_html_e( 'Usuarios', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th> -->
                    </tr>
                    </tfoot>
                </table>
                 <script type="text/javascript">
                    // Script para confirmar eliminación
                    jQuery(document).ready(function($) {
                        $('.delete-tag').on('click', function(e) {
                            e.preventDefault();
                            var tagKey = $(this).data('tag-key');
                            var tagName = $(this).data('tag-name');
                            if (confirm('<?php echo esc_js( __( '¿Estás seguro de que quieres eliminar la etiqueta "%s"? Esta acción no se puede deshacer.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) ); ?>'.replace('%s', tagName))) {
                                $('#delete-tag-form-' + tagKey).submit();
                            }
                        });
                    });
                </script>
            </div>
        </div><!-- /col-right -->

    </div><!-- /col-container -->
    <?php
}


// =========================================================================
// == RENDERIZADO DE LA PÁGINA DE AJUSTES PRINCIPAL ==
// =========================================================================

/**
 * Genera el HTML para la página de ajustes completa (API + Etiquetas).
 * Esta función es llamada por el hook 'admin_menu' (add_submenu_page).
 */

function crm_evolution_sender_settings_page_html() {
    // Verificar permisos del usuario
    if ( ! current_user_can( 'manage_options' ) ) {
        crm_log('Intento de acceso no autorizado a la página de ajustes.', 'ERROR');
        wp_die( __( 'No tienes permisos suficientes para acceder a esta página.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) );
    }
    
    crm_log('Cargando página de ajustes del plugin (API y Etiquetas).');

    ?>
    <div class="wrap crm-evolution-sender-wrap">
        <!-- <h1><span class="dashicons dashicons-admin-settings" style="vertical-align: middle;"></span> <?php echo esc_html( get_admin_page_title() ); ?></h1> -->

        <?php
            // Mostrar notificaciones de admin (ej: "Ajustes guardados.")
            // Mostrará mensajes de ambos grupos si se registran correctamente.
            settings_errors();
        ?>

        <!-- ============================================================== -->
        <!-- == Formulario SOLO para los ajustes de la API (URL y Token) == -->
        <!-- ============================================================== -->
        <form action="options.php" method="post">
            <?php
            // Output de campos nonce, action y option_page para el grupo 'crm_evolution_sender_options_group'
            settings_fields( 'crm_evolution_sender_options_group' );

            // --- Renderizar explícitamente la sección de la API ---
            echo '<h2>' . __('Configuración de la API Evolution', CRM_EVOLUTION_SENDER_TEXT_DOMAIN) . '</h2>';
            crm_evolution_sender_api_section_callback(); // Renderiza la descripción de la sección API
            ?>
            <table class="form-table">
                <?php
                // Renderizar los campos específicos de la sección API
                // Esto asegura que solo los campos de la API estén dentro de este form
                do_settings_fields('crm-evolution-sender-settings', 'crm_evolution_sender_api_section');
                ?>
            </table>
            <?php
            // Botón de guardar SOLO para los ajustes de la API
            submit_button( __( 'Guardar Ajustes API', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) );

            crm_log('Formulario principal de ajustes API renderizado.');
            ?>
        </form>
        <!-- == FIN Formulario API == -->

        <hr> <!-- Separador visual -->

        <!-- ============================================================== -->
        <!-- == Sección de Gestión de Etiquetas (Renderizada Aparte) == -->
        <!-- ============================================================== -->
        <?php
        // Renderizar la sección de Etiquetas FUERA del formulario de options.php
        // Llama directamente a la función que renderiza el título y el contenido CRUD de las etiquetas.
        // Esta función (crm_evolution_sender_tags_section_render) ya contiene su propio <form>
        // que es manejado por crm_handle_tag_actions vía admin_init.
        echo '<h2>' . __('Gestionar Etiquetas (Ciclo de Vida)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN) . '</h2>';
        crm_evolution_sender_tags_section_render();
        crm_log('Sección de gestión de etiquetas renderizada.');
        ?>
        <!-- == FIN Sección Etiquetas == -->


    </div><!-- .wrap -->
    <?php
     crm_log('Página de ajustes (API y Etiquetas) renderizada completamente.');
}

// --- FIN del archivo crm-setting.php ---
