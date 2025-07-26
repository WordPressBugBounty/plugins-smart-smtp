<?php

/**
 * ChangeLogs Controller.
 *
 * @since 1.0.0
 * @package  namespace SmartSMTP\Controller\ChangeLogsController
 */

namespace SmartSMTP\Controller;

/**
 * Changelog controller class.
 *
 * @since 1.0.0
 */
class ChangeLogsController {

	/**
	 * Get item.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Rest_Request $request Full detail about the request.
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function get_item( $request ) {
		$changelog = $this->read_changelog();
		$changelog = $this->parse_changelog( $changelog );
		return new \WP_REST_Response(
			$changelog,
			200
		);
	}

	/**
	 * Read changelog.
	 *
	 * @since 1.0.0
	 *
	 * @return \WP_Error|string
	 */
	protected function read_changelog() {
		$raw_changelog = $this->file_get_contents( 'readme.txt' );
		if ( ! $raw_changelog ) {
			return new \WP_Error( 'changelog_read_error', esc_html__( 'Failed to read changelog.', 'smart-smtp' ) );
		}

		return $raw_changelog;
	}

	/**
	 * EVF file get contents.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $file File path.
	 */
	protected function file_get_contents( $file ) {
		if ( $file ) {
			$local_file = preg_replace( '/\\\\|\/\//', '/', plugin_dir_path( SMART_SMTP_PLUGIN_FILE ) . $file );
			$response   = file_get_contents( $local_file );
			if ( $response ) {
				return $response;
			}
			global $wp_filesystem;
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
			$local_file = preg_replace( '/\\\\|\/\//', '/', plugin_dir_path( SMART_SMTP_PLUGIN_FILE ) . $file );
			if ( $wp_filesystem->exists( $local_file ) ) {
				$response = $wp_filesystem->get_contents( $local_file );
				return $response;
			}
		}
		return false;
	}
	/**
	 * Parse Changelog contents.
	 *
	 * @since 1.0.0
	 *
	 * @param string $raw_changelog Raw changelog that needs to be parsed properly.
	 */
	protected function parse_changelog( $raw_changelog ) {
		if ( is_wp_error( $raw_changelog ) ) {
			return $raw_changelog;
		}

		$entries = preg_split( '/(?=\=\s\d+\.\d+\.\d+|\Z)/', $raw_changelog, -1, PREG_SPLIT_NO_EMPTY );
		array_shift( $entries );

		$parsed_changelog = array();

		foreach ( $entries as $entry ) {
			$date    = null;
			$version = null;

			if ( preg_match( '/^\=\s*(\d+(?:\.\d+)*?)\s+\-\s+(\d{2}|\bxx\b)-(\d{2}|\bxx\b)-(\d{4}|\bxxxx\b)/', $entry, $matches ) ) {
				$version = $matches[1] ?? null; // phpcs:ignore
				// $date    = $matches[2] ?? null; // phpcs:ignore
				$day   = $matches[2] === 'xx' ? 'xx' : $matches[2];
				$month = $matches[3] === 'xx' ? 'xx' : $matches[3];
				$year  = $matches[4] === 'xxxx' ? 'xxxx' : $matches[4];
				$date  = "$day-$month-$year";
			}

			$changes_arr = array();

			if ( preg_match_all( '/^\* (\w+(\s*-\s*.+)?)$/m', $entry, $matches ) ) {
				$changes = $matches[1] ?? null; // phpcs:ignore

				if ( is_array( $changes ) ) {
					foreach ( $changes as $change ) {
						$parts = explode( ' - ', $change );
						$tag   = trim( $parts[0] ?? '' ); // phpcs:ignore
						$data  = isset( $parts[1] ) ? trim( $parts[1] ) : '';

						if ( isset( $changes_arr[ $tag ] ) ) {
							$changes_arr[ $tag ][] = $data;
						} else {
							$changes_arr[ $tag ] = array( $data );
						}
					}
				}
			}

			if ( $version && $date && $changes_arr ) {
				$parsed_changelog[] = array(
					'version' => $version,
					'date'    => $date,
					'changes' => $changes_arr,
				);
			}
		}

		return $parsed_changelog;
	}
}
