<?php

/**
 * Plugin Name: Solwed WP
 * Plugin URI: https://solwed.es
 * Description: Plugin propio de Solwed para hacerle la vida más fácil
 * Version: 1.0.0
 * Author: Solwed ✌️
 * License: GPL v2 or later
 * Text Domain: solwed-wp
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

// Define plugin constants
define('SOLWED_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SOLWED_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SOLWED_PLUGIN_VERSION', '1.0.0');

// Main plugin class
class Solwed_Integrations
{

	private $modules = array();
	private $active_modules = array();

	public function __construct()
	{
		add_action('init', array($this, 'init'));
	}

	public function init()
	{
		// Load modules
		$this->load_modules();

		// Load active modules
		$this->active_modules = get_option('solwed_active_modules', array());

		// Initialize active modules
		foreach ($this->active_modules as $module_slug) {
			if (isset($this->modules[$module_slug])) {
				$module_class = $this->modules[$module_slug]['class'];
				if (class_exists($module_class)) {
					new $module_class();
				}
			}
		}

		// Admin initialization
		if (is_admin()) {
			add_action('admin_menu', array($this, 'add_admin_menu'));
			add_action('admin_init', array($this, 'register_settings'));
		}
	}

	private function load_modules()
	{
		// Default available modules
		$this->modules = array(
			'facturascripts-integration' => array(
				'name' => 'Integración de FacturaScripts',
				'description' => 'Enviar datos del formulario de Elementor a la API de FacturaScripts',
				'class' => 'Solwed_FacturaScripts_Integration',
				'file' => SOLWED_PLUGIN_PATH . 'modules/facturascripts-integration.php'
			)
			// Future modules can be added here
		);

		// Allow other plugins to add modules
		$this->modules = apply_filters('solwed_register_modules', $this->modules);

		// Load module files
		foreach ($this->modules as $module) {
			if (file_exists($module['file'])) {
				include_once $module['file'];
			}
		}
	}

	public function add_admin_menu()
	{
		add_options_page(
			'Solwed ✌️',
			'Solwed ✌️',
			'manage_options',
			'solwed-wp',
			array($this, 'render_settings_page')
		);
	}

	public function register_settings()
	{
		register_setting('solwed_settings', 'solwed_active_modules', array(
			'type' => 'array',
			'sanitize_callback' => array($this, 'sanitize_module_settings')
		));

		add_settings_section(
			'solwed_modules_section',
			'Módulos disponibles',
			array($this, 'render_modules_section'),
			'solwed-wp'
		);

		add_settings_field(
			'solwed_active_modules',
			'Activar módulos',
			array($this, 'render_modules_field'),
			'solwed-wp',
			'solwed_modules_section'
		);
	}

	public function sanitize_module_settings($input)
	{
		$sanitized = array();
		if (is_array($input)) {
			foreach ($input as $module_slug) {
				if (array_key_exists($module_slug, $this->modules)) {
					$sanitized[] = $module_slug;
				}
			}
		}
		return $sanitized;
	}

	public function render_settings_page()
	{
?>
		<div class="wrap">
			<h1>Configuración de Integraciones de Solwed</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields('solwed_settings');
				do_settings_sections('solwed-wp');
				submit_button();
				?>
			</form>
		</div>
<?php
	}

	public function render_modules_section()
	{
		echo '<p>Seleccione qué módulos activar:</p>';
	}

	public function render_modules_field()
	{
		$active_modules = get_option('solwed_active_modules', array());

		foreach ($this->modules as $slug => $module) {
			$checked = in_array($slug, $active_modules) ? 'checked="checked"' : '';
			echo '<div style="margin-bottom: 10px;">';
			echo '<label>';
			echo '<input type="checkbox" name="solwed_active_modules[]" value="' . esc_attr($slug) . '" ' . $checked . '> ';
			echo '<strong>' . esc_html($module['name']) . '</strong>';
			echo '</label>';
			echo '<p class="description">' . esc_html($module['description']) . '</p>';
			echo '</div>';
		}
	}
}

// Initialize the plugin
new Solwed_Integrations();
