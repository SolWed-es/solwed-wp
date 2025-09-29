<?php
/**
 * Personalizador de Login
 */

if (!defined('ABSPATH')) {
    exit;
}

class Solwed_Login_Customizer {
    
    public function init(): void {
        add_action('login_enqueue_scripts', [$this, 'enqueue_login_assets']);
        add_filter('login_headerurl', [$this, 'custom_login_header_url']);
        add_filter('login_headertext', [$this, 'custom_login_header_text']);
        add_action('login_head', [$this, 'add_custom_login_css']);
    }

    public function enqueue_login_assets(): void {
        // Cargar fuente Orbitron
        wp_enqueue_style(
            'orbitron-font-login',
            'https://fonts.googleapis.com/css2?family=Orbitron:wght@100;300;400;500;600;700&display=swap',
            [],
            SOLWED_WP_VERSION
        );
        
        wp_enqueue_style(
            'solwed-login-style',
            SOLWED_WP_PLUGIN_URL . 'assets/css/admin.css',
            ['orbitron-font-login'],
            SOLWED_WP_VERSION
        );
    }

    public function add_custom_login_css(): void {
        ?>
        <style type="text/css">
            /* Reset y base */
            .login {
                background: linear-gradient(135deg, #2E3536 0%, #3A4344 100%) !important;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Orbitron', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            }
            
            .login #login {
                padding-top: 0 !important;
                width: 100%;
                max-width: 400px;
            }
            
            /* Logo superior */
            .login h1 {
                text-align: center;
                margin-bottom: 30px;
            }
            
            .login h1 a {
                background-image: url('<?php echo esc_url(SOLWED_WP_PLUGIN_URL . 'assets/img/IsotipoOscuro.png'); ?>') !important;
                background-size: contain !important;
                background-repeat: no-repeat !important;
                background-position: center !important;
                width: 120px !important;
                height: 120px !important;
                display: block !important;
                margin: 0 auto !important;
                padding: 0 !important;
                text-indent: -9999px !important;
                outline: none !important;
                overflow: hidden !important;
                text-decoration: none !important;
            }
            
            /* Formulario principal */
            .login form {
                background: rgba(255, 255, 255, 0.1) !important;
                backdrop-filter: blur(15px) !important;
                border: 1px solid rgba(255, 255, 255, 0.2) !important;
                border-radius: 12px !important;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
                padding: 30px !important;
                margin-top: 0 !important;
            }
            
            /* Campos de entrada */
            .login form .input,
            .login input[type=text],
            .login input[type=password],
            .login input[type=email] {
                background: rgba(255, 255, 255, 0.9) !important;
                border: 1px solid rgba(255, 255, 255, 0.3) !important;
                border-radius: 6px !important;
                padding: 12px !important;
                font-size: 16px !important;
                color: #2E3536 !important;
                width: 100% !important;
                box-sizing: border-box !important;
                transition: all 0.3s ease !important;
            }
            
            .login form .input:focus,
            .login input[type=text]:focus,
            .login input[type=password]:focus,
            .login input[type=email]:focus {
                border-color: #F2E501 !important;
                box-shadow: 0 0 0 2px rgba(242, 229, 1, 0.2) !important;
                outline: none !important;
            }
            
            /* Labels */
            .login form label {
                color: #ffffff !important;
                font-weight: 300 !important;
                font-size: 14px !important;
                margin-bottom: 5px !important;
                display: block !important;
                letter-spacing: 1px !important;
            }
            
            /* Botón principal */
            .login .button-primary {
                background: linear-gradient(135deg, #F2E501 0%, #E6CF01 100%) !important;
                border: none !important;
                color: #2E3536 !important;
                font-weight: 500 !important;
                font-size: 16px !important;
                font-family: 'Orbitron', sans-serif !important;
                letter-spacing: 1px !important;
                padding: 12px 24px !important;
                border-radius: 6px !important;
                width: 100% !important;
                cursor: pointer !important;
                transition: all 0.3s ease !important;
                text-transform: uppercase !important;
            }
            
            .login .button-primary:hover,
            .login .button-primary:focus {
                background: linear-gradient(135deg, #E6CF01 0%, #D4BE01 100%) !important;
                transform: translateY(-1px) !important;
                box-shadow: 0 4px 15px rgba(242, 229, 1, 0.4) !important;
            }
            
            /* Checkbox "Recordarme" */
            .login .forgetmenot {
                margin: 15px 0 !important;
            }
            
            .login .forgetmenot input[type="checkbox"] {
                margin-right: 8px !important;
            }
            
            .login .forgetmenot label {
                color: #ffffff !important;
                font-size: 14px !important;
                font-weight: 300 !important;
                letter-spacing: 0.5px !important;
            }
            
            /* Enlaces inferiores */
            .login #nav,
            .login #backtoblog {
                text-align: center !important;
                margin-top: 20px !important;
            }
            
            .login #nav a,
            .login #backtoblog a {
                color: #F2E501 !important;
                text-decoration: none !important;
                font-weight: 300 !important;
                font-size: 14px !important;
                letter-spacing: 0.5px !important;
                transition: color 0.3s ease !important;
            }
            
            .login #nav a:hover,
            .login #backtoblog a:hover {
                color: #ffffff !important;
            }
            
            /* Mensajes */
            .login .message {
                background: rgba(242, 229, 1, 0.1) !important;
                border: 1px solid rgba(242, 229, 1, 0.3) !important;
                border-left: 4px solid #F2E501 !important;
                color: #ffffff !important;
                border-radius: 6px !important;
                margin-bottom: 20px !important;
            }
            
            .login .error {
                background: rgba(214, 54, 56, 0.1) !important;
                border: 1px solid rgba(214, 54, 56, 0.3) !important;
                border-left: 4px solid #D63638 !important;
                color: #ffffff !important;
                border-radius: 6px !important;
                margin-bottom: 20px !important;
            }
            
            /* Selector de idioma en esquina */
            .login .language-switcher {
                position: fixed !important;
                top: 20px !important;
                right: 20px !important;
                z-index: 999 !important;
                background: rgba(255, 255, 255, 0.1) !important;
                backdrop-filter: blur(10px) !important;
                border: 1px solid rgba(255, 255, 255, 0.2) !important;
                border-radius: 8px !important;
                padding: 8px 12px !important;
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2) !important;
            }
            
