import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { CdxField, CdxTextInput } from '@wikimedia/codex';
import NumberAttributesEditor from '@/components/SchemaEditor/Property/NumberAttributesEditor.vue';
import ConstraintAttributesEditor from '@/components/SchemaEditor/Property/ConstraintAttributesEditor.vue';
import PrecisionInput from '@/components/SchemaEditor/Property/PrecisionInput.vue';
import { newNumberProperty, NumberProperty } from '@/domain/propertyTypes/Number';
import { AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract';
import { createTestWrapper, FieldProps, setupMwMock } from '../../../VueTestHelpers';

describe( 'NumberAttributesEditor', () => {

	beforeEach( () => {
		setupMwMock( {
			messages: {
				'neowiki-property-editor-precision': 'Precision',
				'neowiki-property-editor-precision-non-negative': 'Precision must be a non-negative whole number.',
				'neowiki-property-editor-range': 'Range',
				'neowiki-property-editor-minimum': 'Minimum',
				'neowiki-property-editor-maximum': 'Maximum',
				'neowiki-property-editor-min-exceeds-max': 'Minimum cannot exceed maximum.',
			},
			functions: [ 'message' ],
		} );
	} );

	function newWrapper( props: Partial<AttributesEditorProps<NumberProperty>> = {} ): VueWrapper {
		return createTestWrapper( NumberAttributesEditor, {
			property: newNumberProperty(),
			...props,
		} );
	}

	function getPrecisionFieldProps( wrapper: VueWrapper ): FieldProps {
		return wrapper.findComponent( PrecisionInput ).findComponent( CdxField ).props() as FieldProps;
	}

	it( 'renders ConstraintAttributesEditor and PrecisionInput', () => {
		const wrapper = newWrapper();
		expect( wrapper.findComponent( ConstraintAttributesEditor ).exists() ).toBe( true );
		expect( wrapper.findComponent( PrecisionInput ).exists() ).toBe( true );
	} );

	it( 'displays existing precision', () => {
		const wrapper = newWrapper( { property: newNumberProperty( { precision: 2 } ) } );
		const precisionInput = wrapper.findComponent( PrecisionInput ).findComponent( CdxTextInput );
		expect( precisionInput.props( 'modelValue' ) ).toBe( '2' );
	} );

	it( 'accepts precision of 0', async () => {
		const wrapper = newWrapper();
		const precisionInput = wrapper.findComponent( PrecisionInput ).findComponent( CdxTextInput );
		await precisionInput.vm.$emit( 'update:modelValue', '0' );
		expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { precision: 0 } ] ] );
		expect( getPrecisionFieldProps( wrapper ).status ).toBe( 'default' );
	} );

	it( 'shows error for negative precision', async () => {
		const wrapper = newWrapper();
		const precisionInput = wrapper.findComponent( PrecisionInput ).findComponent( CdxTextInput );
		await precisionInput.vm.$emit( 'update:modelValue', '-1' );
		expect( getPrecisionFieldProps( wrapper ).status ).toBe( 'error' );
		expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
	} );

	it( 'shows error for decimal precision', async () => {
		const wrapper = newWrapper();
		const precisionInput = wrapper.findComponent( PrecisionInput ).findComponent( CdxTextInput );
		await precisionInput.vm.$emit( 'update:modelValue', '1.5' );
		expect( getPrecisionFieldProps( wrapper ).status ).toBe( 'error' );
		expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
	} );

	it( 'emits update with undefined when precision cleared', async () => {
		const wrapper = newWrapper( { property: newNumberProperty( { precision: 2 } ) } );
		const precisionInput = wrapper.findComponent( PrecisionInput ).findComponent( CdxTextInput );
		await precisionInput.vm.$emit( 'update:modelValue', '' );
		expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { precision: undefined } ] ] );
	} );

} );
