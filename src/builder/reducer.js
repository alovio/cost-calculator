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
	slider: { label: 'Slider', min: 0, max: 100, step: 1, default: 0, unit: '', showInSummary: true },
	select: { label: 'Dropdown', options: [ { label: 'Option 1', price: 0 } ], showInSummary: false },
	radio: { label: 'Choose one', options: [ { label: 'Option 1', price: 0 } ], showInSummary: false },
	checkbox_group: { label: 'Check all that apply', options: [ { label: 'Option 1', price: 0 } ], showInSummary: false },
	toggle: { label: 'Toggle', price: 0, default: false, showInSummary: false },
	quantity: { label: 'Quantity', min: 0, max: null, step: 1, default: 1, showInSummary: false },
	text: { label: 'Text', placeholder: '', showInSummary: false },
	date: { label: 'Date', placeholder: '', showInSummary: false },
	email: { label: 'Email', placeholder: '', showInSummary: false },
	phone: { label: 'Phone', placeholder: '', showInSummary: false },
	url: { label: 'Website', placeholder: '', showInSummary: false },
	textarea: { label: 'Notes', placeholder: '', showInSummary: false },
	heading: { label: 'Section heading', showInSummary: false },
	html: { label: 'Content', content: '', showInSummary: false },
	formula: { label: 'Total', expression: '', showInSummary: true },
	step: { label: 'Step', description: '', showInSummary: false },
	repeater: { label: 'Repeater', fields: [], minRows: 1, maxRows: 10, addLabel: '', rowLabel: 'Row {n}', rowExpression: '', showInSummary: true },
};