            .login .language-switcher select {
                background: transparent !important;
                border: none !important;
                color: #ffffff !important;
                font-family: 'Orbitron', sans-serif !important;
                font-weight: 300 !important;
                font-size: 12px !important;
                letter-spacing: 0.5px !important;
                cursor: pointer !important;
                outline: none !important;
                min-width: 80px !important;
            }
            
            .login .language-switcher select option {
                background: #2E3536 !important;
                color: #ffffff !important;
            }
            
            .login .language-switcher button {
                background: #F2E501 !important;
                border: none !important;
                color: #2E3536 !important;
                padding: 4px 8px !important;
                border-radius: 4px !important;
                font-size: 10px !important;
                font-weight: 500 !important;
                margin-left: 5px !important;
                cursor: pointer !important;
                transition: all 0.3s ease !important;
            }
            
            .login .language-switcher button:hover {
                background: #E6CF01 !important;
                transform: scale(1.05) !important;
            }
            
            /* Mejorar centrado del contenido principal */
            .login {
                background: linear-gradient(135deg, #2E3536 0%, #3A4344 100%) !important;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Orbitron', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
                position: relative !important;
            }
            
            .login #login {
                padding-top: 0 !important;
                width: 100%;
                max-width: 400px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }
            
            /* Ocultar elementos que puedan interferir con el centrado */
            .login .privacy-policy-page-link {
                position: fixed !important;
                bottom: 20px !important;
                left: 20px !important;
                right: auto !important;
                color: rgba(242, 229, 1, 0.7) !important;
                font-size: 12px !important;
                text-decoration: none !important;
                font-family: 'Orbitron', sans-serif !important;
                font-weight: 300 !important;
            }
            
