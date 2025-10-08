<?php
/**
 * Pestaña de Apariencia
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase de Apariencia Unificada - Solwed WP Plugin
 */
class Solwed_Appearance_Unified {
    
    private $config;

    public function __construct() {
        $this->config = [
            'banner_enabled' => get_option(SOLWED_WP_PREFIX . 'banner_enabled', '1'),
            'banner_text' => get_option(SOLWED_WP_PREFIX . 'banner_text', 'Desarrollo Web Profesional'),
            'banner_company_url' => get_option(SOLWED_WP_PREFIX . 'banner_company_url', ''),
            'banner_text_color' => get_option(SOLWED_WP_PREFIX . 'banner_text_color', '#ffffff'),
            'banner_background_color' => get_option(SOLWED_WP_PREFIX . 'banner_background_color', '#2E3536'),
            'banner_position' => get_option(SOLWED_WP_PREFIX . 'banner_position', 'bottom'),
            'banner_animation' => get_option(SOLWED_WP_PREFIX . 'banner_animation', 'slide')
        ];
    }

    public function init(): void {
        if ($this->config['banner_enabled'] === '1') {
            add_action('wp_footer', [$this, 'render_banner'], 999);
        }
    }

    public function is_banner_enabled(): bool {
        return $this->config['banner_enabled'] === '1';
    }

