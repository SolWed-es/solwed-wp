<?php
/**
 * Plugin Name: WP Solwed
 * Plugin URI: https://solwed.es (Opcional)
 * Description: Conecta Elementor Forms con FacturaScripts CRM y otras personalizaciones para Solwed.
 * Version: 1.7
 * Author: Solwed
 * Author URI: https://solwed.es (Opcional)
 * Text Domain: solwed
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

define( 'SOLWED_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SOLWED_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SOLWED_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

$solwed_settings_class_file = SOLWED_PLUGIN_DIR . 'includes/class-solwed-settings-page.php';
if ( file_exists( $solwed_settings_class_file ) ) {
    require_once $solwed_settings_class_file;
} else {
    add_action( 'admin_notices', 'solwed_admin_notice_missing_class_file' );
    function solwed_admin_notice_missing_class_file() {
        $attempted_path = SOLWED_PLUGIN_DIR . 'includes/class-solwed-settings-page.php';
        ?>
        <div class="error">
            <p>
                <?php esc_html_e( 'El plugin "WP Solwed" no puede cargar un archivo esencial y no funcionará correctamente.', 'solwed' ); ?>
                <br>
                <?php esc_html_e( 'Archivo que falta:', 'solwed' ); ?>
                <code><?php echo esc_html( 'includes/class-solwed-settings-page.php' ); ?></code>
                <br>
                <?php esc_html_e( 'Ruta completa intentada:', 'solwed' ); ?>
                <code><?php echo esc_html( $attempted_path ); ?></code>
            </p>
        </div>
        <?php
    }
    return;
}

function solwed_run_plugin() {
    if ( class_exists( 'SOLWED_Settings_Page' ) ) {
        new SOLWED_Settings_Page();
    } else {
        add_action( 'admin_notices', 'solwed_admin_notice_class_not_found' );
        function solwed_admin_notice_class_not_found() {
            ?>
            <div class="error">
                <p><?php esc_html_e( 'El plugin "WP Solwed" encontró un error: la clase SOLWED_Settings_Page no existe.', 'solwed' ); ?></p>
            </div>
            <?php
        }
    }
}
add_action( 'plugins_loaded', 'solwed_run_plugin' );

function solwed_load_textdomain() {
    load_plugin_textdomain( 'solwed', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'solwed_load_textdomain' );

function solwed_add_settings_link( $links ) {
    $settings_page_slug = 'solwed_settings';
    $settings_link = '<a href="' . admin_url( 'options-general.php?page=' . $settings_page_slug ) . '">' . __( 'Configuración WP Solwed', 'solwed' ) . '</a>'; // Título del enlace actualizado
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . SOLWED_PLUGIN_BASENAME, 'solwed_add_settings_link' );

/**
 * Configura PHPMailer para usar los ajustes SMTP guardados.
 * Este hook se ejecuta justo antes de que se envíe un correo.
 */
add_action( 'phpmailer_init', 'solwed_configure_smtp' );

function solwed_configure_smtp( $phpmailer ) {
    $smtp_settings = get_option( 'solwed_smtp_settings', [] ); // Obtener ajustes SMTP

    // Solo configurar si el host SMTP está definido (indicador de que se quiere usar SMTP)
    if ( empty( $smtp_settings['smtp_host'] ) ) {
        return; // No hacer nada si el host SMTP no está configurado
    }

    $phpmailer->isSMTP(); // Usar SMTP
    $phpmailer->Host       = $smtp_settings['smtp_host'];
    $phpmailer->Port       = !empty($smtp_settings['smtp_port']) ? (int) $smtp_settings['smtp_port'] : 587; // Puerto por defecto si no está
    
    if ( !empty($smtp_settings['smtp_encryption']) && $smtp_settings['smtp_encryption'] !== 'none' ) {
        $phpmailer->SMTPSecure = $smtp_settings['smtp_encryption']; // 'ssl' o 'tls'
    }

    if ( isset($smtp_settings['smtp_auth']) && $smtp_settings['smtp_auth'] === 'yes' ) {
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $smtp_settings['smtp_user'];
        $phpmailer->Password = $smtp_settings['smtp_pass'];
    } else {
        $phpmailer->SMTPAuth = false;
    }
}

// Hooks adicionales para forzar el email y nombre del remitente
add_filter( 'wp_mail_from', 'solwed_smtp_from_email' );
function solwed_smtp_from_email( $original_from_email ) {
    $smtp_settings = get_option( 'solwed_smtp_settings', [] );
    if ( !empty( $smtp_settings['smtp_from_email'] ) && !empty( $smtp_settings['smtp_host'] ) ) { // Solo aplicar si SMTP está configurado
        return sanitize_email( $smtp_settings['smtp_from_email'] );
    }
    return $original_from_email;
}