            .login .privacy-policy-page-link:hover {
                color: #F2E501 !important;
            }
            
            /* Responsive */
            @media (max-width: 768px) {
                .login .language-switcher {
                    top: 10px !important;
                    right: 10px !important;
                    padding: 6px 10px !important;
                }
                .login #login {
                    max-width: 90%;
                    padding: 0 20px;
                }
                
                .login h1 a {
                    width: 80px !important;
                    height: 80px !important;
                }
                
                .login form {
                    padding: 20px !important;
                }
            }
        </style>
        
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                // Buscar el selector de idioma existente
                var languageSelector = document.querySelector('.wp-core-ui .language-switcher, .language-switcher, [class*="language"]');
                
                // Si no se encuentra por clase, buscar por elementos select que puedan ser el selector de idioma
                if (!languageSelector) {
                    var selects = document.querySelectorAll('select');
                    for (var i = 0; i < selects.length; i++) {
                        if (selects[i].name && (selects[i].name.includes('language') || selects[i].name.includes('locale'))) {
                            languageSelector = selects[i].closest('div, p') || selects[i];
                            break;
                        }
                    }
                }
                
                // Si encontramos el selector, moverlo a la esquina
                if (languageSelector) {
                    languageSelector.classList.add('language-switcher');
                    languageSelector.style.cssText += `
                        position: fixed !important;
                        top: 20px !important;
                        right: 20px !important;
                        z-index: 999 !important;
                        background: rgba(255, 255, 255, 0.1) !important;
                        backdrop-filter: blur(10px) !important;
                        border: 1px solid rgba(255, 255, 255, 0.2) !important;
                        border-radius: 8px !important;
                        padding: 8px 12px !important;
                        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2) !important;
                        margin: 0 !important;
                    `;
                    
                    // Estilos para elementos internos
                    var select = languageSelector.querySelector('select');
                    if (select) {
                        select.style.cssText += `
                            background: transparent !important;
                            border: none !important;
                            color: #ffffff !important;
                            font-family: 'Orbitron', sans-serif !important;
                            font-weight: 300 !important;
                            font-size: 12px !important;
                            letter-spacing: 0.5px !important;
                            cursor: pointer !important;
                            outline: none !important;
                            min-width: 80px !important;
                        `;
                    }
                    
                    var button = languageSelector.querySelector('button, input[type="submit"]');
                    if (button) {
                        button.style.cssText += `
                            background: #F2E501 !important;
                            border: none !important;
                            color: #2E3536 !important;
                            padding: 4px 8px !important;
                            border-radius: 4px !important;
                            font-size: 10px !important;
                            font-weight: 500 !important;
                            margin-left: 5px !important;
                            cursor: pointer !important;
                            transition: all 0.3s ease !important;
                        `;
                        
                        button.addEventListener('mouseenter', function() {
                            this.style.background = '#E6CF01 !important';
                            this.style.transform = 'scale(1.05)';
                        });
                        
                        button.addEventListener('mouseleave', function() {
                            this.style.background = '#F2E501 !important';
                            this.style.transform = 'scale(1)';
                        });
                    }
                }
                
                // Mejorar centrado removiendo elementos que interfieran
                var body = document.body;
                if (body) {
                    body.style.margin = '0';
                    body.style.padding = '0';
                    body.style.height = '100vh';
                    body.style.overflow = 'hidden';
                }
                
                // Centrar perfectamente el contenido principal
                var loginDiv = document.getElementById('login');
                if (loginDiv) {
                    loginDiv.style.position = 'relative';
                    loginDiv.style.zIndex = '1';
                }
            });
        </script>
        <?php
    }

    public function custom_login_header_url(): string {
        return 'https://solwed.com';
    }

    public function custom_login_header_text(): string {
        return __('Desarrollado por Solwed', 'solwed-wp');
    }
}