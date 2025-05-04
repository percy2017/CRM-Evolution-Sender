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
        <!-- <h1><span class="dashicons dashicons-format-chat" style="vertical-align: middle;"></span> <?php esc_html_e( 'Historial de Chats CRM', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></h1> -->

        <div id="crm-chat-container" class="crm-chat-container">
            <div id="chat-list-column" class="chat-list-column">
                <div class="chat-list-header">
                    <!-- <h2><?php esc_html_e( 'Conversaciones', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></h2> -->
                    <!-- Inicio: Campo de búsqueda -->
                    <div class="chat-search-container">
                        <input type="search" id="chat-search-input" placeholder="<?php esc_attr_e( 'Buscar o empezar un chat nuevo', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>">
                    </div>
                    <!-- Fin: Campo de búsqueda -->
                </div>
                <div id="chat-list-items" class="chat-list-items">
                    <p><?php esc_html_e( 'Cargando conversaciones...', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
                </div>
            </div>
            <div id="chat-view-column" class="chat-view-column"> <!-- Cambiado ID para claridad -->
                <div id="chat-messages-area" class="chat-messages-area">
                    <p class="no-chat-selected"><?php esc_html_e( 'Selecciona un chat para ver los mensajes.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
                    <!-- Aquí se cargarán los mensajes -->
                </div>
                <div id="chat-input-area" class="chat-input-area" style="display: none;"> <!-- Oculto inicialmente -->
                    <!-- Contenedor del panel de emojis (inicialmente oculto) -->
                    <div id="emoji-picker-container" class="emoji-picker-container" style="display: none;">

                        <?php // --- Caras y Emociones --- ?>
                        <span class="emoji-option">😊</span>
                        <span class="emoji-option">😂</span>
                        <span class="emoji-option">😍</span>
                        <span class="emoji-option">🤔</span>
                        <span class="emoji-option">😢</span>
                        <span class="emoji-option">😮</span>
                        <span class="emoji-option">😎</span>
                        <span class="emoji-option">🥳</span>
                        <span class="emoji-option">😭</span>
                        <span class="emoji-option">😉</span>
                        <span class="emoji-option">😋</span>
                        <span class="emoji-option">😇</span>
                        <span class="emoji-option">😅</span>
                        <span class="emoji-option">😜</span>
                        <span class="emoji-option">🙄</span>
                        <span class="emoji-option">🤯</span>
                        <?php // --- Gestos y Personas --- ?>
                        <span class="emoji-option">👍</span>
                        <span class="emoji-option">👎</span>
                        <span class="emoji-option">🙏</span>
                        <span class="emoji-option">👋</span>
                        <span class="emoji-option">👌</span>
                        <span class="emoji-option">🙌</span>
                        <span class="emoji-option">🤷</span>
                        <span class="emoji-option">👀</span>
                        <?php // --- Símbolos y Objetos --- ?>
                        <span class="emoji-option">❤️</span>
                        <span class="emoji-option">🎉</span>
                        <span class="emoji-option">🔥</span>
                        <span class="emoji-option">✅</span>
                        <span class="emoji-option">💰</span>
                        <span class="emoji-option">✨</span>
                        <span class="emoji-option">🚀</span>
                        <span class="emoji-option">💡</span>
                        <span class="emoji-option">💯</span>
                        <span class="emoji-option">❓</span>
                        <span class="emoji-option">❗</span>

                    </div>
                    <button id="emoji-picker-button" class="button button-secondary btn-emoji" title="Emojis"><span class="dashicons dashicons-smiley"></span></button> <?php // <-- Botón Emoji ?>
                    <button class="button button-secondary btn-attach" title="Adjuntar (Próximamente)"><span class="dashicons dashicons-paperclip"></span></button>
                    <!-- Contenedor para la vista previa del adjunto -->
                    <div id="chat-attachment-preview" class="chat-attachment-preview" style="display: none;"></div>
                    <textarea id="chat-message-input" placeholder="<?php esc_attr_e( 'Escribe un mensaje aquí...', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>" rows="1"></textarea>
                    <button id="send-chat-message" class="button button-primary btn-send" title="Enviar Mensaje"><span class="dashicons dashicons-arrow-right-alt"></span></button> <?php // <-- ID CORREGIDO ?>
                </div>
            </div>
        </div>

    </div>
    <?php
}