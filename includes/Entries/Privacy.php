<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Entries;

defined( 'ABSPATH' ) || exit;

/** WP personal-data exporter + eraser, keyed on email (spec §5). */
final class Privacy {

	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	public function register_exporter( array $exporters ): array {
		$exporters['alovio-calculator'] = array(
			'exporter_friendly_name' => __( 'Alovio Calculator quote requests', 'alovio-calculator' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	public function register_eraser( array $erasers ): array {
		$erasers['alovio-calculator'] = array(
			'eraser_friendly_name' => __( 'Alovio Calculator quote requests', 'alovio-calculator' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	public function export( string $email_address ): array {
		$items = array();
		foreach ( ( new EntriesRepository() )->get_by_email( $email_address ) as $row ) {
			$data = array(
				array( 'name' => __( 'Name', 'alovio-calculator' ), 'value' => $row['name'] ),
				array( 'name' => __( 'Email', 'alovio-calculator' ), 'value' => $row['email'] ),
				array( 'name' => __( 'Phone', 'alovio-calculator' ), 'value' => $row['phone'] ),
				array( 'name' => __( 'Message', 'alovio-calculator' ), 'value' => (string) $row['message'] ),
				array( 'name' => __( 'Quote total', 'alovio-calculator' ), 'value' => $row['total'] ),
				array( 'name' => __( 'Submitted at', 'alovio-calculator' ), 'value' => $row['created_at'] ),
			);
			$items[] = array(
				'group_id'    => 'alc_entries',
				'group_label' => __( 'Quote requests', 'alovio-calculator' ),
				'item_id'     => 'alc-entry-' . $row['id'],
				'data'        => $data,
			);
		}
		return array(
			'data' => $items,
			'done' => true, // Per-email volume is small; single page.
		);
	}

	public function erase( string $email_address ): array {
		$removed = ( new EntriesRepository() )->delete_by_email( $email_address );
		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
