<?php

/**
 * CommonAuth Middleware.
 *
 * @package  namespace SmartSMTP\Middleware\CommonAuth
 *
 * @since 1.0.0
 */

namespace SmartSMTP\Middleware;

/**
 * CommonAuth Middleware class.
 *
 * @since 1.0.0
 */
class CommonAuth {
	/**
	 * Check if a given request has access to update a setting.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function check_access_permissions( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		// Nonce check.
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				esc_html__( 'You do not have permissions to perform this action.', 'smart-smtp' ),
				array( 'status' => 403 )
			);
		}
		// Capability check.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				esc_html__( 'You are not allowed to access this resource.', 'smart-smtp' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}
}
