/** Quote form submission per the spec §10 response contract. */
export function wireQuoteForm( root, config, getRawValues, validateRequired ) {
	const form = root.querySelector( '.alc-quote' );
	if ( ! form ) {
		return;
	}
	const feedback = form.querySelector( '.alc-quote-feedback' );
	const button = form.querySelector( '.alc-quote__submit' );
	const state = { uploading: false };

	const setFeedback = ( text, kind ) => {
		feedback.textContent = text;
		feedback.className = 'alc-quote-feedback' + ( kind ? ` is-${ kind }` : '' );
	};

	// Errors can land on a contact field ([name="alc_contact_*"]) or, for THEN=require,
	// on a calculator field ([data-alc-field]).
	const showFieldError = ( key, message ) => {
		const target =
			form.querySelector( `[name="alc_contact_${ key }"]` )?.closest( '.alc-quote__field' ) ||
			root.querySelector( `[data-alc-field="${ key }"]` );
		if ( ! target ) {
			return;
		}
		const note = document.createElement( 'span' );
		note.className = 'alc-field-error';
		note.textContent = message;
		target.appendChild( note );
	};

	const clearFieldErrors = () => {
		root.querySelectorAll( '.alc-field-error' ).forEach( ( el ) => el.remove() );
	};

	wireFileUpload( form, config, state );

	form.addEventListener( 'submit', async ( e ) => {
		e.preventDefault();
		button.disabled = true;
		clearFieldErrors();
		setFeedback( '', null );

		if ( state.uploading ) {
			setFeedback( config.i18n.fileUploading, 'error' );
			button.disabled = false;
			return;
		}

		// Client-side require pre-check (the server re-validates authoritatively).
		const reqErrors = validateRequired ? validateRequired() : {};
		if ( Object.keys( reqErrors ).length ) {
			Object.entries( reqErrors ).forEach( ( [ key, message ] ) => showFieldError( key, message ) );
			setFeedback( config.i18n.requiredError || 'Please fill in the required fields.', 'error' );
			button.disabled = false;
			return;
		}

		const contact = {};
		form.querySelectorAll( '[name^="alc_contact_"]' ).forEach( ( input ) => {
			contact[ input.name.replace( 'alc_contact_', '' ) ] = input.value;
		} );
		const honeypot = form.querySelector( '[name="alc_website"]' );

		let response, body;
		try {
			response = await window.fetch( config.quoteEndpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( {
					calculatorId: config.calculatorId,
					values: getRawValues(),
					contact,
					fileToken: form.querySelector( '[name="alc_file_token"]' )?.value || '',
					alc_website: honeypot ? honeypot.value : '',
				} ),
			} );
			body = await response.json();
		} catch ( err ) {
			setFeedback( config.i18n.networkError, 'error' );
			button.disabled = false;
			return;
		}

		if ( response.status === 201 && body.ok ) {
			// Success: replace the form contents; calculator selections persist.
			form.innerHTML = `<p class="alc-quote-success">${ config.settings.quoteForm.successMessage }</p>`;
			// An add-on (e.g. Pro) may return a download URL for the just-created quote.
			if ( typeof body.pdfUrl === 'string' && /^https?:\/\//.test( body.pdfUrl ) ) {
				const link = document.createElement( 'a' );
				link.className = 'alc-quote-download';
				link.href = body.pdfUrl;
				link.textContent = config.settings.quoteForm.downloadLabel || 'Download PDF';
				link.setAttribute( 'download', '' );
				link.rel = 'noopener';
				form.appendChild( link );
			}
			return;
		}

		if ( response.status === 400 && body.fieldErrors ) {
			Object.entries( body.fieldErrors ).forEach( ( [ key, message ] ) => showFieldError( key, message ) );
		}
		setFeedback( body.message || config.i18n.networkError, 'error' );
		button.disabled = false;
	} );
}

/**
 * Async file upload (spec §3.3): upload on selection, keep only the returned
 * tokens. Several files are allowed when the author raised Max files — the
 * hidden input carries a comma-joined token list, and each file gets a row it
 * can be removed from without starting the field over.
 */
function wireFileUpload( form, config, state ) {
	const fileCfg = config.settings.quoteForm.file;
	const picker = form.querySelector( '.alc-quote__file' );
	if ( ! fileCfg || ! fileCfg.enabled || ! picker ) {
		return;
	}
	const hidden = form.querySelector( '[name="alc_file_token"]' );
	const list = form.querySelector( '.alc-quote__file-list' );
	const status = form.querySelector( '.alc-quote__file-status' );
	const maxFiles = Math.max( 1, parseInt( fileCfg.maxFiles, 10 ) || 1 );
	const say = ( text ) => {
		if ( status ) {
			status.textContent = text;
		}
	};

	// [{ token, name }] — the single source of truth; the hidden input mirrors it.
	let attached = [];

	const countText = () => {
		if ( ! attached.length ) {
			return '';
		}
		const tpl = maxFiles > 1 ? ( config.i18n.fileCount || '%1$d of %2$d attached' ) : ( config.i18n.fileOne || '1 attached' );
		return tpl.replace( '%1$d', String( attached.length ) ).replace( '%2$d', String( maxFiles ) );
	};

	const sync = () => {
		hidden.value = attached.map( ( f ) => f.token ).join( ',' );
		if ( ! list ) {
			return;
		}
		list.textContent = '';
		attached.forEach( ( f, i ) => {
			const row = document.createElement( 'span' );
			row.className = 'alc-quote__file-item';
			const name = document.createElement( 'span' );
			name.className = 'alc-quote__file-name';
			name.textContent = f.name;
			const remove = document.createElement( 'button' );
			remove.type = 'button';
			remove.className = 'alc-quote__file-remove';
			remove.setAttribute( 'aria-label', config.i18n.fileRemove || 'Remove' );
			remove.textContent = '×';
			remove.addEventListener( 'click', () => {
				attached.splice( i, 1 );
				sync();
				say( countText() );
			} );
			row.append( name, remove );
			list.append( row );
		} );
	};

	picker.addEventListener( 'change', async () => {
		const chosen = Array.from( picker.files || [] );
		picker.value = '';               // let the same file be picked again after a removal
		if ( ! chosen.length ) {
			return;
		}

		const room = maxFiles - attached.length;
		if ( room <= 0 ) {
			say( ( config.i18n.fileMax || 'Up to %d files.' ).replace( '%d', String( maxFiles ) ) );
			return;
		}
		const queue = chosen.slice( 0, room );
		const skipped = chosen.length - queue.length;

		state.uploading = true;
		say( config.i18n.fileUploading );
		for ( const file of queue ) {
			if ( file.size > fileCfg.maxMb * 1048576 ) {
				say( config.i18n.fileTooLarge.replace( '%d', String( fileCfg.maxMb ) ) );
				continue;
			}
			try {
				const body = new FormData();
				body.append( 'file', file );
				body.append( 'calculatorId', String( config.calculatorId ) );
				body.append( 'alc_website', '' );
				const resp = await window.fetch( fileCfg.endpoint, { method: 'POST', body } );
				const data = await resp.json();
				if ( ! resp.ok || ! data.token ) {
					throw new Error( ( data && data.message ) || config.i18n.networkError );
				}
				attached.push( { token: data.token, name: data.name } );
				sync();
				say( countText() );
			} catch ( err ) {
				say( err.message || config.i18n.networkError );
			}
		}
		if ( skipped > 0 ) {
			say( ( config.i18n.fileMax || 'Up to %d files.' ).replace( '%d', String( maxFiles ) ) );
		}
		state.uploading = false;
	} );
}
