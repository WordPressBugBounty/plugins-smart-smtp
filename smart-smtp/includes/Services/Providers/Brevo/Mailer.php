<?php
/**
 * Brevo mailer.
 *
 * @since 1.0.0
 * @package Brevo mailer.
 */

namespace SmartSMTP\Services\Providers\Brevo;

use SmartSMTP\Controller\ProviderController;
use SmartSMTP\Services\Providers\MailerAbstract;

/**
 * Brevo mailer class.
 *
 * @since 0
 */
class Mailer extends MailerAbstract {
	/**
	 * Email send code.
	 *
	 * @since 0
	 * @var int
	 */
	protected $email_sent_code = 201;
	/**
	 * Api url.
	 *
	 * @since 0
	 * @var string
	 */
	protected static $url = 'https://api.brevo.com/v3/smtp/email';
	/**
	 * Validate the api key.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected static $validate_url = 'https://api.brevo.com/v3/account';

	/**
	 * Allow attachment extensions.
	 *
	 * @since 0
	 * @var array
	 */
	protected $allowed_attachment_exts = array(
		'xlsx',
		'xls',
		'ods',
		'docx',
		'docm',
		'doc',
		'csv',
		'pdf',
		'txt',
		'gif',
		'jpg',
		'jpeg',
		'png',
		'tif',
		'tiff',
		'rtf',
		'bmp',
		'cgm',
		'css',
		'shtml',
		'html',
		'htm',
		'zip',
		'xml',
		'ppt',
		'pptx',
		'tar',
		'ez',
		'ics',
		'mobi',
		'msg',
		'pub',
		'eps',
		'odt',
		'mp3',
		'm4a',
		'm4v',
		'wma',
		'ogg',
		'flac',
		'wav',
		'aif',
		'aifc',
		'aiff',
		'mp4',
		'mov',
		'avi',
		'mkv',
		'mpeg',
		'mpg',
		'wmv',
	);

	/**
	 * Provider type name.
	 *
	 * @since 0
	 * @var string
	 */
	protected static $type = 'brevo';
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
	 * Set the From information for an email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email The mail from.
	 * @param string $name The mail name.
	 */
	public function set_from( $email, $name ) {

		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			return;
		}

