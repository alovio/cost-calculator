/**
 * Repeater row management (spec §3.1): the server renders row markup once in a
 * <template>; this module clones it, renumbers labels + radio/checkbox names,
 * and enforces minRows/maxRows. Value changes bubble to the calculator's own
 * input/change listeners; add/remove call onChange() explicitly.
 */

function rowLabel( field, n ) {
	const tpl = field.rowLabel || '';
	return tpl !== '' ? tpl.replace( '{n}', String( n ) ) : `${ field.label || '' } ${ n }`.trim();
}

function setupRepeater( root, field, onChange ) {
	const wrap = root.querySelector( `[data-alc-field="${ field.id }"]` );
	if ( ! wrap ) {
		return;
	}
	const rowsEl = wrap.querySelector( '[data-alc-rows]' );
	const template = wrap.querySelector( '[data-alc-row-template]' );
	const addBtn = wrap.querySelector( '[data-alc-add]' );
	if ( ! rowsEl || ! template || ! addBtn ) {
		return;
	}
	const rows = () => rowsEl.querySelectorAll( '[data-alc-row]' );

	const renumber = () => {
		const all = rows();
		all.forEach( ( row, i ) => {
			const label = row.querySelector( '[data-alc-row-label]' );
			if ( label ) {
				label.textContent = rowLabel( field, i + 1 );
			}
			row.querySelectorAll( '[name]' ).forEach( ( input ) => {
				// Names end in "_<row>"; the template ships "___ROW__".
				input.name = input.name.replace( /_(?:__ROW__|\d+)$/, `_${ i + 1 }` );
			} );
		} );
		addBtn.disabled = all.length >= field.maxRows;
		all.forEach( ( row ) => {
			const remove = row.querySelector( '[data-alc-remove]' );
			if ( remove ) {
				remove.hidden = all.length <= field.minRows;
			}
		} );
	};

	addBtn.addEventListener( 'click', () => {
		if ( rows().length >= field.maxRows ) {
			return;
		}
		rowsEl.appendChild( template.content.firstElementChild.cloneNode( true ) );
		renumber();
		onChange();
	} );

	wrap.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest( '[data-alc-remove]' );
		if ( ! btn || ! wrap.contains( btn ) || rows().length <= field.minRows ) {
			return;
		}
		btn.closest( '[data-alc-row]' ).remove();
		renumber();
		onChange();
	} );

	renumber();
}

export function setupRepeaters( root, fields, onChange ) {
	fields.filter( ( f ) => f.type === 'repeater' ).forEach( ( f ) => setupRepeater( root, f, onChange ) );
}
