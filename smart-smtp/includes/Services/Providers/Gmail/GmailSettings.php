<?php
/**
 * Gmail setting.
 *
 * @since 1.0.2
 * @package Gmail settings.
 */

namespace SmartSMTP\Services\Providers\Gmail;

use SmartSMTP\Controller\ProviderController;

defined( 'ABSPATH' ) || exit;

/**
 * Gmail Settings class.
 *
 * @since 1.0.2
 */
class GmailSettings {
	/**
	 * Client.
	 *
	 * @var object
	 */
	public $client;

	/**
	 * GoogleCalendar integration client id.
	 *
	 * @var string
	 */
	public $client_id;

	/**
	 * GoogleCalendar integration client Secret.
	 *
	 * @var string
	 */
	public $client_secret;

	/**
	 * GoogleCalendar integration refresh token.
	 *
	 * @var string
	 */
	public $refresh_token;

	/**
	 * GoogleCalendar integration authorization code.
	 *
	 * @var string
	 */
	public $auth_token;

	/**
	 * GoogleCalendar access token.
	 *
	 * @var string
	 */
	public $access_token;

	/**
	 * Integration object associated with GoogleCalendar.
	 *
	 * @var object
	 */
	public $integration;

	/**
	 * Status of the GoogleCalendar account integration.
	 *
	 * @var string
	 */
	public $account_status;

	/**
	 * The connection type.
	 *
	 * @var [string] $conn The connection type.
	 */
	public $conn;

	/**
	 * Gmail
	 *
	 * @param [type] $settings
	 */
	public function __construct( $settings ) {
		$this->client_id     = isset( $settings['client_id'] ) ? $settings['client_id'] : '';
		$this->client_secret = isset( $settings['client_secret'] ) ? $settings['client_secret'] : '';
		$this->access_token  = isset( $settings['access_token'] ) ? $settings['access_token'] : '';
		$this->auth_token    = isset( $settings['auth_token'] ) ? $settings['auth_token'] : '';
		$this->refresh_token = isset( $settings['refresh_token'] ) ? $settings['refresh_token'] : '';
		$this->conn          = isset( $settings['conn'] ) ? $settings['conn'] : '';

		$this->account_status = $this->is_auth_required() ? 'disconnected' : 'connected';
	}

	/**
	 * Is authentication required?
	 *
	 * @return bool
	 */
	public function is_auth_required() {
		return empty( $this->access_token ) || empty( $this->refresh_token );
	}

