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

/** Async file upload (spec §3.3): upload on selection, keep only the returned token. */
function wireFileUpload( form, config, state ) {
	const fileCfg = config.settings.quoteForm.file;
	const picker = form.querySelector( '.alc-quote__file' );
	if ( ! fileCfg || ! fileCfg.enabled || ! picker ) {
		return;
	}
	const hidden = form.querySelector( '[name="alc_file_token"]' );
	const status = form.querySelector( '.alc-quote__file-status' );
	const say = ( text ) => {
		if ( status ) {
			status.textContent = text;
		}
	};
	picker.addEventListener( 'change', async () => {
		const file = picker.files && picker.files[ 0 ];
		hidden.value = '';
		if ( ! file ) {
			say( '' );
			return;
		}
		if ( file.size > fileCfg.maxMb * 1048576 ) {
			picker.value = '';
			say( config.i18n.fileTooLarge.replace( '%d', String( fileCfg.maxMb ) ) );
			return;
		}
		say( config.i18n.fileUploading );
		state.uploading = true;
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
			hidden.value = data.token;
			say( '✓ ' + data.name );
		} catch ( err ) {
			picker.value = '';
			say( err.message || config.i18n.networkError );
		}
		state.uploading = false;
	} );
}
