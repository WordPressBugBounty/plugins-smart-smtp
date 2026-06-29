<?php
/**
 * GoogleConnectController class.
 *
 * Provides a dedicated WordPress admin page for connecting a Google account
 * to Smart SMTP via the secure PKCE OAuth2 flow.
 *
 * Flow overview:
 *  1. User opens the Google Connect admin page.
 *  2. Controller generates code_verifier, code_challenge, and state, stores
 *     them in a per-user transient (10-minute TTL).
 *  3. "Connect" button links to the ThemeGrill API /start endpoint which
 *     redirects the user's browser to Google's consent screen.
 *  4. Google → API → plugin callback URL.
 *  5. admin_init detects the callback, verifies state, exchanges the code
 *     directly with Google (PKCE — no client_secret needed), and saves the
 *     resulting tokens to wp_options.
 *
 * @package SmartSMTP\Controller\Admin
 * @since   1.1.3
 */

namespace SmartSMTP\Controller\Admin;

use SmartSMTP\Controller\ProviderController;
use SmartSMTP\Traits\Singleton;

defined( 'ABSPATH' ) || exit;

/**
 * GoogleConnectController class.
 *
 * @since 1.1.3
 */
class GoogleConnectController {

	use Singleton;

	/** Admin page slug. */
	const PAGE_SLUG = 'smart-smtp-google-connect';

	/** Transient key prefix — one transient per logged-in user. */
	const TRANSIENT_PREFIX = 'smart_smtp_pkce_';

	/** How long (seconds) PKCE state is valid. Must cover the Google consent round-trip. */
	const TRANSIENT_TTL = 600; // 10 minutes

	/** Google token endpoint. */
	const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';

	/** Valid connection slots. */
	const VALID_CONNS = array( 'primary', 'fallback' );

