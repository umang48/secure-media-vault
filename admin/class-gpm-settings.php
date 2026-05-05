<?php
/**
 * Plugin Settings Page.
 *
 * Registers all settings sections and fields using the WordPress Settings API.
 *
 * @package SecureMediaVault
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GPM_Settings
 */
class GPM_Settings {

	/**
	 * Single instance.
	 *
	 * @var GPM_Settings
	 */
	private static $instance = null;

	/**
	 * Settings option group.
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'gpm_settings_group';

	/**
	 * Get single instance.
	 *
	 * @return GPM_Settings
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'update_option_gpm_token_expiry', array( $this, 'flush_on_settings_change' ) );
	}

	/**
	 * Register all settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		$settings = array(
			'gpm_default_protection'   => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'public',
			),
			'gpm_token_expiry'          => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 3600,
			),
			'gpm_hotlink_protection'    => array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_bool' ),
				'default'           => true,
			),
			'gpm_seo_noindex'           => array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_bool' ),
				'default'           => true,
			),
			'gpm_disable_attachments'   => array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_bool' ),
				'default'           => true,
			),
			'gpm_robots_txt'            => array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_bool' ),
				'default'           => false,
			),
			'gpm_debug_mode'            => array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_bool' ),
				'default'           => false,
			),
			'gpm_ip_validation'         => array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_bool' ),
				'default'           => false,
			),
			'gpm_stream_large_files'    => array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_bool' ),
				'default'           => true,
			),
			'gpm_stream_threshold'      => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 10,
			),
			'gpm_log_access'            => array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_bool' ),
				'default'           => true,
			),
			'gpm_log_retention_days'    => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 30,
			),
		);

		foreach ( $settings as $key => $args ) {
			register_setting(
				self::OPTION_GROUP,
				$key,
				array(
					'type'              => $args['type'],
					'sanitize_callback' => $args['sanitize_callback'],
					'default'           => $args['default'],
				)
			);
		}

		// Section: General.
		add_settings_section(
			'gpm_general',
			__( 'General Settings', 'guardify-private-media' ),
			'__return_null',
			self::OPTION_GROUP
		);

		// Section: Token.
		add_settings_section(
			'gpm_token',
			__( 'Token & URL Settings', 'guardify-private-media' ),
			'__return_null',
			self::OPTION_GROUP
		);

		// Section: SEO.
		add_settings_section(
			'gpm_seo',
			__( 'SEO & Indexing Protection', 'guardify-private-media' ),
			'__return_null',
			self::OPTION_GROUP
		);

		// Section: Performance.
		add_settings_section(
			'gpm_performance',
			__( 'Performance', 'guardify-private-media' ),
			'__return_null',
			self::OPTION_GROUP
		);

		// Section: Logs.
		add_settings_section(
			'gpm_logs',
			__( 'Access Logging', 'guardify-private-media' ),
			'__return_null',
			self::OPTION_GROUP
		);

		// Fields.
		$this->add_fields();
	}

	/**
	 * Add settings fields to their respective sections.
	 *
	 * @return void
	 */
	private function add_fields() {
		$fields = array(
			// General.
			array(
				'id'       => 'gpm_default_protection',
				'title'    => __( 'Default Protection', 'guardify-private-media' ),
				'callback' => array( $this, 'render_default_protection' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'gpm_general',
			),
			array(
				'id'       => 'gpm_hotlink_protection',
				'title'    => __( 'Hotlink Protection', 'guardify-private-media' ),
				'callback' => array( $this, 'render_checkbox_field' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'gpm_general',
				'args'     => array(
					'option'      => 'gpm_hotlink_protection',
					'description' => __( 'Block files from being embedded on external websites.', 'guardify-private-media' ),
				),
			),

			// Token.
			array(
				'id'       => 'gpm_token_expiry',
				'title'    => __( 'Token Expiry (seconds)', 'guardify-private-media' ),
				'callback' => array( $this, 'render_number_field' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'gpm_token',
				'args'     => array(
					'option'      => 'gpm_token_expiry',
					'description' => __( 'How long a generated secure URL remains valid. Default: 3600 (1 hour).', 'guardify-private-media' ),
					'min'         => 60,
					'max'         => 86400 * 7,
					'step'        => 60,
				),
			),
			array(
				'id'       => 'gpm_ip_validation',
				'title'    => __( 'IP Validation', 'guardify-private-media' ),
				'callback' => array( $this, 'render_checkbox_field' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'gpm_token',
				'args'     => array(
					'option'      => 'gpm_ip_validation',
					'description' => __( 'Bind tokens to the requester\'s IP address (may break behind proxies/CDNs).', 'guardify-private-media' ),
				),
			),

			// SEO.
			array(
				'id'       => 'gpm_seo_noindex',
				'title'    => __( 'Noindex Protected Files', 'guardify-private-media' ),
				'callback' => array( $this, 'render_checkbox_field' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'gpm_seo',
				'args'     => array(
					'option'      => 'gpm_seo_noindex',
					'description' => __( 'Send X-Robots-Tag: noindex, nofollow for all protected file requests.', 'guardify-private-media' ),
				),
			),
			array(
				'id'       => 'gpm_disable_attachments',
				'title'    => __( 'Disable Attachment Pages', 'guardify-private-media' ),
				'callback' => array( $this, 'render_checkbox_field' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'gpm_seo',
				'args'     => array(
					'option'      => 'gpm_disable_attachments',
					'description' => __( 'Redirect WordPress media attachment pages to the parent post or homepage.', 'guardify-private-media' ),
				),
			),
			array(
				'id'       => 'gpm_robots_txt',
				'title'    => __( 'Block via robots.txt', 'guardify-private-media' ),
				'callback' => array( $this, 'render_checkbox_field' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'gpm_seo',
				'args'     => array(
					'option'      => 'gpm_robots_txt',
					'description' => __( 'Add Disallow rules for the uploads directory in robots.txt.', 'guardify-private-media' ),
				),
			),

			// Performance.
			array(
				'id'       => 'gpm_stream_large_files',
				'title'    => __( 'Stream Large Files', 'guardify-private-media' ),
				'callback' => array( $this, 'render_checkbox_field' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'gpm_performance',
				'args'     => array(
					'option'      => 'gpm_stream_large_files',
					'description' => __( 'Use chunked streaming (with HTTP Range support) for files above the threshold.', 'guardify-private-media' ),
				),
			),
			array(
				'id'       => 'gpm_stream_threshold',
				'title'    => __( 'Streaming Threshold (MB)', 'guardify-private-media' ),
				'callback' => array( $this, 'render_number_field' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'gpm_performance',
				'args'     => array(
					'option'      => 'gpm_stream_threshold',
					'description' => __( 'Files larger than this will be streamed in chunks.', 'guardify-private-media' ),
					'min'         => 1,
					'max'         => 1000,
					'step'        => 1,
				),
			),

			// Logs.
			array(
				'id'       => 'gpm_log_access',
				'title'    => __( 'Enable Access Logging', 'guardify-private-media' ),
				'callback' => array( $this, 'render_checkbox_field' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'gpm_logs',
				'args'     => array(
					'option'      => 'gpm_log_access',
					'description' => __( 'Log all file access attempts (granted and denied).', 'guardify-private-media' ),
				),
			),
			array(
				'id'       => 'gpm_log_retention_days',
				'title'    => __( 'Log Retention (days)', 'guardify-private-media' ),
				'callback' => array( $this, 'render_number_field' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'gpm_logs',
				'args'     => array(
					'option'      => 'gpm_log_retention_days',
					'description' => __( 'Automatically delete access log entries older than this many days.', 'guardify-private-media' ),
					'min'         => 1,
					'max'         => 365,
					'step'        => 1,
				),
			),

			// Debug (in general section).
			array(
				'id'       => 'gpm_debug_mode',
				'title'    => __( 'Debug Mode', 'guardify-private-media' ),
				'callback' => array( $this, 'render_checkbox_field' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'gpm_general',
				'args'     => array(
					'option'      => 'gpm_debug_mode',
					'description' => __( 'Log debug information. Disable on production sites.', 'guardify-private-media' ),
				),
			),
		);

		foreach ( $fields as $field ) {
			add_settings_field(
				$field['id'],
				$field['title'],
				$field['callback'],
				$field['page'],
				$field['section'],
				$field['args'] ?? array()
			);
		}
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'guardify-private-media' ) );
		}
		include GPM_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Render the default protection dropdown.
	 *
	 * @return void
	 */
	public function render_default_protection() {
		$value   = get_option( 'gpm_default_protection', 'public' );
		$options = array(
			'public'    => __( 'Public (WordPress default)', 'guardify-private-media' ),
			'logged_in' => __( 'Logged-in users only', 'guardify-private-media' ),
		);
		echo '<select name="gpm_default_protection" id="gpm_default_protection">';
		foreach ( $options as $key => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $key ),
				selected( $value, $key, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Default protection type applied to newly uploaded files.', 'guardify-private-media' ) . '</p>';
	}

	/**
	 * Render a checkbox field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_checkbox_field( $args ) {
		$option      = $args['option'];
		$value       = get_option( $option );
		$description = $args['description'] ?? '';
		printf(
			'<label><input type="checkbox" name="%s" id="%s" value="1"%s> %s</label>',
			esc_attr( $option ),
			esc_attr( $option ),
			checked( 1, $value, false ),
			esc_html( $description )
		);
	}

	/**
	 * Render a number input field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_number_field( $args ) {
		$option      = $args['option'];
		$value       = get_option( $option );
		$min         = $args['min'] ?? 0;
		$max         = $args['max'] ?? 99999;
		$step        = $args['step'] ?? 1;
		$description = $args['description'] ?? '';
		printf(
			'<input type="number" name="%s" id="%s" value="%s" min="%d" max="%d" step="%d" class="small-text">',
			esc_attr( $option ),
			esc_attr( $option ),
			esc_attr( $value ),
			absint( $min ),
			absint( $max ),
			absint( $step )
		);
		if ( $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
	}

	/**
	 * Sanitize a boolean value from a checkbox.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public function sanitize_bool( $value ) {
		return (bool) $value;
	}

	/**
	 * Flush rewrite rules when key settings change.
	 *
	 * @return void
	 */
	public function flush_on_settings_change() {
		flush_rewrite_rules();
	}
}
