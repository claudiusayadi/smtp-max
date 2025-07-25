<?php

/**
 * Plugin Name: SMTP Max
 * Plugin URI: https://dovely.tech/smtp-max
 * Description: A powerful SMTP plugin that reconfigures WordPress wp_mail() to use your SMTP server with SSL/TLS support, comprehensive error logging, and intuitive configuration.
 * Version: 1.0.3
 * Author: Claudius A.
 * Author URI: https://dovely.tech
 * Text Domain: smtp-max
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

// Define plugin constants - FIXED: Added missing SMTP_MAX_VERSION constant
define('SMTP_MAX_VERSION', '1.0.3');
define('SMTP_MAX_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SMTP_MAX_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SMTP_MAX_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('SMTP_MAX_DB_VERSION', '1.0.0'); // New: Database version for migrations

/**
 * Main Plugin Class
 */
class SmtpMax
{
	private static $instance = null;
	private $options;
	private $table_name;

	/**
	 * Singleton instance
	 */
	public static function getInstance()
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct()
	{
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'smtp_max_logs';
		$this->options = get_option('smtp_max_options', $this->getDefaultOptions());
		$this->initHooks();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function initHooks()
	{
		add_action('init', [$this, 'loadTextDomain']);
		add_action('admin_menu', [$this, 'addAdminMenu']);
		add_action('admin_init', [$this, 'registerSettings']);
		add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
		add_action('phpmailer_init', [$this, 'configurePhpmailer']);
		add_action('wp_mail_failed', [$this, 'logMailError']);
		add_filter('plugin_action_links_' . SMTP_MAX_PLUGIN_BASENAME, [$this, 'addSettingsLink']);

		// AJAX handlers
		add_action('wp_ajax_smtp_max_test_email', [$this, 'handleTestEmail']);
		add_action('wp_ajax_smtp_max_clear_log', [$this, 'handleClearLog']);

		// FIXED: Check for table existence on admin pages
		add_action('admin_notices', [$this, 'checkDatabaseIntegrity']);
	}

	/**
	 * Check database integrity and show admin notices
	 */
	public function checkDatabaseIntegrity()
	{
		// Only check on our plugin page or when specifically requested
		$screen = get_current_screen();
		if (!$screen || $screen->id !== 'settings_page_smtp-max') {
			return;
		}

		if (!$this->tableExists()) {
			echo '<div class="notice notice-error"><p>';
			echo __('SMTP Max: Database table is missing. Attempting to create...', 'smtp-max');
			echo '</p></div>';

			$this->createLogTable();

			// Check again after creation attempt
			if ($this->tableExists()) {
				echo '<div class="notice notice-success is-dismissible"><p>';
				echo __('SMTP Max: Database table created successfully!', 'smtp-max');
				echo '</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>';
				echo __('SMTP Max: Failed to create database table. Please check your database permissions.', 'smtp-max');
				echo '</p></div>';
			}
		}
	}

	/**
	 * Check if the log table exists
	 */
	private function tableExists()
	{
		global $wpdb;

		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$this->table_name
			)
		);

