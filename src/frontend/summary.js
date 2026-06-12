import { fromScaled } from '../shared/formula';
import { formatCurrency } from '../shared/currency';

/** Updates the server-rendered .alc-summary in place (rows matched by [data-alc-line]). */
export function updateSummary( root, result, currency ) {
	const list = root.querySelector( '.alc-summary__lines' );
	if ( list ) {
		const seen = new Set();
		result.lineItems.forEach( ( item ) => {
			seen.add( item.id );
			let row = list.querySelector( `[data-alc-line="${ item.id }"]` );
			if ( ! row ) {
				row = document.createElement( 'li' );
				row.setAttribute( 'data-alc-line', item.id );
				row.innerHTML = '<span class="alc-line-label"></span><span class="alc-line-value"></span>';
				list.appendChild( row );
			}
			row.querySelector( '.alc-line-label' ).textContent = item.label;
			row.querySelector( '.alc-line-value' ).textContent = item.isCurrency
				? formatCurrency( item.amount, currency )
				: fromScaled( item.amount );
		} );
		list.querySelectorAll( '[data-alc-line]' ).forEach( ( row ) => {
			if ( ! seen.has( row.getAttribute( 'data-alc-line' ) ) ) {
				row.remove();
			}
		} );
	}

	const total = root.querySelector( '[data-alc-total] .alc-total__value' );
	if ( total ) {
		total.textContent = formatCurrency( result.totalScaled || 0, currency );
	}
}
