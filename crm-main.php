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
        <!-- <h1><span class="dashicons dashicons-whatsapp" style="vertical-align: middle;"></span> <?php esc_html_e( 'CRM Evolution Sender', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></h1> -->

        <?php // Mostrar notificaciones de admin (ej: después de guardar ajustes)
            settings_errors();
        ?>

        <h2 class="nav-tab-wrapper">
            <a href="?page=crm-evolution-sender-main&tab=instancias" class="nav-tab <?php echo $active_tab == 'instancias' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-cloud-saved" style="vertical-align: text-bottom;"></span> <?php esc_html_e( 'Instancias API', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>
            </a>
        
            <a href="?page=crm-evolution-sender-main&tab=marketing" class="nav-tab <?php echo $active_tab == 'marketing' ? 'nav-tab-active' : ''; ?>">
                 <span class="dashicons dashicons-megaphone" style="vertical-align: text-bottom;"></span> <?php esc_html_e( 'Envíos Masivos', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>
            </a>
          
        </h2>

        <div id="crm-tab-content" class="crm-tab-content">
            <?php
            // Cargar el contenido de la pestaña activa
            switch ( $active_tab ) {

                case 'marketing':
                    crm_evolution_sender_render_marketing_tab();
                    break;
                
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
        <a href="#TB_inline?width=300&height=350&inlineId=add-instance-modal-content" id="btn-add-instance" class="button button-primary thickbox" title="<?php esc_attr_e('Añadir Nueva Instancia Evolution API', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?>">
            <?php esc_html_e( 'Añadir Nueva Instancia', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>
        </a>
        
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
            <!-- === INICIO: Contenido del Modal para Añadir Instancia (para Thickbox) === -->
            <div id="add-instance-modal-content" style="display:none;">
                <form id="add-instance-form" class="crm-modal-form">
                    <?php wp_nonce_field( 'crm_create_instance_action', 'crm_create_instance_nonce' ); ?>
        
                    <p>
                        <label for="instance_name"><?php esc_html_e( 'Nombre de la Instancia (obligatorio)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label>
                        <input type="text" name="instance_name" id="instance_name" class="regular-text" required title="<?php esc_attr_e('Solo letras, números, guiones bajos y guiones medios.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?>">
                        <p class="description"><?php esc_html_e( 'Identificador único para la instancia. Sin espacios ni caracteres especiales.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
                    </p>
                    <p>
                        <label for="webhook_url"><?php esc_html_e( 'Webhook URL (obligatorio)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label>
                        <input type="url" name="webhook_url" id="webhook_url" class="regular-text" value="<?php echo esc_url( rest_url( 'crm-evolution-api/v1/webhook' ) ); ?>" required>
                        <p class="description"><?php esc_html_e( 'URL generada automáticamente para recibir eventos en este plugin. No editable.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>
                    </p>
               
        
                    <?php submit_button( __( 'Crear Instancia', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), 'primary', 'submit-add-instance' ); ?>
                </form>
            </div>
            <!-- === FIN: Contenido del Modal para Añadir Instancia === -->
        
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
                    <label for="campaign_interval"><?php esc_html_e( 'Intervalo (minutos)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label>
                    <input type="number" name="campaign_interval" id="campaign_interval" min="0" step="1" class="small-text" placeholder="5">
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

 

              
                <p class="crm-form-field-full-width">
                    <label for="campaign_media_url"><?php esc_html_e( 'Archivo Multimedia (Opcional)', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></label>
                    <span class="crm-media-input-wrapper">
                        <input type="text" name="campaign_media_url" id="campaign_media_url" class="regular-text" placeholder="<?php esc_attr_e('URL del archivo seleccionado', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?>" readonly>
                        <button type="button" id="select-media-button" class="button button-secondary"><span class="dashicons dashicons-admin-media"></span> <?php esc_html_e('Seleccionar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></button>
                        <button type="button" id="clear-media-button" class="button button-secondary" style="display: none;"><span class="dashicons dashicons-no-alt"></span> <?php esc_html_e('Quitar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></button>
                    </span>
                    <span id="media-filename" class="description" style="display: block; margin-top: 5px;"></span>
                </p>
  
                <p class="crm-form-field-full-width">
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
