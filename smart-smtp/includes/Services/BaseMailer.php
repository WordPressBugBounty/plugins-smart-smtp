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

		try {
			$res = $this->send_from( $mail_data, 'primary', $provider_type );
		} catch ( \Exception $e ) {
			// Initial send mail from the fallback connection.

			if ( ! $config_inst->get_is_fallback_enabled() ) {
				throw $e;
			}

			$provider_type = $config_inst->get_provider_type( 'fallback' );

			if ( empty( $provider_type ) ) {
				throw $e;
			}

			$mail_data['phpmailer_exception_code'] = $e->getCode();

			// Catching the Primary log.
			$error = new \WP_Error( 'wp_mail_failed', $e->getMessage(), $mail_data );

			$service        = Services::init();
			$primary_log_id = $service->on_email_failed( $error );

			// Sending mail from the fallback.
			$res = $this->send_from( $mail_data, 'fallback', $provider_type );

			// Storing the primary log id in the fallback log.
			$mail_data['primary_id'] = false === $primary_log_id ? 0 : $primary_log_id;
		}

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
			$res            = $this->send_from( $mail_data, $connection, $provider_type );
			$service        = Services::init();
			$primary_log_id = $service->smtp_email_logs( $mail_data );

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
