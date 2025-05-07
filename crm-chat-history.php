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
                <div id="chat-messages-area" class="chat-messages-area chat-placeholder-active"> <?php // Añadir clase inicial ?>
                    <!-- === INICIO: Placeholder WhatsApp Style === -->
                    <div class="chat-placeholder-container">
                        <img src="<?php echo esc_url( CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/images/whatsapp-placeholder.png' ); ?>" alt="<?php esc_attr_e('Mantén tu teléfono conectado', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?>" class="chat-placeholder-image">
                        <h2 class="chat-placeholder-title"><?php esc_html_e('Mantén tu teléfono conectado', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></h2>
                        <p class="chat-placeholder-text"><?php esc_html_e('WhatsApp se conecta a tu teléfono para sincronizar los mensajes. Para reducir el uso de datos, conecta tu teléfono a una red Wi-Fi.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></p>
                    </div>
                    <!-- <p class="no-chat-selected"><?php esc_html_e( 'Selecciona un chat para ver los mensajes.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p> -->
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
                    <button id="send-chat-message" class="button button-primary btn-send" title="Enviar Mensaje"><span class="dashicons dashicons-arrow-right-alt"></span></button>
                </div>
            </div>
            <!-- === INICIO: Nueva Columna para Detalles del Contacto === -->
            <div id="contact-details-column" class="contact-details-column" style="display: none;">
                <div class="contact-details-header">
                    <h3><?php esc_html_e( 'Detalles del Contacto', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></h3>
                    <!-- === INICIO: Botón Cerrar Sidebar === -->
                    <button id="close-contact-details" class="button-icon" title="<?php esc_attr_e( 'Cerrar detalles', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
                    <!-- === FIN: Botón Cerrar Sidebar === -->
                </div>
                <div id="contact-details-content" class="contact-details-content">
                    <p><?php esc_html_e( 'Cargando detalles...', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
                    <!-- Aquí se cargarán los campos del contacto vía AJAX -->
                </div>

                <!-- === INICIO: Vista Ampliada del Avatar (MOVIDO FUERA DE contact-details-content) === -->
                <div id="contact-avatar-expanded-view" class="contact-avatar-expanded-view" style="display: none;">
                    <button id="close-avatar-expanded-view" class="button-icon close-expanded-avatar-button" title="<?php esc_attr_e( 'Cerrar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
                    <img id="expanded-avatar-image" src="" alt="<?php esc_attr_e( 'Avatar Ampliado', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>" />
                    <div class="expanded-avatar-actions">
                        <button id="trigger-update-avatar-button" class="button button-primary"><?php esc_html_e( 'Actualizar foto', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></button>
                        <!-- Podríamos añadir un input file oculto aquí si no usamos el media uploader de WP directamente -->
                    </div>
                </div>
                <!-- === FIN: Vista Ampliada del Avatar === -->
            </div>
            <!-- === FIN: Nueva Columna para Detalles del Contacto === -->
        </div>

    </div>
    <?php
}