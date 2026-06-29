<?php
/**
 * SmartSMTP Services class.
 *
 * @package  namespace SmartSMTP\Services\BaseMailer
 *
 * @since 1.0.0
 */

namespace SmartSMTP\Services;

use SmartSMTP\SmartSMTP;
use SmartSMTP\Model\Provider;

/**
 * Base Mailer.
 *
 * @since 1.0.0
 */
class BaseMailer {

	/**
	 * Easymail smtp mailer.
	 *
	 * @since 0
	 * @var [type] $php_mailer base php mailer.
	 */
	protected $php_mailer = null;
	/**
	 * BaseMailer constructor.
	 *
	 * @since 0
	 *
	 * @param  [type] $php_mailer The default mailer.
	 */
	public function __construct( $php_mailer ) {
		$this->php_mailer = $php_mailer;
	}
	/**
	 * Send Mail.
	 *
	 * This method sends an email using the specified provider type.
	 *
	 * @since 0.0.1
	 *
	 * @param array $mail_data An array containing email details such as 'to', 'subject', 'message', 'headers', and 'attachments'.
	 *                         This array is passed by reference and can be modified within the method.
	 *
	 * @return mixed The response from the mail provider's send method. It returns false if the provider type is not recognized.
	 */
	public function send( &$mail_data ) {
		$config_inst = new Provider();

		// Initial send mail from the primary connection.
		$provider_type = $config_inst->get_provider_type( 'primary' );
		$res           = false;
		$exception     = null;

		try {
			$res = $this->send_from( $mail_data, 'primary', $provider_type );
		} catch ( \Exception $e ) {
			$exception = $e;
		}

		// Primary failed if it threw, returned a WP_Error (e.g. Gmail), or returned false.
		$primary_failed = ( null !== $exception ) || is_wp_error( $res ) || false === $res;

		if ( ! $primary_failed ) {
			return $res;
		}

		// No fallback available — preserve original behaviour (rethrow on exception).
		$fallback_type = $config_inst->get_provider_type( 'fallback' );
		if ( ! $config_inst->get_is_fallback_enabled() || empty( $fallback_type ) ) {
			if ( null !== $exception ) {
				throw $exception;
			}
			return $res;
		}

		// Log the primary failure, then send from the fallback connection.
		$primary_message = null !== $exception
			? $exception->getMessage()
			: ( is_wp_error( $res ) ? $res->get_error_message() : esc_html__( 'Send failed.', 'smart-smtp' ) );

		if ( null !== $exception ) {
			$mail_data['phpmailer_exception_code'] = $exception->getCode();
		}

		$error          = new \WP_Error( 'wp_mail_failed', $primary_message, $mail_data );
		$service        = Services::init();
		$primary_log_id = $service->on_email_failed( $error );

		$res = $this->send_from( $mail_data, 'fallback', $fallback_type );

		update_option( 'smart_smtp_fallback_triggered_count', (int) get_option( 'smart_smtp_fallback_triggered_count', 0 ) + 1, false );

		$mail_data['primary_id'] = false === $primary_log_id ? 0 : $primary_log_id;

		return $res;
	}

	/**
	 * Routes to respective provider to send mail.
	 *
	 * @param [type]   $mail_data The mail data.
	 * @param [string] $conn The connection type
	 * @param [string] $provider_type The provider type.
	 * @return void
	 */
	private function send_from( &$mail_data, $conn, $provider_type ) {
		$res = false;

		switch ( $provider_type ) {
			case 'brevo':
				$brevo = new \SmartSMTP\Services\Providers\Brevo\Mailer( $this->php_mailer, $conn );
				$res   = $brevo->send( $mail_data );
				break;
			case 'gmail':
				$gmail = new \SmartSMTP\Services\Providers\Gmail\Mailer( $this->php_mailer, $conn );
				$res   = $gmail->send( $mail_data );
				break;
			case 'other':
				$other = new \SmartSMTP\Services\Providers\Other\Mailer( $this->php_mailer, $conn );
				$res   = $other->send( $mail_data );
				break;
			default:
				$other = new \SmartSMTP\Services\Providers\DefaultSmtp\Mailer( $this->php_mailer, $conn );
				$res   = $other->send( $mail_data );
		}

		return $res;
	}

	/**
	 * Send test mail.
	 *
	 * @param [type] $mail_data The mail data.
	 * @param [type] $connection The connection type.
	 * @return void
	 */
	public function send_test_mail( &$mail_data, $connection ) {
		$config_inst = new Provider();

		$provider_type = $config_inst->get_provider_type( $connection );
		try {
			$res     = $this->send_from( $mail_data, $connection, $provider_type );
			$service = Services::init();

			// Some providers (e.g. Gmail) return a WP_Error instead of throwing on
			// failure. Treat WP_Error / false as a failed send so it is logged as
			// Failed, not Sent.
			if ( is_wp_error( $res ) || false === $res ) {
				$message = is_wp_error( $res ) ? $res->get_error_message() : esc_html__( 'Send failed.', 'smart-smtp' );
				$service->on_email_failed( new \WP_Error( 'wp_mail_failed', $message, $mail_data ) );

				return array(
					'res'     => false,
					'message' => $message,
					'code'    => 400,
				);
			}

			$service->smtp_email_logs( $mail_data );

			return $res;
		} catch ( \Exception $e ) {
			$error = new \WP_Error( 'wp_mail_failed', $e->getMessage(), $mail_data );

			$service        = Services::init();
			$primary_log_id = $service->on_email_failed( $error );

			return array(
				'res'     => false,
				'message' => $e->getMessage(),
				'code'    => 400,
			);
		}
	}
}
