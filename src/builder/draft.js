/**
 * localStorage draft recovery (spec §2.6). Pure module — Jest-tested with an
 * injectable storage. Best-effort by design: every storage failure is silent.
 */
export const DRAFT_DEBOUNCE_MS = 1000;

export function draftKey( calcId ) {
	return `alovio_calc_draft_${ calcId }`;
}

export function saveDraft( calcId, data, storage = window.localStorage ) {
	try {
		storage.setItem( draftKey( calcId ), JSON.stringify( { ...data, calcId, savedAt: Date.now() } ) );
	} catch ( e ) {
		// quota / private mode — drafts are best-effort
	}
}

export function loadDraft( calcId, storage = window.localStorage ) {
	try {
		const raw = storage.getItem( draftKey( calcId ) );
		const d = raw ? JSON.parse( raw ) : null;
		return d && Array.isArray( d.fields ) && 'number' === typeof d.savedAt ? d : null;
	} catch ( e ) {
		return null;
	}
}

export function clearDraft( calcId, storage = window.localStorage ) {
	try {
		storage.removeItem( draftKey( calcId ) );
	} catch ( e ) {
		// ignore
	}
}

/** MySQL GMT 'YYYY-MM-DD HH:MM:SS' → epoch ms (0 when absent/invalid). */
export function parseModifiedGmt( modified ) {
	if ( ! modified || 'string' !== typeof modified ) {
		return 0;
	}
	const t = Date.parse( modified.replace( ' ', 'T' ) + 'Z' );
	return Number.isNaN( t ) ? 0 : t;
}

export function isDraftNewer( draft, modifiedGmt ) {
	return !! draft && draft.savedAt > parseModifiedGmt( modifiedGmt );
}
