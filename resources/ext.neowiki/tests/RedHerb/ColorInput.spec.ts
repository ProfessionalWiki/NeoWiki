import './../neowiki-test-env.ts';
import { VueWrapper } from '@vue/test-utils';
import { beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { CdxField, CdxTextInput } from '@wikimedia/codex';
import ColorInput from '@redherb/ColorInput.vue';
import { newStringValue, StringValue, Value } from '@/domain/Value.ts';
import { PropertyDefinition } from '@/domain/PropertyDefinition.ts';
import { createTestWrapper, setupMwMock } from '../VueTestHelpers.ts';
import { loadRedHerbFrontend, newColorProperty } from './RedHerbRegistration.ts';

const SWATCH = '.ext-redherb-color-input__swatch';
const EMPTY_SWATCH = '.ext-redherb-color-input__swatch--empty';

interface ColorInputExposes {
	getCurrentValue: () => Value | undefined;
}

function newWrapper(
	modelValue: Value | undefined = undefined,
	property: PropertyDefinition = newColorProperty(),
): VueWrapper {
	return createTestWrapper( ColorInput, {
		modelValue: modelValue,
		property: property,
		label: 'Favourite color',
	} );
}

async function typeColor( wrapper: VueWrapper, color: string ): Promise<void> {
	await wrapper.findComponent( CdxTextInput ).vm.$emit( 'update:modelValue', color );
}

function lastEmittedValue( wrapper: VueWrapper ): Value | undefined {
	const emitted = wrapper.emitted( 'update:modelValue' );
	expect( emitted ).toBeTruthy();
	return emitted![ emitted!.length - 1 ][ 0 ] as Value | undefined;
}

describe( 'ColorInput', () => {
	beforeAll( async () => {
		await loadRedHerbFrontend();
	} );

	beforeEach( () => {
		setupMwMock();
	} );

	it( 'renders the label', () => {
		const wrapper = newWrapper();

		expect( wrapper.text() ).toContain( 'Favourite color' );
	} );

	it( 'shows the current color in the text input', () => {
		const wrapper = newWrapper( newStringValue( '#ff5733' ) );

		expect( wrapper.findComponent( CdxTextInput ).props( 'modelValue' ) ).toBe( '#ff5733' );
	} );

	it( 'previews the current color in the swatch', () => {
		const wrapper = newWrapper( newStringValue( '#ff5733' ) );

		expect( wrapper.find( SWATCH ).attributes( 'style' ) ).toContain( 'background-color: rgb(255, 87, 51)' );
	} );

	it( 'marks the swatch as empty when there is no color yet', () => {
		const wrapper = newWrapper();

		expect( wrapper.find( EMPTY_SWATCH ).exists() ).toBe( true );
	} );

	it( 'marks the swatch as empty while the typed color is incomplete', async () => {
		const wrapper = newWrapper();

		await typeColor( wrapper, '#ff57' );

		expect( wrapper.find( EMPTY_SWATCH ).exists() ).toBe( true );
	} );

	it( 'previews the typed color once it is a complete hex color', async () => {
		const wrapper = newWrapper();

		await typeColor( wrapper, '#ff5733' );

		expect( wrapper.find( SWATCH ).attributes( 'style' ) ).toContain( 'background-color: rgb(255, 87, 51)' );
	} );

	it( 'emits the typed color as a string value', async () => {
		const wrapper = newWrapper();

		await typeColor( wrapper, '#ff5733' );

		expect( lastEmittedValue( wrapper ) ).toEqual( newStringValue( '#ff5733' ) );
	} );

	it( 'emits a color the palette does not allow, leaving validation to the backend', async () => {
		const wrapper = newWrapper( undefined, newColorProperty( { allowedColors: [ '#000000' ] } ) );

		await typeColor( wrapper, '#ff5733' );

		expect( lastEmittedValue( wrapper ) ).toEqual( newStringValue( '#ff5733' ) );
	} );

	it( 'emits no value once the color is cleared', async () => {
		const wrapper = newWrapper( newStringValue( '#ff5733' ) );

		await typeColor( wrapper, '' );

		expect( lastEmittedValue( wrapper ) ).toBeUndefined();
	} );

	it( 'exposes the current color to the subject editor', async () => {
		const wrapper = newWrapper();

		await typeColor( wrapper, '#ff5733' );

		const exposed = ( wrapper.vm as unknown as ColorInputExposes ).getCurrentValue();
		expect( ( exposed as StringValue ).parts ).toEqual( [ '#ff5733' ] );
	} );

	it( 'marks the field optional when the property is not required', () => {
		const wrapper = newWrapper( undefined, newColorProperty( { required: false } ) );

		expect( wrapper.findComponent( CdxField ).props( 'optional' ) ).toBe( true );
	} );

	it( 'does not mark the field optional when the property is required', () => {
		const wrapper = newWrapper( undefined, newColorProperty( { required: true } ) );

		expect( wrapper.findComponent( CdxField ).props( 'optional' ) ).toBe( false );
	} );
} );