	/**
	 * Detect whether the stored token format indicates PKCE / one-click OAuth.
	 *
	 * In one-click, we store the full token response JSON-encoded in access_token,
	 * whereas the manual auth flow typically stores a serialized array.
	 *
	 * @return bool
	 */
	private function is_pkce_one_click_token(): bool {
		if ( empty( $this->access_token ) ) {
			return false;
		}

		if ( ! is_string( $this->access_token ) ) {
			return false;
		}

		$raw = trim( $this->access_token );
		if ( '' === $raw || '{' !== substr( $raw, 0, 1 ) ) {
			return false;
		}

		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) && isset( $decoded['access_token'] );
	}

	/**
	 * Get the client.
	 *
	 * @since 1.0.2
	 * @return \Google_Client
	 */
	public function get_client( $is_ajax = false ) {

		$client_id     = trim( (string) $this->client_id );
		$client_secret = trim( (string) ( ! empty( $this->client_secret ) ? $this->client_secret : ( defined( 'SMART_SMTP_GOOGLE_CLIENT_SECRET' ) ? constant( 'SMART_SMTP_GOOGLE_CLIENT_SECRET' ) : '' ) ) );

		$client = new \Google_Client();
		$client->setClientId( $client_id );
		$client->setClientSecret( $client_secret );
		$client->setRedirectUri( trailingslashit( home_url() ) );
		$client->setApplicationName( 'SmartSMTP - Gmail API v' );
		$client->setScopes( array( \Google_Service_Gmail::GMAIL_COMPOSE ) );
		$client->setAccessType( 'offline' );
		$client->setPrompt( 'select_account consent' );

		/** @since 1.0.2 */
		$client = apply_filters( 'smart_smtp_google_gmail_auth_get_client_custom_options', $client );

		if ( $is_ajax ) {
			return $client;
		}

		// ── PKCE / one-click flow ──────────────────────────────────────────────
		// For one-click OAuth the stored access_token is a JSON-encoded token
		// response (no client_secret needed). We manage expiry ourselves so that
		// Google_Client never tries to instantiate UserRefreshCredentials, which
		// throws "json key is missing the client_secret field" when client_secret
		// is empty.
		if ( $this->is_pkce_one_click_token() ) {
			$token_data = json_decode( $this->access_token, true );

			// Ensure google/apiclient knows when the token was issued so it
			// can calculate expiry correctly.
			if ( is_array( $token_data ) && ! isset( $token_data['created'] ) ) {
				$token_data['created'] = time();
			}

			// Check expiry without touching the library's credential stack.
			$is_expired = false;
			if ( is_array( $token_data ) ) {
				$created    = isset( $token_data['created'] ) ? (int) $token_data['created'] : 0;
				$expires_in = isset( $token_data['expires_in'] ) ? (int) $token_data['expires_in'] : 0;
				if ( $created && $expires_in ) {
					// 30-second buffer to account for clock skew.
					$is_expired = ( $created + $expires_in - 30 ) < time();
				}
			}

			if ( $is_expired && ! empty( $this->refresh_token ) ) {
				$refreshed = $this->pkce_refresh_token( $client_id, $this->refresh_token );

				if ( empty( $refreshed['error'] ) ) {
					if ( ! isset( $refreshed['created'] ) ) {
						$refreshed['created'] = time();
					}

					$prov_ctrlr              = new ProviderController();
					$settings                = $prov_ctrlr->get_provider_config_by_conn( $this->conn, 'gmail' );
					$settings['access_token']  = wp_json_encode( $refreshed );
					$settings['refresh_token'] = isset( $refreshed['refresh_token'] ) ? $refreshed['refresh_token'] : $this->refresh_token;
					$prov_ctrlr->update_provider_config_by_conn( $this->conn, $settings );

					$token_data = $refreshed;
				}
			}

			// Hand the (possibly refreshed) token to the client and return —
			// never call isAccessTokenExpired() or fetchAccessTokenWithRefreshToken()
			// so UserRefreshCredentials is never instantiated.
			if ( is_array( $token_data ) ) {
				$client->setAccessToken( $token_data );
			}

			return $client;
		}

		// ── Legacy manual OAuth flow ───────────────────────────────────────────
		if ( ! empty( $this->auth_token ) && $this->is_auth_required() ) {
			try {
				$accessToken = $client->fetchAccessTokenWithAuthCode( $this->auth_token );
			} catch ( \Exception $e ) {
				$accessToken = array( 'error' => $e->getMessage() );
			}

			if ( ! empty( $accessToken['error'] ) ) {
				return $client;
			}

			$prov_ctrlr              = new ProviderController();
			$settings                = $prov_ctrlr->get_provider_config_by_conn( $this->conn, 'gmail' );
			$settings['access_token']  = $client->getAccessToken();
			$settings['refresh_token'] = $client->getRefreshToken();
			$prov_ctrlr->update_provider_config_by_conn( $this->conn, $settings );
		}

		if ( ! empty( $this->access_token ) ) {
			$client->setAccessToken( $this->access_token );
		}

		if ( $client->isAccessTokenExpired() ) {
			$refresh = $client->getRefreshToken() ?: $this->refresh_token;

			if ( ! empty( $refresh ) ) {
				try {
					$refreshToken = $client->fetchAccessTokenWithRefreshToken( $refresh );
				} catch ( \Exception $e ) {
					$refreshToken = array( 'error' => $e->getMessage() );
				}

				if ( ! empty( $refreshToken['error'] ) ) {
					return $client;
				}

				$prov_ctrlr              = new ProviderController();
				$settings                = $prov_ctrlr->get_provider_config_by_conn( $this->conn, 'gmail' );
				$settings['access_token']  = $client->getAccessToken();
				$settings['refresh_token'] = $client->getRefreshToken();
				$prov_ctrlr->update_provider_config_by_conn( $this->conn, $settings );
			}
		}

		return $client;
	}

	/**
	 * Refresh an access token for a PKCE / public client (no client_secret).
	 *
	 * The google/apiclient library cannot handle this case because it strips
	 * empty values with array_filter() before passing creds to UserRefreshCredentials.
	 * This method calls Google's token endpoint directly via wp_remote_post.
	 *
	 * @param string $client_id    The OAuth client ID.
	 * @param string $refresh_token The refresh token.
	 * @return array Token array on success, array with 'error' key on failure.
	 */
	private function pkce_refresh_token( string $client_id, string $refresh_token ): array {
		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => array(
					'client_id'     => $client_id,
					'refresh_token' => $refresh_token,
					'grant_type'    => 'refresh_token',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'error' => $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			$msg = isset( $body['error_description'] ) ? $body['error_description'] : $body['error'];
			return array( 'error' => $msg );
		}

		// Ensure created field exists so google/apiclient can check expiry.
		if ( ! isset( $body['created'] ) ) {
			$body['created'] = time();
		}

		return $body;
	}

	/**
	 * Get auth url.
	 *
	 * @return void
	 */
	public function get_auth_url() {

		$client = $this->get_client( true );

		return filter_var( $client->createAuthUrl(), FILTER_SANITIZE_URL );
	}

	/**
	 * Revoke the authentication.
	 *
	 * @since string $tokens The access token.
	 */
	public function revokeAuth( $tokens ) {

		$revoke_url = 'https://accounts.google.com/o/oauth2/revoke?token=' . urlencode( $tokens['access_token'] );

		$response = wp_remote_get( $revoke_url );
		return $response;
	}


	/**
	 * Google Gmail API authenticate.
	 *
	 * @since 1.0.2
	 * @param array $posted_data Posted client credentials.
	 */
	public function verify_the_api_auth() {

		// Is valid auth to proceed?
		if ( empty( $this->auth_token ) ) {

			return array(
				'error'     => esc_html__( 'Could not authenticate to the gmail.', 'smart-smtp' ),
				'error_msg' => esc_html__( 'Please provide the correct Google access code.', 'smart-smtp' ),
			);
		}

		$client      = $this->get_client( true );
		$accessToken = $client->fetchAccessTokenWithAuthCode( $this->auth_token );

		if ( isset( $accessToken['access_token'] ) ) {

			return array(
				'access_token'  => $client->getAccessToken(),
				'refresh_token' => $client->getRefreshToken(),
			);

		} else {

			return array(
				'error'     => esc_html__( 'Could not authenticate to the Gmail.', 'smart-smtp' ),
				'error_msg' => esc_html( $accessToken['error_description'] ),
			);
		}
	}
}
