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
    <div class="wrap">
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




