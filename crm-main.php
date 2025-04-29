<?php
/**
 * Archivo principal para la interfaz de administración del plugin CRM Evolution Sender.
 * Muestra la página principal con pestañas para Instancias, Usuarios y Marketing.
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Genera el HTML para la página principal del plugin CRM Evolution Sender.
 * Esta función es llamada por el hook 'admin_menu' en crm-evolution-sender.php.
 */
function crm_evolution_sender_main_page_html() {    
    // Verificar permisos del usuario
    if ( ! current_user_can( 'manage_options' ) ) {
        // crm_log('Intento de acceso no autorizado a la página principal.', 'ERROR');
        wp_die( __( 'No tienes permisos suficientes para acceder a esta página.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) );
    }

    // Obtener la pestaña activa (si existe), por defecto 'instancias'
    $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'instancias';

    ?>
    <div class="wrap crm-evolution-sender-wrap">
        <h1><span class="dashicons dashicons-whatsapp" style="vertical-align: middle;"></span> <?php esc_html_e( 'CRM Evolution Sender', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></h1>

        <?php // Mostrar notificaciones de admin (ej: después de guardar ajustes)
            settings_errors();
        ?>

        <h2 class="nav-tab-wrapper">
            <a href="?page=crm-evolution-sender-main&tab=instancias" class="nav-tab <?php echo $active_tab == 'instancias' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-cloud-saved" style="vertical-align: text-bottom;"></span> <?php esc_html_e( 'Instancias API', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>
            </a>
            <a href="?page=crm-evolution-sender-main&tab=usuarios" class="nav-tab <?php echo $active_tab == 'usuarios' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-admin-users" style="vertical-align: text-bottom;"></span> <?php esc_html_e( 'Usuarios WP', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>
            </a>
            <a href="?page=crm-evolution-sender-main&tab=marketing" class="nav-tab <?php echo $active_tab == 'marketing' ? 'nav-tab-active' : ''; ?>">
                 <span class="dashicons dashicons-megaphone" style="vertical-align: text-bottom;"></span> <?php esc_html_e( 'Envíos Masivos', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>
            </a>
             <a href="?page=crm-evolution-sender-settings" class="nav-tab <?php echo (isset($_GET['page']) && $_GET['page'] == 'crm-evolution-sender-settings') ? 'nav-tab-active' : ''; ?>">
                 <span class="dashicons dashicons-admin-settings" style="vertical-align: text-bottom;"></span> <?php esc_html_e( 'Ajustes API', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>
            </a>
        </h2>

        <div id="crm-tab-content" class="crm-tab-content">
            <?php
            // Cargar el contenido de la pestaña activa
            switch ( $active_tab ) {
                case 'usuarios':
                    crm_evolution_sender_render_usuarios_tab();
                    break;
                case 'marketing':
                    crm_evolution_sender_render_marketing_tab();
                    break;
                case 'instancias':
                default: // Por defecto mostrar instancias
                    crm_evolution_sender_render_instancias_tab();
                    break;
            }
            ?>
        </div>

    </div><!-- .wrap -->
    <?php
     
}

/**
 * Renderiza el contenido de la pestaña "Instancias API".
 * (Aquí irá el CRUD de instancias)
 */
function crm_evolution_sender_render_instancias_tab() {
    ?>
    <div id="tab-instancias">
        <button type="button" id="btn-open-add-instance-modal" class="button button-primary">Añadir Nueva Instancia</button>
        <table id="instances-table" class="display wp-list-table widefat fixed striped table-view-list" style="width:100%">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Avatar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Nombre Instancia', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Estado', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'WhatsApp', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Acciones', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="5" class="dataTables_empty"><?php esc_html_e( 'Cargando instancias...', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
}



/**
 * Renderiza el contenido de la pestaña "Usuarios WP".
 */
function crm_evolution_sender_render_usuarios_tab() {

    // Obtener las etiquetas AHORA para incluirlas en el HTML estático del modal
    $lifecycle_tags = crm_get_lifecycle_tags(); // Usa la función de crm-setting.php

    ?>
    <div id="tab-usuarios">
        <a href="#TB_inline?width=600&height=550&inlineId=add-user-modal-content" id="btn-add-user" class="button button-primary thickbox" title="<?php esc_attr_e('Añadir Nuevo Usuario WP', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?>">
            <?php esc_html_e( 'Añadir Usuario WP', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>
        </a>

        <!-- Tabla para mostrar los usuarios -->
        <table id="users-table" class="display wp-list-table widefat fixed striped table-view-list" style="width:100%">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'ID', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Usuario', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Etiqueta', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Nombre', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Email', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Rol', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Teléfono', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                    <!-- <th><?php // esc_html_e( 'Acciones', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th> -->
                </tr>
            </thead>
            <tbody>
                 <tr><td colspan="7"><?php esc_html_e( 'Cargando usuarios...', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></td></tr> <!-- Ajustar colspan -->
            </tbody>
        </table>

        <!-- === INICIO: Contenido del Modal para Añadir Usuario (para Thickbox) === -->
        <div id="add-user-modal-content" style="display:none;">
            <form id="add-user-form" class="crm-modal-form">
                <?php wp_nonce_field( 'crm_add_wp_user_action', 'crm_add_wp_user_nonce' ); ?>
                 <p>
                    <label for="user_first_name"><?php esc_html_e( 'Nombres', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label>
                    <input type="text" name="user_first_name" id="user_first_name" class="regular-text">
                </p>
                <p>
                    <label for="user_last_name"><?php esc_html_e( 'Apellidos', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label>
                    <input type="text" name="user_last_name" id="user_last_name" class="regular-text">
                </p>
                <p>
                    <label for="user_email"><?php esc_html_e( 'Correo electrónico (obligatorio)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label>
                    <input type="email" name="user_email" id="user_email" class="regular-text" required autocomplete="email">
                </p>
                <p>
                    <label for="user_phone_input"><?php esc_html_e( 'Teléfono (obligatorio)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label>
                    <input type="tel" name="user_phone_input" id="user_phone_input" class="regular-text" required>
                    <!-- <p class="description"><?php esc_html_e('Selecciona el país y luego ingresa el número. El formato internacional se aplicará automáticamente.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></p> -->
                </p>
                <p>
                    <label for="user_role"><?php esc_html_e( 'Rol', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label>
                    <select name="user_role" id="user_role">
                        <?php wp_dropdown_roles( get_option('default_role') ); ?>
                    </select>
                </p>
                <p>
                    <label for="user_etiqueta"><?php esc_html_e( 'Etiqueta (Ciclo de Vida)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label>
                    <!-- Generar las opciones del select directamente aquí -->
                    <select name="user_etiqueta" id="user_etiqueta">
                        <option value=""><?php esc_html_e('-- Seleccionar Etapa --', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></option>
                        <?php if (!empty($lifecycle_tags)): ?>
                            <?php foreach ($lifecycle_tags as $key => $name): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                             <option value="" disabled><?php esc_html_e('No hay etiquetas definidas', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></option>
                        <?php endif; ?>
                    </select>
                </p>

                <?php submit_button( __( 'Añadir Usuario', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), 'primary', 'submit-add-user' ); ?>
            </form>
        </div>
        <!-- === FIN: Contenido del Modal para Añadir Usuario === -->

    </div>
    <?php
}

/**
 * Renderiza el contenido de la pestaña "Envíos Masivos".
 * (Aquí irá el CRUD de campañas de marketing)
 */
function crm_evolution_sender_render_marketing_tab() {
    ?>
    <div id="tab-marketing">
        <a href="#TB_inline?width=700&height=600&inlineId=marketing-modal-content" id="btn-marketing" class="button button-primary thickbox" title="<?php esc_attr_e('Crear Nueva Campaña', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?>">
            <?php esc_html_e( 'Crear Campaña', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>
        </a>

        <!-- Tabla para mostrar las campañas -->
        <table id="campaigns-table" class="display wp-list-table widefat fixed striped table-view-list" style="width:100%">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Nombre', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Instancia', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Mensaje', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Multimedia', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Etiqueta', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Intervalo', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Estado', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Acciones', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></th>
                </tr>
            </thead>
             <tbody>
                 <tr><td colspan="8"><?php esc_html_e( 'Cargando campañas...', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></td></tr>
            </tbody>
        </table>

        <!-- === INICIO: Contenido del Modal para Crear/Editar Campaña (para Thickbox) === -->
        <div id="marketing-modal-content" style="display:none;">
           
            <form id="campaign-form" class="crm-modal-form">
                <?php // Nonce se añadirá dinámicamente con JS si es necesario, o usar uno general ?>
                <input type="hidden" name="action" id="campaign_action" value="crm_create_campaign"> <?php // Acción por defecto: crear ?>
                <input type="hidden" name="campaign_id" id="campaign_id" value=""> <?php // ID de la campaña para editar ?>

                <p>
                    <label for="campaign_name"><?php esc_html_e( 'Nombre Campaña (obligatorio)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label>
                    <input type="text" name="campaign_name" id="campaign_name" class="regular-text" required>
                </p>

                <p>
                    <label for="campaign_instance"><?php esc_html_e( 'Instancia API (obligatorio)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label>
                    <select name="campaign_instance" id="campaign_instance" required>
                        <option value=""><?php esc_html_e('-- Cargando Instancias --', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></option>
                        <?php /* Las opciones se cargarán vía AJAX con JavaScript */ ?>
                    </select>
                </p>

                <p>
                    <label for="campaign_target_tag"><?php esc_html_e( 'Etiqueta Destinatarios (obligatorio)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label>
                    <select name="campaign_target_tag" id="campaign_target_tag" required>
                         <option value=""><?php esc_html_e('-- Cargando Etiquetas --', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></option>
                         <?php /* Las opciones se cargarán vía AJAX con JavaScript */ ?>
                    </select>
                </p>

                <p>
                    <label for="campaign_interval"><?php esc_html_e( 'Intervalo (minutos)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label>
                    <input type="number" name="campaign_interval" id="campaign_interval" min="0" step="1" class="small-text" placeholder="5">
                </p>

              
                <p class="crm-media-field">
                    <label for="campaign_media_url"><?php esc_html_e( 'Archivo Multimedia (Opcional)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label>
                    <span class="crm-media-input-wrapper">
                        <input type="text" name="campaign_media_url" id="campaign_media_url" class="regular-text" placeholder="<?php esc_attr_e('URL del archivo seleccionado', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?>" readonly>
                        <button type="button" id="select-media-button" class="button button-secondary"><span class="dashicons dashicons-admin-media"></span> <?php esc_html_e('Seleccionar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></button>
                        <button type="button" id="clear-media-button" class="button button-secondary" style="display: none;"><span class="dashicons dashicons-no-alt"></span> <?php esc_html_e('Quitar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></button>
                    </span>
                    <span id="media-filename" class="description" style="display: block; margin-top: 5px;"></span>
                </p>
  
                <p class="crm-message-field">
                    <label for="campaign_message"><?php esc_html_e( 'Mensaje (obligatorio)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label>
                    <textarea name="campaign_message" id="campaign_message" rows="6" class="large-text" required></textarea>
                </p>
    

                <?php submit_button( __( 'Guardar Campaña', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), 'primary', 'submit-campaign' ); ?>
            </form>
        </div>
        <!-- === FIN: Contenido del Modal para Crear/Editar Campaña === -->
    </div>
    <?php
}