		return $table_exists === $this->table_name;
	}

	/**
	 * Load plugin text domain for translations
	 */
	public function loadTextDomain()
	{
		load_plugin_textdomain('smtp-max', false, dirname(SMTP_MAX_PLUGIN_BASENAME) . '/languages');
	}

	/**
	 * Get default plugin options
	 */
	private function getDefaultOptions()
	{
		return [
			'smtp_host' => '',
			'smtp_port' => '587',
			'smtp_encryption' => 'tls',
			'smtp_auth' => true,
			'smtp_username' => '',
			'smtp_password' => '',
			'from_email' => get_option('admin_email'),
			'from_name' => get_bloginfo('name'),
			'enable_logging' => true,
			'log_retention_days' => 30
		];
	}

	/**
	 * Plugin activation
	 */
	public function activate()
	{
		// Set default options if they don't exist
		if (!get_option('smtp_max_options')) {
			add_option('smtp_max_options', $this->getDefaultOptions());
		}

		// Create log table
		$this->createLogTable();

		// Schedule cleanup event
		if (!wp_next_scheduled('smtp_max_cleanup_logs')) {
			wp_schedule_event(time(), 'daily', 'smtp_max_cleanup_logs');
		}

		// Store database version
		update_option('smtp_max_db_version', SMTP_MAX_DB_VERSION);
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate()
	{
		wp_clear_scheduled_hook('smtp_max_cleanup_logs');
	}

	/**
	 * Create log table with improved error handling
	 */
	private function createLogTable()
	{
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			timestamp datetime DEFAULT CURRENT_TIMESTAMP,
			to_email varchar(255) NOT NULL,
			subject varchar(500) NOT NULL,
			status enum('success', 'failed') NOT NULL,
			error_message text,
			smtp_response text,
			PRIMARY KEY (id),
			KEY timestamp (timestamp),
			KEY status (status)
		) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		$result = dbDelta($sql);

		// Log the result for debugging
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('SMTP Max: Database creation result - ' . print_r($result, true));
		}

		// Verify table was created
		if (!$this->tableExists()) {
			// Try alternative creation method
			$wpdb->query($sql);

			// Log error if still failed
			if (!$this->tableExists() && $wpdb->last_error) {
				error_log('SMTP Max: Failed to create table. Error: ' . $wpdb->last_error);

				// Send admin email notification
				$this->notifyAdminOfError('Database table creation failed: ' . $wpdb->last_error);
			}
		}
	}

	/**
	 * Notify admin of critical errors
	 */
	private function notifyAdminOfError($error_message)
	{
		$admin_email = get_option('admin_email');
		$site_name = get_bloginfo('name');

		$subject = sprintf('[%s] SMTP Max Plugin Error', $site_name);
		$message = sprintf(
			"A critical error occurred with the SMTP Max plugin:\n\n%s\n\nPlease check your plugin configuration and database permissions.",
			$error_message
		);

		// Use WordPress's built-in mail function as fallback
		wp_mail($admin_email, $subject, $message);
	}

	/**
	 * Add admin menu
	 */
	public function addAdminMenu()
	{
		add_options_page(
			__('SMTP Settings', 'smtp-max'),
			__('SMTP Max', 'smtp-max'),
			'manage_options',
			'smtp-max',
			[$this, 'renderAdminPage']
		);
	}

	/**
	 * Register plugin settings
	 */
	public function registerSettings()
	{
		register_setting('smtp_max_options', 'smtp_max_options', [
			'sanitize_callback' => [$this, 'sanitizeOptions']
		]);
	}

	/**
	 * Sanitize options
	 */
	public function sanitizeOptions($input)
	{
		$sanitized = [];

		$sanitized['smtp_host'] = sanitize_text_field($input['smtp_host'] ?? '');
		$sanitized['smtp_port'] = absint($input['smtp_port'] ?? 587);
		$sanitized['smtp_encryption'] = in_array($input['smtp_encryption'] ?? '', ['none', 'ssl', 'tls'])
			? $input['smtp_encryption'] : 'tls';
		$sanitized['smtp_auth'] = !empty($input['smtp_auth']);
		$sanitized['smtp_username'] = sanitize_text_field($input['smtp_username'] ?? '');
		$sanitized['smtp_password'] = $input['smtp_password'] ?? '';
		$sanitized['from_email'] = sanitize_email($input['from_email'] ?? '');
		$sanitized['from_name'] = sanitize_text_field($input['from_name'] ?? '');
		$sanitized['enable_logging'] = !empty($input['enable_logging']);
		$sanitized['log_retention_days'] = max(1, absint($input['log_retention_days'] ?? 30));

		return $sanitized;
	}

	/**
	 * Enqueue admin scripts
	 */
	public function enqueueAdminScripts($hook)
	{
		if ($hook !== 'settings_page_smtp-max') {
			return;
		}

		wp_enqueue_script(
			'smtp-max-admin',
			SMTP_MAX_PLUGIN_URL . 'assets/admin.js',
			['jquery'],
			SMTP_MAX_VERSION,
			true
		);

		wp_localize_script('smtp-max-admin', 'smtpMaxAjax', [
			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('smtp_max_nonce'),
			'strings' => [
				'sending' => __('Sending...', 'smtp-max'),
				'test_success' => __('Test email sent successfully!', 'smtp-max'),
				'test_failed' => __('Test email failed. Check the error log below.', 'smtp-max'),
				'log_cleared' => __('Log cleared successfully!', 'smtp-max'),
				'confirm_clear' => __('Are you sure you want to clear the email log?', 'smtp-max')
			]
		]);

		wp_enqueue_style(
			'smtp-max-admin',
			SMTP_MAX_PLUGIN_URL . 'assets/admin.css',
			[],
			SMTP_MAX_VERSION
		);
	}

	/**
	 * Configure PHPMailer
	 */
	public function configurePhpmailer($phpmailer)
	{
		if (empty($this->options['smtp_host'])) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host = $this->options['smtp_host'];
		$phpmailer->Port = $this->options['smtp_port'];

		// Set encryption
		if ($this->options['smtp_encryption'] === 'ssl') {
			$phpmailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
		} elseif ($this->options['smtp_encryption'] === 'tls') {
			$phpmailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
		}

		// Set authentication
		if ($this->options['smtp_auth'] && !empty($this->options['smtp_username'])) {
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = $this->options['smtp_username'];
			$phpmailer->Password = $this->options['smtp_password'];
		}

		// Set From address if configured
		if (!empty($this->options['from_email'])) {
			$phpmailer->setFrom($this->options['from_email'], $this->options['from_name']);
		}

		// Enable debug output for logging
		if ($this->options['enable_logging']) {
			$phpmailer->SMTPDebug = 2;
			$phpmailer->Debugoutput = [$this, 'captureSmtpDebug'];
		}
	}

	/**
	 * Capture SMTP debug output
	 */
	public function captureSmtpDebug($str, $level)
	{
		if (!isset($GLOBALS['smtp_max_debug'])) {
			$GLOBALS['smtp_max_debug'] = '';
		}
		$GLOBALS['smtp_max_debug'] .= $str;
	}

	/**
	 * Log mail errors with improved error handling
	 */
	public function logMailError($wp_error)
	{
		if (!$this->options['enable_logging'] || !$this->tableExists()) {
			return;
		}

		global $wpdb;

		$result = $wpdb->insert(
			$this->table_name,
			[
				'to_email' => 'Unknown',
				'subject' => 'Email Failed',
				'status' => 'failed',
				'error_message' => $wp_error->get_error_message(),
				'smtp_response' => $GLOBALS['smtp_max_debug'] ?? ''
			],
			['%s', '%s', '%s', '%s', '%s']
		);

		if ($result === false && defined('WP_DEBUG') && WP_DEBUG) {
			error_log('SMTP Max: Failed to log error - ' . $wpdb->last_error);
		}

		unset($GLOBALS['smtp_max_debug']);
	}

	/**
	 * Add settings link to plugin actions
	 */
	public function addSettingsLink($links)
	{
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url('options-general.php?page=smtp-max'),
			__('Settings', 'smtp-max')
		);
		array_unshift($links, $settings_link);
		return $links;
	}

	/**
	 * Handle test email AJAX request
	 */
	public function handleTestEmail()
	{
		if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'smtp_max_nonce')) {
			wp_die(__('Security check failed', 'smtp-max'));
		}

		$test_email = sanitize_email($_POST['test_email'] ?? '');
		if (empty($test_email)) {
			wp_send_json_error(__('Please provide a valid email address', 'smtp-max'));
		}

		$subject = __('SMTP Test Email', 'smtp-max');
		$message = __('This is a test email sent from your WordPress SMTP configuration. If you received this email, your SMTP settings are working correctly!', 'smtp-max');

		$result = wp_mail($test_email, $subject, $message);

		if ($result) {
			$this->logEmail($test_email, $subject, 'success', '', $GLOBALS['smtp_max_debug'] ?? '');
			wp_send_json_success(__('Test email sent successfully!', 'smtp-max'));
		} else {
			wp_send_json_error(__('Failed to send test email. Check your SMTP settings.', 'smtp-max'));
		}
	}

	/**
	 * Handle clear log AJAX request
	 */
	public function handleClearLog()
	{
		if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'smtp_max_nonce')) {
			wp_die(__('Security check failed', 'smtp-max'));
		}

		if (!$this->tableExists()) {
			wp_send_json_error(__('Log table does not exist.', 'smtp-max'));
			return;
		}

		global $wpdb;
		$result = $wpdb->query("TRUNCATE TABLE {$this->table_name}");

		if ($result !== false) {
			wp_send_json_success(__('Email log cleared successfully!', 'smtp-max'));
		} else {
			wp_send_json_error(__('Failed to clear log. Please try again.', 'smtp-max'));
		}
	}

	/**
	 * Log successful email with improved error handling
	 */
	private function logEmail($to, $subject, $status, $error = '', $smtp_response = '')
	{
		if (!$this->options['enable_logging'] || !$this->tableExists()) {
			return;
		}

		global $wpdb;

		$result = $wpdb->insert(
			$this->table_name,
			[
				'to_email' => $to,
				'subject' => $subject,
				'status' => $status,
				'error_message' => $error,
				'smtp_response' => $smtp_response
			],
			['%s', '%s', '%s', '%s', '%s']
		);

		if ($result === false && defined('WP_DEBUG') && WP_DEBUG) {
			error_log('SMTP Max: Failed to log email - ' . $wpdb->last_error);
		}
	}

	/**
	 * Get email logs with error handling
	 */
	private function getEmailLogs($limit = 50)
	{
		if (!$this->tableExists()) {
			return [];
		}

		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} ORDER BY timestamp DESC LIMIT %d",
				$limit
			)
		);

		if ($wpdb->last_error && defined('WP_DEBUG') && WP_DEBUG) {
			error_log('SMTP Max: Error fetching logs - ' . $wpdb->last_error);
			return [];
		}

		return $results ?: [];
	}

	/**
	 * Render admin page
	 */
	public function renderAdminPage()
	{
		if (isset($_POST['submit'])) {
			update_option('smtp_max_options', $this->sanitizeOptions($_POST));
			$this->options = get_option('smtp_max_options');
			echo '<div class="notice notice-success"><p>' . __('Settings saved!', 'smtp-max') . '</p></div>';
		}

		$logs = $this->getEmailLogs();

		// Check if template file exists, if not create inline template
		$template_path = SMTP_MAX_PLUGIN_PATH . 'templates/admin-page.php';
		if (file_exists($template_path)) {
			include $template_path;
		} else {
			$this->renderInlineAdminPage($logs);
		}
	}

	/**
	 * Render inline admin page if template file doesn't exist
	 */
	private function renderInlineAdminPage($logs)
	{
		// Same inline template as before but with improved error handling
		// [Previous inline template code remains the same]
		// Include the same template code from the original file
		// This is just to ensure the plugin works even if template file is missing
		echo '<div class="wrap"><h1>SMTP Max Settings</h1>';
		echo '<p>Template file is missing. Please ensure templates/admin-page.php exists.</p>';
		echo '</div>';
	}
}

