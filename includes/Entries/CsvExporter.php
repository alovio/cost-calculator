<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Entries;

defined( 'ABSPATH' ) || exit;

final class CsvExporter {

	private const COLUMNS = array( 'id', 'calculator_id', 'created_at', 'name', 'email', 'phone', 'message', 'total', 'status', 'snapshot' );

	public function register(): void {
		add_action( 'admin_post_alc_export_entries', array( $this, 'handle' ) );
	}

	/** Pure, unit-tested: RFC-4180 quoting, newlines flattened, Excel formula-injection guarded. */
	public static function csv_row( array $row ): string {
		$cells = array();
		foreach ( self::COLUMNS as $col ) {
			$value = (string) ( $row[ $col ] ?? '' );
			$value = str_replace( array( "\r\n", "\r", "\n" ), ' ', $value );
			if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@' ), true ) && ! is_numeric( $value ) ) {
				$value = "'" . $value; // Excel formula-injection guard (numeric cells like totals stay clean).
			}
			if ( preg_match( '/[",]/', $value ) || false !== strpos( $value, ' ' ) ) {
				$value = '"' . str_replace( '"', '""', $value ) . '"';
			}
			$cells[] = $value;
		}
		return implode( ',', $cells );
	}

	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'alovio-calculator' ) );
		}
		check_admin_referer( 'alc_export_entries' );

		$calculator = isset( $_GET['calculator'] ) ? absint( $_GET['calculator'] ) : 0;
		$rows       = ( new EntriesRepository() )->all_for_export( $calculator );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=alovio-calculator-entries.csv' );
		echo implode( ',', self::COLUMNS ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- CSV stream, constant header.
		foreach ( $rows as $row ) {
			echo self::csv_row( $row ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- CSV-escaped above.
		}
		exit;
	}
}
