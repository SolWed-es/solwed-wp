<?php

// Función helper para cargar estilos comunes de las pestañas
function solwed_load_tab_styles() {
    static $styles_loaded = false;
    
    if (!$styles_loaded) {
        echo '<style>
        /* Solo estilos específicos que no están en admin.css */
        .solwed-switch { position: relative; display: inline-block; width: 50px; height: 24px; }
        .solwed-switch input { opacity: 0; width: 0; height: 0; }
        .solwed-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: #ccc; transition: 0.3s; border-radius: 24px; }
        .solwed-slider:before { position: absolute; content: ""; height: 18px; width: 18px;
            left: 3px; bottom: 3px; background-color: white; transition: 0.3s; border-radius: 50%; }
        input:checked + .solwed-slider { background-color: var(--solwed-accent); }
        input:checked + .solwed-slider:before { transform: translateX(26px); }
        .solwed-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px; margin: 20px 0; }
        .solwed-status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .solwed-status-badge.sent { background: #d4edda; color: #155724; }
        .solwed-status-badge.failed { background: #f8d7da; color: #721c24; }
        .solwed-smtp-presets .button { margin-right: 10px; margin-bottom: 10px; }
        .solwed-banner-preview { margin: 15px 0; border: 2px dashed #ddd; padding: 20px; 
            background: #f9f9f9; border-radius: 6px; }
        </style>';
        $styles_loaded = true;
    }
}

/**
 * Página de Configuración del Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'smtp';
$solwed_wp = solwed_wp();

// Manejar toggle del banner
if (
    isset($_GET['action']) &&
    $_GET['action'] === 'solwed_toggle_banner' &&
    isset($_GET['_wpnonce']) &&
    wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'solwed_toggle_banner') &&
    current_user_can('manage_options')
) {
    $appearance = $solwed_wp->get_module('appearance');
    if ($appearance) {
        $appearance->toggle_banner();
        wp_redirect(admin_url('admin.php?page=solwed-wp-settings&tab=appearance'));
        exit;
    }
}

$tabs = [
    'smtp' => [
        'title' => __('SMTP', 'solwed-wp'),
        'icon' => 'dashicons-email-alt'
    ],
    'facturascript' => [
        'title' => __('FacturaScript', 'solwed-wp'),
        'icon' => 'dashicons-businessman'
    ],
    'appearance' => [
        'title' => __('Apariencia', 'solwed-wp'),
        'icon' => 'dashicons-admin-appearance'
    ],
    'security' => [
        'title' => __('Seguridad', 'solwed-wp'),
        'icon' => 'dashicons-shield'
    ],
    'code-editor' => [
        'title' => __('Editor de Código', 'solwed-wp'),
        'icon' => 'dashicons-editor-code'
    ],
    'logs' => [
        'title' => __('Logs', 'solwed-wp'),
        'icon' => 'dashicons-list-view'
    ]
];

// Validar pestaña actual frente a la lista permitida; fallback seguro
if (!array_key_exists($current_tab, $tabs)) {
    $current_tab = 'smtp';
}
?>

<div class="wrap solwed-settings">
    <h1>
        <img src="<?php echo esc_url(SOLWED_WP_PLUGIN_URL . 'assets/img/LototipoOscuro.png'); ?>" alt="Solwed" style="height: 32px; vertical-align: middle; margin-right: 10px;">
        <?php esc_html_e('Configuración - Solwed WP', 'solwed-wp'); ?>
    </h1>

    <nav class="nav-tab-wrapper">
        <?php foreach ($tabs as $tab_key => $tab_info): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=solwed-wp-settings&tab=' . $tab_key)); ?>" 
               class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons <?php echo esc_attr($tab_info['icon']); ?>"></span>
                <?php echo esc_html($tab_info['title']); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="tab-content">
        <?php
        // Cargar estilos comunes una sola vez
        solwed_load_tab_styles();
        
        // Sistema optimizado de carga de pestañas
        $effective_tab = $current_tab; // no mutar $current_tab para que la navegación siga coherente
        $tab_file = SOLWED_WP_PLUGIN_DIR . 'includes/admin/tabs/' . sanitize_file_name($effective_tab) . '.php';
        $did_fallback = false;

        // Verificar si la pestaña existe, sino usar smtp como default solo para render
        if (!file_exists($tab_file)) {
            $effective_tab = 'smtp';
            $tab_file = SOLWED_WP_PLUGIN_DIR . 'includes/admin/tabs/smtp.php';
            $did_fallback = true;
        }
        
    if (file_exists($tab_file)) {
            // Los archivos ya están incluidos desde el plugin principal
            // Solo necesitamos llamar a la función de renderizado
            $render_function = 'render_' . str_replace('-', '_', $effective_tab) . '_tab';

            if (function_exists($render_function)) {
                // Capturar salida para detectar casos en los que no se imprime nada
                ob_start();
                call_user_func($render_function);
                $rendered = ob_get_clean();
                if (trim($rendered) === '') {
                    if (current_user_can('manage_options')) {
                        echo '<div class="notice notice-warning"><p>';
                        /* translators: %1$s: tab name, %2$s: render function name */
                        echo sprintf(esc_html(__('La pestaña "%1$s" no generó contenido visible. Revisa la función %2$s.', 'solwed-wp')), esc_html($effective_tab), esc_html($render_function));
                        echo '</p></div>';
                    }
                } else {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $rendered is already escaped output from tab functions
                    echo $rendered;
                }
            } else {
                // Si no existe la función, intentar incluir el archivo específico
                include_once $tab_file;
                
                if (function_exists($render_function)) {
                    ob_start();
                    call_user_func($render_function);
                    $rendered = ob_get_clean();
                    if (trim($rendered) === '') {
                        if (current_user_can('manage_options')) {
                            echo '<div class="notice notice-warning"><p>';
                            /* translators: %1$s: tab name, %2$s: tab file path */
                            echo sprintf(esc_html(__('La pestaña "%1$s" no generó contenido visible tras incluir el archivo. Revisa %2$s.', 'solwed-wp')), esc_html($effective_tab), esc_html($tab_file));
                            echo '</p></div>';
                        }
                    } else {
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $rendered is already escaped output from tab functions
                        echo $rendered;
                    }
                } else {
                    echo '<div class="notice notice-error"><p>';
                    /* translators: %1$s: tab name, %2$s: render function name */
                    echo sprintf(esc_html(__('Error: No se pudo cargar la pestaña %1$s. Función %2$s no encontrada.', 'solwed-wp')), 
                               esc_html($effective_tab), esc_html($render_function));
                    echo '</p></div>';
                }
            }
            // Silenciar avisos de fallback para no mostrar mensajes innecesarios
        } else {
            echo '<div class="notice notice-error"><p>';
            /* translators: %s: tab name */
            echo sprintf(esc_html(__('Error: Archivo de pestaña %s no encontrado.', 'solwed-wp')), esc_html($effective_tab));
            echo '</p></div>';
        }
        ?>
    </div>
