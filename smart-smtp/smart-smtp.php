<?php
/**
 * Plugin Name: SmartSMTP
 * Plugin URI: https://wordpress.org/plugins/smart-smtp
 * Description: Effortlessly send reliable and secure emails through SMTP with the SmartSMTP plugin.
 * Version: 1.1.2
 * Author: ThemeGrill
 * Author URI: https://themegrill.com/
 * Text Domain: smart-smtp
 * Domain Path: /languages/
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 5.2
 * Requires PHP: 7.4
 *
 * @package WPEverest_SMTP
 */

defined( 'ABSPATH' ) || exit;

defined( 'WPINC' ) || exit;

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			sprintf(
			/* translators: 1: composer command. 2: plugin directory */
				esc_html__( 'Your installation of the SmartSMTP is incomplete. Please run %1$s within the %2$s directory.', 'smart-smtp' ),
				'`composer install`',
				'`' . esc_html( str_replace( ABSPATH, '', __DIR__ ) ) . '`'
			)
		);
	}

	/**
	 * Outputs an admin notice if composer install has not been ran.
	 */
	add_action(
		'admin_notices',
		function () {
			?>
			 <div class="notice notice-error">
				 <p>
					<?php
					printf(
						/* translators: 1: composer command. 2: plugin directory */
						esc_html__( 'Your installation of the  SmartSMTP plugin is incomplete. Please run %1$s within the %2$s directory.', 'smart-smtp' ),
						'<code>composer install</code>',
						'<code>' . esc_html( str_replace( ABSPATH, '', __DIR__ ) ) . '</code>'
					);
					?>
				 </p>
			 </div>
			 <?php
		}
	);
	return;
}

// Define SMART_SMTP_VERSION.
 ! defined( 'SMART_SMTP_VERSION' ) && define( 'SMART_SMTP_VERSION', '1.1.2' );

// Define SMART_SMTP_PLUGIN_FILE.
 ! defined( 'SMART_SMTP_PLUGIN_FILE' ) && define( 'SMART_SMTP_PLUGIN_FILE', __FILE__ );

// Define SMART_SMTP_PLUGIN_BASENAME.
 ! defined( 'SMART_SMTP_PLUGIN_BASENAME' ) && define( 'SMART_SMTP_PLUGIN_BASENAME', plugin_basename( SMART_SMTP_PLUGIN_FILE ) );

// Define SMART_SMTP_DIR.
 ! defined( 'SMART_SMTP_DIR' ) && define( 'SMART_SMTP_DIR', plugin_dir_path( __FILE__ ) );

// Define SMART_SMTP_DS.
 ! defined( 'SMART_SMTP_DS' ) && define( 'SMART_SMTP_DS', DIRECTORY_SEPARATOR );

// Define SMART_SMTP_URL.
 ! defined( 'SMART_SMTP_URL' ) && define( 'SMART_SMTP_URL', plugin_dir_url( __FILE__ ) );

// Define SMART_SMTP_ASSETS_URL.
 ! defined( 'SMART_SMTP_ASSETS_URL' ) && define( 'SMART_SMTP_ASSETS_URL', SMART_SMTP_URL . 'assets' );

/**
 * Initialization of SmartSMTP instance.
 *
 * @since 1.0.0
*/
add_action( 'plugins_loaded', array( 'SmartSMTP\\SmartSMTP', 'init' ) );

if ( ! function_exists( 'wp_mail' ) ) {
	/**
	 * Function to over ride the wp_mail function.
	 *
	 * @since 1.0.0
	 *
	 * @param  [type] $to The reciever email address.
	 * @param  [type] $subject The Subject of email.
	 * @param  [type] $message The message of email.
	 * @param  string $headers The Header of email.
	 * @param  array  $attachments The attachment of email.
	 */
	function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
		return SmartSMTP\Services\Services::smart_smtp_mail( $to, $subject, $message, $headers, $attachments );
	}
}
