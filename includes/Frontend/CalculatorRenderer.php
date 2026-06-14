<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Frontend;

use Alovio\Calculator\Formula\DecimalMath;
use Alovio\Calculator\Logic\Evaluation;

defined( 'ABSPATH' ) || exit;

/**
 * Server render (spec §8): initial state comes from the same Evaluation authority
 * the quote endpoint uses — no FOUC, meaningful output without JS. The embedded
 * JSON payload carries ONLY what the front end needs (never notifyEmail).
 */
final class CalculatorRenderer {

	public static function render( int $id, array $config ): string {
		$result   = Evaluation::run( $config, array() );
		$currency = $config['settings']['currency'];
		$quote    = $config['settings']['quoteForm'];
		$accent   = $config['settings']['theme']['accent'];

		$successMessage = '' !== $quote['successMessage']
			? $quote['successMessage']
			: __( "Thanks! We'll be in touch shortly.", 'alovio-calculator' ); // Translated here — the frontend bundle ships no wp-i18n.

		$payload = array(
			'calculatorId'  => $id,
			'quoteEndpoint' => esc_url( rest_url( 'alovio-calc/v1/quote' ) ),
			'fields'        => $config['fields'],
			'settings'      => array(
				'currency'  => $currency,
				'quoteForm' => array(
					'enabled'        => $quote['enabled'],
					'fields'         => $quote['fields'],
					'successMessage' => $successMessage,
				),
			),
		);

		$html  = sprintf( '<div class="alc-calculator" data-alc-id="%d" style="--alc-accent:%s">', $id, esc_attr( $accent ) );
		$html .= '<div class="alc-fields">';
		foreach ( $config['fields'] as $field ) {
			$html .= self::render_field( $field, $result );
		}
		$html .= '</div>';
		$html .= self::render_summary( $result, $currency );
		if ( $quote['enabled'] ) {
			$html .= self::render_quote_form( $quote );
		}
		$html .= '<script type="application/json" class="alc-config">'
			. wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP )
			. '</script>';
		$html .= '</div>';