	/**
	 * Constructor — registers WordPress hooks.
	 *
	 * @since 1.1.3
	 */
	protected function __construct() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_callback' ) );
		add_action( 'admin_post_smart_smtp_google_disconnect', array( $this, 'handle_disconnect' ) );
	}

	/**
	 * Register a hidden page (no menu entry) so WordPress allows the OAuth callback URL.
	 *
	 * Using null as the parent slug registers the page without adding it to any menu.
	 *
	 * @since 1.1.3
	 */
	public function add_page() {
		add_submenu_page(
			null,
			'',
			'',
			'manage_options',
			self::PAGE_SLUG,
			'__return_null'
		);
	}

	/**
	 * Detect the OAuth callback on admin_init — before any output — so wp_safe_redirect() works.
	 *
	 * @since 1.1.3
	 */
	public function maybe_handle_callback() {
		if ( ! $this->is_our_page() ) {
			return;
		}
		if ( isset( $_GET['code'] ) || isset( $_GET['error'] ) ) {
			$this->handle_callback();
		}
	}

	// -------------------------------------------------------------------------
	// OAuth callback handler (admin_init)
	// -------------------------------------------------------------------------

	/**
	 * Detect and process the OAuth callback from the ThemeGrill API.
	 *
	 * Runs on admin_init so it fires before any output, allowing us to use
	 * wp_safe_redirect() cleanly (PRG pattern).
	 *
	 * @since 1.1.3
	 */
	public function handle_callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'smart-smtp' ) );
		}

		// ── Error returned by Google / API ────────────────────────────────────
		if ( isset( $_GET['error'] ) ) {
			$error = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			$this->redirect_with_notice( 'error', $error );
		}

		// ── Extract code, state, and client_secret ───────────────────────────
		$code          = isset( $_GET['code'] )          ? sanitize_text_field( wp_unslash( $_GET['code'] ) )          : '';
		$state         = isset( $_GET['state'] )         ? sanitize_text_field( wp_unslash( $_GET['state'] ) )         : '';
		$client_secret = isset( $_GET['client_secret'] ) ? sanitize_text_field( wp_unslash( $_GET['client_secret'] ) ) : '';
		$client_id     = isset( $_GET['client_id'] )     ? sanitize_text_field( wp_unslash( $_GET['client_id'] ) )     : '';

		if ( empty( $code ) || empty( $state ) || empty( $client_id ) || empty( $client_secret ) ) {
			$this->redirect_with_notice( 'error', __( 'Missing required parameters. Please try connecting again.', 'smart-smtp' ) );
		}

		// ── Retrieve saved PKCE data for this user ────────────────────────────
		$user_id       = get_current_user_id();
		$conn          = 'primary';
		$transient_key = '';

		// Derive the connection from the state prefix (most reliable — OAuth returns
		// state verbatim). Fall back to the redirect_back `conn` param, then to trying
		// both slots.
		$conn_from_state = '';
		if ( false !== strpos( $state, '_' ) ) {
			$prefix = substr( $state, 0, strpos( $state, '_' ) );
			if ( in_array( $prefix, self::VALID_CONNS, true ) ) {
				$conn_from_state = $prefix;
			}
		}

		$conn_param = isset( $_GET['conn'] ) ? sanitize_text_field( wp_unslash( $_GET['conn'] ) ) : '';

		if ( '' !== $conn_from_state ) {
			$candidates = array( $conn_from_state );
		} elseif ( in_array( $conn_param, self::VALID_CONNS, true ) ) {
			$candidates = array( $conn_param );
		} else {
			$candidates = self::VALID_CONNS;
		}

		foreach ( $candidates as $candidate ) {
			$key = self::TRANSIENT_PREFIX . $user_id . '_' . $candidate;
			$t   = get_transient( $key );
			if ( $t && is_array( $t ) && ! empty( $t['state'] ) && hash_equals( $t['state'], $state ) ) {
				$conn          = $candidate;
				$transient_key = $key;
				$transient     = $t;
				break;
			}
		}

		if ( empty( $transient_key ) ) {
			$this->redirect_with_notice( 'error', __( 'Session expired or not found. Please start the connection again.', 'smart-smtp' ) );
		}

		$code_verifier = $transient['code_verifier'];

		// Delete immediately — transient is single-use to prevent replay attacks.
		delete_transient( $transient_key );

		// ── Exchange code for tokens ──────────────────────────────────────────
		$token_response = $this->exchange_code( $code, $code_verifier, $client_id, $client_secret );

		if ( is_wp_error( $token_response ) ) {
			$this->redirect_with_notice( 'error', $token_response->get_error_message(), $conn );
		}

		// ── Persist tokens ────────────────────────────────────────────────────
		$this->save_tokens( $token_response, $client_secret, $conn );

		$this->redirect_with_notice( 'success', __( 'Google account connected successfully!', 'smart-smtp' ), $conn );
	}

	// -------------------------------------------------------------------------
	// Disconnect handler
	// -------------------------------------------------------------------------

	/**
	 * Clear stored tokens, effectively disconnecting the Google account.
	 *
	 * Triggered by a form POST to admin-post.php with action=smart_smtp_google_disconnect.
	 *
	 * @since 1.1.3
	 */
	public function handle_disconnect() {
		check_admin_referer( 'smart_smtp_google_disconnect' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'smart-smtp' ) );
		}

		$conn = isset( $_POST['connection'] ) ? sanitize_text_field( wp_unslash( $_POST['connection'] ) ) : 'primary';
		$conn = in_array( $conn, self::VALID_CONNS, true ) ? $conn : 'primary';

		$prov_ctrl = new ProviderController();
		$settings  = $prov_ctrl->get_provider_config_by_conn( $conn, 'gmail' );

		$settings['access_token']  = '';
		$settings['refresh_token'] = '';
		$settings['auth_token']    = '';
		$settings['client_id']     = '';
		$settings['client_secret'] = '';

		$prov_ctrl->update_provider_config_by_conn( $conn, $settings );

		$this->redirect_with_notice( 'success', __( 'Google account disconnected.', 'smart-smtp' ), $conn );
	}

	// -------------------------------------------------------------------------
	// Page rendering
	// -------------------------------------------------------------------------

	/**
	 * Render the Google Connect admin page.
	 *
	 * Prepares view variables and delegates all HTML output to the view template.
	 *
	 * @since 1.1.3
	 */
	// -------------------------------------------------------------------------
	// PKCE helpers
	// -------------------------------------------------------------------------

	/**
	 * Build the ThemeGrill API start URL, generating fresh PKCE params and
	 * saving them to a per-user transient.
	 *
	 * @since 1.1.3
	 *
	 * @return string Full URL to redirect the user's browser to.
	 */
	/**
	 * Return the OAuth start URL for the React app, or empty string if not applicable.
	 *
	 * @since 1.1.3
	 *
	 * @return string
	 */
	public function get_connect_url( string $conn = 'primary' ): string {
		if ( $this->is_connected( $conn ) || ! is_ssl() ) {
			return '';
		}
		return $this->build_start_url( $conn );
	}

	private function build_start_url( string $conn = 'primary' ): string {
		$user_id       = get_current_user_id();
		$transient_key = self::TRANSIENT_PREFIX . $user_id . '_' . $conn;
		$existing      = get_transient( $transient_key );

		// Reuse existing PKCE params if still valid — prevents overwriting the
		// transient on every React render, which would invalidate the code_challenge
		// already sent to Google on the previous call.
		if ( $existing && is_array( $existing ) && ! empty( $existing['code_verifier'] ) && ! empty( $existing['state'] ) ) {
			$code_verifier = $existing['code_verifier'];
			$state         = $existing['state'];
		} else {
			$code_verifier = $this->generate_code_verifier();
			// Prefix the connection onto the state. OAuth round-trips `state` verbatim
			// (Google → API → callback), so this is the most reliable way to know which
			// connection initiated the flow, regardless of how the API handles redirect_back.
			$state = $conn . '_' . bin2hex( random_bytes( 16 ) );

			set_transient(
				$transient_key,
				array(
					'code_verifier' => $code_verifier,
					'state'         => $state,
					'conn'          => $conn,
				),
				self::TRANSIENT_TTL
			);
		}

		$code_challenge = $this->generate_code_challenge( $code_verifier );

		// redirect_back must match the API allowlist: HTTPS + /wp-admin/admin.php prefix.
		// Include the connection so the callback can target the right slot deterministically.
		$redirect_back = add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'conn' => $conn,
			),
			admin_url( 'admin.php' )
		);

		$api_base = defined( 'SMART_SMTP_API_BASE_URL' ) ? SMART_SMTP_API_BASE_URL : 'https://breeder-antennae-freemason.ngrok-free.dev/smart-smtp';

		return add_query_arg(
			array(
				'site_url'       => rawurlencode( home_url() ),
				'redirect_back'  => rawurlencode( $redirect_back ),
				'state'          => rawurlencode( $state ),
				'code_challenge' => rawurlencode( $code_challenge ),
			),
			$api_base . '/auth/google/start'
		);
	}

	/**
	 * Generate a cryptographically random code_verifier for PKCE.
	 *
	 * RFC 7636 §4.1: 43–128 base64url characters; 32 random bytes → 43 chars.
	 *
	 * @since 1.1.3
	 *
	 * @return string URL-safe base64 string without padding.
	 */
	private function generate_code_verifier(): string {
		return rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
	}

	/**
	 * Derive the S256 code_challenge from a code_verifier.
	 *
	 * code_challenge = base64url( SHA-256( ASCII( code_verifier ) ) )
	 *
	 * @since 1.1.3
	 *
	 * @param string $verifier The code_verifier string.
	 * @return string
	 */
	private function generate_code_challenge( string $verifier ): string {
		return rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
	}

	// -------------------------------------------------------------------------
	// Token exchange
	// -------------------------------------------------------------------------

	/**
	 * Exchange the authorization code for access + refresh tokens.
	 *
	 * Pure PKCE — no client_secret is sent. Google validates the code_verifier
	 * against the code_challenge that was included in the authorization request.
	 *
	 * @since 1.1.3
	 *
	 * @param string $code          Authorization code from Google (via API callback).
	 * @param string $code_verifier The original random verifier saved in the transient.
	 * @return array|\WP_Error Token response array on success, WP_Error on failure.
	 */
	private function exchange_code( string $code, string $code_verifier, string $client_id, string $client_secret ) {
		// The redirect_uri MUST exactly match what was registered and used in the auth request.
		$redirect_uri = SMART_SMTP_API_BASE_URL . '/auth/google/callback';

		$body = array(
			'code'          => $code,
			'redirect_uri'  => $redirect_uri,
			'grant_type'    => 'authorization_code',
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'code_verifier' => $code_verifier,
		);

		$response = wp_remote_post(
			self::GOOGLE_TOKEN_URL,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			$message = isset( $body['error_description'] ) ? $body['error_description'] : $body['error'];
			return new \WP_Error( 'token_exchange_failed', $message );
		}

		if ( empty( $body['access_token'] ) || empty( $body['refresh_token'] ) ) {
			return new \WP_Error(
				'token_incomplete',
				__( 'Google returned an incomplete token response. Please try connecting again.', 'smart-smtp' )
			);
		}

		return $body;
	}

	// -------------------------------------------------------------------------
	// Token persistence
	// -------------------------------------------------------------------------

	/**
	 * Save the token response into the Gmail provider configuration.
	 *
	 * The access_token is stored as a JSON-encoded array (full token response)
	 * so it is compatible with google/apiclient's Google_Client::setAccessToken().
	 * The refresh_token is stored separately as a plain string.
	 *
	 * @since 1.1.3
	 *
	 * @param array $token_response Decoded JSON response from the Google token endpoint.
	 */
	private function save_tokens( array $token_response, string $client_secret = '', string $conn = 'primary' ): void {
		$prov_ctrl = new ProviderController();
		$settings  = $prov_ctrl->get_provider_config_by_conn( $conn, 'gmail' );

		// Preserve existing config and update token fields.
		$settings['providerType']  = 'gmail';
		$settings['access_token']  = wp_json_encode( $token_response );
		$settings['refresh_token'] = $token_response['refresh_token'];
		$settings['auth_token']    = ''; // clear legacy auth-code field

		// Store client_secret so Google_Client can refresh tokens later.
		if ( ! empty( $client_secret ) ) {
			$settings['client_secret'] = $client_secret;
		}

		$prov_ctrl->update_provider_config_by_conn( $conn, $settings );
	}

	// -------------------------------------------------------------------------
	// Utility helpers
	// -------------------------------------------------------------------------

	/**
	 * Whether a Google access + refresh token pair is already stored.
	 *
	 * @since 1.1.3
	 *
	 * @return bool
	 */
	private function is_connected( string $conn = 'primary' ): bool {
		$prov_ctrl = new ProviderController();
		return $prov_ctrl->is_mailer_complete( $conn, 'gmail' );
	}

	/**
	 * Whether the current request is for our admin page.
	 *
	 * @since 1.1.3
	 *
	 * @return bool
	 */
	private function is_our_page(): bool {
		return is_admin()
			&& isset( $_GET['page'] )
			&& self::PAGE_SLUG === sanitize_text_field( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Redirect back to the connect page with a one-time notice stored as a transient.
	 *
	 * Uses the PRG (Post-Redirect-Get) pattern so reloading the page does not
	 * re-process the callback.
	 *
	 * @since 1.1.3
	 *
	 * @param string $type    'success' or 'error'.
	 * @param string $message Human-readable message.
	 */
	private function redirect_with_notice( string $type, string $message, string $conn = 'primary' ): void {
		set_transient(
			'smart_smtp_notice_' . get_current_user_id(),
			array( 'type' => $type, 'message' => $message ),
			60
		);

		$hash = 'success' === $type ? '#/' . $conn . '-connection' : '';
		wp_safe_redirect( admin_url( 'admin.php?page=smart-smtp' ) . $hash );
		exit;
	}

	/**
	 * Retrieve and immediately delete the one-time notice transient.
	 *
	 * @since 1.1.3
	 *
	 * @return array|null Array with 'type' and 'message' keys, or null if none.
	 */
	private function get_notice(): ?array {
		$key    = 'smart_smtp_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( $notice ) {
			delete_transient( $key );
			return $notice;
		}
		return null;
	}
}
