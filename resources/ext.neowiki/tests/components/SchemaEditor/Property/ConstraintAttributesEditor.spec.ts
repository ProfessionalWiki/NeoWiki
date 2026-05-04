import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { CdxField, CdxTextInput } from '@wikimedia/codex';
import ConstraintAttributesEditor from '@/components/SchemaEditor/Property/ConstraintAttributesEditor.vue';
import { newTextProperty, TextProperty } from '@/domain/propertyTypes/Text';
import { newUrlProperty, UrlProperty } from '@/domain/propertyTypes/Url';
import { newNumberProperty, NumberProperty } from '@/domain/propertyTypes/Number';
import { newDateTimeProperty, DateTimeProperty } from '@/domain/propertyTypes/DateTime';
import { newSelectProperty, SelectProperty } from '@/domain/propertyTypes/Select';
import { newRelationProperty, RelationProperty } from '@/domain/propertyTypes/Relation';
import { fromLocalInputValue, toLocalInputValue } from '@/domain/propertyTypes/dateTimeConversion';
import { createTestWrapper, FieldProps, setupMwMock } from '../../../VueTestHelpers';

describe( 'ConstraintAttributesEditor', () => {

	beforeEach( () => {
		setupMwMock( {
			messages: {
				'neowiki-property-editor-multiple': 'Multiple',
				'neowiki-property-editor-unique-items': 'Unique items',
				'neowiki-property-editor-character-length': 'Character length',
				'neowiki-property-editor-range': 'Range',
				'neowiki-property-editor-minimum': 'Minimum',
				'neowiki-property-editor-maximum': 'Maximum',
				'neowiki-property-editor-length-whole-number': 'Must be a whole number of at least 1.',
				'neowiki-property-editor-length-min-exceeds-max': 'Minimum cannot exceed maximum.',
				'neowiki-property-editor-min-exceeds-max': 'Minimum cannot exceed maximum.',
			},
			functions: [ 'message' ],
		} );
	} );

	function mountWith<P extends { type: string }>(
		property: P,
	): VueWrapper {
		return createTestWrapper( ConstraintAttributesEditor, { property } );
	}

	function getRangeFieldProps( wrapper: VueWrapper, slot: 'min' | 'max' ): FieldProps {
		const fields = wrapper.findAllComponents( CdxField );
		return fields[ slot === 'min' ? fields.length - 2 : fields.length - 1 ].props() as FieldProps;
	}

	describe( 'with TextProperty', () => {

		it( 'renders multiple, unique-items toggles, and integer-range inputs', () => {
			const wrapper = mountWith<TextProperty>( newTextProperty( { multiple: true } ) );
			const toggles = wrapper.findAll( 'input[type="checkbox"]' );
			expect( toggles ).toHaveLength( 2 );
			expect( wrapper.findAllComponents( CdxTextInput ) ).toHaveLength( 2 );
		} );

		it( 'hides unique-items toggle when multiple is false', () => {
			const wrapper = mountWith<TextProperty>( newTextProperty( { multiple: false } ) );
			expect( wrapper.findAll( 'input[type="checkbox"]' ) ).toHaveLength( 1 );
		} );

		it( 'displays existing minLength and maxLength', () => {
			const wrapper = mountWith<TextProperty>(
				newTextProperty( { minLength: 5, maxLength: 100 } ),
			);
			const inputs = wrapper.findAllComponents( CdxTextInput );
			expect( inputs[ 0 ].props( 'modelValue' ) ).toBe( '5' );
			expect( inputs[ 1 ].props( 'modelValue' ) ).toBe( '100' );
		} );

		it( 'shows error on minLength field for zero', async () => {
			const wrapper = mountWith<TextProperty>( newTextProperty() );
			const inputs = wrapper.findAllComponents( CdxTextInput );
			await inputs[ 0 ].vm.$emit( 'update:modelValue', '0' );
			expect( getRangeFieldProps( wrapper, 'min' ).status ).toBe( 'error' );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'shows error on maxLength field for decimals', async () => {
			const wrapper = mountWith<TextProperty>( newTextProperty() );
			const inputs = wrapper.findAllComponents( CdxTextInput );
			await inputs[ 1 ].vm.$emit( 'update:modelValue', '5.5' );
			expect( getRangeFieldProps( wrapper, 'max' ).status ).toBe( 'error' );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'shows min-exceeds-max error', async () => {
			const wrapper = mountWith<TextProperty>( newTextProperty( { maxLength: 5 } ) );
			const inputs = wrapper.findAllComponents( CdxTextInput );
			await inputs[ 0 ].vm.$emit( 'update:modelValue', '10' );
			expect( getRangeFieldProps( wrapper, 'min' ).status ).toBe( 'error' );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'emits update on valid integer change', async () => {
			const wrapper = mountWith<TextProperty>( newTextProperty() );
			const inputs = wrapper.findAllComponents( CdxTextInput );
			await inputs[ 0 ].vm.$emit( 'update:modelValue', '5' );
			expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { minLength: 5 } ] ] );
		} );

		it( 'emits update with undefined when input cleared', async () => {
			const wrapper = mountWith<TextProperty>( newTextProperty( { minLength: 5 } ) );
			const inputs = wrapper.findAllComponents( CdxTextInput );
			await inputs[ 0 ].vm.$emit( 'update:modelValue', '' );
			expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { minLength: undefined } ] ] );
		} );

		it( 'displays existing multiple and uniqueItems', () => {
			const wrapper = mountWith<TextProperty>(
				newTextProperty( { multiple: true, uniqueItems: false } ),
			);
			const toggles = wrapper.findAll( 'input[type="checkbox"]' );
			expect( ( toggles[ 0 ].element as HTMLInputElement ).checked ).toBe( true );
			expect( ( toggles[ 1 ].element as HTMLInputElement ).checked ).toBe( false );
		} );

		it( 'shows no error when both length fields are empty', () => {
			const wrapper = mountWith<TextProperty>( newTextProperty() );
			expect( getRangeFieldProps( wrapper, 'min' ).status ).toBe( 'default' );
			expect( getRangeFieldProps( wrapper, 'max' ).status ).toBe( 'default' );
		} );

		it( 'shows error on minLength for negative numbers', async () => {
			const wrapper = mountWith<TextProperty>( newTextProperty() );
			const inputs = wrapper.findAllComponents( CdxTextInput );
			await inputs[ 0 ].vm.$emit( 'update:modelValue', '-5' );
			expect( getRangeFieldProps( wrapper, 'min' ).status ).toBe( 'error' );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'shows error on minLength for non-numeric input', async () => {
			const wrapper = mountWith<TextProperty>( newTextProperty() );
			const inputs = wrapper.findAllComponents( CdxTextInput );
			await inputs[ 0 ].vm.$emit( 'update:modelValue', 'abc' );
			expect( getRangeFieldProps( wrapper, 'min' ).status ).toBe( 'error' );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'shows error on maxLength when max is less than min', async () => {
			const wrapper = mountWith<TextProperty>( newTextProperty( { minLength: 10 } ) );
			const inputs = wrapper.findAllComponents( CdxTextInput );
			await inputs[ 1 ].vm.$emit( 'update:modelValue', '5' );
			expect( getRangeFieldProps( wrapper, 'max' ).status ).toBe( 'error' );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'allows minLength equal to maxLength', async () => {
			const wrapper = mountWith<TextProperty>( newTextProperty( { maxLength: 5 } ) );
			const inputs = wrapper.findAllComponents( CdxTextInput );
			await inputs[ 0 ].vm.$emit( 'update:modelValue', '5' );
			expect( getRangeFieldProps( wrapper, 'min' ).status ).toBe( 'default' );
			expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
		} );

		it( 'clears min error when valid value resolves it', async () => {
			const wrapper = mountWith<TextProperty>( newTextProperty() );
			const inputs = wrapper.findAllComponents( CdxTextInput );
			await inputs[ 0 ].vm.$emit( 'update:modelValue', '0' );
			expect( getRangeFieldProps( wrapper, 'min' ).status ).toBe( 'error' );
			await inputs[ 0 ].vm.$emit( 'update:modelValue', '3' );
			expect( getRangeFieldProps( wrapper, 'min' ).status ).toBe( 'default' );
			expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
		} );

		it( 'emits maxLength on valid integer change', async () => {
			const wrapper = mountWith<TextProperty>( newTextProperty() );
			const inputs = wrapper.findAllComponents( CdxTextInput );
			await inputs[ 1 ].vm.$emit( 'update:modelValue', '10' );
			expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { maxLength: 10 } ] ] );
		} );

		it( 'emits maxLength as undefined when cleared', async () => {
			const wrapper = mountWith<TextProperty>( newTextProperty( { maxLength: 10 } ) );
			const inputs = wrapper.findAllComponents( CdxTextInput );
			await inputs[ 1 ].vm.$emit( 'update:modelValue', '' );
			expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { maxLength: undefined } ] ] );
		} );

		it( 'emits update on multiple toggle', async () => {
			const wrapper = mountWith<TextProperty>( newTextProperty() );
			const toggle = wrapper.findAll( 'input[type="checkbox"]' )[ 0 ];
			await toggle.setValue( true );
			expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { multiple: true } ] ] );
		} );

		it( 'emits update on uniqueItems toggle when multiple is true', async () => {
			const wrapper = mountWith<TextProperty>( newTextProperty( { multiple: true } ) );
			const toggle = wrapper.findAll( 'input[type="checkbox"]' )[ 1 ];
			await toggle.setValue( false );
			expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { uniqueItems: false } ] ] );
		} );

	} );

	describe( 'with UrlProperty', () => {

		it( 'renders only the two toggles, no range inputs', () => {
			const wrapper = mountWith<UrlProperty>( newUrlProperty( { multiple: true } ) );
			expect( wrapper.findAll( 'input[type="checkbox"]' ) ).toHaveLength( 2 );
			expect( wrapper.findAllComponents( CdxTextInput ) ).toHaveLength( 0 );
		} );

		it( 'displays existing multiple and uniqueItems', () => {
			const wrapper = mountWith<UrlProperty>(
				newUrlProperty( { multiple: true, uniqueItems: false } ),
			);
			const toggles = wrapper.findAll( 'input[type="checkbox"]' );
			expect( ( toggles[ 0 ].element as HTMLInputElement ).checked ).toBe( true );
			expect( ( toggles[ 1 ].element as HTMLInputElement ).checked ).toBe( false );
		} );

		it( 'emits update on uniqueItems toggle', async () => {
			const wrapper = mountWith<UrlProperty>( newUrlProperty( { multiple: true, uniqueItems: true } ) );
			const toggles = wrapper.findAll( 'input[type="checkbox"]' );
			await toggles[ 1 ].setValue( false );
			expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { uniqueItems: false } ] ] );
		} );

	} );

	describe( 'with NumberProperty', () => {

		it( 'renders only numeric range inputs', () => {
			const wrapper = mountWith<NumberProperty>( newNumberProperty() );
			expect( wrapper.findAll( 'input[type="checkbox"]' ) ).toHaveLength( 0 );
			expect( wrapper.findAllComponents( CdxTextInput ) ).toHaveLength( 2 );
		} );

		it( 'displays existing minimum and maximum', () => {
			const wrapper = mountWith<NumberProperty>(
				newNumberProperty( { minimum: -5, maximum: 100 } ),
			);
			const inputs = wrapper.findAllComponents( CdxTextInput );
			expect( inputs[ 0 ].props( 'modelValue' ) ).toBe( '-5' );
			expect( inputs[ 1 ].props( 'modelValue' ) ).toBe( '100' );
		} );

		it( 'accepts negative numbers (no positive-integer restriction)', async () => {
			const wrapper = mountWith<NumberProperty>( newNumberProperty() );
			const inputs = wrapper.findAllComponents( CdxTextInput );
			await inputs[ 0 ].vm.$emit( 'update:modelValue', '-10' );
			expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { minimum: -10 } ] ] );
		} );

		it( 'shows min-exceeds-max error', async () => {
			const wrapper = mountWith<NumberProperty>( newNumberProperty( { maximum: 5 } ) );
			const inputs = wrapper.findAllComponents( CdxTextInput );
			await inputs[ 0 ].vm.$emit( 'update:modelValue', '10' );
			expect( getRangeFieldProps( wrapper, 'min' ).status ).toBe( 'error' );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

	} );

	describe( 'with DateTimeProperty', () => {

		it( 'renders datetime-local range inputs', () => {
			const wrapper = mountWith<DateTimeProperty>( newDateTimeProperty() );
			expect( wrapper.findAll( 'input[type="checkbox"]' ) ).toHaveLength( 0 );
			expect( wrapper.findAllComponents( CdxTextInput ) ).toHaveLength( 2 );
		} );

		it( 'renders datetime-local input type', () => {
			const wrapper = mountWith<DateTimeProperty>( newDateTimeProperty() );
			const inputs = wrapper.findAllComponents( CdxTextInput );
			expect( inputs[ 0 ].props( 'inputType' ) ).toBe( 'datetime-local' );
			expect( inputs[ 1 ].props( 'inputType' ) ).toBe( 'datetime-local' );
		} );

		it( 'displays minimum and maximum as host-local wall-clock for the prop ISOs', () => {
			const minimum = '2020-01-01T00:00:00Z';
			const maximum = '2025-12-31T23:00:00Z';
			const wrapper = mountWith<DateTimeProperty>(
				newDateTimeProperty( { minimum, maximum } ),
			);
			const inputs = wrapper.findAllComponents( CdxTextInput );
			expect( inputs[ 0 ].props( 'modelValue' ) ).toBe( toLocalInputValue( minimum ) );
			expect( inputs[ 1 ].props( 'modelValue' ) ).toBe( toLocalInputValue( maximum ) );
		} );

		it( 'displays empty inputs when minimum and maximum are undefined', () => {
			const wrapper = mountWith<DateTimeProperty>( newDateTimeProperty() );
			const inputs = wrapper.findAllComponents( CdxTextInput );
			expect( inputs[ 0 ].props( 'modelValue' ) ).toBe( '' );
			expect( inputs[ 1 ].props( 'modelValue' ) ).toBe( '' );
		} );

		it( 'shows min-exceeds-max error', async () => {
			const wrapper = mountWith<DateTimeProperty>(
				newDateTimeProperty( { maximum: '2025-01-01T00:00:00Z' } ),
			);
			const inputs = wrapper.findAllComponents( CdxTextInput );
			await inputs[ 0 ].vm.$emit( 'update:modelValue', '2026-01-01T00:00' );
			expect( getRangeFieldProps( wrapper, 'min' ).status ).toBe( 'error' );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'shows error on max field when max is less than min', async () => {
			const wrapper = mountWith<DateTimeProperty>(
				newDateTimeProperty( { minimum: '2025-01-01T00:00:00Z' } ),
			);
			const inputs = wrapper.findAllComponents( CdxTextInput );
			await inputs[ 1 ].vm.$emit( 'update:modelValue', '2024-01-01T00:00' );
			expect( getRangeFieldProps( wrapper, 'max' ).status ).toBe( 'error' );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'allows min equal to max', async () => {
			const maximum = '2025-01-01T00:00:00Z';
			const minLocalValue = toLocalInputValue( maximum );
			const wrapper = mountWith<DateTimeProperty>(
				newDateTimeProperty( { maximum } ),
			);
			const inputs = wrapper.findAllComponents( CdxTextInput );
			await inputs[ 0 ].vm.$emit( 'update:modelValue', minLocalValue );
			expect( getRangeFieldProps( wrapper, 'min' ).status ).toBe( 'default' );
			expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
		} );

		it( 'clears min error when valid value resolves it', async () => {
			const wrapper = mountWith<DateTimeProperty>(
				newDateTimeProperty( { maximum: '2020-01-01T00:00:00Z' } ),
			);
			const inputs = wrapper.findAllComponents( CdxTextInput );
			await inputs[ 0 ].vm.$emit( 'update:modelValue', '2030-01-01T00:00' );
			expect( getRangeFieldProps( wrapper, 'min' ).status ).toBe( 'error' );
			await inputs[ 0 ].vm.$emit( 'update:modelValue', '2010-01-01T00:00' );
			expect( getRangeFieldProps( wrapper, 'min' ).status ).toBe( 'default' );
		} );

		it( 'emits minimum as UTC ISO from typed local', async () => {
			const wrapper = mountWith<DateTimeProperty>( newDateTimeProperty() );
			const inputs = wrapper.findAllComponents( CdxTextInput );
			const local = '2025-06-15T12:00';
			await inputs[ 0 ].vm.$emit( 'update:modelValue', local );
			expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { minimum: fromLocalInputValue( local ) } ] ] );
		} );

		it( 'emits maximum as UTC ISO from typed local', async () => {
			const wrapper = mountWith<DateTimeProperty>( newDateTimeProperty() );
			const inputs = wrapper.findAllComponents( CdxTextInput );
			const local = '2025-06-15T12:00';
			await inputs[ 1 ].vm.$emit( 'update:modelValue', local );
			expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { maximum: fromLocalInputValue( local ) } ] ] );
		} );

		it( 'emits undefined minimum when min input cleared', async () => {
			const wrapper = mountWith<DateTimeProperty>(
				newDateTimeProperty( { minimum: '2020-01-01T00:00:00Z' } ),
			);
			const inputs = wrapper.findAllComponents( CdxTextInput );
			await inputs[ 0 ].vm.$emit( 'update:modelValue', '' );
			expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { minimum: undefined } ] ] );
		} );

		it( 'emits undefined maximum when max input cleared', async () => {
			const wrapper = mountWith<DateTimeProperty>(
				newDateTimeProperty( { maximum: '2025-12-31T23:59:00Z' } ),
			);
			const inputs = wrapper.findAllComponents( CdxTextInput );
			await inputs[ 1 ].vm.$emit( 'update:modelValue', '' );
			expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { maximum: undefined } ] ] );
		} );

	} );

	describe( 'with SelectProperty', () => {

		it( 'renders only the multiple toggle', () => {
			const wrapper = mountWith<SelectProperty>(
				newSelectProperty( { options: [ { id: 'a', label: 'A' } ] } ),
			);
			expect( wrapper.findAll( 'input[type="checkbox"]' ) ).toHaveLength( 1 );
			expect( wrapper.findAllComponents( CdxTextInput ) ).toHaveLength( 0 );
		} );

	} );

	describe( 'with RelationProperty', () => {

		it( 'renders nothing — empty getConstraintAttributes()', () => {
			const wrapper = mountWith<RelationProperty>( newRelationProperty() );
			expect( wrapper.findAll( 'input[type="checkbox"]' ) ).toHaveLength( 0 );
			expect( wrapper.findAllComponents( CdxTextInput ) ).toHaveLength( 0 );
		} );

	} );

} );
