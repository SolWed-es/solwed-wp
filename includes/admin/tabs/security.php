<?php
/**
 * Pestaña de Seguridad
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase de Seguridad Unificada - Solwed WP Plugin
 */
class Solwed_Security_Unified {
    
    private $config;

    public function __construct() {
        $this->config = [
            'security_enabled' => get_option(SOLWED_WP_PREFIX . 'security_enabled', '1'),
            'max_login_attempts' => get_option(SOLWED_WP_PREFIX . 'max_login_attempts', 3),
            'lockout_duration' => get_option(SOLWED_WP_PREFIX . 'lockout_duration', 1800),
            'enable_custom_login' => get_option(SOLWED_WP_PREFIX . 'enable_custom_login', '0'),
            'custom_login_url' => get_option(SOLWED_WP_PREFIX . 'custom_login_url', 'login'),
            'force_ssl' => get_option(SOLWED_WP_PREFIX . 'force_ssl', '0')
        ];
    }

    public function init(): void {
        // Inicializar funcionalidades de seguridad si están habilitadas
        if ($this->is_ssl_forced()) {
            add_action('template_redirect', [$this, 'enforce_ssl'], 1);
        }
    }

    public function is_enabled(): bool {
        return $this->config['security_enabled'] === '1';
    }

    public function is_ssl_forced(): bool {
        return $this->config['force_ssl'] === '1';
    }

    /**
     * Fuerza el uso de SSL/HTTPS en todo el sitio
     */
    public function enforce_ssl(): void {
        // Solo aplicar en el frontend y si no estamos ya en HTTPS
        if (is_admin() || (defined('WP_CLI') && WP_CLI) || is_ssl()) {
            return;
        }

        // Obtener la URL actual y convertirla a HTTPS
        $redirect_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        
        // Redirección permanente (301)
        wp_redirect($redirect_url, 301);
        exit;
    }

    /**
     * Guardar configuración de seguridad
     */
    public function save_settings(array $data): bool {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $settings = [
            'security_enabled' => isset($data['security_enabled']) ? '1' : '0',
            'max_login_attempts' => isset($data['max_login_attempts']) ? (int) $data['max_login_attempts'] : 3,
            'lockout_duration' => isset($data['lockout_duration']) ? (int) $data['lockout_duration'] : 1800,
            'enable_custom_login' => isset($data['enable_custom_login']) ? '1' : '0',
            'custom_login_url' => isset($data['custom_login_url']) ? sanitize_text_field($data['custom_login_url']) : 'login',
            'force_ssl' => isset($data['force_ssl']) ? '1' : '0'
        ];

        $success = true;
        foreach ($settings as $key => $value) {
            if (!update_option(SOLWED_WP_PREFIX . $key, $value)) {
                $success = false;
            }
        }

        // Actualizar configuración local
        $this->config = array_merge($this->config, $settings);

        return $success;
    }

    private function get_client_ip(): string {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function get_stats(): array {
        global $wpdb;
        
        $stats = [
            'total_failed_attempts' => 0,
            'current_lockouts' => 0,
            'recent_lockouts' => []
        ];

        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}solwed_blocked_ips'")) {
            $stats['current_lockouts'] = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}solwed_blocked_ips WHERE blocked_until > NOW()"
            );
            
            $stats['recent_lockouts'] = $wpdb->get_results(
                "SELECT ip_address, blocked_at as lockout_time FROM {$wpdb->prefix}solwed_blocked_ips 
                 ORDER BY blocked_at DESC LIMIT 10"
            );
        }

        return $stats;
    }
}

