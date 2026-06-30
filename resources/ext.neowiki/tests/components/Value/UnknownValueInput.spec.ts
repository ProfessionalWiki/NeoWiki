import { mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import UnknownValueInput from '@/components/Value/UnknownValueInput.vue';
import { newUnknownValue, type Value } from '@/domain/Value';
import { PropertyName, type PropertyDefinition } from '@/domain/PropertyDefinition';
import { ValueInputExposes } from '@/components/Value/ValueInputContract.ts';

function unknownProperty( type: string ): PropertyDefinition {
	return { name: new PropertyName( 'brandColour' ), type, description: '', required: false };
}

function createWrapper( modelValue: Value | undefined, typeName = 'color' ): VueWrapper {
	return mount( UnknownValueInput, {
		props: {
			modelValue: modelValue,
			label: 'Brand colour',
			property: unknownProperty( typeName ),
		},
	} );
}

describe( 'UnknownValueInput', () => {

	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			msg: vi.fn( ( key: string, ...params: string[] ) => `${ key }:${ params.join( ',' ) }` ),
		} );
	} );

	it( 'renders the field label', () => {
		const wrapper = createWrapper( newUnknownValue( 'color', '#ff0000' ) );

		expect( wrapper.text() ).toContain( 'Brand colour' );
	} );

	it( 'renders the raw stored value', () => {
		const wrapper = createWrapper( newUnknownValue( 'color', '#ff0000' ) );

		expect( wrapper.text() ).toContain( '#ff0000' );
	} );

	it( 'shows a note that the unknown type cannot be edited', () => {
		const wrapper = createWrapper( newUnknownValue( 'color', '#ff0000' ), 'color' );

		expect( wrapper.text() ).toContain( 'neowiki-property-type-unknown-input-note:color' );
	} );

	it( 'preserves the original value via getCurrentValue so it round-trips on save', () => {
		const modelValue = newUnknownValue( 'color', { hex: '#ff0000' } );
		const wrapper = createWrapper( modelValue );

		expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() ).toStrictEqual( modelValue );
	} );

	it( 'does not emit value updates because it is read-only', () => {
		const wrapper = createWrapper( newUnknownValue( 'color', '#ff0000' ) );

		expect( wrapper.emitted( 'update:modelValue' ) ).toBeUndefined();
	} );

} );