		return $html;
	}

	private static function render_field( array $field, array $result ): string {
		$id     = $field['id'];
		$active = $result['active'][ $id ] ?? true;
		$hidden = $active ? '' : ' hidden';

		$inner = self::render_input( $field, $result );

		return sprintf(
			'<div class="alc-field alc-field--%s" data-alc-field="%s"%s>%s</div>',
			esc_attr( $field['type'] ),
			esc_attr( $id ),
			$hidden,
			$inner
		);
	}

	private static function render_input( array $field, array $result ): string {
		$id    = esc_attr( $field['id'] );
		$label = esc_html( $field['label'] );

		switch ( $field['type'] ) {
			case 'number':
			case 'quantity':
				return sprintf(
					'<label for="alc-%1$s">%2$s</label><input type="number" id="alc-%1$s"%3$s value="%4$s">',
					$id,
					$label,
					self::range_attrs( $field ),
					esc_attr( self::default_number( $field ) )
				);

			case 'slider':
				$value = self::default_number( $field );
				return sprintf(
					'<label for="alc-%1$s">%2$s</label><div class="alc-slider"><input type="range" id="alc-%1$s"%3$s value="%4$s"><output for="alc-%1$s">%4$s</output></div>',
					$id,
					$label,
					self::range_attrs( $field ),
					esc_attr( $value )
				);

			case 'select':
				$options = '<option value="">' . esc_html__( '— select —', 'alovio-calculator' ) . '</option>';
				foreach ( $field['options'] as $opt ) {
					$options .= sprintf( '<option value="%s">%s</option>', esc_attr( $opt['value'] ), esc_html( $opt['label'] ) );
				}
				return sprintf( '<label for="alc-%1$s">%2$s</label><select id="alc-%1$s">%3$s</select>', $id, $label, $options );

			case 'radio':
			case 'checkbox_group':
				$type  = 'radio' === $field['type'] ? 'radio' : 'checkbox';
				$items = '';
				foreach ( $field['options'] as $i => $opt ) {
					$image = '';
					if ( 'radio' === $field['type'] && ! empty( $opt['image'] ) ) {
						$image = '<span class="alc-choice__image">' . wp_get_attachment_image( (int) $opt['image'], 'thumbnail' ) . '</span>';
					}
					$items .= sprintf(
						'<label class="alc-choice"><input type="%1$s" name="alc_%2$s" value="%3$s">%4$s<span class="alc-choice__label">%5$s</span></label>',
						$type,
						$id,
						esc_attr( $opt['value'] ),
						$image,
						esc_html( $opt['label'] )
					);
				}
				return sprintf( '<fieldset><legend>%s</legend>%s</fieldset>', $label, $items );

			case 'toggle':
				$checked = ! empty( $field['default'] ) ? ' checked' : '';
				return sprintf(
					'<label class="alc-toggle"><input type="checkbox" id="alc-%1$s"%2$s><span class="alc-toggle__track" aria-hidden="true"></span>%3$s</label>',
					$id,
					$checked,
					$label
				);

			case 'text':
				return sprintf(
					'<label for="alc-%1$s">%2$s</label><input type="text" id="alc-%1$s" placeholder="%3$s">',
					$id,
					$label,
					esc_attr( $field['placeholder'] ?? '' )
				);

			case 'heading':
				return sprintf( '<h3 class="alc-heading">%s</h3>', $label );

			case 'html':
				return wp_kses_post( $field['content'] ?? '' );

			case 'formula':
				$amount = $result['values'][ $field['id'] ] ?? 0;
				return sprintf(
					'<div class="alc-line"><span>%s</span><span class="alc-line__value">%s</span></div>',
					$label,
					esc_html( DecimalMath::fromScaled( $amount ) )
				);
		}

		return '';
	}

	private static function render_summary( array $result, array $currency ): string {
		$rows = '';
		foreach ( $result['lineItems'] as $item ) {
			$value = $item['isCurrency']
				? CurrencyFormatter::format( $item['amount'], $currency )
				: DecimalMath::fromScaled( $item['amount'] );
			$rows .= sprintf(
				'<li data-alc-line="%s"><span class="alc-line-label">%s</span><span class="alc-line-value">%s</span></li>',
				esc_attr( $item['id'] ),
				esc_html( $item['label'] ),
				esc_html( $value )
			);
		}
		$total = CurrencyFormatter::format( $result['totalScaled'] ?? 0, $currency );

		return '<aside class="alc-summary"><ul class="alc-summary__lines">' . $rows . '</ul>'
			. '<p class="alc-total" aria-live="polite" data-alc-total>'
			. '<span class="alc-total__label">' . esc_html__( 'Total', 'alovio-calculator' ) . '</span>'
			. '<span class="alc-total__value">' . esc_html( $total ) . '</span>'
			. '</p></aside>';
	}

	private static function render_quote_form( array $quote ): string {
		$inputs = '';
		$labels = array(
			'name'    => __( 'Name', 'alovio-calculator' ),
			'email'   => __( 'Email', 'alovio-calculator' ),
			'phone'   => __( 'Phone', 'alovio-calculator' ),
			'message' => __( 'Message', 'alovio-calculator' ),
		);
		foreach ( $quote['fields'] as $key ) {
			$required = in_array( $key, array( 'name', 'email' ), true ) ? ' required' : '';
			if ( 'message' === $key ) {
				$inputs .= sprintf(
					'<label class="alc-quote__field">%s<textarea name="alc_contact_message" rows="3"></textarea></label>',
					esc_html( $labels[ $key ] )
				);
				continue;
			}
			$type    = 'email' === $key ? 'email' : ( 'phone' === $key ? 'tel' : 'text' );
			$inputs .= sprintf(
				'<label class="alc-quote__field">%1$s<input type="%2$s" name="alc_contact_%3$s"%4$s></label>',
				esc_html( $labels[ $key ] ),
				$type,
				esc_attr( $key ),
				$required
			);
		}

		return '<form class="alc-quote" novalidate>'
			. '<h3>' . esc_html__( 'Get this quote by email', 'alovio-calculator' ) . '</h3>'
			. $inputs
			. '<input type="text" name="alc_website" class="alc-hp" tabindex="-1" autocomplete="off" aria-hidden="true">'
			. '<button type="submit" class="alc-quote__submit">' . esc_html__( 'Request quote', 'alovio-calculator' ) . '</button>'
			. '<div class="alc-quote-feedback" role="status"></div>'
			. '</form>';
	}

	private static function range_attrs( array $field ): string {
		$attrs = '';
		foreach ( array( 'min', 'max', 'step' ) as $k ) {
			if ( isset( $field[ $k ] ) && null !== $field[ $k ] ) {
				$attrs .= sprintf( ' %s="%s"', $k, esc_attr( self::trim_float( (float) $field[ $k ] ) ) );
			}
		}
		return $attrs;
	}

	private static function default_number( array $field ): string {
		$v = isset( $field['default'] ) && null !== $field['default'] ? (float) $field['default'] : 0.0;
		if ( isset( $field['min'] ) && null !== $field['min'] ) {
			$v = max( (float) $field['min'], $v );
		}
		if ( isset( $field['max'] ) && null !== $field['max'] ) {
			$v = min( (float) $field['max'], $v );
		}
		return self::trim_float( $v );
	}

	private static function trim_float( float $v ): string {
		$s = (string) $v;
		return $s;
	}
}
