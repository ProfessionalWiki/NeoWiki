import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import UrlAttributesEditor from '@/components/SchemaEditor/Property/UrlAttributesEditor.vue';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';
import { newUrlProperty, UrlProperty } from '@/domain/propertyTypes/Url';
import { AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { createTestWrapper, setupMwMock } from '../../../VueTestHelpers.ts';

describe( 'UrlAttributesEditor', () => {
	beforeEach( () => {
		setupMwMock();
	} );

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
			const checkboxes = wrapper.findAll( 'input[type="checkbox"]' );

			expect( ( checkboxes[ 0 ].element as HTMLInputElement ).checked ).toBe( true );
			expect( ( checkboxes[ 1 ].element as HTMLInputElement ).checked ).toBe( false );
		} );
	} );

	describe( 'conditional display', () => {
		it( 'hides uniqueItems checkbox when multiple is false', () => {
			const wrapper = newWrapper( {
				property: newUrlProperty( { multiple: false } ),
			} );
			const checkboxes = wrapper.findAll( 'input[type="checkbox"]' );

			expect( checkboxes ).toHaveLength( 1 );
		} );

		it( 'shows uniqueItems checkbox when multiple is true', () => {
			const wrapper = newWrapper( {
				property: newUrlProperty( { multiple: true } ),
			} );
			const checkboxes = wrapper.findAll( 'input[type="checkbox"]' );

			expect( checkboxes ).toHaveLength( 2 );
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
			const checkboxes = wrapper.findAll( 'input[type="checkbox"]' );

			await checkboxes[ 1 ].setValue( false );

			expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { uniqueItems: false } ] );
		} );
	} );

	describe( 'Constraint severity', () => {
		function uniqueItemsSeverityInput( wrapper: VueWrapper ): VueWrapper<InstanceType<typeof SeverityInput>> {
			return ( wrapper.findComponent( '.url-attributes__unique-items' ) as VueWrapper ).findComponent( SeverityInput );
		}

		it( 'shows the current severity of unique values', () => {
			const wrapper = newWrapper( {
				property: { ...newUrlProperty( { multiple: true, uniqueItems: true } ), constraintSeverities: { uniqueItems: 'error' } },
			} );

			expect( uniqueItemsSeverityInput( wrapper ).props( 'modelValue' ) ).toBe( 'error' );
		} );

		it( 'offers no severity while unique values are not required', () => {
			const wrapper = newWrapper( { property: newUrlProperty( { multiple: true, uniqueItems: false } ) } );

			expect( uniqueItemsSeverityInput( wrapper ).exists() ).toBe( false );
		} );

		it( 'emits the changed severity of unique values', async () => {
			const wrapper = newWrapper( { property: newUrlProperty( { multiple: true, uniqueItems: true } ) } );

			await uniqueItemsSeverityInput( wrapper ).vm.$emit( 'update:modelValue', 'error' );

			expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { constraintSeverities: { uniqueItems: 'error' } } ] ] );
		} );
	} );
} );
