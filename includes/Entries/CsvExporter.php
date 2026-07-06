<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Entries;

use Alovio\Calculator\Frontend\CurrencyFormatter;

defined( 'ABSPATH' ) || exit;

final class CsvExporter {

	private const COLUMNS = array( 'id', 'calculator_id', 'created_at', 'name', 'email', 'phone', 'message', 'total', 'status', 'repeaters', 'snapshot' );

	public function register(): void {
		add_action( 'admin_post_alovio_calc_export_entries', array( $this, 'handle' ) );
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
		check_admin_referer( 'alovio_calc_export_entries' );

		$calculator = isset( $_GET['calculator'] ) ? absint( $_GET['calculator'] ) : 0;
		$rows       = ( new EntriesRepository() )->all_for_export( $calculator );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=alovio-calculator-entries.csv' );
		echo implode( ',', self::COLUMNS ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- CSV stream, constant header.
		foreach ( $rows as $row ) {
			$decoded          = json_decode( (string) ( $row['snapshot'] ?? '' ), true );
			$row['repeaters'] = self::repeater_cell( is_array( $decoded ) ? $decoded : array() );
			echo self::csv_row( $row ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- CSV-escaped above.
		}
		exit;
	}

	/**
	 * Spec §3.1: ONE cell per entry — rows joined with " | ", each as
	 * "Room 1: r_area=20, r_rate=Standard ($120.00)" (keys = child IDS, values =
	 * display labels). Empty displays skipped; the csv_row() injection guard and
	 * RFC-4180 quoting then apply to the whole cell unchanged.
	 */
	public static function repeater_cell( array $snapshot ): string {
		$parts = array();
		foreach ( (array) ( $snapshot['repeaters'] ?? array() ) as $rep ) {
			foreach ( (array) ( $rep['rows'] ?? array() ) as $row ) {
				$vals = array();
				foreach ( (array) ( $row['values'] ?? array() ) as $cid => $display ) {
					if ( '' === (string) $display ) {
						continue;
					}
					$vals[] = $cid . '=' . $display;
				}
				$money   = CurrencyFormatter::format(
					(int) ( $row['total'] ?? 0 ),
					(array) ( $snapshot['currency'] ?? array() ) + array(
						'symbol'      => '$',
						'position'    => 'before',
						'decimals'    => 2,
						'thousandSep' => ',',
						'decimalSep'  => '.',
					)
				);
				$parts[] = $row['label'] . ( $vals ? ': ' . implode( ', ', $vals ) : ':' ) . ' (' . $money . ')';
			}
		}
		return implode( ' | ', $parts );
	}
}
