<?php
/**
 * TestMail.
 *
 * @since 1.0.0
 * @package  namespace SmartSMTP\Routes\TestMail
 */

namespace SmartSMTP\Routes;

use SmartSMTP\Controller\TestMailController;
use SmartSMTP\Middleware\CommonAuth;

/**
 * TestMail class.
 *
 * @since 1.0.0
 */
class TestMail extends AbstractRoutes {

	/**
	 * Route base.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $rest_base = 'test-mail';

	/**
	 * Register routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/save',
			array(
				'methods'             => 'POST',
				'callback'            => array( new TestMailController(), 'save_test_mail_config' ),
				'permission_callback' => array( new CommonAuth(), 'check_access_permissions' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/get',
			array(
				'methods'             => 'POST',
				'callback'            => array( new TestMailController(), 'get_test_mail_config' ),
				'permission_callback' => array( new CommonAuth(), 'check_access_permissions' ),
			)
		);
	}

}
