/**
 * Admin JavaScript for SMTP Max
 * Path: assets/admin.js
 */

jQuery(document).ready(($) => {

    // Toggle SMTP authentication fields
    const toggleAuthFields = () => {
        const isChecked = $('#smtp_auth').is(':checked');
        $('.smtp-auth-row').toggle(isChecked);

        if (isChecked) {
            $('#smtp_username, #smtp_password').attr('required', true);
        } else {
            $('#smtp_username, #smtp_password').removeAttr('required');
        }
    };

    $('#smtp_auth').on('change', toggleAuthFields);
    toggleAuthFields(); // Initial state

    // Auto-update port based on encryption selection
    $('#smtp_encryption').on('change', function() {
        const encryption = $(this).val();
        const $port = $('#smtp_port');
        const currentPort = $port.val();

        // Only auto-update if the port is one of the common defaults
        if (['25', '465', '587'].includes(currentPort)) {
            switch (encryption) {
                case 'ssl':
                    $port.val('465');
                    break;
                case 'tls':
                    $port.val('587');
                    break;
                case 'none':
                    $port.val('25');
                    break;
            }
        }
    });

    // Test email functionality
    $('#send_test_email').on('click', function() {
        const $button = $(this);
        const $result = $('#test_email_result');
        const testEmail = $('#test_email_address').val().trim();

        if (!testEmail) {
            $result.html('<div class="notice notice-error"><p>' +
                        'Please enter a valid email address.' + '</p></div>');
            return;
        }

        if (!isValidEmail(testEmail)) {
            $result.html('<div class="notice notice-error"><p>' +
                        'Please enter a valid email address format.' + '</p></div>');
            return;
        }

        // Disable button and show loading
        $button.prop('disabled', true).text(smtpMaxAjax.strings.sending);
        $result.empty();

        $.ajax({
            url: smtpMaxAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'smtp_max_test_email',
                test_email: testEmail,
                nonce: smtpMaxAjax.nonce
            },
            success: (response) => {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' +
                                response.data + '</p></div>');
                } else {
                    $result.html('<div class="notice notice-error"><p>' +
                                response.data + '</p></div>');
                }
            },
            error: (xhr, status, error) => {
                $result.html('<div class="notice notice-error"><p>' +
                            'AJAX Error: ' + error + '</p></div>');
            },
            complete: () => {
                $button.prop('disabled', false).text('Send Test Email');
            }
        });
    });

    // Clear email log functionality
    $('#clear_email_log').on('click', function() {
        if (!confirm(smtpMaxAjax.strings.confirm_clear)) {
            return;
        }

        const $button = $(this);

        $button.prop('disabled', true);

        $.ajax({
            url: smtpMaxAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'smtp_max_clear_log',
                nonce: smtpMaxAjax.nonce
            },
            success: (response) => {
                if (response.success) {
                    $('.email-logs tbody').empty();
                    $('.email-logs tbody').html('<tr><td colspan="5">' +
                                              'No email logs found.' + '</td></tr>');

                    // Show success message
                    const $notice = $('<div class="notice notice-success is-dismissible"><p>' +
                                    response.data + '</p></div>');
                    $('.wrap h1').after($notice);

                    // Auto-dismiss after 3 seconds
                    setTimeout(() => {
                        $notice.fadeOut();
                    }, 3000);
                }
            },
            error: (xhr, status, error) => {
                alert('Error clearing log: ' + error);
            },
            complete: () => {
                $button.prop('disabled', false);
            }
        });
    });
        $('.smtp-auth-row').toggle(isChecked);

        if (isChecked) {
            $('#smtp_username, #smtp_password').attr('required', true);
        } else {
            $('#smtp_username, #smtp_password').removeAttr('required');
        }
    };

    $('#smtp_auth').on('change', toggleAuthFields);
    toggleAuthFields(); // Initial state

    // Auto-update port based on encryption selection
    $('#smtp_encryption').on('change', function() {
        const encryption = $(this).val();
        const $port = $('#smtp_port');
        const currentPort = $port.val();

        // Only auto-update if the port is one of the common defaults
        if (['25', '465', '587'].includes(currentPort)) {
            switch (encryption) {
                case 'ssl':
                    $port.val('465');
                    break;
                case 'tls':
                    $port.val('587');
                    break;
                case 'none':
                    $port.val('25');
                    break;
            }
        }
    });

    // Test email functionality
    $('#send_test_email').on('click', function() {
        const $button = $(this);
        const $result = $('#test_email_result');
        const testEmail = $('#test_email_address').val().trim();

        if (!testEmail) {
            $result.html('<div class="notice notice-error"><p>' +
                        'Please enter a valid email address.' + '</p></div>');
            return;
        }

        if (!isValidEmail(testEmail)) {
            $result.html('<div class="notice notice-error"><p>' +
                        'Please enter a valid email address format.' + '</p></div>');
            return;
        }

        // Disable button and show loading
        $button.prop('disabled', true).text(simpleSmtpAjax.strings.sending);
        $result.empty();

        $.ajax({
            url: simpleSmtpAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'simple_smtp_test_email',
                test_email: testEmail,
                nonce: simpleSmtpAjax.nonce
            },
            success: (response) => {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' +
                                response.data + '</p></div>');
                } else {
                    $result.html('<div class="notice notice-error"><p>' +
                                response.data + '</p></div>');
                }
            },
            error: (xhr, status, error) => {
                $result.html('<div class="notice notice-error"><p>' +
                            'AJAX Error: ' + error + '</p></div>');
            },
            complete: () => {
                $button.prop('disabled', false).text('Send Test Email');
            }
        });
    });

    // Clear email log functionality
    $('#clear_email_log').on('click', function() {
        if (!confirm(simpleSmtpAjax.strings.confirm_clear)) {
            return;
        }

        const $button = $(this);

        $button.prop('disabled', true);

        $.ajax({
            url: simpleSmtpAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'simple_smtp_clear_log',
                nonce: simpleSmtpAjax.nonce
            },
            success: (response) => {
                if (response.success) {
                    $('.email-logs tbody').empty();
                    $('.email-logs tbody').html('<tr><td colspan="5">' +
                                              'No email logs found.' + '</td></tr>');

                    // Show success message
                    const $notice = $('<div class="notice notice-success is-dismissible"><p>' +
                                    response.data + '</p></div>');
                    $('.wrap h1').after($notice);

                    // Auto-dismiss after 3 seconds
                    setTimeout(() => {
                        $notice.fadeOut();
                    }, 3000);
                }
            },
            error: (xhr, status, error) => {
                alert('Error clearing log: ' + error);
            },
            complete: () => {
                $button.prop('disabled', false);
            }
        });
    });

    // Toggle log details
    $(document).on('click', '.toggle-details', function() {
        const $button = $(this);
        const targetId = $button.data('target');
        const $details = $('#' + targetId);

        if ($details.is(':visible')) {
            $details.slideUp();
            $button.text('Show Details');
        } else {
            // Hide all other open details first
            $('.log-details:visible').slideUp();
            $('.toggle-details').text('Show Details');

            $details.slideDown();
            $button.text('Hide Details');
        }
    });

    // SMTP preset buttons functionality
    $('.smtp-preset').on('click', function() {
        const preset = $(this).data('preset');
        applySmtpPreset(preset);
    });

    // Form validation
    $('form').on('submit', function(e) {
        const $form = $(this);
        let hasErrors = false;

        // Clear previous error styling
        $('.form-invalid').removeClass('form-invalid');

        // Validate required fields
        $form.find('[required]').each(function() {
            const $field = $(this);
            if (!$field.val().trim()) {
                $field.addClass('form-invalid');
                hasErrors = true;
            }
        });

        // Validate email fields
        $form.find('input[type="email"]').each(function() {
            const $field = $(this);
            const email = $field.val().trim();
            if (email && !isValidEmail(email)) {
                $field.addClass('form-invalid');
                hasErrors = true;
            }
        });

        // Validate port range
        const port = parseInt($('#smtp_port').val());
        if (port < 1 || port > 65535) {
            $('#smtp_port').addClass('form-invalid');
            hasErrors = true;
        }

        if (hasErrors) {
            e.preventDefault();
            alert('Please fix the highlighted fields before saving.');
            return false;
        }
    });

    // Password visibility toggle
    const $passwordField = $('#smtp_password');
    if ($passwordField.length) {
        const $toggleButton = $('<button type="button" class="button button-secondary password-toggle">' +
                               '<span class="dashicons dashicons-visibility"></span></button>');

        $passwordField.after($toggleButton);

        $toggleButton.on('click', function() {
            const $button = $(this);
            const $icon = $button.find('.dashicons');

            if ($passwordField.attr('type') === 'password') {
                $passwordField.attr('type', 'text');
                $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                $passwordField.attr('type', 'password');
                $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        });
    }

    // Auto-save draft functionality (optional)
    let saveTimeout;
    $('input, select, textarea').on('change input', function() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(saveDraft, 2000);
    });

    // Helper functions
    const isValidEmail = (email) => {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    };

    const applySmtpPreset = (preset) => {
        const presets = {
            gmail: {
                host: 'smtp.gmail.com',
                port: '587',
                encryption: 'tls'
            },
            outlook: {
                host: 'smtp-mail.outlook.com',
                port: '587',
                encryption: 'tls'
            },
            yahoo: {
                host: 'smtp.mail.yahoo.com',
                port: '587',
                encryption: 'tls'
            }
        };

        if (presets[preset]) {
            const config = presets[preset];
            $('#smtp_host').val(config.host);
            $('#smtp_port').val(config.port);
            $('#smtp_encryption').val(config.encryption);
            $('#smtp_auth').prop('checked', true);
            toggleAuthFields();
        }
    };

    const saveDraft = () => {
        // Optional: Implement auto-save draft functionality
        // This could save settings temporarily without full validation
        console.log('Auto-saving draft...');
    };

    // Connection test with timeout
    const testConnection = (host, port, timeout = 5000) => {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const timer = setTimeout(() => {
                reject(new Error('Connection timeout'));
            }, timeout);

            img.onload = img.onerror = () => {
                clearTimeout(timer);
                resolve();
            };

            img.src = `http://${host}:${port}/favicon.ico?${Date.now()}`;
        });
    };

    // Add connection test button (optional feature)
    if ($('#smtp_host').length) {
        const $testConnButton = $('<button type="button" class="button button-secondary test-connection">' +
                                  'Test Connection</button>');
        $('#smtp_port').after($testConnButton);

        $testConnButton.on('click', function() {
            const host = $('#smtp_host').val().trim();
            const port = $('#smtp_port').val().trim();

            if (!host || !port) {
                alert('Please enter both host and port before testing connection.');
                return;
            }

            const $button = $(this);
            $button.prop('disabled', true).text('Testing...');

            testConnection(host, port)
                .then(() => {
                    alert('Connection test completed. Note: This only tests if the server is reachable, not SMTP functionality.');
                })
                .catch((error) => {
                    alert('Connection test failed: ' + error.message);
                })
                .finally(() => {
                    $button.prop('disabled', false).text('Test Connection');
                });
        });
    }

    // Keyboard shortcuts
    $(document).on('keydown', (e) => {
        // Ctrl+S to save
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            $('form').submit();
        }

        // Ctrl+T to send test email
        if (e.ctrlKey && e.key === 't') {
            e.preventDefault();
            $('#send_test_email').click();
        }
    });

    // Responsive table handling
    const makeTablesResponsive = () => {
        $('.email-logs-table-wrapper table').each(function() {
            const $table = $(this);
            if (!$table.parent().hasClass('table-responsive')) {
                $table.wrap('<div class="table-responsive"></div>');
            }
        });
    };

    makeTablesResponsive();

    // Initialize tooltips (if needed)
    if (typeof $().tooltip === 'function') {
        $('[title]').tooltip();
    }

    // Status indicator for form changes
    let formChanged = false;
    $('input, select, textarea').on('change', () => {
        if (!formChanged) {
            formChanged = true;
            $(window).on('beforeunload', () => {
                return 'You have unsaved changes. Are you sure you want to leave?';
            });
        }
    });

    $('form').on('submit', () => {
        formChanged = false;
        $(window).off('beforeunload');
    });
});

// Additional utility functions available globally
window.SimpleSmtpAdmin = {
    validateForm: () => {
        // Global form validation function
        return true;
    },

    resetForm: () => {
        // Reset form to defaults
        if (confirm('Reset all settings to default values?')) {
            location.reload();
        }
    },

    exportSettings: () => {
        // Export settings as JSON (excluding sensitive data)
        const settings = {
            smtp_host: $('#smtp_host').val(),
            smtp_port: $('#smtp_port').val(),
            smtp_encryption: $('#smtp_encryption').val(),
            smtp_auth: $('#smtp_auth').is(':checked'),
            from_email: $('#from_email').val(),
            from_name: $('#from_name').val(),
            enable_logging: $('#enable_logging').is(':checked'),
            log_retention_days: $('#log_retention_days').val()
        };

        const dataStr = JSON.stringify(settings, null, 2);
        const dataBlob = new Blob([dataStr], {type: 'application/json'});

        const link = document.createElement('a');
        link.href = URL.createObjectURL(dataBlob);
        link.download = 'smtp-settings.json';
        link.click();
    }
};