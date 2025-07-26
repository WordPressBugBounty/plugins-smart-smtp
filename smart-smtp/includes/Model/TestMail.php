<?php
/**
 * TestMail model.
 *
 * @since 1.0.0
 * @package  namespace SmartSMTP\Model\TestMail
 */

namespace SmartSMTP\Model;

/**
 * TestMail Model class.
 *
 * @since 1.0.0
 */
class TestMail {

	/**
	 * Email logs table name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $table_name;

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
	 * @var $config_option The config option.
	 */
	protected $config_option = 'smart_smtp_test_mail_configuration';

	/**
	 * The class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->con = $wpdb;
	}

	/**
	 * Get the config data.
	 *
	 * @since 1.0.0
	 */
	public function get_test_mail_config() {
		return get_option( $this->config_option, array() );
	}

	/**
	 * Update the mail config data.
	 *
	 * @since 1.0.0
	 * @param mixed $data The mail config data.
	 */
	public function update_test_mail_config( $data ) {
		return update_option( $this->config_option, $data );
	}

}
