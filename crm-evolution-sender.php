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
require_once CRM_EVOLUTION_SENDER_PLUGIN_DIR . 'crm-main.php';
require_once CRM_EVOLUTION_SENDER_PLUGIN_DIR . 'crm-setting.php';
// require_once CRM_EVOLUTION_SENDER_PLUGIN_DIR . 'crm-cron.php'; // Descomentar cuando se implemente
require_once CRM_EVOLUTION_SENDER_PLUGIN_DIR . 'crm-ajax-handlers.php';
require_once CRM_EVOLUTION_SENDER_PLUGIN_DIR . 'crm-rest-api.php';
require_once CRM_EVOLUTION_SENDER_PLUGIN_DIR . 'crm-cpt-chat.php';




// --- Logging Básico (PHP) ---
if ( ! function_exists( 'crm_log' ) ) {
    /**
     * Función simple de logging. Escribe en wp-content/debug.log si WP_DEBUG_LOG está activado.
     * @param mixed $message Mensaje o dato a registrar.
     * @param string $level Nivel de log (INFO, DEBUG, ERROR).
     */
    function crm_log( $message, $level = 'INFO' ) {
        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG === true ) {
            $prefix = '[CRM Evolution Sender ' . $level . ' ' . date( 'Y-m-d H:i:s' ) . '] ';
            if ( is_array( $message ) || is_object( $message ) ) {
                error_log( $prefix . print_r( $message, true ) );
            } else {
                error_log( $prefix . $message );
            }
        }
    }
}

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
        'crm_evolution_sender_main_page_html', // Función que muestra el contenido
        'dashicons-whatsapp', // Icono (WhatsApp)
        25 // Posición
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
    add_submenu_page(
        'crm-evolution-sender-main',                     // Slug del menú padre
        __( 'Historial de Chats', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ), // Título de la página
        __( 'Chats CRM', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ),          // Título del submenú
        'edit_posts',                                    // Capacidad requerida para ver posts
        'edit.php?post_type=crm_chat',                   // Slug: URL directa a la lista del CPT
        null                                             // No necesita función de callback, WP maneja edit.php
    );
    
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
        'crm-evolution_page_crm-evolution-sender-settings' // Hook para la página de ajustes
    ];
    if ( ! in_array($hook, $plugin_pages) ) {
        return;
    }
    
    // biblioteca de medios
    wp_enqueue_media();
    
    // Añadir Thickbox
    add_thickbox();

    // --- Estilos ---
    // SweetAlert2 CSS
    wp_enqueue_style(
        'sweetalert2-css',
        CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/vendor/sweetalert2/sweetalert2.min.css',
        array(),
        '11.10.6' // Reemplazar con la versión actual de tu librería
    );
    // DataTables CSS
    wp_enqueue_style(
        'datatables-css',
        CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/vendor/datatables/dataTables.min.css',
        array(),
        '1.13.6' // Reemplazar con la versión actual de tu librería
    );

    wp_enqueue_style(
        'intl-tel-input-css',
        CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/vendor/intl-tel-input/intlTelInput.min.css', // Verifica esta ruta
        [], // Sin dependencias CSS
        '17.0.15' // ¡Reemplaza con la versión que descargaste!
    );
    
    // Estilo Principal del Plugin
    wp_enqueue_style(
        'crm-evolution-sender-style',
        CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/style.css',
        array('thickbox','sweetalert2-css', 'datatables-css', 'intl-tel-input-css'), // Dependencias
        CRM_EVOLUTION_SENDER_VERSION
    );

    // --- Scripts ---
    // SweetAlert2 JS
    wp_enqueue_script(
        'sweetalert2-js',
        CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/vendor/sweetalert2/sweetalert2.all.min.js',
        array('jquery'), // Dependencia de jQuery
        '11.10.6', // Reemplazar con la versión actual
        true // Cargar en el footer
    );
    // DataTables JS
    wp_enqueue_script(
        'datatables-js',
        CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/vendor/datatables/dataTables.min.js',
        array('jquery'), // Dependencia de jQuery
        '1.13.6', // Reemplazar con la versión actual
        true // Cargar en el footer
    );

    wp_enqueue_script(
        'intl-tel-input-js',
        CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/vendor/intl-tel-input/intlTelInput.min.js', // Verifica esta ruta
        ['jquery'], // Depende de jQuery
        '17.0.15', // ¡Reemplaza con la versión que descargaste!
        true // Cargar en el footer
    );

    // Script Principal del Plugin (app.js)
    wp_enqueue_script(
        'crm-evolution-sender-appjs',
        CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/app.js',
        array('jquery',  'thickbox', 'sweetalert2-js', 'datatables-js', 'intl-tel-input-js'), // Dependencias
        CRM_EVOLUTION_SENDER_VERSION,
        true // Cargar en el footer
    );

    // Pasar datos de PHP a JavaScript (ej: ajaxurl, nonce, etc.)
    wp_localize_script( 'crm-evolution-sender-appjs', 'crm_evolution_sender_params', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'crm_evolution_sender_nonce' ), // Nonce para seguridad AJAX
        'utils_script_path' => CRM_EVOLUTION_SENDER_PLUGIN_URL . 'assets/vendor/intl-tel-input/js/utils.js',
        'i18n' => array(
            'creatingText' => esc_js( __( 'Creando...', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) ),
            // Puedes añadir más cadenas aquí si las necesitas en JS
            // 'savingText'   => esc_js( __( 'Guardando...', CRM_EVOLUTION_SENDER_TEXT_DOMAIN ) ),
        )
    ));
}
add_action( 'admin_enqueue_scripts', 'crm_evolution_sender_enqueue_assets' );


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
        add_option( 'crm_evolution_api_token', '' ); // No guardar el token por defecto por seguridad
    }
    // Podrías crear una tabla para guardar las campañas aquí
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
        $user_id = (int) $id_or_email->user_id; // Para comentarios
    } elseif ( is_object( $id_or_email ) && isset( $id_or_email->ID ) ) {
         $user_id = (int) $id_or_email->ID; // Para WP_User
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
        $size = isset( $args['size'] ) ? $args['size'] : 96; // Usar tamaño de args o 96 por defecto
        $image_data = wp_get_attachment_image_src( $attachment_id, array( $size, $size ) ); // Pedir tamaño cuadrado

        // Si obtuvimos la URL de la imagen
        if ( $image_data && isset( $image_data[0] ) ) {
            $args['url'] = $image_data[0]; // Reemplazar la URL por la de nuestra imagen
            $args['found_avatar'] = true; // Indicar que encontramos un avatar personalizado
            // Opcional: añadir una clase CSS
            $args['class'] = array_merge( (array) $args['class'], array('crm-local-avatar') );
            crm_log("Usando avatar local (Attachment ID: {$attachment_id}) para usuario ID {$user_id}.", 'DEBUG');
        } else {
             crm_log("Se encontró Attachment ID {$attachment_id} para usuario {$user_id}, pero wp_get_attachment_image_src falló.", 'WARN');
        }
    }

    return $args; // Devolver los argumentos (modificados o no)
}
add_filter( 'get_avatar_data', 'crm_evolution_use_media_library_avatar', 10, 2 );






?>
