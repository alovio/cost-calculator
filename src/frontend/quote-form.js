/** Quote form submission per the spec §10 response contract. */
export function wireQuoteForm( root, config, getRawValues ) {
	const form = root.querySelector( '.alc-quote' );
	if ( ! form ) {
		return;
	}
	const feedback = form.querySelector( '.alc-quote-feedback' );
	const button = form.querySelector( '.alc-quote__submit' );

	const setFeedback = ( text, kind ) => {
		feedback.textContent = text;
		feedback.className = 'alc-quote-feedback' + ( kind ? ` is-${ kind }` : '' );
	};

	const clearFieldErrors = () => {
		form.querySelectorAll( '.alc-field-error' ).forEach( ( el ) => el.remove() );
	};

	form.addEventListener( 'submit', async ( e ) => {
		e.preventDefault();
		button.disabled = true;
		clearFieldErrors();
		setFeedback( '', null );

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
			return;
		}

		if ( response.status === 400 && body.fieldErrors ) {
			Object.entries( body.fieldErrors ).forEach( ( [ key, message ] ) => {
				const input = form.querySelector( `[name="alc_contact_${ key }"]` );
				if ( input ) {
					const note = document.createElement( 'span' );
					note.className = 'alc-field-error';
					note.textContent = message;
					input.closest( '.alc-quote__field' ).appendChild( note );
				}
			} );
		}
		setFeedback( body.message || config.i18n.networkError, 'error' );
		button.disabled = false;
	} );
}
