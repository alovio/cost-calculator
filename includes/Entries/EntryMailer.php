<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Entries;

use Alovio\Calculator\Formula\DecimalMath;

defined( 'ABSPATH' ) || exit;

final class EntryMailer {

	public function notify( \WP_Post $calculator, array $config, array $contact, array $snapshot ): void {
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
		foreach ( $snapshot['lineItems'] as $item ) {
			$lines[] = $item['label'] . ': ' . DecimalMath::fromScaled( $item['amount'] );
		}
		$lines[] = __( 'Total', 'alovio-calculator' ) . ': ' . DecimalMath::fromScaled( $snapshot['totalScaled'] );

		/* translators: %s: site name. */
		$subject = sprintf( __( '[%s] New quote request', 'alovio-calculator' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		$sent    = wp_mail( $to, $subject, implode( "\n", $lines ) );
		if ( ! $sent && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// Spec §12: logged silently; the entry is already stored.
			error_log( 'Alovio Calculator: quote notification email failed to send.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- WP_DEBUG-guarded.
		}
	}
}
