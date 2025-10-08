<?php
/**
 * Pestaña de Logs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Clase base para las tablas de logs.
 */
class Solwed_List_Table extends WP_List_Table {
	public function __construct( $args = [] ) {
		parent::__construct(
			[
				'singular' => 'log',
				'plural'   => 'logs',
				'ajax'     => false,
			]
		);
	}

	public function no_items() {
		esc_html_e( 'No hay registros disponibles.', 'solwed-wp' );
	}

	public function get_columns() {
		return [];
	}

	public function prepare_items() {
		$this->_column_headers = [ $this->get_columns(), [], [] ];
	}

	protected function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '–';
	}
}

/**
 * Tabla para los bloqueos vigentes.
 */
class Solwed_Current_Blocks_List_Table extends Solwed_List_Table {
	public function __construct() {
		parent::__construct(
			[
				'singular' => __( 'Bloqueo Vigente', 'solwed-wp' ),
				'plural'   => __( 'Bloqueos Vigentes', 'solwed-wp' ),
				'ajax'     => false,
			]
		);
	}

	public function no_items() {
		esc_html_e( 'No hay bloqueos activos en este momento.', 'solwed-wp' );
	}

	public function get_columns() {
		return [
			'ip_address'    => __( 'IP Bloqueada', 'solwed-wp' ),
			'blocked_until' => __( 'Bloqueado Hasta', 'solwed-wp' ),
			'expires_in'    => __( 'Expira en', 'solwed-wp' ),
		];
	}

	public function prepare_items() {
		global $wpdb;

		$this->_column_headers = [ $this->get_columns(), [], [] ];

		$blocked_ips_table     = $wpdb->prefix . 'solwed_blocked_ips';
		$failed_attempts_table = $wpdb->prefix . 'solwed_failed_attempts';
		$per_page              = 20;
		$current_page          = $this->get_pagenum();
		$total_items           = 0;
		$query                 = '';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$blocked_ips_table}'" ) === $blocked_ips_table ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$total_items = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$blocked_ips_table} WHERE blocked_until > NOW()" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$query       = "SELECT ip_address, blocked_until FROM {$blocked_ips_table} WHERE blocked_until > NOW() ORDER BY blocked_until DESC";
		} elseif ( $wpdb->get_var( "SHOW TABLES LIKE '{$failed_attempts_table}'" ) === $failed_attempts_table ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$total_items = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$failed_attempts_table} WHERE blocked_until IS NOT NULL AND blocked_until > NOW()" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$query       = "SELECT ip_address, blocked_until FROM {$failed_attempts_table} WHERE blocked_until IS NOT NULL AND blocked_until > NOW() ORDER BY blocked_until DESC";
		}

		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $per_page,
			]
		);

		$offset = ( $current_page - 1 ) * $per_page;

		if ( $query ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for logs display
			$this->items = $wpdb->get_results(
				$wpdb->prepare( $query . ' LIMIT %d OFFSET %d', $per_page, $offset ),
				ARRAY_A
			);
		} else {
			$this->items = [];
		}
	}

	protected function column_blocked_until( $item ) {
		return esc_html( date_i18n( 'd/m/Y H:i:s', strtotime( (string) $item['blocked_until'] ) ) );
	}

	protected function column_expires_in( $item ) {
		return esc_html( human_time_diff( current_time( 'timestamp' ), strtotime( (string) $item['blocked_until'] ) ) );
	}
}

/**
 * Tabla para los logs de email.
 */
class Solwed_Email_Logs_List_Table extends Solwed_List_Table {
	public function __construct() {
		parent::__construct(
			[
				'singular' => __( 'Email Log', 'solwed-wp' ),
				'plural'   => __( 'Email Logs', 'solwed-wp' ),
				'ajax'     => false,
			]
		);
	}

	public function get_columns() {
		return [
			'timestamp'     => __( 'Fecha', 'solwed-wp' ),
			'email_to'      => __( 'Para', 'solwed-wp' ),
			'subject'       => __( 'Asunto', 'solwed-wp' ),
			'status'        => __( 'Estado', 'solwed-wp' ),
			'error_message' => __( 'Error', 'solwed-wp' ),
		];
	}

