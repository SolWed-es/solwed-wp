<?php
/**
 * Manejador de Acciones Administrativas
 */

if (!defined('ABSPATH')) {
    exit;
}

class Solwed_WP_Admin_Actions {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks(): void {
        add_action('admin_post_solwed_save_settings', [$this, 'handle_save_settings']);
        add_action('admin_post_solwed_test_smtp', [$this, 'handle_test_smtp']);
        add_action('admin_post_solwed_unblock_ip', [$this, 'handle_unblock_ip']);
        add_action('admin_post_solwed_clear_logs', [$this, 'handle_clear_logs']);
        add_action('admin_post_solwed_save_code', [$this, 'handle_save_code']);
        add_action('wp_ajax_solwed_get_stats', [$this, 'handle_ajax_stats']);

    }
    
    /**
     * Manejar guardado de configuración
     */
    public function handle_save_settings(): void {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'] ?? '', 'solwed_settings')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'solwed-wp'));
        }

        $action = $_POST['solwed_action'] ?? '';
        $redirect_tab = 'appearance';
        
        switch ($action) {
            case 'save_appearance':
                $this->save_appearance_settings();
                $redirect_tab = 'appearance';
                break;
                
            case 'save_security':
                $this->save_security_settings();
                $redirect_tab = 'security';
                break;
                
            case 'save_smtp':
                $this->save_smtp_settings();
                $redirect_tab = 'smtp';
                break;
                
            case 'test_smtp':
                $this->handle_smtp_test();
                // No mostrar mensaje de "configuración guardada" para tests
                set_transient('settings_errors', get_settings_errors(), 30);
                wp_redirect(admin_url('admin.php?page=solwed-wp-settings&tab=smtp&test-completed=true'));
                exit;
        }
        
        // Mostrar mensaje de éxito
        add_settings_error(
            'solwed_messages',
            'solwed_message',
            __('Configuración guardada correctamente.', 'solwed-wp'),
            'updated'
        );
        
        set_transient('settings_errors', get_settings_errors(), 30);
        
