<?php
/**
 * Tab FacturaScript - Integración con FacturaScripts CRM
 * 
 * @package SolwedWP
 * @since 2.0.0
 * @author Solwed
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase principal para la integración con FacturaScripts
 */
final class Solwed_FacturaScript_Integration {
    
    /**
     * Configuraciones por defecto
     */
    public const DEFAULT_API_URL = 'http://erpdemo.test/api/3';
    public const DEFAULT_API_TOKEN = 'QLojHJpvraWR6FsFTtAC';
    
    /**
     * Constructor - Registrar hooks solo    // Obtener configuración actual
    $enabled = get_option('solwed_facturascript_enabled', false);
    $api_url = get_option('solwed_facturascript_api_url', Solwed_FacturaScript_Integration::DEFAULT_API_URL);
    $api_token = get_option('solwed_facturascript_api_token', Solwed_FacturaScript_Integration::DEFAULT_API_TOKEN);está habilitado
     */
    public function __construct() {
        // Solo registrar hooks si el módulo está habilitado
        if (get_option('solwed_facturascript_enabled', false)) {
            add_action('elementor_pro/forms/new_record', [$this, 'handle_form_submission'], 10, 2);
        }
        
        // AJAX handlers
        add_action('wp_ajax_solwed_test_fs_connection', [$this, 'test_connection']);
        add_action('wp_ajax_solwed_fs_bulk_import', [$this, 'bulk_import_submissions']);
        add_action('wp_ajax_solwed_refresh_fs_stats', [$this, 'refresh_statistics']);
    }
    
    /**
     * Manejar submission de formulario Elementor
     */
    public function handle_form_submission($record, $handler): bool {
        try {
            // Verificar que los objetos necesarios existen
            if (!$record || !$handler) {
                error_log('Solwed FS: Record o Handler nulo');
                return false;
            }
            
            // Obtener datos del formulario de forma segura
            $form_data = null;
            if (method_exists($record, 'get')) {
                $form_data = $record->get('fields');
            } elseif (isset($record->fields)) {
                $form_data = $record->fields;
            }
            
            if (!$form_data) {
                error_log('Solwed FS: No se pudieron obtener datos del formulario');
                return false;
            }
            
            $page_title = get_the_title() ?: 'Formulario Web';
            $submission_text = $this->format_submission_data($form_data);
            
            if (!$submission_text) {
                error_log('Solwed FS: No se pudo formatear datos de la submission');
                return false;
            }
            
            // Crear oportunidad
            $result = $this->create_opportunity($page_title, $submission_text);
            
            // Actualizar estadísticas
            $this->update_statistics($result);
            
            return $result;
            
        } catch (Exception $e) {
            error_log('Solwed FS Error: ' . $e->getMessage());
            $this->update_statistics(false, $e->getMessage());
            return false;
        }
    }
    
    /**
     * Formatear datos del formulario en texto simple
     */
    private function format_submission_data(array $form_data): string {
        $formatted_text = [];
        
        foreach ($form_data as $field) {
            $field_id = $field['id'] ?? 'Campo';
            $field_value = $field['value'] ?? '';
            
            if (!empty($field_value)) {
                $formatted_text[] = "{$field_id}: {$field_value}";
            }
        }
        
        return implode("\n", $formatted_text);
    }
    