export const REPEATER_CHILD_TYPES = [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity' ];

let counter = 0;
export function makeId() {
	counter += 1;
	return `fld_${ counter }_${ Math.random().toString( 36 ).slice( 2, 7 ) }`;
}

export const HISTORY_LIMIT = 50;

export const initialState = { name: '', fields: [], settings: {}, selectedId: null, past: [], future: [] };

function cloneOptions( options ) {
	return Array.isArray( options ) ? options.map( ( o ) => ( { ...o } ) ) : undefined;
}

/** What undo/redo restores (spec §2.6): structure, settings and the calculator name. */
function snapshot( state ) {
	return { name: state.name, fields: state.fields, settings: state.settings };
}

/** Push the current snapshot before a mutating action (bounded). */
function remember( state ) {
	const past = [ ...state.past, snapshot( state ) ];
	if ( past.length > HISTORY_LIMIT ) {
		past.shift();
	}
	return past;
}

function makeField( fieldType, id ) {
	const defaults = DEFAULTS[ fieldType ] || DEFAULTS.text;
	const field = { id, type: fieldType, ...defaults };
	if ( defaults.options ) {
		field.options = cloneOptions( defaults.options );
	}
	return field;
}

function clampIndex( index, length ) {
	const i = typeof index === 'number' && ! Number.isNaN( index ) ? index : length;
	return Math.max( 0, Math.min( i, length ) );
}

/** Keep the selection only if the restored snapshot still contains that field. */
function keepSelection( fields, selectedId ) {
	return fields.some( ( f ) => f.id === selectedId ) ? selectedId : null;
}

/** Apply fn to one repeater parent's field, recording history (child edits are undoable). */
function mapParent( state, parentId, fn ) {
	return {
		...state,
		past: remember( state ),
		future: [],
		fields: state.fields.map( ( f ) => ( f.id === parentId && f.type === 'repeater' ? fn( f ) : f ) ),
	};
}

export function reducer( state = initialState, action = {} ) {
	switch ( action.type ) {
		case 'ADD_FIELD': {
			const field = makeField( action.fieldType, action.id );
			return { ...state, past: remember( state ), future: [], fields: [ ...state.fields, field ], selectedId: field.id };
		}
		case 'UPDATE_FIELD':
			return {
				...state,
				past: remember( state ),
				future: [],
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
			return { ...state, past: remember( state ), future: [], fields, selectedId: state.selectedId === action.id ? null : state.selectedId };
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
			return { ...state, past: remember( state ), future: [], fields, selectedId: copy.id };
		}
		case 'REORDER': {
			const fields = [ ...state.fields ];
			if ( action.to < 0 || action.to >= fields.length ) {
				return state;
			}
			const [ moved ] = fields.splice( action.from, 1 );
			fields.splice( action.to, 0, moved );
			return { ...state, past: remember( state ), future: [], fields };
		}
		case 'SELECT':
			return { ...state, selectedId: action.id };
		case 'UPDATE_SETTINGS':
			return { ...state, past: remember( state ), future: [], settings: { ...state.settings, ...action.patch } };
		case 'INSERT_AT': {
			const field = makeField( action.fieldType, action.id );
			const fields = [ ...state.fields ];
			fields.splice( clampIndex( action.index, fields.length ), 0, field );
			return { ...state, past: remember( state ), future: [], fields, selectedId: field.id };
		}
		case 'INSERT_FIELDS': {
			if ( ! Array.isArray( action.fields ) || ! action.fields.length ) {
				return state;
			}
			const fields = [ ...state.fields ];
			fields.splice( clampIndex( action.index, fields.length ), 0, ...action.fields );
			return { ...state, past: remember( state ), future: [], fields, selectedId: action.fields[ 0 ].id };
		}
		case 'SET_NAME':
			return { ...state, past: remember( state ), future: [], name: String( action.name ?? '' ) };
		case 'UNDO': {
			if ( ! state.past.length ) {
				return state;
			}
			const past = [ ...state.past ];
			const prev = past.pop();
			return {
				...state,
				...prev,
				past,
				future: [ snapshot( state ), ...state.future ],
				selectedId: keepSelection( prev.fields, state.selectedId ),
			};
		}
		case 'REDO': {
			if ( ! state.future.length ) {
				return state;
			}
			const [ next, ...future ] = state.future;
			return {
				...state,
				...next,
				past: remember( state ),
				future,
				selectedId: keepSelection( next.fields, state.selectedId ),
			};
		}
		case 'HYDRATE':
			return {
				...state,
				name: typeof action.name === 'string' ? action.name : '',
				fields: Array.isArray( action.fields ) ? action.fields : [],
				settings: action.settings && typeof action.settings === 'object' ? action.settings : {},
				past: [],
				future: [],
			};
		case 'ADD_CHILD_FIELD': {
			if ( ! REPEATER_CHILD_TYPES.includes( action.fieldType ) ) {
				return state;
			}
			const defaults = DEFAULTS[ action.fieldType ];
			const child = { id: action.id, type: action.fieldType, ...defaults };
			if ( defaults.options ) {
				child.options = cloneOptions( defaults.options );
			}
			return mapParent( state, action.parentId, ( p ) => ( { ...p, fields: [ ...( p.fields || [] ), child ] } ) );
		}
		case 'UPDATE_CHILD_FIELD':
			return mapParent( state, action.parentId, ( p ) => ( {
				...p,
				fields: ( p.fields || [] ).map( ( c ) => ( c.id === action.id ? { ...c, ...action.patch } : c ) ),
			} ) );
		case 'REMOVE_CHILD_FIELD':
			return mapParent( state, action.parentId, ( p ) => ( {
				...p,
				fields: ( p.fields || [] ).filter( ( c ) => c.id !== action.id ),
			} ) );
		case 'REORDER_CHILD':
			return mapParent( state, action.parentId, ( p ) => {
				const children = [ ...( p.fields || [] ) ];
				if ( action.to < 0 || action.to >= children.length || action.from < 0 || action.from >= children.length ) {
					return p;
				}
				const [ moved ] = children.splice( action.from, 1 );
				children.splice( action.to, 0, moved );
				return { ...p, fields: children };
			} );
		default:
			return state;
	}
}

/**
 * Remap template-local ids to fresh unique ids, rewriting intra-template
 * condition references. Refs to ids outside the template and option `value`
 * slugs are left untouched. Never mutates its input.
 */
export function remapFields( templateFields ) {
	const idMap = {};
	const fields = ( templateFields || [] ).map( ( f ) => {
		const copy = JSON.parse( JSON.stringify( f ) );
		idMap[ copy.id ] = makeId();
		copy.id = idMap[ copy.id ];
		return copy;
	} );
	fields.forEach( ( f ) => {
		if ( Array.isArray( f.conditions ) ) {
			f.conditions = f.conditions.map( ( r ) => ( r.field && idMap[ r.field ] ? { ...r, field: idMap[ r.field ] } : r ) );
		}
	} );
	return fields;
}

export const actions = {
	addField: ( fieldType ) => ( { type: 'ADD_FIELD', fieldType, id: makeId() } ),
	updateField: ( id, patch ) => ( { type: 'UPDATE_FIELD', id, patch } ),
	removeField: ( id ) => ( { type: 'REMOVE_FIELD', id } ),
	duplicateField: ( id ) => ( { type: 'DUPLICATE_FIELD', id, newId: makeId() } ),
	reorder: ( from, to ) => ( { type: 'REORDER', from, to } ),
	selectField: ( id ) => ( { type: 'SELECT', id } ),
	updateSettings: ( patch ) => ( { type: 'UPDATE_SETTINGS', patch } ),
	insertAt: ( fieldType, index ) => ( { type: 'INSERT_AT', fieldType, index, id: makeId() } ),
	insertFields: ( templateFields, index ) => ( { type: 'INSERT_FIELDS', fields: remapFields( templateFields ), index } ),
	undo: () => ( { type: 'UNDO' } ),
	redo: () => ( { type: 'REDO' } ),
	setName: ( name ) => ( { type: 'SET_NAME', name } ),
	hydrate: ( fields, settings, name ) => ( { type: 'HYDRATE', fields, settings, name } ),
	addChildField: ( parentId, fieldType ) => ( { type: 'ADD_CHILD_FIELD', parentId, fieldType, id: makeId() } ),
	updateChildField: ( parentId, id, patch ) => ( { type: 'UPDATE_CHILD_FIELD', parentId, id, patch } ),
	removeChildField: ( parentId, id ) => ( { type: 'REMOVE_CHILD_FIELD', parentId, id } ),
	reorderChild: ( parentId, from, to ) => ( { type: 'REORDER_CHILD', parentId, from, to } ),
};

export const selectors = {
	getFields: ( state ) => state.fields,
	getSettings: ( state ) => state.settings,
	getSelected: ( state ) => state.fields.find( ( f ) => f.id === state.selectedId ) || null,
	getSelectedId: ( state ) => state.selectedId,
	getName: ( state ) => state.name,
	canUndo: ( state ) => state.past.length > 0,
	canRedo: ( state ) => state.future.length > 0,
};
