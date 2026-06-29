<?php
/**
 * Gmail mailer.
 *
 * @since 1.0.2
 * @package Gmail mailer.
 */

namespace SmartSMTP\Services\Providers\Gmail;

use SmartSMTP\Controller\ProviderController;
use SmartSMTP\SMTP\Helper;
use SmartSMTP\Services\Providers\MailerAbstract;

/**
 * Default mailer class.
 *
 * @since 1.0.2
 */
class Mailer extends MailerAbstract {

	/**
	 * Provider type name.
	 *
	 * @since 1.0.2
	 * @var string
	 */
	protected static $type = 'gmail';
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
	 * Gmail configuration.
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
	 * @since 1.0.2
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

		// Prepare the email for sending.
		$this->php_mailer->preSend();

		// Get the MIME message.
		$mime_message = $this->php_mailer->getSentMIMEMessage();

		$encoded_message = base64_encode( $mime_message );
		$encoded_message = strtr(
			$encoded_message,
			array(
				'+' => '-',
				'/' => '_',
				'=' => '',
			)
		); // URL-safe base64

		$googleMessage = new \Google_Service_Gmail_Message();
		$googleMessage->setRaw( $encoded_message );

		// Pass the connection so GmailSettings can persist refreshed tokens to the
		// correct slot. Without it, get_client() refreshes on expiry but writes to an
		// empty connection (no-op), silently degrading the connection after ~1 hour.
		$gmail  = new GmailSettings( array_merge( $mail_config, array( 'conn' => $this->conn ) ) );
		$client = $gmail->get_client();

		// Prepare the Gmail service.
		$service = new \Google_Service_Gmail( $client );

		try {
			$message = $service->users_messages->send( 'me', $googleMessage );

			return $message->getId();
		} catch ( \Throwable $e ) {
			// Google exceptions carry a raw JSON blob as the message. Map to a short,
			// human-readable message instead of dumping the full error payload. Returning
			// a WP_Error (rather than throwing) is fine — BaseMailer treats a WP_Error
			// result from the primary as a failure and triggers the fallback connection.
			$code = (int) $e->getCode();

			if ( 401 === $code || 403 === $code ) {
				$friendly = esc_html__( 'Gmail authentication failed. Please reconnect your Google account.', 'smart-smtp' );
			} else {
				$friendly = esc_html__( 'Could not send the email through Gmail. Please try again.', 'smart-smtp' );
			}

			return new \WP_Error( 422, $friendly, array() );
		}
	}


	/**
	 * Func to check the mail is complete or not.
	 *
	 * @since 1.0.2
	 * @param string $conn The connection.
	 */
	public static function is_mailer_complete( $conn ) {
		$config = self::get_configuration( $conn );

		return isset( $config['access_token'] ) && ! empty( $config['access_token'] );
	}
}
