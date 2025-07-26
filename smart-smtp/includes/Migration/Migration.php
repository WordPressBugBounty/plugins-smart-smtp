<?php
/**
 * SmartSMTP Migration class.
 *
 * @package  namespace SmartSMTP\Migration
 * @since 1.0.0
 */

namespace SmartSMTP\Migration;

use SmartSMTP\Traits\Singleton;

/**
 * Abstract Migration class for handling database migrations.
 *
 * @since 1.0.0
 */
abstract class Migration {

	use Singleton;

	/**
	 * The WordPress database connection instance.
	 *
	 * @since 1.0.0
	 * @var wpdb
	 */
	protected $connection;

	/**
	 * Database charset and collation.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $charset_collate;

	/**
	 * Table prefix for the database tables.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $prefix;

	/**
	 * Constructor for the Migration class.
	 *
	 * Initializes the database connection and charset/collation properties.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->connection      = $wpdb;
		$this->prefix          = $wpdb->prefix;
		$this->charset_collate = $this->get_collation();
	}

	/**
	 * Retrieve the WordPress database connection instance.
	 *
	 * @return wpdb
	 */
	public function get_connection() {
		return $this->connection;
	}

	protected function get_collation() {
		if ( ! isset( $this->connection ) ) {
			return '';
		}
		if ( ! $this->connection->has_cap( 'collation' ) ) {
			return '';
		}

		return $this->connection->get_charset_collate();
	}
}
