<?php

/**
 * Uninstall script for SMTP Max
 * This file runs when the plugin is deleted (not just deactivated)
 * Path: uninstall.php
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

// Remove plugin options
delete_option('smtp_max_options');

// Remove any transients
delete_transient('smtp_max_test_result');

// Remove scheduled events
wp_clear_scheduled_hook('smtp_max_cleanup_logs');

// Remove custom database table
global $wpdb;
$table_name = $wpdb->prefix . 'smtp_max_logs';

// Drop the custom table
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Remove any custom user meta (if we had any)
// delete_metadata('user', 0, 'smtp_max_user_preference', '', true);

// Clean up any cached data
wp_cache_flush();

// Optional: Remove any uploaded files or directories created by the plugin
// (This plugin doesn't create any, but here's how you would do it)
// $upload_dir = wp_upload_dir();
// $plugin_upload_dir = $upload_dir['basedir'] . '/smtp-max/';
// if (is_dir($plugin_upload_dir)) {
//     // Recursively delete directory and contents
//     smtp_max_delete_directory($plugin_upload_dir);
// }

/**
 * Recursively delete a directory and its contents
 *
 * @param string $dir Directory path to delete
 * @return bool Success status
 */
function smtp_max_delete_directory($dir)
{
	if (!file_exists($dir)) {
		return true;
	}

	if (!is_dir($dir)) {
		return unlink($dir);
	}

	foreach (scandir($dir) as $item) {
		if ($item == '.' || $item == '..') {
			continue;
		}

		if (!smtp_max_delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
			return false;
		}
	}

	return rmdir($dir);
}

// Log the uninstallation (optional, for debugging)
if (defined('WP_DEBUG') && WP_DEBUG) {
	error_log('SMTP Max: Plugin uninstalled and cleaned up successfully.');
}
