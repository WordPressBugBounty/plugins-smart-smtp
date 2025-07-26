<?php
/**
 * Provider Model class.
 *
 * @since 1.0.0
 * @package  namespace SmartSMTP\Model\Provider
 */

namespace SmartSMTP\Model;

/**
 *  Provider class for Wpeverest stmp.
 *
 * @since 1.0.0
 */
class Provider {

	/**
	 * Email logs table name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $table_name;
	/**
	 * The name of the database connection to use.
	 *
	 * @var wpdb
	 */
	protected $con;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;

		$this->con = $wpdb;
	}

	/**
	 * Get the config data type.
	 *
	 * @since 1.0.0
	 */
	public function get_current_provider_type() {
		return get_option( 'smart_smtp_provider_type', 'default' );
	}

	/**
	 * Set the mail provider type.
	 *
	 * @since 1.0.0
	 * @param mixed $data The provider data.
	 */
	public function set_provider_type( $data ) {
		return update_option( 'smart_smtp_provider_type', $data );
	}

	/**
	 * Get the provider type.
	 *
	 * @since 1.0.0
	 * @param string $conn The connection type.
	 */
	public function get_provider_type( $conn ) {
		if ( 'fallback' === $conn ) {
			return get_option( 'smart_smtp_fallback_provider_type', '' );
		}

		$provider_type = get_option( 'smart_smtp_provider_type', '' );

		return $provider_type;
	}

	/**
	 * Get the config params.
	 *
	 * @since 1.0.0
	 * @param string $option_name The data provider option name.
	 */
	public function get_provider_config( $option_name ) {

		return get_option( $option_name, array( 'providerType' => 'default' ) );
	}

	/**
	 * Update the mail config params.
	 *
	 * @since 1.0.0
	 * @param string $option_name The option name.
	 * @param mixed  $params The mail config params.
	 */
	public function update_provider_config( $option_name, $data ) {

		return update_option( $option_name, $data );
	}

	/**
	 * Set the active provider type.
	 *
	 * @since 0
	 *
	 * @param  [type] $option_name The option name.
	 * @param  [type] $prov_type The provider type.
	 */
	public function set_active_provider( $option_name, $prov_type ) {

		return update_option( $option_name, $prov_type );
	}
	/**
	 * Get fallback enabled
	 *
	 * @return void
	 */
	public function get_is_fallback_enabled() {
		return get_option( 'smart_smtp_is_fallback_enabled', false );
	}

	/**
	 * Save enabled fallback.
	 *
	 * @since 0
	 *
	 * @param  [boolean] $is_enabled The fallback is enabled or not.
	 */
	public function save_is_fallback_enabled( $is_enabled ) {

		return update_option( 'smart_smtp_is_fallback_enabled', $is_enabled );
	}
}
