<?php
/**
 * Dashboard del Admin - Página principal del plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

$solwed_wp = solwed_wp();
$security = $solwed_wp->get_module('security');
$smtp = $solwed_wp->get_module('smtp');
$appearance = $solwed_wp->get_module('appearance');

// Obtener estadísticas de todos los módulos
$security_stats = $security ? $security->get_stats() : [];
$smtp_stats = $smtp ? $smtp->get_stats() : [];

// Estadísticas de editor de código
$editor_settings = [
    'custom_css' => get_option(SOLWED_WP_PREFIX . 'custom_css', ''),
    'custom_js' => get_option(SOLWED_WP_PREFIX . 'custom_js', ''),
    'custom_css_priority' => get_option(SOLWED_WP_PREFIX . 'custom_css_priority', ''),
    'custom_php' => get_option(SOLWED_WP_PREFIX . 'custom_php', '')
];

// Estadísticas de logs
global $wpdb;
$log_prefix = SOLWED_WP_PREFIX;
$logs_stats = [
    'current_blocks' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$log_prefix}failed_attempts WHERE blocked_until > NOW()"), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    'total_emails' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$log_prefix}email_logs"), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    'email_today' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$log_prefix}email_logs WHERE DATE(timestamp) = CURDATE()"), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    'blocks_today' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$log_prefix}security_logs WHERE DATE(timestamp) = CURDATE()") // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
];

// Configuración del banner
$banner_enabled = $appearance ? $appearance->is_banner_enabled() : false;
?>

<style>
.solwed-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}
.solwed-module-card {
    background: white;
    border: 1px solid #dcdcde;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.solwed-module-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}
.solwed-module-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #eee;
}
.solwed-module-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
    font-weight: 600;
    margin: 0;
    color: #2E3536;
}
.solwed-module-status {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
}
.solwed-module-description {
    color: #666;
    font-size: 13px;
    line-height: 1.4;
    margin: 10px 0 15px 0;
    min-height: 35px;
}
.solwed-module-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin: 15px 0;
}
.solwed-stat-box {
    text-align: center;
    padding: 12px 8px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #e9ecef;
}
.solwed-stat-number {
    font-size: 20px;
    font-weight: bold;
    color: #2E3536;
    margin-bottom: 5px;
    display: block;
}
.solwed-stat-label {
    font-size: 11px;
    color: #666;
    margin: 0;
    line-height: 1.2;
}
.solwed-module-actions {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
    text-align: center;
}
.solwed-dashboard-header {
    background: linear-gradient(135deg, #2E3536 0%, #4a5658 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 20px;
    text-align: center;
}
.solwed-dashboard-header h1 {
    margin: 0 0 10px 0;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
}
.solwed-dashboard-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    background: rgba(255,255,255,0.1);
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
}
.solwed-overview-item {
    text-align: center;
    color: rgba(255,255,255,0.9);
}
.solwed-overview-number {
    font-size: 28px;
    font-weight: bold;
    color: #F2E501;
}
.solwed-overview-label {
    font-size: 14px;
    margin-top: 5px;
}
.solwed-info-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 8px;
    padding: 20px;
}
.solwed-info-card h3 {
    color: white;
    margin: 0 0 15px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.solwed-info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid rgba(255,255,255,0.2);
    font-size: 13px;
}
.solwed-info-item:last-child {
    border-bottom: none;
}
.solwed-quick-links {
    display: grid;
    gap: 8px;
    margin-top: 15px;
}
.solwed-quick-link {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: rgba(255,255,255,0.1);
    border-radius: 4px;
    color: white;
    text-decoration: none;
    font-size: 12px;
    transition: background 0.2s ease;
}
.solwed-quick-link:hover {
    background: rgba(255,255,255,0.2);
    color: white;
}
@media (max-width: 1400px) {
    .solwed-dashboard-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 900px) {
    .solwed-dashboard-grid {
        grid-template-columns: 1fr;
    }
}
/* Estilo personalizado para botones con fondo negro */
.solwed-dashboard .button.button-primary {
    background: #2E3536 !important;
    border-color: #2E3536 !important;
    color: white !important;
    text-shadow: none !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2) !important;
    transition: all 0.3s ease !important;
    border-radius: 6px !important;
    padding: 8px 16px !important;
    font-weight: 600 !important;
}
.solwed-dashboard .button.button-primary:hover {
    background: #1a2021 !important;
    border-color: #1a2021 !important;
    color: white !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 8px rgba(0,0,0,0.3) !important;
}
.solwed-dashboard .button.button-primary:active,
.solwed-dashboard .button.button-primary:focus {
    background: #0f1415 !important;
    border-color: #0f1415 !important;
    color: white !important;
    box-shadow: 0 1px 2px rgba(0,0,0,0.3) !important;
    transform: translateY(0) !important;
}
</style>

