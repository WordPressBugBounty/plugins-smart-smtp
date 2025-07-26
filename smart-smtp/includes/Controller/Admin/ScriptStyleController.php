<?php
/**
 * ScriptStyleController class.
 *
 * @package  namespace SmartSMTP\Controller\Admin\ScriptStyleController
 *
 * @since 1.0.0
 */

namespace SmartSMTP\Controller\Admin;

use SmartSMTP\Helper;
use SmartSMTP\Traits\Singleton;

/**
 * ScriptStyleController class.
 *
 * @since 1.0.0
 */
class ScriptStyleController {

	use Singleton;

	/**
	 * Scripts.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private $scripts = array();

	/**
	 * Styles.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private $styles = array();

	/**
	 * Localized scripts.
	 *
	 * @var array
	 */
	private $localized_scripts = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'after_wp_init' ) );
		add_action( 'init', array( $this, 'register_scripts_styles' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'localize_admin_scripts' ) );
	}

	/**
	 * Get asset url.
	 *
	 * @since 1.0.0
	 * @param string  $filename Asset filename.
	 * @param boolean $dev Has dev url.
	 * @return string
	 */
	public static function get_asset_url( $filename, $dev = true ) {
		$path = plugins_url( 'dist/', SMART_SMTP_PLUGIN_FILE );

		if ( $dev && Helper::is_development() ) {
			$path = 'http://localhost:3000/dist/';
		}

		return apply_filters( 'smart_smtp_asset_url', $path . $filename );
	}

	/**
	 * After WP init.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function after_wp_init() {

		$this->scripts = array(
			'main' => array(
				'src'     => self::get_asset_url( 'main.js' ),
				'deps'    => array( 'wp-element', 'react', 'react-dom', 'wp-api-fetch', 'wp-i18n', 'wp-blocks' ),
				'version' => SMART_SMTP_VERSION,
			),
		);

		$this->styles = array(
			'main' => array(
				'src'     => self::get_asset_url( 'main.css' ),
				'version' => SMART_SMTP_VERSION,
				'deps'    => array(),
			),
		);

		$this->scripts = apply_filters( 'smart_smtp_scripts', $this->scripts );
		$this->styles  = apply_filters( 'smart_smtp_styles', $this->styles );
	}

	/**
	 * Register scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_scripts() {
		foreach ( $this->scripts as $handle => $script ) {
			wp_register_script( "emsmtp-$handle", $script['src'], $script['deps'], $script['version'], true );
		}
	}

	/**
	 * Register styles.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_styles() {
		foreach ( $this->styles as $handle => $style ) {
			wp_register_style( "emsmtp-$handle", $style['src'], $style['deps'], $style['version'] );
		}
	}

	/**
	 * Register scripts and styles for plugin.
	 *
	 * @since 1.0.0
	 */
	public function register_scripts_styles() {
		$this->register_scripts();
		$this->register_styles();
	}

	/**
	 * Localize block scripts.
	 *
	 * @return void
	 */
	public function localize_admin_scripts() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'wp_get_themes' ) ) {
			require_once ABSPATH . 'wp-admin/includes/theme.php';
		}
		$installed_plugin_slugs = array_keys( get_plugins() );
		$installed_theme_slugs  = array_keys( wp_get_themes() );

		$current_theme = get_stylesheet();

		$allowed_plugin_slugs = array(
			'everest-forms/everest-forms.php',
			'user-registration/user-registration.php',
			'blockart-blocks/blockart.php',
			'learning-management-system/lms.php',
			'magazine-blocks/magazine-blocks.php',
		);
		wp_localize_script(
			'emsmtp-main',
			'smart_smtp_script_data',
			array(
				'adminURL'           => esc_url( admin_url() ),
				'siteURL'            => esc_url( home_url( '/' ) ),
				'emsmtpRestApiNonce' => wp_create_nonce( 'wp_rest' ),
				'rootApiUrl'         => esc_url_raw( rest_url() ),
				'settingsURL'        => esc_url( admin_url( '/admin.php?page=evf-settings' ) ),
				'liveDemoURL'        => esc_url_raw( 'https://everestforms.demoswp.net/' ),
				'restURL'            => rest_url(),
				'version'            => defined( 'SMART_SMTP_VERSION' ) ? SMART_SMTP_VERSION : '',
				'plugins'            => array_reduce(
					$allowed_plugin_slugs,
					function ( $acc, $curr ) use ( $installed_plugin_slugs ) {
						if ( in_array( $curr, $installed_plugin_slugs, true ) ) {

							if ( is_plugin_active( $curr ) ) {
								$acc[ $curr ] = 'active';
							} else {
								$acc[ $curr ] = 'inactive';
							}
						} else {
							$acc[ $curr ] = 'not-installed';
						}
						return $acc;
					},
					array()
				),
				'themes'             => array(
					'zakra'    => strpos( $current_theme, 'zakra' ) !== false ? 'active' : (
						in_array( 'zakra', $installed_theme_slugs, true ) ? 'inactive' : 'not-installed'
					),
					'colormag' => strpos( $current_theme, 'colormag' ) !== false || strpos( $current_theme, 'colormag-pro' ) !== false ? 'active' : (
						in_array( 'colormag', $installed_theme_slugs, true ) || in_array( 'colormag-pro', $installed_theme_slugs, true ) ? 'inactive' : 'not-installed'
					),
				),
			)
		);
	}
}
