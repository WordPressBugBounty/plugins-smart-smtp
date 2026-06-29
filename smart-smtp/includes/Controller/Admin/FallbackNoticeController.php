<?php
/**
 * SmartSMTP FallbackNoticeController class.
 *
 * @package  namespace SmartSMTP\Controller\Admin\FallbackNoticeController
 *
 * @since 1.1.4
 */

namespace SmartSMTP\Controller\Admin;

use SmartSMTP\Traits\Singleton;

/**
 * Shows a WP admin notice when the fallback SMTP connection has been triggered,
 * with a counter of how many emails were routed through fallback since last dismissal.
 *
 * @since 1.1.4
 */
class FallbackNoticeController {

	use Singleton;

	const TRIGGERED_OPTION = 'smart_smtp_fallback_triggered_count';
	const DISMISSED_OPTION = 'smart_smtp_fallback_dismissed_count';

	/**
	 * Constructor.
	 *
	 * @since 1.1.4
	 */
	protected function __construct() {
		add_action( 'admin_notices', array( $this, 'show_fallback_notice' ) );
		add_action( 'wp_ajax_smart_smtp_dismiss_fallback_notice', array( $this, 'dismiss_notice' ) );
		add_filter(
			'smart-smtp_ignore_hide_admin_notices',
			function ( $allowed ) {
				$allowed[] = 'show_fallback_notice';
				return $allowed;
			}
		);
	}

	/**
	 * Render the admin notice when fallback emails have been sent since last dismissal.
	 *
	 * @since 1.1.4
	 */
	public function show_fallback_notice() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'toplevel_page_smart-smtp' ), true ) ) {
			return;
		}

		$triggered = (int) get_option( self::TRIGGERED_OPTION, 0 );
		$dismissed = (int) get_option( self::DISMISSED_OPTION, 0 );
		$count     = $triggered - $dismissed;

		if ( $count <= 0 ) {
			return;
		}

		$logs_url = admin_url( 'admin.php?page=smart-smtp#/mail-logs' );
		$nonce    = wp_create_nonce( 'smart_smtp_dismiss_fallback_notice' );
		?>
		<div class="notice is-dismissible smart-smtp-fallback-notice" style="border-left-color:#C01E1E;">
			<p>
				<?php
				printf(
					wp_kses(
						/* translators: %1$d: number of fallback mails, %2$s: URL to mail logs */
						_n( 'Smart SMTP: Fallback connection triggered for <strong>%1$d</strong> email. Please monitor your primary SMTP connections. <a href="%2$s">View Mail Logs &rarr;</a>', 'Smart SMTP: Fallback connection triggered for <strong>%1$d</strong> emails. Please monitor your primary SMTP connections. <a href="%2$s">View Mail Logs &rarr;</a>', $count, 'smart-smtp' ),
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
				onclick="(function(btn){btn.disabled=true;fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=smart_smtp_dismiss_fallback_notice&nonce=<?php echo esc_js( $nonce ); ?>'}).then(function(){btn.closest('.notice').remove();});})(this)"
			>
				<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'smart-smtp' ); ?></span>
			</button>
		</div>
		<?php
	}

	/**
	 * AJAX handler to dismiss the fallback notice.
	 * Sets dismissed_count = triggered_count so the notice hides until new fallbacks occur.
	 *
	 * @since 1.1.4
	 */
	public function dismiss_notice() {
		check_ajax_referer( 'smart_smtp_dismiss_fallback_notice', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}

		$triggered = (int) get_option( self::TRIGGERED_OPTION, 0 );
		update_option( self::DISMISSED_OPTION, $triggered, false );

		wp_send_json_success();
	}
}