    public function get_banner_html(bool $is_preview = false): string {
        $banner_text = $this->config['banner_text'];
        $company_url = $this->config['banner_company_url'];
        
        $container_style = $is_preview ? $this->get_banner_preview_css() : '';
        $link_style = $is_preview ? $this->get_banner_link_styles() : '';
        
        ob_start();
        ?>
        <div style="<?php echo esc_attr($container_style); ?>">
            <div class="solwed-banner-content">
                <?php if (!empty($company_url)): ?>
                    <span>
                        <a href="<?php echo esc_url($company_url); ?>" target="_blank" rel="noopener"
                           <?php echo $is_preview ? ' style="' . esc_attr($link_style) . '"' : ''; ?>>
                            <?php echo esc_html($banner_text); ?>
                        </a> | 
                        Powered by <a href="https://solwed.es" target="_blank" rel="noopener"
                           <?php echo $is_preview ? ' style="' . esc_attr($link_style) . '"' : ''; ?>>Solwed.es✌️</a>
                    </span>
                <?php else: ?>
                    <span>
                        <?php echo esc_html($banner_text); ?> | 
                        Powered by <a href="https://solwed.es" target="_blank" rel="noopener"
                           <?php echo $is_preview ? ' style="' . esc_attr($link_style) . '"' : ''; ?>>Solwed.es✌️</a>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_banner_preview_css(): string {
        $background_color = $this->config['banner_background_color'];
        $text_color = $this->config['banner_text_color'];
        
        return "background: linear-gradient(135deg, {$background_color} 0%, " . 
               $this->adjust_color_brightness($background_color, 20) . " 100%); color: {$text_color}; " .
               "padding: 15px 30px; text-align: center; border-radius: 6px; display: flex; " .
               "align-items: center; justify-content: center; gap: 15px; font-family: 'Orbitron', monospace; " .
               "font-weight: 100; line-height: 1.2; letter-spacing: 2px;";
    }

    public function get_banner_link_styles(): string {
        return "color: #F2E501; text-decoration: none; font-weight: 100; letter-spacing: 2px;";
    }

    private function adjust_color_brightness(string $hex_color, int $percent): string {
        $hex_color = ltrim($hex_color, '#');
        $r = hexdec(substr($hex_color, 0, 2));
        $g = hexdec(substr($hex_color, 2, 2));
        $b = hexdec(substr($hex_color, 4, 2));
        
        $r = min(255, max(0, $r + ($r * $percent / 100)));
        $g = min(255, max(0, $g + ($g * $percent / 100)));
        $b = min(255, max(0, $b + ($b * $percent / 100)));
        
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    public function toggle_banner(): bool {
        $current_state = $this->is_banner_enabled();
        $new_state = $current_state ? '0' : '1';
        
        $result = update_option(SOLWED_WP_PREFIX . 'banner_enabled', $new_state);
        
        if ($result) {
            $this->config['banner_enabled'] = $new_state;
        }
        
        return $result;
    }

    /**
     * Guardar configuración del banner
     */
    public function save_settings(): bool {
        // Verificar nonce de seguridad
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'solwed_settings')) {
            return false;
        }

        // Verificar permisos
        if (!current_user_can('manage_options')) {
            return false;
        }

        // Actualizar configuraciones
        $updated = true;
        
        // Banner habilitado/deshabilitado
        $banner_enabled = isset($_POST['banner_enabled']) ? '1' : '0';
        $updated &= update_option(SOLWED_WP_PREFIX . 'banner_enabled', $banner_enabled);

        // Texto del banner
        if (isset($_POST['banner_text'])) {
            $banner_text = sanitize_text_field(wp_unslash($_POST['banner_text']));
            $updated &= update_option(SOLWED_WP_PREFIX . 'banner_text', $banner_text);
        }

        // URL de la empresa
        if (isset($_POST['banner_company_url'])) {
            $company_url = esc_url_raw(wp_unslash($_POST['banner_company_url']));
            $updated &= update_option(SOLWED_WP_PREFIX . 'banner_company_url', $company_url);
        }

        // Color del texto (priorizar el hex manual)
        $text_color = '#ffffff'; // valor por defecto
        if (!empty($_POST['banner_text_color_hex'])) {
            $text_color = sanitize_hex_color(wp_unslash($_POST['banner_text_color_hex']));
        } elseif (!empty($_POST['banner_text_color'])) {
            $text_color = sanitize_hex_color(wp_unslash($_POST['banner_text_color']));
        }
        $updated &= update_option(SOLWED_WP_PREFIX . 'banner_text_color', $text_color);

        // Color de fondo (priorizar el hex manual)
        $bg_color = '#2E3536'; // valor por defecto
        if (!empty($_POST['banner_background_color_hex'])) {
            $bg_color = sanitize_hex_color(wp_unslash($_POST['banner_background_color_hex']));
        } elseif (!empty($_POST['banner_background_color'])) {
            $bg_color = sanitize_hex_color(wp_unslash($_POST['banner_background_color']));
        }
        $updated &= update_option(SOLWED_WP_PREFIX . 'banner_background_color', $bg_color);

        // Posición del banner
        if (isset($_POST['banner_position'])) {
            $position = in_array(sanitize_text_field(wp_unslash($_POST['banner_position'])), ['top', 'bottom']) ? sanitize_text_field(wp_unslash($_POST['banner_position'])) : 'bottom';
            $updated &= update_option(SOLWED_WP_PREFIX . 'banner_position', $position);
        }

        // Animación del banner
        if (isset($_POST['banner_animation'])) {
            $animation = in_array(sanitize_text_field(wp_unslash($_POST['banner_animation'])), ['none', 'fade', 'slide', 'bounce']) ? sanitize_text_field(wp_unslash($_POST['banner_animation'])) : 'slide';
            $updated &= update_option(SOLWED_WP_PREFIX . 'banner_animation', $animation);
        }

        // Actualizar configuración local
        if ($updated) {
            $this->config = [
                'banner_enabled' => $banner_enabled,
                'banner_text' => get_option(SOLWED_WP_PREFIX . 'banner_text', 'Desarrollo Web Profesional'),
                'banner_company_url' => get_option(SOLWED_WP_PREFIX . 'banner_company_url', ''),
                'banner_text_color' => get_option(SOLWED_WP_PREFIX . 'banner_text_color', '#ffffff'),
                'banner_background_color' => get_option(SOLWED_WP_PREFIX . 'banner_background_color', '#2E3536'),
                'banner_position' => get_option(SOLWED_WP_PREFIX . 'banner_position', 'bottom'),
                'banner_animation' => get_option(SOLWED_WP_PREFIX . 'banner_animation', 'slide')
            ];
        }

        return $updated;
    }

