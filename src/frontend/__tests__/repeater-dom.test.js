/** @jest-environment jsdom */
import { setupRepeaters } from '../repeater';
import { collectRawValues } from '../calculator';

const FIELD = {
	id: 'rooms', type: 'repeater', label: 'Rooms', minRows: 1, maxRows: 2, rowLabel: 'Room {n}',
	fields: [
		{ id: 'r_area', type: 'number', label: 'Area' },
		{ id: 'r_rate', type: 'radio', label: 'Rate', options: [ { value: 'opt_a', label: 'A', price: 1 } ] },
	],
};

const row = ( index ) => `
	<div class="alc-repeater__row" data-alc-row>
		<div class="alc-repeater__row-head"><span data-alc-row-label>Room ${ index }</span><button type="button" data-alc-remove>×</button></div>
		<div class="alc-repeater__row-fields">
			<div data-alc-child="r_area"><label>Area<input type="number" value="5"></label></div>
			<div data-alc-child="r_rate"><fieldset><label><input type="radio" name="alc_rooms_r_rate_${ index }" value="opt_a"></label></fieldset></div>
		</div>
	</div>`;

function mount() {
	document.body.innerHTML = `
		<div class="alc-calculator">
			<div class="alc-field alc-field--repeater" data-alc-field="rooms"><fieldset class="alc-repeater">
				<div class="alc-repeater__rows" data-alc-rows>${ row( 1 ) }</div>
				<template data-alc-row-template>${ row( '__ROW__' ) }</template>
				<button type="button" data-alc-add>Add row</button>
			</fieldset></div>
		</div>`;
	return document.querySelector( '.alc-calculator' );
}

describe( 'repeater DOM behaviour', () => {
	it( 'adds rows from the template up to maxRows, renumbering names and labels', () => {
		const root = mount();
		const onChange = jest.fn();
		setupRepeaters( root, [ FIELD ], onChange );
		const add = root.querySelector( '[data-alc-add]' );

		add.click();
		const rows = root.querySelectorAll( '[data-alc-rows] [data-alc-row]' );
		expect( rows ).toHaveLength( 2 );
		expect( rows[ 1 ].querySelector( '[data-alc-row-label]' ).textContent ).toBe( 'Room 2' );
		expect( rows[ 1 ].querySelector( 'input[type="radio"]' ).name ).toBe( 'alc_rooms_r_rate_2' );
		expect( add.disabled ).toBe( true ); // maxRows reached
		expect( onChange ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'removes rows down to minRows and hides remove buttons at the floor', () => {
		const root = mount();
		setupRepeaters( root, [ FIELD ], jest.fn() );
		root.querySelector( '[data-alc-add]' ).click();
		root.querySelector( '[data-alc-rows] [data-alc-row] [data-alc-remove]' ).click();
		const rows = root.querySelectorAll( '[data-alc-rows] [data-alc-row]' );
		expect( rows ).toHaveLength( 1 );
		expect( rows[ 0 ].querySelector( '[data-alc-remove]' ).hidden ).toBe( true );
		expect( rows[ 0 ].querySelector( '[data-alc-row-label]' ).textContent ).toBe( 'Room 1' );
	} );

	it( 'collectRawValues returns one object per row keyed by child id', () => {
		const root = mount();
		setupRepeaters( root, [ FIELD ], jest.fn() );
		root.querySelector( '[data-alc-add]' ).click();
		root.querySelectorAll( '[data-alc-child="r_area"] input' )[ 1 ].value = '9';
		root.querySelectorAll( 'input[type="radio"]' )[ 1 ].checked = true;
		const raw = collectRawValues( root, [ FIELD ] );
		expect( raw.rooms ).toEqual( [ { r_area: '5', r_rate: '' }, { r_area: '9', r_rate: 'opt_a' } ] );
	} );
} );
