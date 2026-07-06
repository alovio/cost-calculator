import { useState } from '@wordpress/element';
import StudioShell from './StudioShell';
import CalculatorList from './CalculatorList';
import EntriesList from './EntriesList';

export default function App() {
	const [ view, setView ] = useState( 'list' );
	const [ calculatorId, setCalculatorId ] = useState( null );

	if ( view === 'builder' && calculatorId ) {
		return <StudioShell calculatorId={ calculatorId } onBack={ () => setView( 'list' ) } />;
	}
	if ( view === 'entries' ) {
		return <EntriesList onBack={ () => setView( 'list' ) } />;
	}
	return (
		<CalculatorList
			onEdit={ ( id ) => {
				setCalculatorId( id );
				setView( 'builder' );
			} }
			onEntries={ () => setView( 'entries' ) }
		/>
	);
}
