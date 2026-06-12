<?php
namespace Alovio\Calculator\Entries;

use Alovio\Calculator\Formula\DecimalMath;

final class EntriesRepository {

	/** Pure shaping — unit-tested. Snapshot = values + line items + totals (spec §5). */
	public static function row_from_submission( int $calculator_id, array $contact, array $result ): array {
		return [
			'calculator_id' => $calculator_id,
			'name'          => mb_substr( (string) ( $contact['name'] ?? '' ), 0, 190 ),
			'email'         => mb_substr( (string) ( $contact['email'] ?? '' ), 0, 190 ),
			'phone'         => mb_substr( (string) ( $contact['phone'] ?? '' ), 0, 64 ),
			'message'       => (string) ( $contact['message'] ?? '' ),
			'snapshot'      => wp_json_encode( $result ),
			'total'         => number_format( ( $result['totalScaled'] ?? 0 ) / DecimalMath::SCALE, 4, '.', '' ),
			'status'        => 'new',
			'created_at'    => current_time( 'mysql' ),
		];
	}

	public function insert( array $row ): int {
		global $wpdb;
		$wpdb->insert( EntriesTable::table_name(), $row );
		return (int) $wpdb->insert_id;
	}

	/** @return array{rows: array[], total: int} */
	public function paginate( int $calculator_id = 0, int $page = 1, int $per_page = 20 ): array {
		global $wpdb;
		$table  = EntriesTable::table_name();
		$where  = $calculator_id > 0 ? $wpdb->prepare( 'WHERE calculator_id = %d', $calculator_id ) : '';
		$offset = max( 0, ( $page - 1 ) * $per_page );
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL -- table name + pre-prepared where
		$total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL
		return [ 'rows' => $rows ?: [], 'total' => $total ];
	}

	public function set_status( int $id, string $status ): void {
		global $wpdb;
		$wpdb->update( EntriesTable::table_name(), [ 'status' => 'read' === $status ? 'read' : 'new' ], [ 'id' => $id ] );
	}

	public function delete( int $id ): void {
		global $wpdb;
		$wpdb->delete( EntriesTable::table_name(), [ 'id' => $id ] );
	}

	/** Used by the entries REST routes for 404 semantics. */
	public function find( int $id ): ?array {
		global $wpdb;
		$table = EntriesTable::table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		return $row ?: null;
	}

	/** Used by the CSV exporter — ALL rows, no pagination. */
	public function all_for_export( int $calculator_id = 0 ): array {
		global $wpdb;
		$table = EntriesTable::table_name();
		$where = $calculator_id > 0 ? $wpdb->prepare( 'WHERE calculator_id = %d', $calculator_id ) : '';
		return $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY id ASC", ARRAY_A ) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL
	}

	public function delete_by_email( string $email ): int {
		global $wpdb;
		return (int) $wpdb->delete( EntriesTable::table_name(), [ 'email' => $email ] );
	}

	/** @return array[] */
	public function get_by_email( string $email ): array {
		global $wpdb;
		$table = EntriesTable::table_name();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s ORDER BY id ASC", $email ), ARRAY_A ) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL
	}
}
