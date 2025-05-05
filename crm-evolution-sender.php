<?php
/**
 * Plugin Name:       CRM Evolution Sender
 * Plugin URI:        https://percyalvarez.com/plugins-wordpress
 * Description:       Gestiona instancias de Evolution API, usuarios de WP y envíos masivos.
 * Version:           1.0.0
 * Author:            Ing. Percy Alvarez
 * Author URI:        https://percyalvarez.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       crm-evolution-sender
 * Domain Path:       /languages
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Constantes del Plugin
define( 'CRM_EVOLUTION_SENDER_VERSION', '1.0.0' );
define( 'CRM_EVOLUTION_SENDER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CRM_EVOLUTION_SENDER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CRM_EVOLUTION_SENDER_TEXT_DOMAIN', 'crm-evolution-sender' );

// --- Archivos Requeridos ---
// require_once CRM_EVOLUTION_SENDER_PLUGIN_DIR . 'crm-main.php';
require_once CRM_EVOLUTION_SENDER_PLUGIN_DIR . 'crm-setting.php';
require_once CRM_EVOLUTION_SENDER_PLUGIN_DIR . 'crm-ajax-handlers.php';
require_once CRM_EVOLUTION_SENDER_PLUGIN_DIR . 'crm-rest-api.php';
require_once CRM_EVOLUTION_SENDER_PLUGIN_DIR . 'crm-instances.php'; // <-- Movido después de crm-rest-api.php
require_once CRM_EVOLUTION_SENDER_PLUGIN_DIR . 'crm-cpt-chat.php';
require_once CRM_EVOLUTION_SENDER_PLUGIN_DIR . 'crm-cpt-campaign.php';
require_once CRM_EVOLUTION_SENDER_PLUGIN_DIR . 'crm-chat-history.php';

// --- Hooks ---

/**
 * Registra el menú principal en el administrador de WordPress.
 */
function crm_evolution_sender_admin_menu() {
    add_menu_page(
        __( 'CRM Evolution', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), // Título de la página
        __( 'CRM Evolution', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), // Título del menú
        'manage_options', // Capacidad requerida
        'crm-evolution-sender-main', // Slug del menú
        'crm_render_instances_page_html', // <--- CAMBIO AQUÍ: Nueva función callback (definida en crm-instances.php)
        'dashicons-whatsapp', // Icono (WhatsApp)
        25 // Posición
    );
    // --- Submenú para Historial de Chats Estilo WhatsApp ---
    add_submenu_page(
        'crm-evolution-sender-main',                     // Slug del menú padre
        __( 'Conversaciones', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), // Título de la página
        __( 'Conversaciones', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),       // Título del submenú
        'edit_posts',                                    // Capacidad requerida (ver chats)
        'crm-evolution-chat-history',                    // Slug de este submenú
        'crm_evolution_sender_chat_history_page_html'    // Función que muestra el contenido (de crm-chat-history.php)
    );
    // Página de Ajustes (como submenú)
    add_submenu_page(
        'crm-evolution-sender-main', // Slug del menú padre
        __( 'Ajustes API', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), // Título de la página
        __( 'Ajustes API', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), // Título del submenú
        'manage_options', // Capacidad requerida
        'crm-evolution-sender-settings', // Slug de este submenú
        'crm_evolution_sender_settings_page_html' // Función que muestra el contenido
    );
    // Añadir submenú para listar los Chats CRM (CPT)
    // add_submenu_page(
    //     'crm-evolution-sender-main',                     // Slug del menú padre
    //     __( 'Historial de Chats', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), // Título de la página
    //     __( 'Chats CRM', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),          // Título del submenú
    //     'edit_posts',                                    // Capacidad requerida para ver posts
    //     'edit.php?post_type=crm_chat',                   // Slug: URL directa a la lista del CPT
    //     null                                             // No necesita función de callback, WP maneja edit.php
    // );

    
    
}
add_action( 'admin_menu', 'crm_evolution_sender_admin_menu' );

/**
 * Encola los scripts y estilos necesarios en las páginas del plugin.
 *
 * @param string $hook Hook de la página actual.
 */
