<?php
/**
 * Renderiza la pestaña de configuración de SMTP.
 */
if (!defined('ABSPATH')) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Clase base para las tablas de logs.
 */
class Solwed_SMTP_List_Table extends WP_List_Table {
	public function __construct( $args = [] ) {
		parent::__construct(
			[
				'singular' => 'log',
				'plural'   => 'logs',
				'ajax'     => false,
			]
		);
	}

	public function no_items() {
		_e( 'No hay registros disponibles.', 'solwed-wp' );
	}

	public function get_columns() {
		return [];
	}

	public function prepare_items() {
		$this->_column_headers = [ $this->get_columns(), [], [] ];
	}

	protected function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '–';
	}
}

/**
 * Tabla para los logs de email en SMTP.
 */
class Solwed_SMTP_Email_Logs_List_Table extends Solwed_SMTP_List_Table {
	public function __construct() {
		parent::__construct(
			[
				'singular' => __( 'Email Log', 'solwed-wp' ),
				'plural'   => __( 'Email Logs', 'solwed-wp' ),
				'ajax'     => false,
			]
		);
	}

	public function get_columns() {
		return [
			'timestamp'     => __( 'Fecha', 'solwed-wp' ),
			'email_to'      => __( 'Para', 'solwed-wp' ),
			'subject'       => __( 'Asunto', 'solwed-wp' ),
			'status'        => __( 'Estado', 'solwed-wp' ),
			'error_message' => __( 'Error', 'solwed-wp' ),
		];
	}

	public function prepare_items() {
		global $wpdb;

		$this->_column_headers = [ $this->get_columns(), [], [] ];
		$table_name            = $wpdb->prefix . 'solwed_email_logs';
		$per_page              = 10; // Menos elementos para la pestaña SMTP
		$current_page          = $this->get_pagenum();
		$total_items           = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table_name}" );

		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $per_page,
			]
		);

		$offset = ( $current_page - 1 ) * $per_page;

		$this->items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} ORDER BY timestamp DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);
	}

	protected function column_timestamp( $item ) {
		return esc_html( date_i18n( 'd/m/Y H:i:s', strtotime( $item['timestamp'] ) ) );
	}

	protected function column_subject( $item ) {
		return esc_html( wp_trim_words( $item['subject'], 10 ) );
	}

	protected function column_status( $item ) {
		$status_class = 'sent' === $item['status'] ? 'sent' : 'failed';
		$status_label = 'sent' === $item['status'] ? __( 'Enviado', 'solwed-wp' ) : __( 'Error', 'solwed-wp' );

		return sprintf( '<span class="solwed-status-badge %s">%s</span>', esc_attr( $status_class ), esc_html( $status_label ) );
	}

	protected function column_error_message( $item ) {
		if ( 'failed' === $item['status'] && ! empty( $item['error_message'] ) ) {
			return sprintf(
				'<span class="dashicons dashicons-warning"></span><div class="solwed-error-tooltip">%s</div>',
				esc_html( $item['error_message'] )
			);
		}
		return '–';
	}
}

/**
 * Clase SMTP Optimizada para PHP 8.1+ - Solwed WP Plugin
 * 
 * @package SolwedWP
 * @since 2.0.0
 * @author Solwed
 */
final class Solwed_SMTP_Unified {
    
    /**
     * Configuración SMTP
     */
    private array $settings;
    
    /**
     * Email por defecto para pruebas
     */
    private const DEFAULT_TEST_EMAIL = 'soporte@solwed.es';
    
    /**
     * Email por defecto del remitente
     */
    private const DEFAULT_FROM_EMAIL = 'hola@solwed.es';
    
    /**
     * Timeout por defecto para conexiones SMTP
     */
    private const DEFAULT_TIMEOUT = 30;
    
    /**
     * Puertos SMTP estándar
     */
    private const SMTP_PORTS = [
        'tls' => 587,
        'ssl' => 465,
        'none' => 25
    ];

    public function __construct() {
        $this->settings = $this->load_settings();
    }

    /**
     * Cargar configuración SMTP de forma optimizada
     */
    private function load_settings(): array {
        $option_keys = [
            'smtp_enabled',
            'smtp_host',
            'smtp_port',
            'smtp_username',
            'smtp_password',
            'smtp_encryption',
            'smtp_from_name',
            'smtp_from_email'
        ];

        // Prefijar todas las claves para la consulta
        $prefixed_keys = array_map(fn($key) => SOLWED_WP_PREFIX . $key, $option_keys);

        // Obtener todas las opciones en una sola consulta
        $all_options = solwed_get_option_group($prefixed_keys);

        // Mapear los valores recuperados
        $options = [];
        foreach ($option_keys as $key) {
            $prefixed_key = SOLWED_WP_PREFIX . $key;
            $options[$key] = $all_options[$prefixed_key] ?? null;
        }

        return [
            'enabled' => ($options['smtp_enabled'] ?? '1') === '1',
            'host' => trim($options['smtp_host'] ?? 'mail.solwed.es'),
            'port' => (int) ($options['smtp_port'] ?? self::SMTP_PORTS['tls']),
            'username' => trim($options['smtp_username'] ?? 'hola@solwed.es'),
            'password' => $options['smtp_password'] ?? '@Solwed8.',
            'encryption' => $options['smtp_encryption'] ?? 'tls',
            'from_name' => trim($options['smtp_from_name'] ?? get_bloginfo('name')),
            'from_email' => trim($options['smtp_from_email'] ?? '') // Vacío por defecto como solicitado
        ];
    }

    /**
     * Inicializar hooks de WordPress
     */
    public function init(): void {
        // Primero asegurar que las funciones básicas estén disponibles
        $this->ensure_basic_functions();
        
        if (!$this->settings['enabled'] || !$this->is_configured()) {
            return;
        }
        
        add_action('phpmailer_init', [$this, 'configure_phpmailer'], 10, 1);
        add_filter('wp_mail_from', [$this, 'set_from_email'], 20);
        add_filter('wp_mail_from_name', [$this, 'set_from_name'], 20);
        add_action('wp_mail_failed', [$this, 'log_failed_email']);
        add_action('wp_mail', [$this, 'log_sent_email']);

        // Hook para el test de AJAX
        add_action('wp_ajax_test_smtp_ajax', [$this, 'handle_test_ajax']);
        add_action('wp_ajax_diagnose_mail_ajax', [$this, 'handle_diagnose_ajax']);
        add_action('wp_ajax_auto_repair_mail_ajax', [$this, 'handle_auto_repair_ajax']);
        add_action('wp_ajax_quick_mail_check_ajax', [$this, 'handle_quick_mail_check_ajax']);
        add_action('wp_ajax_force_load_phpmailer_ajax', [$this, 'handle_force_load_phpmailer_ajax']);
    }

