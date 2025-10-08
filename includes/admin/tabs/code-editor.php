<?php
/**
 * Pestaña de Editor de Código
 * Compatible con PHP 8.1+
 */

if (!defined('ABSPATH')) {
    exit;
}

function render_code_editor_tab() {
    $editor_settings = [
        'custom_css' => get_option(SOLWED_WP_PREFIX . 'custom_css', ''),
        'custom_js' => get_option(SOLWED_WP_PREFIX . 'custom_js', ''),
        'custom_css_priority' => get_option(SOLWED_WP_PREFIX . 'custom_css_priority', ''),
        'custom_php' => get_option(SOLWED_WP_PREFIX . 'custom_php', '')
    ];
    
    // Calcular estadísticas de código
    $css_lines = !empty($editor_settings['custom_css']) ? substr_count($editor_settings['custom_css'], "\n") + 1 : 0;
    $css_priority_lines = !empty($editor_settings['custom_css_priority']) ? substr_count($editor_settings['custom_css_priority'], "\n") + 1 : 0;
    $js_lines = !empty($editor_settings['custom_js']) ? substr_count($editor_settings['custom_js'], "\n") + 1 : 0;
    $php_lines = !empty($editor_settings['custom_php']) ? substr_count($editor_settings['custom_php'], "\n") + 1 : 0;
    $total_lines = $css_lines + $css_priority_lines + $js_lines + $php_lines;
    ?>

    <style>
    .solwed-code-editor-container {
        display: flex;
        gap: 20px;
        margin: 20px 0;
    }
    .solwed-code-editor-main {
        flex: 2;
        max-width: 800px;
    }
    .solwed-code-editor-sidebar {
        flex: 1;
        min-width: 300px;
        max-width: 350px;
    }
    .solwed-sidebar-panel {
        position: sticky;
        top: 32px;
        margin-bottom: 20px;
    }
    .solwed-code-preview {
        background: #f8f9fa;
        border: 1px solid #e1e3e6;
        border-radius: 4px;
        padding: 10px;
        font-family: 'Courier New', monospace;
        font-size: 11px;
        max-height: 150px;
        overflow-y: auto;
    }
    </style>

    <div class="solwed-code-editor-container">
        <!-- FORMULARIO PRINCIPAL (Izquierda) -->
        <div class="solwed-code-editor-main">
            <form method="post" action="">
                <?php wp_nonce_field('solwed_settings'); ?>
                <input type="hidden" name="solwed_action" value="save_code_editor">
                
                <div class="solwed-form-section">
                    <h2><?php esc_html_e('👨‍💻 Editor de Código Avanzado', 'solwed-wp'); ?></h2>
                    <p class="description"><?php esc_html_e('Añade CSS, JavaScript y PHP personalizado a tu sitio web. Usa esta funcionalidad con precaución.', 'solwed-wp'); ?></p>
                    
                    <!-- CSS Personalizado -->
                    <div class="solwed-code-editor-wrapper">
                        <h3><?php esc_html_e('CSS Personalizado (Normal)', 'solwed-wp'); ?></h3>
                        <p class="description"><?php esc_html_e('CSS que se carga con prioridad normal en wp_head.', 'solwed-wp'); ?></p>
                        <textarea name="custom_css" id="solwed-custom-css" class="solwed-code-editor" rows="8" placeholder="/* Tu CSS personalizado aquí */&#10;.mi-clase {&#10;    color: #000;&#10;}"><?php echo esc_textarea($editor_settings['custom_css']); ?></textarea>
                    </div>
                    
                    <!-- CSS de Alta Prioridad -->
                    <div class="solwed-code-editor-wrapper">
                        <h3><?php esc_html_e('CSS de Alta Prioridad', 'solwed-wp'); ?></h3>
                        <p class="description"><?php esc_html_e('CSS que se carga con máxima prioridad y sobrescribe otros estilos. Se ejecuta después de todos los demás CSS.', 'solwed-wp'); ?></p>
                        <textarea name="custom_css_priority" id="solwed-custom-css-priority" class="solwed-code-editor" rows="8" placeholder="/* CSS con alta prioridad - sobrescribe otros estilos */&#10;body {&#10;    background-color: #fff !important;&#10;}"><?php echo esc_textarea($editor_settings['custom_css_priority']); ?></textarea>
                    </div>
                    
                    <!-- JavaScript Personalizado -->
                    <div class="solwed-code-editor-wrapper">
                        <h3><?php esc_html_e('JavaScript Personalizado', 'solwed-wp'); ?></h3>
                        <p class="description"><?php esc_html_e('JavaScript que se carga en el footer. Se ejecuta cuando el DOM esté listo.', 'solwed-wp'); ?></p>
                        <textarea name="custom_js" id="solwed-custom-js" class="solwed-code-editor" rows="8" placeholder="// Tu JavaScript personalizado aquí&#10;document.addEventListener('DOMContentLoaded', function() {&#10;    console.log('Código personalizado cargado');&#10;});"><?php echo esc_textarea($editor_settings['custom_js']); ?></textarea>
                    </div>
                    
                    <!-- PHP Personalizado -->
                    <div class="solwed-code-editor-wrapper">
                        <h3><?php esc_html_e('PHP Personalizado', 'solwed-wp'); ?> <span style="color: #d63638; font-weight: bold;"><?php esc_html_e('(¡PELIGROSO!)', 'solwed-wp'); ?></span></h3>
                        <div class="notice notice-warning inline" style="margin: 10px 0; padding: 10px;">
                            <p><strong><?php esc_html_e('⚠️ ADVERTENCIA:', 'solwed-wp'); ?></strong> <?php esc_html_e('El código PHP se ejecuta directamente. Un error puede romper tu sitio web. Solo úsalo si sabes lo que haces.', 'solwed-wp'); ?></p>
                        </div>
                        <p class="description"><?php esc_html_e('Código PHP que se ejecuta en el hook "init". No incluyas las etiquetas de apertura/cierre de PHP.', 'solwed-wp'); ?></p>
                        <textarea name="custom_php" id="solwed-custom-php" class="solwed-code-editor" rows="8" placeholder="// Ejemplo: Redirección personalizada&#10;if (is_page('old-page')) {&#10;    wp_redirect(home_url('/new-page/'));&#10;    exit;&#10;}&#10;&#10;// Ejemplo: Hook personalizado&#10;add_action('wp_footer', function() {&#10;    echo '&lt;!-- Código personalizado --&gt;';&#10;});"><?php echo esc_textarea($editor_settings['custom_php']); ?></textarea>
                    </div>

                    <div class="solwed-form-actions">
                        <?php submit_button(__('Guardar Cambios', 'solwed-wp'), 'primary solwed-btn'); ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- PANEL DE ESTADÍSTICAS (Derecha) -->
        <div class="solwed-code-editor-sidebar">
            <div class="solwed-sidebar-panel solwed-panel">
                <h3><?php esc_html_e('📊 Estadísticas de Código', 'solwed-wp'); ?></h3>
                <div class="solwed-stats-info">
                    <p><strong><?php esc_html_e('Total Líneas:', 'solwed-wp'); ?></strong> 
                        <span class="solwed-status-badge <?php echo ($total_lines > 0) ? 'sent' : 'failed'; ?>">
                            <?php echo esc_html($total_lines); ?>
                        </span>
                    </p>
                    <p><strong><?php esc_html_e('CSS Normal:', 'solwed-wp'); ?></strong> <?php echo esc_html($css_lines); ?> líneas</p>
                    <p><strong><?php esc_html_e('CSS Prioritario:', 'solwed-wp'); ?></strong> <?php echo esc_html($css_priority_lines); ?> líneas</p>
                    <p><strong><?php esc_html_e('JavaScript:', 'solwed-wp'); ?></strong> <?php echo esc_html($js_lines); ?> líneas</p>
                    <p><strong><?php esc_html_e('PHP:', 'solwed-wp'); ?></strong> 
                        <span class="solwed-status-badge <?php echo ($php_lines > 0) ? 'failed' : 'sent'; ?>">
                            <?php echo esc_html($php_lines); ?> líneas
                        </span>
                    </p>
                </div>
            </div>

            <div class="solwed-sidebar-panel solwed-panel">
                <h3><?php esc_html_e('⚡ Estado Activo', 'solwed-wp'); ?></h3>
                <div class="solwed-stats-info">
                    <p><strong><?php esc_html_e('CSS Personalizado:', 'solwed-wp'); ?></strong> 
                        <span class="solwed-status-badge <?php echo !empty($editor_settings['custom_css']) ? 'sent' : 'failed'; ?>">
                            <?php echo !empty($editor_settings['custom_css']) ? esc_html(__('Activo', 'solwed-wp')) : esc_html(__('Vacío', 'solwed-wp')); ?>
                        </span>
                    </p>
                    <p><strong><?php esc_html_e('CSS Alta Prioridad:', 'solwed-wp'); ?></strong> 
                        <span class="solwed-status-badge <?php echo !empty($editor_settings['custom_css_priority']) ? 'sent' : 'failed'; ?>">
                            <?php echo !empty($editor_settings['custom_css_priority']) ? esc_html(__('Activo', 'solwed-wp')) : esc_html(__('Vacío', 'solwed-wp')); ?>
                        </span>
                    </p>
                    <p><strong><?php esc_html_e('JavaScript:', 'solwed-wp'); ?></strong> 
                        <span class="solwed-status-badge <?php echo !empty($editor_settings['custom_js']) ? 'sent' : 'failed'; ?>">
                            <?php echo !empty($editor_settings['custom_js']) ? esc_html(__('Activo', 'solwed-wp')) : esc_html(__('Vacío', 'solwed-wp')); ?>
                        </span>
                    </p>
                    <p><strong><?php esc_html_e('PHP Personalizado:', 'solwed-wp'); ?></strong> 
                        <span class="solwed-status-badge <?php echo !empty($editor_settings['custom_php']) ? 'failed' : 'sent'; ?>">
                            <?php echo !empty($editor_settings['custom_php']) ? esc_html(__('¡Activo!', 'solwed-wp')) : esc_html(__('Seguro', 'solwed-wp')); ?>
                        </span>
                    </p>
                </div>
            </div>

            <?php if (!empty($editor_settings['custom_php'])): ?>
            <div class="solwed-sidebar-panel solwed-panel" style="border-left: 4px solid #d63638;">
                <h3><?php esc_html_e('⚠️ PHP Activo', 'solwed-wp'); ?></h3>
                <p style="color: #d63638; font-size: 12px; line-height: 1.4;">
                    <strong><?php esc_html_e('¡ADVERTENCIA!', 'solwed-wp'); ?></strong><br>
                    <?php esc_html_e('Hay código PHP personalizado ejecutándose. Asegúrate de que es correcto para evitar errores.', 'solwed-wp'); ?>
                </p>
                <div class="solwed-code-preview" style="border-color: #d63638;">
                    <?php echo esc_html(substr($editor_settings['custom_php'], 0, 200)) . (strlen($editor_settings['custom_php']) > 200 ? '...' : ''); ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="solwed-sidebar-panel solwed-panel">
                <h3><?php esc_html_e('💡 Consejos', 'solwed-wp'); ?></h3>
                <ul style="padding-left: 20px; line-height: 1.6;">
                    <li><?php esc_html_e('Usa CSS normal para estilos estándar', 'solwed-wp'); ?></li>
                    <li><?php esc_html_e('CSS de alta prioridad sobrescribe todo', 'solwed-wp'); ?></li>
                    <li><?php esc_html_e('JavaScript se carga en el footer (más rápido)', 'solwed-wp'); ?></li>
                    <li><?php esc_html_e('EVITA PHP personalizado si es posible', 'solwed-wp'); ?></li>
                    <li><?php esc_html_e('Haz backup antes de guardar cambios', 'solwed-wp'); ?></li>
                </ul>
            </div>

            <?php if (!empty($editor_settings['custom_css']) || !empty($editor_settings['custom_css_priority'])): ?>
            <div class="solwed-sidebar-panel solwed-panel">
                <h3><?php esc_html_e('🎨 Vista Previa CSS', 'solwed-wp'); ?></h3>
                <?php if (!empty($editor_settings['custom_css'])): ?>
                <h4 style="font-size: 12px; margin: 10px 0 5px 0;"><?php esc_html_e('CSS Normal:', 'solwed-wp'); ?></h4>
                <div class="solwed-code-preview">
                    <?php echo esc_html(substr($editor_settings['custom_css'], 0, 150)) . (strlen($editor_settings['custom_css']) > 150 ? '...' : ''); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($editor_settings['custom_css_priority'])): ?>
                <h4 style="font-size: 12px; margin: 10px 0 5px 0;"><?php esc_html_e('CSS Prioritario:', 'solwed-wp'); ?></h4>
                <div class="solwed-code-preview" style="border-color: #ff9800;">
                    <?php echo esc_html(substr($editor_settings['custom_css_priority'], 0, 150)) . (strlen($editor_settings['custom_css_priority']) > 150 ? '...' : ''); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php
}
