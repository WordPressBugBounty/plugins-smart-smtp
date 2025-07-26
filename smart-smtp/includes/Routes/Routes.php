<?php
/**
 * Routes class.
 *
 * @since 1.0.0
 * @package  namespace SmartSMTP\Routes
 */

namespace SmartSMTP\Routes;

use SmartSMTP\Traits\Singleton;
use SmartSMTP\Routes\Provider;
use SmartSMTP\Routes\MailLogs;
use SmartSMTP\Routes\ChangeLogs;
use SmartSMTP\Routes\TestMail;

/**
 * Routes class.
 *
 * @since 1.0.0
 */
class Routes {
	use Singleton;
	/**
	 * REST API classes and endpoints.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected $rest_classes = array();

	/**
	 * Hook into WordPress ready to init the REST API as needed.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 */
	public function register_rest_routes() {
		foreach ( $this->get_rest_classes() as $rest_namespace => $classes ) {

			foreach ( $classes as $class_name ) {
				if ( class_exists( $class_name ) ) {
					$object = new $class_name();
					$object->register_routes();
				}
			}
		}
	}

	/**
	 * Get API Classes - new classes should be registered here.
	 *
	 * @since 1.0.0
	 *
	 * @return array List of Classes.
	 */
	protected function get_rest_classes() {

		/**
		 * Filters rest API controller classes.
		 *
		 * @since 1.0.0
		 *
		 * @param array $rest_routes API namespace to API classes index array.
		 */
		return apply_filters(
			'smart_smtp_rest_api_get_rest_namespaces',
			array(
				'smart-smtp/v1' => $this->get_routes_classes(),
			)
		);
	}

	/**
	 * List of classes in the smart-smtp/v1 namespace.
	 *
	 * @since 1.0.0
	 * @static
	 *
	 * @return array
	 */
	protected function get_routes_classes() {
		return array(
			'provider'   => Provider::class,
			'changelogs' => ChangeLogs::class,
			'maillogs'   => MailLogs::class,
			'testmail'   => TestMail::class,
		);
	}
}