<div class="wrap solwed-dashboard">
    <!-- Header del Dashboard -->
    <div class="solwed-dashboard-header">
        <h1>
            <img src="<?php echo esc_url(SOLWED_WP_PLUGIN_URL . 'assets/img/LogotipoClaro.png'); ?>" alt="Solwed" style="height: 40px;">
            <?php esc_html_e('Dashboard - Solwed WP', 'solwed-wp'); ?>
        </h1>
        <p style="margin: 0; font-size: 16px; opacity: 0.9;"><?php esc_html_e('Centro de control y monitoreo de todos los módulos', 'solwed-wp'); ?></p>
        
        <div class="solwed-dashboard-overview">
            <div class="solwed-overview-item">
                <div class="solwed-overview-number"><?php echo esc_html($1); ?></div>
                <div class="solwed-overview-label"><?php esc_html_e('Emails Hoy', 'solwed-wp'); ?></div>
            </div>
            <div class="solwed-overview-item">
                <div class="solwed-overview-number"><?php echo esc_html($1); ?></div>
                <div class="solwed-overview-label"><?php esc_html_e('Bloqueos Hoy', 'solwed-wp'); ?></div>
            </div>
            <div class="solwed-overview-item">
                <div class="solwed-overview-number"><?php echo esc_html($1); ?></div>
                <div class="solwed-overview-label"><?php esc_html_e('Bloqueos Activos', 'solwed-wp'); ?></div>
            </div>
            <div class="solwed-overview-item">
                <div class="solwed-overview-number">6</div>
                <div class="solwed-overview-label"><?php esc_html_e('Módulos Disponibles', 'solwed-wp'); ?></div>
            </div>
        </div>
    </div>

    <div class="solwed-dashboard-container">
        <!-- GRID DE MÓDULOS -->
        <div class="solwed-dashboard-grid">

            <!-- Módulo SMTP -->
            <div class="solwed-module-card">
                <div class="solwed-module-header">
                    <h3 class="solwed-module-title">
                        📧 <?php esc_html_e('SMTP', 'solwed-wp'); ?>
                    </h3>
                    <span class="solwed-module-status solwed-status-badge <?php echo ($smtp && $smtp->is_enabled()) ? 'sent' : 'failed'; ?>">
                        <?php echo ($smtp && $smtp->is_enabled()) ? esc_html(__('Activo', 'solwed-wp')) : esc_html(__('Inactivo', 'solwed-wp')); ?>
                    </span>
                </div>
                
                <p class="solwed-module-description"><?php esc_html_e('Configuración del servidor SMTP para el envío de emails desde WordPress.', 'solwed-wp'); ?></p>
                
                <div class="solwed-module-stats">
                    <div class="solwed-stat-box">
                        <span class="solwed-stat-number"><?php echo esc_html($smtp_stats['total_sent'] ?? 0); ?></span>
                        <p class="solwed-stat-label"><?php esc_html_e('Enviados', 'solwed-wp'); ?></p>
                    </div>
                    <div class="solwed-stat-box">
                        <span class="solwed-stat-number"><?php echo esc_html($smtp_stats['total_failed'] ?? 0); ?></span>
                        <p class="solwed-stat-label"><?php esc_html_e('Fallidos', 'solwed-wp'); ?></p>
                    </div>
                    <div class="solwed-stat-box">
                        <span class="solwed-stat-number"><?php echo esc_html($1); ?></span>
                        <p class="solwed-stat-label"><?php esc_html_e('Hoy', 'solwed-wp'); ?></p>
                    </div>
                </div>
                
                <div class="solwed-module-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=solwed-wp-settings&tab=smtp')); ?>" class="button button-primary">
                        <?php esc_html_e('Configurar SMTP', 'solwed-wp'); ?>
                    </a>
                </div>
            </div>

            <!-- Módulo FacturaScript -->
            <?php 
            $facturascript_enabled = get_option(SOLWED_WP_PREFIX . 'facturascript_enabled', '0');
            $fs_stats = [
                'opportunities' => get_option(SOLWED_WP_PREFIX . 'facturascript_total_opportunities', 0),
                'clients' => get_option(SOLWED_WP_PREFIX . 'facturascript_total_clients', 0),
                'today' => get_option(SOLWED_WP_PREFIX . 'facturascript_opportunities_today', 0)
            ];
            ?>
            <div class="solwed-module-card">
                <div class="solwed-module-header">
                    <h3 class="solwed-module-title">
                        🏢 <?php esc_html_e('FacturaScript', 'solwed-wp'); ?>
                    </h3>
                    <span class="solwed-module-status solwed-status-badge <?php echo ($facturascript_enabled === '1') ? 'sent' : 'failed'; ?>">
                        <?php echo ($facturascript_enabled === '1') ? esc_html(__('Activo', 'solwed-wp')) : esc_html(__('Inactivo', 'solwed-wp')); ?>
                    </span>
                </div>
                
                <p class="solwed-module-description"><?php esc_html_e('Integración con FacturaScript CRM para crear oportunidades y clientes automáticamente.', 'solwed-wp'); ?></p>
                
                <div class="solwed-module-stats">
                    <div class="solwed-stat-box">
                        <span class="solwed-stat-number"><?php echo esc_html($fs_stats['opportunities']); ?></span>
                        <p class="solwed-stat-label"><?php esc_html_e('Oportunidades', 'solwed-wp'); ?></p>
                    </div>
                    <div class="solwed-stat-box">
                        <span class="solwed-stat-number"><?php echo esc_html($fs_stats['clients']); ?></span>
                        <p class="solwed-stat-label"><?php esc_html_e('Clientes', 'solwed-wp'); ?></p>
                    </div>
                    <div class="solwed-stat-box">
                        <span class="solwed-stat-number"><?php echo esc_html($fs_stats['today']); ?></span>
                        <p class="solwed-stat-label"><?php esc_html_e('Hoy', 'solwed-wp'); ?></p>
                    </div>
                </div>
                
                <div class="solwed-module-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=solwed-wp-settings&tab=facturascript')); ?>" class="button button-primary">
                        <?php esc_html_e('Configurar CRM', 'solwed-wp'); ?>
                    </a>
                </div>
            </div>

            <!-- Módulo Apariencia -->
            <div class="solwed-module-card">
                <div class="solwed-module-header">
                    <h3 class="solwed-module-title">
                        🎨 <?php esc_html_e('Apariencia', 'solwed-wp'); ?>
                    </h3>
                    <span class="solwed-module-status solwed-status-badge <?php echo esc_html($1); ?>">
                        <?php echo esc_html($1); ?>
                    </span>
                </div>
                
                <p class="solwed-module-description"><?php esc_html_e('Configuración del banner inferior de Solwed y personalización de la apariencia del sitio.', 'solwed-wp'); ?></p>
                
                <div class="solwed-module-stats">
                    <div class="solwed-stat-box">
                        <span class="solwed-stat-number"><?php echo esc_html($1); ?></span>
                        <p class="solwed-stat-label"><?php esc_html_e('Banner', 'solwed-wp'); ?></p>
                    </div>
                    <div class="solwed-stat-box">
                        <span class="solwed-stat-number" style="font-size: 12px;"><?php echo esc_html(ucfirst(get_option(SOLWED_WP_PREFIX . 'banner_position', 'bottom'))); ?></span>
                        <p class="solwed-stat-label"><?php esc_html_e('Posición', 'solwed-wp'); ?></p>
                    </div>
                    <div class="solwed-stat-box">
                        <span class="solwed-stat-number" style="font-size: 12px;"><?php echo esc_html(ucfirst(get_option(SOLWED_WP_PREFIX . 'banner_animation', 'slide'))); ?></span>
                        <p class="solwed-stat-label"><?php esc_html_e('Animación', 'solwed-wp'); ?></p>
                    </div>
                </div>
                
                <div class="solwed-module-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=solwed-wp-settings&tab=appearance')); ?>" class="button button-primary">
                        <?php esc_html_e('Configurar Apariencia', 'solwed-wp'); ?>
                    </a>
                </div>
            </div>

            <!-- Módulo Seguridad -->
            <div class="solwed-module-card">
                <div class="solwed-module-header">
                    <h3 class="solwed-module-title">
                        🔒 <?php esc_html_e('Seguridad', 'solwed-wp'); ?>
                    </h3>
                    <span class="solwed-module-status solwed-status-badge <?php echo ($security && $security->is_enabled()) ? 'sent' : 'failed'; ?>">
                        <?php echo ($security && $security->is_enabled()) ? esc_html(__('Protegido', 'solwed-wp')) : esc_html(__('Desprotegido', 'solwed-wp')); ?>
                    </span>
                </div>
                
                <p class="solwed-module-description"><?php esc_html_e('Protección contra ataques de fuerza bruta, SSL forzado y URL de login personalizada.', 'solwed-wp'); ?></p>
                
                <div class="solwed-module-stats">
                    <div class="solwed-stat-box">
                        <span class="solwed-stat-number"><?php echo esc_html($security_stats['total_failed_attempts'] ?? 0); ?></span>
                        <p class="solwed-stat-label"><?php esc_html_e('Intentos Fallidos', 'solwed-wp'); ?></p>
                    </div>
                    <div class="solwed-stat-box">
                        <span class="solwed-stat-number"><?php echo esc_html($1); ?></span>
                        <p class="solwed-stat-label"><?php esc_html_e('IPs Bloqueadas', 'solwed-wp'); ?></p>
                    </div>
                    <div class="solwed-stat-box">
                        <span class="solwed-stat-number"><?php echo esc_html($1); ?></span>
                        <p class="solwed-stat-label"><?php esc_html_e('Bloqueos Hoy', 'solwed-wp'); ?></p>
                    </div>
                </div>
                
                <div class="solwed-module-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=solwed-wp-settings&tab=security')); ?>" class="button button-primary">
                        <?php esc_html_e('Configurar Seguridad', 'solwed-wp'); ?>
                    </a>
                </div>
            </div>

            <!-- Módulo Editor de Código -->
            <?php 
            $total_code_lines = 0;
            $active_sections = 0;
            if (!empty($editor_settings['custom_css'])) { $total_code_lines += substr_count($editor_settings['custom_css'], "\n") + 1; $active_sections++; }
            if (!empty($editor_settings['custom_css_priority'])) { $total_code_lines += substr_count($editor_settings['custom_css_priority'], "\n") + 1; $active_sections++; }
            if (!empty($editor_settings['custom_js'])) { $total_code_lines += substr_count($editor_settings['custom_js'], "\n") + 1; $active_sections++; }
            if (!empty($editor_settings['custom_php'])) { $total_code_lines += substr_count($editor_settings['custom_php'], "\n") + 1; $active_sections++; }
            ?>
            <div class="solwed-module-card">
                <div class="solwed-module-header">
                    <h3 class="solwed-module-title">
                        👨‍💻 <?php esc_html_e('Editor de Código', 'solwed-wp'); ?>
                    </h3>
                    <span class="solwed-module-status solwed-status-badge <?php echo ($total_code_lines > 0) ? 'sent' : 'failed'; ?>">
                        <?php echo ($total_code_lines > 0) ? esc_html(__('Código Activo', 'solwed-wp')) : esc_html(__('Sin Código', 'solwed-wp')); ?>
                    </span>
                </div>
                
                <p class="solwed-module-description"><?php esc_html_e('Editor avanzado para añadir CSS, JavaScript y PHP personalizado al sitio web.', 'solwed-wp'); ?></p>
                
                <div class="solwed-module-stats">
                    <div class="solwed-stat-box">
                        <span class="solwed-stat-number"><?php echo esc_html($1); ?></span>
                        <p class="solwed-stat-label"><?php esc_html_e('Líneas de Código', 'solwed-wp'); ?></p>
                    </div>
                    <div class="solwed-stat-box">
                        <span class="solwed-stat-number"><?php echo esc_html($1); ?></span>
                        <p class="solwed-stat-label"><?php esc_html_e('Secciones Activas', 'solwed-wp'); ?></p>
                    </div>
                    <div class="solwed-stat-box">
                        <span class="solwed-stat-number"><?php echo !empty($editor_settings['custom_php']) ? '⚠️' : '✓'; ?></span>
                        <p class="solwed-stat-label"><?php esc_html_e('PHP Status', 'solwed-wp'); ?></p>
                    </div>
                </div>
                
                <div class="solwed-module-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=solwed-wp-settings&tab=code-editor')); ?>" class="button button-primary">
                        <?php esc_html_e('Editor de Código', 'solwed-wp'); ?>
                    </a>
                </div>
            </div>

            <!-- Módulo Logs -->
            <div class="solwed-module-card">
                <div class="solwed-module-header">
                    <h3 class="solwed-module-title">
                        📋 <?php esc_html_e('Logs del Sistema', 'solwed-wp'); ?>
                    </h3>
                    <span class="solwed-module-status solwed-status-badge <?php echo ($logs_stats['current_blocks'] > 0) ? 'failed' : 'sent'; ?>">
                        <?php echo ($logs_stats['current_blocks'] > 0) ? esc_html(__('Amenazas Activas', 'solwed-wp')) : esc_html(__('Sin Amenazas', 'solwed-wp')); ?>
                    </span>
                </div>
                
                <p class="solwed-module-description"><?php esc_html_e('Registros de actividad del sistema: emails enviados, bloqueos de seguridad y logs de eventos.', 'solwed-wp'); ?></p>
                
                <div class="solwed-module-stats">
                    <div class="solwed-stat-box">
                        <span class="solwed-stat-number"><?php echo esc_html($1); ?></span>
                        <p class="solwed-stat-label"><?php esc_html_e('Total Emails', 'solwed-wp'); ?></p>
                    </div>
                    <div class="solwed-stat-box">
                        <span class="solwed-stat-number"><?php echo esc_html($1); ?></span>
                        <p class="solwed-stat-label"><?php esc_html_e('Bloqueos Activos', 'solwed-wp'); ?></p>
                    </div>
                    <div class="solwed-stat-box">
                        <span class="solwed-stat-number"><?php echo esc_html($1); ?></span>
                        <p class="solwed-stat-label"><?php esc_html_e('Actividad Hoy', 'solwed-wp'); ?></p>
                    </div>
                </div>
                
                <div class="solwed-module-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=solwed-wp-settings&tab=logs')); ?>" class="button button-primary">
                        <?php esc_html_e('Ver Logs', 'solwed-wp'); ?>
                    </a>
                </div>
            </div>

            <!-- Tarjeta 1: Accesos Rápidos -->
            <div class="solwed-module-card solwed-info-card">
                <div class="solwed-module-header">
                    <h3 class="solwed-module-title">🔗 <?php esc_html_e('Accesos Rápidos', 'solwed-wp'); ?></h3>
                </div>
                
                <p ><?php esc_html_e('Enlaces directos a todas las configuraciones del plugin.', 'solwed-wp'); ?></p>
                
                <div class="solwed-quick-links">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=solwed-wp-settings&tab=smtp')); ?>" class="solwed-quick-link">
                        📧 <?php esc_html_e('Configurar SMTP', 'solwed-wp'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=solwed-wp-settings&tab=facturascript')); ?>" class="solwed-quick-link">
                        🏢 <?php esc_html_e('FacturaScript CRM', 'solwed-wp'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=solwed-wp-settings&tab=security')); ?>" class="solwed-quick-link">
                        🔒 <?php esc_html_e('Configurar Seguridad', 'solwed-wp'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=solwed-wp-settings&tab=appearance')); ?>" class="solwed-quick-link">
                        🎨 <?php esc_html_e('Configurar Apariencia', 'solwed-wp'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=solwed-wp-settings&tab=code-editor')); ?>" class="solwed-quick-link">
                        👨‍💻 <?php esc_html_e('Editor de Código', 'solwed-wp'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=solwed-wp-settings&tab=logs')); ?>" class="solwed-quick-link">
                        📋 <?php esc_html_e('Ver Logs', 'solwed-wp'); ?>
                    </a>
                </div>

                

                <?php if ($logs_stats['current_blocks'] > 0): ?>
                <div style="margin-top: 15px; padding: 10px; background: rgba(244, 67, 54, 0.2); border-radius: 4px; border-left: 4px solid #f44336;">
                    <strong style="color: #f44336;">⚠️ <?php esc_html_e('Alerta de Seguridad', 'solwed-wp'); ?></strong><br>
                    <small><?php echo esc_html($1); ?> <?php esc_html_e('IPs bloqueadas activamente', 'solwed-wp'); ?></small>
                </div>
                <?php endif; ?>

                <?php if (!empty($editor_settings['custom_php'])): ?>
                <div style="margin-top: 15px; padding: 10px; background: rgba(255, 152, 0, 0.2); border-radius: 4px; border-left: 4px solid #ff9800;">
                    <strong style="color: #ff9800;">⚠️ <?php esc_html_e('PHP Personalizado', 'solwed-wp'); ?></strong><br>
                    <small><?php esc_html_e('Código PHP activo en el sistema', 'solwed-wp'); ?></small>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tarjeta 2: Información del Sistema -->
            <div class="solwed-module-card solwed-info-card">
                <div class="solwed-module-header">
                    <h3 class="solwed-module-title">ℹ️ <?php esc_html_e('Información del Sistema', 'solwed-wp'); ?></h3>
                </div>
                
                <p><?php esc_html_e('Detalles técnicos del servidor y software instalado.', 'solwed-wp'); ?></p>
                
                <div class="solwed-info-item">
                    <span><?php esc_html_e('Plugin Solwed WP:', 'solwed-wp'); ?></span>
                    <strong>v<?php echo esc_html(SOLWED_WP_VERSION); ?></strong>
                </div>
                <div class="solwed-info-item">
                    <span><?php esc_html_e('WordPress:', 'solwed-wp'); ?></span>
                    <strong>v<?php echo esc_html(get_bloginfo('version')); ?></strong>
                </div>
                <div class="solwed-info-item">
                    <span><?php esc_html_e('PHP:', 'solwed-wp'); ?></span>
                    <strong>v<?php echo PHP_VERSION; ?></strong>
                </div>
                <div class="solwed-info-item">
                    <span><?php esc_html_e('MySQL:', 'solwed-wp'); ?></span>
                    <strong>v<?php echo esc_html($1); ?></strong>
                </div>
                <?php if (class_exists('WooCommerce')): ?>
                <div class="solwed-info-item">
                    <span><?php esc_html_e('WooCommerce:', 'solwed-wp'); ?></span>
                    <strong>v<?php echo esc_html(WC_VERSION); ?></strong>
                </div>
                <?php endif; ?>
                <div class="solwed-info-item">
                    <span><?php esc_html_e('Servidor:', 'solwed-wp'); ?></span>
                    <strong><?php echo esc_html(substr(sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'] ?? '')), 0, 20)); ?></strong>
                </div>
                <div class="solwed-info-item">
                    <span><?php esc_html_e('Memoria PHP:', 'solwed-wp'); ?></span>
                    <strong><?php echo esc_html(ini_get('memory_limit')); ?></strong>
                </div>
                <div class="solwed-info-item">
                    <span><?php esc_html_e('Última actualización:', 'solwed-wp'); ?></span>
                    <strong><?php echo esc_html(date_i18n('d/m/Y H:i')); ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>



<style>
.solwed-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.solwed-card-full {
    grid-column: 1 / -1;
}

.solwed-card {
    background: white;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.solwed-card h2 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #2E3536;
    border-bottom: 2px solid #F2E501;
    padding-bottom: 10px;
}

.solwed-status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.solwed-status-item {
    display: flex;
    align-items: center;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 6px;
    border-left: 4px solid #ddd;
}

.solwed-status-item .solwed-status-icon.active {
    color: #46B450;
}

.solwed-status-item .solwed-status-icon.inactive {
    color: #D63638;
}

.solwed-status-icon {
    font-size: 24px;
    margin-right: 15px;
}

.solwed-status-content h3 {
    margin: 0 0 5px 0;
    font-size: 16px;
}

.solwed-status-content p {
    margin: 0;
    color: #666;
    font-size: 14px;
}

.solwed-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.solwed-stat-item {
    text-align: center;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 6px;
}

.solwed-stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #2E3536;
    line-height: 1;
}

.solwed-stat-label {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

.solwed-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.solwed-table th,
.solwed-table td {
    padding: 8px 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.solwed-table th {
    background: #f9f9f9;
    font-weight: 600;
    color: #2E3536;
}

.solwed-status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.solwed-status-badge.configured,
.solwed-status-badge.sent {
    background: #d1eddb;
    color: #0f5132;
}

.solwed-status-badge.not-configured,
.solwed-status-badge.failed {
    background: #f8d7da;
    color: #721c24;
}

.solwed-quick-actions {
    display: grid;
    gap: 10px;
}

.solwed-quick-action {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    background: #2E3536;
    color: white !important;
    text-decoration: none;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.solwed-quick-action:hover {
    background: #3A4344;
    transform: translateY(-2px);
}

.solwed-quick-action.success {
    background: #46B450;
}

.solwed-quick-action.success:hover {
    background: #3da946;
}

.solwed-quick-action.warning {
    background: #FFB900;
    color: #2E3536 !important;
}

.solwed-quick-action.warning:hover {
    background: #e6a600;
}

.solwed-quick-action .dashicons {
    margin-right: 8px;
}

.solwed-info-table {
    width: 100%;
}

.solwed-info-table td {
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}
</style>
