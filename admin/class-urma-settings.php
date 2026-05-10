<?php
/**
 * Plugin Settings Page.
 *
 * Registers all settings sections and fields using the WordPress Settings API.
 *
 * @package UmangRestrictedMediaAccess
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class URMA_Settings
 */
class URMA_Settings {

	/**
	 * Single instance.
	 *
	 * @var URMA_Settings
	 */
	private static $instance = null;

	/**
	 * Settings option group.
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'urma_settings_group';

	/**
	 * Get single instance.
	 *
	 * @return URMA_Settings
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
		add_action( 'update_option_urma_token_expiry', array( $this, 'flush_on_settings_change' ) );
	}

	/**
	 * Register all settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		$settings = array(
			'urma_default_protection'   => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'public',
			),
			'urma_token_expiry'          => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 3600,
			),
			'urma_hotlink_protection'    => array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_bool' ),
				'default'           => true,
			),
			'urma_seo_noindex'           => array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_bool' ),
				'default'           => true,
			),
			'urma_disable_attachments'   => array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_bool' ),
				'default'           => true,
			),
			'urma_robots_txt'            => array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_bool' ),
				'default'           => false,
			),
			'urma_debug_mode'            => array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_bool' ),
				'default'           => false,
			),
			'urma_ip_validation'         => array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_bool' ),
				'default'           => false,
			),
			'urma_stream_large_files'    => array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_bool' ),
				'default'           => true,
			),
			'urma_stream_threshold'      => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 10,
			),
			'urma_log_access'            => array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_bool' ),
				'default'           => true,
			),
			'urma_log_retention_days'    => array(
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
			'urma_general',
			__( 'General Settings', 'secure-media-vault' ),
			'__return_null',
			self::OPTION_GROUP
		);

		// Section: Token.
		add_settings_section(
			'urma_token',
			__( 'Token & URL Settings', 'secure-media-vault' ),
			'__return_null',
			self::OPTION_GROUP
		);

		// Section: SEO.
		add_settings_section(
			'urma_seo',
			__( 'SEO & Indexing Protection', 'secure-media-vault' ),
			'__return_null',
			self::OPTION_GROUP
		);

		// Section: Performance.
		add_settings_section(
			'urma_performance',
			__( 'Performance', 'secure-media-vault' ),
			'__return_null',
			self::OPTION_GROUP
		);

		// Section: Logs.
		add_settings_section(
			'urma_logs',
			__( 'Access Logging', 'secure-media-vault' ),
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
				'id'       => 'urma_default_protection',
				'title'    => __( 'Default Protection', 'secure-media-vault' ),
				'callback' => array( $this, 'render_default_protection' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'urma_general',
			),
			array(
				'id'       => 'urma_hotlink_protection',
				'title'    => __( 'Hotlink Protection', 'secure-media-vault' ),
				'callback' => array( $this, 'render_checkbox_field' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'urma_general',
				'args'     => array(
					'option'      => 'urma_hotlink_protection',
					'description' => __( 'Block files from being embedded on external websites.', 'secure-media-vault' ),
				),
			),

			// Token.
			array(
				'id'       => 'urma_token_expiry',
				'title'    => __( 'Token Expiry (seconds)', 'secure-media-vault' ),
				'callback' => array( $this, 'render_number_field' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'urma_token',
				'args'     => array(
					'option'      => 'urma_token_expiry',
					'description' => __( 'How long a generated secure URL remains valid. Default: 3600 (1 hour).', 'secure-media-vault' ),
					'min'         => 60,
					'max'         => 86400 * 7,
					'step'        => 60,
				),
			),
			array(
				'id'       => 'urma_ip_validation',
				'title'    => __( 'IP Validation', 'secure-media-vault' ),
				'callback' => array( $this, 'render_checkbox_field' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'urma_token',
				'args'     => array(
					'option'      => 'urma_ip_validation',
					'description' => __( 'Bind tokens to the requester\'s IP address (may break behind proxies/CDNs).', 'secure-media-vault' ),
				),
			),

			// SEO.
			array(
				'id'       => 'urma_seo_noindex',
				'title'    => __( 'Noindex Protected Files', 'secure-media-vault' ),
				'callback' => array( $this, 'render_checkbox_field' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'urma_seo',
				'args'     => array(
					'option'      => 'urma_seo_noindex',
					'description' => __( 'Send X-Robots-Tag: noindex, nofollow for all protected file requests.', 'secure-media-vault' ),
				),
			),
			array(
				'id'       => 'urma_disable_attachments',
				'title'    => __( 'Disable Attachment Pages', 'secure-media-vault' ),
				'callback' => array( $this, 'render_checkbox_field' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'urma_seo',
				'args'     => array(
					'option'      => 'urma_disable_attachments',
					'description' => __( 'Redirect WordPress media attachment pages to the parent post or homepage.', 'secure-media-vault' ),
				),
			),
			array(
				'id'       => 'urma_robots_txt',
				'title'    => __( 'Block via robots.txt', 'secure-media-vault' ),
				'callback' => array( $this, 'render_checkbox_field' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'urma_seo',
				'args'     => array(
					'option'      => 'urma_robots_txt',
					'description' => __( 'Add Disallow rules for the uploads directory in robots.txt.', 'secure-media-vault' ),
				),
			),

			// Performance.
			array(
				'id'       => 'urma_stream_large_files',
				'title'    => __( 'Stream Large Files', 'secure-media-vault' ),
				'callback' => array( $this, 'render_checkbox_field' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'urma_performance',
				'args'     => array(
					'option'      => 'urma_stream_large_files',
					'description' => __( 'Use chunked streaming (with HTTP Range support) for files above the threshold.', 'secure-media-vault' ),
				),
			),
			array(
				'id'       => 'urma_stream_threshold',
				'title'    => __( 'Streaming Threshold (MB)', 'secure-media-vault' ),
				'callback' => array( $this, 'render_number_field' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'urma_performance',
				'args'     => array(
					'option'      => 'urma_stream_threshold',
					'description' => __( 'Files larger than this will be streamed in chunks.', 'secure-media-vault' ),
					'min'         => 1,
					'max'         => 1000,
					'step'        => 1,
				),
			),

			// Logs.
			array(
				'id'       => 'urma_log_access',
				'title'    => __( 'Enable Access Logging', 'secure-media-vault' ),
				'callback' => array( $this, 'render_checkbox_field' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'urma_logs',
				'args'     => array(
					'option'      => 'urma_log_access',
					'description' => __( 'Log all file access attempts (granted and denied).', 'secure-media-vault' ),
				),
			),
			array(
				'id'       => 'urma_log_retention_days',
				'title'    => __( 'Log Retention (days)', 'secure-media-vault' ),
				'callback' => array( $this, 'render_number_field' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'urma_logs',
				'args'     => array(
					'option'      => 'urma_log_retention_days',
					'description' => __( 'Automatically delete access log entries older than this many days.', 'secure-media-vault' ),
					'min'         => 1,
					'max'         => 365,
					'step'        => 1,
				),
			),

			// Debug (in general section).
			array(
				'id'       => 'urma_debug_mode',
				'title'    => __( 'Debug Mode', 'secure-media-vault' ),
				'callback' => array( $this, 'render_checkbox_field' ),
				'page'     => self::OPTION_GROUP,
				'section'  => 'urma_general',
				'args'     => array(
					'option'      => 'urma_debug_mode',
					'description' => __( 'Log debug information. Disable on production sites.', 'secure-media-vault' ),
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
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'secure-media-vault' ) );
		}
		include URMA_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Render the default protection dropdown.
	 *
	 * @return void
	 */
	public function render_default_protection() {
		$value   = get_option( 'urma_default_protection', 'public' );
		$options = array(
			'public'    => __( 'Public (WordPress default)', 'secure-media-vault' ),
			'logged_in' => __( 'Logged-in users only', 'secure-media-vault' ),
		);
		echo '<select name="urma_default_protection" id="urma_default_protection">';
		foreach ( $options as $key => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $key ),
				selected( $value, $key, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Default protection type applied to newly uploaded files.', 'secure-media-vault' ) . '</p>';
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
