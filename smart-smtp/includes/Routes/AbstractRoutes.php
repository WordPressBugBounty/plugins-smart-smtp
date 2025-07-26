<?php

/**
 * Abstract Routes.
 *
 * @since 1.0.0
 * @package SmartSMTP\Routes
 */

namespace SmartSMTP\Routes;

/**
 * Abstract class for defining routes.
 *
 * @since 1.0.0
 */
abstract class AbstractRoutes {

    /**
     * The namespace of this controller's route.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $namespace = 'smart-smtp';

    /**
     * The base of this controller's route.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $rest_base;

    /**
     * Register routes.
     *
     * @since 1.0.0
     *
     * @return void
     */
    abstract public function register_routes();
}
