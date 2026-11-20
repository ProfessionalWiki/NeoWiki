import { enableAutoUnmount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { CdxButton, CdxField } from '@wikimedia/codex';
import NumberDisplayAttributesEditor from '@/components/LayoutEditor/DisplayAttributes/NumberDisplayAttributesEditor.vue';
import NeoNativeInput from '@/components/common/NeoNativeInput.vue';
import type { DisplayAttributesEditorProps } from '@/components/LayoutEditor/DisplayAttributes/DisplayAttributesEditorContract.ts';
import { newNumberProperty, type NumberProperty } from '@/domain/propertyTypes/Number.ts';
import { createTestWrapper, type FieldProps, reportUnparseableNumber, setupMwMock } from '../../../VueTestHelpers.ts';

enableAutoUnmount( afterEach );

describe( 'NumberDisplayAttributesEditor', () => {
	beforeEach( () => {
		setupMwMock( {
			messages: {
				'neowiki-property-editor-precision-non-negative': 'Precision cannot be negative.',
			},
			functions: [ 'config', 'message' ],
		} );
	} );

	function newWrapper( props: Partial<DisplayAttributesEditorProps<NumberProperty>> = {} ): VueWrapper {
		return createTestWrapper( NumberDisplayAttributesEditor, {
			property: newNumberProperty( {} ),
			displayAttributes: {},
			...props,
		} );
	}

	function precisionInput( wrapper: VueWrapper ): HTMLInputElement {
		return wrapper.find( 'input' ).element as HTMLInputElement;
	}

	async function typePrecision( wrapper: VueWrapper, text: string ): Promise<void> {
		await wrapper.find( 'input' ).setValue( text );
	}

	function precisionField( wrapper: VueWrapper ): FieldProps {
		return wrapper.findComponent( CdxField ).props() as FieldProps;
	}

	function resetButton( wrapper: VueWrapper ): VueWrapper {
		return wrapper.findComponent( CdxButton );
	}

	function emittedAttributes( wrapper: VueWrapper ): unknown[] {
		return ( wrapper.emitted( 'update:displayAttributes' ) ?? [] ).map( ( [ attributes ] ) => attributes );
	}

	it( 'shows the precision the Layout sets', () => {
		const wrapper = newWrapper( {
			property: newNumberProperty( { precision: 3 } ),
			displayAttributes: { precision: 1 },
		} );

		expect( precisionInput( wrapper ).value ).toBe( '1' );
	} );

	it( 'leaves the field empty, holding the Schema\'s precision as its placeholder, when the Layout sets none', () => {
		const wrapper = newWrapper( { property: newNumberProperty( { precision: 3 } ) } );

		expect( precisionInput( wrapper ).value ).toBe( '' );
		expect( precisionInput( wrapper ).placeholder ).toBe( '3' );
	} );

	it( 'says an empty field takes the Schema\'s precision', () => {
		const wrapper = newWrapper( { property: newNumberProperty( { precision: 3 } ) } );

		expect( wrapper.text() ).toContain( 'neowiki-display-attribute-precision-from-schema3' );
	} );

	it( 'says an empty field shows numbers as entered when the Schema sets no precision', () => {
		const wrapper = newWrapper();

		expect( wrapper.text() ).toContain( 'neowiki-display-attribute-precision-as-entered' );
	} );

	it( 'sets the precision typed', async () => {
		const wrapper = newWrapper();

		await typePrecision( wrapper, '2' );

		expect( emittedAttributes( wrapper ) ).toEqual( [ { precision: 2 } ] );
	} );

	it( 'sets no precision once the field is emptied', async () => {
		const wrapper = newWrapper( { displayAttributes: { precision: 1 } } );

		await typePrecision( wrapper, '' );

		expect( emittedAttributes( wrapper ) ).toStrictEqual( [ { precision: undefined } ] );
	} );

	it( 'refuses a negative precision, saying why', async () => {
		const wrapper = newWrapper( { displayAttributes: { precision: 1 } } );

		await typePrecision( wrapper, '-1' );

		expect( precisionField( wrapper ).messages ).toEqual( { error: 'Precision cannot be negative.' } );
		expect( emittedAttributes( wrapper ) ).toEqual( [] );
	} );

	it( 'sets a precision of zero', async () => {
		const wrapper = newWrapper();

		await typePrecision( wrapper, '0' );

		expect( emittedAttributes( wrapper ) ).toEqual( [ { precision: 0 } ] );
	} );

	it( 'lets go of its error once a precision it can set is typed', async () => {
		const wrapper = newWrapper();

		await typePrecision( wrapper, '-1' );
		await typePrecision( wrapper, '2' );

		expect( precisionField( wrapper ).messages ).toEqual( {} );
	} );

	it( 'says so when the field holds text that is no number', async () => {
		const wrapper = newWrapper();

		await wrapper.findComponent( NeoNativeInput ).vm.$emit( 'unparseable-change', 'Not a number' );

		expect( precisionField( wrapper ).messages ).toEqual( { error: 'Not a number' } );
	} );

	it( 'offers no reset while the Layout sets no precision', () => {
		const wrapper = newWrapper();

		expect( resetButton( wrapper ).exists() ).toBe( false );
	} );

	it( 'empties a field holding text that is no number when the precision is reset', async () => {
		const wrapper = newWrapper( { displayAttributes: { precision: 1 } } );
		await reportUnparseableNumber( wrapper.find( 'input' ) );

		await resetButton( wrapper ).trigger( 'click' );

		expect( precisionField( wrapper ).messages ).toEqual( {} );
	} );

	it( 'leaves the focus in the field once the precision is reset', async () => {
		const wrapper = newWrapper( { displayAttributes: { precision: 1 } } );
		document.body.appendChild( wrapper.element );

		await resetButton( wrapper ).trigger( 'click' );

		expect( document.activeElement ).toBe( wrapper.find( 'input' ).element );
	} );

	it( 'resets the precision to the Schema\'s', async () => {
		const wrapper = newWrapper( { displayAttributes: { precision: 1 } } );

		await resetButton( wrapper ).trigger( 'click' );

		expect( emittedAttributes( wrapper ) ).toStrictEqual( [ { precision: undefined } ] );
	} );

} );
