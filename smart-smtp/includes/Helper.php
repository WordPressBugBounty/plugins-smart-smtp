<?php
/**
 * SmartSMTP Helper class.
 *
 * @package  namespace SmartSMTP\Helper
 *
 * @since 1.0.0
 */

namespace SmartSMTP;

/**
 * Helper methods for Wpeverest stmp.
 *
 * @since 1.0.0
 */
class Helper {
	/**
	 * Function to get the plugin url.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $path Path.
	 */
	public static function plugin_url( $path = '/' ) {
		return untrailingslashit( plugins_url( $path, SMART_SMTP_PLUGIN_FILE ) );
	}
	/**
	 * Function to get the mail config data.
	 *
	 * @since 1.0.0
	 */
	public static function get_provider_config() {
		$mail_config = get_option( 'smart_smtp_mail_configuration', array() );

		return $mail_config;
	}

	/**
	 * Get current user IP Address.
	 *
	 * @since
	 *
	 * @return string
	 */
	public static function get_ip_address() {
		if ( isset( $_SERVER['HTTP_X_REAL_IP'] ) ) { // WPCS: input var ok, CSRF ok.
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );  // WPCS: input var ok, CSRF ok.
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) { // WPCS: input var ok, CSRF ok.
			// Proxy servers can send through this header like this: X-Forwarded-For: client1, proxy1, proxy2
			// Make sure we always only send through the first IP in the list which should always be the client IP.
			return (string) rest_is_ip_address( trim( current( preg_split( '/[,:]/', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) ) ) ); // WPCS: input var ok, CSRF ok.
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) { // @codingStandardsIgnoreLine
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ); // @codingStandardsIgnoreLine
		}
		return '';
	}
	/**
	 * Encrypt and decrypt the string.
	 *
	 * @since 1.0.0
	 *
	 * @param  [type] $string The string value.
	 * @param  string $action The action type.
	 */
	public static function crypt_the_string( $string, $action = 'e' ) {
		$secret_key = get_option( 'smart_smtp_secret_key' );
		$secret_iv  = get_option( 'smart_smtp_secret_iv' );

		if ( empty( $secret_key ) || empty( $secret_iv ) ) {
			$secret_key = self::generate_random_key();
			$secret_iv  = self::generate_random_key();
			update_option( 'smart_smtp_secret_key', $secret_key );
			update_option( 'smart_smtp_secret_iv', $secret_iv );
		}

			$output         = false;
			$encrypt_method = 'AES-256-CBC';
			$key            = hash( 'sha256', $secret_key );
			$iv             = substr( hash( 'sha256', $secret_iv ), 0, 16 );

		if ( 'e' === $action ) {
			if ( function_exists( 'openssl_encrypt' ) ) {
				$output = base64_encode( openssl_encrypt( $string, $encrypt_method, $key, 0, $iv ) );
			} else {
				$output = base64_encode( $string );
			}
		} elseif ( 'd' === $action ) {
			if ( function_exists( 'openssl_decrypt' ) ) {
				$output = openssl_decrypt( base64_decode( $string ), $encrypt_method, $key, 0, $iv );
			} else {
				$output = base64_decode( $string );
			}
		}

		return $output;
	}

	/**
	 * Function to generate the random key.
	 *
	 * @since 3.0.2.1
	 */
	public static function generate_random_key() {
		$length              = 32;
		$allow_special_chars = true;
		$key                 = wp_generate_password( $length, $allow_special_chars );
		return $key;
	}

	/**
	 * Sanitize the input fields.
	 *
	 * @since 0
	 * @param arry $form_data The form data.
	 */
	public static function sanitize_input_fields( $form_data ) {
		if ( empty( $form_data ) ) {
			return $form_data;
		}

		$sanitized_data = array_map(
			function ( $key, $value ) {
				switch ( $key ) {
					case 'smtp_from_email_address':
					case 'smtp_reply_to_email_address':
					case 'smtp_test_send_to':
						return sanitize_email( $value );
					case 'smtp_authentication':
					case 'smtp_test_html':
						return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
					default:
						return sanitize_text_field( $value );
				}
			},
			array_keys( $form_data ),
			$form_data
		);

		$sanitized_data = array_combine( array_keys( $form_data ), $sanitized_data );

		return apply_filters( 'smart_smtp_form_data_after_sanitization', $sanitized_data, $form_data );
	}

	/**
	 * Return development.
	 *
	 * @since 1.0.0
	 *
	 * @return boolean
	 */
	public static function is_development() {
		return defined( 'SMART_SMTP_DEVELOPMENT' ) && SMART_SMTP_DEVELOPMENT;
	}

	/**
	 * Return Production.
	 *
	 * @since 1.0.0
	 *
	 * @return boolean
	 */
	public static function is_production() {
		return ! defined( 'SMART_SMTP_DEVELOPMENT' ) || ! SMART_SMTP_DEVELOPMENT;
	}
}
