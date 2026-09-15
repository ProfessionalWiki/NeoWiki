import './../neowiki-test-env.ts';
import { mount, VueWrapper } from '@vue/test-utils';
import { beforeAll, beforeEach, describe, expect, it } from 'vitest';
import ColorDisplay from '@redherb/ColorDisplay.vue';
import { newNumberValue, newStringValue, Value } from '@/domain/Value.ts';
import { setupMwMock } from '../VueTestHelpers.ts';
import { loadRedHerbFrontend, newColorProperty } from './RedHerbRegistration.ts';

const SWATCH = '.ext-redherb-color-display__swatch';
const HEX = '.ext-redherb-color-display__hex';

function newWrapper( value: Value, allowedColors: string[] = [] ): VueWrapper {
	return mount( ColorDisplay, {
		props: {
			value: value,
			property: newColorProperty( { allowedColors: allowedColors } ),
		},
	} );
}

describe( 'ColorDisplay', () => {
	beforeAll( async () => {
		await loadRedHerbFrontend();
	} );

	beforeEach( () => {
		setupMwMock( {
			messages: {
				'redherb-color-invalid-fallback': ( raw: string ) => `not a color: ${ raw }`,
			},
		} );
	} );

	it( 'renders the hex code of a valid color', () => {
		const wrapper = newWrapper( newStringValue( '#ff5733' ) );

		expect( wrapper.find( HEX ).text() ).toBe( '#ff5733' );
	} );

	it( 'paints the swatch with the color', () => {
		const wrapper = newWrapper( newStringValue( '#ff5733' ) );

		expect( wrapper.find( SWATCH ).attributes( 'style' ) ).toContain( 'background-color: rgb(255, 87, 51)' );
	} );

	it( 'accepts uppercase hex digits', () => {
		const wrapper = newWrapper( newStringValue( '#FF5733' ) );

		expect( wrapper.find( HEX ).text() ).toBe( '#FF5733' );
	} );

	it( 'renders the fallback message instead of a swatch for a value that is not a six-digit hex color', () => {
		const wrapper = newWrapper( newStringValue( 'rebeccapurple' ) );

		expect( wrapper.find( SWATCH ).exists() ).toBe( false );
		expect( wrapper.text() ).toContain( 'not a color: rebeccapurple' );
	} );

	it( 'renders the fallback message for three-digit shorthand', () => {
		const wrapper = newWrapper( newStringValue( '#f53' ) );

		expect( wrapper.text() ).toContain( 'not a color: #f53' );
	} );

	it( 'renders the fallback message for a value of the wrong type', () => {
		const wrapper = newWrapper( newNumberValue( 42 ) );

		expect( wrapper.find( SWATCH ).exists() ).toBe( false );
	} );

	it( 'renders a swatch for a stored color the palette no longer allows', () => {
		const wrapper = newWrapper( newStringValue( '#ff5733' ), [ '#000000' ] );

		expect( wrapper.find( HEX ).text() ).toBe( '#ff5733' );
	} );
} );
