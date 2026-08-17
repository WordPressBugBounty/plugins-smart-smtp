<?php
/**
 * Maillogs Controller.
 *
 * @since 1.0.0
 * @package  namespace SmartSMTP\Controller\MailLogsController
 */

namespace SmartSMTP\Controller;

use SmartSMTP\Model\MailLogs;

/**
 * Maillogs controller class.
 *
 * @since 1.0.0
 */
class MailLogsController {

	/**
	 * Maillogs object.
	 *
	 * @since 1.0.0
	 */
	protected $mail_logs;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->mail_logs = new MailLogs();
	}

	/**
	 * Function to get the logs data.
	 *
	 * @since 1.0.0
	 * @param object|array $request The form data.
	 */
	public function get_mail_logs( $request ) {
		if ( ! isset( $request['reqst_data'] ) || empty( $request['reqst_data'] ) ) {

			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => esc_html__( 'Request data not found.', 'smart-smtp' ),
				),
				200
			);
		}
		$request_data = $request['reqst_data'];
		$page_size    = isset( $request_data['page_size'] ) ? absint( $request_data['page_size'] ) : 5;
		$offset       = isset( $request_data['offset'] ) ? absint( $request_data['offset'] ) : 0;
		$search_query = isset( $request_data['search_query'] ) ? $request_data['search_query'] : array();

		$res = $this->mail_logs->get_email_logs(
			array(
				'page_size'    => $page_size,
				'offset'       => $offset,
				'search_query' => $search_query,
			)
		);
		$res = $this->sanitize_log_bodies( $res );
		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => array(
					'message' => esc_html__( 'Mail logs data loaded successfully', 'smart-smtp' ),
					'data'    => $res,

				),
			),
			200
		);
	}

	/**
	 * Function for bulk action.
	 *
	 * @since 1.0.0
	 * @param array|object $request The request data.
	 */
	public function bulk_action( $request ) {
		$action = isset( $request['type'] ) ? sanitize_text_field( $request['type'] ) : '';
		$data   = isset( $request['data'] ) ? array_map( 'sanitize_text_field', $request['data'] ) : array();

		if ( '' === ( $request['action'] ) ) {

			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => esc_html__( 'Action not found.', 'smart-smtp' ),
				),
				200
			);
		}

		if ( empty( $data ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => esc_html__( 'Data not found.', 'smart-smtp' ),
				),
				200
			);
		}

		$res = $this->mail_logs->bulk_action( $action, $data );

		return new \WP_REST_Response(
			array(
				'success' => $res,
				'message' => esc_html__( 'All selected logs deleted successfully!!', 'smart-smtp' ),
			),
			200
		);
	}

	/**
	 * Get the log content.
	 *
	 * @since 1.0.0
	 *
	 * @param  [array] $request The request data.
	 */
	public function get_log_content( $request ) {
		$id  = isset( $request['id'] ) ? absint( $request['id'] ) : 0;
		$res = $this->mail_logs->get_email_logs(
			array(
				'id' => $id,
			)
		);
		$res = $this->sanitize_log_bodies( $res );

		if ( ! empty( $res['result'] ) ) {
			foreach ( $res['result'] as &$log ) {
				$paths = array();
				if ( ! empty( $log['attachments'] ) ) {
					$paths = array_values( array_filter( array_map( 'trim', explode( ',', $log['attachments'] ) ) ) );
				}
				$urls = array();
				foreach ( $paths as $path ) {
					$urls[] = array(
						'name' => basename( $path ),
						'url'  => $this->attachment_path_to_url( $path ),
					);
				}
				$log['attachment_list'] = $urls;
			}
			unset( $log );
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => esc_html__( 'All selected logs deleted successfully!!', 'smart-smtp' ),
				'data'    => $res,
			),
			200
		);
	}

	/**
	 * Sanitize the stored email body of each log row before it is returned to the admin UI.
	 *
	 * The body is stored verbatim so the log stays a faithful record of the email that was sent,
	 * but the Mail Logs viewer renders it as HTML. We strip <script>, <iframe>, srcdoc, on* event
	 * handlers and javascript: URIs (preventing stored XSS) while preserving legitimate email
	 * formatting. Logged emails are frequently full HTML documents whose <head><style> block
	 * carries the layout, so <style> is explicitly allowed: wp_kses_post() would drop the <style>
	 * element but keep its CSS as visible text, breaking the rendered email.
	 *
	 * @since 1.2.1
	 *
	 * @param array $res Result set from MailLogs::get_email_logs().
	 * @return array The result set with each row's body sanitized for output.
	 */
	private function sanitize_log_bodies( $res ) {
		if ( empty( $res['result'] ) || ! is_array( $res['result'] ) ) {
			return $res;
		}

		$allowed          = wp_kses_allowed_html( 'post' );
		$allowed['style'] = array(
			'type'  => true,
			'media' => true,
		);

		foreach ( $res['result'] as &$log ) {
			if ( isset( $log['body'] ) && is_string( $log['body'] ) ) {
				$log['body'] = wp_kses( $log['body'], $allowed );
			}
		}
		unset( $log );

		return $res;
	}

	/**
	 * Convert an absolute file path to a public URL.
	 *
	 * @param string $path Absolute file path.
	 * @return string URL or empty string if not resolvable.
	 */
	private function attachment_path_to_url( $path ) {
		if ( empty( $path ) ) {
			return '';
		}
		$path = wp_normalize_path( $path );

		$upload_dir = wp_upload_dir();
		$basedir    = wp_normalize_path( $upload_dir['basedir'] );
		$baseurl    = $upload_dir['baseurl'];
		if ( '' !== $basedir && 0 === stripos( $path, $basedir ) ) {
			return $baseurl . substr( $path, strlen( $basedir ) );
		}

		$abspath = wp_normalize_path( ABSPATH );
		$siteurl = get_site_url();
		if ( '' !== $abspath && 0 === stripos( $path, $abspath ) ) {
			return trailingslashit( $siteurl ) . ltrim( substr( $path, strlen( $abspath ) ), '/' );
		}

		return '';
	}

	/**
	 * Resend an email from a log entry.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $request The request data.
	 */
	public function resend_mail( $request ) {
		$id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;

		if ( ! $id ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => esc_html__( 'Invalid log ID.', 'smart-smtp' ),
				),
				200
			);
		}

		$res = $this->mail_logs->get_email_logs( array( 'id' => $id ) );

		if ( empty( $res['result'] ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => esc_html__( 'Log not found.', 'smart-smtp' ),
				),
				200
			);
		}

		$log          = $res['result'][0];
		$status       = intval( $log['status'] );
		$resent_count = intval( $log['resent_count'] ?? 0 );
		$to           = sanitize_email( $log['to'] );
		$subject      = sanitize_text_field( $log['subject'] );
		$body         = wp_kses_post( $log['body'] );

		// Send via wp_mail(). SmartSMTP overrides wp_mail() so this still routes through
		// our own engine (primary + fallback). skip_logging stops the wp_mail_succeeded
		// hook from inserting a duplicate row — we update the existing log row below.
		$mail_headers = array();
		if ( $body !== wp_strip_all_tags( $body ) ) {
			$mail_headers[] = 'Content-Type: text/html; charset=UTF-8';
		}
		if ( ! empty( $log['from'] ) ) {
			$mail_headers[] = 'From: ' . $log['from'];
		}

		\SmartSMTP\Services\Services::$skip_logging = true;
		$fallback_before = (int) get_option( 'smart_smtp_fallback_triggered_count', 0 );
		$sent            = wp_mail( $to, $subject, $body, $mail_headers );
		$used_fallback   = (int) get_option( 'smart_smtp_fallback_triggered_count', 0 ) > $fallback_before;
		\SmartSMTP\Services\Services::$skip_logging = false;
		// Retry success resets count to 0 (no number shown); resend increments it.
		$new_count = ( 1 === $status ) ? $resent_count + 1 : 0;

		if ( $sent ) {
			// Case 1 & 2: success — always update same row.
			$update = array(
				'status'       => 1,
				'resent_count' => $new_count,
				'updated_at'   => current_time( 'mysql' ),
			);
			// Sent via primary (no failover) — drop any stale fallback marker so the
			// row no longer shows the Fallback status.
			if ( ! $used_fallback ) {
				$update['primary_id'] = 0;
			}
			$this->mail_logs->update_log( $id, $update );
			return new \WP_REST_Response(
				array(
					'success'      => true,
					'message'      => esc_html__( 'Email resent successfully.', 'smart-smtp' ),
					'action'       => 'updated',
					'new_status'   => 1,
					'resent_count' => $new_count,
				),
				200
			);
		}

		// Failure path.
		// Resend of an already-sent log that has been resent more than once: keep a
		// single linked retry row. Reuse it on further failures instead of spawning a
		// new row each time.
		if ( 1 === $status && $resent_count > 1 ) {
			global $wpdb;
			$existing_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}smart_smtp_mail_logs WHERE primary_id = %d ORDER BY id ASC LIMIT 1",
					$id
				)
			);

			if ( $existing_id ) {
				// Already have a retry row for this log — just refresh it.
				$this->mail_logs->update_log(
					$existing_id,
					array(
						'status'        => 0,
						'error_message' => esc_html__( 'Resend failed.', 'smart-smtp' ),
						'updated_at'    => current_time( 'mysql' ),
					)
				);
				$new_id = $existing_id;
			} else {
				$new_log = array(
					'to'            => $log['to'],
					'from'          => $log['from'],
					'subject'       => $log['subject'],
					'body'          => $log['body'],
					'header'        => $log['header'],
					'attachments'   => $log['attachments'],
					'status'        => 0,
					'resent_count'  => 0,
					'primary_id'    => $id,
					'error_message' => esc_html__( 'Resend failed.', 'smart-smtp' ),
					// Omit created_at/updated_at so the DB DEFAULT CURRENT_TIMESTAMP applies,
					// matching every other log row. Passing current_time('mysql') here uses a
					// different timezone than the DB default, making the row sort to the bottom.
				);
				$new_id = $this->mail_logs->insert_email_logs( $new_log );
			}

			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => esc_html__( 'Failed to resend email.', 'smart-smtp' ),
					'action'  => 'new_row',
					'new_id'  => $new_id,
				),
				200
			);
		}

		// Otherwise (retry of a failed log, or resend with count <= 1): update same row.
		$this->mail_logs->update_log( $id, array(
			'resent_count' => $new_count,
			'updated_at'   => current_time( 'mysql' ),
		) );
		return new \WP_REST_Response(
			array(
				'success'      => false,
				'message'      => esc_html__( 'Failed to resend email.', 'smart-smtp' ),
				'action'       => 'updated',
				'new_status'   => $status,
				'resent_count' => $new_count,
			),
			200
		);
	}
}