	public function prepare_items() {
		global $wpdb;

		$this->_column_headers = [ $this->get_columns(), [], [] ];
		$table_name            = $wpdb->prefix . 'solwed_email_logs';
		$per_page              = 20;
		$current_page          = $this->get_pagenum();
		$total_items           = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $per_page,
			]
		);

		$offset = ( $current_page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for logs display
		$this->items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} ORDER BY timestamp DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);
	}

	protected function column_timestamp( $item ) {
		return esc_html( date_i18n( 'd/m/Y H:i:s', strtotime( $item['timestamp'] ) ) );
	}

	protected function column_subject( $item ) {
		return esc_html( wp_trim_words( $item['subject'], 10 ) );
	}

	protected function column_status( $item ) {
		$status_class = 'sent' === $item['status'] ? 'sent' : 'failed';
		$status_label = 'sent' === $item['status'] ? __( 'Enviado', 'solwed-wp' ) : __( 'Error', 'solwed-wp' );

		return sprintf( '<span class="solwed-status-badge %s">%s</span>', esc_attr( $status_class ), esc_html( $status_label ) );
	}

	protected function column_error_message( $item ) {
		if ( 'failed' === $item['status'] && ! empty( $item['error_message'] ) ) {
			return sprintf(
				'<span class="dashicons dashicons-warning"></span><div class="solwed-error-tooltip">%s</div>',
				esc_html( $item['error_message'] )
			);
		}
		return '–';
	}
}

/**
 * Tabla para los logs de seguridad.
 */
class Solwed_Security_Logs_List_Table extends Solwed_List_Table {
	public function __construct() {
		parent::__construct(
			[
				'singular' => __( 'Security Log', 'solwed-wp' ),
				'plural'   => __( 'Security Logs', 'solwed-wp' ),
				'ajax'     => false,
			]
		);
	}

	public function get_columns() {
		return [
			'timestamp'  => __( 'Fecha de Bloqueo', 'solwed-wp' ),
			'ip_address' => __( 'IP Bloqueada', 'solwed-wp' ),
			'reason'     => __( 'Razón', 'solwed-wp' ),
		];
	}

	public function prepare_items() {
		global $wpdb;

		$this->_column_headers = [ $this->get_columns(), [], [] ];
		$table_name            = $wpdb->prefix . 'solwed_security_logs';
		$per_page              = 20;
		$current_page          = $this->get_pagenum();
		$total_items           = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$table_name} WHERE action = %s", 'ip_blocked' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $per_page,
			]
		);

		$offset = ( $current_page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for logs display
		$this->items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ip_address, action, timestamp FROM {$table_name} WHERE action = %s ORDER BY timestamp DESC LIMIT %d OFFSET %d",
				'ip_blocked',
				$per_page,
				$offset
			),
			ARRAY_A
		);
	}

	protected function column_timestamp( $item ) {
		return esc_html( date_i18n( 'd/m/Y H:i:s', strtotime( $item['timestamp'] ) ) );
	}

	protected function column_reason( $item ) {
		return __( 'Demasiados intentos de login fallidos', 'solwed-wp' );
	}
}


/**
 * Renderiza el contenido de la pestaña de logs.
 */