</div>

<?php
// Todo el contenido de las pestañas se ha movido a sus respectivos archivos en la carpeta /tabs
?>

<style>
.solwed-settings .nav-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.solwed-form-section {
    background: white;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.solwed-form-section h2 {
    margin-top: 0;
    color: #2E3536;
    border-bottom: 2px solid #F2E501;
    padding-bottom: 10px;
}

.solwed-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.solwed-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.solwed-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.3s;
    border-radius: 24px;
}

.solwed-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

input:checked + .solwed-slider {
    background-color: #F2E501;
}

input:checked + .solwed-slider:before {
    transform: translateX(26px);
}

.solwed-btn {
    background: #2E3536 !important;
    border-color: #2E3536 !important;
    color: white !important;
}

.solwed-btn:hover {
    background: #3A4344 !important;
    border-color: #3A4344 !important;
}

.solwed-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.solwed-stat-card {
    text-align: center;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
    border: 1px solid #ddd;
}

.solwed-stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #2E3536;
    line-height: 1;
}

.solwed-stat-label {
    font-size: 14px;
    color: #666;
    margin-top: 8px;
}

.solwed-smtp-presets {
    margin: 15px 0;
}

.solwed-smtp-presets .button {
    margin-right: 10px;
    margin-bottom: 10px;
}

.solwed-banner-preview {
    margin: 15px 0;
    border: 2px dashed #ddd;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 6px;
}

.solwed-form-actions {
    margin-top: 20px;
}
</style>
