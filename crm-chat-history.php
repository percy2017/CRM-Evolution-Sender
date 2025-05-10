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
                    <div class="chat-list-top-bar">
                        <h3 class="chat-list-title"><?php esc_html_e( 'Chats', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></h3>
                        <div class="chat-list-actions">
                            <button id="add-new-chat-button" class="button-icon" title="<?php esc_attr_e( 'Nuevo/Actualizar Usuario', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>">
                                <span class="dashicons dashicons-plus-alt2"></span>
                            </button>
                            <div class="dropdown-menu-container">
                                <button id="instance-filter-button" class="button-icon" title="<?php esc_attr_e( 'Instancias', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>">
                                    <span class="dashicons dashicons-menu-alt"></span>
                                </button>
                                <div id="instance-filter-dropdown" class="dropdown-menu" style="display: none;">
                                    <a href="#" data-instance="all" class="instance-filter-item active"><?php esc_html_e( 'Todas las instancias', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></a>
                                    <!-- Las instancias se cargarán aquí con JS -->
                                    <!-- Ejemplo: <a href="#" data-instance="instance_name_1" class="instance-filter-item">Instancia 1</a> -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="chat-search-container">
                        <input type="search" id="chat-search-input" placeholder="<?php esc_attr_e( 'Buscar o empezar un chat nuevo', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>">
                    </div>

                    <div class="chat-list-filters">
                        <button class="filter-button active" data-filter="all"><?php esc_html_e( 'Todos', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></button>
                        <button class="filter-button" data-filter="favorites"><?php esc_html_e( 'Favoritos', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></button>
                        <button class="filter-button" data-filter="contacts"><?php esc_html_e( 'Contactos', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></button>
                        <button class="filter-button" data-filter="groups"><?php esc_html_e( 'Grupos', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></button>
                    </div>
                </div>
                <div id="chat-list-items" class="chat-list-items">
                    <p><?php esc_html_e( 'Cargando conversaciones...', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></p>
                </div>
            </div>
            <div id="chat-view-column" class="chat-view-column"> <!-- Cambiado ID para claridad -->
                <div id="active-chat-header" style="display: none;"> <!-- Mantenemos display:none aquí, el resto lo maneja CSS -->
                    <img src="" alt="Avatar" class="chat-header-avatar">
                    <span class="chat-header-name"></span>
                </div>

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
                        <?php // --- Emojis Adicionales --- ?>
                        <span class="emoji-option">👍</span>
                        <span class="emoji-option">👎</span>
                        <span class="emoji-option">👌</span>
                        <span class="emoji-option">🤷</span>
                        <span class="emoji-option">🎉</span>

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
                <div class="contact-details-header contact-details-main-header"> <!-- Clase añadida aquí -->
                    <h3><?php esc_html_e( 'Detalles del Contacto', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?></h3>
                    <!-- === INICIO: Botón Cerrar Sidebar === -->
                    <button id="close-contact-details" class="button-icon" title="<?php esc_attr_e( 'Cerrar detalles', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
                    <!-- === FIN: Botón Cerrar Sidebar === -->
                </div>
                <div id="contact-details-content" class="contact-details-content">
                    <!-- Sección Avatar -->
                    <div class="detail-item contact-avatar-container text-center">
                        <img id="contact-avatar-img" src="<?php echo esc_url(includes_url('images/blank.gif')); ?>" alt="<?php esc_attr_e('Avatar del Contacto', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?>" class="contact-avatar-sidebar">
                        <button id="change-avatar-btn" class="button button-small button-secondary change-avatar-btn-sidebar" title="<?php esc_attr_e('Cambiar avatar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?>"><span class="dashicons dashicons-camera"></span> <?php esc_html_e('Cambiar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></button>
                    </div>

                    <!-- Sección Datos de WordPress -->
                    <div id="contact-wp-data" class="contact-data-section">
                        <h4><span class="dashicons dashicons-wordpress"></span> <?php esc_html_e('Datos de WordPress', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></h4>
                        <div class="detail-item editable-field" data-field-name="display_name">
                            <label><?php esc_html_e('Nombre Completo:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></label>
                            <span class="value" id="detail-wp-displayname"></span>
                            <button class="edit-field-btn button-icon" title="<?php esc_attr_e('Editar Nombre Completo', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?>"><span class="dashicons dashicons-edit"></span></button>
                            <div class="edit-controls" style="display:none;">
                                <input type="text" id="edit-wp-displayname" class="regular-text">
                                <button class="save-field-btn button button-primary button-small"><?php esc_html_e('Guardar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></button>
                                <button class="cancel-edit-btn button button-secondary button-small"><?php esc_html_e('Cancelar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></button>
                            </div>
                        </div>
                        <div class="detail-item editable-field" data-field-name="first_name">
                            <label><?php esc_html_e('Nombre:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></label>
                            <span class="value" id="detail-wp-firstname"></span>
                            <button class="edit-field-btn button-icon" title="<?php esc_attr_e('Editar Nombre', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?>"><span class="dashicons dashicons-edit"></span></button>
                            <div class="edit-controls" style="display:none;">
                                <input type="text" id="edit-wp-firstname" class="regular-text">
                                <button class="save-field-btn button button-primary button-small"><?php esc_html_e('Guardar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></button>
                                <button class="cancel-edit-btn button button-secondary button-small"><?php esc_html_e('Cancelar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></button>
                            </div>
                        </div>
                        <div class="detail-item editable-field" data-field-name="last_name">
                            <label><?php esc_html_e('Apellido:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></label>
                            <span class="value" id="detail-wp-lastname"></span>
                            <button class="edit-field-btn button-icon" title="<?php esc_attr_e('Editar Apellido', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?>"><span class="dashicons dashicons-edit"></span></button>
                            <div class="edit-controls" style="display:none;">
                                <input type="text" id="edit-wp-lastname" class="regular-text">
                                <button class="save-field-btn button button-primary button-small"><?php esc_html_e('Guardar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></button>
                                <button class="cancel-edit-btn button button-secondary button-small"><?php esc_html_e('Cancelar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></button>
                            </div>
                        </div>
                        <div class="detail-item editable-field" data-field-name="email">
                            <label><?php esc_html_e('Email:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></label>
                            <span class="value" id="detail-wp-email"></span>
                            <button class="edit-field-btn button-icon" title="<?php esc_attr_e('Editar Email', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?>"><span class="dashicons dashicons-edit"></span></button>
                            <div class="edit-controls" style="display:none;">
                                <input type="email" id="edit-wp-email" class="regular-text">
                                <button class="save-field-btn button button-primary button-small"><?php esc_html_e('Guardar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></button>
                                <button class="cancel-edit-btn button button-secondary button-small"><?php esc_html_e('Cancelar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></button>
                            </div>
                        </div>
                        <div class="detail-item">
                            <label><?php esc_html_e('Rol:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></label>
                            <span class="value" id="detail-wp-role"></span>
                        </div>
                        <div class="detail-item">
                            <label><?php esc_html_e('Registrado:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></label>
                            <span class="value" id="detail-wp-registrationdate"></span>
                        </div>
                    </div>
                    <hr>
                    <!-- Sección Datos del CRM -->
                    <div id="contact-crm-data" class="contact-data-section">
                        <h4><span class="dashicons dashicons-admin-users"></span> <?php esc_html_e('Datos del CRM', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></h4>
                        <div class="detail-item">
                            <label><?php esc_html_e('Teléfono:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></label>
                            <span class="value" id="detail-crm-phone"></span>
                        </div>
                        <div class="detail-item">
                            <label><?php esc_html_e('JID WhatsApp:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></label>
                            <span class="value" id="detail-crm-jid"></span>
                        </div>
                        <div class="detail-item editable-field" data-field-name="tag_key">
                            <label><?php esc_html_e('Etiqueta:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></label>
                            <span class="value" id="detail-crm-tag"></span>
                            <button class="edit-field-btn button-icon" title="<?php esc_attr_e('Editar Etiqueta', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?>"><span class="dashicons dashicons-edit"></span></button>
                            <div class="edit-controls" style="display:none;">
                                <select id="edit-crm-tag" class="regular-text"></select>
                                <button class="save-field-btn button button-primary button-small"><?php esc_html_e('Guardar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></button>
                                <button class="cancel-edit-btn button button-secondary button-small"><?php esc_html_e('Cancelar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></button>
                            </div>
                        </div>
                        <div class="detail-item editable-field" data-field-name="notes">
                            <label><?php esc_html_e('Notas:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></label>
                            <span class="value pre-wrap" id="detail-crm-notes"></span>
                            <button class="edit-field-btn button-icon" title="<?php esc_attr_e('Editar Notas', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?>"><span class="dashicons dashicons-edit"></span></button>
                            <div class="edit-controls" style="display:none;">
                                <textarea id="edit-crm-notes" rows="3" class="regular-text"></textarea>
                                <button class="save-field-btn button button-primary button-small"><?php esc_html_e('Guardar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></button>
                                <button class="cancel-edit-btn button button-secondary button-small"><?php esc_html_e('Cancelar', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></button>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <!-- Sección Datos de WooCommerce -->
                    <div id="contact-woo-data" class="contact-data-section">
                        <h4><span class="dashicons dashicons-cart"></span> <?php esc_html_e('Datos de WooCommerce', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></h4>
                        <div id="woo-last-purchase-details" style="display:none;">
                            <h5><?php esc_html_e('Última Compra:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></h5>
                            <div class="detail-item"><label><?php esc_html_e('ID Pedido:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></label> <span class="value" id="detail-woo-lastorder-id"></span> <a href="#" id="detail-woo-lastorder-url" target="_blank" class="button-icon" title="<?php esc_attr_e('Ver Pedido', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?>"><span class="dashicons dashicons-external"></span></a></div>
                            <div class="detail-item"><label><?php esc_html_e('Fecha:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></label> <span class="value" id="detail-woo-lastorder-date"></span></div>
                            <div class="detail-item"><label><?php esc_html_e('Total:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></label> <span class="value" id="detail-woo-lastorder-total"></span></div>
                            <div class="detail-item"><label><?php esc_html_e('Estado:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></label> <span class="value" id="detail-woo-lastorder-status"></span></div>
                        </div>
                        <div id="woo-no-last-purchase" class="notice notice-info inline" style="display:none;"><p><?php esc_html_e('No hay compras recientes.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></p></div>

                        <div id="woo-customer-history-details" style="display:none; margin-top:15px;">
                            <h5><?php esc_html_e('Historial del Cliente:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></h5>
                            <div class="detail-item"><label><?php esc_html_e('Total Pedidos:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></label> <span class="value" id="detail-woo-total-orders"></span></div>
                            <div class="detail-item"><label><?php esc_html_e('Ingresos Totales:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></label> <span class="value" id="detail-woo-total-revenue"></span></div>
                            <div class="detail-item"><label><?php esc_html_e('Valor Medio Pedido:', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></label> <span class="value" id="detail-woo-avg-order-value"></span></div>
                        </div>
                        <div id="woo-no-customer-history" class="notice notice-info inline" style="display:none;"><p><?php esc_html_e('No hay historial de cliente disponible.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN); ?></p></div>
                    </div>
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