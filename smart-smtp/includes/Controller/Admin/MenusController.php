<?php
/**
 * SmartSMTP MenusController class.
 *
 * @package  namespace SmartSMTP\Controller\Admin\MenuController
 *
 * @since 1.0.0
 */

namespace SmartSMTP\Controller\Admin;

use SmartSMTP\Helper;
use SmartSMTP\Traits\Singleton;

/**
 * MenusController class for Wpeverest stmp.
 *
 * @since 1.0.0
 */
class MenusController {

	use Singleton;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		add_action( 'admin_menu', array( $this, 'smtp_menu' ) );
		add_action( 'in_admin_header', array( $this, 'hide_admin_notices' ) );
	}

	/**
	 * Add menu items.
	 *
	 * @since 1.0.0
	 */
	public function smtp_menu() {
		$svg      = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="25" fill="none">
  <path fill="#82878C" d="M7.562 6.528 14.89 2.34l6.282 4.188-6.806 5.235-6.805-5.235Z"/>
  <path fill="#82878C" fill-rule="evenodd" d="m14.9 2.094 6.632 4.422-7.165 5.512-7.182-5.526 7.714-4.408Zm-6.961 4.46 6.428 4.945 6.447-4.959-5.932-3.954L7.94 6.554Z" clip-rule="evenodd"/>
  <path fill="#82878C" d="M12.273 12.81H2.85l5.235 3.141 4.188-3.14Z"/>
  <path fill="#82878C" fill-rule="evenodd" d="M2.094 12.601h10.808l-4.804 3.602-6.004-3.602Zm1.512.419 4.466 2.68 3.573-2.68H3.606Z" clip-rule="evenodd"/>
  <path fill="#82878C" d="m7.244 7.194 6.076 4.712H7.244V7.194Zm7.646 6.14-6.28 5.759-6.283-5.76v8.377h12.565v-8.376Z"/>
  <path fill="#82878C" fill-rule="evenodd" d="m7.034 6.767 6.898 5.348H7.034V6.767Zm.419.854v4.075h5.255L7.453 7.621Zm-5.336 5.237 6.492 5.95 6.491-5.95v9.061H2.117v-9.061Zm.419.952v7.69H14.68v-7.69l-6.07 5.567-6.074-5.567Z" clip-rule="evenodd"/>
  <path fill="#82878C" d="M21.697 16.998V7.053l-5.76 5.235v4.711h5.76Z"/>
  <path fill="#82878C" fill-rule="evenodd" d="M21.906 6.578v10.63h-6.178v-5.014l6.178-5.616Zm-5.759 5.801v4.41h5.34V7.525l-5.34 4.854Z" clip-rule="evenodd"/>
</svg>';
	  $base64_svg = 'data:image/svg+xml;base64,' . base64_encode($svg); // phpcs:ignore

		$page = add_menu_page( esc_html__( 'SmartSMTP', 'smart-smtp' ), esc_html__( 'SmartSMTP', 'smart-smtp' ), 'manage_options', 'smart-smtp', array( $this, 'smtp_page' ), $base64_svg );
		add_action( "admin_print_scripts-$page", array( $this, 'enqueue' ) );

	}

	/**
	 * Handles output of the reports page in admin.
	 */
	public function smtp_page() {
		wp_enqueue_style( 'emsmtp-main' );
		echo '<div id="smart-smtp"></div>';
	}

	/**
	 * Enqueue.
	 *
	 * @since 1.0.0
	 */
	public function enqueue() {
		wp_enqueue_script( 'emsmtp-main' );
	}

	/**
	 * Hide admin notices from SmartSMTP admin pages.
	 *
	 * @since 1.0.0
	 */
	public function hide_admin_notices() {

		// Bail if we're not on a SmartSMTP screen or page.
		if ( empty( $_REQUEST['page'] ) || false === strpos( sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ), 'smart-smtp' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		global $wp_filter;
		$ignore_notices = apply_filters( 'smart-smtp_ignore_hide_admin_notices', array() );

		foreach ( array( 'user_admin_notices', 'admin_notices', 'all_admin_notices' ) as $wp_notice ) {
			if ( empty( $wp_filter[ $wp_notice ] ) ) {
				continue;
			}

			$hook_callbacks = $wp_filter[ $wp_notice ]->callbacks;

			if ( empty( $hook_callbacks ) || ! is_array( $hook_callbacks ) ) {
				continue;
			}

			foreach ( $hook_callbacks as $priority => $hooks ) {
				foreach ( $hooks as $name => $callback ) {
					if ( ! empty( $name ) && in_array( $name, $ignore_notices, true ) ) {
						continue;
					}
					if (
						! empty( $callback['function'] ) &&
						! is_a( $callback['function'], '\Closure' ) &&
						isset( $callback['function'][0], $callback['function'][1] ) &&
						is_object( $callback['function'][0] ) &&
						in_array( $callback['function'][1], $ignore_notices, true )
					) {
						continue;
					}
					unset( $wp_filter[ $wp_notice ]->callbacks[ $priority ][ $name ] );
				}
			}
		}
	}
}