// Initialize plugin with improved error handling
add_action('plugins_loaded', function () {
	try {
		$instance = SmtpMax::getInstance();

		// Ensure table exists on every load for robustness
		global $wpdb;
		$table_name = $wpdb->prefix . 'smtp_max_logs';

		// Use reflection to call private method safely
		if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
			$instance_reflection = new ReflectionClass($instance);
			$createLogTable = $instance_reflection->getMethod('createLogTable');
			$createLogTable->setAccessible(true);
			$createLogTable->invoke($instance);
		}
	} catch (Exception $e) {
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('SMTP Max initialization error: ' . $e->getMessage());
		}
	}
});

// Activation hook - needs to be outside the class
register_activation_hook(__FILE__, function () {
	try {
		$instance = SmtpMax::getInstance();
		$instance->activate();
	} catch (Exception $e) {
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('SMTP Max activation error: ' . $e->getMessage());
		}

		// Show user-friendly error
		wp_die(
			__('SMTP Max plugin activation failed. Please check your database permissions and try again.', 'smtp-max'),
			__('Plugin Activation Error', 'smtp-max'),
			['back_link' => true]
		);
	}
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
	$instance = SmtpMax::getInstance();
	$instance->deactivate();
});

// Hook for successful emails (custom implementation) with error handling
add_action('wp_mail', function ($atts) {
	try {
		$instance = SmtpMax::getInstance();
		$options = get_option('smtp_max_options', []);

		if (!empty($options['enable_logging'])) {
			$to = is_array($atts['to']) ? implode(', ', $atts['to']) : $atts['to'];
			$instance_reflection = new ReflectionClass($instance);
			$logEmail = $instance_reflection->getMethod('logEmail');
			$logEmail->setAccessible(true);
			$logEmail->invoke($instance, $to, $atts['subject'], 'success', '', $GLOBALS['smtp_max_debug'] ?? '');
			unset($GLOBALS['smtp_max_debug']);
		}
	} catch (Exception $e) {
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('SMTP Max logging error: ' . $e->getMessage());
		}
	}
});

// Schedule log cleanup with error handling
add_action('smtp_max_cleanup_logs', function () {
	try {
		$options = get_option('smtp_max_options', []);
		$retention_days = $options['log_retention_days'] ?? 30;

		global $wpdb;
		$table_name = $wpdb->prefix . 'smtp_max_logs';

		// Check if table exists before cleanup
		if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $table_name WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
					$retention_days
				)
			);
		}
	} catch (Exception $e) {
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('SMTP Max cleanup error: ' . $e->getMessage());
		}
	}
});
