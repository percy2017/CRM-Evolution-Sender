<?php
/**
 * Archivo para la página del historial de chats estilo WhatsApp Web.
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Renderiza el contenido de la nueva página "Historial de Chats".
 * Esta función es llamada por el hook 'admin_menu' para el nuevo menú principal.
 */
function crm_evolution_sender_chat_history_page_html() {
    // Verificar permisos del usuario
    if ( ! current_user_can( 'edit_posts' ) ) { // Usar la misma capacidad que el menú
        wp_die( __( 'No tienes permisos suficientes para acceder a esta página.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) );
    }
    ?>
    <div class="wrap crm-evolution-sender-wrap crm-chat-history-page">
        <h1><span class="dashicons dashicons-format-chat" style="vertical-align: middle;"></span> <?php esc_html_e( 'Historial de Chats CRM', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></h1>

        <div id="crm-chat-container" class="crm-chat-container">
            <div id="chat-list-column" class="chat-list-column">
                <div class="chat-list-header">
                    <h2><?php esc_html_e( 'Conversaciones', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></h2>
                    <!-- Aquí podría ir un campo de búsqueda -->
                </div>
                <div id="chat-list-items" class="chat-list-items">
                    <p><?php esc_html_e( 'Cargando conversaciones...', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
                </div>
            </div>
            <div id="chat-messages-column" class="chat-messages-column">
                <p class="no-chat-selected"><?php esc_html_e( 'Selecciona un chat para ver los mensajes.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
                <!-- Aquí se cargarán los mensajes de la conversación seleccionada -->
            </div>
        </div>

    </div>
    <?php
}