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
	 * Get the client.
	 *
	 * @since 1.0.2
	 * @return void
	 */
	public function get_client( $is_ajax = false ) {

		$client = new \Google_Client();
		$client->setClientId( $this->client_id );
		$client->setClientSecret( $this->client_secret );
		$client->setRedirectUri( trailingslashit( home_url() ) );
		$client->setApplicationName( 'SmartSMTP - Gmail API v' );
		$client->setScopes( array( \Google_Service_Gmail::GMAIL_COMPOSE ) );
		$client->setAccessType( 'offline' );
		$client->setPrompt( 'select_account consent' );

		/**
		 * Filter to add the custom options.
		 *
		 * @param mixed $client The client.
		 * @since 1.0.2
		 */
		$client = apply_filters( 'smart_smtp_google_gmail_auth_get_client_custom_options', $client );

		if ( $is_ajax ) {
			return $client;
		}

		if (
			! empty( $this->auth_token )
			&& $this->is_auth_required()
		) {
			try {
				// Exchange authorization code for an access token.
				$accessToken = $client->fetchAccessTokenWithAuthCode( $this->auth_token );
			} catch ( \Exception $e ) {
				$accessToken['error'] = $e->getMessage();
			}

			if ( ! empty( $accessToken['error'] ) ) {
				return $client;
			}

			// Update access and refresh token.
			$prov_ctrlr = new ProviderController();
			$settings   = $prov_ctrlr->get_provider_config_by_conn( $this->conn, 'gmail' );

			$settings['access_token']  = $client->getAccessToken();
			$settings['refresh_token'] = $client->getRefreshToken();

			$res = $prov_ctrlr->update_provider_config_by_conn( $this->conn, $settings );
		}

		// Set the access token used for requests.
		if ( ! empty( $this->access_token ) ) {
			$client->setAccessToken( $this->access_token );
		}

		// Refresh the token if it's expired.
		if ( $client->isAccessTokenExpired() ) {
			$refresh = $client->getRefreshToken();
			if ( empty( $refresh ) && isset( $this->refresh_token ) ) {
				$refresh = $this->refresh_token;
			}

			if ( ! empty( $refresh ) ) {
				try {
					// Refresh the token if possible, else fetch a new one.
					$refreshToken = $client->fetchAccessTokenWithRefreshToken( $refresh );
				} catch ( \Exception $e ) {
					$refreshToken['error'] = $e->getMessage();
				}

				if ( ! empty( $refreshToken['error'] ) ) {
					return $client;
				}

				// Update access and refresh token.
				$prov_ctrlr = new ProviderController();
				$settings   = $prov_ctrlr->get_provider_config_by_conn( $this->conn, 'gmail' );

				$settings['access_token']  = $client->getAccessToken();
				$settings['refresh_token'] = $client->getRefreshToken();

				$res = $prov_ctrlr->update_provider_config_by_conn( $this->conn, $settings );
			}
		}

		return $client;
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
