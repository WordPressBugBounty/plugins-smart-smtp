<?php
/**
 * ProviderController class.
 *
 * @since 1.0.0
 * @package  namespace SmartSMTP\Controller
 */

namespace SmartSMTP\Controller;

use SmartSMTP\Helper;
use SmartSMTP\Model\Provider;
use SmartSMTP\Model\MailLogs;
use SmartSMTP\Model\MailProviderModel;
use SmartSMTP\Services\Providers\Brevo\Mailer as BrevoMailer;
use SmartSMTP\Services\Providers\DefaultSmtp\Mailer as DefaultMailer;
use SmartSMTP\Services\Providers\Gmail\GmailSettings;
use SmartSMTP\Services\Providers\Other\Mailer as OtherMailer;
use SmartSMTP\Services\Providers\Gmail\Mailer as GmailMailer;


/**
 * ProviderController.
 *
 * @since 1.0.0
 */
class ProviderController {
	/**
	 * Provider object.
	 *
	 * @since 1.0.0
	 */
	protected $provider;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->provider = new Provider();
	}

	/**
	 * Get the selected provider.
	 *
	 * @since 1.0.0
	 */
	public function get_current_provider_type() {

		$provider_type = $this->provider->get_current_provider_type();

		return new \WP_REST_Response(
			$provider_type,
			200
		);
	}

	/**
	 * Get provider type.
	 *
	 * @param object|array $request The requested data.
	 * @since 1.0.0
	 */
	public function get_provider_type( $request ) {
		$conn = isset( $request['connection'] ) ? sanitize_text_field( $request['connection'] ) : '';

		if ( empty( $conn ) ) {

			return new \WP_REST_Response(
				array(
					'message' => esc_html__( 'Missing the connection type!', 'smart-smtp' ),
				),
				400
			);
		}

		$provider_type = $this->provider->get_provider_type( $conn );
		$provider_type = '' !== $provider_type ? $provider_type : 'default';

		return new \WP_REST_Response(
			$provider_type,
			200
		);
	}

	/**
	 * Function to save the config data.
	 *
	 * @since 1.0.0
	 * @param object|array $request The form data.
	 */
	public function save_provider_config( $request ) {
		$provider_config = isset( $request['provider_config'] ) ? $request['provider_config'] : array();

		if ( empty( $provider_config ) ) {

			return new \WP_REST_Response(
				array(
					'message' => esc_html__( 'Provider Configuration data is empty.', 'smart-smtp' ),
				),
				400
			);
		}

		$provider_type = isset( $provider_config['providerType'] ) ? sanitize_text_field( $provider_config['providerType'] ) : '';

		if ( empty( $provider_type ) ) {

			return new \WP_REST_Response(
				array(
					'message' => esc_html__( 'Missing provider type!!', 'smart-smtp' ),
				),
				400
			);
		}

		$conn = isset( $provider_config['connection'] ) ? $provider_config['connection'] : '';

		if ( empty( $conn ) ) {

			return new \WP_REST_Response(
				array(
					'message' => esc_html__( 'Missing connection type!!', 'smart-smtp' ),
				),
				400
			);
		}

		if ( isset( $provider_config['smtp_user_password'] ) ) {
			$provider_config['smtp_user_password'] = Helper::crypt_the_string( $provider_config['smtp_user_password'] );
		}

		$sanitized_form_data = Helper::sanitize_input_fields( $provider_config );
		$is_validate         = true;

		// Validate the brevo api before save.
		if ( isset( $sanitized_form_data['providerType'] ) && 'brevo' === $sanitized_form_data['providerType'] ) {
			if ( isset( $sanitized_form_data['smtp_api_key'] ) && ! empty( $sanitized_form_data['smtp_api_key'] ) ) {
				$res         = BrevoMailer::check_brevo_api_key( ( $sanitized_form_data['smtp_api_key'] ) );
				$is_validate = 200 === $res['code'];
			}

			if ( ! $is_validate ) {
				return new \WP_REST_Response(
					array(
						'message' => esc_html__( 'Api key authentication failed.', 'smart-smtp' ),
					),
					$res['code']
				);
			}
		}

		// validate the gmail authentication before save.
		if ( isset( $sanitized_form_data['providerType'] ) && 'gmail' === $sanitized_form_data['providerType'] ) {
			if ( isset( $sanitized_form_data['auth_token'] ) && ! empty( $sanitized_form_data['auth_token'] ) ) {
				// First time authentication verification
				if ( ! $this->is_mailer_complete( $conn, 'gmail' ) ) {

					$gmail = new GmailSettings(
						array(
							'client_id'     => $sanitized_form_data['client_id'],
							'client_secret' => $sanitized_form_data['client_secret'],
							'auth_token'    => $sanitized_form_data['auth_token'],
						)
					);

					$tokens = $gmail->verify_the_api_auth();

					if ( isset( $tokens['error'] ) ) {

						return new \WP_REST_Response(
							array(
								'message' => isset( $tokens['error_msg'] ) ? $tokens['error_msg'] : esc_html__( 'Could not authenticate to the Gmail.', 'smart-smtp' ),
							),
							'400'
						);
					}

					$sanitized_form_data = array_merge( $sanitized_form_data, $tokens );
				} else {
					$old_settings = $this->get_provider_config_by_conn( $conn, $sanitized_form_data['providerType'] );

					$sanitized_form_data['access_token']  = $old_settings['access_token'];
					$sanitized_form_data['refresh_token'] = $old_settings['refresh_token'];
				}
			} else {

				return new \WP_REST_Response(
					array(
						'message' => esc_html__( 'Before saving, please get the auth token for authentication.', 'smart-smtp' ),
					),
					'400'
				);
			}
		}
		if ( isset( $sanitized_form_data['smtp_is_active'] ) ) {
			$is_checked = $sanitized_form_data['smtp_is_active'];

			if ( $is_checked ) {
				$provider_type = $sanitized_form_data['providerType'];
				$res           = $this->set_active_provider_by_conn( $conn, $provider_type, $is_checked );
			}

			unset( $sanitized_form_data['smtp_is_active'] );
		}

		$res = $this->update_provider_config_by_conn( $conn, $sanitized_form_data );

		if ( $res ) {

			return new \WP_REST_Response(
				array(
					'message' => esc_html__( 'Mail provider setting saved successfully.', 'smart-smtp' ),
				),
				200
			);
		} else {

			return new \WP_REST_Response(
				array(
					'message' => esc_html__( 'Mail configuration is up to date.', 'smart-smtp' ),
				),
				200
			);
		}
	}

	/**
	 * Active provider function.
	 *
	 * @param string $conn The connection type.
	 * @param [type] $prov_type The provider type.
	 * @param [type] $is_checked The is checked or not.
	 * @return void
	 */
	protected function set_active_provider_by_conn( $conn, $prov_type, $is_checked ) {
		$option_name = '';

		if ( 'primary' === $conn ) {
			$option_name = 'smart_smtp_provider_type';
		} elseif ( 'fallback' === $conn ) {
			$option_name = 'smart_smtp_fallback_provider_type';
		}

		if ( ! $is_checked ) {
			$prov_type = '';
		}

		return $this->provider->set_active_provider( $option_name, $prov_type );
	}
	/**
	 * Update provider config by connection.
	 *
	 * @param [type] $conn The connectionn type.
	 * @param [type] $data The configuration data.
	 * @return void
	 */
	public function update_provider_config_by_conn( $conn, $data ) {
		$prov_type = isset( $data['providerType'] ) ? $data['providerType'] : '';

		if ( empty( $prov_type ) ) {
			$prov_type = $this->provider->get_provider_type( $conn );

			if ( '' === $prov_type ) {
				$prov_type = 'default';
			}
		}

		$option_name = '';

		if ( 'primary' === $conn ) {
			$option_name = 'smart_smtp_' . $prov_type . '_configuration';
		} elseif ( 'fallback' === $conn ) {
			$option_name = 'smart_smtp_' . $conn . '_' . $prov_type . '_configuration';
		}

		if ( empty( $option_name ) ) {
			return false;
		}

		return $this->provider->update_provider_config( $option_name, $data );
	}

	/**
	 * Function to get the config data.
	 *
	 * @since 1.0.0
	 * @param array $params  The extra params.
	 */
	public function get_provider_config( $params = array() ) {
		$provider_type = isset( $params['providerType'] ) ? sanitize_text_field( $params['providerType'] ) : '';
		$conn          = isset( $params['connection'] ) ? sanitize_text_field( $params['connection'] ) : '';

		$res = $this->get_provider_config_by_conn( $conn, $provider_type );

		$is_configured = array(
			'default' => $this->is_mailer_complete( $conn, 'default' ),
			'brevo'   => $this->is_mailer_complete( $conn, 'brevo' ),
			'other'   => $this->is_mailer_complete( $conn, 'other' ),
			'gmail'   => $this->is_mailer_complete( $conn, 'gmail' ),
		);

		$res = array_merge( $res, array( 'is_configured' => $is_configured ) );

		if ( 'fallback' === $conn ) {
			$res = array_merge( $res, array( 'is_fallback_enabled' => $this->provider->get_is_fallback_enabled() ) );
		}

		$res['smtp_is_active'] = $provider_type === $res['smtp_active_provider_type'];

		if ( isset( $res['smtp_user_password'] ) ) {
			$res['smtp_user_password'] = Helper::crypt_the_string( $res['smtp_user_password'], 'd' );
		}

		return new \WP_REST_Response(
			$res,
			200
		);
	}

	/**
	 * Get the provider config based on the connection.
	 *
	 * @param [type] $conn The connection type.
	 * @param [type] $prov_type The provider type.
	 * @return void
	 */
	public function get_provider_config_by_conn( $conn, $prov_type ) {

		if ( empty( $prov_type ) ) {
			$prov_type = $this->provider->get_provider_type( $conn );

			if ( '' === $prov_type ) {
				$prov_type = 'default';
			}
		}

		$option_name = '';

		if ( 'primary' === $conn ) {
			$option_name = 'smart_smtp_' . $prov_type . '_configuration';
		} elseif ( 'fallback' === $conn ) {
			$option_name = 'smart_smtp_' . $conn . '_' . $prov_type . '_configuration';
		}

		if ( empty( $option_name ) ) {
			$config = array();
		} else {
			$config = $this->provider->get_provider_config( $option_name );
		}

		$active_provider = $this->provider->get_provider_type( $conn );

		return array_merge(
			! empty( $config ) ? $config : array(),
			array(
				'smtp_active_provider_type' => $active_provider,
			),
			'fallback' === $conn ? array(
				'primary_active_prov_type' => $this->provider->get_provider_type( 'primary' ),
			) : array(),
		);
	}

	/**
	 * Func to check the mail is complete or not.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 * @param  string $conn The connection type.
	 * @param string $provider_type The provider type.
	 * @
	 */
	public function is_mailer_complete( $conn, $provider_type = '' ) {
		$res = false;

		if ( '' === $provider_type ) {
			$provider_type = $this->provider->get_provider_type( $conn );
			if ( '' === $provider_type || empty( $provider_type ) ) {
				return $res;
			}
		}

		switch ( $provider_type ) {
			case 'brevo':
				$res = BrevoMailer::is_mailer_complete( $conn );
				break;
			case 'gmail':
				$res = GmailMailer::is_mailer_complete( $conn );
				break;
			case 'other':
				$res = OtherMailer::is_mailer_complete( $conn );
				break;
			case 'default':
				$res = DefaultMailer::is_mailer_complete( $conn );
		}

		return $res;
	}

	/**
	 * Get the authentication and access token.
	 *
	 * @param object $request The requested data object.
	 * @return void
	 */
	public function get_provider_auth( $request ) {

		$provider_type = isset( $request['providerType'] ) ? sanitize_text_field( $request['providerType'] ) : '';

		if ( empty( $provider_type ) ) {

			return new \WP_REST_Response(
				array(
					'message' => esc_html__( 'Missing provider type!!', 'smart-smtp' ),
				),
				400
			);
		}

		$conn = isset( $request['connection'] ) ? sanitize_text_field( $request['connection'] ) : '';

		if ( empty( $conn ) ) {

			return new \WP_REST_Response(
				array(
					'message' => esc_html__( 'Missing connection type!!', 'smart-smtp' ),
				),
				400
			);
		}

		switch ( $provider_type ) {
			case 'gmail':
				$client_id     = isset( $request['client_id'] ) ? sanitize_text_field( $request['client_id'] ) : '';
				$client_secret = isset( $request['client_secret'] ) ? sanitize_text_field( $request['client_secret'] ) : '';
				$errors        = array();

				if ( empty( $client_id ) ) {
					return new \WP_REST_Response(
						array(
							'message' => esc_html__( 'Application Client ID is required.', 'smart-smtp' ),
						),
						400
					);
				}

				if ( empty( $client_secret ) ) {
					return new \WP_REST_Response(
						array(
							'message' => esc_html__( 'Application Client Secret is required.', 'smart-smtp' ),
						),
						400
					);
				}

				$gmail = new GmailSettings(
					array(
						'client_id'     => $client_id,
						'client_secret' => $client_secret,
						'conn'          => $conn,
					)
				);

				$auth_url = $gmail->get_auth_url();

				return new \WP_REST_Response(
					array(
						'url' => $auth_url,
					),
					200
				);

				break;
			default:
				break;
		}
	}

	/**
	 * remove the google authentication.
	 *
	 * @param object $request The requested data object.
	 * @return void
	 */
	public function remove_provider_auth( $request ) {

		if ( ! isset( $request['source'] ) || empty( $request['source'] ) ) {

			return new \WP_REST_Response(
				array(
					'message' => esc_html__( 'Source is missing.', 'smart-smtp' ),
				),
				400
			);
		}

		$provider_type = isset( $request['providerType'] ) ? sanitize_text_field( $request['providerType'] ) : '';

		if ( empty( $provider_type ) ) {

			return new \WP_REST_Response(
				array(
					'message' => esc_html__( 'Missing provider type!!', 'smart-smtp' ),
				),
				400
			);
		}

		$conn = isset( $request['connection'] ) ? sanitize_text_field( $request['connection'] ) : '';

		if ( empty( $conn ) ) {

			return new \WP_REST_Response(
				array(
					'message' => esc_html__( 'Missing connection type!!', 'smart-smtp' ),
				),
				400
			);
		}

		switch ( $provider_type ) {
			case 'gmail':
				$source = isset( $request['source'] ) ? sanitize_text_field( $request['source'] ) : '';
				$errors = array();

				if ( 'smart-smtp-edit' !== $source ) {
					return new \WP_REST_Response(
						array(
							'message' => esc_html__( 'Request from invalid source.', 'smart-smtp' ),
						),
						400
					);
				}
				$settings = $this->get_provider_config_by_conn( $conn, $provider_type );

				$gmail    = new GmailSettings( array_merge( $settings, array( 'conn' => $conn ) ) );
				$client   = $gmail->get_client();
				$response = $gmail->revokeAuth( $client->getAccessToken() );

				if ( ! is_wp_error( $response ) ) {
					$settings['access_token']  = '';
					$settings['auth_token']    = '';
					$settings['refresh_token'] = '';

					$res = $this->update_provider_config_by_conn( $conn, $settings );
					return new \WP_REST_Response(
						array(
							'message' => esc_html__( 'Authentication removed successfully!!', 'smart-smtp' ),
						),
						200
					);
				} else {

					return new \WP_REST_Response(
						array(
							'message' => esc_html__( $response->get_error_message(), 'smart-smtp' ),
						),
						$response->get_error_code()
					);
				}

				break;
			default:
				break;
		}
	}
	/**
	 * Get fallback enabled.
	 *
	 * @return void
	 */
	public function get_is_fallback_enabled() {
		return $this->provider->get_is_fallback_enabled();
	}
	/**
	 * Save the enable fallback.
	 *
	 * @param object $request The requested data object.
	 */
	public function save_is_fallback_enabled( $request ) {
		$is_fallback_enabled = isset( $request['isFallbackEnalbed'] ) ? sanitize_text_field( $request['isFallbackEnalbed'] ) : '';
		$res                 = $this->provider->save_is_fallback_enabled( $is_fallback_enabled );

		return new \WP_REST_Response(
			array(
				'message' => esc_html__( 'Saved successfully!!', 'smart-smtp' ),
			),
			200
		);
	}
}
