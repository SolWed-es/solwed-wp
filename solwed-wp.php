<?php
/**
 * Plugin Name: Solwed WP
 * Plugin URI: https://solwed.es
 * Description: Plugin profesional para gestión web desarrollado por Solwed - Soluciones Web a Medida
 * Version: 2.1.0
 * Author: Solwed - Solutions Website Design
 * Author URI: https://solwed.es
 * License: GPL v2 or later
 * Text Domain: solwed-wp
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 8.1
 * Network: false
 * Update Server: https://solwed.es
 */

// Verificar que WordPress esté cargado
if (!defined('ABSPATH')) {
    exit;
}

// Verificar versiones mínimas
if (version_compare(PHP_VERSION, '8.1', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>Solwed WP Plugin:</strong> Requiere PHP 8.1 o superior para funcionar correctamente. Versión actual: ' . PHP_VERSION . '</p></div>';
    });
    return;
}

// Definir constantes del plugin
define('SOLWED_WP_VERSION', '2.1.0');
define('SOLWED_WP_PLUGIN_FILE', __FILE__);
define('SOLWED_WP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SOLWED_WP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SOLWED_WP_PREFIX', 'solwed_');

// Cargar archivos requeridos
$required_files = [
    'includes/class-installer.php',
    'includes/class-login-customizer.php',
    'includes/admin/admin-actions.php',
    'includes/admin/tabs/security.php',
    'includes/admin/tabs/smtp.php',
    'includes/admin/tabs/facturascript.php',
    'includes/admin/tabs/appearance.php',
    'includes/admin/tabs/code-editor.php',
    'includes/admin/tabs/logs.php'
];

foreach ($required_files as $file) {
    $file_path = SOLWED_WP_PLUGIN_DIR . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        add_action('admin_notices', function() use ($file) {
            echo '<div class="notice notice-error"><p>Solwed WP Plugin: No se pudo cargar el archivo ' . esc_html($file) . '</p></div>';
        });
        return;
    }
}

/**
 * Clase principal del plugin Solwed WP
 */
class Solwed_WP_Main {
    private static $instance = null;
    private $modules = array();

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init() {
        $this->load_textdomain();
        $this->check_and_create_tables();
        $this->init_modules();
        $this->init_hooks();
    }

