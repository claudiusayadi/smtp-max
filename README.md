# SMTP Max - WordPress Plugin

A lightweight, secure WordPress plugin that replaces the default `wp_mail()` functionality with SMTP authentication, supporting SSL/TLS encryption, comprehensive error logging, and an intuitive admin interface.

## Features

- ✅ **SMTP Authentication**: Secure email delivery using your SMTP server
- ✅ **SSL/TLS Support**: Encrypted connections for maximum security
- ✅ **Error Logging**: Comprehensive logging with troubleshooting details
- ✅ **Test Email Functionality**: Send test emails to verify configuration
- ✅ **User-Friendly Interface**: Easy-to-use admin dashboard
- ✅ **Multiple SMTP Providers**: Pre-configured settings for Gmail, Outlook, Yahoo
- ✅ **Performance Optimized**: Lightweight code with minimal impact
- ✅ **Translation Ready**: Full internationalization support
- ✅ **Security Focused**: Credential protection and secure storage
- ✅ **WordPress Standards**: Follows all WordPress coding best practices

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Valid SMTP server credentials

## Installation

### Manual Installation

1. Download the plugin files
2. Create a folder named `smtp-max` in your `/wp-content/plugins/` directory
3. Upload all plugin files to this folder
4. Ensure the following file structure:

```
/wp-content/plugins/smtp-max/
├── smtp-max.php (main plugin file)
├── templates/
│   └── admin-page.php
├── assets/
│   ├── admin.js
│   └── admin.css
├── languages/
└── README.md
```

5. Go to WordPress Admin → Plugins
6. Find "SMTP Max" and click "Activate"

### Via WordPress Admin

1. Go to Plugins → Add New
2. Click "Upload Plugin"
3. Choose the plugin zip file
4. Click "Install Now" and then "Activate"

## Configuration

### Basic Setup

1. Navigate to **Settings → SMTP Max** in your WordPress admin
2. Fill in your SMTP server details:

   - **SMTP Host**: Your SMTP server address (e.g., smtp.gmail.com)
   - **SMTP Port**: Usually 587 for TLS or 465 for SSL
   - **Encryption**: Choose TLS, SSL, or None
   - **Authentication**: Enable if your server requires login
   - **Username**: Your SMTP username (usually your email)
   - **Password**: Your SMTP password or app-specific password

3. Configure the "From" address:

   - **From Email**: The email address emails will be sent from
   - **From Name**: The name that appears as the sender

4. Click **Save Changes**

### Common SMTP Settings

#### Gmail

```
Host: smtp.gmail.com
Port: 587
Encryption: TLS
Authentication: Yes
Username: your-email@gmail.com
Password: your-app-password (not your regular password)
```

**Note**: For Gmail, you need to use an App Password, not your regular Google password. Generate one at: https://myaccount.google.com/apppasswords

#### Outlook/Office 365

```
Host: smtp-mail.outlook.com
Port: 587
Encryption: TLS
Authentication: Yes
Username: your-email@outlook.com
Password: your-password
```

#### Yahoo Mail

```
Host: smtp.mail.yahoo.com
Port: 587 or 465
Encryption: TLS or SSL
Authentication: Yes
Username: your-email@yahoo.com
Password: your-app-password
```

#### Custom SMTP Server

Contact your hosting provider or email service for specific settings.

## Testing Your Configuration

1. After saving your settings, scroll to the **Test Email** section
2. Enter a test email address (your own email recommended)
3. Click **Send Test Email**
4. Check your inbox and the plugin's email log for results

## Email Logging

The plugin maintains detailed logs of all email attempts:

- **Enable Logging**: Toggle in the Logging Settings section
- **Log Retention**: Set how many days to keep logs (1-365 days)
- **View Logs**: Scroll to the bottom of the settings page
- **Clear Logs**: Use the "Clear Log" button to remove all log entries

### Log Information Includes:

- Timestamp of email attempt
- Recipient email address
- Email subject
- Success/failure status
- Error messages (if any)
- SMTP server responses

## Troubleshooting

### Common Issues

#### "SMTP Authentication Failed"

- Verify your username and password are correct
- For Gmail, ensure you're using an App Password
- Check if two-factor authentication is enabled on your email account

#### "Connection Timeout"

- Verify the SMTP host and port are correct
- Check if your hosting provider blocks outgoing SMTP connections
- Try a different port (587, 465, or 25)

