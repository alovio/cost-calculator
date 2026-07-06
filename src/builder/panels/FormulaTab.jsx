import FormulaPanel from '../FormulaPanel';

/** Formula tab = existing live-validated editor; errors also surface as canvas badges (same validator). */
export default function FormulaTab( { field, fields, set } ) {
	return <FormulaPanel field={ field } fields={ fields } set={ set } />;
}