    /**
     * Asegurar que las funciones básicas estén disponibles
     */
    private function ensure_basic_functions(): void {
        // Cargar pluggable.php si wp_mail no existe
        if (!function_exists('wp_mail')) {
            $pluggable_file = ABSPATH . WPINC . '/pluggable.php';
            if (file_exists($pluggable_file)) {
                require_once $pluggable_file;
            }
        }

        // Intentar cargar PHPMailer de manera preventiva
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer') && !class_exists('PHPMailer')) {
            $this->load_phpmailer();
        }
    }

    /**
     * Verificar si SMTP está habilitado
     */
    public function is_enabled(): bool {
        return $this->settings['enabled'];
    }

    /**
     * Verificar si SMTP está correctamente configurado
     */
    public function is_configured(): bool {
        return !empty($this->settings['host']) &&
               !empty($this->settings['username']) &&
               !empty($this->settings['password']) &&
               filter_var($this->settings['from_email'], FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Configura PHPMailer con los ajustes SMTP.
     *
     * @param PHPMailer $phpmailer Instancia de PHPMailer.
     */
    public function configure_phpmailer( $phpmailer ): void {
        $phpmailer->isSMTP();
        $phpmailer->Host       = $this->settings['host'];
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Port       = $this->settings['port'];
        $phpmailer->Username   = $this->settings['username'];
        $phpmailer->Password   = $this->settings['password'];
        $phpmailer->SMTPSecure = in_array($this->settings['encryption'], ['ssl', 'tls']) ? $this->settings['encryption'] : false;
        $phpmailer->Timeout    = self::DEFAULT_TIMEOUT;
        $phpmailer->CharSet    = 'UTF-8';
        
        // Configurar opciones SMTP más robustas
        $phpmailer->SMTPOptions = [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'cafile' => ABSPATH . WPINC . '/certificates/ca-bundle.crt'
            ],
        ];

        // En entorno de desarrollo, relajar las verificaciones SSL
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $phpmailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
        }
    }

    /**
     * Establece el email del remitente.
     */
    public function set_from_email( $original_email ): string {
        return $this->settings['from_email'];
    }

    /**
     * Establece el nombre del remitente.
     */
    public function set_from_name( $original_name ): string {
        return $this->settings['from_name'];
    }

    /**
     * Maneja la petición AJAX para probar la conexión SMTP.
     */
    public function handle_test_ajax(): void {
        if (!check_ajax_referer('solwed_smtp_test_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Error de seguridad.', 'solwed-wp')]);
            return;
        }

        $test_data = wp_unslash($_POST);
        $result = $this->test_connection($test_data);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Maneja la petición AJAX para diagnóstico de correo
     */
    public function handle_diagnose_ajax(): void {
        if (!check_ajax_referer('solwed_mail_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Error de seguridad.', 'solwed-wp')]);
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sin permisos suficientes.', 'solwed-wp')]);
            return;
        }

        $diagnosis = $this->diagnose_mail_system();
        wp_send_json_success(['diagnosis' => $diagnosis]);
    }

    /**
     * Maneja la petición AJAX para auto-reparación
     */
    public function handle_auto_repair_ajax(): void {
        if (!check_ajax_referer('solwed_mail_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Error de seguridad.', 'solwed-wp')]);
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sin permisos suficientes.', 'solwed-wp')]);
            return;
        }

        $repair_result = $this->auto_repair_mail_config();
        
        if ($repair_result['success']) {
            wp_send_json_success($repair_result);
        } else {
            wp_send_json_error($repair_result);
        }
    }

    /**
     * Maneja la petición AJAX para verificación rápida de wp_mail
     */
    public function handle_quick_mail_check_ajax(): void {
        if (!check_ajax_referer('solwed_mail_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Error de seguridad.', 'solwed-wp')]);
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sin permisos suficientes.', 'solwed-wp')]);
            return;
        }

        // Verificar wp_mail de manera básica
        if (!function_exists('wp_mail')) {
            // Intentar cargar pluggable.php
            $pluggable_file = ABSPATH . WPINC . '/pluggable.php';
            if (file_exists($pluggable_file)) {
                require_once $pluggable_file;
            }
        }

        if (!function_exists('wp_mail')) {
            wp_send_json_error([
                'message' => __('wp_mail() no está disponible. Archivos de WordPress faltantes o dañados.', 'solwed-wp')
            ]);
            return;
        }

        // Prueba simple sin configuración SMTP - SIEMPRE usar soporte@solwed.es
        $test_email = 'soporte@solwed.es';
        $admin_email = get_option('admin_email', 'admin@' . parse_url(home_url(), PHP_URL_HOST));
        
        $subject = 'Prueba wp_mail() - Solwed WP - ' . date('d/m/Y H:i:s');
        $message = "✅ PRUEBA EXITOSA de wp_mail()\n\n";
        $message .= "Esta es una prueba básica de la función wp_mail() de WordPress.\n\n";
        $message .= "Email de admin configurado: $admin_email\n";
        $message .= "Email de prueba: $test_email\n\n";
        $message .= "Detalles:\n";
        $message .= "- Sitio: " . get_bloginfo('url') . "\n";
        $message .= "- Fecha: " . date('d/m/Y H:i:s') . "\n";
        $message .= "- PHP Version: " . PHP_VERSION . "\n";
        $message .= "- WordPress Version: " . get_bloginfo('version') . "\n\n";
        $message .= "Si recibes este email, wp_mail() funciona correctamente.\n\n";
        $message .= "Plugin Solwed WP";

        // Usar wp_mail sin configuración SMTP personalizada
        remove_all_actions('phpmailer_init'); // Quitar configuraciones SMTP temporalmente
        
        $result = wp_mail($test_email, $subject, $message);

        if ($result) {
            wp_send_json_success([
                'message' => sprintf(
                    __('✅ wp_mail() funciona correctamente. Email de prueba enviado a %s', 'solwed-wp'), 
                    $test_email
                )
            ]);
        } else {
            // Verificar errores globales
            global $phpmailer;
            $error_info = '';
            if (isset($phpmailer) && !empty($phpmailer->ErrorInfo)) {
                $error_info = ' Error: ' . $phpmailer->ErrorInfo;
            }
            
            wp_send_json_error([
                'message' => __('❌ wp_mail() falló al enviar el email de prueba.', 'solwed-wp') . $error_info . sprintf(' (Destinatario: %s)', $test_email)
            ]);
        }
    }

    /**
     * Maneja la petición AJAX para forzar carga de PHPMailer
     */
    public function handle_force_load_phpmailer_ajax(): void {
        if (!check_ajax_referer('solwed_mail_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Error de seguridad.', 'solwed-wp')]);
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sin permisos suficientes.', 'solwed-wp')]);
            return;
        }

        $details = [];
        $success = false;

        // Intentar cargar PHPMailer
        $load_result = $this->load_phpmailer();
        if ($load_result) {
            $details[] = 'PHPMailer cargado exitosamente';
            $success = true;
        } else {
            $details[] = 'No se pudo cargar PHPMailer automáticamente';
        }

        // Verificar estado después del intento
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $details[] = 'PHPMailer moderno disponible (con namespaces)';
            $success = true;
        }

        if (class_exists('PHPMailer')) {
            $details[] = 'PHPMailer clásico disponible';
            $success = true;
        }

        // Forzar carga usando ensure_mail_functions
        $ensure_result = $this->ensure_mail_functions();
        if (!empty($ensure_result['actions_taken'])) {
            $details = array_merge($details, $ensure_result['actions_taken']);
            $success = true;
        }

        if ($success) {
            wp_send_json_success([
                'message' => __('Carga de PHPMailer completada', 'solwed-wp'),
                'details' => $details
            ]);
        } else {
            wp_send_json_error([
                'message' => __('No se pudo cargar PHPMailer. Puede haber un problema con la instalación de WordPress.', 'solwed-wp'),
                'details' => $details
            ]);
        }
    }

    /**
     * Realiza una prueba de conexión SMTP.
     *
     * @param array $data Datos para la prueba (email, configuraciones, etc.).
     * @return array Resultado de la prueba.
     */
    public function test_connection( array $data ): array {
        // Asegurar que las funciones básicas estén cargadas
        $this->ensure_basic_functions();
        
        // Verificar que wp_mail esté disponible
        if (!function_exists('wp_mail')) {
            return [
                'success' => false,
                'message' => __('wp_mail() no está disponible. Usar el "Test Independiente" para diagnosticar.', 'solwed-wp')
            ];
        }

        // Intentar cargar PHPMailer usando los métodos de WordPress
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer') && !class_exists('PHPMailer')) {
            $load_success = $this->load_phpmailer();
            if (!$load_success) {
                return [
                    'success' => false,
                    'message' => __('PHPMailer no está disponible. WordPress puede estar dañado.', 'solwed-wp')
                ];
            }
        }

        $to_email = !empty($data['test_email']) && filter_var($data['test_email'], FILTER_VALIDATE_EMAIL)
            ? $data['test_email']
            : self::DEFAULT_TEST_EMAIL;

        try {
            // Crear instancia de PHPMailer de manera segura
            $mail = null;
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            } elseif (class_exists('PHPMailer')) {
                $mail = new PHPMailer(true);
            } else {
                throw new Exception('No se pudo crear instancia de PHPMailer');
            }

            // Usar datos del POST si existen, si no, los guardados
            $host = !empty($data['smtp_host']) ? $data['smtp_host'] : $this->settings['host'];
            $port = !empty($data['smtp_port']) ? (int)$data['smtp_port'] : $this->settings['port'];
            $username = !empty($data['smtp_username']) ? $data['smtp_username'] : $this->settings['username'];
            $password = !empty($data['smtp_password']) ? $data['smtp_password'] : $this->settings['password'];
            $encryption = !empty($data['smtp_encryption']) ? $data['smtp_encryption'] : $this->settings['encryption'];
            $from_name = !empty($data['smtp_from_name']) ? $data['smtp_from_name'] : $this->settings['from_name'];
            $from_email = !empty($data['smtp_from_email']) ? $data['smtp_from_email'] : $this->settings['from_email'];

            // Configurar PHPMailer
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->Port = $port;
            $mail->SMTPSecure = in_array($encryption, ['ssl', 'tls']) ? $encryption : false;
            $mail->Timeout = self::DEFAULT_TIMEOUT;
            $mail->CharSet = 'UTF-8';
            
            // Configuración SSL simplificada
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];

            // Para debugging básico
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $mail->SMTPDebug = 1; // Errors only
            }
            
            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($to_email);
            
            $mail->isHTML(true);
            $mail->Subject = 'Prueba SMTP - Solwed WP - ' . date('d/m/Y H:i:s');
            
            $body_html = sprintf(
                '<h2>✅ Prueba de SMTP Exitosa</h2>
                <p>Este es un correo de prueba para verificar la configuración de SMTP.</p>
                <p><strong>Configuración utilizada:</strong></p>
                <ul>
                    <li>Servidor: %s</li>
                    <li>Puerto: %s</li>
                    <li>Cifrado: %s</li>
                    <li>Usuario: %s</li>
                </ul>
                <p>Fecha: %s</p>
                <p><em>Plugin Solwed WP</em></p>',
                esc_html($host),
                esc_html($port),
                esc_html($encryption),
                esc_html($username),
                date('d/m/Y H:i:s')
            );
            
            $mail->Body = $body_html;
            $mail->AltBody = strip_tags($body_html);

            $mail->send();

            update_option(SOLWED_WP_PREFIX . 'smtp_last_test', current_time('mysql'));
            update_option(SOLWED_WP_PREFIX . 'smtp_last_test_status', 'success');

            return ['success' => true, 'message' => __('Correo de prueba enviado correctamente a ', 'solwed-wp') . $to_email];

        } catch (Exception $e) {
            update_option(SOLWED_WP_PREFIX . 'smtp_last_test', current_time('mysql'));
            update_option(SOLWED_WP_PREFIX . 'smtp_last_test_status', 'error');
            
            // Proporcionar un mensaje de error más detallado
            $error_info = isset($mail) && isset($mail->ErrorInfo) && !empty($mail->ErrorInfo) 
                ? $mail->ErrorInfo 
                : $e->getMessage();
                
            $error_message = sprintf(
                __('El correo no pudo ser enviado. Error: %s', 'solwed-wp'),
                $error_info
            );
            return ['success' => false, 'message' => $error_message];
        }
    }

    /**
     * Obtener estadísticas con información mejorada
     */
    public function get_stats(): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'solwed_email_logs';
        
        $last_test = get_option(SOLWED_WP_PREFIX . 'smtp_last_test');
        $last_test_status = get_option(SOLWED_WP_PREFIX . 'smtp_last_test_status');

        $total_sent = (int) $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE status = 'sent'");
        $total_failed = (int) $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE status = 'failed'");
        $recent_emails = $wpdb->get_results("SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT 5");

        return [
            'enabled' => $this->settings['enabled'],
            'configured' => $this->is_configured(),
            'last_test' => $last_test ? date_i18n('d/m/Y H:i', strtotime($last_test)) : 'Nunca',
            'last_test_status' => $last_test_status,
            'status' => $this->get_status_text(),
            'host' => $this->settings['host'],
            'port' => $this->settings['port'],
            'username' => $this->settings['username'],
            'password' => $this->settings['password'],
            'encryption' => $this->settings['encryption'],
            'from_email' => $this->settings['from_email'],
            'total_sent' => $total_sent,
            'total_failed' => $total_failed,
            'recent_emails' => $recent_emails,
            'wp_mail_config' => $this->get_wp_mail_config()
        ];
    }

    /**
     * Obtener configuración actual de wp_mail - VERSIÓN SEGURA
     */
    private function get_wp_mail_config(): array {
        // Verificar si PHPMailer está disponible de manera segura
        $phpmailer_version = 'No disponible';
        
        try {
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                $phpmailer_version = 'PHPMailer v' . PHPMailer\PHPMailer\PHPMailer::VERSION;
            } elseif (class_exists('PHPMailer')) {
                // Versión antigua de PHPMailer
                $reflection = new ReflectionClass('PHPMailer');
                if ($reflection->hasConstant('VERSION')) {
                    $phpmailer_version = 'PHPMailer v' . PHPMailer::VERSION;
                } else {
                    $phpmailer_version = 'PHPMailer (versión clásica)';
                }
            } else {
                // Intentar cargar y verificar
                $this->load_phpmailer();
                if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                    $phpmailer_version = 'PHPMailer v' . PHPMailer\PHPMailer\PHPMailer::VERSION;
                } elseif (class_exists('PHPMailer')) {
                    $phpmailer_version = 'PHPMailer (cargado dinámicamente)';
                }
            }
        } catch (Exception $e) {
            $phpmailer_version = 'Error al verificar: ' . $e->getMessage();
        }

        $wp_config = [
            'admin_email' => get_option('admin_email', 'No configurado'),
            'blogname' => get_option('blogname', 'Sin nombre'),
            'mailserver_url' => get_option('mailserver_url', ''),
            'mailserver_port' => get_option('mailserver_port', ''),
            'mailserver_login' => get_option('mailserver_login', ''),
            'mailserver_pass' => get_option('mailserver_pass') ? '***' : '',
            'use_smtp' => defined('SMTP') ? 'Definido en wp-config.php' : 'No definido',
            'php_mailer_type' => $phpmailer_version,
            'wp_mail_smtp_plugins' => $this->detect_smtp_plugins_safe()
        ];
        
        return $wp_config;
    }
    
    /**
     * Detectar plugins SMTP de manera segura
     */
    private function detect_smtp_plugins_safe(): array {
        try {
            return $this->detect_smtp_plugins();
        } catch (Exception $e) {
            return [['name' => 'Error al detectar plugins: ' . $e->getMessage(), 'active' => false, 'version' => '']];
        }
    }
    
    /**
     * Detectar otros plugins de SMTP instalados
     */
    private function detect_smtp_plugins(): array {
        $smtp_plugins = [];
        $all_plugins = get_plugins();
        
        $smtp_keywords = ['smtp', 'mail', 'wp-mail', 'email'];
        
        foreach ($all_plugins as $plugin_path => $plugin_data) {
            $plugin_name_lower = strtolower($plugin_data['Name']);
            $plugin_desc_lower = strtolower($plugin_data['Description']);
            
            foreach ($smtp_keywords as $keyword) {
                if (strpos($plugin_name_lower, $keyword) !== false || 
                    strpos($plugin_desc_lower, $keyword) !== false) {
                    $smtp_plugins[] = [
                        'name' => $plugin_data['Name'],
                        'active' => is_plugin_active($plugin_path),
                        'version' => $plugin_data['Version']
                    ];
                    break;
                }
            }
        }
        
        return $smtp_plugins;
    }

    /**
     * Obtener texto de estado
     */
    private function get_status_text(): string {
        if (!$this->settings['enabled']) {
            return 'Deshabilitado';
        }

        if (!$this->is_configured()) {
            return 'No configurado';
        }

        return 'Activo y configurado';
    }

    /**
     * Verificar y corregir configuración de WordPress para correos
     */
    public function validate_wp_mail_config(): array {
        $issues = [];
        $fixes = [];

        // Verificar admin_email
        $admin_email = get_option('admin_email');
        if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $issues[] = 'Email de administrador no válido';
            $fixes[] = 'Actualizar admin_email en Configuración > General';
        }

        // Verificar función wp_mail
        if (!function_exists('wp_mail')) {
            $issues[] = 'Función wp_mail no disponible';
            $fixes[] = 'WordPress Core podría estar dañado - reinstalar WordPress';
        }

        // Verificar PHPMailer
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer') && !class_exists('PHPMailer')) {
            $issues[] = 'PHPMailer no está disponible';
            $fixes[] = 'Intentar cargar PHPMailer manualmente';
        }

        // Verificar configuración de PHP mail
        if (!ini_get('sendmail_path') && !ini_get('SMTP')) {
            $issues[] = 'PHP no está configurado para enviar emails';
        }

        return [
            'issues' => $issues,
            'fixes' => $fixes,
            'healthy' => empty($issues)
        ];
    }

    /**
     * Forzar la carga de PHPMailer y wp_mail
     */
    public function ensure_mail_functions(): array {
        $status = [
            'wp_mail_loaded' => false,
            'phpmailer_loaded' => false,
            'actions_taken' => [],
            'errors' => []
        ];

        // 1. Verificar y cargar wp_mail si no existe
        if (!function_exists('wp_mail')) {
            $pluggable_file = ABSPATH . WPINC . '/pluggable.php';
            if (file_exists($pluggable_file)) {
                include_once $pluggable_file;
                $status['actions_taken'][] = 'Cargado pluggable.php para wp_mail()';
            } else {
                $status['errors'][] = 'No se pudo encontrar pluggable.php';
            }
        }
        $status['wp_mail_loaded'] = function_exists('wp_mail');

        // 2. Verificar y cargar PHPMailer
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer') && !class_exists('PHPMailer')) {
            $this->load_phpmailer();
            $status['actions_taken'][] = 'Intentado cargar PHPMailer';
        }
        $status['phpmailer_loaded'] = class_exists('PHPMailer\PHPMailer\PHPMailer') || class_exists('PHPMailer');

        return $status;
    }

    /**
     * Cargar PHPMailer de forma robusta - VERSIÓN MEJORADA
     */
    private function load_phpmailer(): bool {
        // Si ya está cargado, no hacer nada
        if (class_exists('PHPMailer\PHPMailer\PHPMailer') || class_exists('PHPMailer')) {
            return true;
        }

        $loaded = false;

        // Método 1: WordPress 6.0+ (Moderno con namespaces)
        $modern_files = [
            ABSPATH . WPINC . '/PHPMailer/PHPMailer.php',
            ABSPATH . WPINC . '/PHPMailer/SMTP.php',
            ABSPATH . WPINC . '/PHPMailer/Exception.php'
        ];

        $all_modern_exist = true;
        foreach ($modern_files as $file) {
            if (!file_exists($file)) {
                $all_modern_exist = false;
                break;
            }
        }

        if ($all_modern_exist) {
            try {
                foreach ($modern_files as $file) {
                    require_once $file;
                }
                $loaded = true;
            } catch (Exception $e) {
                // Continuar con el siguiente método
            }
        }

        // Método 2: WordPress 5.x (Clásico)
        if (!$loaded) {
            $classic_files = [
                ABSPATH . WPINC . '/class-phpmailer.php',
                ABSPATH . WPINC . '/class-smtp.php'
            ];

            $all_classic_exist = true;
            foreach ($classic_files as $file) {
                if (!file_exists($file)) {
                    $all_classic_exist = false;
                    break;
                }
            }

            if ($all_classic_exist) {
                try {
                    foreach ($classic_files as $file) {
                        require_once $file;
                    }
                    $loaded = true;
                } catch (Exception $e) {
                    // Continuar con el siguiente método
                }
            }
        }

        // Método 3: Usar wp_mail para forzar la carga (si existe)
        if (!$loaded && function_exists('wp_mail')) {
            try {
                // wp_mail automáticamente inicializa PHPMailer
                // Simular un envío que falle rápidamente pero cargue las clases
                wp_mail('test@invalid', 'test', 'test');
                $loaded = class_exists('PHPMailer\PHPMailer\PHPMailer') || class_exists('PHPMailer');
            } catch (Exception $e) {
                // No pasa nada, es esperado que falle
            }
        }

        return $loaded;
    }

    /**
     * Diagnóstico completo del sistema de correo
     */
    public function diagnose_mail_system(): array {
        $diagnosis = [
            'wordpress_config' => [],
            'php_config' => [],
            'server_config' => [],
            'recommendations' => []
        ];

        // Diagnóstico de WordPress
        $diagnosis['wordpress_config'] = [
            'wp_version' => get_bloginfo('version'),
            'wp_mail_exists' => function_exists('wp_mail'),
            'phpmailer_modern' => class_exists('PHPMailer\PHPMailer\PHPMailer'),
            'phpmailer_classic' => class_exists('PHPMailer'),
            'admin_email' => get_option('admin_email'),
            'admin_email_valid' => filter_var(get_option('admin_email'), FILTER_VALIDATE_EMAIL) !== false,
            'plugins_loaded' => did_action('plugins_loaded'),
            'wp_loaded' => did_action('wp_loaded')
        ];

        // Diagnóstico de PHP
        $diagnosis['php_config'] = [
            'php_version' => PHP_VERSION,
            'mail_function' => function_exists('mail'),
            'sendmail_path' => ini_get('sendmail_path'),
            'smtp_server' => ini_get('SMTP'),
            'smtp_port' => ini_get('smtp_port'),
            'openssl' => extension_loaded('openssl'),
            'sockets' => extension_loaded('sockets')
        ];

        // Diagnóstico del servidor
        $diagnosis['server_config'] = [
            'os' => PHP_OS,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Desconocido'
        ];

        // Generar recomendaciones
        if (!$diagnosis['wordpress_config']['wp_mail_exists']) {
            $diagnosis['recommendations'][] = 'wp_mail() no está disponible - verificar instalación de WordPress';
        }

        if (!$diagnosis['wordpress_config']['phpmailer_modern'] && !$diagnosis['wordpress_config']['phpmailer_classic']) {
            $diagnosis['recommendations'][] = 'PHPMailer no está disponible - verificar archivos de WordPress';
        }

        if (!$diagnosis['wordpress_config']['admin_email_valid']) {
            $diagnosis['recommendations'][] = 'Configurar un email de administrador válido en Configuración > General';
        }

        if (!$diagnosis['php_config']['openssl']) {
            $diagnosis['recommendations'][] = 'Extensión OpenSSL no disponible - contactar con el proveedor de hosting';
        }

        if (empty($diagnosis['php_config']['sendmail_path']) && empty($diagnosis['php_config']['smtp_server'])) {
            $diagnosis['recommendations'][] = 'PHP no tiene configuración de correo - usar SMTP es recomendado';
        }

        return $diagnosis;
    }

    /**
     * Auto-reparar configuración de correo básica
     */
    public function auto_repair_mail_config(): array {
        $repairs = [];
        $errors = [];

        // 1. Intentar cargar funciones de correo
        $mail_status = $this->ensure_mail_functions();
        if (!empty($mail_status['actions_taken'])) {
            $repairs = array_merge($repairs, $mail_status['actions_taken']);
        }
        if (!empty($mail_status['errors'])) {
            $errors = array_merge($errors, $mail_status['errors']);
        }

        // 2. Verificar/corregir admin_email
        $admin_email = get_option('admin_email');
        if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $new_email = 'admin@' . parse_url(home_url(), PHP_URL_HOST);
            if (filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                update_option('admin_email', $new_email);
                $repairs[] = "Admin email corregido a: $new_email";
            } else {
                $errors[] = 'No se pudo auto-corregir el admin email';
            }
        }

        // 3. Verificar configuración del sitio
        $blogname = get_option('blogname');
        if (empty($blogname) || $blogname === 'Mi sitio') {
            $site_url = parse_url(home_url(), PHP_URL_HOST);
            update_option('blogname', ucfirst($site_url));
            $repairs[] = "Nombre del sitio actualizado a: " . ucfirst($site_url);
        }

        return [
            'repairs_made' => $repairs,
            'errors' => $errors,
            'success' => empty($errors)
        ];
    }

    /**
     * Registrar email enviado exitosamente
     */
    public function log_sent_email($mail_data): void {
        if (!is_array($mail_data) || empty($mail_data)) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'solwed_email_logs';

        $to = is_array($mail_data['to']) ? implode(', ', $mail_data['to']) : $mail_data['to'];

        $wpdb->insert(
            $table_name,
            [
                'timestamp' => current_time('mysql'),
                'email_to' => $to,
                'subject' => $mail_data['subject'] ?? '',
                'status' => 'sent',
                'error_message' => null
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Registrar email fallido
     */
    public function log_failed_email($wp_error): void {
        if (!is_wp_error($wp_error)) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'solwed_email_logs';

        $error_data = $wp_error->get_error_data();
        $to = '';
        $subject = '';

        if (isset($error_data['to'])) {
            $to = is_array($error_data['to']) ? implode(', ', $error_data['to']) : $error_data['to'];
        }

        if (isset($error_data['subject'])) {
            $subject = $error_data['subject'];
        }

        $wpdb->insert(
            $table_name,
            [
                'timestamp' => current_time('mysql'),
                'email_to' => $to,
                'subject' => $subject,
                'status' => 'failed',
                'error_message' => $wp_error->get_error_message()
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Guardar configuración SMTP
     */
    public function save_settings(array $data): array {
        try {
            // Validar y sanitizar los datos
            $validated_data = $this->validate_smtp_settings($data);
            
            if (!$validated_data['success']) {
                return [
                    'success' => false,
                    'message' => 'Error en la validación: ' . implode(', ', $validated_data['errors'])
                ];
            }

            // Guardar las opciones
            $settings = $validated_data['data'];
            
            foreach ($settings as $key => $value) {
                update_option("solwed_smtp_{$key}", $value);
            }

            // Recargar configuraciones
            $this->load_settings();

            return [
                'success' => true,
                'message' => 'Configuración SMTP guardada correctamente'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al guardar la configuración: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validar configuración SMTP
     */
    private function validate_smtp_settings(array $data): array {
        $errors = [];
        $validated = [];

        // Validar habilitado
        $validated['enabled'] = isset($data['smtp_enabled']) ? (bool)$data['smtp_enabled'] : false;

        // Validar servidor SMTP
        if (!empty($data['smtp_host'])) {
            $validated['host'] = sanitize_text_field($data['smtp_host']);
        } else {
            $errors[] = 'Servidor SMTP es requerido';
        }

        // Validar puerto
        $port = intval($data['smtp_port'] ?? 587);
        if ($port > 0 && $port <= 65535) {
            $validated['port'] = $port;
        } else {
            $errors[] = 'Puerto SMTP debe estar entre 1 y 65535';
        }

        // Validar usuario
        if (!empty($data['smtp_username'])) {
            $validated['username'] = sanitize_text_field($data['smtp_username']);
        } else {
            $errors[] = 'Usuario SMTP es requerido';
        }

        // Validar contraseña (si se proporciona)
        if (!empty($data['smtp_password'])) {
            $validated['password'] = $data['smtp_password']; // No sanitizar contraseña
        }

        // Validar seguridad
        $security = sanitize_text_field($data['smtp_security'] ?? 'tls');
        if (in_array($security, ['none', 'ssl', 'tls'])) {
            $validated['security'] = $security;
        } else {
            $validated['security'] = 'tls';
        }

        // Validar email del remitente
        $from_email = sanitize_email($data['smtp_from_email'] ?? self::DEFAULT_FROM_EMAIL);
        if (filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
            $validated['from_email'] = $from_email;
        } else {
            $validated['from_email'] = self::DEFAULT_FROM_EMAIL;
            $errors[] = 'Email del remitente no válido, usando ' . self::DEFAULT_FROM_EMAIL;
        }

        // Validar nombre del remitente
        $validated['from_name'] = sanitize_text_field($data['smtp_from_name'] ?? get_bloginfo('name'));

        return [
            'success' => empty($errors),
            'errors' => $errors,
            'data' => $validated
        ];
    }
}

/**
 * Función para obtener una única instancia de la clase de utilidad.
 * Esto es útil si necesitas acceder a las opciones desde fuera.
 */
function solwed_get_option_group(array $keys): array {
    global $wpdb;
    $placeholders = implode(', ', array_fill(0, count($keys), '%s'));
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ($placeholders)",
            $keys
        ),
        OBJECT_K
    );

    $options = [];
    foreach ($keys as $key) {
        $options[$key] = isset($results[$key]) ? maybe_unserialize($results[$key]->option_value) : null;
    }
    return $options;
}


function render_smtp_tab() {
    $smtp = solwed_wp()->get_module('smtp');
    if (!$smtp) {
        echo '<div class="notice notice-error"><p>' . __('El módulo SMTP no está disponible.', 'solwed-wp') . '</p></div>';
        return;
    }

    $smtp_enabled = $smtp->is_enabled();
    $stats = $smtp->get_stats();
    
    $smtp_settings = [
        'host' => get_option(SOLWED_WP_PREFIX . 'smtp_host', 'mail.solwed.es'),
        'port' => get_option(SOLWED_WP_PREFIX . 'smtp_port', 587),
        'username' => get_option(SOLWED_WP_PREFIX . 'smtp_username', 'hola@solwed.es'),
        'password' => get_option(SOLWED_WP_PREFIX . 'smtp_password', '@Solwed8.'),
        'encryption' => get_option(SOLWED_WP_PREFIX . 'smtp_encryption', 'tls'),
        'from_name' => get_option(SOLWED_WP_PREFIX . 'smtp_from_name', get_bloginfo('name')),
        'from_email' => get_option(SOLWED_WP_PREFIX . 'smtp_from_email', '') // Vacío por defecto como solicitado
    ];
    
    wp_enqueue_script('jquery');
    ?>

    <style>
    /* Layout específico para el panel SMTP - Solo lo que no está en admin.css */
    .solwed-smtp-container {
        display: flex;
        gap: 20px;
        margin: 20px 0;
    }
    .solwed-smtp-main {
        flex: 2;
        max-width: 800px;
    }
    .solwed-smtp-sidebar {
        flex: 1;
        min-width: 300px;
        max-width: 350px;
    }
    .solwed-sidebar-panel {
        position: sticky;
        top: 32px;
        margin-bottom: 20px;
    }
    .solwed-sidebar-panel h3 {
        margin: 0 0 15px 0;
        padding-bottom: 8px;
        border-bottom: 1px solid #dcdcde;
    }
    .stats-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    .stats-table th, .stats-table td {
        padding: 8px;
        text-align: left;
        border-bottom: 1px solid #ddd;
        font-size: 12px;
    }
    .stats-table th {
        background-color: #f1f1f1;
        font-weight: bold;
    }
    .stats-refresh {
        margin-top: 10px;
        text-align: center;
    }
    .buttons-section {
        text-align: center;
        padding: 20px;
        background: #f9f9f9;
        border-radius: 4px;
        margin: 20px 0;
        border-top: 1px solid #dcdcde;
    }
    .buttons-section .solwed-btn {
        margin: 0 10px;
        min-width: 150px;
    }
    </style>

    <div class="solwed-smtp-container">
        <!-- FORMULARIO PRINCIPAL (Izquierda) -->
        <div class="solwed-smtp-main">
            <!-- Formulario Simple -->
            <form method="post" action="" id="solwed-smtp-form">
                <?php wp_nonce_field('solwed_settings'); ?>
                <input type="hidden" name="solwed_action" value="save_smtp">
                
                <div class="solwed-form-section">
                    <h2><?php _e('📧 Configuración SMTP Simple', 'solwed-wp'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Activar SMTP', 'solwed-wp'); ?></th>
                            <td>
                                <label class="solwed-switch">
                                    <input type="checkbox" name="smtp_enabled" value="1" <?php checked($smtp_enabled); ?>>
                                    <span class="solwed-slider"></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Servidor SMTP', 'solwed-wp'); ?></th>
                            <td><input type="text" name="smtp_host" value="<?php echo esc_attr($smtp_settings['host']); ?>" class="regular-text" placeholder="smtp.gmail.com"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Puerto', 'solwed-wp'); ?></th>
                            <td><input type="number" name="smtp_port" value="<?php echo esc_attr($smtp_settings['port']); ?>" class="small-text" min="1" max="65535"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Cifrado', 'solwed-wp'); ?></th>
                            <td>
                                <select name="smtp_encryption">
                                    <option value="none" <?php selected($smtp_settings['encryption'], 'none'); ?>><?php _e('Ninguno', 'solwed-wp'); ?></option>
                                    <option value="tls" <?php selected($smtp_settings['encryption'], 'tls'); ?>>TLS</option>
                                    <option value="ssl" <?php selected($smtp_settings['encryption'], 'ssl'); ?>>SSL</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Usuario SMTP', 'solwed-wp'); ?></th>
                        <td><input type="text" name="smtp_username" value="<?php echo esc_attr($smtp_settings['username']); ?>" class="regular-text" placeholder="tu-email@dominio.com"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Contraseña SMTP', 'solwed-wp'); ?></th>
                        <td><input type="password" name="smtp_password" value="<?php echo esc_attr($smtp_settings['password']); ?>" class="regular-text" placeholder="<?php echo !empty($smtp_settings['password']) ? __('Contraseña guardada', 'solwed-wp') : __('Tu contraseña', 'solwed-wp'); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Email del Remitente', 'solwed-wp'); ?></th>
                        <td><input type="email" name="smtp_from_email" value="<?php echo esc_attr($smtp_settings['from_email']); ?>" class="regular-text" placeholder="soporte@solwed.es"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Nombre del Remitente', 'solwed-wp'); ?></th>
                        <td><input type="text" name="smtp_from_name" value="<?php echo esc_attr($smtp_settings['from_name']); ?>" class="regular-text" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>"></td>
                    </tr>
                </table>
            </div>

            <!-- 3 BOTONES PRINCIPALES -->
            <div class="buttons-section">
                <button type="submit" class="solwed-btn">
                    💾 <?php _e('Guardar Configuración', 'solwed-wp'); ?>
                </button>
                
                <button type="button" id="send-test-email" class="solwed-btn">
                    📤 <?php _e('Enviar Email de Prueba', 'solwed-wp'); ?>
                </button>
                
                <button type="button" id="solwed-default-config" class="solwed-btn">
                     <?php _e('Configuración Solwed', 'solwed-wp'); ?>
                </button>
            </div>
        </form>

        <!-- Campo oculto para email de prueba -->
        <div id="test-email-section" style="display: none; text-align: center; margin: 20px 0;">
            <label for="test_email_input"><?php _e('Email de destino:', 'solwed-wp'); ?></label>
            <input type="email" id="test_email_input" value="soporte@solwed.es" class="regular-text" style="margin: 0 10px;">
            <button type="button" id="execute-test" class="solwed-btn">✉️ Enviar</button>
            <button type="button" id="cancel-test" class="solwed-btn">❌ Cancelar</button>
        </div>

        <!-- Resultados de Prueba -->
        <div id="test-results" style="display: none;"></div>

        <!-- Estadísticas -->
        </div>

        <!-- PANEL DE ESTADÍSTICAS (Derecha) -->
        <div class="solwed-smtp-sidebar">
            <div class="solwed-form-section solwed-sidebar-panel">
                <h3><?php _e('📊 Estado de Configuración', 'solwed-wp'); ?></h3>
                <div id="statistics-content">
                    <?php render_smtp_statistics($stats); ?>
                </div>
                <div class="stats-refresh">
                    <button type="button" id="refresh-stats" class="solwed-btn">
                        🔄 <?php _e('Actualizar', 'solwed-wp'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Botón Enviar Email de Prueba
        $('#send-test-email').click(function() {
            $('#test-email-section').slideDown();
            $('#test_email_input').focus();
        });

        $('#cancel-test').click(function() {
            $('#test-email-section').slideUp();
            $('#test-results').slideUp();
        });

        $('#execute-test').click(function() {
            var button = $(this);
            var testEmail = $('#test_email_input').val();
            
            if (!testEmail) {
                alert('Por favor, ingresa un email de destino');
                return;
            }

            button.prop('disabled', true).text('📤 Enviando...');

            $.post(ajaxurl, {
                action: 'solwed_smtp_test',
                test_email: testEmail,
                nonce: '<?php echo wp_create_nonce('smtp_test'); ?>'
            }, function(response) {
                var message = '';
                if (response.success) {
                    message = '<div class="notice notice-success"><p>✅ ' + response.data.message + '</p></div>';
                } else {
                    message = '<div class="notice notice-error"><p>❌ ' + response.data.message + '</p></div>';
                }
                $('#test-results').html(message).slideDown();
            }).fail(function() {
                $('#test-results').html('<div class="notice notice-error"><p>❌ Error de conexión</p></div>').slideDown();
            }).always(function() {
                button.prop('disabled', false).text('✉️ Enviar');
            });
        });

        // Botón Configuración Solwed por Defecto
        $('#solwed-default-config').click(function() {
            if (confirm('¿Deseas aplicar la configuración por defecto de Solwed? Esto configurará automáticamente los valores típicos de Solwed.')) {
                // Activar SMTP
                $('input[name="smtp_enabled"]').prop('checked', true);
                
                // Configurar valores por defecto de Solwed
                $('input[name="smtp_host"]').val('smtp.gmail.com');
                $('input[name="smtp_port"]').val('587');
                $('select[name="smtp_encryption"]').val('tls');
                $('input[name="smtp_from_email"]').val('soporte@solwed.es');
                $('input[name="smtp_from_name"]').val('<?php echo esc_js(get_bloginfo('name')); ?> - Solwed');
                $('#test_email_input').val('soporte@solwed.es');
                
                alert('✅ Configuración Solwed aplicada. Recuerda completar usuario y contraseña SMTP antes de guardar.');
            }
        });

        // Botón Actualizar Estadísticas
        $('#refresh-stats').click(function() {
            var button = $(this);
            button.prop('disabled', true).text('🔄 Actualizando...');
            
            $.post(ajaxurl, {
                action: 'solwed_smtp_stats',
                nonce: '<?php echo wp_create_nonce('smtp_stats'); ?>'
            }, function(response) {
                if (response.success) {
                    $('#statistics-content').html(response.data.html);
                }
            }).always(function() {
                button.prop('disabled', false).text('� <?php _e('Actualizar', 'solwed-wp'); ?>');
            });
        });
    });
    </script>
    <?php
}

function render_smtp_statistics($stats) {
    // Asegurar que $stats sea un array y tenga valores por defecto
    $stats = is_array($stats) ? $stats : [];
    
    // Valores por defecto para evitar errores
    $defaults = [
        'enabled' => false,
        'host' => '',
        'port' => 0,
        'username' => '',
        'password' => '',
        'wp_mail_config' => [
            'admin_email' => get_option('admin_email', '')
        ],
        'last_test' => '',
        'last_test_status' => ''
    ];
    
    $stats = array_merge($defaults, $stats);
    if (!is_array($stats['wp_mail_config'])) {
        $stats['wp_mail_config'] = $defaults['wp_mail_config'];
    }
    ?>
    <table class="stats-table">
        <tbody>
            <tr>
                <td><strong><?php _e('SMTP', 'solwed-wp'); ?></strong></td>
                <td><?php echo $stats['enabled'] ? '<span class="solwed-status-badge sent">✅</span>' : '<span class="solwed-status-badge failed">❌</span>'; ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Servidor', 'solwed-wp'); ?></strong></td>
                <td><?php echo !empty($stats['host']) ? '<span class="solwed-status-badge sent">✅</span>' : '<span class="solwed-status-badge failed">❌</span>'; ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Puerto', 'solwed-wp'); ?></strong></td>
                <td><?php echo ($stats['port'] > 0) ? '<span class="solwed-status-badge sent">✅</span>' : '<span class="solwed-status-badge failed">❌</span>'; ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Usuario', 'solwed-wp'); ?></strong></td>
                <td><?php echo !empty($stats['username']) ? '<span class="solwed-status-badge sent">✅</span>' : '<span class="solwed-status-badge failed">❌</span>'; ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Contraseña', 'solwed-wp'); ?></strong></td>
                <td><?php echo !empty($stats['password']) ? '<span class="solwed-status-badge sent">✅</span>' : '<span class="solwed-status-badge failed">❌</span>'; ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('WordPress', 'solwed-wp'); ?></strong></td>
                <td><?php echo function_exists('wp_mail') ? '<span class="solwed-status-badge sent">✅</span>' : '<span class="solwed-status-badge failed">❌</span>'; ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('PHPMailer', 'solwed-wp'); ?></strong></td>
                <td><?php echo (class_exists('PHPMailer\PHPMailer\PHPMailer') || class_exists('PHPMailer')) ? '<span class="solwed-status-badge sent">✅</span>' : '<span class="solwed-status-badge failed">❌</span>'; ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Email Admin', 'solwed-wp'); ?></strong></td>
                <td><?php echo filter_var($stats['wp_mail_config']['admin_email'], FILTER_VALIDATE_EMAIL) ? '<span class="solwed-status-badge sent">✅</span>' : '<span class="solwed-status-badge failed">❌</span>'; ?></td>
            </tr>
        </tbody>
    </table>

    <?php if (isset($stats['last_test']) && $stats['last_test'] && $stats['last_test'] !== 'Nunca'): ?>
    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
        <h4 style="margin-bottom: 8px; font-size: 12px;"><?php _e('Última Prueba', 'solwed-wp'); ?></h4>
        <p style="margin: 0; font-size: 11px;">
            <?php if (isset($stats['last_test_status']) && $stats['last_test_status'] === 'success'): ?>
                <span class="solwed-status-badge sent">✅ Exitosa</span>
            <?php elseif (isset($stats['last_test_status']) && $stats['last_test_status'] === 'error'): ?>
                <span class="solwed-status-badge failed">❌ Falló</span>
            <?php else: ?>
                <span class="solwed-status-badge">⚠️ Desconocido</span>
            <?php endif; ?>
            <br><small><?php echo esc_html($stats['last_test']); ?></small>
        </p>
    </div>
    <?php endif; ?>
    <?php
}

// Agregar handlers AJAX para los nuevos botones simplificados
add_action('wp_ajax_solwed_smtp_stats', 'handle_solwed_smtp_stats_ajax');

function handle_solwed_smtp_stats_ajax() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'smtp_stats')) {
        wp_die('Acceso denegado');
    }

    $smtp = solwed_wp()->get_module('smtp');
    if (!$smtp) {
        wp_send_json_error(['message' => 'Módulo SMTP no disponible']);
    }

    $stats = $smtp->get_stats();
    
    ob_start();
    render_smtp_statistics($stats);
    $html = ob_get_clean();
    
    wp_send_json_success(['html' => $html]);
}