#### "SSL Certificate Verify Failed"

- Try changing encryption from SSL to TLS or vice versa
- Contact your hosting provider about SSL certificate issues

#### Emails Not Being Delivered

- Check the email logs for error messages
- Verify the "From" email address is valid
- Ensure your SMTP server allows sending from your domain
- Check recipient spam folders

### Getting Help

1. **Check Email Logs**: Most issues show detailed error messages in the logs
2. **Test Email Feature**: Use this to isolate configuration problems
3. **Review SMTP Settings**: Double-check all credentials and server details
4. **Contact Support**: Reach out with specific error messages from the logs

## Security Considerations

- **Password Storage**: Passwords are stored securely in the WordPress database
- **Access Control**: Only administrators can access SMTP settings
- **App Passwords**: Use app-specific passwords when available (Gmail, Yahoo)
- **Two-Factor Authentication**: Keep 2FA enabled on your email accounts
- **Regular Updates**: Keep the plugin updated for security patches

## Performance Notes

- **Lightweight Design**: Minimal impact on site performance
- **Efficient Logging**: Database queries are optimized
- **Memory Usage**: Low memory footprint
- **Load Time**: No front-end scripts or styles loaded

## Compatibility

### WordPress

- WordPress 5.0+ (tested up to 6.4)
- Multisite compatible
- Works with all standard WordPress themes

### PHP

- PHP 7.4+ required
- PHP 8.0+ recommended
- Uses PHPMailer (included with WordPress)

### Popular Plugin Compatibility

- Contact Form 7
- WooCommerce
- Gravity Forms
- WP Mail Bank
- Easy WP SMTP

## Development

### Hooks and Filters

The plugin provides several hooks for developers:

#### Actions

```php
// Before SMTP configuration is applied
do_action('smtp_max_before_configure', $phpmailer);

// After successful email send
do_action('smtp_max_email_sent', $to, $subject, $message);

// Before logging email attempt
do_action('smtp_max_before_log', $log_data);
```

#### Filters

```php
// Modify SMTP settings
$settings = apply_filters('smtp_max_settings', $settings);

// Customize log retention
$days = apply_filters('smtp_max_log_retention_days', $days);

// Modify test email content
$content = apply_filters('smtp_max_test_email_content', $content);
```

### Custom Integration Example

```php
// Override SMTP settings programmatically
add_filter('smtp_max_settings', function($settings) {
    if (defined('CUSTOM_SMTP_HOST')) {
        $settings['smtp_host'] = CUSTOM_SMTP_HOST;
        $settings['smtp_port'] = CUSTOM_SMTP_PORT;
        $settings['smtp_username'] = CUSTOM_SMTP_USER;
        $settings['smtp_password'] = CUSTOM_SMTP_PASS;
    }
    return $settings;
});
```

## File Structure

```
smtp-max/
├── smtp-max.php     # Main plugin file
├── templates/
│   └── admin-page.php         # Admin interface template
├── assets/
│   ├── admin.js              # JavaScript for admin interface
│   └── admin.css             # Styling for admin interface
├── languages/                # Translation files directory
│   └── smtp-max.pot
└── README.md                 # This documentation
```

## Translation

The plugin is fully translation-ready. To translate:

1. Use the `.pot` file in the `languages` directory
2. Create translation files: `smtp-max-{locale}.po` and `.mo`
3. Place files in `/wp-content/languages/plugins/`

## Changelog

### Version 1.0.0

- Initial release
- SMTP authentication support
- SSL/TLS encryption
- Email logging functionality
- Test email feature
- Admin interface
- WordPress 6.4 compatibility

## Support

For support, documentation, and updates:

- **GitHub**: [Plugin Repository](https://github.com/yourname/smtp-max)
- **WordPress.org**: [Plugin Page](https://wordpress.org/plugins/smtp-max)
- **Documentation**: [Online Docs](https://example.com/docs)

## License

This plugin is licensed under the GPL v2 or later.

```
SMTP Max WordPress Plugin
Copyright (C) 2024 Your Name

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## Contributing

We welcome contributions! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

### Coding Standards

- Follow WordPress coding standards
- Use arrow functions where applicable (as per your preferences)
- Maintain TypeScript-style strict typing in comments
- Document all functions and classes

---

**Made with ❤️ for the WordPress community**
