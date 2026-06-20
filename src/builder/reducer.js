/**
 * Pure builder state: reducer, action creators, selectors. No @wordpress imports
 * here so it is unit-testable with plain Jest. The @wordpress/data store wrapper
 * lives in store.js.
 *
 * Calculator config = { fields[], settings{} } (spec §5). Options are objects
 * ({ label, price, image, value? }) — new options ship WITHOUT `value`; the server
 * assigns stable `opt_` slugs on save and the app re-hydrates from the response.
 */

export const DEFAULTS = {
	number: { label: 'Number', min: null, max: null, step: null, default: 0, showInSummary: false },
	slider: { label: 'Slider', min: 0, max: 100, step: 1, default: 0, showInSummary: true },
	select: { label: 'Dropdown', options: [ { label: 'Option 1', price: 0 } ], showInSummary: false },
	radio: { label: 'Choose one', options: [ { label: 'Option 1', price: 0 } ], showInSummary: false },
	checkbox_group: { label: 'Check all that apply', options: [ { label: 'Option 1', price: 0 } ], showInSummary: false },
	toggle: { label: 'Toggle', price: 0, default: false, showInSummary: false },
	quantity: { label: 'Quantity', min: 0, max: null, step: 1, default: 1, showInSummary: false },
	text: { label: 'Text', placeholder: '', showInSummary: false },
	heading: { label: 'Section heading', showInSummary: false },
	html: { label: 'Content', content: '', showInSummary: false },
	formula: { label: 'Total', expression: '', showInSummary: true },
	step: { label: 'Step', description: '', showInSummary: false },
};

let counter = 0;
export function makeId() {
	counter += 1;
	return `fld_${ counter }_${ Math.random().toString( 36 ).slice( 2, 7 ) }`;
}

export const initialState = { fields: [], settings: {}, selectedId: null };

function cloneOptions( options ) {
	return Array.isArray( options ) ? options.map( ( o ) => ( { ...o } ) ) : undefined;
}

export function reducer( state = initialState, action = {} ) {
	switch ( action.type ) {
		case 'ADD_FIELD': {
			const defaults = DEFAULTS[ action.fieldType ] || DEFAULTS.text;
			const field = { id: action.id, type: action.fieldType, ...defaults };
			if ( defaults.options ) {
				field.options = cloneOptions( defaults.options );
			}
			return { ...state, fields: [ ...state.fields, field ], selectedId: field.id };
		}
		case 'UPDATE_FIELD':
			return {
				...state,
				fields: state.fields.map( ( f ) => ( f.id === action.id ? { ...f, ...action.patch } : f ) ),
			};
		case 'REMOVE_FIELD': {
			const fields = state.fields
				.filter( ( f ) => f.id !== action.id )
				.map( ( f ) => {
					if ( Array.isArray( f.conditions ) && f.conditions.some( ( r ) => r.field === action.id ) ) {
						return { ...f, conditions: f.conditions.filter( ( r ) => r.field !== action.id ) };
					}
					return f;
				} );
			return { ...state, fields, selectedId: state.selectedId === action.id ? null : state.selectedId };
		}
		case 'DUPLICATE_FIELD': {
			const idx = state.fields.findIndex( ( f ) => f.id === action.id );
			if ( idx === -1 ) {
				return state;
			}
			const copy = { ...JSON.parse( JSON.stringify( state.fields[ idx ] ) ), id: action.newId };
			if ( copy.label ) {
				copy.label += ' (copy)';
			}
			if ( Array.isArray( copy.options ) ) {
				// Duplicated options must get NEW slugs server-side, or conditions would couple the twins.
				copy.options = copy.options.map( ( { value, ...rest } ) => rest );
			}
			const fields = [ ...state.fields ];
			fields.splice( idx + 1, 0, copy );
			return { ...state, fields, selectedId: copy.id };
		}
		case 'REORDER': {
			const fields = [ ...state.fields ];
			if ( action.to < 0 || action.to >= fields.length ) {
				return state;
			}
			const [ moved ] = fields.splice( action.from, 1 );
			fields.splice( action.to, 0, moved );
			return { ...state, fields };
		}
		case 'SELECT':
			return { ...state, selectedId: action.id };
		case 'UPDATE_SETTINGS':
			return { ...state, settings: { ...state.settings, ...action.patch } };
		case 'HYDRATE':
			return {
				...state,
				fields: Array.isArray( action.fields ) ? action.fields : [],
				settings: action.settings && typeof action.settings === 'object' ? action.settings : {},
			};
		default:
			return state;
	}
}

export const actions = {
	addField: ( fieldType ) => ( { type: 'ADD_FIELD', fieldType, id: makeId() } ),
	updateField: ( id, patch ) => ( { type: 'UPDATE_FIELD', id, patch } ),
	removeField: ( id ) => ( { type: 'REMOVE_FIELD', id } ),
	duplicateField: ( id ) => ( { type: 'DUPLICATE_FIELD', id, newId: makeId() } ),
	reorder: ( from, to ) => ( { type: 'REORDER', from, to } ),
	selectField: ( id ) => ( { type: 'SELECT', id } ),
	updateSettings: ( patch ) => ( { type: 'UPDATE_SETTINGS', patch } ),
	hydrate: ( fields, settings ) => ( { type: 'HYDRATE', fields, settings } ),
};

export const selectors = {
	getFields: ( state ) => state.fields,
	getSettings: ( state ) => state.settings,
	getSelected: ( state ) => state.fields.find( ( f ) => f.id === state.selectedId ) || null,
	getSelectedId: ( state ) => state.selectedId,
};
