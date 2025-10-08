<?php
/**
 * Instalador del plugin Solwed WP
 * @package Solwed_WP
 * @version 1.0.1
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestiona la activación, desactivación y desinstalación del plugin.
 *
 * @final
 */
final class Solwed_Installer {

	/**
	 * Nombres de las tablas de la base de datos.
	 */
	private const TABLE_SECURITY_LOGS   = 'solwed_security_logs';
	private const TABLE_FAILED_ATTEMPTS = 'solwed_failed_attempts';
	private const TABLE_EMAIL_LOGS      = 'solwed_email_logs';

	/**
	 * Opciones del plugin en la base de datos.
	 */
	private const OPTION_VERSION      = 'solwed_wp_version';
	private const OPTION_INSTALL_DATE = 'solwed_wp_install_date';

	/**
	 * Se ejecuta en la activación del plugin.
	 */
	public static function activate(): void {
		self::create_tables();
		self::set_default_options();
		self::update_plugin_version();
		self::add_install_date();
	}

	/**
	 * Se ejecuta en la desactivación del plugin.
	 */
	public static function deactivate(): void {
		// No se requieren acciones costosas como flush_rewrite_rules si no hay CPTs.
	}

	/**
	 * Se ejecuta en la desinstalación del plugin.
	 */
	public static function uninstall(): void {
		self::drop_tables();
		self::delete_options();
	}

	/**
	 * Crea o migra las tablas necesarias para el plugin.
	 */
	public static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();

		// Definición de la estructura de las tablas.
		$tables = [
			self::TABLE_SECURITY_LOGS   => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                ip_address varchar(45) NOT NULL,
                username varchar(60) DEFAULT '',
                action varchar(50) NOT NULL,
                timestamp datetime DEFAULT CURRENT_TIMESTAMP,
                user_agent text,
                status varchar(20) DEFAULT 'failed',
                request_uri varchar(500) DEFAULT '',
                country_code varchar(10) DEFAULT '',
                PRIMARY KEY (id),
                KEY ip_address (ip_address),
                KEY timestamp (timestamp),
                KEY action (action)
            ",
			self::TABLE_FAILED_ATTEMPTS => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                ip_address varchar(45) NOT NULL,
                attempts int(11) DEFAULT 0,
                blocked_until datetime DEFAULT NULL,
                last_attempt datetime DEFAULT CURRENT_TIMESTAMP,
                user_agent text,
                username_attempted varchar(100) DEFAULT '',
                PRIMARY KEY (id),
                UNIQUE KEY ip_address (ip_address),
                KEY blocked_until (blocked_until)
            ",
			self::TABLE_EMAIL_LOGS       => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                email_to varchar(255) NOT NULL,
                subject varchar(255) NOT NULL,
                status varchar(20) DEFAULT 'sent',
                error_message text,
                timestamp datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY timestamp (timestamp)
            ",
		];

		// Itera y crea/actualiza cada tabla.
		foreach ( $tables as $table_name => $columns ) {
			$sql = "CREATE TABLE {$wpdb->prefix}{$table_name} ($columns) $charset_collate;";
			dbDelta( $sql );
		}

