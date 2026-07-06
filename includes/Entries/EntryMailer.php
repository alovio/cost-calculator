<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Entries;

use Alovio\Calculator\Formula\DecimalMath;
use Alovio\Calculator\Frontend\CurrencyFormatter;

defined( 'ABSPATH' ) || exit;

final class EntryMailer {

	public function notify( \WP_Post $calculator, array $config, array $contact, array $snapshot, int $entryId = 0 ): void {
		$to = $config['settings']['quoteForm']['notifyEmail'];
		if ( '' === $to ) {
			$to = get_option( 'admin_email' );
		}
		$lines = array();
		/* translators: %s: calculator title. */
		$lines[] = sprintf( __( 'New quote request — %s', 'alovio-calculator' ), $calculator->post_title );
		foreach ( $contact as $k => $v ) {
			$lines[] = ucfirst( $k ) . ': ' . $v;
		}
		$lines[] = '';
		$printed = array();
		foreach ( $snapshot['lineItems'] as $item ) {
			$repId = (string) ( $item['repeaterId'] ?? '' );
			if ( '' !== $repId ) {
				if ( isset( $printed[ $repId ] ) ) {
					continue; // All rows of this repeater were already expanded below.
				}
				$printed[ $repId ] = true;
				foreach ( self::repeater_lines( $snapshot, $repId ) as $line ) {
					$lines[] = $line;
				}
				continue;
			}
			$lines[] = $item['label'] . ': ' . ( isset( $item['display'] ) ? (string) $item['display'] : DecimalMath::fromScaled( $item['amount'] ) );
		}
		$lines[] = __( 'Total', 'alovio-calculator' ) . ': ' . DecimalMath::fromScaled( $snapshot['totalScaled'] );

		if ( ! empty( $snapshot['file']['name'] ) ) {
			$lines[] = '';
			/* translators: %s: uploaded file name. */
			$lines[] = sprintf( __( 'Attached file: %s', 'alovio-calculator' ), $snapshot['file']['name'] );
			/* translators: %s: admin dashboard URL. */
			$lines[] = sprintf( __( 'Download it from the entry in your dashboard: %s', 'alovio-calculator' ), admin_url( 'admin.php?page=alovio-calculator' ) );
		}

		/* translators: %s: site name. */
		$subject = sprintf( __( '[%s] New quote request', 'alovio-calculator' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );

		$mail = array(
			'to'          => $to,
			'subject'     => $subject,
			'message'     => implode( "\n", $lines ),
			'headers'     => array(),
			'attachments' => array(),
		);

		/**
		 * Filters the quote-notification email before sending. The free plugin sends
		 * a plain-text admin notice; the Pro add-on hooks this to attach a PDF and/or
		 * add a customer recipient.
		 *
		 * @param array    $mail       Keys: to, subject, message, headers, attachments.
		 * @param \WP_Post $calculator Calculator post.
		 * @param array    $config     Calculator config.
		 * @param array    $contact    Sanitized contact fields.
		 * @param array    $snapshot   Quote snapshot.
		 * @param int      $entryId    Stored entry id.
		 */
		$mail = apply_filters( 'alovio_calc_quote_email', $mail, $calculator, $config, $contact, $snapshot, $entryId );

		$sent = wp_mail(
			$mail['to'],
			(string) $mail['subject'],
			(string) $mail['message'],
			(array) $mail['headers'],
			(array) $mail['attachments']
		);
		if ( ! $sent && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// Spec §12: logged silently; the entry is already stored.
			error_log( 'Alovio Calculator: quote notification email failed to send.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- WP_DEBUG-guarded.
		}
	}

	/**
	 * Detail-modal-style lines (spec §3.1): "Room 1: Area 20, Rate Standard — $120.00".
	 * Toggle children print their label alone; empty displays are skipped. Pure, unit-tested.
	 *
	 * @return string[]
	 */
	public static function repeater_lines( array $snapshot, string $repId ): array {
		$lines = array();
		foreach ( (array) ( $snapshot['repeaters'] ?? array() ) as $rep ) {
			if ( ( $rep['id'] ?? '' ) !== $repId ) {
				continue;
			}
			foreach ( (array) ( $rep['rows'] ?? array() ) as $row ) {
				$parts = array();
				foreach ( (array) ( $row['values'] ?? array() ) as $cid => $display ) {
					if ( '' === (string) $display ) {
						continue;
					}
					$label   = (string) ( $rep['children'][ $cid ] ?? $cid );
					$parts[] = 'toggle' === (string) ( $rep['types'][ $cid ] ?? '' ) ? $label : $label . ' ' . $display;
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
				$lines[] = $row['label'] . ': ' . implode( ', ', $parts ) . ( $parts ? ' — ' : ' ' ) . $money;
			}
		}
		return $lines;
	}
}
