<?php
/**
 * SmartSMTP Services class.
 *
 * @package  namespace SmartSMTP\Services
 *
 * @since 1.0.0
 */

namespace SmartSMTP\Services;

use SmartSMTP\Helper;
use SmartSMTP\Model\MailLogs;
use SmartSMTP\Model\Provider;
use SmartSMTP\Services\BaseMailer;
use SmartSMTP\Traits\Singleton;

/**
 * Services class for Wpeverest stmp.
 *
 * @since 1.0.0
 */
class Services {

	use Singleton;

	/**
	 * Variable to access the response message.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	public static $response_message = array();

	/**
	 * Cached parsed header info captured from wp_mail filter before any SMTP plugin processes it.
	 *
	 * @var array|null
	 */
	public static $cached_header_info = null;

	/** When true, smtp_email_logs skips inserting a new row (used during resend). */
	public static $skip_logging = false;

	/** Recursion guard — true while our own dispatch is sending. */
	public static $sending = false;

	/**
	 * Services class constructor.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		add_action( 'wp_mail_failed', array( $this, 'on_email_failed' ) );
		add_action( 'wp_mail_succeeded', array( $this, 'smtp_email_logs' ) );
		add_filter( 'wp_mail', array( $this, 'capture_headers_early' ), 1 );
		// Win conflicts with other SMTP plugins (e.g. Fluent SMTP) that own wp_mail():
		// their wp_mail() fires the pre_wp_mail filter, so we hijack it and send through
		// Smart SMTP (primary + fallback) instead. Works for WP core wp_mail() too.
		add_filter( 'pre_wp_mail', array( $this, 'maybe_take_over' ), 1, 2 );
	}

	/**
	 * Take over sending when another plugin's (or core's) wp_mail() runs.
	 *
	 * @param null|bool $return The short-circuit value.
	 * @param array     $atts   wp_mail() arguments.
	 * @return null|bool Null to let the caller proceed, or a bool send result.
	 */
	public function maybe_take_over( $return, $atts ) {
		// Already handled by something else, or our own dispatch is in progress.
		if ( null !== $return || self::$sending ) {
			return $return;
		}

		$to          = isset( $atts['to'] ) ? $atts['to'] : '';
		$subject     = isset( $atts['subject'] ) ? $atts['subject'] : '';
		$message     = isset( $atts['message'] ) ? $atts['message'] : '';
		$headers     = isset( $atts['headers'] ) ? $atts['headers'] : '';
		$attachments = isset( $atts['attachments'] ) ? $atts['attachments'] : array();

		return self::smart_smtp_mail( $to, $subject, $message, $headers, $attachments );
	}

	/**
	 * Capture original headers before any SMTP plugin processes them.
	 *
	 * @param array $atts wp_mail arguments.
	 * @return array
	 */
	public function capture_headers_early( $atts ) {
		$raw = isset( $atts['headers'] ) ? $atts['headers'] : '';

		if ( is_string( $raw ) ) {
			// If headers is a JSON string, decode and use associatively.
			$json = json_decode( $raw, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $json ) ) {
				$lines = $json;
			} else {
				$lines = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r\n", "\n", $raw ) ) ) );
			}
		} elseif ( is_array( $raw ) ) {
			$lines = $raw;
		} else {
			$lines = array();
		}

		$info = array(
			'content-type' => '',
			'reply-to'     => array(),
			'cc'           => array(),
			'bcc'          => array(),
		);

		foreach ( $lines as $hkey => $hval ) {
			if ( is_string( $hkey ) ) {
				$hname    = strtolower( trim( $hkey ) );
				$hcontent = trim( (string) $hval );
			} elseif ( is_string( $hval ) && strpos( $hval, ':' ) !== false ) {
				list( $hname, $hcontent ) = explode( ':', $hval, 2 );
				$hname    = strtolower( trim( $hname ) );
				$hcontent = trim( $hcontent );
			} else {
				continue;
			}
			switch ( $hname ) {
				case 'content-type':
					$info['content-type'] = strtok( $hcontent, ';' );
					break;
				case 'reply-to':
					$info['reply-to'][] = array( 'email' => $hcontent );
					break;
				case 'cc':
					$info['cc'][] = $hcontent;
					break;
				case 'bcc':
					$info['bcc'][] = $hcontent;
					break;
			}
		}