    /**
     * Crear oportunidad en FacturaScripts con contacto vinculado
     */
    private function create_opportunity(string $page_title, string $submission_text): bool {
        $api_url = get_option('solwed_facturascript_api_url', self::DEFAULT_API_URL);
        $api_token = get_option('solwed_facturascript_api_token', self::DEFAULT_API_TOKEN);
        
        if (empty($api_url) || empty($api_token)) {
            return false;
        }
        
        // Extraer datos de contacto del submission
        $contact_data = $this->extract_contact_data($submission_text);
        $client_code = null;
        
        // Crear cliente si hay datos de contacto
        if (!empty($contact_data)) {
            $client_code = $this->create_or_update_client($api_url, $api_token, $contact_data);
        }
        
        // Datos de la oportunidad
        $opportunity_data = [
            'descripcion' => $page_title,
            'observaciones' => $submission_text,
            'fecha' => date('Y-m-d')
        ];
        
        // Vincular cliente si se creó exitosamente
        if ($client_code) {
            $opportunity_data['codcliente'] = $client_code;
        }
        
        // Enviar a FacturaScripts
        $response = wp_remote_post($api_url . '/crmoportunidades', [
            'headers' => [
                'Token' => $api_token,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'body' => $opportunity_data,
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($status_code === 200) {
            $response_data = json_decode($response_body, true);
            return isset($response_data['ok']);
        }
        
        return false;
    }
    
    /**
     * Extraer datos de contacto del texto del submission
     */
    private function extract_contact_data(string $submission_text): array {
        $lines = explode("\n", $submission_text);
        $contact_data = [];
        
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$field, $value] = explode(':', $line, 2);
                $field = strtolower(trim($field));
                $value = trim($value);
                
                // Mapear campos comunes
                if (in_array($field, ['name', 'nombre', 'first_name'])) {
                    $contact_data['nombre'] = $value;
                } elseif (in_array($field, ['email', 'correo', 'e-mail'])) {
                    $contact_data['email'] = $value;
                } elseif (in_array($field, ['phone', 'telefono', 'tel', 'mobile'])) {
                    $contact_data['telefono1'] = $value;
                } elseif (in_array($field, ['company', 'empresa', 'organization'])) {
                    $contact_data['razonsocial'] = $value;
                }
            }
        }
        
        return $contact_data;
    }
    
