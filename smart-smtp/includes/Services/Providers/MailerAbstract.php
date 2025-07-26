<?php
/**
 * SmartSMTP Provider class.
 *
 * @package  namespace SmartSMTP\Services\Providers
 *
 * @since 1.0.0
 */

namespace SmartSMTP\Services\Providers;

use SmartSMTP\SmartSMTP;
use SmartSMTP\Model\Provider;

/**
 * Base Mailer.
 *
 * @since 1.0.0
 */
class MailerAbstract {

	/**
	 * Easymail smtp mailer.
	 *
	 * @since 0
	 * @var [type] $php_mailer base php mailer.
	 */
	protected $php_mailer = null;
	/**
	 * Set the email headers.
	 *
	 * @since 1.0.0
	 *
	 * @param array $headers List of key=>value pairs.
	 */
	public function set_headers( $headers ) {

		foreach ( $headers as $header ) {
			$name  = isset( $header[0] ) ? $header[0] : false;
			$value = isset( $header[1] ) ? $header[1] : false;

			if ( empty( $name ) || empty( $value ) ) {
				continue;
			}

			$this->set_header( $name, $value );
		}
	}

	/**
	 * Set individual header key=>value pair for the email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name The name.
	 * @param string $value The value.
	 */
	public function set_header( $name, $value ) {

		$name = sanitize_text_field( $name );

		$this->headers[ $name ] = $value;
	}

	/**
	 * Set the from name and email if the force option is enabled.
	 *
	 * @param [type] $mail_config The mail config.
	 * @param array  $mail_data An associative array containing email details, which may include:
	 *                           - 'to'          => Recipient email address.
	 *                           - 'subject'     => Email subject.
	 *                           - 'message'     => Email body content.
	 *                           - 'headers'     => Additional headers for the email.
	 *                           - 'attachments' => List of file paths for attachments.
	 *                           This array is passed by reference and can be modified within the method.
	 * @return void
	 */
	public function set_force_from_name_and_email( $mail_config, &$mail_data ) {

		if ( isset( $mail_config['smtp_from_name'] ) && ! empty( $mail_config['smtp_from_name'] ) && isset( $mail_config['smtp_from_email_address'] ) && ! empty( $mail_config['smtp_from_email_address'] ) ) {
			$mail_data['headers']['from'] = sanitize_email( $this->php_mailer->From );

			if ( isset( $mail_config['smtp_force_from_email'] ) && $mail_config['smtp_force_from_email'] && isset( $mail_config['smtp_force_from_name'] ) && $mail_config['smtp_force_from_name'] ) {
				$this->php_mailer->setFrom( sanitize_email( $mail_config['smtp_from_email_address'] ), sanitize_text_field( $mail_config['smtp_from_name'] ) );
				$mail_data['headers']['from'] = sanitize_email( $mail_config['smtp_from_email_address'] );
			} elseif ( isset( $mail_config['smtp_force_from_email'] ) && $mail_config['smtp_force_from_email'] ) {
				$this->php_mailer->setFrom( sanitize_email( $mail_config['smtp_from_email_address'] ), sanitize_text_field( $this->php_mailer->FromName ) );
				$mail_data['headers']['from'] = sanitize_email( $mail_config['smtp_from_email_address'] );

			} elseif ( isset( $mail_config['smtp_force_from_name'] ) && $mail_config['smtp_force_from_name'] ) {
				$this->php_mailer->setFrom( sanitize_email( $this->php_mailer->From ), sanitize_text_field( $mail_config['smtp_from_name'] ) );
			}
		}

		if ( isset( $mail_config['smtp_set_return_path'] ) && $mail_config['smtp_set_return_path'] ) {
			$this->php_mailer->Sender = $mail_config['smtp_from_email_address'];
		}
	}
}
