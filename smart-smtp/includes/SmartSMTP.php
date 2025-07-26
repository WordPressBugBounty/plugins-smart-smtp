<?php
/**
 * SmartSMTP class.
 *
 * @since 1.0.0
 * @package SmartSMTP\SmartSMTP
 */

namespace SmartSMTP;

defined( 'ABSPATH' ) || exit;

use SmartSMTP\Controller\Admin\AdminController;
use SmartSMTP\Helper;
use SmartSMTP\Controller\Admin\MenusController;
use SmartSMTP\Controller\Admin\ScriptStyleController;
use SmartSMTP\Services\Services as Services;
use SmartSMTP\RestApi\RestApi;
use SmartSMTP\Migration\MailLogsMigration;
use SmartSMTP\Routes\Routes;
use SmartSMTP\Traits\Singleton;

/**
 * SmartSMTP class
 *
 * @since 1.0.0
 */
final class SmartSMTP {

	use Singleton;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 20, 2 );
		if ( defined( 'SMART_SMTP_PLUGIN_BASENAME' ) ) {
			add_filter( 'plugin_action_links_' . SMART_SMTP_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
		}
		$this->includes();
	}

	/**
	 * Display action links in the Plugins list table.
	 *
	 * @since 1.0.0
	 * @param  array $actions Plugin Action links.
	 * @return array
	 */
	public function plugin_action_links( $actions ) {
		$new_actions = array(
			'settings' => '<a href="' . admin_url( 'admin.php?page=smart-smtp' ) . '" aria-label="' . esc_attr__( 'View Mail Configuration Settings', 'smart-smtp' ) . '">' . esc_html__( 'Settings', 'smart-smtp' ) . '</a>',
		);

		return array_merge( $new_actions, $actions );
	}

	/**
	 * Load Localization files.
	 *
	 * Note: the first-loaded translation file overrides any following ones if the same translation is present.
	 *
	 * @since 1.0.0
	 */
	public function load_plugin_textdomain() {
		$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
		$locale = apply_filters( 'plugin_locale', $locale, 'smart-smtp' );

		unload_textdomain( 'smart-smtp' );
		load_textdomain( 'smart-smtp', WP_LANG_DIR . '/smart-smtp/smart-smtp-' . $locale . '.mo' );
		load_plugin_textdomain( 'smart-smtp', false, plugin_basename( __DIR__ ) . '/languages' );
	}

	/**
	 * Display row meta in the Plugins list table.
	 *
	 * @since 1.0.0
	 *
	 * @param  array  $plugin_meta Plugin Row Meta.
	 * @param  string $plugin_file Plugin Base file.
	 * @return array
	 */
	public function plugin_row_meta( $plugin_meta, $plugin_file ) {
		if ( plugin_basename( SMART_SMTP_PLUGIN_FILE ) === $plugin_file ) {
			$new_plugin_meta = array(
				'docs' => '<a href="' . esc_url( '' ) . '" aria-label="' . esc_attr__( 'View User Registration Form Restriction documentation', 'smart-smtp' ) . '">' . esc_html__( 'Docs', 'smart-smtp' ) . '</a>',
			);

			return array_merge( $plugin_meta, $new_plugin_meta );
		}

		return (array) $plugin_meta;
	}

	/**
	 * Include necessary classes for plugin functionality.
	 *
	 * @since 1.0.0
	 */
	private function includes() {
		if ( current_user_can( 'manage_options' ) ) {
			AdminController::init();
			ScriptStyleController::init();
			MenusController::init();
		}
		Routes::init();
		MailLogsMigration::init();
		Services::init();
	}
}