		// Ejecuta migraciones adicionales si es necesario.
		self::migrate_tables();
	}

	/**
	 * Migra tablas existentes para agregar nuevas columnas si faltan.
	 * Centraliza la lógica de migración para evitar código repetido.
	 */
	private static function migrate_tables(): void {
		global $wpdb;
		$attempts_table = $wpdb->prefix . self::TABLE_FAILED_ATTEMPTS;

		// Columnas a verificar/añadir en la tabla de intentos fallidos.
		$columns_to_add = [
			'user_agent'         => 'TEXT',
			'username_attempted' => "VARCHAR(100) DEFAULT ''",
		];

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $attempts_table ) ) !== $attempts_table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			return; // La tabla no existe, no hay nada que migrar.
		}

		foreach ( $columns_to_add as $column_name => $column_definition ) {
			if ( ! self::column_exists( $attempts_table, $column_name ) ) {
				$wpdb->query( "ALTER TABLE {$attempts_table} ADD COLUMN {$column_name} {$column_definition}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
			}
		}
	}

	/**
	 * Verifica si una columna existe en una tabla.
	 *
	 * @param string $table_name Nombre completo de la tabla.
	 * @param string $column_name Nombre de la columna a verificar.
	 * @return bool
	 */
	private static function column_exists( string $table_name, string $column_name ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table_name} LIKE %s", $column_name ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}


	/**
	 * Elimina las tablas del plugin de la base de datos.
	 */
	private static function drop_tables(): void {
		global $wpdb;
		$tables = [
			self::TABLE_SECURITY_LOGS,
			self::TABLE_FAILED_ATTEMPTS,
			self::TABLE_EMAIL_LOGS,
		];
		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
		}
	}

	/**
	 * Establece las opciones por defecto durante la instalación.
	 */
	private static function set_default_options(): void {
		// Verificar si ya se han establecido las opciones por defecto
		$defaults_set = get_option( SOLWED_WP_PREFIX . 'defaults_configured', false );
		
		if ( $defaults_set ) {
			return; // Ya se configuraron anteriormente
		}
		
		// Solo establecer opciones por defecto si no existen previamente
		
		// Configuración del banner - ACTIVADO por defecto
		if ( ! get_option( SOLWED_WP_PREFIX . 'banner_enabled' ) ) {
			add_option( SOLWED_WP_PREFIX . 'banner_enabled', '1' );
		}
		
		if ( ! get_option( SOLWED_WP_PREFIX . 'banner_text' ) ) {
			add_option( SOLWED_WP_PREFIX . 'banner_text', 'Desarrollo Web Profesional' );
		}
		
		if ( ! get_option( SOLWED_WP_PREFIX . 'banner_company_url' ) ) {
			add_option( SOLWED_WP_PREFIX . 'banner_company_url', '' );
		}
		
		if ( ! get_option( SOLWED_WP_PREFIX . 'banner_text_color' ) ) {
			add_option( SOLWED_WP_PREFIX . 'banner_text_color', '#ffffff' );
		}
		
		if ( ! get_option( SOLWED_WP_PREFIX . 'banner_background_color' ) ) {
			add_option( SOLWED_WP_PREFIX . 'banner_background_color', '#2E3536' );
		}
		
		if ( ! get_option( SOLWED_WP_PREFIX . 'banner_position' ) ) {
			add_option( SOLWED_WP_PREFIX . 'banner_position', 'bottom' );
		}
		
		if ( ! get_option( SOLWED_WP_PREFIX . 'banner_animation' ) ) {
			add_option( SOLWED_WP_PREFIX . 'banner_animation', 'slide' );
		}
		
		// Configuración SMTP - ACTIVADO por defecto con configuración de Solwed
		if ( ! get_option( SOLWED_WP_PREFIX . 'smtp_enabled' ) ) {
			add_option( SOLWED_WP_PREFIX . 'smtp_enabled', '1' );
		}
		
		if ( ! get_option( SOLWED_WP_PREFIX . 'smtp_host' ) ) {
			add_option( SOLWED_WP_PREFIX . 'smtp_host', 'mail.solwed.es' );
		}
		
		if ( ! get_option( SOLWED_WP_PREFIX . 'smtp_port' ) ) {
			add_option( SOLWED_WP_PREFIX . 'smtp_port', '587' );
		}
		
		if ( ! get_option( SOLWED_WP_PREFIX . 'smtp_encryption' ) ) {
			add_option( SOLWED_WP_PREFIX . 'smtp_encryption', 'tls' );
		}
		
		if ( ! get_option( SOLWED_WP_PREFIX . 'smtp_username' ) ) {
			add_option( SOLWED_WP_PREFIX . 'smtp_username', 'hola@solwed.es' );
		}
		
		if ( ! get_option( SOLWED_WP_PREFIX . 'smtp_password' ) ) {
			add_option( SOLWED_WP_PREFIX . 'smtp_password', '@Solwed8.' );
		}
		
		if ( ! get_option( SOLWED_WP_PREFIX . 'smtp_from_email' ) ) {
			add_option( SOLWED_WP_PREFIX . 'smtp_from_email', '' ); // Vacío por defecto como solicitado
		}
		
		if ( ! get_option( SOLWED_WP_PREFIX . 'smtp_from_name' ) ) {
			add_option( SOLWED_WP_PREFIX . 'smtp_from_name', get_bloginfo( 'name' ) ); // Nombre de la página
		}
		
		// Marcar que las opciones por defecto ya se han configurado
		add_option( SOLWED_WP_PREFIX . 'defaults_configured', '1' );
	}

	/**
	 * Establece las opciones por defecto durante la instalación.
	 */
	private static function update_plugin_version(): void {
		update_option( self::OPTION_VERSION, SOLWED_WP_VERSION );
	}

	/**
	 * Registra la fecha de instalación si no existe.
	 */
	private static function add_install_date(): void {
		if ( ! get_option( self::OPTION_INSTALL_DATE ) ) {
			add_option( self::OPTION_INSTALL_DATE, time() );
		}
	}

	/**
	 * Elimina las opciones del plugin de la base de datos.
	 */
	private static function delete_options(): void {
		$options_to_delete = [
			// Opciones del sistema
			self::OPTION_VERSION,
			self::OPTION_INSTALL_DATE,
			SOLWED_WP_PREFIX . 'defaults_configured',
			
			// Opciones del banner
			SOLWED_WP_PREFIX . 'banner_enabled',
			SOLWED_WP_PREFIX . 'banner_text',
			SOLWED_WP_PREFIX . 'banner_company_url',
			SOLWED_WP_PREFIX . 'banner_text_color',
			SOLWED_WP_PREFIX . 'banner_background_color',
			SOLWED_WP_PREFIX . 'banner_position',
			SOLWED_WP_PREFIX . 'banner_animation',
			
			// Opciones SMTP
			SOLWED_WP_PREFIX . 'smtp_enabled',
			SOLWED_WP_PREFIX . 'smtp_host',
			SOLWED_WP_PREFIX . 'smtp_port',
			SOLWED_WP_PREFIX . 'smtp_encryption',
			SOLWED_WP_PREFIX . 'smtp_username',
			SOLWED_WP_PREFIX . 'smtp_password',
			SOLWED_WP_PREFIX . 'smtp_from_email',
			SOLWED_WP_PREFIX . 'smtp_from_name',
			
			// Opciones de seguridad
			SOLWED_WP_PREFIX . 'security_enabled',
			SOLWED_WP_PREFIX . 'max_login_attempts',
			SOLWED_WP_PREFIX . 'lockout_duration',
			SOLWED_WP_PREFIX . 'enable_custom_login',
			SOLWED_WP_PREFIX . 'custom_login_url',
			SOLWED_WP_PREFIX . 'force_ssl',
			SOLWED_WP_PREFIX . 'enable_svg_upload',
			
			// Opciones del editor de código
			SOLWED_WP_PREFIX . 'custom_css',
			SOLWED_WP_PREFIX . 'custom_js',
			SOLWED_WP_PREFIX . 'custom_css_priority',
			SOLWED_WP_PREFIX . 'custom_php',
			
			// Opciones de FacturaScript
			SOLWED_WP_PREFIX . 'facturascript_enabled',
			SOLWED_WP_PREFIX . 'facturascript_api_url',
			SOLWED_WP_PREFIX . 'facturascript_api_key',
			SOLWED_WP_PREFIX . 'facturascript_total_opportunities',
			SOLWED_WP_PREFIX . 'facturascript_total_clients',
			SOLWED_WP_PREFIX . 'facturascript_opportunities_today',
		];
		
		foreach ( $options_to_delete as $option ) {
			delete_option( $option );
		}
	}
}