    public function get_stats(): array {
        return [
            'banner_enabled' => $this->is_banner_enabled(),
            'banner_text' => $this->config['banner_text'],
            'banner_position' => $this->config['banner_position'],
            'banner_background_color' => $this->config['banner_background_color'],
            'banner_text_color' => $this->config['banner_text_color'],
            'banner_animation' => $this->config['banner_animation']
        ];
    }

    public function get_banner_css(): string {
        $background_color = $this->config['banner_background_color'];
        $text_color = $this->config['banner_text_color'];
        $position = $this->config['banner_position'];
        $animation = $this->config['banner_animation'];
        
        $css = "
        .solwed-banner {
            position: fixed;
            " . ($position === 'top' ? 'top: 0;' : 'bottom: 0;') . "
            left: 0;
            width: 100%;
            background: linear-gradient(135deg, {$background_color} 0%, " . $this->adjust_color_brightness($background_color, 20) . " 100%);
            color: {$text_color};
            padding: 10px 20px;
            text-align: center;
            font-family: 'Orbitron', monospace;
            font-weight: 100;
            font-size: 12px;
            line-height: 1.2;
            letter-spacing: 1px;
            z-index: 9999;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }
        .solwed-banner a {
            color: #F2E501;
            text-decoration: none;
            font-weight: 100;
        }
        .solwed-banner a:hover {
            text-decoration: underline;
        }";
        
        if ($animation !== 'none') {
            $css .= "
            .solwed-banner {
                animation: solwed-banner-{$animation} 1s ease-out;
            }";
            
            switch ($animation) {
                case 'fade':
                    $css .= "
                    @keyframes solwed-banner-fade {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }";
                    break;
                case 'slide':
                    $css .= "
                    @keyframes solwed-banner-slide {
                        from { transform: translateY(" . ($position === 'top' ? '-100%' : '100%') . "); }
                        to { transform: translateY(0); }
                    }";
                    break;
                case 'bounce':
                    $css .= "
                    @keyframes solwed-banner-bounce {
                        0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
                        40% { transform: translateY(-10px); }
                        60% { transform: translateY(-5px); }
                    }";
                    break;
            }
        }
        
        return $css;
    }

    public function render_banner(): void {
        if (!$this->is_banner_enabled()) {
            return;
        }

        echo '<div class="solwed-banner">' . wp_kses_post($this->get_banner_html()) . '</div>';
    }
}