function render_security_tab() {
    $security = solwed_wp()->get_module('security');
    $security_enabled = $security ? $security->is_enabled() : false;
    $stats = $security ? $security->get_stats() : [];
    
    $security_settings = [
        'max_attempts' => get_option(SOLWED_WP_PREFIX . 'max_login_attempts', 3),
        'lockout_duration' => get_option(SOLWED_WP_PREFIX . 'lockout_duration', 1800),
        'enable_custom_login' => get_option(SOLWED_WP_PREFIX . 'enable_custom_login', '0'),
        'custom_login_url' => get_option(SOLWED_WP_PREFIX . 'custom_login_url', 'login'),
        'force_ssl' => get_option(SOLWED_WP_PREFIX . 'force_ssl', '0')
    ];
    ?>

    <style>
    .solwed-security-container {
        display: flex;
        gap: 20px;
        margin: 20px 0;
    }
    .solwed-security-main {
        flex: 2;
        max-width: 800px;
    }
    .solwed-security-sidebar {
        flex: 1;
        min-width: 300px;
        max-width: 350px;
    }
    .solwed-sidebar-panel {
        position: sticky;
        top: 32px;
        margin-bottom: 20px;
    }
    </style>

    <div class="solwed-security-container">
        <!-- FORMULARIO PRINCIPAL (Izquierda) -->
        <div class="solwed-security-main">
            <form method="post" action="">
                <?php wp_nonce_field('solwed_settings'); ?>
                <input type="hidden" name="solwed_action" value="save_security">
                
                <div class="solwed-form-section">
                    <h2><?php _e('🔒 Control de Intentos de Login', 'solwed-wp'); ?></h2>
                    <p class="description"><?php _e('Protege tu sitio web limitando los intentos fallidos de login.', 'solwed-wp'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Activar Protección', 'solwed-wp'); ?></th>
                            <td>
                                <label class="solwed-switch">
                                    <input type="checkbox" name="security_enabled" value="1" <?php checked($security_enabled); ?>>
                                    <span class="solwed-slider"></span>
                                </label>
                                <p class="description"><?php _e('Activa la protección contra intentos de login masivos.', 'solwed-wp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Máximo Intentos', 'solwed-wp'); ?></th>
                            <td>
                                <input type="number" name="max_login_attempts" 
                                       value="<?php echo esc_attr($security_settings['max_attempts']); ?>" 
                                       min="1" max="10" class="small-text">
                                <p class="description"><?php _e('Número máximo de intentos fallidos antes del bloqueo (recomendado: 3).', 'solwed-wp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Duración del Bloqueo', 'solwed-wp'); ?></th>
                            <td>
                                <select name="lockout_duration">
                                    <option value="900" <?php selected($security_settings['lockout_duration'], 900); ?>>15 <?php _e('minutos', 'solwed-wp'); ?></option>
                                    <option value="1800" <?php selected($security_settings['lockout_duration'], 1800); ?>>30 <?php _e('minutos', 'solwed-wp'); ?></option>
                                    <option value="3600" <?php selected($security_settings['lockout_duration'], 3600); ?>>1 <?php _e('hora', 'solwed-wp'); ?></option>
                                    <option value="7200" <?php selected($security_settings['lockout_duration'], 7200); ?>>2 <?php _e('horas', 'solwed-wp'); ?></option>
                                    <option value="21600" <?php selected($security_settings['lockout_duration'], 21600); ?>>6 <?php _e('horas', 'solwed-wp'); ?></option>
                                    <option value="86400" <?php selected($security_settings['lockout_duration'], 86400); ?>>24 <?php _e('horas', 'solwed-wp'); ?></option>
                                </select>
                                <p class="description"><?php _e('Tiempo que permanecerá bloqueada una IP tras superar el límite.', 'solwed-wp'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Sección de SSL/HTTPS -->
                <div class="solwed-form-section">
                    <h2><?php _e('🔐 SSL/HTTPS', 'solwed-wp'); ?></h2>
                    <p class="description"><?php _e('Fuerza el uso de HTTPS en todo tu sitio web para mayor seguridad.', 'solwed-wp'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Forzar SSL en todo el sitio', 'solwed-wp'); ?></th>
                            <td>
                                <label class="solwed-switch">
                                    <input type="checkbox" name="force_ssl" value="1" <?php checked($security_settings['force_ssl'], '1'); ?>>
                                    <span class="solwed-slider"></span>
                                </label>
                                <p class="description">
                                    <?php _e('Redirige automáticamente todas las páginas HTTP a HTTPS (redirección 301).', 'solwed-wp'); ?>
                                    <?php if ($security_settings['force_ssl'] === '1'): ?>
                                        <br><strong style="color: #46B450;"><?php _e('✓ SSL forzado está activo', 'solwed-wp'); ?></strong>
                                    <?php else: ?>
                                        <br><strong style="color: #d63638;"><?php _e('⚠ SSL no está forzado', 'solwed-wp'); ?></strong>
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Sección de Login Personalizado -->
                <div class="solwed-form-section">
                    <h2><?php _e('🚪 URL de Login Personalizada', 'solwed-wp'); ?></h2>
                    <p class="description"><?php _e('Personaliza la URL de acceso al login. Cuando está activa, /wp-admin y /wp-login.php serán inaccesibles.', 'solwed-wp'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Activar URL Personalizada', 'solwed-wp'); ?></th>
                            <td>
                                <label class="solwed-switch">
                                    <input type="checkbox" name="enable_custom_login" value="1" <?php checked($security_settings['enable_custom_login'], '1'); ?>>
                                    <span class="solwed-slider"></span>
                                </label>
                                <p class="description"><?php _e('Cuando está activa, solo se podrá acceder al login a través de la URL personalizada.', 'solwed-wp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('URL Personalizada', 'solwed-wp'); ?></th>
                            <td>
                                <input type="text" name="custom_login_url" 
                                       value="<?php echo esc_attr($security_settings['custom_login_url']); ?>" 
                                       class="regular-text" placeholder="login">
                                <p class="description">
                                    <?php _e('URL personalizada para acceder al login (solo la parte final, sin /).', 'solwed-wp'); ?>
                                    <?php if ($security_settings['enable_custom_login']): ?>
                                        <br><strong style="color: #2271b1;"><?php _e('URL activa:', 'solwed-wp'); ?></strong> 
                                        <a href="<?php echo esc_url(home_url($security_settings['custom_login_url'])); ?>" target="_blank">
                                            <?php echo esc_url(home_url($security_settings['custom_login_url'])); ?>
                                        </a>
                                        <br><strong style="color: #d63638;"><?php _e('Importante:', 'solwed-wp'); ?></strong> 
                                        <em><?php _e('/wp-admin y /wp-login.php estarán inaccesibles', 'solwed-wp'); ?></em>
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="solwed-form-actions">
                    <?php submit_button(__('Guardar Configuración', 'solwed-wp'), 'primary solwed-btn'); ?>
                </div>
            </form>
        </div>

        <!-- PANEL DE ESTADÍSTICAS (Derecha) -->
        <div class="solwed-security-sidebar">
            <div class="solwed-sidebar-panel solwed-panel">
                <h3><?php _e('🛡️ Estado de Seguridad', 'solwed-wp'); ?></h3>
                <div class="solwed-stats-info">
                    <p><strong><?php _e('Protección Login:', 'solwed-wp'); ?></strong> 
                        <span class="solwed-status-badge <?php echo $security_enabled ? 'sent' : 'failed'; ?>">
                            <?php echo $security_enabled ? __('Activa', 'solwed-wp') : __('Inactiva', 'solwed-wp'); ?>
                        </span>
                    </p>
                    <p><strong><?php _e('SSL Forzado:', 'solwed-wp'); ?></strong> 
                        <span class="solwed-status-badge <?php echo ($security_settings['force_ssl'] === '1') ? 'sent' : 'failed'; ?>">
                            <?php echo ($security_settings['force_ssl'] === '1') ? __('Activo', 'solwed-wp') : __('Inactivo', 'solwed-wp'); ?>
                        </span>
                    </p>
                    <p><strong><?php _e('Login Personalizado:', 'solwed-wp'); ?></strong> 
                        <span class="solwed-status-badge <?php echo ($security_settings['enable_custom_login'] === '1') ? 'sent' : 'failed'; ?>">
                            <?php echo ($security_settings['enable_custom_login'] === '1') ? __('Activo', 'solwed-wp') : __('Inactivo', 'solwed-wp'); ?>
                        </span>
                    </p>
                    <p><strong><?php _e('Máx. Intentos:', 'solwed-wp'); ?></strong> <?php echo esc_html($security_settings['max_attempts']); ?></p>
                    <p><strong><?php _e('Duración Bloqueo:', 'solwed-wp'); ?></strong> <?php echo esc_html(human_time_diff(0, $security_settings['lockout_duration'])); ?></p>
                </div>
            </div>

            <?php if ($security_enabled && !empty($stats)): ?>
            <div class="solwed-sidebar-panel solwed-panel">
                <h3><?php _e('📊 Estadísticas', 'solwed-wp'); ?></h3>
                <div class="solwed-stats-info">
                    <p><strong><?php _e('Intentos Fallidos:', 'solwed-wp'); ?></strong> <?php echo esc_html($stats['total_failed_attempts'] ?? 0); ?></p>
                    <p><strong><?php _e('IPs Bloqueadas:', 'solwed-wp'); ?></strong> <?php echo esc_html($stats['current_lockouts'] ?? 0); ?></p>
                    <?php if (!empty($stats['recent_lockouts'])): ?>
                        <p><strong><?php _e('Bloqueos Recientes:', 'solwed-wp'); ?></strong> <?php echo count($stats['recent_lockouts']); ?></p>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($stats['recent_lockouts'])): ?>
                <h4><?php _e('IPs Bloqueadas Recientemente:', 'solwed-wp'); ?></h4>
                <div style="max-height: 200px; overflow-y: auto; font-size: 11px;">
                    <?php foreach (array_slice($stats['recent_lockouts'], 0, 10) as $lockout): ?>
                    <div style="padding: 5px 0; border-bottom: 1px solid #eee;">
                        <strong><?php echo esc_html($lockout->ip_address); ?></strong><br>
                        <small style="color: #666;">
                            <?php echo esc_html(date_i18n('d/m/Y H:i', $lockout->lockout_time)); ?><br>
                            Expira: <?php echo esc_html(human_time_diff($lockout->lockout_time, time() + $security_settings['lockout_duration'])); ?>
                        </small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="solwed-sidebar-panel solwed-panel">
                <h3><?php _e('🔧 Recomendaciones', 'solwed-wp'); ?></h3>
                <ul style="padding-left: 20px; line-height: 1.6;">
                    <li><?php _e('Use SSL/HTTPS siempre para mayor seguridad', 'solwed-wp'); ?></li>
                    <li><?php _e('Mantenga 3 intentos máximos para balance usabilidad-seguridad', 'solwed-wp'); ?></li>
                    <li><?php _e('El login personalizado oculta las URLs estándar de WordPress', 'solwed-wp'); ?></li>
                    <li><?php _e('Monitoree regularmente las estadísticas de bloqueos', 'solwed-wp'); ?></li>
                </ul>
            </div>

            <?php if ($security_settings['enable_custom_login'] === '1'): ?>
            <div class="solwed-sidebar-panel solwed-panel" style="border-left: 4px solid #d63638;">
                <h3><?php _e('⚠️ URL Login Activa', 'solwed-wp'); ?></h3>
                <p style="font-size: 12px; line-height: 1.4;">
                    <strong><?php _e('URL Personalizada:', 'solwed-wp'); ?></strong><br>
                    <a href="<?php echo esc_url(home_url($security_settings['custom_login_url'])); ?>" target="_blank" style="word-break: break-all;">
                        <?php echo esc_url(home_url($security_settings['custom_login_url'])); ?>
                    </a>
                </p>
                <p style="color: #d63638; font-size: 11px; margin: 10px 0 0 0;">
                    <strong><?php _e('¡Importante!', 'solwed-wp'); ?></strong> <?php _e('Guarda esta URL en un lugar seguro. Las rutas estándar estarán inaccesibles.', 'solwed-wp'); ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php
}
