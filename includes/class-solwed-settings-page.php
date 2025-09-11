<?php
/**
 * Clase SOLWED_Settings_Page
 *
 * Gestiona la creación y el manejo de la página de configuración
 * para el plugin WP Solwed (conexión CRM y configuración SMTP en una sola sección).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

class SOLWED_Settings_Page {

    private $option_group = 'solwed_options_group';
    private $crm_option_name = 'solwed_crm_settings';
    private $smtp_option_name = 'solwed_smtp_settings';
    private $page_slug = 'solwed_settings';

    // Sección única para todos los ajustes
    private $main_section_id = 'solwed_plugin_general_section';

    private $default_crm_settings = [
        'solwed_api_url'   => 'https://tu-dominio-erp.com/api/3/contactos',
        'solwed_api_token' => 'TU_TOKEN_DE_EJEMPLO_AQUI_12345',
    ];

    private $default_smtp_settings = [
        'smtp_host'         => 'solwed.es',
        'smtp_port'         => 465,
        'smtp_encryption'   => 'ssl',
        'smtp_auth'         => 'yes',
        'smtp_user'         => 'no-reply@solwed.es',
        'smtp_pass'         => '90g01b%Qx.',
        'smtp_from_email'   => '',
        'smtp_from_name'    => '',
    ];

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'settings_init' ] );
    }

    public function add_admin_menu() {
        add_options_page(
            __( 'Configuración WP Solwed', 'solwed' ),
            __( 'WP Solwed Settings', 'solwed' ), // Título que aparece en el menú de Ajustes
            'manage_options',
            $this->page_slug, // Slug de la página
            [ $this, 'options_page_html' ]
        );
    }

    public function settings_init() {
        // Registrar los grupos de opciones (CRM y SMTP por separado pero bajo el mismo option_group)
        register_setting( $this->option_group, $this->crm_option_name, [ 'default' => $this->default_crm_settings, 'sanitize_callback' => [ $this, 'sanitize_crm_settings' ]]);
        register_setting( $this->option_group, $this->smtp_option_name, [ 'default' => $this->default_smtp_settings, 'sanitize_callback' => [ $this, 'sanitize_smtp_settings' ]]);

        // Añadir una única sección para todos los ajustes
        add_settings_section(
            $this->main_section_id, // ID de la sección única
            __( 'Configuración General de WP Solwed', 'solwed' ), // Título de la sección
            [ $this, 'main_section_callback' ], // Callback para la descripción de la sección
            $this->page_slug // Página donde se mostrará
        );

        // Campos para Configuración CRM (asignados a la sección única)
        add_settings_field( 'solwed_api_url', __( 'URL API CRM', 'solwed' ), [ $this, 'api_url_render' ], $this->page_slug, $this->main_section_id, ['label_for' => 'solwed_api_url_field']);
        add_settings_field( 'solwed_api_token', __( 'Token API CRM', 'solwed' ), [ $this, 'api_token_render' ], $this->page_slug, $this->main_section_id, ['label_for' => 'solwed_api_token_field']);

        // Campos para Configuración SMTP (asignados a la sección única)
        // Podríamos añadir un subtítulo visual aquí si quisiéramos, usando un campo 'dummy' o HTML en el callback del primer campo SMTP.
        // Por ahora, se listarán directamente después de los campos CRM.
        add_settings_field( 'smtp_host', __( 'Servidor SMTP (Host)', 'solwed' ), [ $this, 'smtp_host_render' ], $this->page_slug, $this->main_section_id, ['label_for' => 'solwed_smtp_host_field']);
        add_settings_field( 'smtp_port', __( 'Puerto SMTP', 'solwed' ), [ $this, 'smtp_port_render' ], $this->page_slug, $this->main_section_id, ['label_for' => 'solwed_smtp_port_field']);
        add_settings_field( 'smtp_encryption', __( 'Cifrado SMTP', 'solwed' ), [ $this, 'smtp_encryption_render' ], $this->page_slug, $this->main_section_id, ['label_for' => 'solwed_smtp_encryption_field']);
        add_settings_field( 'smtp_auth', __( 'Autenticación SMTP', 'solwed' ), [ $this, 'smtp_auth_render' ], $this->page_slug, $this->main_section_id);
        add_settings_field( 'smtp_user', __( 'Usuario SMTP', 'solwed' ), [ $this, 'smtp_user_render' ], $this->page_slug, $this->main_section_id, ['label_for' => 'solwed_smtp_user_field']);
        add_settings_field( 'smtp_pass', __( 'Contraseña SMTP', 'solwed' ), [ $this, 'smtp_pass_render' ], $this->page_slug, $this->main_section_id, ['label_for' => 'solwed_smtp_pass_field']);
        add_settings_field( 'smtp_from_email', __( 'Email Remitente (SMTP)', 'solwed' ), [ $this, 'smtp_from_email_render' ], $this->page_slug, $this->main_section_id, ['label_for' => 'solwed_smtp_from_email_field']);
        add_settings_field( 'smtp_from_name', __( 'Nombre Remitente (SMTP)', 'solwed' ), [ $this, 'smtp_from_name_render' ], $this->page_slug, $this->main_section_id, ['label_for' => 'solwed_smtp_from_name_field']);
    }

    /**
     * Callback para la descripción de la sección general.
     */
    public function main_section_callback() {
        echo '<p>' . esc_html__( 'Aquí puedes configurar los ajustes para la conexión CRM y el envío de correos SMTP.', 'solwed' ) . '</p>';
        // Para añadir una separación visual antes de los campos SMTP, podrías hacer algo como:
        // echo '<h3>' . esc_html__('Ajustes SMTP', 'solwed') . '</h3>';
        // Sin embargo, esto es menos "WordPress nativo". Los campos simplemente se listarán.
        // Si quieres una separación más marcada, considera usar un campo 'dummy' con un callback que solo imprima un <hr> o un <h2>.
    }

    // --- Callbacks para renderizar campos CRM ---
    public function api_url_render() {
        $options = get_option( $this->crm_option_name, $this->default_crm_settings );
        $api_url = isset($options['solwed_api_url']) ? $options['solwed_api_url'] : $this->default_crm_settings['solwed_api_url'];
        ?>
        <input type='url' id='solwed_api_url_field' class="regular-text" name='<?php echo esc_attr($this->crm_option_name); ?>[solwed_api_url]' value='<?php echo esc_attr($api_url); ?>' placeholder="<?php esc_attr_e( 'ej: https://tu-erp.com/api/3/contactos', 'solwed' ); ?>" required>
        <p class="description"><?php esc_html_e( 'URL de la API de FacturaScripts (CRM).', 'solwed' ); ?></p>
        <?php
    }
    public function api_token_render() {
        $options = get_option( $this->crm_option_name, $this->default_crm_settings );
        $api_token = isset($options['solwed_api_token']) ? $options['solwed_api_token'] : $this->default_crm_settings['solwed_api_token'];
        ?>
        <input type='text' id='solwed_api_token_field' class="regular-text" name='<?php echo esc_attr($this->crm_option_name); ?>[solwed_api_token]' value='<?php echo esc_attr($api_token); ?>' placeholder="<?php esc_attr_e( 'ej: TU_TOKEN_DE_EJEMPLO', 'solwed' ); ?>" required>
        <p class="description"><?php esc_html_e( 'Token de la API de FacturaScripts (CRM).', 'solwed' ); ?></p>
        <?php
    }

    // --- Callbacks para renderizar campos SMTP ---
    public function smtp_host_render() {
        $options = get_option( $this->smtp_option_name, $this->default_smtp_settings );
        $val = isset($options['smtp_host']) ? $options['smtp_host'] : $this->default_smtp_settings['smtp_host'];
        ?>
        <hr style="margin-top: 20px; margin-bottom:20px; border-style: dashed;">
        <h4><?php esc_html_e('Ajustes de Correo SMTP', 'solwed'); ?></h4>
        <input type='text' id='solwed_smtp_host_field' class="regular-text" name='<?php echo esc_attr($this->smtp_option_name); ?>[smtp_host]' value='<?php echo esc_attr($val); ?>'>
        <?php
    }
    public function smtp_port_render() {
        $options = get_option( $this->smtp_option_name, $this->default_smtp_settings );
        $val = isset($options['smtp_port']) ? $options['smtp_port'] : $this->default_smtp_settings['smtp_port'];
        ?>
        <input type='number' id='solwed_smtp_port_field' class="small-text" name='<?php echo esc_attr($this->smtp_option_name); ?>[smtp_port]' value='<?php echo esc_attr($val); ?>' min="1" max="65535">
        <?php
    }
    public function smtp_encryption_render() {
        $options = get_option( $this->smtp_option_name, $this->default_smtp_settings );
        $val = isset($options['smtp_encryption']) ? $options['smtp_encryption'] : $this->default_smtp_settings['smtp_encryption'];
        ?>
        <select id='solwed_smtp_encryption_field' name='<?php echo esc_attr($this->smtp_option_name); ?>[smtp_encryption]'>
            <option value='none' <?php selected($val, 'none'); ?>><?php esc_html_e('Ninguno', 'solwed'); ?></option>
            <option value='ssl' <?php selected($val, 'ssl'); ?>>SSL</option>
            <option value='tls' <?php selected($val, 'tls'); ?>>TLS</option>
        </select>
        <p class="description"><?php esc_html_e('TLS es recomendado si está disponible.', 'solwed'); ?></p>
        <?php
    }
     public function smtp_auth_render() {
        $options = get_option( $this->smtp_option_name, $this->default_smtp_settings );
        $val = isset($options['smtp_auth']) ? $options['smtp_auth'] : $this->default_smtp_settings['smtp_auth'];
        ?>
        <fieldset>
            <label><input type="radio" name="<?php echo esc_attr($this->smtp_option_name); ?>[smtp_auth]" value="yes" <?php checked($val, 'yes'); ?>> <?php esc_html_e('Sí', 'solwed'); ?></label><br>
            <label><input type="radio" name="<?php echo esc_attr($this->smtp_option_name); ?>[smtp_auth]" value="no" <?php checked($val, 'no'); ?>> <?php esc_html_e('No', 'solwed'); ?></label>
        </fieldset>
        <p class="description"><?php esc_html_e('Especifica si tu servidor SMTP requiere autenticación.', 'solwed'); ?></p>
        <?php
    }
    public function smtp_user_render() {
        $options = get_option( $this->smtp_option_name, $this->default_smtp_settings );
        $val = isset($options['smtp_user']) ? $options['smtp_user'] : $this->default_smtp_settings['smtp_user'];
        ?>
        <input type='text' id='solwed_smtp_user_field' class="regular-text" name='<?php echo esc_attr($this->smtp_option_name); ?>[smtp_user]' value='<?php echo esc_attr($val); ?>' placeholder="<?php esc_attr_e('ej: usuario@example.com', 'solwed'); ?>">
        <p class="description"><?php esc_html_e('Solo necesario si la autenticación está activada.', 'solwed'); ?></p>
        <?php
    }
    public function smtp_pass_render() {
        $options = get_option( $this->smtp_option_name, $this->default_smtp_settings );
        $val = isset($options['smtp_pass']) ? $options['smtp_pass'] : '';
        ?>
        <input type='password' id='solwed_smtp_pass_field' class="regular-text" name='<?php echo esc_attr($this->smtp_option_name); ?>[smtp_pass]' value='<?php echo esc_attr($val); ?>' placeholder="<?php esc_attr_e('Introduce la contraseña SMTP', 'solwed'); ?>">
        <p class="description"><?php esc_html_e('Solo necesario si la autenticación está activada.', 'solwed'); ?></p>
        <?php
    }
    public function smtp_from_email_render() {
        $options = get_option( $this->smtp_option_name, $this->default_smtp_settings );
        $val = isset($options['smtp_from_email']) ? $options['smtp_from_email'] : $this->default_smtp_settings['smtp_from_email'];
        ?>
        <input type='email' id='solwed_smtp_from_email_field' class="regular-text" name='<?php echo esc_attr($this->smtp_option_name); ?>[smtp_from_email]' value='<?php echo esc_attr($val); ?>'>
        <p class="description"><?php esc_html_e('Los correos se enviarán desde esta dirección.', 'solwed'); ?></p>
        <?php
    }
    public function smtp_from_name_render() {
        $options = get_option( $this->smtp_option_name, $this->default_smtp_settings );
        $val = isset($options['smtp_from_name']) ? $options['smtp_from_name'] : $this->default_smtp_settings['smtp_from_name'];
        ?>
        <input type='text' id='solwed_smtp_from_name_field' class="regular-text" name='<?php echo esc_attr($this->smtp_option_name); ?>[smtp_from_name]' value='<?php echo esc_attr($val); ?>'>
        <p class="description"><?php esc_html_e('Los correos mostrarán este nombre como remitente.', 'solwed'); ?></p>
        <?php
    }

    // --- HTML de la página de opciones ---
    public function options_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'solwed' ) );
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( $this->option_group ); // Grupo de opciones para ambos (CRM y SMTP)
                do_settings_sections( $this->page_slug ); // Muestra la sección única y todos sus campos
                submit_button( __( 'Guardar Todos los Cambios', 'solwed' ) );
                ?>
            </form>
        </div>
        <?php
    }

    // --- Funciones de sanitización (sin cambios) ---
    public function sanitize_crm_settings( $input ) {
        $sanitized_input = [];
        $defaults = $this->default_crm_settings;
        if ( isset( $input['solwed_api_url'] ) ) { $sanitized_input['solwed_api_url'] = esc_url_raw( trim( $input['solwed_api_url'] ) ); } else { $sanitized_input['solwed_api_url'] = $defaults['solwed_api_url']; }
        if ( isset( $input['solwed_api_token'] ) ) { $sanitized_input['solwed_api_token'] = sanitize_text_field( trim( $input['solwed_api_token'] ) ); } else { $sanitized_input['solwed_api_token'] = $defaults['solwed_api_token']; }
        return $sanitized_input;
    }
    public function sanitize_smtp_settings( $input ) {
        $sanitized_input = [];
        $defaults = $this->default_smtp_settings;
        $sanitized_input['smtp_host'] = isset( $input['smtp_host'] ) ? sanitize_text_field( $input['smtp_host'] ) : $defaults['smtp_host'];
        $sanitized_input['smtp_port'] = isset( $input['smtp_port'] ) ? absint( $input['smtp_port'] ) : $defaults['smtp_port'];
        $allowed_encryption = ['none', 'ssl', 'tls'];
        $sanitized_input['smtp_encryption'] = isset( $input['smtp_encryption'] ) && in_array($input['smtp_encryption'], $allowed_encryption) ? $input['smtp_encryption'] : $defaults['smtp_encryption'];
        $allowed_auth = ['yes', 'no'];
        $sanitized_input['smtp_auth'] = isset( $input['smtp_auth'] ) && in_array($input['smtp_auth'], $allowed_auth) ? $input['smtp_auth'] : $defaults['smtp_auth'];
        $sanitized_input['smtp_user'] = isset( $input['smtp_user'] ) ? sanitize_text_field( $input['smtp_user'] ) : $defaults['smtp_user'];
        $sanitized_input['smtp_pass'] = isset( $input['smtp_pass'] ) ? trim( $input['smtp_pass'] ) : '';
        $sanitized_input['smtp_from_email'] = isset( $input['smtp_from_email'] ) ? sanitize_email( $input['smtp_from_email'] ) : $defaults['smtp_from_email'];
        if (empty($sanitized_input['smtp_from_email'])) { $sanitized_input['smtp_from_email'] = $defaults['smtp_from_email']; }
        $sanitized_input['smtp_from_name'] = isset( $input['smtp_from_name'] ) ? sanitize_text_field( $input['smtp_from_name'] ) : $defaults['smtp_from_name'];
        if (empty($sanitized_input['smtp_from_name'])) { $sanitized_input['smtp_from_name'] = $defaults['smtp_from_name']; }
        return $sanitized_input;
    }
}
?>