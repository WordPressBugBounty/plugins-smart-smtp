<?php
/**
 * TestMailController class.
 *
 * @since 1.0.0
 * @package  namespace SmartSMTP\Controller\TestMailController
 */

namespace SmartSMTP\Controller;

use SmartSMTP\Model\TestMail as TestMailModel;
use SmartSMTP\Services\Services;
use SmartSMTP\Helper;
use SmartSMTP\Model\Provider;
use SmartSMTP\Controller\ProviderController;

/**
 * TestMailController.
 *
 * @since 1.0.0
 */
class TestMailController {
	/**
	 * Test mail object.
	 *
	 * @since 1.0.0
	 */
	protected $test_mail;
	/**
	 * Construtor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->test_mail = new TestMailModel();
	}

	/**
	 * Function to save the data.
	 *
	 * @since 1.0.0
	 * @param object|array $request The form data.
	 */
	public function save_test_mail_config( $request ) {

		$test_config = isset( $request['mail_test_config'] ) ? $request['mail_test_config'] : array();

		if ( empty( $test_config ) ) {

			return new \WP_REST_Response(
				array(
					'message' => esc_html__( 'Send Test Mail Configuration data is empty.', 'smart-smtp' ),
				),
				400
			);
		}

		$sanitized_test_data = Helper::sanitize_input_fields( $test_config );

		$res = $this->test_mail->update_test_mail_config( $sanitized_test_data );
		try {
			$service       = Services::init();
			$test_response = $service->send_test_mail( $sanitized_test_data );
			$response      = Services::$response_message;
			if ( $test_response ) {
				return new \WP_REST_Response(
					array(
						'message' => isset( $test_response['message'] ) ? $test_response['message'] : esc_html__( 'Sent Successfully', 'smart-smtp' ),
					),
					isset( $test_response['code'] ) && ! empty( $test_response['code'] ) ? $test_response['code'] : 200
				);
			} else {
				return new \WP_REST_Response(
					array(
						'message' => isset( $response['message'] ) ? $response['message'] : esc_html__( 'Send Failed!!', 'smart-smtp' ),
					),
					isset( $response['code'] ) && ! empty( $response['code'] ) ? ( strlen(
						$response['code']
					) < 3 ? 400 : $response['code']
					) : 400
				);
			}
		} catch ( Exception $e ) {
			return new \WP_REST_Response(
				array(
					'message' => $e->getMessage(),
				),
				400
			);
		}
	}

	/**
	 * Function to get the config data.
	 *
	 * @since 1.0.0
	 */
	public function get_test_mail_config() {
		$test_mail_config = $this->test_mail->get_test_mail_config();

		$provider           = new ProviderController();
		$is_mailer_complete = array(
			'primary'  => array(
				'id'    => 'primary',
				'label' => __( 'Primary Connection', 'smart-smtp' ),
				'value' => $provider->is_mailer_complete( 'primary' ),
			),
			'fallback' => array(
				'id'    => 'fallback',
				'label' => __( 'Fallback Connection', 'smart-smtp' ),
				'value' => $provider->get_is_fallback_enabled() && $provider->is_mailer_complete( 'fallback' ),
			),
		);

		$res = array(
			'test_mail_config'   => $test_mail_config,
			'is_mailer_complete' => $is_mailer_complete,
		);

		return new \WP_REST_Response(
			$res,
			200
		);
	}
}
