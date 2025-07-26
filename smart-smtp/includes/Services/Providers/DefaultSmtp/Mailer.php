<?php
/**
 * Default mailer.
 *
 * @since 1.0.0
 * @package Default mailer.
 */

namespace SmartSMTP\Services\Providers\DefaultSmtp;

use SmartSMTP\Controller\ProviderController;
use SmartSMTP\SMTP\Helper;
use SmartSMTP\Services\Providers\MailerAbstract;

/**
 * Default mailer class.
 *
 * @since 0
 */
class Mailer extends MailerAbstract {

	/**
	 * Provider type name.
	 *
	 * @since 0
	 * @var string
	 */
	protected static $type = 'default';
	/**
	 * The connection type.
	 *
	 * @var string
	 */
	protected $conn = '';

	/**
	 * Brevo mailer constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param  [type] $php_mailer The default mailer.
	 * @param string $conn The connection type.
	 */
	public function __construct( $php_mailer, $conn ) {
		$this->php_mailer = $php_mailer;
		$this->conn       = $conn;
	}
	/**
	 * Default configuration.
	 *
	 * @since 1.0.0
	 * @param string $conn The connection type.
	 */
	public static function get_configuration( $conn ) {
		$provider = new ProviderController();

		return $provider->get_provider_config_by_conn( $conn, self::$type );
	}
	/**
	 * Function to send an email using PHPMailer.
	 *
	 * This method configures PHPMailer with the sender's email address and name, and sends the email based on the provided `mail_data`.
	 * The email configuration is retrieved from the `get_configuration` method, and the email is sent using the PHPMailer instance.
	 * The `mail_data` array contains details such as the recipient, subject, message, headers, and attachments.
	 *
	 * @since 1.0.0
	 *
	 * @param array $mail_data An associative array containing email details:
	 *                          - 'to'          => Recipient email address.
	 *                          - 'subject'     => Email subject.
	 *                          - 'message'     => Email body content.
	 *                          - 'headers'     => Additional headers for the email.
	 *                          - 'attachments' => List of file paths for attachments.
	 *                          This array is passed by reference and can be modified within the method.
	 *
	 * @return bool True on success, false on failure. Returns the result of the `send` method from the PHPMailer instance.
	 */
	public function send( &$mail_data ) {
		$mail_config = $this->get_configuration( $this->conn );
		$this->set_force_from_name_and_email( $mail_config, $mail_data );

		$result = $this->php_mailer->send();

		return $result;
	}

	/**
	 * Func to check the mail is complete or not.
	 *
	 * @since 1.0.0
	 */
	public static function is_mailer_complete( $conn ) {
		$provider    = new ProviderController();
		$mail_config = $provider->get_provider_config_by_conn( $conn, self::$type );

		if ( ! isset( $mail_config['smtp_from_name'] ) ) {
			return false;
		}

		if ( empty( $mail_config['smtp_from_name'] ) ) {
			return false;
		}

		if ( ! isset( $mail_config['smtp_from_email_address'] ) ) {
			return false;
		}
		if ( empty( $mail_config['smtp_from_email_address'] ) ) {
			return false;
		}

		return true;
	}
}
