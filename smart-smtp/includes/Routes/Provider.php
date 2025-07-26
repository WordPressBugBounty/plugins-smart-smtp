<?php
/**
 * SmartSMTP Routes class for Provider.
 *
 * @package  namespace SmartSMTP\Routes
 *
 * @since 1.0.0
 */

namespace SmartSMTP\Routes;

use SmartSMTP\Controller\ProviderController;
use SmartSMTP\Middleware\CommonAuth;

/**
 * Test Mail class for Wpeverest stmp.
 *
 * @since 1.0.0
 */
class Provider extends AbstractRoutes {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'provider';


	/**
	 * Provider instance.
	 *
	 * @since 1.0.
	 * @var [object] The provider instance.
	 */
	protected $provider_inst;

	/**
	 * Register routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/get',
			array(
				'methods'             => 'POST',
				'callback'            => array( new ProviderController(), 'get_current_provider_type' ),
				'permission_callback' => array( new CommonAuth(), 'check_access_permissions' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/get-type',
			array(
				'methods'             => 'POST',
				'callback'            => array( new ProviderController(), 'get_provider_type' ),
				'permission_callback' => array( new CommonAuth(), 'check_access_permissions' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/save-settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( new ProviderController(), 'save_provider_config' ),
				'permission_callback' => array( new CommonAuth(), 'check_access_permissions' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/get-settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( new ProviderController(), 'get_provider_config' ),
				'permission_callback' => array( new CommonAuth(), 'check_access_permissions' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/get-provider-auth',
			array(
				'methods'             => 'POST',
				'callback'            => array( new ProviderController(), 'get_provider_auth' ),
				'permission_callback' => array( new CommonAuth(), 'check_access_permissions' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/remove-provider-auth',
			array(
				'methods'             => 'POST',
				'callback'            => array( new ProviderController(), 'remove_provider_auth' ),
				'permission_callback' => array( new CommonAuth(), 'check_access_permissions' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/save-is-fallback-enabled',
			array(
				'methods'             => 'POST',
				'callback'            => array( new ProviderController(), 'save_is_fallback_enabled' ),
				'permission_callback' => array( new CommonAuth(), 'check_access_permissions' ),
			)
		);
	}
}