function render_appearance_tab() {
    // Cargar fuente Orbitron para la vista previa
    wp_enqueue_style('orbitron-font-admin', 
        'https://fonts.googleapis.com/css2?family=Orbitron:wght@100;300;400;500;600;700&display=swap',
        [], SOLWED_WP_VERSION);
    
    $appearance = solwed_wp()->get_module('appearance');
    $banner_enabled = $appearance ? $appearance->is_banner_enabled() : false;
    $banner_settings = [
        'text' => get_option(SOLWED_WP_PREFIX . 'banner_text', 'Desarrollo Web Profesional'),
        'company_url' => get_option(SOLWED_WP_PREFIX . 'banner_company_url', ''),
        'text_color' => get_option(SOLWED_WP_PREFIX . 'banner_text_color', '#ffffff'),
        'bg_color' => get_option(SOLWED_WP_PREFIX . 'banner_background_color', '#2E3536'),
        'position' => get_option(SOLWED_WP_PREFIX . 'banner_position', 'bottom'),
        'animation' => get_option(SOLWED_WP_PREFIX . 'banner_animation', 'slide')
    ];
    ?>

    <style>
    .solwed-appearance-container {
        display: flex;
        gap: 20px;
        margin: 20px 0;
    }
    .solwed-appearance-main {
        flex: 2;
        max-width: 800px;
    }
    .solwed-appearance-sidebar {
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

    <div class="solwed-appearance-container">
        <!-- FORMULARIO PRINCIPAL (Izquierda) -->
        <div class="solwed-appearance-main">
            <form method="post" action="">
                <?php wp_nonce_field('solwed_settings'); ?>
                <input type="hidden" name="solwed_action" value="save_appearance">
                <div class="solwed-form-section">
                    <h2><?php esc_html_e('🎨 Banner Inferior', 'solwed-wp'); ?></h2>
                    <p class="description"><?php esc_html_e('Controla la visualización del banner de Solwed en la parte inferior del sitio web.', 'solwed-wp'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Mostrar Banner', 'solwed-wp'); ?></th>
                            <td>
                                <label class="solwed-switch">
                                    <input type="checkbox" name="banner_enabled" value="1" <?php checked($banner_enabled); ?>>
                                    <span class="solwed-slider"></span>
                                </label>
                                <p class="description"><?php esc_html_e('Activa o desactiva el banner inferior del sitio.', 'solwed-wp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Texto del Banner', 'solwed-wp'); ?></th>
                            <td>
                                <input type="text" name="banner_text" 
                                       value="<?php echo esc_attr($banner_settings['text']); ?>" class="regular-text"
                                       placeholder="<?php esc_attresc_html_e('Desarrollo Web Profesional', 'solwed-wp'); ?>">
                                <p class="description"><?php esc_html_e('Personaliza el texto que aparece en el banner.', 'solwed-wp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('URL de la Empresa', 'solwed-wp'); ?></th>
                            <td>
                                <input type="url" name="banner_company_url" 
                                       value="<?php echo esc_attr($banner_settings['company_url']); ?>" class="regular-text"
                                       placeholder="<?php esc_attresc_html_e('https://tuempresa.com', 'solwed-wp'); ?>">
                                <p class="description"><?php esc_html_e('URL de la empresa del diseñador (opcional). El texto del banner será un enlace a esta URL.', 'solwed-wp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Color del Texto', 'solwed-wp'); ?></th>
                            <td>
                                <input type="color" name="banner_text_color" 
                                       value="<?php echo esc_attr($banner_settings['text_color']); ?>" class="solwed-color-picker">
                                <input type="text" name="banner_text_color_hex" 
                                       value="<?php echo esc_attr($banner_settings['text_color']); ?>" class="regular-text solwed-color-input" 
                                       placeholder="#ffffff">
                                <p class="description"><?php esc_html_e('Color del texto del banner. Usa el selector o introduce un código hexadecimal.', 'solwed-wp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Color de Fondo', 'solwed-wp'); ?></th>
                            <td>
                                <input type="color" name="banner_background_color" 
                                       value="<?php echo esc_attr($banner_settings['bg_color']); ?>" class="solwed-color-picker">
                                <input type="text" name="banner_background_color_hex" 
                                       value="<?php echo esc_attr($banner_settings['bg_color']); ?>" class="regular-text solwed-color-input" 
                                       placeholder="#2E3536">
                                <p class="description"><?php esc_html_e('Color de fondo del banner. Usa el selector o introduce un código hexadecimal.', 'solwed-wp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Posición del Banner', 'solwed-wp'); ?></th>
                            <td>
                                <select name="banner_position">
                                    <option value="bottom" <?php selected($banner_settings['position'], 'bottom'); ?>><?php esc_html_e('Parte Inferior', 'solwed-wp'); ?></option>
                                    <option value="top" <?php selected($banner_settings['position'], 'top'); ?>><?php esc_html_e('Parte Superior', 'solwed-wp'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Posición donde aparecerá el banner en el sitio web.', 'solwed-wp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Animación', 'solwed-wp'); ?></th>
                            <td>
                                <select name="banner_animation">
                                    <option value="none" <?php selected($banner_settings['animation'], 'none'); ?>><?php esc_html_e('Sin animación', 'solwed-wp'); ?></option>
                                    <option value="fade" <?php selected($banner_settings['animation'], 'fade'); ?>><?php esc_html_e('Desvanecimiento', 'solwed-wp'); ?></option>
                                    <option value="slide" <?php selected($banner_settings['animation'], 'slide'); ?>><?php esc_html_e('Deslizamiento', 'solwed-wp'); ?></option>
                                    <option value="bounce" <?php selected($banner_settings['animation'], 'bounce'); ?>><?php esc_html_e('Rebote', 'solwed-wp'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Tipo de animación para mostrar el banner.', 'solwed-wp'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <?php if ($banner_enabled): ?>
                    <div class="solwed-preview">
                        <h3><?php esc_html_e('Vista previa del banner:', 'solwed-wp'); ?></h3>
                        <div class="solwed-banner-preview">
                            <?php echo wp_kses_post($appearance->get_banner_html(true)); ?>
                            <p class="description" style="margin-top: 10px;">
                                <strong><?php esc_html_e('Nota:', 'solwed-wp'); ?></strong> <?php esc_html_e('Esta vista previa muestra cómo se verá el banner en el sitio web. Se usa la fuente Orbitron con peso ligero para un aspecto moderno.', 'solwed-wp'); ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="solwed-form-actions">
                        <?php submit_button(__('Guardar Configuración', 'solwed-wp'), 'primary solwed-btn'); ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- PANEL DE ESTADÍSTICAS (Derecha) -->
        <div class="solwed-appearance-sidebar">
            <div class="solwed-sidebar-panel solwed-panel">
                <h3><?php esc_html_e('📊 Estado del Banner', 'solwed-wp'); ?></h3>
                <div class="solwed-stats-info">
                    <p><strong><?php esc_html_e('Estado:', 'solwed-wp'); ?></strong> 
                        <span class="solwed-status-badge <?php echo $banner_enabled ? 'sent' : 'failed'; ?>">
                            <?php echo $banner_enabled ? esc_html(__('Activo', 'solwed-wp')) : esc_html(__('Inactivo', 'solwed-wp')); ?>
                        </span>
                    </p>
                    <p><strong><?php esc_html_e('Posición:', 'solwed-wp'); ?></strong> <?php echo esc_html(ucfirst($banner_settings['position'])); ?></p>
                    <p><strong><?php esc_html_e('Animación:', 'solwed-wp'); ?></strong> <?php echo esc_html(ucfirst($banner_settings['animation'])); ?></p>
                    <p><strong><?php esc_html_e('Color de Fondo:', 'solwed-wp'); ?></strong> 
                        <span style="display: inline-block; width: 20px; height: 20px; background: <?php echo esc_attr($banner_settings['bg_color']); ?>; border: 1px solid #ddd; vertical-align: middle;"></span>
                        <?php echo esc_html($banner_settings['bg_color']); ?>
                    </p>
                    <p><strong><?php esc_html_e('Color de Texto:', 'solwed-wp'); ?></strong> 
                        <span style="display: inline-block; width: 20px; height: 20px; background: <?php echo esc_attr($banner_settings['text_color']); ?>; border: 1px solid #ddd; vertical-align: middle;"></span>
                        <?php echo esc_html($banner_settings['text_color']); ?>
                    </p>
                </div>
            </div>

            <div class="solwed-sidebar-panel solwed-panel">
                <h3><?php esc_html_e('🎯 Consejos de Apariencia', 'solwed-wp'); ?></h3>
                <ul style="padding-left: 20px; line-height: 1.6;">
                    <li><?php esc_html_e('Use colores contrastantes para mejor legibilidad', 'solwed-wp'); ?></li>
                    <li><?php esc_html_e('La fuente Orbitron proporciona un aspecto moderno y profesional', 'solwed-wp'); ?></li>
                    <li><?php esc_html_e('Las animaciones sutiles mejoran la experiencia del usuario', 'solwed-wp'); ?></li>
                    <li><?php esc_html_e('La posición inferior es menos intrusiva para el contenido', 'solwed-wp'); ?></li>
                </ul>
            </div>

            <?php if (!empty($banner_settings['company_url'])): ?>
            <div class="solwed-sidebar-panel solwed-panel">
                <h3><?php esc_html_e('🔗 URL de la Empresa', 'solwed-wp'); ?></h3>
                <p><a href="<?php echo esc_url($banner_settings['company_url']); ?>" target="_blank" rel="noopener">
                    <?php echo esc_html($banner_settings['company_url']); ?>
                </a></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php
}
