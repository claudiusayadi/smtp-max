<?php

/**
 * Admin page template for SMTP Max
 * Path: templates/admin-page.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="wrap">
	<h1><?php _e('SMTP Max Settings', 'smtp-max'); ?></h1>

	<div class="smtp-maxcontainer">
		<div class="smtp-maxmain">
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

		<div class="smtp-maxsidebar">
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
				<p><strong><?php _e('Version:', 'smtp-max'); ?></strong> <?php echo smtp_max_VERSION; ?></p>
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