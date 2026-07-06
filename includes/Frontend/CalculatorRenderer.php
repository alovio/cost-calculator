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
		$preset   = $config['settings']['theme']['preset'] ?? 'classic';

		$successMessage = '' !== $quote['successMessage']
			? $quote['successMessage']
			: __( "Thanks! We'll be in touch shortly.", 'alovio-calculator' ); // Translated here — the frontend bundle ships no wp-i18n.

		$payload = array(
			'calculatorId'  => $id,
			'quoteEndpoint' => esc_url( rest_url( 'alovio-calc/v1/quote' ) ),
			'fields'        => $config['fields'],
			'settings'      => array(
				'currency'  => $currency,
				'layout'    => $config['settings']['theme']['layout'] ?? 'single',
				'quoteForm' => array(
					'enabled'        => $quote['enabled'],
					'fields'         => $quote['fields'],
					'successMessage' => $successMessage,
					'downloadLabel'  => __( 'Download PDF', 'alovio-calculator' ),
				),
			),
		);

		$html  = sprintf( '<div class="alc-calculator alc-theme--%s" data-alc-id="%d" style="--alc-accent:%s">', esc_attr( $preset ), $id, esc_attr( $accent ) );
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

		$help = '';
		if ( ! empty( $field['help'] ) ) {
			$help = sprintf( '<p class="alc-field__help">%s</p>', esc_html( $field['help'] ) );
		}

		return sprintf(
			'<div class="alc-field alc-field--%s" data-alc-field="%s"%s>%s%s</div>',
			esc_attr( $field['type'] ),
			esc_attr( $id ),
			$hidden,
			$inner,
			$help
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
				$unit  = (string) ( $field['unit'] ?? '' );
				$min   = isset( $field['min'] ) && null !== $field['min'] ? (float) $field['min'] : 0.0;
				$max   = isset( $field['max'] ) && null !== $field['max'] ? (float) $field['max'] : 100.0;
				$pos   = $max > $min ? ( ( (float) $value - $min ) / ( $max - $min ) ) * 100 : 0.0;
				return sprintf(
					'<label for="alc-%1$s">%2$s</label>'
					. '<div class="alc-slider" data-alc-unit="%5$s" style="--alc-pos:%6$s%%">'
					. '<div class="alc-slider__rail"><input type="range" id="alc-%1$s"%3$s value="%4$s">'
					. '<output for="alc-%1$s" class="alc-slider__bubble">%7$s</output></div>'
					. '<div class="alc-slider__scale" aria-hidden="true"><span>%8$s</span><span>%9$s</span></div>'
					. '</div>',
					$id,
					$label,
					self::range_attrs( $field ),
					esc_attr( $value ),
					esc_attr( $unit ),
					esc_attr( self::trim_float( round( $pos, 2 ) ) ),
					esc_html( $value . ( '' !== $unit ? ' ' . $unit : '' ) ),
					esc_html( self::trim_float( $min ) ),
					esc_html( self::trim_float( $max ) )
				);

			case 'select':
				$options = '<option value="">' . esc_html__( '— select —', 'alovio-calculator' ) . '</option>';
				foreach ( $field['options'] as $opt ) {
					$options .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $opt['value'] ), ! empty( $opt['default'] ) ? ' selected' : '', esc_html( $opt['label'] ) );
				}
				return sprintf( '<label for="alc-%1$s">%2$s</label><select id="alc-%1$s">%3$s</select>', $id, $label, $options );

			case 'radio':
			case 'checkbox_group':
				$type       = 'radio' === $field['type'] ? 'radio' : 'checkbox';
				$has_images = false;
				foreach ( $field['options'] as $opt ) {
					if ( ! empty( $opt['image'] ) ) {
						$has_images = true;
						break;
					}
				}
				$items = '';
				foreach ( $field['options'] as $opt ) {
					$image = '';
					if ( ! empty( $opt['image'] ) ) {
						$image = '<span class="alc-choice__image">' . wp_get_attachment_image( (int) $opt['image'], $has_images ? 'medium' : 'thumbnail' ) . '</span>';
					}
					$items .= sprintf(
						'<label class="alc-choice"><input type="%1$s" name="alc_%2$s" value="%3$s"%6$s>%4$s<span class="alc-choice__label">%5$s</span></label>',
						$type,
						$id,
						esc_attr( $opt['value'] ),
						$image,
						esc_html( $opt['label'] ),
						! empty( $opt['default'] ) ? ' checked' : ''
					);
				}
				return sprintf( '<fieldset class="alc-choices%3$s"><legend>%1$s</legend>%2$s</fieldset>', $label, $items, $has_images ? ' alc-choices--cards' : '' );

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

			case 'date':
			case 'email':
			case 'phone':
			case 'url':
				$types = array(
					'date'  => 'date',
					'email' => 'email',
					'phone' => 'tel',
					'url'   => 'url',
				);
				return sprintf(
					'<label for="alc-%1$s">%2$s</label><input type="%3$s" id="alc-%1$s" placeholder="%4$s">',
					$id,
					$label,
					$types[ $field['type'] ],
					esc_attr( $field['placeholder'] ?? '' )
				);

			case 'textarea':
				return sprintf(
					'<label for="alc-%1$s">%2$s</label><textarea id="alc-%1$s" rows="3" placeholder="%3$s"></textarea>',
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

			case 'repeater':
				return self::render_repeater( $field, $result );

			case 'step':
				$desc = ! empty( $field['description'] )
					? '<p class="alc-step__desc">' . esc_html( $field['description'] ) . '</p>'
					: '';
				return sprintf( '<div class="alc-step__head"><h3 class="alc-step__title">%s</h3>%s</div>', $label, $desc );
		}

		return '';
	}

	/** Rows container + one inert row copy in <template> (spec §3.1: PHP renders row markup ONCE). */
	private static function render_repeater( array $field, array $result ): string {
		$rows    = '';
		$initial = isset( $result['repeaters'][ $field['id'] ]['rows'] ) ? $result['repeaters'][ $field['id'] ]['rows'] : array();
		$count   = max( 1, count( $initial ) );
		for ( $i = 1; $i <= $count; $i++ ) {
			$rows .= self::render_repeater_row( $field, (string) $i );
		}
		$add = '' !== $field['addLabel'] ? $field['addLabel'] : __( 'Add row', 'alovio-calculator' );

		return '<fieldset class="alc-repeater"><legend>' . esc_html( $field['label'] ) . '</legend>'
			. '<div class="alc-repeater__rows" data-alc-rows>' . $rows . '</div>'
			. '<template data-alc-row-template>' . self::render_repeater_row( $field, '__ROW__' ) . '</template>'
			. '<button type="button" class="alc-repeater__add" data-alc-add>' . esc_html( $add ) . '</button>'
			. '</fieldset>';
	}

	private static function render_repeater_row( array $field, string $index ): string {
		$children = '';
		foreach ( $field['fields'] as $child ) {
			$children .= sprintf(
				'<div class="alc-repeater__child alc-repeater__child--%s" data-alc-child="%s">%s</div>',
				esc_attr( $child['type'] ),
				esc_attr( $child['id'] ),
				self::render_repeater_child( $field['id'], $child, $index )
			);
		}
		$label = '' !== $field['rowLabel']
			? str_replace( '{n}', $index, $field['rowLabel'] )
			: trim( $field['label'] . ' ' . $index );

		return '<div class="alc-repeater__row" data-alc-row>'
			. '<div class="alc-repeater__row-head">'
			. '<span class="alc-repeater__row-label" data-alc-row-label>' . esc_html( $label ) . '</span>'
			. '<button type="button" class="alc-repeater__remove" data-alc-remove aria-label="' . esc_attr__( 'Remove row', 'alovio-calculator' ) . '">&times;</button>'
			. '</div><div class="alc-repeater__row-fields">' . $children . '</div></div>';
	}

	/**
	 * Child controls use IMPLICIT label association (no id/for — nothing to reindex on
	 * clone). Only radio/checkbox names carry the row index; JS renumbers those.
	 * Option images are deliberately not rendered inside rows (template weight).
	 */
	private static function render_repeater_child( string $repId, array $child, string $index ): string {
		$label = esc_html( $child['label'] );
		switch ( $child['type'] ) {
			case 'number':
			case 'quantity':
				return sprintf( '<label>%s<input type="number"%s value="%s"></label>', $label, self::range_attrs( $child ), esc_attr( self::default_number( $child ) ) );
			case 'slider':
				$value = self::default_number( $child );
				return sprintf(
					'<label>%1$s<span class="alc-slider"><input type="range"%2$s value="%3$s"><output>%3$s</output></span></label>',
					$label,
					self::range_attrs( $child ),
					esc_attr( $value )
				);
			case 'select':
				$options = '<option value="">' . esc_html__( '— select —', 'alovio-calculator' ) . '</option>';
				foreach ( $child['options'] as $opt ) {
					$options .= sprintf( '<option value="%s">%s</option>', esc_attr( $opt['value'] ), esc_html( $opt['label'] ) );
				}
				return sprintf( '<label>%s<select>%s</select></label>', $label, $options );
			case 'radio':
			case 'checkbox_group':
				$type  = 'radio' === $child['type'] ? 'radio' : 'checkbox';
				$name  = sprintf( 'alc_%s_%s_%s', $repId, $child['id'], $index );
				$items = '';
				foreach ( $child['options'] as $opt ) {
					$items .= sprintf(
						'<label class="alc-choice"><input type="%1$s" name="%2$s" value="%3$s"><span class="alc-choice__label">%4$s</span></label>',
						$type,
						esc_attr( $name ),
						esc_attr( $opt['value'] ),
						esc_html( $opt['label'] )
					);
				}
				return sprintf( '<fieldset class="alc-choices"><legend>%s</legend>%s</fieldset>', $label, $items );
			case 'toggle':
				$checked = ! empty( $child['default'] ) ? ' checked' : '';
				return sprintf(
					'<label class="alc-toggle"><input type="checkbox"%s><span class="alc-toggle__track" aria-hidden="true"></span>%s</label>',
					$checked,
					$label
				);
		}
		return '';
	}

	private static function render_summary( array $result, array $currency ): string {
		$rows = '';
		foreach ( $result['lineItems'] as $item ) {
			$value = isset( $item['display'] )
				? (string) $item['display']
				: ( $item['isCurrency'] ? CurrencyFormatter::format( $item['amount'], $currency ) : DecimalMath::fromScaled( $item['amount'] ) );
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