		self::$cached_header_info = $info;
		return $atts;
	}

	/**
	 * Easy mail smtp mail function.
	 *
	 * @since 1.0.0
	 *
	 * @param  [type] $to The reciever email address.
	 * @param  [type] $subject The Subject of email.
	 * @param  [type] $message The message of email.
	 * @param  string $headers The Header of email.
	 * @param  array  $attachments The attachment of email.
	 */
	public static function smart_smtp_mail( $to, $subject, $message, $headers, $attachments ) {
		// Guard so our pre_wp_mail takeover filter does not re-enter while we send.
		self::$sending = true;
		try {
			return self::dispatch_mail( $to, $subject, $message, $headers, $attachments );
		} finally {
			self::$sending = false;
		}
	}

	/**
	 * Actual mail dispatch through Smart SMTP (primary + fallback).
	 *
	 * @param mixed  $to          Recipient(s).
	 * @param string $subject     Subject.
	 * @param string $message     Body.
	 * @param mixed  $headers     Headers.
	 * @param mixed  $attachments Attachments.
	 * @return bool
	 */
	private static function dispatch_mail( $to, $subject, $message, $headers, $attachments ) {
		// Compact the input, apply the filters, and extract them back out.

		/**
		 * Filters the wp_mail() arguments.
		 *
		 * @since 2.2.0
		 *
		 * @param array $args {
		 *     Array of the `wp_mail()` arguments.
		 *
		 *     @type string|string[] $to          Array or comma-separated list of email addresses to send message.
		 *     @type string          $subject     Email subject.
		 *     @type string          $message     Message contents.
		 *     @type string|string[] $headers     Additional headers.
		 *     @type string|string[] $attachments Paths to files to attach.
		 * }
		 */
		$atts = apply_filters(
			'wp_mail',
			compact( 'to', 'subject', 'message', 'headers', 'attachments' )
		);

		/**
		 * Filters whether to preempt sending an email.
		 *
		 * Returning a non-null value will short-circuit wp_mail(), returning
		 * that value instead. A boolean return value should be used to indicate whether
		 * the email was successfully sent.
		 *
		 * @since 5.7.0
		 *
		 * @param null|bool $return Short-circuit return value.
		 * @param array     $atts {
		 *     Array of the `wp_mail()` arguments.
		 *
		 *     @type string|string[] $to          Array or comma-separated list of email addresses to send message.
		 *     @type string          $subject     Email subject.
		 *     @type string          $message     Message contents.
		 *     @type string|string[] $headers     Additional headers.
		 *     @type string|string[] $attachments Paths to files to attach.
		 * }
		 */
		$pre_wp_mail = apply_filters( 'pre_wp_mail', null, $atts );

		if ( null !== $pre_wp_mail ) {
			return $pre_wp_mail;
		}

		if ( isset( $atts['to'] ) ) {
			$to = $atts['to'];
		}

		if ( ! is_array( $to ) ) {
			$to = explode( ',', $to );
		}

		if ( isset( $atts['subject'] ) ) {
			$subject = $atts['subject'];
		}

		if ( isset( $atts['message'] ) ) {
			$message = $atts['message'];
		}

		if ( isset( $atts['headers'] ) ) {
			$headers = $atts['headers'];
		}

		if ( isset( $atts['attachments'] ) ) {
			$attachments = $atts['attachments'];
		}

		if ( ! is_array( $attachments ) ) {
			$attachments = explode( "\n", str_replace( "\r\n", "\n", $attachments ) );
		}

		// De-duplicate: the wp_mail filter fires here AND earlier in WP core's
		// wp_mail(), so any filter that appends an attachment path would add it
		// twice. array_unique keeps only distinct paths; array_values resets keys.
		$attachments = array_values( array_unique( array_filter( $attachments ) ) );

		global $phpmailer;

		if ( ! ( $phpmailer instanceof \PHPMailer\PHPMailer\PHPMailer ) ) {
			require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
			require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
			require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
			$phpmailer = new \PHPMailer\PHPMailer\PHPMailer( true ); //phpcs:ignore

			$phpmailer::$validator = static function ( $email ) {
				return (bool) is_email( $email );
			};
		}
		// Headers.
		$cc       = array();
		$bcc      = array();
		$reply_to = array();

		if ( empty( $headers ) ) {
			$headers = array();
		} else {
			if ( ! is_array( $headers ) ) {
				/*
				* Explode the headers out, so this function can take
				* both string headers and an array of headers.
				*/
				$tempheaders = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
			} else {
				$tempheaders = $headers;
			}
			$headers = array();

			if ( ! empty( $tempheaders ) ) {
				foreach ( (array) $tempheaders as $header ) {
					if ( strpos( $header, ':' ) === false ) {
						if ( false !== stripos( $header, 'boundary=' ) ) {
							$parts    = preg_split( '/boundary=/i', trim( $header ) );
							$boundary = trim( str_replace( array( "'", '"' ), '', $parts[1] ) );
						}
						continue;
					}
					list($name, $content) = explode( ':', trim( $header ), 2 );

					$name    = trim( $name );
					$content = trim( $content );

					switch ( strtolower( $name ) ) {
						case 'from':
							$bracket_pos = strpos( $content, '<' );
							if ( false !== $bracket_pos ) {
								if ( $bracket_pos > 0 ) {
									$from_name = substr( $content, 0, $bracket_pos - 1 );
									$from_name = str_replace( '"', '', $from_name );
									$from_name = trim( $from_name );
								}

								$from_email = substr( $content, $bracket_pos + 1 );
								$from_email = str_replace( '>', '', $from_email );
								$from_email = trim( $from_email );

							} elseif ( '' !== trim( $content ) ) {
								$from_email = trim( $content );
							}
							break;
						case 'content-type':
							if ( strpos( $content, ';' ) !== false ) {
								list($type, $charset_content) = explode( ';', $content );
								$content_type                 = trim( $type );
								if ( false !== stripos( $charset_content, 'charset=' ) ) {
									$charset = trim( str_replace( array( 'charset=', '"' ), '', $charset_content ) );
								} elseif ( false !== stripos( $charset_content, 'boundary=' ) ) {
									$boundary = trim( str_replace( array( 'BOUNDARY=', 'boundary=', '"' ), '', $charset_content ) );
									$charset  = '';
								}
							} elseif ( '' !== trim( $content ) ) {
								$content_type = trim( $content );
							}
							break;
						case 'cc':
							$cc = array_merge( (array) $cc, explode( ',', $content ) );
							break;
						case 'bcc':
							$bcc = array_merge( (array) $bcc, explode( ',', $content ) );
							break;
						case 'reply-to':
							$reply_to = array_merge( (array) $reply_to, explode( ',', $content ) );
							break;
						default:
							$headers[ trim( $name ) ] = trim( $content );
							break;
					}
				}
			}
		}

		$phpmailer->clearAllRecipients();
		$phpmailer->clearAttachments();
		$phpmailer->clearCustomHeaders();
		$phpmailer->clearReplyTos();
		$phpmailer->Body    = '';
		$phpmailer->AltBody = '';

		// Set "From" name and email.

		// If we don't have a name from the input headers.
		if ( ! isset( $from_name ) ) {
			$from_name = 'WordPress';
		}
		/*
		* If we don't have an email from the input headers, default to wordpress@$sitename
		* Some hosts will block outgoing mail from this address if it doesn't exist,
		* but there's no easy alternative. Defaulting to admin_email might appear to be
		* another option, but some hosts may refuse to relay mail from an unknown domain.
		* See https://core.trac.wordpress.org/ticket/5007.
		*/
		if ( ! isset( $from_email ) ) {
			// Get the site domain and get rid of www.
			$sitename   = wp_parse_url( network_home_url(), PHP_URL_HOST );
			$from_email = 'wordpress@';

			if ( null !== $sitename ) {
				if ( str_starts_with( $sitename, 'www.' ) ) {
					$sitename = substr( $sitename, 4 );
				}

				$from_email .= $sitename;
			}
		}
		/**
	 * Filters the email address to send from.
	 *
	 * @since 2.2.0
	 *
	 * @param string $from_email Email address to send from.
	 */
		$from_email = apply_filters( 'wp_mail_from', $from_email );

		/**
		 * Filters the name to associate with the "from" email address.
		 *
		 * @since 2.3.0
		 *
		 * @param string $from_name Name associated with the "from" email address.
		 */
		$from_name = apply_filters( 'wp_mail_from_name', $from_name );
		try {
			$phpmailer->setFrom( $from_email, $from_name, false );
		} catch ( \PHPMailer\PHPMailer\Exception $e ) {
			$mail_error_data                             = compact( 'to', 'subject', 'message', 'headers', 'attachments' );
			$mail_error_data['phpmailer_exception_code'] = $e->getCode();

			do_action(
				'wp_mail_failed',
				new \WP_Error(
					'wp_mail_failed',
					$e->getMessage(),
					$mail_error_data
				)
			);

			return false;
		}

		$phpmailer->Subject = $subject;
		$phpmailer->Body    = $message;

		// Set destination addresses, using appropriate methods for handling addresses.
		$address_headers = compact( 'to', 'cc', 'bcc', 'reply_to' );

		foreach ( $address_headers as $address_header => $addresses ) {
			if ( empty( $addresses ) ) {
				continue;
			}

			foreach ( (array) $addresses as $address ) {
				try {
					$recipient_name = '';

					if ( preg_match( '/(.*)<(.+)>/', $address, $matches ) ) {
						if ( count( $matches ) == 3 ) {
							$recipient_name = $matches[1];
							$address        = $matches[2];
						}
					}

					switch ( $address_header ) {
						case 'to':
							$phpmailer->addAddress( $address, $recipient_name );
							break;
						case 'cc':
							$phpmailer->addCc( $address, $recipient_name );
							break;
						case 'bcc':
							$phpmailer->addBcc( $address, $recipient_name );
							break;
						case 'reply_to':
							$phpmailer->addReplyTo( $address, $recipient_name );
							break;
					}
				} catch ( \PHPMailer\PHPMailer\Exception $e ) {
					continue;
				}
			}
		}

		// Set to use PHP's mail().
		$phpmailer->isMail();
		// If we don't have a Content-Type from the input headers.
		if ( ! isset( $content_type ) ) {
			$content_type = 'text/plain';
		}
		/**
	 * Filters the wp_mail() content type.
	 *
	 * @since 2.3.0
	 *
	 * @param string $content_type Default wp_mail() content type.
	 */
		$content_type = apply_filters( 'wp_mail_content_type', $content_type );

		$phpmailer->ContentType = $content_type;
			// If we don't have a charset from the input headers.
		if ( ! isset( $charset ) ) {
			$charset = get_bloginfo( 'charset' );
		}
		/**
	 * Filters the default wp_mail() charset.
	 *
	 * @since 2.3.0
	 *
	 * @param string $charset Default email charset.
	 */
		$phpmailer->CharSet = apply_filters( 'wp_mail_charset', $charset );

		// Set custom headers.
		if ( ! empty( $headers ) ) {
			foreach ( (array) $headers as $name => $content ) {
				// Only add custom headers not added automatically by PHPMailer.
				if ( ! in_array( $name, array( 'MIME-Version', 'X-Mailer' ), true ) ) {
					try {
						$phpmailer->addCustomHeader( sprintf( '%1$s: %2$s', $name, $content ) );
					} catch ( \PHPMailer\PHPMailer\Exception $e ) {
						continue;
					}
				}
			}

			if ( false !== stripos( $content_type, 'multipart' ) && ! empty( $boundary ) ) {
				$phpmailer->addCustomHeader( sprintf( 'Content-Type: %s; boundary="%s"', $content_type, $boundary ) );
			}
		}

		if ( ! empty( $attachments ) ) {
			foreach ( $attachments as $filename => $attachment ) {
				$filename = is_string( $filename ) ? $filename : '';

				try {
					$phpmailer->addAttachment( $attachment, $filename );
				} catch ( \PHPMailer\PHPMailer\Exception $e ) {
					continue;
				}
			}
		}
			/**
		 * Fires after PHPMailer is initialized.
		 *
		 * @since 2.2.0
		 *
		 * @param PHPMailer $phpmailer The PHPMailer instance (passed by reference).
		 */
		do_action_ref_array( 'phpmailer_init', array( &$phpmailer ) );

		$mail_data = compact( 'to', 'subject', 'message', 'headers', 'attachments' );
		$mail_data['_header_info'] = array(
			'content-type' => isset( $content_type ) && ! empty( $content_type ) ? $content_type : $phpmailer->ContentType,
			'reply-to'     => $reply_to,
			'cc'           => $cc,
			'bcc'          => $bcc,
		);

		try {
			$basemailer = new BaseMailer( $phpmailer );

			$send = $basemailer->send( $mail_data );

			do_action( 'wp_mail_succeeded', $mail_data );
			self::$response_message = $send;

			return true;

		} catch ( \Throwable $e ) {

			$mail_data['phpmailer_exception_code'] = $e->getCode();

			/**
			 * Fires after a PHPMailer\PHPMailer\Exception is caught.
			 *
			 * @param WP_Error $error A WP_Error object with the PHPMailer\PHPMailer\Exception message, and an array
			 *                        containing the mail recipient, subject, message, headers, and attachments.
			 * @since 4.4.0
			 */
			do_action( 'wp_mail_failed', new \WP_Error( 'wp_mail_failed', $e->getMessage(), $mail_data ) );

			self::$response_message = array(
				'res'     => false,
				'message' => $e->getMessage(),
				'code'    => $e->getCode(),
			);
			return false;
		}
	}
	/**
	 * Send test mail.
	 *
	 * @since 1.0.0
	 *
	 * @param  [array] $test_config The test mail config data.
	 */
	public function send_test_mail( $test_config ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$to  = isset( $test_config['smtp_test_send_to'] ) ? $test_config['smtp_test_send_to'] : '';
		$res = false;

		if ( '' === $to ) {
			return $res;
		}

		$subject = "SmartSMTP: Test email to $to ";

		$message = apply_filters( 'smart_smtp_test_mail_content', 'Congrats, the test email was sent successfully! Thank you for trying out, SmartSMTP. We\'re on a mission to make sure that your emails get delivered.' );

		if ( isset( $test_config['smtp_test_html'] ) && true === $test_config['smtp_test_html'] ) {
			ob_start();
			?>
			<table class="smart-mail-email-body" border="0" cellpadding="0" cellspacing="0" width="100%" bgcolor="#ebebeb" style="background-color: #ebebeb;">
				<tbody>
					<tr>
						<td align="center" style="padding: 20px 0;">
							<table class="smart-mail-email" border="0" cellpadding="0" cellspacing="0" align="center" width="600" bgcolor="#ffffff" style="width: 600px; max-width: 90%; margin: 0 auto; background: #ffffff; border: 0; border-radius: 11px; border-collapse: separate;">
								<tbody>
									<tr>
										<td style="text-align: left; padding: 40px 40px 36px; font-family: Arial, sans-serif;">
								<?php echo wp_kses_post( $message ); ?>
										</td>
									</tr>
								</tbody>
							</table>
						</td>
					</tr>
				</tbody>
			</table>
			<?php
			$message = wp_kses_post( ob_get_clean() );
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		} else {
			$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		}

		$connection = isset( $test_config['smtp_test_connection'] ) ? $test_config['smtp_test_connection'] : '';

		if ( ! empty( $connection ) ) {
			global $phpmailer;

			if ( ! ( $phpmailer instanceof \PHPMailer\PHPMailer\PHPMailer ) ) {
				require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
				require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
				require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
				$phpmailer = new \PHPMailer\PHPMailer\PHPMailer( true ); //phpcs:ignore
			}

			$mail_data = compact( 'to', 'subject', 'message', 'headers' );

			$phpmailer->addAddress( $to );
			$phpmailer->Subject = $subject;
			$phpmailer->Body    = $message;
			foreach ( $headers as $header ) {
				$phpmailer->addCustomHeader( $header );
			}

			$mailer = new BaseMailer( $phpmailer );
			$res    = $mailer->send_test_mail( $mail_data, $connection );
		} else {
			$res = wp_mail( $to, $subject, $message, $headers );
		}

		return $res;
	}

	/**
	 * Get the email logs.
	 *
	 * @since 1.0.0
	 *
	 * @param  [type] $logs The email logs.
	 */
	public function smtp_email_logs( $logs ) {
		if ( self::$skip_logging ) {
			return;
		}
		$email_logs = $this->format_log_data( $logs );

		$email_log_model = new MailLogs();

		$res = $email_log_model->insert_email_logs( $email_logs );
	}

	/**
	 * Function that format the logs.
	 *
	 * @since 1.0.0
	 *
	 * @param  [type] $logs The logs.
	 */
	public function format_log_data( $logs ) {

		$email_logs = array();
		if ( ! empty( $logs ) ) {
			$email_logs = array(
				'site_id'       => '',
				'to'            => '',
				'from'          => '',
				'subject'       => '',
				'body'          => '',
				'header'        => '',
				'attachments'   => '',
				'status'        => true,
				'response'      => '',
				'extra'         => '',
				'retries'       => '',
				'resent_count'  => '',
				'source'        => '',
				'primary_id'    => 0,
				'ip_address'    => Helper::get_ip_address(),
				'error_message' => '',
			);
			foreach ( $logs as $key => $log ) {
				switch ( $key ) {
					case 'to':
						$email_logs['to'] = is_array( $logs['to'] ) ? implode( ', ', $logs['to'] ) : $logs['to'];
						break;
					case 'headers':
						$raw_headers = is_array( $logs['headers'] ) ? $logs['headers'] : array();

						// Extract from for dedicated field.
						foreach ( $raw_headers as $key => $header ) {
							if ( 'from' === strtolower( $key ) ) {
								$email_logs['from'] = $header;
								break;
							}
							if ( is_string( $header ) && strpos( $header, 'From:' ) === 0 ) {
								$email_logs['from'] = trim( str_replace( 'From:', '', $header ) );
								break;
							}
						}

						// Build JSON header for display.
						$header_data = array();
						if ( isset( $logs['_header_info'] ) ) {
							// smart_smtp_mail ran — use pre-parsed header info.
							$info = $logs['_header_info'];
							if ( ! empty( $info['content-type'] ) ) {
								$header_data['content-type'] = $info['content-type'];
							}
							if ( ! empty( $info['reply-to'] ) ) {
								$header_data['reply-to'] = array_values( array_map( function( $e ) {
									return array( 'email' => trim( $e ) );
								}, (array) $info['reply-to'] ) );
							}
							$header_data['cc']  = ! empty( $info['cc'] ) ? array_values( (array) $info['cc'] ) : array();
							$header_data['bcc'] = ! empty( $info['bcc'] ) ? array_values( (array) $info['bcc'] ) : array();
						} else {
							// Another plugin owns wp_mail — parse headers (both indexed strings and associative).
							$parsed = array(
								'content-type' => '',
								'reply-to'     => array(),
								'cc'           => array(),
								'bcc'          => array(),
							);
							foreach ( $raw_headers as $hkey => $hval ) {
								if ( is_string( $hkey ) ) {
									// Associative: key = header name, value = header content.
									$hname    = strtolower( trim( $hkey ) );
									$hcontent = trim( (string) $hval );
								} elseif ( is_string( $hval ) && strpos( $hval, ':' ) !== false ) {
									// Indexed: "Header-Name: value" string.
									list( $hname, $hcontent ) = explode( ':', $hval, 2 );
									$hname    = strtolower( trim( $hname ) );
									$hcontent = trim( $hcontent );
								} else {
									continue;
								}
								switch ( $hname ) {
									case 'content-type':
										$parsed['content-type'] = strtok( $hcontent, ';' );
										break;
									case 'reply-to':
										$parsed['reply-to'][] = array( 'email' => $hcontent );
										break;
									case 'cc':
										$parsed['cc'][] = $hcontent;
										break;
									case 'bcc':
										$parsed['bcc'][] = $hcontent;
										break;
								}
							}
							// If raw headers were empty (e.g. Fluent SMTP consumed them), fall back to filter-captured info.
							if ( empty( $parsed['content-type'] ) && empty( $parsed['reply-to'] ) && empty( $parsed['cc'] ) && empty( $parsed['bcc'] ) && ! is_null( self::$cached_header_info ) ) {
								$parsed = self::$cached_header_info;
							}

							if ( ! empty( $parsed['content-type'] ) ) {
								$header_data['content-type'] = $parsed['content-type'];
							}
							if ( ! empty( $parsed['reply-to'] ) ) {
								$header_data['reply-to'] = $parsed['reply-to'];
							}
							$header_data['cc']  = ! empty( $parsed['cc'] ) ? $parsed['cc'] : array();
							$header_data['bcc'] = ! empty( $parsed['bcc'] ) ? $parsed['bcc'] : array();
						}
						// Merge remaining non-standard custom headers (associative keys only).
						// Only allow valid HTTP header names (letters, digits, hyphens) to prevent
						// JSON-encoded strings passed as headers from leaking in as keys.
						foreach ( $raw_headers as $k => $v ) {
							if ( is_string( $k ) && is_string( $v ) && 'from' !== strtolower( $k ) && ! isset( $header_data[ $k ] ) && preg_match( '/^[A-Za-z][A-Za-z0-9\-]*$/', $k ) ) {
								$header_data[ $k ] = $v;
							}
						}
						$email_logs['header'] = ! empty( $header_data ) ? wp_json_encode( $header_data ) : '';
						self::$cached_header_info = null;
						break;
					case 'subject':
						$email_logs['subject'] = $logs['subject'];
						break;
					case 'message':
						$email_logs['body'] = maybe_serialize( $logs['message'] );
						break;
					case 'attachments':
						$email_logs['attachments'] = is_array( $logs['attachments'] ) ? implode( ',', $logs['attachments'] ) : '';
						break;
					case 'primary_id':
						$email_logs['primary_id'] = absint( $logs['primary_id'] );
						break;
				}
			}
		}

		return $email_logs;
	}

	/**
	 * Function to handle the error after failed.
	 *
	 * @since 1.0.0
	 *
	 * @param  [array] $wp_error The error data.
	 */
	public function on_email_failed( $wp_error ) {
		if ( self::$skip_logging ) {
			return;
		}
		if ( ! is_wp_error( $wp_error ) ) {
			return;
		}

		$mail_error_data    = $wp_error->get_error_data( 'wp_mail_failed' );
		$mail_error_message = $wp_error->get_error_message( 'wp_mail_failed' );

		if ( ! is_array( $mail_error_data ) ) {
			return;
		}

		if ( ! isset( $mail_error_data['to'] ) ) {
			return;
		}

		$mail_error_data                  = $this->format_log_data( $mail_error_data );
		$mail_error_data['status']        = false;
		$mail_error_data['error_message'] = $mail_error_message;

		$email_log_model = new MailLogs();

		$res = $email_log_model->insert_email_logs( $mail_error_data );

		return $res;
	}
}