add_filter( 'wp_mail_from_name', 'solwed_smtp_from_name' );
function solwed_smtp_from_name( $original_from_name ) {
    $smtp_settings = get_option( 'solwed_smtp_settings', [] );
    if ( !empty( $smtp_settings['smtp_from_name'] ) && !empty( $smtp_settings['smtp_host'] ) ) { // Solo aplicar si SMTP está configurado
        return sanitize_text_field( $smtp_settings['smtp_from_name'] );
    }
    return $original_from_name;
}

add_filter('woocommerce_available_payment_gateways', 'solwed_filtra_pasarelas_pago');

function solwed_filtra_pasarelas_pago($available_gateways) {
    if (is_admin()) return $available_gateways;

    $has_subscription = false;

    if (function_exists('WC') && WC()->cart) {
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (class_exists('WC_Subscriptions_Product') && WC_Subscriptions_Product::is_subscription($cart_item['data'])) {
                $has_subscription = true;
                break;
            }
        }
    }

    // Si hay suscripción, quitar Redsys
    if ($has_subscription) {
        if (isset($available_gateways['redsys'])) {
            unset($available_gateways['redsys']);
        }
    } else {
        // Si no hay suscripción, quitar Stripe
        if (isset($available_gateways['stripe'])) {
            unset($available_gateways['stripe']);
        }
    }

    return $available_gateways;
}

/**
 * Personalizar el logo de la página de login
 */
add_filter( 'login_head', function () {
    // Update the line below with the URL to your own logo.
    // Adjust the Width & Height accordingly.
    $custom_logo = 'https://filedn.eu/litOB0SUT8q5aLOM933djFm/solwed_es_negro.png';
    $logo_width  = 320;
    $logo_height = 89;

    printf(
        '<style>.login h1 a {background-image:url(%1$s) !important; margin:0 auto; width: %2$spx; height: %3$spx; background-size: 100%%;}</style>',
        $custom_logo,
        $logo_width,
        $logo_height
    );
}, 990 );

/**
 * Personalizar el texto del footer del admin
 */
add_filter(
    'admin_footer_text',
    function ( $footer_text ) {
        // Edit the line below to customize the footer text.
        $footer_text = 'Powered by <a href="https://www.solwed.es" target="_blank" rel="noopener"><b style="color:#293133">SOLWED.ES ✌️</b></a>';
        
        return $footer_text;
    }
);

/**
 * Allow SVG uploads for administrator users.
 *
 * @param array $upload_mimes Allowed mime types.
 *
 * @return mixed
 */
add_filter('upload_mimes', function($mimes) {
    if (current_user_can('administrator')) {
        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
        $mimes['json'] = 'application/json';
        $mimes['zip']  = 'application/zip';
    }
    return $mimes;
});
add_filter('mime_types', function($mime_types) {
    $mime_types['json'] = 'JSON';
    $mime_types['zip']  = 'ZIP';
    $mime_types['svg']  = 'SVG';
    return $mime_types;
});

/**
 * Add SVG files mime check.
 *
 * @param array        $wp_check_filetype_and_ext Values for the extension, mime type, and corrected filename.
 * @param string       $file Full path to the file.
 * @param string       $filename The name of the file (may differ from $file due to $file being in a tmp directory).
 * @param string[]     $mimes Array of mime types keyed by their file extension regex.
 * @param string|false $real_mime The actual mime type or false if the type cannot be determined.
 */
add_filter(
    'wp_check_filetype_and_ext',
    function ( $wp_check_filetype_and_ext, $file, $filename, $mimes, $real_mime ) {

        if ( ! $wp_check_filetype_and_ext['type'] ) {

            $check_filetype  = wp_check_filetype( $filename, $mimes );
            $ext             = $check_filetype['ext'];
            $type            = $check_filetype['type'];
            $proper_filename = $filename;

            if ( $type && 0 === strpos( $type, 'image/' ) && 'svg' !== $ext ) {
                $ext  = false;
                $type = false;
            }

            $wp_check_filetype_and_ext = compact( 'ext', 'type', 'proper_filename' );
        }

        return $wp_check_filetype_and_ext;

    },
    10,
    5
);

/**
 * Deshabilitar el editor de bloques Gutenberg
 */
add_filter('gutenberg_can_edit_post', '__return_false', 5);
add_filter('use_block_editor_for_post', '__return_false', 5);
add_filter('use_widgets_block_editor', '__return_false' );

?>



