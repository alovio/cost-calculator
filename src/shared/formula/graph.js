// Line-for-line port of includes/Formula/FormulaGraph.php (Kahn's algorithm).
// idToRefs: formula field id => referenced field ids (non-formula refs ignored).
export function orderFormulas( idToRefs ) {
	const ids = Object.keys( idToRefs );
	const idSet = new Set( ids );
	const indegree = {};
	const edges = {}; // dependency => dependents[]

	ids.forEach( ( id ) => {
		indegree[ id ] = 0;
	} );

	ids.forEach( ( id ) => {
		[ ...new Set( idToRefs[ id ] ) ].forEach( ( ref ) => {
			if ( idSet.has( ref ) && ref !== id ) {
				if ( ! edges[ ref ] ) {
					edges[ ref ] = [];
				}
				edges[ ref ].push( id );
				indegree[ id ]++;
			}
			if ( ref === id ) { // Self-reference is a cycle of one.
				indegree[ id ]++;
			}
		} );
	} );

	const queue = ids.filter( ( id ) => indegree[ id ] === 0 );
	const order = [];

	while ( queue.length ) {
		const id = queue.shift();
		order.push( id );
		( edges[ id ] || [] ).forEach( ( dependent ) => {
			if ( --indegree[ dependent ] === 0 ) {
				queue.push( dependent );
			}
		} );
	}

	const cycles = ids.filter( ( id ) => ! order.includes( id ) );

	return { order, cycles };
}
