<?php
/**
 * SmartSMTP FailedEmailNoticeController class.
 *
 * @package  namespace SmartSMTP\Controller\Admin\FailedEmailNoticeController
 *
 * @since 1.2.0
 */

namespace SmartSMTP\Controller\Admin;

use SmartSMTP\Traits\Singleton;

/**
 * Shows a WP admin notice and sends an email to the site admin when email delivery
 * has completely failed (both primary and fallback, if configured, have failed).
 *
 * @since 1.2.0
 */
class FailedEmailNoticeController {

	use Singleton;

	const TRIGGERED_OPTION  = 'smart_smtp_failed_emails_count';
	const DISMISSED_OPTION  = 'smart_smtp_failed_emails_dismissed_count';
	const LAST_SENT_OPTION  = 'smart_smtp_failure_notification_last_sent';
	const FAILURE_THRESHOLD = 5;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 */
	protected function __construct() {
		add_action( 'wp_mail_failed', array( $this, 'on_mail_failed' ) );
		add_action( 'wp_mail_succeeded', array( $this, 'on_mail_succeeded' ) );
		add_action( 'admin_notices', array( $this, 'show_failed_email_notice' ) );
		add_action( 'wp_ajax_smart_smtp_dismiss_failed_email_notice', array( $this, 'dismiss_notice' ) );
		add_filter(
			'smart-smtp_ignore_hide_admin_notices',
			function ( $allowed ) {
				$allowed[] = 'show_failed_email_notice';
				return $allowed;
			}
		);
	}

	/**
	 * Fires on wp_mail_failed: increments the failure counter and maybe sends a notification email.
	 *
	 * @since 1.2.0
	 *
	 * @param \WP_Error $wp_error The error object passed by WordPress.
	 */
	public function on_mail_failed( $wp_error ) {
		$count = (int) get_option( self::TRIGGERED_OPTION, 0 ) + 1;
		update_option( self::TRIGGERED_OPTION, $count, false );

		$this->maybe_send_notification_email( $wp_error, $count );
	}

	/**
	 * Fires on wp_mail_succeeded: auto-dismisses the admin notice when email delivery resumes.
	 *
	 * @since 1.2.0
	 */
	public function on_mail_succeeded() {
		$triggered = (int) get_option( self::TRIGGERED_OPTION, 0 );
		update_option( self::DISMISSED_OPTION, $triggered, false );
	}

	/**
	 * Sends a notification email to the site admin after the failure threshold is crossed,
	 * then at most once per hour while failures persist. Uses PHP's native mail() to bypass
	 * Smart SMTP's wp_mail() override, ensuring delivery even when the configured SMTP is broken.
	 *
	 * @since 1.2.0
	 *
	 * @param \WP_Error $wp_error     The error object.
	 * @param int       $total_count  Current total failure count.
	 */
	private function maybe_send_notification_email( $wp_error, $total_count ) {
		if ( $total_count < self::FAILURE_THRESHOLD ) {
			return;
		}

		$last_sent = (int) get_option( self::LAST_SENT_OPTION, 0 );

		if ( $last_sent > 0 && ( time() - $last_sent ) < HOUR_IN_SECONDS ) {
			return;
		}

		update_option( self::LAST_SENT_OPTION, time(), false );

		$admin_email   = get_option( 'admin_email' );
		$site_name     = get_bloginfo( 'name' );
		$logs_url      = admin_url( 'admin.php?page=smart-smtp#/mail-logs' );
		$error_message = ( $wp_error instanceof \WP_Error ) ? $wp_error->get_error_message() : __( 'Unknown error', 'smart-smtp' );

		$subject = sprintf(
			'[%s] %s',
			$site_name,
			__( 'Smart SMTP: Email Delivery Failure Detected', 'smart-smtp' )
		);

		$body = sprintf(
			/* translators: 1: site URL, 2: failure count, 3: error message, 4: mail logs URL */
			__(
				"Your website (%1\$s) has encountered repeated email delivery failures.\n\n" .
				"Total failures recorded: %2\$d\n" .
				"Latest error: %3\$s\n\n" .
				"Please check your SMTP configuration in Smart SMTP.\n" .
				'View mail logs: %4$s',
				'smart-smtp'
			),
			home_url(),
			$total_count,
			$error_message,
			$logs_url
		);

		$headers = sprintf( 'From: %s <%s>', $site_name, $admin_email );

		// Use PHP's native mail() to bypass Smart SMTP's wp_mail() override.
		// This ensures the notification is sent even when the configured SMTP is broken.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		if ( ! mail( $admin_email, $subject, $body, $headers ) ) {
			error_log( 'Smart SMTP: Failed to send failure notification email via PHP mail(). Admin notice is still visible in WP admin.' );
		}
	}

	/**
	 * Render the admin notice when unacknowledged email failures exist.
	 *
	 * @since 1.2.0
	 */
	public function show_failed_email_notice() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'toplevel_page_smart-smtp' ), true ) ) {
			return;
		}

		$primary_provider = get_option( 'smart_smtp_provider_type', '' );
		if ( empty( $primary_provider ) ) {
			return;
		}

		$triggered = (int) get_option( self::TRIGGERED_OPTION, 0 );
		$dismissed = (int) get_option( self::DISMISSED_OPTION, 0 );
		$count     = $triggered - $dismissed;

		if ( $count <= 0 ) {
			return;
		}

		$logs_url = admin_url( 'admin.php?page=smart-smtp#/mail-logs' );
		$nonce    = wp_create_nonce( 'smart_smtp_dismiss_failed_email_notice' );
		?>
		<div class="notice is-dismissible smart-smtp-failed-email-notice" style="border-left-color:#C01E1E;">
			<p>
				<?php
				printf(
					wp_kses(
						/* translators: %1$d: number of failed emails, %2$s: URL to mail logs */
						_n( 'Smart SMTP: <strong>%1$d</strong> email failed to send. Please check your primary SMTP configuration. <a href="%2$s">View Mail Logs &rarr;</a>', 'Smart SMTP: <strong>%1$d</strong> emails failed to send. Please check your primary SMTP configuration. <a href="%2$s">View Mail Logs &rarr;</a>', $count, 'smart-smtp' ),
						array(
							'strong' => array(),
							'a'      => array( 'href' => array() ),
						)
					),
					absint( $count ),
					esc_url( $logs_url )
				);
				?>
			</p>
			<button
				type="button"
				class="notice-dismiss"
				onclick="(function(btn){btn.disabled=true;fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=smart_smtp_dismiss_failed_email_notice&nonce=<?php echo esc_js( $nonce ); ?>'}).then(function(){btn.closest('.notice').remove();});})(this)"
			>
				<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'smart-smtp' ); ?></span>
			</button>
		</div>
		<?php
	}

	/**
	 * AJAX handler to dismiss the failed email notice.
	 * Sets dismissed_count = triggered_count so the notice hides until new failures occur.
	 *
	 * @since 1.2.0
	 */
	public function dismiss_notice() {
		check_ajax_referer( 'smart_smtp_dismiss_failed_email_notice', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}

		$triggered = (int) get_option( self::TRIGGERED_OPTION, 0 );
		update_option( self::DISMISSED_OPTION, $triggered, false );

		wp_send_json_success();
	}
}
