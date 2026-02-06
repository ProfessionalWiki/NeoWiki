import { VueWrapper } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import UrlAttributesEditor from '@/components/SchemaEditor/Property/UrlAttributesEditor.vue';
import { newUrlProperty, UrlProperty } from '@/domain/propertyTypes/Url';
import { AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { createTestWrapper } from '../../../VueTestHelpers.ts';

describe( 'UrlAttributesEditor', () => {

	function newWrapper( props: Partial<AttributesEditorProps<UrlProperty>> = {} ): VueWrapper {
		return createTestWrapper( UrlAttributesEditor, {
			property: newUrlProperty( {} ),
			...props,
		} );
	}

	describe( 'displaying existing values', () => {
		it( 'displays existing multiple and uniqueItems', () => {
			const wrapper = newWrapper( {
				property: newUrlProperty( { multiple: true, uniqueItems: false } ),
			} );
			const toggles = wrapper.findAll( 'input[type="checkbox"]' );

			expect( ( toggles[ 0 ].element as HTMLInputElement ).checked ).toBe( true );
			expect( ( toggles[ 1 ].element as HTMLInputElement ).checked ).toBe( false );
		} );
	} );

	describe( 'conditional display', () => {
		it( 'hides uniqueItems toggle when multiple is false', () => {
			const wrapper = newWrapper( {
				property: newUrlProperty( { multiple: false } ),
			} );
			const toggles = wrapper.findAll( 'input[type="checkbox"]' );

			expect( toggles ).toHaveLength( 1 );
		} );

		it( 'shows uniqueItems toggle when multiple is true', () => {
			const wrapper = newWrapper( {
				property: newUrlProperty( { multiple: true } ),
			} );
			const toggles = wrapper.findAll( 'input[type="checkbox"]' );

			expect( toggles ).toHaveLength( 2 );
		} );
	} );

	describe( 'emitting updates', () => {
		it( 'emits update when multiple is toggled', async () => {
			const wrapper = newWrapper();

			await wrapper.find( 'input[type="checkbox"]' ).setValue( true );

			expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { multiple: true } ] );
		} );

		it( 'emits update when uniqueItems is toggled', async () => {
			const wrapper = newWrapper( {
				property: newUrlProperty( { multiple: true, uniqueItems: true } ),
			} );
			const toggles = wrapper.findAll( 'input[type="checkbox"]' );

			await toggles[ 1 ].setValue( false );

			expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { uniqueItems: false } ] );
		} );
	} );

} );
