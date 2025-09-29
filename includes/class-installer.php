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

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $attempts_table ) ) !== $attempts_table ) {
			return; // La tabla no existe, no hay nada que migrar.
		}

		foreach ( $columns_to_add as $column_name => $column_definition ) {
			if ( ! self::column_exists( $attempts_table, $column_name ) ) {
				$wpdb->query( "ALTER TABLE {$attempts_table} ADD COLUMN {$column_name} {$column_definition}" );
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
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table_name, $column_name ) );
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
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
		}
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
		delete_option( self::OPTION_VERSION );
		delete_option( self::OPTION_INSTALL_DATE );
		// Aquí se deberían eliminar todas las demás opciones del plugin.
		// Ejemplo: delete_option('solwed_smtp_settings');
	}
}