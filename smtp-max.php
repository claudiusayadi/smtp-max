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

// Define plugin constants
define('SMTP_MAX_VERSION', '1.0.0');
define('SMTP_MAX_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SMTP_MAX_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SMTP_MAX_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
class SmtpMax
{

	private static $instance = null;
	private $options;

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

		// Activation/Deactivation hooks - removed from here as they need to be outside the class
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
		if (!get_option('smtp_max_options')) {
			add_option('smtp_max_options', $this->getDefaultOptions());
		}

		// Create log table
		$this->createLogTable();

		// Schedule cleanup event
		if (!wp_next_scheduled('smtp_max_cleanup_logs')) {
			wp_schedule_event(time(), 'daily', 'smtp_max_cleanup_logs');
		}
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate()
	{
		wp_clear_scheduled_hook('smtp_max_cleanup_logs');
	}

	/**
	 * Create log table
	 */
	private function createLogTable()
	{
		global $wpdb;

		$table_name = $wpdb->prefix . 'smtp_max_logs';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
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
		dbDelta($sql);
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
	 * Log mail errors
	 */
	public function logMailError($wp_error)
	{
		if (!$this->options['enable_logging']) {
			return;
		}

		global $wpdb;

		$table_name = $wpdb->prefix . 'smtp_max_logs';

		$wpdb->insert(
			$table_name,
			[
				'to_email' => 'Unknown',
				'subject' => 'Email Failed',
				'status' => 'failed',
				'error_message' => $wp_error->get_error_message(),
				'smtp_response' => $GLOBALS['smtp_max_debug'] ?? ''
			],
			['%s', '%s', '%s', '%s', '%s']
		);

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

		global $wpdb;
		$table_name = $wpdb->prefix . 'smtp_max_logs';
		$wpdb->query("TRUNCATE TABLE $table_name");

		wp_send_json_success(__('Email log cleared successfully!', 'smtp-max'));
	}

	/**
	 * Log successful email
	 */
	private function logEmail($to, $subject, $status, $error = '', $smtp_response = '')
	{
		if (!$this->options['enable_logging']) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'smtp_max_logs';

		$wpdb->insert(
			$table_name,
			[
				'to_email' => $to,
				'subject' => $subject,
				'status' => $status,
				'error_message' => $error,
				'smtp_response' => $smtp_response
			],
			['%s', '%s', '%s', '%s', '%s']
		);
	}

	/**
	 * Get email logs
	 */
	private function getEmailLogs($limit = 50)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'smtp_max_logs';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT %d",
				$limit
			)
		);
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
?>
		<div class="wrap">
			<h1><?php _e('SMTP Max Settings', 'smtp-max'); ?></h1>

			<div class="smtp-max-container">
				<div class="smtp-max-main">
					<form method="post" action="">
						<?php wp_nonce_field('smtp_max_save_settings'); ?>

						<div class="card">
							<h2><?php _e('SMTP Configuration', 'smtp-max'); ?></h2>

							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="smtp_host"><?php _e('SMTP Host', 'smtp-max'); ?></label>
									</th>
									<td>
										<input type="text" id="smtp_host" name="smtp_host"
											value="<?php echo esc_attr($this->options['smtp_host']); ?>"
											class="regular-text" placeholder="smtp.gmail.com" required />
										<p class="description">
											<?php _e('Your SMTP server hostname (e.g., smtp.gmail.com, smtp.office365.com)', 'smtp-max'); ?>
										</p>
									</td>
								</tr>

								<tr>
									<th scope="row">
										<label for="smtp_port"><?php _e('SMTP Port', 'smtp-max'); ?></label>
									</th>
									<td>
										<input type="number" id="smtp_port" name="smtp_port"
											value="<?php echo esc_attr($this->options['smtp_port']); ?>"
											class="small-text" min="1" max="65535" required />
										<p class="description">
											<?php _e('Common ports: 25 (non-encrypted), 465 (SSL), 587 (TLS)', 'smtp-max'); ?>
										</p>
									</td>
								</tr>

								<tr>
									<th scope="row">
										<label for="smtp_encryption"><?php _e('Encryption', 'smtp-max'); ?></label>
									</th>
									<td>
										<select id="smtp_encryption" name="smtp_encryption">
											<option value="none" <?php selected($this->options['smtp_encryption'], 'none'); ?>>
												<?php _e('None', 'smtp-max'); ?>
											</option>
											<option value="ssl" <?php selected($this->options['smtp_encryption'], 'ssl'); ?>>
												<?php _e('SSL/SMTPS', 'smtp-max'); ?>
											</option>
											<option value="tls" <?php selected($this->options['smtp_encryption'], 'tls'); ?>>
												<?php _e('TLS/STARTTLS', 'smtp-max'); ?>
											</option>
										</select>
										<p class="description">
											<?php _e('Select the encryption method supported by your SMTP server', 'smtp-max'); ?>
										</p>
									</td>
								</tr>

								<tr>
									<th scope="row">
										<?php _e('Authentication', 'smtp-max'); ?>
									</th>
									<td>
										<label>
											<input type="checkbox" id="smtp_auth" name="smtp_auth" value="1"
												<?php checked($this->options['smtp_auth']); ?> />
											<?php _e('Use SMTP Authentication', 'smtp-max'); ?>
										</label>
										<p class="description">
											<?php _e('Enable if your SMTP server requires authentication', 'smtp-max'); ?>
										</p>
									</td>
								</tr>

								<tr class="smtp-auth-row" <?php echo !$this->options['smtp_auth'] ? 'style="display:none;"' : ''; ?>>
									<th scope="row">
										<label for="smtp_username"><?php _e('Username', 'smtp-max'); ?></label>
									</th>
									<td>
										<input type="text" id="smtp_username" name="smtp_username"
											value="<?php echo esc_attr($this->options['smtp_username']); ?>"
											class="regular-text" autocomplete="username" />
										<p class="description">
											<?php _e('Your SMTP username (usually your email address)', 'smtp-max'); ?>
										</p>
									</td>
								</tr>

								<tr class="smtp-auth-row" <?php echo !$this->options['smtp_auth'] ? 'style="display:none;"' : ''; ?>>
									<th scope="row">
										<label for="smtp_password"><?php _e('Password', 'smtp-max'); ?></label>
									</th>
									<td>
										<input type="password" id="smtp_password" name="smtp_password"
											value="<?php echo esc_attr($this->options['smtp_password']); ?>"
											class="regular-text" autocomplete="current-password" />
										<p class="description">
											<?php _e('Your SMTP password or app-specific password', 'smtp-max'); ?>
										</p>
									</td>
								</tr>
							</table>
						</div>

						<div class="card">
							<h2><?php _e('From Address Settings', 'smtp-max'); ?></h2>

							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="from_email"><?php _e('From Email', 'smtp-max'); ?></label>
									</th>
									<td>
										<input type="email" id="from_email" name="from_email"
											value="<?php echo esc_attr($this->options['from_email']); ?>"
											class="regular-text" required />
										<p class="description">
											<?php _e('The email address that emails will be sent from', 'smtp-max'); ?>
										</p>
									</td>
								</tr>

								<tr>
									<th scope="row">
										<label for="from_name"><?php _e('From Name', 'smtp-max'); ?></label>
									</th>
									<td>
										<input type="text" id="from_name" name="from_name"
											value="<?php echo esc_attr($this->options['from_name']); ?>"
											class="regular-text" />
										<p class="description">
											<?php _e('The name that will appear as the sender', 'smtp-max'); ?>
										</p>
									</td>
								</tr>
							</table>
						</div>

						<div class="card">
							<h2><?php _e('Logging Settings', 'smtp-max'); ?></h2>

							<table class="form-table">
								<tr>
									<th scope="row">
										<?php _e('Enable Logging', 'smtp-max'); ?>
									</th>
									<td>
										<label>
											<input type="checkbox" id="enable_logging" name="enable_logging" value="1"
												<?php checked($this->options['enable_logging']); ?> />
											<?php _e('Log email delivery attempts', 'smtp-max'); ?>
										</label>
										<p class="description">
											<?php _e('Keep logs of email delivery attempts for troubleshooting', 'smtp-max'); ?>
										</p>
									</td>
								</tr>

								<tr>
									<th scope="row">
										<label for="log_retention_days"><?php _e('Log Retention', 'smtp-max'); ?></label>
									</th>
									<td>
										<input type="number" id="log_retention_days" name="log_retention_days"
											value="<?php echo esc_attr($this->options['log_retention_days']); ?>"
											class="small-text" min="1" max="365" />
										<span><?php _e('days', 'smtp-max'); ?></span>
										<p class="description">
											<?php _e('Number of days to keep email logs (1-365)', 'smtp-max'); ?>
										</p>
									</td>
								</tr>
							</table>
						</div>

						<?php submit_button(); ?>
					</form>
				</div>

				<div class="smtp-max-sidebar">
					<div class="card">
						<h2><?php _e('Test Email', 'smtp-max'); ?></h2>
						<p><?php _e('Send a test email to verify your SMTP configuration:', 'smtp-max'); ?></p>

						<div class="test-email-form">
							<input type="email" id="test_email_address" placeholder="<?php esc_attr_e('Enter email address', 'smtp-max'); ?>"
								value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" class="regular-text" />
							<button type="button" id="send_test_email" class="button button-secondary">
								<?php _e('Send Test Email', 'smtp-max'); ?>
							</button>
						</div>

						<div id="test_email_result" class="test-result"></div>
					</div>

					<div class="card">
						<h2><?php _e('Common SMTP Settings', 'smtp-max'); ?></h2>

						<div class="smtp-presets">
							<h4>Gmail</h4>
							<ul>
								<li><strong><?php _e('Host:', 'smtp-max'); ?></strong> smtp.gmail.com</li>
								<li><strong><?php _e('Port:', 'smtp-max'); ?></strong> 587</li>
								<li><strong><?php _e('Encryption:', 'smtp-max'); ?></strong> TLS</li>
							</ul>

							<h4>Outlook/Office365</h4>
							<ul>
								<li><strong><?php _e('Host:', 'smtp-max'); ?></strong> smtp-mail.outlook.com</li>
								<li><strong><?php _e('Port:', 'smtp-max'); ?></strong> 587</li>
								<li><strong><?php _e('Encryption:', 'smtp-max'); ?></strong> TLS</li>
							</ul>

							<h4>Yahoo</h4>
							<ul>
								<li><strong><?php _e('Host:', 'smtp-max'); ?></strong> smtp.mail.yahoo.com</li>
								<li><strong><?php _e('Port:', 'smtp-max'); ?></strong> 587 or 465</li>
								<li><strong><?php _e('Encryption:', 'smtp-max'); ?></strong> TLS or SSL</li>
							</ul>
						</div>
					</div>

					<div class="card">
						<h2><?php _e('Plugin Information', 'smtp-max'); ?></h2>
						<p><strong><?php _e('Version:', 'smtp-max'); ?></strong> <?php echo SMTP_MAX_VERSION; ?></p>
						<p><strong><?php _e('WordPress Version:', 'smtp-max'); ?></strong> <?php echo get_bloginfo('version'); ?></p>
						<p><strong><?php _e('PHP Version:', 'smtp-max'); ?></strong> <?php echo PHP_VERSION; ?></p>
					</div>
				</div>
			</div>

			<?php if ($this->options['enable_logging']): ?>
				<div class="card email-logs">
					<div class="email-logs-header">
						<h2><?php _e('Email Logs', 'smtp-max'); ?></h2>
						<button type="button" id="clear_email_log" class="button button-secondary">
							<?php _e('Clear Log', 'smtp-max'); ?>
						</button>
					</div>

					<?php if (!empty($logs)): ?>
						<div class="email-logs-table-wrapper">
							<table class="wp-list-table widefat fixed striped">
								<thead>
									<tr>
										<th><?php _e('Date/Time', 'smtp-max'); ?></th>
										<th><?php _e('To', 'smtp-max'); ?></th>
										<th><?php _e('Subject', 'smtp-max'); ?></th>
										<th><?php _e('Status', 'smtp-max'); ?></th>
										<th><?php _e('Details', 'smtp-max'); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($logs as $log): ?>
										<tr>
											<td><?php echo esc_html(mysql2date('Y-m-d H:i:s', $log->timestamp)); ?></td>
											<td><?php echo esc_html($log->to_email); ?></td>
											<td><?php echo esc_html(wp_trim_words($log->subject, 8)); ?></td>
											<td>
												<span class="status-<?php echo esc_attr($log->status); ?>">
													<?php echo $log->status === 'success'
														? __('Success', 'smtp-max')
														: __('Failed', 'smtp-max'); ?>
												</span>
											</td>
											<td>
												<?php if (!empty($log->error_message) || !empty($log->smtp_response)): ?>
													<button type="button" class="button button-small toggle-details"
														data-target="log-details-<?php echo $log->id; ?>">
														<?php _e('Show Details', 'smtp-max'); ?>
													</button>
													<div id="log-details-<?php echo $log->id; ?>" class="log-details" style="display:none;">
														<?php if (!empty($log->error_message)): ?>
															<p><strong><?php _e('Error:', 'smtp-max'); ?></strong></p>
															<pre><?php echo esc_html($log->error_message); ?></pre>
														<?php endif; ?>

														<?php if (!empty($log->smtp_response)): ?>
															<p><strong><?php _e('SMTP Response:', 'smtp-max'); ?></strong></p>
															<pre><?php echo esc_html($log->smtp_response); ?></pre>
														<?php endif; ?>
													</div>
												<?php else: ?>
													<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
												<?php endif; ?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php else: ?>
						<p><?php _e('No email logs found.', 'smtp-max'); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<style>
			.smtp-max-container {
				display: flex;
				gap: 20px;
				margin-top: 20px;
			}

			.smtp-max-main {
				flex: 2;
			}

			.smtp-max-sidebar {
				flex: 1;
				max-width: 300px;
			}

			.smtp-max-container .card {
				background: #fff;
				border: 1px solid #ccd0d4;
				border-radius: 4px;
				box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
				margin-bottom: 20px;
				padding: 20px;
			}

			.smtp-max-container .card h2 {
				margin-top: 0;
				margin-bottom: 15px;
				font-size: 18px;
				font-weight: 600;
				color: #23282d;
				border-bottom: 1px solid #eee;
				padding-bottom: 10px;
			}

			.test-email-form input[type="email"] {
				width: 100%;
				margin-bottom: 10px;
			}

			.test-email-form button {
				width: 100%;
			}

			.smtp-presets h4 {
				margin: 15px 0 8px 0;
				font-weight: 600;
				color: #0073aa;
			}

			.smtp-presets ul {
				margin: 0 0 15px 0;
				padding-left: 15px;
			}

			.smtp-presets li {
				margin-bottom: 3px;
				font-size: 13px;
			}

			.email-logs-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 15px;
			}

			.email-logs-header h2 {
				margin: 0;
				border: none;
				padding: 0;
			}

			.status-success {
				color: #46b450;
				font-weight: 600;
			}

			.status-failed {
				color: #dc3232;
				font-weight: 600;
			}

			.log-details {
				margin-top: 10px;
				padding: 10px;
				background: #f9f9f9;
				border: 1px solid #ddd;
				border-radius: 3px;
			}

			.log-details pre {
				background: #fff;
				border: 1px solid #ddd;
				padding: 8px;
				font-size: 11px;
				max-height: 200px;
				overflow-y: auto;
				white-space: pre-wrap;
				word-wrap: break-word;
			}

			.toggle-details {
				font-size: 12px;
				padding: 2px 8px;
				height: auto;
				line-height: 1.4;
			}

			.test-result {
				margin-top: 15px;
			}

			.test-result .notice {
				margin: 0;
				padding: 8px 12px;
			}

			@media (max-width: 782px) {
				.smtp-max-container {
					flex-direction: column;
				}

				.smtp-max-sidebar {
					max-width: none;
				}
			}
		</style>

		<script>
			jQuery(document).ready(function($) {
				// Toggle SMTP authentication fields
				$('#smtp_auth').on('change', function() {
					$('.smtp-auth-row').toggle($(this).is(':checked'));
				});

				// Test email functionality
				$('#send_test_email').on('click', function() {
					var $button = $(this);
					var $result = $('#test_email_result');
					var testEmail = $('#test_email_address').val();

					if (!testEmail) {
						$result.html('<div class="notice notice-error"><p>Please enter a valid email address.</p></div>');
						return;
					}

					$button.prop('disabled', true).text('Sending...');
					$result.empty();

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'smtp_max_test_email',
							test_email: testEmail,
							nonce: '<?php echo wp_create_nonce('smtp_max_nonce'); ?>'
						},
						success: function(response) {
							if (response.success) {
								$result.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
							} else {
								$result.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
							}
						},
						error: function() {
							$result.html('<div class="notice notice-error"><p>AJAX Error occurred</p></div>');
						},
						complete: function() {
							$button.prop('disabled', false).text('Send Test Email');
						}
					});
				});

				// Clear log functionality
				$('#clear_email_log').on('click', function() {
					if (!confirm('Are you sure you want to clear the email log?')) return;

					var $button = $(this);
					$button.prop('disabled', true);

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'smtp_max_clear_log',
							nonce: '<?php echo wp_create_nonce('smtp_max_nonce'); ?>'
						},
						success: function(response) {
							if (response.success) {
								location.reload();
							}
						},
						complete: function() {
							$button.prop('disabled', false);
						}
					});
				});

				// Toggle log details
				$('.toggle-details').on('click', function() {
					var $button = $(this);
					var targetId = $button.data('target');
					var $details = $('#' + targetId);

					if ($details.is(':visible')) {
						$details.slideUp();
						$button.text('Show Details');
					} else {
						$('.log-details:visible').slideUp();
						$('.toggle-details').text('Show Details');
						$details.slideDown();
						$button.text('Hide Details');
					}
				});
			});
		</script>
<?php
	}
}

// Initialize plugin
add_action('plugins_loaded', function () {
	$instance = SmtpMax::getInstance();
	// Fallback: Ensure log table exists
	global $wpdb;
	$table_name = $wpdb->prefix . 'smtp_max_logs';
	if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
		$instance_reflection = new ReflectionClass($instance);
		$createLogTable = $instance_reflection->getMethod('createLogTable');
		$createLogTable->setAccessible(true);
		$createLogTable->invoke($instance);
	}
});

// Activation hook - needs to be outside the class
register_activation_hook(__FILE__, function () {
	$instance = SmtpMax::getInstance();
	$instance->activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
	$instance = SmtpMax::getInstance();
	$instance->deactivate();
});

// Hook for successful emails (custom implementation)
add_action('wp_mail', function ($atts) {
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
});

// Schedule log cleanup
add_action('smtp_max_cleanup_logs', function () {
	$options = get_option('smtp_max_options', []);
	$retention_days = $options['log_retention_days'] ?? 30;

	global $wpdb;
	$table_name = $wpdb->prefix . 'smtp_max_logs';

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM $table_name WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
			$retention_days
		)
	);
});