    private function load_textdomain() {
        load_plugin_textdomain('solwed-wp', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    private function check_and_create_tables() {
        $db_version = get_option(SOLWED_WP_PREFIX . 'db_version');

        if (version_compare($db_version, SOLWED_WP_VERSION, '<')) {
            if (class_exists('Solwed_Installer')) {
                Solwed_Installer::create_tables();
                update_option(SOLWED_WP_PREFIX . 'db_version', SOLWED_WP_VERSION);
            }
        }
    }

    private function init_modules() {
        $module_classes = [
            'security' => 'Solwed_Security_Unified',
            'smtp' => 'Solwed_SMTP_Unified', 
            'appearance' => 'Solwed_Appearance_Unified'
        ];

        foreach ($module_classes as $key => $class) {
            if (class_exists($class)) {
                $this->modules[$key] = new $class();
                $this->modules[$key]->init();
            }
        }
        
        // Inicializar login customizer
        if (class_exists('Solwed_Login_Customizer')) {
            $login_customizer = new Solwed_Login_Customizer();
            $login_customizer->init();
        }
    }

    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
        add_action('admin_init', [$this, 'handle_admin_actions']);
        add_action('init', [$this, 'execute_custom_php']);
        add_action('wp_head', [$this, 'output_custom_css'], 10);
        add_action('wp_head', [$this, 'output_custom_css_priority'], 999);
        add_action('wp_footer', [$this, 'output_custom_js'], 999);
        add_filter('admin_footer_text', [$this, 'customize_admin_footer_text'], 999);
    }

    public function add_admin_menu() {
        // Menú principal
        add_menu_page(
            __('Solwed WP', 'solwed-wp'),
            __('Solwed WP', 'solwed-wp'),
            'manage_options',
            'solwed-wp',
            [$this, 'render_dashboard_page'],
            'data:image/svg+xml;base64,' . base64_encode('<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><text x="1" y="15" font-size="16" fill="currentColor">✌️</text></svg>'),
            99
        );

        // Dashboard (página principal)
        add_submenu_page(
            'solwed-wp',
            __('Dashboard', 'solwed-wp'),
            __('Dashboard', 'solwed-wp'),
            'manage_options',
            'solwed-wp',
            [$this, 'render_dashboard_page']
        );

        // Configuración
        add_submenu_page(
            'solwed-wp',
            __('Configuración', 'solwed-wp'),
            __('Configuración', 'solwed-wp'),
            'manage_options',
            'solwed-wp-settings',
            [$this, 'render_settings_page']
        );
    }

    public function render_dashboard_page() {
        require_once SOLWED_WP_PLUGIN_DIR . 'includes/admin/admin-dashboard.php';
    }

    public function render_settings_page() {
        require_once SOLWED_WP_PLUGIN_DIR . 'includes/admin/admin-settings.php';
    }

    public function enqueue_admin_assets($hook) {
        if (!str_contains($hook, 'solwed-wp')) {
            return;
        }

        // Versionado por filemtime para evitar caché al actualizar assets
        $css_path = SOLWED_WP_PLUGIN_DIR . 'assets/css/admin.css';
        $js_path  = SOLWED_WP_PLUGIN_DIR . 'assets/js/admin.js';
        $css_ver = file_exists($css_path) ? (SOLWED_WP_VERSION . '-' . filemtime($css_path)) : SOLWED_WP_VERSION;
        $js_ver  = file_exists($js_path)  ? (SOLWED_WP_VERSION . '-' . filemtime($js_path))  : SOLWED_WP_VERSION;

        wp_enqueue_style(
            'solwed-admin-style',
            SOLWED_WP_PLUGIN_URL . 'assets/css/admin.css',
            [],
            $css_ver
        );

        wp_enqueue_script(
            'solwed-admin-script',
            SOLWED_WP_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            $js_ver,
            true
        );


    }

    public function enqueue_public_assets() {
        // Assets públicos si son necesarios
        if ($this->modules['appearance']->is_banner_enabled()) {
            wp_add_inline_style('wp-block-library', $this->modules['appearance']->get_banner_css());
        }
    }

    public function handle_admin_actions() {
        if (!isset($_POST['solwed_action']) || !wp_verify_nonce($_POST['_wpnonce'], 'solwed_settings')) {
            return;
        }

        $action = sanitize_text_field($_POST['solwed_action']);
        $result = false;

        switch ($action) {
            case 'save_appearance':
                $result = $this->modules['appearance']->save_settings($_POST);
                break;
            case 'save_security':
                $result = $this->modules['security']->save_settings($_POST);
                break;
            case 'save_smtp':
                $result = $this->modules['smtp']->save_settings($_POST);
                break;
            case 'test_smtp':
                $result = $this->modules['smtp']->test_connection($_POST);
                break;
            case 'save_code_editor':
                $result = $this->save_code_editor_settings($_POST);
                break;
        }

        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . __('Configuración guardada correctamente.', 'solwed-wp') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Error al guardar la configuración.', 'solwed-wp') . '</p></div>';
            });
        }
    }

    public function get_module(string $name): ?object {
        return $this->modules[$name] ?? null;
    }

    public static function activate() {
        require_once SOLWED_WP_PLUGIN_DIR . 'includes/class-installer.php';
        Solwed_Installer::activate();
    }

    public static function deactivate() {
        require_once SOLWED_WP_PLUGIN_DIR . 'includes/class-installer.php';
        Solwed_Installer::deactivate();
    }

    public static function uninstall() {
        require_once SOLWED_WP_PLUGIN_DIR . 'includes/class-installer.php';
        Solwed_Installer::uninstall();
    }
    
    /**
     * Guardar configuración del editor de código
     */
    public function save_code_editor_settings(array $data): bool {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $settings = [
            'custom_css' => isset($data['custom_css']) ? wp_unslash(sanitize_textarea_field($data['custom_css'])) : '',
            'custom_js' => isset($data['custom_js']) ? wp_unslash(sanitize_textarea_field($data['custom_js'])) : '',
            'custom_css_priority' => isset($data['custom_css_priority']) ? wp_unslash(sanitize_textarea_field($data['custom_css_priority'])) : '',
            'custom_php' => isset($data['custom_php']) ? wp_unslash(sanitize_textarea_field($data['custom_php'])) : ''
        ];

        $success = true;
        foreach ($settings as $key => $value) {
            if (!update_option(SOLWED_WP_PREFIX . $key, $value)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Personalizar el texto del footer del admin
     */
    public function customize_admin_footer_text($text) {
        return 'Gracias por crear con <a style="color: var(--solwed-accent);" href="https://solwed.es" target="_blank">Solwed.es✌️</a>';
    }

    /**
     * Ejecutar código PHP personalizado del usuario
     */
    public function execute_custom_php() {
        $custom_php = get_option(SOLWED_WP_PREFIX . 'custom_php', '');
        
        if (empty(trim($custom_php)) || !current_user_can('manage_options')) {
            return;
        }

        // Ejecutar el código PHP de forma segura
        try {
            // Evitar salida no deseada usando output buffering
            ob_start();
            eval($custom_php);
            ob_end_clean();
        } catch (ParseError $e) {
            // Log del error
            error_log('Solwed WP Custom PHP Parse Error: ' . $e->getMessage());
            
            // Mostrar aviso solo a administradores
            if (current_user_can('manage_options')) {
                add_action('admin_notices', function() use ($e) {
                    echo '<div class="notice notice-error"><p>' . 
                         sprintf(__('<strong>Error de sintaxis en PHP personalizado:</strong> %s', 'solwed-wp'), esc_html($e->getMessage())) . 
                         ' <a href="' . admin_url('admin.php?page=solwed-wp-settings&tab=code-editor') . '">' . __('Editar código', 'solwed-wp') . '</a></p></div>';
                });
            }
        } catch (Error $e) {
            // Log del error fatal
            error_log('Solwed WP Custom PHP Fatal Error: ' . $e->getMessage());
            
            // Mostrar aviso solo a administradores
            if (current_user_can('manage_options')) {
                add_action('admin_notices', function() use ($e) {
                    echo '<div class="notice notice-error"><p>' . 
                         sprintf(__('<strong>Error fatal en PHP personalizado:</strong> %s', 'solwed-wp'), esc_html($e->getMessage())) . 
                         ' <a href="' . admin_url('admin.php?page=solwed-wp-settings&tab=code-editor') . '">' . __('Editar código', 'solwed-wp') . '</a></p></div>';
                });
            }
        }
    }

    /**
     * Mostrar CSS personalizado normal en wp_head
     */
    public function output_custom_css() {
        $custom_css = get_option(SOLWED_WP_PREFIX . 'custom_css', '');
        
        if (!empty(trim($custom_css))) {
            echo "\n<!-- Solwed WP Custom CSS -->\n";
            echo '<style id="solwed-custom-css" type="text/css">' . "\n";
            echo wp_strip_all_tags($custom_css);
            echo "\n</style>\n";
        }
    }

    /**
     * Mostrar CSS de alta prioridad en wp_head (prioridad 999)
     */
    public function output_custom_css_priority() {
        $custom_css_priority = get_option(SOLWED_WP_PREFIX . 'custom_css_priority', '');
        
        if (!empty(trim($custom_css_priority))) {
            echo "\n<!-- Solwed WP Priority CSS -->\n";
            echo '<style id="solwed-priority-css" type="text/css">' . "\n";
            echo wp_strip_all_tags($custom_css_priority);
            echo "\n</style>\n";
        }
    }

    /**
     * Mostrar JavaScript personalizado en wp_footer
     */
    public function output_custom_js() {
        $custom_js = get_option(SOLWED_WP_PREFIX . 'custom_js', '');
        
        if (!empty(trim($custom_js))) {
            echo "\n<!-- Solwed WP Custom JS -->\n";
            echo '<script id="solwed-custom-js" type="text/javascript">' . "\n";
            echo wp_strip_all_tags($custom_js);
            echo "\n</script>\n";
        }
    }
}

// Hooks de activación/desactivación
register_activation_hook(__FILE__, [Solwed_WP_Main::class, 'activate']);
register_deactivation_hook(__FILE__, [Solwed_WP_Main::class, 'deactivate']);
register_uninstall_hook(__FILE__, [Solwed_WP_Main::class, 'uninstall']);

// Inicializar el plugin
function solwed_wp() {
    return Solwed_WP_Main::get_instance();
}

// Arrancar el plugin
solwed_wp();