function crm_evolution_sender_enqueue_assets( $hook ) {
    // Solo cargar en las páginas de nuestro plugin
    // Ajuste: El hook para submenús incluye el slug del menú padre
    $plugin_pages = [
        'toplevel_page_crm-evolution-sender-main',
        'crm-evolution_page_crm-evolution-sender-settings', // Hook para la página de ajustes
        'crm-evolution_page_crm-evolution-chat-history'     // Hook para la nueva página de historial de chats
    ];

    wp_enqueue_media();
    
    add_thickbox();

    wp_enqueue_style(
        'sweetalert2-css',
        CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/vendor/sweetalert2/sweetalert2.min.css',
        array(),
        '11.10.6'
    );

    if ( in_array($hook, ['crm-evolution_page_crm-evolution-sender-settings', 'crm-evolution_page_crm-evolution-chat-history']) ) {
        wp_enqueue_style(
            'datatables-css',
            CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/vendor/datatables/dataTables.min.css',
            array(), 
            '1.13.6'
        );
    }

    wp_enqueue_style(
        'intl-tel-input-css',
        CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/vendor/intl-tel-input/intlTelInput.min.css',
        [],
        '17.0.15'
    );
    
    // Estilo Principal del Plugin
    wp_enqueue_style(
        'crm-evolution-sender-style',
        CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/style.css',
        array('thickbox','sweetalert2-css', 'datatables-css', 'intl-tel-input-css'), 
        CRM_EVOLUTION_SENDER_VERSION
    );

    // --- Scripts ---
    wp_enqueue_script(
        'sweetalert2-js',
        CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/vendor/sweetalert2/sweetalert2.all.min.js',
        array('jquery'),
        '11.10.6',
        true
    );

    // Cargar DataTables JS solo si estamos en una página que lo necesite
    if ( in_array($hook, ['crm-evolution_page_crm-evolution-sender-settings', 'crm-evolution_page_crm-evolution-chat-history']) ) {
        wp_enqueue_script(
            'datatables-js',
            CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/vendor/datatables/dataTables.min.js',
            array('jquery'),
            '1.13.6',
            true
        );
    }

    wp_enqueue_script(
        'intl-tel-input-js',
        CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/vendor/intl-tel-input/intlTelInput.min.js',
        ['jquery'],
        '17.0.15',
        true
    );

    // Cargar app.js solo si estamos en una página que lo necesite (Ajustes, Chat History?)
    if ( in_array($hook, ['crm-evolution_page_crm-evolution-sender-settings', 'crm-evolution_page_crm-evolution-chat-history']) ) {
        wp_enqueue_script(
            'crm-evolution-sender-appjs',
            CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/app.js',
            array('jquery', 'thickbox', 'sweetalert2-js', 'datatables-js', 'intl-tel-input-js'),
            CRM_EVOLUTION_SENDER_VERSION,
            true
        );

        // Pasar datos de PHP a JavaScript (ej: ajaxurl, nonce, etc.)
        wp_localize_script( 'crm-evolution-sender-appjs', 'crm_evolution_sender_params', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'crm_evolution_sender_nonce' ),
            'utils_script_path' => CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/vendor/intl-tel-input/js/utils.js',
            'i18n' => array(
                'creatingText' => esc_js( __( 'Creando...', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) ),
            )
        ));
    }
}
add_action( 'admin_enqueue_scripts', 'crm_evolution_sender_enqueue_assets' );


/**
 * Encola los scripts y estilos específicos para la página de gestión de Instancias (Cards).
 *
 * @param string $hook_suffix El hook de la página actual.
 */