    /**
     * Crear o actualizar cliente en FacturaScripts
     */
    private function create_or_update_client(string $api_url, string $api_token, array $contact_data): ?string {
        if (empty($contact_data['email'])) {
            return null;
        }
        
        // Buscar cliente existente por email
        $existing_client = $this->find_client_by_email($api_url, $api_token, $contact_data['email']);
        if ($existing_client) {
            return $existing_client;
        }
        
        // Preparar datos del cliente
        $client_data = [
            'nombre' => $contact_data['nombre'] ?? 'Cliente Web',
            'razonsocial' => $contact_data['razonsocial'] ?? $contact_data['nombre'] ?? 'Cliente Web',
            'email' => $contact_data['email'],
            'telefono1' => $contact_data['telefono1'] ?? '',
            'cifnif' => $this->generate_temp_nif($contact_data['email']),
            'personafisica' => true
        ];
        
        // Crear cliente
        $response = wp_remote_post($api_url . '/clientes', [
            'headers' => [
                'Token' => $api_token,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'body' => $client_data,
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($status_code === 200) {
            $response_data = json_decode($response_body, true);
            if (isset($response_data['data']['codcliente'])) {
                return $response_data['data']['codcliente'];
            }
        }
        
        return null;
    }
    
    /**
     * Buscar cliente existente por email
     */
    private function find_client_by_email(string $api_url, string $api_token, string $email): ?string {
        $response = wp_remote_get($api_url . '/clientes', [
            'headers' => [
                'Token' => $api_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 10
        ]);
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }
        
        $clients = json_decode(wp_remote_retrieve_body($response), true);
        
        foreach ($clients as $client) {
            if (isset($client['email']) && strtolower($client['email']) === strtolower($email)) {
                return $client['codcliente'];
            }
        }
        
        return null;
    }
    
    /**
     * Generar NIF temporal basado en email
     */
    private function generate_temp_nif(string $email): string {
        $hash = substr(md5($email), 0, 8);
        $numbers = preg_replace('/[^0-9]/', '', $hash);
        $numbers = substr($numbers . '12345678', 0, 8);
        
        $letters = 'TRWAGMYFPDXBNJZSQVHLCKE';
        $letter = $letters[$numbers % 23];
        
        return $numbers . $letter;
    }
    
    /**
     * Actualizar estadísticas
     */
    private function update_statistics(bool $success, string $error_message = ''): void {
        $stats = get_option('solwed_facturascript_stats', [
            'total_submissions' => 0,
            'successful_submissions' => 0,
            'failed_submissions' => 0,
            'last_submission' => '',
            'last_error' => ''
        ]);
        
        $stats['total_submissions']++;
        $stats['last_submission'] = date('Y-m-d H:i:s');
        
        if ($success) {
            $stats['successful_submissions']++;
            $stats['last_error'] = '';
        } else {
            $stats['failed_submissions']++;
            $stats['last_error'] = $error_message;
        }
        
        update_option('solwed_facturascript_stats', $stats);
    }
    
    /**
     * Test de conexión AJAX
     */
    public function test_connection(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Acceso denegado');
        }
        
        $api_url = get_option('solwed_facturascript_api_url', self::DEFAULT_API_URL);
        $api_token = get_option('solwed_facturascript_api_token', self::DEFAULT_API_TOKEN);
        
        if (empty($api_url) || empty($api_token)) {
            wp_send_json_error('URL de API y Token son requeridos');
        }
        
        $response = wp_remote_get($api_url . '/crmoportunidades?limit=1', [
            'headers' => [
                'Token' => $api_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 10
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Error de conexión: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 200) {
            wp_send_json_success('Conexión exitosa con FacturaScripts CRM');
        } else {
            wp_send_json_error('Error HTTP: ' . $status_code);
        }
    }
    
    /**
     * Volcado masivo de submissions existentes
     */
    public function bulk_import_submissions(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Acceso denegado');
        }
        
        global $wpdb;
        
        // Buscar submissions en la base de datos
        $submissions = $wpdb->get_results("
            SELECT s.*, p.post_title 
            FROM {$wpdb->prefix}e_submissions s
            LEFT JOIN {$wpdb->prefix}posts p ON s.post_id = p.ID
            WHERE s.status = 'new'
            ORDER BY s.created_at DESC
            LIMIT 100
        ");
        
        if (empty($submissions)) {
            wp_send_json_error('No se encontraron submissions en la base de datos');
        }
        
        $total = count($submissions);
        $imported = 0;
        $errors = 0;
        
        foreach ($submissions as $submission) {
            try {
                // Obtener los valores del formulario
                $form_values = $wpdb->get_results($wpdb->prepare("
                    SELECT `key`, `value` 
                    FROM {$wpdb->prefix}e_submissions_values 
                    WHERE submission_id = %d
                ", $submission->id));
                
                if (empty($form_values)) {
                    $errors++;
                    continue;
                }
                
                $page_title = $submission->post_title ?: $submission->form_name ?: 'Página Web';
                $submission_text = $this->format_submission_from_values($form_values);
                
                if ($this->create_opportunity($page_title, $submission_text)) {
                    $imported++;
                } else {
                    $errors++;
                }
                
            } catch (Exception $e) {
                $errors++;
            }
        }
        
        wp_send_json_success("Procesadas {$total} submissions. Importadas: {$imported}. Errores: {$errors}");
    }
    
    /**
     * Formatear datos de submission desde valores de base de datos
     */
    private function format_submission_from_values(array $form_values): string {
        $formatted_text = [];
        
        foreach ($form_values as $field) {
            if (!empty($field->value)) {
                $formatted_text[] = "{$field->key}: {$field->value}";
            }
        }
        
        return implode("\n", $formatted_text);
    }
    
    /**
     * Refrescar estadísticas AJAX
     */
    public function refresh_statistics(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Acceso denegado');
        }
        
        $stats = $this->get_connection_statistics();
        wp_send_json_success($stats);
    }
    
    /**
     * Obtener estadísticas de conexión
     */
    public function get_connection_statistics(): array {
        $api_url = get_option('solwed_facturascript_api_url', self::DEFAULT_API_URL);
        $api_token = get_option('solwed_facturascript_api_token', self::DEFAULT_API_TOKEN);
        $enabled = get_option('solwed_facturascript_enabled', false);
        $stats = get_option('solwed_facturascript_stats', [
            'total_submissions' => 0,
            'successful_submissions' => 0,
            'failed_submissions' => 0,
            'last_submission' => 'Nunca',
            'last_error' => ''
        ]);
        
        // Test de conexión
        $connection_status = false;
        if (!empty($api_url) && !empty($api_token)) {
            $response = wp_remote_get($api_url . '/crmoportunidades?limit=1', [
                'headers' => [
                    'Token' => $api_token,
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 5
            ]);
            
            $connection_status = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
        }
        
        return [
            'enabled' => $enabled,
            'connection' => $connection_status,
            'api_configured' => !empty($api_url) && !empty($api_token),
            'elementor_pro' => is_plugin_active('elementor-pro/elementor-pro.php'),
            'total_submissions' => $stats['total_submissions'],
            'successful_submissions' => $stats['successful_submissions'],
            'failed_submissions' => $stats['failed_submissions'],
            'last_submission' => $stats['last_submission'],
            'last_error' => $stats['last_error'],
            'success_rate' => $stats['total_submissions'] > 0 
                ? round(($stats['successful_submissions'] / $stats['total_submissions']) * 100, 1) 
                : 0
        ];
    }
}

/**
 * Renderizar estadísticas de FacturaScript
 */
function render_facturascript_statistics(array $stats): void {
    ?>
    <table class="stats-table">
        <tbody>
            <tr>
                <td><strong><?php _e('Módulo Activo', 'solwed-wp'); ?></strong></td>
                <td><?php echo $stats['enabled'] ? '<span class="solwed-status-badge sent">✅</span>' : '<span class="solwed-status-badge failed">❌</span>'; ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('API Configurada', 'solwed-wp'); ?></strong></td>
                <td><?php echo $stats['api_configured'] ? '<span class="solwed-status-badge sent">✅</span>' : '<span class="solwed-status-badge failed">❌</span>'; ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Conexión FS', 'solwed-wp'); ?></strong></td>
                <td><?php echo $stats['connection'] ? '<span class="solwed-status-badge sent">✅</span>' : '<span class="solwed-status-badge failed">❌</span>'; ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Elementor Pro', 'solwed-wp'); ?></strong></td>
                <td><?php echo $stats['elementor_pro'] ? '<span class="solwed-status-badge sent">✅</span>' : '<span class="solwed-status-badge failed">❌</span>'; ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Total Envíos', 'solwed-wp'); ?></strong></td>
                <td><?php echo esc_html($stats['total_submissions']); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Exitosos', 'solwed-wp'); ?></strong></td>
                <td><?php echo esc_html($stats['successful_submissions']); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Fallidos', 'solwed-wp'); ?></strong></td>
                <td><?php echo esc_html($stats['failed_submissions']); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Tasa Éxito', 'solwed-wp'); ?></strong></td>
                <td><?php echo esc_html($stats['success_rate']); ?>%</td>
            </tr>
        </tbody>
    </table>

    <?php if (!empty($stats['last_submission']) && $stats['last_submission'] !== 'Nunca'): ?>
    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
        <h4 style="margin-bottom: 8px; font-size: 12px;"><?php _e('Último Envío', 'solwed-wp'); ?></h4>
        <p style="margin: 0; font-size: 11px;">
            <?php echo esc_html($stats['last_submission']); ?>
            <?php if (!empty($stats['last_error'])): ?>
                <br><small style="color: #d63638;"><?php echo esc_html($stats['last_error']); ?></small>
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>
    <?php
}

/**
 * Obtener estadísticas para el panel
 */
function get_facturascript_stats(): array {
    static $fs_integration = null;
    if ($fs_integration === null) {
        $fs_integration = new Solwed_FacturaScript_Integration();
    }
    
    return $fs_integration->get_connection_statistics();
}

/**
 * Renderizar la pestaña FacturaScript
 */
function render_facturascript_tab(): void {
    // Procesar formulario
    if ($_POST) {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'save_config':
                    if (wp_verify_nonce($_POST['fs_nonce'], 'fs_config_nonce')) {
                        update_option('solwed_facturascript_enabled', isset($_POST['fs_enabled']));
                        update_option('solwed_facturascript_api_url', sanitize_text_field($_POST['fs_api_url']));
                        update_option('solwed_facturascript_api_token', sanitize_text_field($_POST['fs_api_token']));
                        echo '<div class="notice notice-success"><p>' . __('Configuración guardada correctamente.', 'solwed-wp') . '</p></div>';
                    }
                    break;
                
                case 'set_default':
                    if (wp_verify_nonce($_POST['fs_nonce'], 'fs_config_nonce')) {
                        update_option('solwed_facturascript_api_url', Solwed_FacturaScript_Integration::DEFAULT_API_URL);
                        update_option('solwed_facturascript_api_token', Solwed_FacturaScript_Integration::DEFAULT_API_TOKEN);
                        echo '<div class="notice notice-success"><p>' . __('Configuración por defecto aplicada.', 'solwed-wp') . '</p></div>';
                    }
                    break;
            }
        }
    }
    
    // Obtener configuración actual
    $enabled = get_option('solwed_facturascript_enabled', false);
    $api_url = get_option('solwed_facturascript_api_url', Solwed_FacturaScript_Integration::DEFAULT_API_URL);
    $api_token = get_option('solwed_facturascript_api_token', Solwed_FacturaScript_Integration::DEFAULT_API_TOKEN);
    
    // Obtener estadísticas
    $stats = get_facturascript_stats();
    
    // Inicializar clase si no existe
    static $fs_integration = null;
    if ($fs_integration === null) {
        $fs_integration = new Solwed_FacturaScript_Integration();
    }
    ?>
    
    <style>
    /* Layout específico para el panel FacturaScript */
    .solwed-fs-container {
        display: flex;
        gap: 20px;
        margin: 20px 0;
    }
    .solwed-fs-main {
        flex: 2;
        max-width: 800px;
    }
    .solwed-fs-sidebar {
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
    .fs-switch-container {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
        padding: 15px;
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
    }
    .fs-switch-container h3 {
        margin: 0;
        color: #23282d;
    }
    </style>

    <div class="solwed-fs-container">
        <div class="solwed-fs-main">
            <div class="solwed-form-section">
                <h2>Integración FacturaScripts CRM</h2>
                
                <!-- Switch de activación/desactivación -->
                <div class="fs-switch-container">
                    <h3>Estado del Módulo</h3>
                    <label class="solwed-switch">
                        <input type="checkbox" id="fs-module-toggle" <?php checked($enabled); ?> />
                        <span class="solwed-slider"></span>
                    </label>
                    <span id="fs-status-text"><?php echo $enabled ? 'Activado' : 'Desactivado'; ?></span>
                </div>
                
                <form method="post" id="fs-config-form">
                    <?php wp_nonce_field('fs_config_nonce', 'fs_nonce'); ?>
                    <input type="hidden" name="fs_enabled" id="fs_enabled_hidden" value="<?php echo $enabled ? '1' : '0'; ?>" />
                    
                    <div class="buttons-section">
                        <button type="submit" class="solwed-btn" name="action" value="save_config">
                            💾 <?php _e('Guardar Configuración', 'solwed-wp'); ?>
                        </button>
                        
                        <button type="button" class="solwed-btn" id="test-fs-connection">
                            🔗 <?php _e('Probar Conexión', 'solwed-wp'); ?>
                        </button>
                        
                        <button type="submit" class="solwed-btn" name="action" value="set_default">
                            ⚙️ <?php _e('Config. Por Defecto', 'solwed-wp'); ?>
                        </button>
                    </div>
                    
                    <!-- Configuración de API -->
                    <div id="fs-config-details">
                        <h3><?php _e('Configuración API FacturaScripts', 'solwed-wp'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th><label for="fs_api_url"><?php _e('URL de la API', 'solwed-wp'); ?></label></th>
                                <td>
                                    <input type="url" name="fs_api_url" id="fs_api_url" 
                                           value="<?php echo esc_attr($api_url); ?>" 
                                           class="regular-text" 
                                           placeholder="http://erpdemo.test/api/3" />
                                    <p class="description"><?php _e('URL base de la API de FacturaScripts', 'solwed-wp'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="fs_api_token"><?php _e('Token de API', 'solwed-wp'); ?></label></th>
                                <td>
                                    <input type="text" name="fs_api_token" id="fs_api_token" 
                                           value="<?php echo esc_attr($api_token); ?>" 
                                           class="regular-text" 
                                           placeholder="Token de autenticación" />
                                    <p class="description"><?php _e('Token de autenticación para la API', 'solwed-wp'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </form>
                
                <!-- Funciones adicionales -->
                <div class="solwed-form-section" style="margin-top: 20px;">
                    <h3><?php _e('Funciones Adicionales', 'solwed-wp'); ?></h3>
                    
                    <div style="display: flex; gap: 20px; justify-content: center; padding: 20px;">
                        <button type="button" id="bulk-import-fs" class="solwed-btn">
                            📥 <?php _e('Importar Envíos', 'solwed-wp'); ?>
                        </button>
                    </div>
                    
                    <div id="fs-action-results" style="margin-top: 15px;"></div>
                </div>
                
                <!-- Información sobre el funcionamiento -->
                <div class="solwed-form-section" style="margin-top: 20px;">
                    <h3><?php _e('¿Cómo funciona?', 'solwed-wp'); ?></h3>
                    <ul style="list-style-type: disc; padding-left: 20px;">
                        <li><strong><?php _e('Automático:', 'solwed-wp'); ?></strong> <?php _e('Detecta formularios de Elementor Pro automáticamente', 'solwed-wp'); ?></li>
                        <li><strong><?php _e('Título:', 'solwed-wp'); ?></strong> <?php _e('Usa el título de la página como nombre de la oportunidad', 'solwed-wp'); ?></li>
                        <li><strong><?php _e('Observaciones:', 'solwed-wp'); ?></strong> <?php _e('Incluye todos los datos del formulario', 'solwed-wp'); ?></li>
                        <li><strong><?php _e('Clientes:', 'solwed-wp'); ?></strong> <?php _e('Crea automáticamente contactos vinculados', 'solwed-wp'); ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- PANEL DE ESTADÍSTICAS (Derecha) -->
        <div class="solwed-fs-sidebar">
            <div class="solwed-form-section solwed-sidebar-panel">
                <h3><?php _e('📊 Estado de Integración', 'solwed-wp'); ?></h3>
                <div id="fs-statistics-content">
                    <?php render_facturascript_statistics($stats); ?>
                </div>
                <div class="stats-refresh">
                    <button type="button" id="refresh-fs-stats" class="solwed-btn">
                        🔄 <?php _e('Actualizar', 'solwed-wp'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Toggle del módulo
        $('#fs-module-toggle').change(function() {
            const isEnabled = $(this).is(':checked');
            $('#fs_enabled_hidden').val(isEnabled ? '1' : '0');
            $('#fs-status-text').text(isEnabled ? 'Activado' : 'Desactivado');
        });
        
        // Test de conexión
        $('#test-fs-connection').click(function() {
            const button = $(this);
            const originalText = button.text();
            
            button.prop('disabled', true).text('🔄 Probando...');
            $('#fs-action-results').html('<p>Probando conexión con FacturaScripts...</p>');
            
            $.post(ajaxurl, {
                action: 'solwed_test_fs_connection'
            }, function(response) {
                let message;
                if (response.success) {
                    message = '<div class="notice notice-success"><p>✅ ' + response.data + '</p></div>';
                } else {
                    message = '<div class="notice notice-error"><p>❌ ' + response.data + '</p></div>';
                }
                $('#fs-action-results').html(message).show();
            }).fail(function() {
                $('#fs-action-results').html('<div class="notice notice-error"><p>❌ Error de conexión</p></div>');
            }).always(function() {
                button.prop('disabled', false).text(originalText);
            });
        });
        
        // Importar submissions
        $('#bulk-import-fs').click(function() {
            if (!confirm('¿Importar todas las submissions de Elementor Pro como oportunidades?\n\nEsto puede tomar varios minutos.')) {
                return;
            }
            
            const button = $(this);
            const originalText = button.text();
            
            button.prop('disabled', true).text('🔄 Importando...');
            $('#fs-action-results').html('<p>📥 Procesando submissions...</p>');
            
            $.post(ajaxurl, {
                action: 'solwed_fs_bulk_import'
            }, function(response) {
                let message;
                if (response.success) {
                    message = '<div class="notice notice-success"><p>✅ ' + response.data + '</p></div>';
                } else {
                    message = '<div class="notice notice-error"><p>❌ ' + response.data + '</p></div>';
                }
                $('#fs-action-results').html(message).show();
                
                // Actualizar estadísticas
                $('#refresh-fs-stats').click();
            }).fail(function() {
                $('#fs-action-results').html('<div class="notice notice-error"><p>❌ Error durante la importación</p></div>');
            }).always(function() {
                button.prop('disabled', false).text(originalText);
            });
        });
        
        // Refrescar estadísticas
        $('#refresh-fs-stats').click(function() {
            const button = $(this);
            const originalText = button.text();
            
            button.prop('disabled', true).text('🔄');
            
            $.post(ajaxurl, {
                action: 'solwed_refresh_fs_stats'
            }, function(response) {
                if (response.success) {
                    // Actualizar contenido de estadísticas
                    const stats = response.data;
                    let html = '<table class="stats-table"><tbody>';
                    
                    html += '<tr><td><strong>Módulo Activo</strong></td><td>' + 
                            (stats.enabled ? '<span class="solwed-status-badge sent">✅</span>' : '<span class="solwed-status-badge failed">❌</span>') + 
                            '</td></tr>';
                    
                    html += '<tr><td><strong>API Configurada</strong></td><td>' + 
                            (stats.api_configured ? '<span class="solwed-status-badge sent">✅</span>' : '<span class="solwed-status-badge failed">❌</span>') + 
                            '</td></tr>';
                    
                    html += '<tr><td><strong>Conexión FS</strong></td><td>' + 
                            (stats.connection ? '<span class="solwed-status-badge sent">✅</span>' : '<span class="solwed-status-badge failed">❌</span>') + 
                            '</td></tr>';
                    
                    html += '<tr><td><strong>Elementor Pro</strong></td><td>' + 
                            (stats.elementor_pro ? '<span class="solwed-status-badge sent">✅</span>' : '<span class="solwed-status-badge failed">❌</span>') + 
                            '</td></tr>';
                    
                    html += '<tr><td><strong>Total Envíos</strong></td><td>' + stats.total_submissions + '</td></tr>';
                    html += '<tr><td><strong>Exitosos</strong></td><td>' + stats.successful_submissions + '</td></tr>';
                    html += '<tr><td><strong>Fallidos</strong></td><td>' + stats.failed_submissions + '</td></tr>';
                    html += '<tr><td><strong>Tasa Éxito</strong></td><td>' + stats.success_rate + '%</td></tr>';
                    
                    html += '</tbody></table>';
                    
                    if (stats.last_submission && stats.last_submission !== 'Nunca') {
                        html += '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">';
                        html += '<h4 style="margin-bottom: 8px; font-size: 12px;">Último Envío</h4>';
                        html += '<p style="margin: 0; font-size: 11px;">' + stats.last_submission;
                        if (stats.last_error) {
                            html += '<br><small style="color: #d63638;">' + stats.last_error + '</small>';
                        }
                        html += '</p></div>';
                    }
                    
                    $('#fs-statistics-content').html(html);
                }
            }).always(function() {
                button.prop('disabled', false).text(originalText);
            });
        });
    });
    </script>
    <?php
}

// Instanciar la integración si estamos en el contexto correcto
if (defined('ABSPATH') && !defined('DOING_AJAX')) {
    new Solwed_FacturaScript_Integration();
}
?>
