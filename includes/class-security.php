<?php
/**
 * Módulo de Seguridad - Control de intentos de login
 */

if (!defined('ABSPATH')) {
    exit;
}

class Solwed_Security {
    
    public function init(): void {
        if (!$this->is_enabled()) {
            return;
        }

        add_action('wp_login_failed', [$this, 'handle_failed_login']);
        add_filter('authenticate', [$this, 'check_blocked_ip'], 30, 3);
        add_action('wp_login', [$this, 'handle_successful_login'], 10, 2);
    }

    public function is_enabled(): bool {
        return get_option(SOLWED_WP_PREFIX . 'security_enabled', '1') === '1';
    }

    public function handle_failed_login(string $username): void {
        $ip = $this->get_client_ip();
        $max_attempts = intval(get_option(SOLWED_WP_PREFIX . 'max_login_attempts', 3));
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'solwed_failed_attempts';
        
        // Registrar intento fallido
        $this->log_security_event($ip, $username, 'login_failed');
        
        // Obtener intentos actuales
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for security functionality
        $current_attempts = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT attempts FROM $table_name WHERE ip_address = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $ip
            )
        );

        if ($current_attempts === null) {
            // Primera vez que falla esta IP
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct query needed for security functionality
            $wpdb->insert(
                $table_name,
                [
                    'ip_address' => $ip,
                    'attempts' => 1,
                    'last_attempt' => current_time('mysql')
                ],
                ['%s', '%d', '%s']
            );
        } else {
            $new_attempts = intval($current_attempts) + 1;
            $blocked_until = null;
            
            if ($new_attempts >= $max_attempts) {
                $lockout_duration = intval(get_option(SOLWED_WP_PREFIX . 'lockout_duration', 1800));
                $blocked_until = gmdate('Y-m-d H:i:s', time() + $lockout_duration);
                
                $this->log_security_event($ip, $username, 'ip_blocked');
            }
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for security functionality
            $wpdb->update(
                $table_name,
                [
                    'attempts' => $new_attempts,
                    'blocked_until' => $blocked_until,
                    'last_attempt' => current_time('mysql')
                ],
                ['ip_address' => $ip],
                ['%d', '%s', '%s'],
                ['%s']
            );
        }
    }

    public function check_blocked_ip($user, string $username, string $password) {
        if (empty($username) || empty($password)) {
            return $user;
        }

        $ip = $this->get_client_ip();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'solwed_failed_attempts';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for security functionality
        $blocked_info = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT blocked_until FROM $table_name WHERE ip_address = %s AND blocked_until IS NOT NULL", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $ip
            )
        );

        if ($blocked_info && strtotime($blocked_info->blocked_until) > time()) {
            $remaining_time = strtotime($blocked_info->blocked_until) - time();
            $minutes = ceil($remaining_time / 60);
            
            return new WP_Error(
                'ip_blocked',
                sprintf(
                    /* translators: %d: number of minutes until the IP is unblocked */
                    __('Tu IP ha sido bloqueada temporalmente debido a múltiples intentos fallidos. Inténtalo de nuevo en %d minutos.', 'solwed-wp'),
                    $minutes
                )
            );
        }

        // Limpiar bloqueos expirados
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for security functionality
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table_name SET blocked_until = NULL WHERE blocked_until IS NOT NULL AND blocked_until < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                current_time('mysql')
            )
        );

        return $user;
    }

    public function handle_successful_login(string $user_login, WP_User $user): void {
        $ip = $this->get_client_ip();
        
        // Limpiar intentos fallidos después de un login exitoso
        global $wpdb;
        $table_name = $wpdb->prefix . 'solwed_failed_attempts';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for security functionality
        $wpdb->delete($table_name, ['ip_address' => $ip], ['%s']);
        
        // Registrar login exitoso
        $this->log_security_event($ip, $user_login, 'login_success');
    }

    public function save_settings(array $data): bool {
        $settings = [
            'security_enabled' => isset($data['security_enabled']) ? '1' : '0',
            'max_login_attempts' => intval($data['max_login_attempts'] ?? 3),
            'lockout_duration' => intval($data['lockout_duration'] ?? 1800)
        ];

        foreach ($settings as $key => $value) {
            update_option(SOLWED_WP_PREFIX . $key, $value);
        }

        return true;
    }

    public function get_stats(): array {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'solwed_security_logs';
        $attempts_table = $wpdb->prefix . 'solwed_failed_attempts';
        
        $stats = [
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for security functionality
            'total_failed_attempts' => $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE action = 'login_failed'"), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'blocked_ips' => $wpdb->get_var("SELECT COUNT(*) FROM $attempts_table WHERE blocked_until IS NOT NULL AND blocked_until > NOW()"), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'total_blocks' => $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE action = 'ip_blocked'"), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'recent_attempts' => $wpdb->get_results(
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct query needed for security functionality
                "SELECT ip_address, username, timestamp FROM $logs_table 
                 WHERE action = 'login_failed' 
                 ORDER BY timestamp DESC LIMIT 10"
            ),
            'current_blocks' => $wpdb->get_results(
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct query needed for security functionality
                "SELECT ip_address, attempts, blocked_until FROM $attempts_table 
                 WHERE blocked_until IS NOT NULL AND blocked_until > NOW()"
            )
        ];

        return $stats;
    }

    public function unblock_ip(string $ip): bool {
        global $wpdb;
        $table_name = $wpdb->prefix . 'solwed_failed_attempts';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for security functionality
        return $wpdb->delete($table_name, ['ip_address' => $ip], ['%s']) !== false;
    }

    private function get_client_ip(): string {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
                $ip = explode(',', sanitize_text_field(wp_unslash($_SERVER[$key])))[0];
                $ip = trim($ip);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '127.0.0.1';
    }

    private function log_security_event(string $ip, string $username, string $action): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'solwed_security_logs';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct query needed for security functionality
        $wpdb->insert(
            $table_name,
            [
                'ip_address' => $ip,
                'username' => $username,
                'action' => $action,
                'timestamp' => current_time('mysql'),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
                'status' => $action === 'login_success' ? 'success' : 'failed'
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );
    }
}