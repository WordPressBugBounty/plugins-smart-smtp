<?php
/**
 * SmartSMTP EmailLogsModel class.
 *
 * @since 1.0.0
 * @package  namespace SmartSMTP\Model\MailLogs
 */

namespace SmartSMTP\Model;

use SmartSMTP\Migration\MailLogsMigration;

/**
 * MailLogs model class.
 *
 * @since 1.0.0
 */
class MailLogs {

	/**
	 * The name of the database connection to use.
	 *
	 * @var wpdb
	 */
	protected $con;
	/**
	 * EmailLogsModel constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;

		$migration = MailLogsMigration::init();

		$this->con = $wpdb;
	}

	/**
	 * Function to insert the email logs.
	 *
	 * @since 1.0.0
	 *
	 * @param  [array] $logs The email logs.
	 */
	public function insert_email_logs( $logs ) {

		$res = $this->con->insert( $this->con->prefix . 'smart_smtp_mail_logs', $logs );

		if ( $res ) {
			return $this->con->insert_id;
		}

		return $res;
	}

	/**
	 * Function to get the email logs.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $param The the extra param to control the query.
	 */
	public function get_email_logs( $param = array() ) {
		$query = $this->con->prepare( "SELECT * FROM {$this->con->prefix}smart_smtp_mail_logs" );
		if ( isset( $param['id'] ) ) {
			$id     = absint( $param['id'] );
			$query .= $this->con->prepare( ' WHERE ID = %d', $id );
		} elseif ( isset( $param['search_query'] ) ) {
			$search_params = $param['search_query'];
			$where         = array();
			$values        = array();
			$query_cond    = '';

			if ( isset( $search_params['searchByItem'] ) ) {
				$search_by_item = '%' . $this->con->esc_like( $search_params['searchByItem'] ) . '%';
				$where[]        = '`to` LIKE %s';
				$values[]       = $search_by_item;
			}

			if ( isset( $search_params['searchByDate'] ) && ! empty( $search_params['searchByDate'] ) ) {
				$date = new \DateTime( $search_params['searchByDate'] );
				$date->modify( '+1 day' );
				$start_of_day = $date->setTime( 0, 0, 0 )->format( 'Y-m-d H:i:s' );
				$end_of_day   = $date->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' );
				$where[]      = '`created_at` BETWEEN %s AND %s';
				$values[]     = $start_of_day;
				$values[]     = $end_of_day;
			}

			if ( isset( $search_params['searchByStatus'] ) && '' !== $search_params['searchByStatus'] ) {

				$where[]  = '`status`=%s';
				$values[] = $search_params['searchByStatus'];
			}

			foreach ( $where as $index => $condition ) {
				$query_cond .= 0 === $index ? ' WHERE ' : ' AND ';
				$query_cond .= $condition;
			}

			$query = $query . $query_cond;

			$query = $this->con->prepare( $query, $values );

			$order_by_columns = array();
			if ( isset( $search_params['orderByCreatedAt'] ) ) {
				$order_by_columns['created_at'] = $search_params['orderByCreatedAt'] ? 'ASC' : 'DESC';
			}
			if ( isset( $search_params['orderByFrom'] ) ) {
				$order_by_columns['from'] = $search_params['orderByFrom'] ? 'ASC' : 'DESC';
			}
			if ( isset( $search_params['orderByTo'] ) ) {
				$order_by_columns['to'] = $search_params['orderByTo'] ? 'ASC' : 'DESC';
			}

			$order_by_clause = array();

			foreach ( $order_by_columns as $column => $direction ) {
				$order_by_clause[] = "`{$column}` {$direction}";
			}
			if ( ! empty( $order_by_clause ) ) {
				$query .= ' ORDER BY ' . implode( ', ', $order_by_clause );
			}
		}
		$count = count( $this->con->get_results( $query, ARRAY_A ) );

		if ( isset( $param['page_size'] ) || isset( $param['offset'] ) ) {
			$page_size = isset( $param['page_size'] ) ? intval( $param['page_size'] ) : PHP_INT_SIZE;
			$offset    = isset( $param['offset'] ) ? intval( $param['offset'] ) : 0;
			$query    .= $this->con->prepare( ' LIMIT %d, %d', $offset, $page_size );
		}

		$res = $this->con->get_results( $query, ARRAY_A );

		return array(
			'total_count' => $count,
			'result'      => $res,
		);
	}

	/**
	 * Function to get the log id by data.
	 *
	 * @since 1.0.0
	 *
	 * @param  [array] $data The log data.
	 */
	public function fetch_log_id_by_data( $data ) {
		if ( empty( $data ) || ! is_array( $data ) ) {
			return 0;
		}

		$query      = $this->con->prepare( "SELECT `ID` FROM {$this->con->prefix}smart_smtp_mail_logs" );
		$query_cond = '';
		$where      = array();
		$values     = array();

		if ( array_key_exists( 'to', $data ) ) {
			$to_email = $data['to'][0];
			$where[]  = '`to` = %s';
			$values[] = trim( $to_email );
		}

		if ( array_key_exists( 'subject', $data ) ) {
			$where[]  = '`subject` = %s';
			$values[] = trim( $data['subject'] );
		}

		if ( array_key_exists( 'attachments', $data ) ) {
			$attachments = is_array( $data['attachments'] ) ? implode( ',', $data['attachments'] ) : '';
			$where[]     = '`attachments` = %s';
			$values[]    = trim( $attachments );
		}

		foreach ( $where as $index => $condition ) {
			$query_cond .= 0 === $index ? ' WHERE ' : ' AND ';
			$query_cond .= $condition;
		}

		$query_cond .= ' ORDER BY `ID` DESC LIMIT 1';

		$query = $query . $query_cond;

		return absint( $this->con->get_var( $this->con->prepare( $query, $values ) ) );
	}

	/**
	 * Update the mail log error to respective.
	 *
	 * @since 1.0.0
	 *
	 * @param [int]   $log_id The log id.
	 * @param  [array] $mail_error_data The mail error data.
	 * @param  [array] $mail_error_message The mail error message.
	 */
	public function update_mail_error_message( $log_id, $mail_error_data, $mail_error_message ) {
		$res = false;

		if ( empty( $log_id ) ) {
			return $res;
		}

		$res = $this->con->update(
			$this->con->prefix . 'smart_smtp_mail_logs',
			array(
				'status'        => 0,
				'error_message' => $mail_error_message,
			),
			array( 'ID' => $log_id )
		);

		return $res;
	}

	/**
	 * Bulk action.
	 *
	 * @since 1.0.0
	 *
	 * @param  [string] $action The action type.
	 * @param  [array]  $data The array data.
	 */
	public function bulk_action( $action, $data ) {
		if ( 'trash' === $action ) {

			foreach ( $data as $id ) {

				$this->con->delete(
					$this->con->prefix . 'smart_smtp_mail_logs',
					array(
						'ID' => $id,
					),
					array(
						' % d',
					)
				);
			}
		}
		return true;
	}
}
