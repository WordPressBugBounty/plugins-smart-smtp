<?php
/**
 * MailLogs Routes.
 *
 * @since 1.0.0
 * @package  namespace SmartSMTP\Routes\MailLogs
 */

namespace SmartSMTP\Routes;

use SmartSMTP\Controller\MailLogsController;
use SmartSMTP\Middleware\CommonAuth;

/**
 * Mail logs class.
 *
 * @since 1.0.0
 */
class MailLogs extends AbstractRoutes {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'maillogs';

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
			'/' . $this->rest_base . '/get',
			array(
				'methods'             => 'POST',
				'callback'            => array( new MailLogsController(), 'get_mail_logs' ),
				'permission_callback' => array( new CommonAuth(), 'check_access_permissions' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/get-mail-log-count',
			array(
				'methods'             => 'POST',
				'callback'            => array( new MailLogsController(), 'get_mail_logs_count' ),
				'permission_callback' => array( new CommonAuth(), 'check_access_permissions' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/bulk-action',
			array(
				'methods'             => 'POST',
				'callback'            => array( new MailLogsController(), 'bulk_action' ),
				'permission_callback' => array( new CommonAuth(), 'check_access_permissions' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/get-log-content',
			array(
				'methods'             => 'POST',
				'callback'            => array( new MailLogsController(), 'get_log_content' ),
				'permission_callback' => array( new CommonAuth(), 'check_access_permissions' ),
			)
		);
	}

}