function render_logs_tab() {
	// Instanciar las tablas
	$current_blocks_table = new Solwed_Current_Blocks_List_Table();
	$current_blocks_table->prepare_items();

	$email_logs_table = new Solwed_Email_Logs_List_Table();
	$email_logs_table->prepare_items();

	$security_logs_table = new Solwed_Security_Logs_List_Table();
	$security_logs_table->prepare_items();

	// Obtener estadísticas rápidas
	global $wpdb;
	$log_prefix = SOLWED_WP_PREFIX;
	
	$stats = [
		'current_blocks' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$log_prefix}failed_attempts WHERE blocked_until > NOW()"), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		'total_blocks' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$log_prefix}security_logs"), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		'email_sent' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$log_prefix}email_logs WHERE status = 'sent'"), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		'email_failed' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$log_prefix}email_logs WHERE status != 'sent'"), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		'email_today' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$log_prefix}email_logs WHERE DATE(timestamp) = CURDATE()"), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		'blocks_today' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$log_prefix}security_logs WHERE DATE(timestamp) = CURDATE()") // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	];
	?>

    <style>
    .solwed-logs-container {
        display: flex;
        gap: 20px;
        margin: 20px 0;
    }
    .solwed-logs-main {
        flex: 2;
        max-width: 800px;
    }
    .solwed-logs-sidebar {
        flex: 1;
        min-width: 300px;
        max-width: 350px;
    }
    .solwed-sidebar-panel {
        position: sticky;
        top: 32px;
        margin-bottom: 20px;
    }
    .solwed-log-section {
        margin-bottom: 30px;
    }
    .solwed-log-section h3 {
        margin-bottom: 15px;
        padding-bottom: 8px;
        border-bottom: 1px solid #dcdcde;
    }
    </style>

    <div class="solwed-logs-container">
        <!-- CONTENIDO PRINCIPAL (Izquierda) -->
        <div class="solwed-logs-main">
            <div class="solwed-form-section">
                <h2><?php esc_html_e( '📋 Registros del Sistema', 'solwed-wp' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Aquí puedes ver los registros de actividad del plugin, como envíos de email y bloqueos de seguridad.', 'solwed-wp' ); ?></p>
            </div>

            <div class="solwed-log-section">
                <h3><?php esc_html_e( '🚫 Bloqueos Vigentes', 'solwed-wp' ); ?></h3>
                <form method="post">
                    <?php $current_blocks_table->display(); ?>
                </form>
            </div>

            <div class="solwed-log-section">
                <h3><?php esc_html_e( '📧 Registros de Email', 'solwed-wp' ); ?></h3>
                <form method="post">
                    <?php $email_logs_table->display(); ?>
                </form>
            </div>

            <div class="solwed-log-section">
                <h3><?php esc_html_e( '🔒 Registros de Seguridad (Bloqueos)', 'solwed-wp' ); ?></h3>
                <form method="post">
                    <?php $security_logs_table->display(); ?>
                </form>
            </div>
        </div>

        <!-- PANEL DE ESTADÍSTICAS (Derecha) -->
        <div class="solwed-logs-sidebar">
            <div class="solwed-sidebar-panel solwed-panel">
                <h3><?php esc_html_e('📊 Resumen General', 'solwed-wp'); ?></h3>
                <div class="solwed-stats-info">
                    <p><strong><?php esc_html_e('Bloqueos Activos:', 'solwed-wp'); ?></strong> 
                        <span class="solwed-status-badge <?php echo ($stats['current_blocks'] > 0) ? 'failed' : 'sent'; ?>">
                            <?php echo esc_html($stats['current_blocks']); ?>
                        </span>
                    </p>
                    <p><strong><?php esc_html_e('Total Bloqueos:', 'solwed-wp'); ?></strong> <?php echo esc_html($stats['total_blocks']); ?></p>
                    <p><strong><?php esc_html_e('Emails Enviados:', 'solwed-wp'); ?></strong> 
                        <span class="solwed-status-badge sent"><?php echo esc_html($stats['email_sent']); ?></span>
                    </p>
                    <p><strong><?php esc_html_e('Emails Fallidos:', 'solwed-wp'); ?></strong> 
                        <span class="solwed-status-badge <?php echo ($stats['email_failed'] > 0) ? 'failed' : 'sent'; ?>">
                            <?php echo esc_html($stats['email_failed']); ?>
                        </span>
                    </p>
                </div>
            </div>

            <div class="solwed-sidebar-panel solwed-panel">
                <h3><?php esc_html_e('📅 Actividad de Hoy', 'solwed-wp'); ?></h3>
                <div class="solwed-stats-info">
                    <p><strong><?php esc_html_e('Emails Hoy:', 'solwed-wp'); ?></strong> 
                        <span class="solwed-status-badge <?php echo ($stats['email_today'] > 0) ? 'sent' : 'failed'; ?>">
                            <?php echo esc_html($stats['email_today']); ?>
                        </span>
                    </p>
                    <p><strong><?php esc_html_e('Bloqueos Hoy:', 'solwed-wp'); ?></strong> 
                        <span class="solwed-status-badge <?php echo ($stats['blocks_today'] > 0) ? 'failed' : 'sent'; ?>">
                            <?php echo esc_html($stats['blocks_today']); ?>
                        </span>
                    </p>
                    <p><strong><?php esc_html_e('Fecha:', 'solwed-wp'); ?></strong> <?php echo esc_html(date_i18n('d/m/Y')); ?></p>
                    <p><strong><?php esc_html_e('Hora:', 'solwed-wp'); ?></strong> <?php echo esc_html(date_i18n('H:i:s')); ?></p>
                </div>
            </div>

            <?php if ($stats['email_failed'] > 0): ?>
            <div class="solwed-sidebar-panel solwed-panel" style="border-left: 4px solid #d63638;">
                <h3><?php esc_html_e('⚠️ Emails Fallidos', 'solwed-wp'); ?></h3>
                <p style="color: #d63638; font-size: 12px; line-height: 1.4;">
                    <strong><?php echo esc_html($stats['email_failed']); ?></strong> <?php esc_html_e('emails han fallado.', 'solwed-wp'); ?><br>
                    <?php esc_html_e('Revisa la configuración SMTP en la pestaña correspondiente.', 'solwed-wp'); ?>
                </p>
            </div>
            <?php endif; ?>

            <?php if ($stats['current_blocks'] > 0): ?>
            <div class="solwed-sidebar-panel solwed-panel" style="border-left: 4px solid #ff9800;">
                <h3><?php esc_html_e('🛡️ Bloqueos Activos', 'solwed-wp'); ?></h3>
                <p style="color: #ff9800; font-size: 12px; line-height: 1.4;">
                    <strong><?php echo esc_html($stats['current_blocks']); ?></strong> <?php esc_html_e('IPs están bloqueadas actualmente.', 'solwed-wp'); ?><br>
                    <?php esc_html_e('Estos bloqueos expirarán automáticamente según la configuración.', 'solwed-wp'); ?>
                </p>
            </div>
            <?php endif; ?>

            <div class="solwed-sidebar-panel solwed-panel">
                <h3><?php esc_html_e('🔧 Gestión de Logs', 'solwed-wp'); ?></h3>
                <ul style="padding-left: 20px; line-height: 1.6;">
                    <li><?php esc_html_e('Los logs se limpian automáticamente después de 30 días', 'solwed-wp'); ?></li>
                    <li><?php esc_html_e('Los bloqueos expiran según la configuración de seguridad', 'solwed-wp'); ?></li>
                    <li><?php esc_html_e('Revisa regularmente los emails fallidos', 'solwed-wp'); ?></li>
                    <li><?php esc_html_e('Los bloqueos excesivos pueden indicar un ataque', 'solwed-wp'); ?></li>
                </ul>
            </div>

            <div class="solwed-sidebar-panel solwed-panel">
                <h3><?php esc_html_e('📈 Ratios de Éxito', 'solwed-wp'); ?></h3>
                <div class="solwed-stats-info">
                    <?php 
                    $total_emails = $stats['email_sent'] + $stats['email_failed'];
                    $email_success_rate = $total_emails > 0 ? round(($stats['email_sent'] / $total_emails) * 100, 1) : 0;
                    ?>
                    <p><strong><?php esc_html_e('Tasa Éxito Emails:', 'solwed-wp'); ?></strong> 
                        <span class="solwed-status-badge <?php echo ($email_success_rate >= 90) ? 'sent' : 'failed'; ?>">
                            <?php echo esc_html($email_success_rate); ?>%
                        </span>
                    </p>
                    <p><strong><?php esc_html_e('Total Emails:', 'solwed-wp'); ?></strong> <?php echo esc_html($total_emails); ?></p>
                    
                    <?php if ($stats['current_blocks'] > 0): ?>
                    <p><strong><?php esc_html_e('Seguridad:', 'solwed-wp'); ?></strong> 
                        <span class="solwed-status-badge failed"><?php esc_html_e('Amenazas Activas', 'solwed-wp'); ?></span>
                    </p>
                    <?php else: ?>
                    <p><strong><?php esc_html_e('Seguridad:', 'solwed-wp'); ?></strong> 
                        <span class="solwed-status-badge sent"><?php esc_html_e('Sin Amenazas', 'solwed-wp'); ?></span>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
	<?php
}
