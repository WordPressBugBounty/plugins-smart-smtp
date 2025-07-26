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

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => esc_html__( 'All selected logs deleted successfully!!', 'smart-smtp' ),
				'data'    => $res,
			),
			200
		);
	}
}