function crm_enqueue_instances_page_assets( $hook_suffix ) {

    // Comprobar si estamos en la página principal del plugin (Instancias)
    if ( 'toplevel_page_crm-evolution-sender-main' === $hook_suffix ) {

        wp_enqueue_style(
            'crm-admin-instances-style',
            CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/admin-instances.css',
            array('thickbox', 'sweetalert2-css'),
            CRM_EVOLUTION_SENDER_VERSION
        );

        wp_enqueue_script(
            'crm-admin-instances-script',
            CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/admin-instances.js',
            array('jquery', 'thickbox', 'sweetalert2-js', 'wp-util'),
            CRM_EVOLUTION_SENDER_VERSION,
            true
        );

        // Localizar datos específicos para el script de instancias
        wp_localize_script( 'crm-admin-instances-script', 'crmInstancesData', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'crm_evolution_sender_nonce' ),
            'create_instance_nonce' => wp_create_nonce( 'crm_create_instance_action' ),
            'webhook_url' => esc_url( rest_url( 'crm-evolution-api/v1/webhook' ) ),
            'i18n'     => array(
                'confirm_delete' => __( '¿Estás seguro de que quieres eliminar esta instancia?', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
                'error_generic'  => __( 'Ocurrió un error. Por favor, inténtalo de nuevo.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
                'instance_added' => __( 'Instancia añadida con éxito.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
                'connecting'     => __( 'Conectando...', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
                'disconnecting'  => __( 'Desconectando...', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
                'deleting'       => __( 'Eliminando...', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
                'generating_qr'  => __( 'Generando QR...', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
                'syncing_contacts' => __( 'Sincronizando contactos...', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
                'sync_contacts_success' => __( 'Sincronización de contactos completada.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
                'sync_contacts_error' => __( 'Error al sincronizar contactos.', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),
            )
        ));
    }
}
// Asegúrate que esta línea también esté presente después de la definición de la función:
add_action( 'admin_enqueue_scripts', 'crm_enqueue_instances_page_assets' );


/**
 * Encola los scripts y estilos específicos para la pantalla de edición del CPT Campañas.
 *
 * @param string $hook_suffix El hook de la página actual.
 */
function crm_enqueue_campaign_edit_assets( $hook_suffix ) {
    // Comprobar si estamos en la página de edición (post.php) o creación (post-new.php)
    if ( 'post.php' === $hook_suffix || 'post-new.php' === $hook_suffix ) {
        // Obtener el tipo de post actual de forma segura
        $current_post_type = get_current_screen()->post_type ?? null;

        // Encolar solo si es nuestro CPT de campañas
        if ( 'crm_sender_campaign' === $current_post_type ) {
            // Encolar el CSS específico
            wp_enqueue_style(
                'crm-admin-campaign-styles',
                CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/admin-campaign.css',
                array(),
                CRM_EVOLUTION_SENDER_VERSION
            );
            
            wp_enqueue_media();

            wp_enqueue_script(
                'crm-admin-campaign-js',
                CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/admin-campaign.js',
                array('jquery'),
                CRM_EVOLUTION_SENDER_VERSION,
                true
            );
        }
    }
}
add_action( 'admin_enqueue_scripts', 'crm_enqueue_campaign_edit_assets' );

/**
 * Añade un enlace de "Ajustes" en la lista de plugins.
 *
 * @param array $links Array de enlaces de acción del plugin.
 * @return array Array modificado de enlaces.
 */
function crm_evolution_sender_add_settings_link( $links ) {
    $settings_link = '<a href="' . admin_url( 'admin.php?page=crm-evolution-sender-settings' ) . '">' . __( 'Ajustes', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
// Construir el hook específico para este plugin
$plugin_basename = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin_basename", 'crm_evolution_sender_add_settings_link' );


function crm_evolution_sender_activate() {
    if ( false === get_option( 'crm_evolution_api_url' ) ) {
        add_option( 'crm_evolution_api_url', 'https://api.percyalvarez.com' );
    }
     if ( false === get_option( 'crm_evolution_api_token' ) ) {
        add_option( 'crm_evolution_api_token', '' );
    }
}
register_activation_hook( __FILE__, 'crm_evolution_sender_activate' );

function crm_evolution_sender_deactivate() {

}
register_deactivation_hook( __FILE__, 'crm_evolution_sender_deactivate' );


/**
 * Filtra los datos del avatar para usar la imagen guardada en Media Library si existe.
 *
 * @param array $args Argumentos para get_avatar().
 * @param mixed $id_or_email El ID de usuario, email, objeto WP_User o WP_Comment.
 * @return array Argumentos modificados.
 */
function crm_evolution_use_media_library_avatar( $args, $id_or_email ) {
    $user_id = null;

    // Determinar el ID del usuario
    if ( is_numeric( $id_or_email ) ) {
        $user_id = (int) $id_or_email;
    } elseif ( is_object( $id_or_email ) && isset( $id_or_email->user_id ) ) {
        $user_id = (int) $id_or_email->user_id;
    } elseif ( is_object( $id_or_email ) && isset( $id_or_email->ID ) ) {
         $user_id = (int) $id_or_email->ID;
    } elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
        $user = get_user_by( 'email', $id_or_email );
        if ( $user ) {
            $user_id = $user->ID;
        }
    }

    // Si no pudimos obtener un ID de usuario, no hacemos nada
    if ( ! $user_id ) {
        return $args;
    }

    // Obtener el ID del adjunto guardado en el metadato
    $attachment_id = get_user_meta( $user_id, '_crm_avatar_attachment_id', true );

    // Si encontramos un ID de adjunto válido
    if ( $attachment_id ) {
        // Obtener la URL de la imagen del tamaño solicitado (o un tamaño razonable)
        $size = isset( $args['size'] ) ? $args['size'] : 96;
        $image_data = wp_get_attachment_image_src( $attachment_id, array( $size, $size ) );
        if ( $image_data && isset( $image_data[0] ) ) {
            $args['url'] = $image_data[0];
            $args['found_avatar'] = true;
            $args['class'] = array_merge( isset($args['class']) ? (array) $args['class'] : array(), array('crm-local-avatar') );
        } else {

        }
    }

    return $args;
}
add_filter( 'get_avatar_data', 'crm_evolution_use_media_library_avatar', 10, 2 );

/**
 * Elimina el archivo de avatar de la Biblioteca de Medios cuando se elimina un usuario.
 *
 * @param int $user_id ID del usuario que se está eliminando.
 */
function crm_delete_user_avatar_on_user_delete( $user_id ) {
    // Obtener el ID del adjunto del avatar guardado en los metadatos del usuario
    $attachment_id = get_user_meta( $user_id, '_crm_avatar_attachment_id', true );

    if ( ! empty( $attachment_id ) && is_numeric( $attachment_id ) ) {
        $deleted = wp_delete_attachment( $attachment_id, true );
    } else {

    }
}
add_action( 'delete_user', 'crm_delete_user_avatar_on_user_delete', 10, 1 );

?>
