<?php

/**
 * ChangeLogs Routes.
 *
 * @since 1.0.0
 * @package  namespace SmartSMTP\Routes\ChangeLogs
 */

namespace SmartSMTP\Routes;

use SmartSMTP\Controller\ChangelogsController;
use SmartSMTP\Middleware\CommonAuth;

/**
 * ChangeLog class.
 *
 * @since 1.0.0
 */
class ChangeLogs extends AbstractRoutes {

	/**
	 * The base of this controller's route.
	 *
	 * @since 1.0.0
	 *
	 * @var string The base of this controller's route.
	 */
	protected $rest_base = 'changelogs';

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
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( new ChangelogsController(), 'get_item' ),
					'permission_callback' => array( new CommonAuth(), 'check_access_permissions' ),
				),
			)
		);
	}
}
