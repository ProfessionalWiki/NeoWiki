import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { CdxField, CdxTextInput, ValidationMessages, ValidationStatusType } from '@wikimedia/codex';
import NumberAttributesEditor from '@/components/SchemaEditor/Property/NumberAttributesEditor.vue';
import { newNumberProperty, NumberProperty } from '@/domain/propertyTypes/Number';
import { AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { createTestWrapper, setupMwMock } from '../../../VueTestHelpers.ts';

interface FieldProps {
	status: ValidationStatusType;
	messages: ValidationMessages;
}

describe( 'NumberAttributesEditor', () => {
	beforeEach( () => {
		setupMwMock( {
			messages: {
				'neowiki-property-editor-precision-non-negative': 'Precision cannot be negative.',
			},
			functions: [ 'message' ],
		} );
	} );

	function newWrapper( props: Partial<AttributesEditorProps<NumberProperty>> = {} ): VueWrapper {
		return createTestWrapper( NumberAttributesEditor, {
			property: newNumberProperty( {} ),
			...props,
		} );
	}

	it( 'displays property values correctly', () => {
		const wrapper = newWrapper( {
			property: newNumberProperty( { minimum: 5, maximum: 100, precision: 2 } ),
		} );

		const inputs = wrapper.findAllComponents( CdxTextInput );
		const modelValues = inputs.map( ( input ) => input.props( 'modelValue' ) );

		expect( modelValues ).toContain( '5' );
		expect( modelValues ).toContain( '100' );
		expect( modelValues ).toContain( '2' );
	} );

	function getPrecisionFieldProps( wrapper: VueWrapper ): FieldProps {
		const fields = wrapper.findAllComponents( CdxField );
		return fields[ 2 ].props() as FieldProps;
	}

	it( 'shows error and does not emit when precision is negative', async () => {
		const wrapper = newWrapper();
		const inputs = wrapper.findAllComponents( CdxTextInput );

		await inputs[ 2 ].vm.$emit( 'update:model-value', '-5' );

		const fieldProps = getPrecisionFieldProps( wrapper );
		expect( fieldProps.status ).toBe( 'error' );
		expect( fieldProps.messages ).toEqual( { error: 'Precision cannot be negative.' } );
		expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
	} );

	it( 'shows no error when precision is zero or positive', async () => {
		const wrapper = newWrapper();
		const inputs = wrapper.findAllComponents( CdxTextInput );

		await inputs[ 2 ].vm.$emit( 'update:model-value', '0' );
		expect( getPrecisionFieldProps( wrapper ).status ).toBe( 'default' );

		await inputs[ 2 ].vm.$emit( 'update:model-value', '5' );
		expect( getPrecisionFieldProps( wrapper ).status ).toBe( 'default' );
	} );

	it( 'emits update event when minimum is changed', async () => {
		const wrapper = newWrapper();

		const inputs = wrapper.findAllComponents( CdxTextInput );
		await inputs[ 0 ].vm.$emit( 'update:model-value', '10' );

		expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
		expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { minimum: 10 } ] );
	} );

	it( 'emits update event when maximum is changed', async () => {
		const wrapper = newWrapper();

		const inputs = wrapper.findAllComponents( CdxTextInput );
		await inputs[ 1 ].vm.$emit( 'update:model-value', '50' );

		expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
		expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { maximum: 50 } ] );
	} );

	it( 'emits update event when precision is changed', async () => {
		const wrapper = newWrapper();

		const inputs = wrapper.findAllComponents( CdxTextInput );
		await inputs[ 2 ].vm.$emit( 'update:model-value', '3' );

		expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
		expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { precision: 3 } ] );
	} );

	it( 'emits undefined when input is cleared', async () => {
		const wrapper = newWrapper( {
			property: newNumberProperty( { minimum: 10 } ),
		} );

		const inputs = wrapper.findAllComponents( CdxTextInput );
		await inputs[ 0 ].vm.$emit( 'update:model-value', '' );

		expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
		expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { minimum: undefined } ] );
	} );
} );
