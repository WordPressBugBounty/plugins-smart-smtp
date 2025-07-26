<?php
/**
 * SmartSMTP AdminController class.
 *
 * @package  namespace SmartSMTP\Controller\Admin\MenuController
 *
 * @since 1.0.2
 */

namespace SmartSMTP\Controller\Admin;

use SmartSMTP\Helper;
use SmartSMTP\Traits\Singleton;

/**
 * MenusController class for Wpeverest stmp.
 *
 * @since 1.0.2
 */
class AdminController {

	use Singleton;

	/**
	 * Constructor.
	 *
	 * @since 1.0.2
	 */
	protected function __construct() {
		add_action( 'template_redirect', array( $this, 'gmail_token_page' ) );
	}

	/**
	 * Google Gmail Verification Token Display Page.
	 *
	 * @since 1.0.2
	 *
	 * @return void
	 */
	public function gmail_token_page() {

		if ( ! empty( $_GET['code'] ) && ! empty( $_GET['scope'] ) && 'https://www.googleapis.com/auth/gmail.compose' === $_GET['scope'] ) {
			wp_head();
			?>
			<style>
				.smart_smtp_google_calendar_token input{
					width:100%;
				}
			</style>
			<div class="smart_smtp_google_calendar_token" id="smart_smtp_google_calendar_token" style="width:80%; margin:auto; margin-top: 80px;">
				<p><h3>Your Authentication Token is: </h3></p>
				<br/>
				<p><input type="text" readonly value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['code'] ) ) ); ?>" /></p>
			</div>
			<?php
			wp_footer();
			exit();
		}
	}
}