        wp_redirect(admin_url('admin.php?page=solwed-wp-settings&tab=' . $redirect_tab . '&settings-updated=true'));
        exit;
    }
    
    /**
     * Guardar configuración de apariencia
     */
    private function save_appearance_settings(): void {
        $banner_enabled = isset($_POST['banner_enabled']) && $_POST['banner_enabled'] === '1';
        $banner_text = sanitize_text_field($_POST['banner_text'] ?? 'Desarrollo Web Profesional');
        
        update_option(SOLWED_WP_PREFIX . 'banner_enabled', $banner_enabled);
        update_option(SOLWED_WP_PREFIX . 'banner_text', $banner_text);
        
        // Log de la acción
        $this->log_admin_action('appearance_updated', [
            'banner_enabled' => $banner_enabled,
            'banner_text' => $banner_text
        ]);
    }
    
    /**
     * Guardar configuración de seguridad
     */
    private function save_security_settings(): void {
        $security_enabled = isset($_POST['security_enabled']) && $_POST['security_enabled'] === '1';
        $max_attempts = max(1, min(10, intval($_POST['max_login_attempts'] ?? 3)));
        $lockout_duration = intval($_POST['lockout_duration'] ?? 1800);
        
        // Validar duración de bloqueo
        $valid_durations = [900, 1800, 3600, 7200, 21600, 86400];
        if (!in_array($lockout_duration, $valid_durations)) {
            $lockout_duration = 1800; // Default a 30 minutos
        }
        
        update_option(SOLWED_WP_PREFIX . 'security_enabled', $security_enabled);
        update_option(SOLWED_WP_PREFIX . 'max_login_attempts', $max_attempts);
        update_option(SOLWED_WP_PREFIX . 'lockout_duration', $lockout_duration);
        
        // Log de la acción
        $this->log_admin_action('security_updated', [
            'security_enabled' => $security_enabled,
            'max_attempts' => $max_attempts,
            'lockout_duration' => $lockout_duration
        ]);
    }
    
    /**
     * Guardar configuración de SMTP
     */
    private function save_smtp_settings(): void {
        $smtp_enabled = isset($_POST['smtp_enabled']) && $_POST['smtp_enabled'] === '1';
        $smtp_host = sanitize_text_field($_POST['smtp_host'] ?? '');
        $smtp_port = max(1, min(65535, intval($_POST['smtp_port'] ?? 587)));
        $smtp_username = sanitize_text_field($_POST['smtp_username'] ?? '');
        $smtp_password = $_POST['smtp_password'] ?? '';
        $smtp_encryption = in_array($_POST['smtp_encryption'] ?? 'tls', ['none', 'tls', 'ssl']) ? $_POST['smtp_encryption'] : 'tls';
        $smtp_from_name = sanitize_text_field($_POST['smtp_from_name'] ?? get_option('blogname'));
        $smtp_from_email = sanitize_email($_POST['smtp_from_email'] ?? 'soporte@solwed.es');
        
        // Validar email del remitente, usar por defecto soporte@solwed.es si no es válido
        if (!filter_var($smtp_from_email, FILTER_VALIDATE_EMAIL)) {
            $smtp_from_email = 'soporte@solwed.es';
        }
        
        // Solo actualizar la contraseña si se proporciona una nueva
        if (!empty($smtp_password)) {
            update_option(SOLWED_WP_PREFIX . 'smtp_password', $this->encrypt_password($smtp_password));
        }
        
        update_option(SOLWED_WP_PREFIX . 'smtp_enabled', $smtp_enabled);
        update_option(SOLWED_WP_PREFIX . 'smtp_host', $smtp_host);
        update_option(SOLWED_WP_PREFIX . 'smtp_port', $smtp_port);
        update_option(SOLWED_WP_PREFIX . 'smtp_username', $smtp_username);
        update_option(SOLWED_WP_PREFIX . 'smtp_encryption', $smtp_encryption);
        update_option(SOLWED_WP_PREFIX . 'smtp_from_name', $smtp_from_name);
        update_option(SOLWED_WP_PREFIX . 'smtp_from_email', $smtp_from_email);
        
        // Log de la acción (sin incluir datos sensibles)
        $this->log_admin_action('smtp_updated', [
            'smtp_enabled' => $smtp_enabled,
            'smtp_host' => $smtp_host,
            'smtp_port' => $smtp_port,
            'smtp_encryption' => $smtp_encryption,
            'smtp_from_name' => $smtp_from_name,
            'smtp_from_email' => $smtp_from_email
        ]);
    }
    
    /**
     * Manejar test de SMTP - Versión simplificada
     */
    public function handle_test_smtp(): void {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'] ?? '', 'solwed_settings')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'solwed-wp'));
        }

        $smtp_module = solwed_wp()->get_module('smtp');
        if (!$smtp_module) {
            wp_die(__('Módulo SMTP no disponible.', 'solwed-wp'));
        }

        // Datos del formulario para el test
        $test_data = [
            'smtp_host' => sanitize_text_field($_POST['smtp_host'] ?? ''),
            'smtp_port' => intval($_POST['smtp_port'] ?? 587),
            'smtp_username' => sanitize_text_field($_POST['smtp_username'] ?? ''),
            'smtp_password' => $_POST['smtp_password'] ?? '',
            'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
            'smtp_from_name' => sanitize_text_field($_POST['smtp_from_name'] ?? ''),
            'smtp_from_email' => sanitize_email($_POST['smtp_from_email'] ?? ''),
            'test_email' => sanitize_email($_POST['test_email_to'] ?? 'soporte@solwed.es')
        ];

        // Ejecutar test
        $result = $smtp_module->test_connection($test_data);
        
        // Redirigir con mensaje
        $message_type = $result['success'] ? 'success' : 'error';
        $redirect_url = admin_url('admin.php?page=solwed-wp-settings&tab=smtp&message=' . urlencode($result['message']) . '&type=' . $message_type . '&from=smtp_test');
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Manejar test de SMTP (sin AJAX)
     */
    private function handle_smtp_test(): void {
        $smtp_module = solwed_wp()->get_module('smtp');
        if (!$smtp_module) {
            add_settings_error(
                'solwed_messages',
                'solwed_smtp_error',
                __('Módulo SMTP no disponible.', 'solwed-wp'),
                'error'
            );
            return;
        }

        // Usar la configuración actual del formulario
        $test_config = [
            'host' => sanitize_text_field($_POST['smtp_host'] ?? ''),
            'port' => intval($_POST['smtp_port'] ?? 587),
            'username' => sanitize_text_field($_POST['smtp_username'] ?? ''),
            'password' => $_POST['smtp_password'] ?? get_option(SOLWED_WP_PREFIX . 'smtp_password', ''),
            'encryption' => $_POST['smtp_encryption'] ?? 'tls',
            'from_name' => sanitize_text_field($_POST['smtp_from_name'] ?? ''),
            'from_email' => sanitize_email($_POST['smtp_from_email'] ?? '')
        ];
        
        // Email de destino para la prueba
        $test_email_to = sanitize_email($_POST['test_email_address'] ?? 'soporte@solwed.es');

        $result = $smtp_module->test_connection($test_config);
        
        if ($result['success']) {
            // Enviar email de prueba
            $test_email = wp_mail(
                $test_email_to,
                __('Test de SMTP - Solwed WP', 'solwed-wp'),
                sprintf(
                    __('Este es un email de prueba enviado desde Solwed WP.%s%sConfiguración utilizada:%s- Host: %s%s- Puerto: %s%s- Usuario: %s%s- Encriptación: %s%s%sSi recibes este mensaje, la configuración SMTP está funcionando correctamente.', 'solwed-wp'),
                    "\n\n",
                    "\n",
                    "\n",
                    $test_config['host'],
                    "\n",
                    $test_config['port'],
                    "\n",
                    $test_config['username'],
                    "\n",
                    strtoupper($test_config['encryption']),
                    "\n\n",
                    "\n"
                )
            );

            if ($test_email) {
                add_settings_error(
                    'solwed_messages',
                    'solwed_smtp_success',
                    sprintf(__('¡Test exitoso! Email de prueba enviado correctamente a %s.', 'solwed-wp'), $test_email_to),
                    'updated'
                );
            } else {
                add_settings_error(
                    'solwed_messages',
                    'solwed_smtp_error',
                    __('Conexión SMTP exitosa, pero falló el envío del email de prueba.', 'solwed-wp'),
                    'error'
                );
            }
        } else {
            add_settings_error(
                'solwed_messages',
                'solwed_smtp_error',
                sprintf(__('Error en el test: %s', 'solwed-wp'), $result['message']),
                'error'
            );
        }
    }
    
    /**
     * Manejar desbloqueo de IP
     */
    public function handle_unblock_ip(): void {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'solwed_unblock_ip')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'solwed-wp'));
        }

        $ip = sanitize_text_field($_GET['ip'] ?? '');
        if (empty($ip)) {
            wp_die(__('IP no válida.', 'solwed-wp'));
        }

        $security_module = solwed_wp()->get_module('security');
        if ($security_module && $security_module->unblock_ip($ip)) {
            $this->log_admin_action('ip_unblocked', ['ip' => $ip]);
            
            add_settings_error(
                'solwed_messages',
                'solwed_message',
                sprintf(__('IP %s desbloqueada correctamente.', 'solwed-wp'), $ip),
                'updated'
            );
        } else {
            add_settings_error(
                'solwed_messages',
                'solwed_message',
                sprintf(__('No se pudo desbloquear la IP %s.', 'solwed-wp'), $ip),
                'error'
            );
        }
        
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_redirect(admin_url('admin.php?page=solwed-wp-settings&tab=security'));
        exit;
    }
    
    /**
     * Manejar limpieza de logs
     */
    public function handle_clear_logs(): void {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'solwed_clear_logs')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'solwed-wp'));
        }

        global $wpdb;
        
        $log_type = $_GET['type'] ?? 'all';
        $success = false;
        
        switch ($log_type) {
            case 'security':
                $success = $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}solwed_security_logs") !== false;
                break;
                
            case 'smtp':
                $success = $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}solwed_smtp_logs") !== false;
                break;
                
            case 'all':
                $success1 = $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}solwed_security_logs") !== false;
                $success2 = $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}solwed_smtp_logs") !== false;
                $success = $success1 && $success2;
                break;
        }
        
        if ($success) {
            $this->log_admin_action('logs_cleared', ['type' => $log_type]);
            
            add_settings_error(
                'solwed_messages',
                'solwed_message',
                __('Logs eliminados correctamente.', 'solwed-wp'),
                'updated'
            );
        } else {
            add_settings_error(
                'solwed_messages',
                'solwed_message',
                __('No se pudieron eliminar los logs.', 'solwed-wp'),
                'error'
            );
        }
        
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_redirect(admin_url('admin.php?page=solwed-wp'));
        exit;
    }
    

    
    /**
     * Encriptar contraseña
     */
    private function encrypt_password(string $password): string {
        if (function_exists('openssl_encrypt')) {
            $key = wp_salt('auth');
            $iv = wp_generate_password(16, false);
            $encrypted = openssl_encrypt($password, 'AES-256-CBC', $key, 0, $iv);
            return base64_encode($iv . $encrypted);
        }
        
        // Fallback a base64 (no es seguro, pero mejor que texto plano)
        return base64_encode($password);
    }
    
    /**
     * Desencriptar contraseña
     */
    public static function decrypt_password(string $encrypted_password): string {
        if (empty($encrypted_password)) {
            return '';
        }
        
        if (function_exists('openssl_decrypt')) {
            $key = wp_salt('auth');
            $data = base64_decode($encrypted_password);
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
            return $decrypted !== false ? $decrypted : '';
        }
        
        // Fallback desde base64
        return base64_decode($encrypted_password);
    }
    
    /**
     * Log de acciones administrativas
     */
    private function log_admin_action(string $action, array $data = []): void {
        global $wpdb;
        
        $user = wp_get_current_user();
        
        $wpdb->insert(
            $wpdb->prefix . 'solwed_security_logs',
            [
                'action' => $action,
                'user_id' => $user->ID,
                'user_login' => $user->user_login,
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'details' => json_encode($data),
                'timestamp' => current_time('mysql')
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s']
        );
    }
    
    /**
     * Obtener IP del cliente
     */
    private function get_client_ip(): string {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }


    
    /**
     * Manejar guardado de código personalizado
     */
    public function handle_save_code(): void {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'] ?? '', 'solwed_save_code')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'solwed-wp'));
        }

        $custom_code = wp_unslash($_POST['custom_code'] ?? '');
        
        // Validar código PHP básico
        if (!empty($custom_code)) {
            // Verificar funciones peligrosas
            $dangerous_functions = [
                'exec', 'shell_exec', 'system', 'passthru', 'eval', 'file_get_contents',
                'file_put_contents', 'fwrite', 'unlink', 'rmdir', 'curl_exec'
            ];
            
            foreach ($dangerous_functions as $func) {
                if (strpos($custom_code, $func) !== false) {
                    wp_redirect(admin_url('admin.php?page=solwed-wp-settings&tab=code-editor&error=dangerous'));
                    exit;
                }
            }
            
            // Añadir etiquetas PHP para validación
            $test_code = '<?php ' . $custom_code;
            
            // Verificar sintaxis básica
            if (!$this->validate_php_syntax($test_code)) {
                wp_redirect(admin_url('admin.php?page=solwed-wp-settings&tab=code-editor&error=syntax'));
                exit;
            }
        }
        
        // Guardar código
        update_option(SOLWED_WP_PREFIX . 'custom_code', $custom_code);
        update_option(SOLWED_WP_PREFIX . 'custom_code_updated', date_i18n('d/m/Y H:i:s'));
        
        wp_redirect(admin_url('admin.php?page=solwed-wp-settings&tab=code-editor&updated=true'));
        exit;
    }
    
    /**
     * Validar sintaxis PHP básica
     */
    private function validate_php_syntax(string $code): bool {
        // Verificar sintaxis usando php -l si está disponible
        if (function_exists('exec')) {
            $temp_file = tempnam(sys_get_temp_dir(), 'solwed_php_check');
            file_put_contents($temp_file, $code);
            
            $output = [];
            $return_code = 0;
            exec("php -l $temp_file 2>&1", $output, $return_code);
            
            unlink($temp_file);
            
            return $return_code === 0;
        }
        
        // Verificación básica de tokens si exec no está disponible
        $tokens = token_get_all($code);
        $brace_count = 0;
        $paren_count = 0;
        
        foreach ($tokens as $token) {
            if ($token === '{') $brace_count++;
            if ($token === '}') $brace_count--;
            if ($token === '(') $paren_count++;
            if ($token === ')') $paren_count--;
        }
        
        return ($brace_count === 0 && $paren_count === 0);
    }
}

// Inicializar manejador de acciones administrativas
new Solwed_WP_Admin_Actions();