		$this->body['sender'] = array(
			'email' => $email,
			'name'  => ! empty( $name ) ? $name : '',
		);
	}
	/**
	 * Set the subject.
	 *
	 * @since 1.0.0
	 * @param mixed $subject The subject of the mail.
	 */
	public function set_subject( $subject ) {

		$this->body['subject'] = $subject;
	}
	/**
	 * Set email content.
	 *
	 * @since 1.0.0
	 *
	 * @param string|array $content The content of mail.
	 */
	public function set_content( $content ) {

		if ( empty( $content ) ) {
			return;
		}

		if ( is_array( $content ) ) {

			if ( ! empty( $content['text'] ) ) {
				$this->body['textContent'] = $content['text'];
			}

			if ( ! empty( $content['html'] ) ) {
				$this->body['htmlContent'] = $content['html'];
			}
		} elseif ( 'text/plain' === $this->php_mailer->ContentType ) {
				$this->body['textContent'] = $content;
		} else {
			$this->body['htmlContent'] = $content;
		}
	}
	/**
	 * Doesn't support this.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email The email.
	 */
	public function set_return_path( $email ) {
	}

	/**
	 * Set the Reply To headers if not set already.
	 *
	 * @since 1.0.0
	 *
	 * @param array $emails The reply emails.
	 */
	public function set_reply_to( $emails ) {

		if ( empty( $emails ) ) {
			return;
		}

		$data = array();

		foreach ( $emails as $user ) {
			$holder = array();
			$addr   = isset( $user[0] ) ? $user[0] : false;
			$name   = isset( $user[1] ) ? $user[1] : false;

			if ( ! filter_var( $addr, FILTER_VALIDATE_EMAIL ) ) {
				continue;
			}

			$holder['email'] = $addr;
			if ( ! empty( $name ) ) {
				$holder['name'] = $name;
			}

			$data[] = $holder;
		}

		if ( ! empty( $data ) ) {
			$this->body['replyTo'] = $data[0];
		}
	}
	/**
	 * Set attachments for an email.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attachments The array of attachments data.
	 */
	public function set_attachments( $attachments ) {

		if ( empty( $attachments ) ) {
			return;
		}

		foreach ( $attachments as $attachment ) {

			$ext = pathinfo( $attachment[1], PATHINFO_EXTENSION );

			if ( ! in_array( $ext, $this->allowed_attach_ext, true ) ) {
				continue;
			}

			$file = $this->get_attachment_file_content( $attachment );

			if ( false === $file ) {
				continue;
			}

			$this->body['attachment'][] = array(
				'name'    => $this->get_attachment_file_name( $attachment ),
				'content' => base64_encode( $file ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			);
		}
	}
	/**
	 * Sets email recipients for 'to', 'cc', and 'bcc'.
	 *
	 * This method processes and validates recipient email addresses and names, and assigns them to the appropriate
	 * categories (to, cc, bcc) in the email data. Only valid emails and non-empty arrays are considered.
	 *
	 * @since 1.0.0
	 *
	 * @param array $recipients Associative array with recipient types ('to', 'cc', 'bcc') and their corresponding
	 *                          email addresses and names. Each recipient can be an array of email address and optional
	 *                          name.
	 */
	public function set_recipients( $recipients ) {

		if ( empty( $recipients ) ) {
			return;
		}

		// Allow for now only these recipient types.
		$default = array( 'to', 'cc', 'bcc' );
		$data    = array();

		foreach ( $recipients as $type => $emails ) {

			if (
				! in_array( $type, $default, true ) ||
				empty( $emails ) ||
				! is_array( $emails )
			) {
				continue;
			}

			$data[ $type ] = array();

			// Iterate over all emails for each type.
			// There might be multiple cc/to/bcc emails.
			foreach ( $emails as $email ) {
				$holder = array();
				$addr   = isset( $email[0] ) ? $email[0] : false;
				$name   = isset( $email[1] ) ? $email[1] : false;

				if ( ! filter_var( $addr, FILTER_VALIDATE_EMAIL ) ) {
					continue;
				}

				$holder['email'] = $addr;
				if ( ! empty( $name ) ) {
					$holder['name'] = $name;
				}

				array_push( $data[ $type ], $holder );
			}
		}

		foreach ( $data as $type => $type_recipients ) {
			$this->body[ $type ] = $type_recipients;
		}
	}
	/**
	 * Process the php mailer.
	 *
	 * @since 1.0.0
	 */
	public function process_phpmailer() {
		$this->set_from( $this->php_mailer->From, $this->php_mailer->FromName );
		$this->set_recipients(
			array(
				'to'  => $this->php_mailer->getToAddresses(),
				'cc'  => $this->php_mailer->getCcAddresses(),
				'bcc' => $this->php_mailer->getBccAddresses(),
			)
		);
		$this->set_subject( $this->php_mailer->Subject );

		if ( 'text/plain' === $this->php_mailer->ContentType ) {
			$this->set_content( $this->php_mailer->Body );
		} else {
			$this->set_content(
				array(
					'text' => $this->php_mailer->AltBody,
					'html' => $this->php_mailer->Body,
				)
			);
		}

		$this->set_return_path( $this->php_mailer->From );
		$this->set_reply_to( $this->php_mailer->getReplyToAddresses() );
		$this->set_attachments( $this->php_mailer->getAttachments() );
	}
	/**
	 * Brevo configuration.
	 *
	 * @since 1.0.0
	 * @param string $conn The connection type.
	 */
	public static function get_configuration( $conn ) {
		$provider = new ProviderController();
		return $provider->get_provider_config_by_conn( $conn, self::$type );
	}
	/**
	 * Function to send an email using an external API.
	 *
	 * This method configures the HTTP headers for the API request, including the API key and content type,
	 * and then sends the email data to the specified API endpoint. The email data is provided in the `$mail_data`
	 * parameter and is encoded as a JSON object in the request body. The function processes the API response and returns
	 * the result of the operation.
	 *
	 * @since 1.0.0
	 *
	 * @param array $mail_data An associative array containing email details. This array should include:
	 *                          - 'to'          => Recipient email address.
	 *                          - 'subject'     => Email subject.
	 *                          - 'message'     => Email body content.
	 *                          - 'headers'     => Additional headers for the email.
	 *                          - 'attachments' => List of file paths for attachments.
	 *                          The `$mail_data` parameter is used to construct the API request body.
	 *
	 * @return mixed The result of the API request. This includes the processed response from the external API.
	 *               It typically returns an array or object depending on the implementation of `process_response()`.
	 */
	public function send( &$mail_data ) {
		$config = self::get_configuration( $this->conn );

		$this->set_force_from_name_and_email( $config, $mail_data );

		$this->process_phpmailer();

		$this->set_header( 'api-key', $config['smtp_api_key'] );
		$this->set_header( 'Accept', 'application/json' );
		$this->set_header( 'Content-Type', 'application/json' );

		$params = array(
			'headers' => $this->headers,
			'body'    => wp_json_encode( $this->body ),
		);

		$response = wp_safe_remote_post( self::$url, $params );
		$reponse  = $this->process_response( $response );
		return $reponse;
	}
	/**
	 * Processes the response from the email API after sending an email.
	 *
	 * This method handles the response received from the API by checking if it is a `WP_Error` object or a valid API response.
	 * It interprets the response code and body to determine if the email was sent successfully or if an error occurred.
	 * Based on the result, it either returns a success message or throws an exception with the error details.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $response The response from the API. This can be:
	 *                        - A `WP_Error` object if there was an issue with the API request.
	 *                        - A response array if the request was successful, containing the API response data.
	 *
	 * @return array|void Returns an associative array with the status of the email sending operation:
	 *                    - 'res'     => Boolean indicating success (`true`) or failure (`false`).
	 *                    - 'message' => A message describing the result (e.g., 'Sent Successfully').
	 *                    - 'code'    => HTTP response code or other relevant code.
	 *                    If an error occurs, an exception is thrown with details from the response.
	 *
	 * @throws \PHPMailer\PHPMailer\Exception If the response code indicates failure, an exception is thrown with
	 *                                        the error message and code from the response body.
	 */
	protected function process_response( $response ) {

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( $response->get_error_code(), $response->get_error_message(), $response->get_error_messages() );
		} else {
			$response_body = wp_remote_retrieve_body( $response );
			$response_code = wp_remote_retrieve_response_code( $response );

			$response_body = \json_decode( $response_body, true );

			if ( $response_code === $this->email_sent_code ) {
				return array(
					'res'     => true,
					'message' => esc_html__( 'Sent Successfully', 'smart-smtp' ),
					'code'    => 202,
				);
			} else {
				throw new \PHPMailer\PHPMailer\Exception( $response_body['message'], $response_code );
			}
		}
	}
	/**
	 * To check the api key is valid or not.
	 *
	 * @since 1.0.0
	 *
	 * @param  [type] $api_key The api key.
	 */
	public static function check_brevo_api_key( $api_key ) {

		$response      = wp_safe_remote_post(
			self::$validate_url,
			array(
				'headers'   => array(
					'api-key' => $api_key,
				),
				'method'    => 'GET',
				'timeout'   => 45,
				'sslverify' => false,
			)
		);
		$response_body = wp_remote_retrieve_body( $response );
		$response_body = \json_decode( $response_body, true );

		$http_code = wp_remote_retrieve_response_code( $response );
		$res       = array(
			'code'      => $http_code,
			'error_msg' => isset( $response_body['message'] ) ? $response_body['message'] : '',
		);
		update_option(
			'smart_smtp_brevo_mailer_validation',
			$res
		);

		return $res;
	}
	/**
	 * Function to get Validated message and codes.
	 *
	 * @since 1.0.0
	 */
	public static function get_mailer_validation_data() {
		return get_option( 'smart_smtp_brevo_mailer_validation', array() );
	}
		/**
		 * Func to check the mail is complete or not.
		 *
		 * @since 1.0.0
		 * @param string $conn The connection type.
		 */
	public static function is_mailer_complete( $conn ) {
		$mail_config = self::get_configuration( $conn );

		if ( empty( $mail_config['smtp_api_key'] ) ) {
			return false;
		}
		$mailer_validated_data = self::get_mailer_validation_data();

		if ( empty( $mailer_validated_data ) ) {
			return false;
		}

		if ( isset( $mailer_validated_data['code'] ) && 200 !== $mailer_validated_data['code'] ) {
			return false;
		}

		return true;
	}
}
