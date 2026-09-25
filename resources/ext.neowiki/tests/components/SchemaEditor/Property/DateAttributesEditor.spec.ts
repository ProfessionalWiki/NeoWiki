import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { CdxSelect } from '@wikimedia/codex';
import DateAttributesEditor from '@/components/SchemaEditor/Property/DateAttributesEditor.vue';
import DateTextInput from '@/components/Value/DateTextInput.vue';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';
import { newDateProperty, DateProperty } from '@/domain/propertyTypes/Date';
import { AttributesEditorExposes, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { createTestWrapper, FieldProps, setupMwMock } from '../../../VueTestHelpers.ts';

describe( 'DateAttributesEditor', () => {
	beforeEach( () => {
		setupMwMock( {
			config: { wgUserLanguage: 'en' },
			messages: {
				'neowiki-property-editor-min-exceeds-max': 'Minimum cannot exceed maximum.',
				'neowiki-field-unreadable-date': ( text: string ) => `not a date: ${ text }`,
			},
			functions: [ 'config', 'message', 'language' ],
		} );
	} );

	function newWrapper( props: Partial<AttributesEditorProps<DateProperty>> = {} ): VueWrapper {
		return createTestWrapper( DateAttributesEditor, {
			property: newDateProperty( {} ),
			...props,
		} );
	}

	// Types and leaves the field: unreadable text is reported once the editing ends.
	async function typeInto( wrapper: VueWrapper, bound: string, text: string ): Promise<void> {
		const input = wrapper.find( `.date-attributes__${ bound } .ext-neowiki-date-text-input__text input` );
		await input.trigger( 'focusin' );
		await input.setValue( text );
		await input.trigger( 'focusout', { relatedTarget: document.body } );
	}

	const MINIMUM = 0;
	const MAXIMUM = 1;

	async function enterBound( wrapper: VueWrapper, bound: number, iso: string | undefined ): Promise<void> {
		wrapper.findAllComponents( DateTextInput )[ bound ].vm.$emit( 'update:modelValue', iso );
		await wrapper.vm.$nextTick();
	}

	function getMinimumFieldProps( wrapper: VueWrapper ): FieldProps {
		return ( wrapper.findComponent( '.date-attributes__minimum' ) as VueWrapper ).props() as FieldProps;
	}

	function getMaximumFieldProps( wrapper: VueWrapper ): FieldProps {
		return ( wrapper.findComponent( '.date-attributes__maximum' ) as VueWrapper ).props() as FieldProps;
	}

	describe( 'displaying existing values', () => {
		it( 'passes the stored minimum and maximum to the two date inputs', () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { minimum: '1900', maximum: '2030-12-31' } ),
			} );
			const inputs = wrapper.findAllComponents( DateTextInput );

			expect( inputs[ MINIMUM ].props( 'modelValue' ) ).toBe( '1900' );
			expect( inputs[ MAXIMUM ].props( 'modelValue' ) ).toBe( '2030-12-31' );
		} );

		it( 'passes undefined when minimum and maximum are unset', () => {
			const inputs = newWrapper().findAllComponents( DateTextInput );

			expect( inputs[ MINIMUM ].props( 'modelValue' ) ).toBeUndefined();
			expect( inputs[ MAXIMUM ].props( 'modelValue' ) ).toBeUndefined();
		} );
	} );

	describe( 'range validation', () => {
		it( 'shows no error when both fields are empty', () => {
			const wrapper = newWrapper();

			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'default' );
			expect( getMaximumFieldProps( wrapper ).status ).toBe( 'default' );
		} );

		it( 'shows error on min field when min exceeds max', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { maximum: '2020-01-01' } ),
			} );

			await enterBound( wrapper, MINIMUM, '2030-01-01' );

			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'error' );
			expect( getMinimumFieldProps( wrapper ).messages ).toEqual( {
				error: 'Minimum cannot exceed maximum.',
			} );
			expect( getMaximumFieldProps( wrapper ).status ).toBe( 'default' );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'shows error on max field when max is less than min', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { minimum: '2030-01-01' } ),
			} );

			await enterBound( wrapper, MAXIMUM, '2020-01-01' );

			expect( getMaximumFieldProps( wrapper ).status ).toBe( 'error' );
			expect( getMaximumFieldProps( wrapper ).messages ).toEqual( {
				error: 'Minimum cannot exceed maximum.',
			} );
			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'default' );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'allows min equal to max', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { maximum: '2020-01-01' } ),
			} );

			await enterBound( wrapper, MINIMUM, '2020-01-01' );

			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'default' );
			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { minimum: '2020-01-01', maximum: '2020-01-01' } ] );
		} );

		it( 'clears min error when valid value resolves conflict', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { maximum: '2020-01-01' } ),
			} );

			await enterBound( wrapper, MINIMUM, '2030-01-01' );
			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'error' );

			await enterBound( wrapper, MINIMUM, '2010-01-01' );
			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'default' );
		} );

		it( 'clears max error when valid min resolves conflict', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { minimum: '2030-01-01' } ),
			} );

			await enterBound( wrapper, MAXIMUM, '2020-01-01' );
			expect( getMaximumFieldProps( wrapper ).status ).toBe( 'error' );

			await enterBound( wrapper, MINIMUM, '2010-01-01' );
			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'default' );
			expect( getMaximumFieldProps( wrapper ).status ).toBe( 'default' );
		} );
	} );

	describe( 'bounds of year or month precision', () => {
		it( 'allows a minimum year that holds the maximum day', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { maximum: '1990-06-30' } ),
			} );

			await enterBound( wrapper, MINIMUM, '1990' );

			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'default' );
			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { minimum: '1990', maximum: '1990-06-30' } ] );
		} );

		it( 'rejects a minimum year after the maximum year', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { maximum: '1990' } ),
			} );

			await enterBound( wrapper, MINIMUM, '1991' );

			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'error' );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'says nothing about the text of a bound while it is still being typed', async () => {
			const wrapper = newWrapper();
			const input = wrapper.find( '.date-attributes__minimum .ext-neowiki-date-text-input__text input' );

			await input.trigger( 'focusin' );
			await input.setValue( '198x' );

			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'default' );
		} );

		it( 'shows why the text of a bound does not read as a date, and keeps the stored bound', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { minimum: '1990' } ),
			} );

			await typeInto( wrapper, 'minimum', '198x' );

			expect( getMinimumFieldProps( wrapper ).messages ).toEqual( { error: 'not a date: 198x' } );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );
	} );

	describe( 'bounds the Schema does not hold yet', () => {
		const heldMessage = ( wrapper: VueWrapper ): string | null =>
			( wrapper.vm as unknown as AttributesEditorExposes ).unparseableInputMessage!();

		it( 'emits a held-back minimum together with the maximum that makes room for it', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { minimum: '2020', maximum: '2030' } ),
			} );

			await enterBound( wrapper, MINIMUM, '2040' );
			await enterBound( wrapper, MAXIMUM, '2050' );

			expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { minimum: '2040', maximum: '2050' } ] ] );
		} );

		it( 'checks a new maximum against the stored minimum while the minimum text does not read as a date', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { minimum: '2020', maximum: '2030' } ),
			} );

			await typeInto( wrapper, 'minimum', '20x' );
			await enterBound( wrapper, MAXIMUM, '2010' );

			expect( getMaximumFieldProps( wrapper ).messages ).toEqual( { error: 'Minimum cannot exceed maximum.' } );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'keeps the conflict on a held-back maximum when the minimum text stops reading as a date', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { minimum: '2020' } ),
			} );

			await enterBound( wrapper, MAXIMUM, '2010' );
			await typeInto( wrapper, 'minimum', '20x' );

			expect( getMaximumFieldProps( wrapper ).messages ).toEqual( { error: 'Minimum cannot exceed maximum.' } );
		} );

		it( 'keeps the stored maximum while the maximum text does not read as a date', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { maximum: '1990' } ),
			} );

			await typeInto( wrapper, 'maximum', '198x' );

			expect( getMaximumFieldProps( wrapper ).messages ).toEqual( { error: 'not a date: 198x' } );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'keeps showing why the maximum text does not read as a date when the minimum changes', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { maximum: '1990' } ),
			} );

			await typeInto( wrapper, 'maximum', '198x' );
			await enterBound( wrapper, MINIMUM, '1980' );

			expect( getMaximumFieldProps( wrapper ).messages ).toEqual( { error: 'not a date: 198x' } );
		} );

		it( 'reports the message of parts that do not form a date, so the save is held', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { minimum: '1990' } ),
			} );

			await typeInto( wrapper, 'minimum', '198x' );

			expect( heldMessage( wrapper ) ).toBe( 'not a date: 198x' );
		} );

		it( 'reports the conflict of a held-back bound, so the save is held', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { maximum: '2020' } ),
			} );

			await enterBound( wrapper, MINIMUM, '2030' );

			expect( heldMessage( wrapper ) ).toBe( 'Minimum cannot exceed maximum.' );
		} );

		it( 'reports no message while the Schema holds what the bounds show', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { maximum: '2020' } ),
			} );

			await enterBound( wrapper, MINIMUM, '2010' );

			expect( heldMessage( wrapper ) ).toBeNull();
		} );
	} );

	describe( 'minPrecision', () => {
		const select = ( wrapper: VueWrapper ): VueWrapper<InstanceType<typeof CdxSelect>> =>
			wrapper.findComponent( '.date-attributes__min-precision' ).findComponent( CdxSelect );

		it( 'shows a year as enough when the property has no minPrecision', () => {
			expect( select( newWrapper() ).props( 'selected' ) ).toBe( 'year' );
		} );

		it( 'shows the stored minPrecision', () => {
			const wrapper = newWrapper( { property: newDateProperty( { minPrecision: 'month' } ) } );

			expect( select( wrapper ).props( 'selected' ) ).toBe( 'month' );
		} );

		it( 'emits the chosen minPrecision', async () => {
			const wrapper = newWrapper();

			await select( wrapper ).vm.$emit( 'update:selected', 'day' );

			expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { minPrecision: 'day' } ] ] );
		} );

		it( 'emits undefined when a year is chosen as enough', async () => {
			const wrapper = newWrapper( { property: newDateProperty( { minPrecision: 'day' } ) } );

			await select( wrapper ).vm.$emit( 'update:selected', 'year' );

			expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { minPrecision: undefined } ] ] );
		} );

		it( 'shows the severity of the minPrecision', () => {
			const wrapper = newWrapper( {
				property: { ...newDateProperty( { minPrecision: 'day' } ), constraintSeverities: { minPrecision: 'error' } },
			} );

			expect(
				wrapper.findComponent( '.date-attributes__min-precision' ).findComponent( SeverityInput ).props( 'modelValue' ),
			).toBe( 'error' );
		} );

		it( 'emits the changed severity of the minPrecision, keeping a bound\'s', async () => {
			const wrapper = newWrapper( {
				property: {
					...newDateProperty( { minimum: '1900', minPrecision: 'day' } ),
					constraintSeverities: { minimum: 'error' },
				},
			} );

			await wrapper.findComponent( '.date-attributes__min-precision' ).findComponent( SeverityInput )
				.vm.$emit( 'update:modelValue', 'error' );

			expect( wrapper.emitted( 'update:property' ) ).toEqual( [
				[ { constraintSeverities: { minimum: 'error', minPrecision: 'error' } } ],
			] );
		} );

		it( 'offers a severity only once a minPrecision is set', () => {
			expect( newWrapper().findAllComponents( SeverityInput ) ).toHaveLength( 0 );
			expect(
				newWrapper( { property: newDateProperty( { minPrecision: 'day' } ) } ).findAllComponents( SeverityInput ),
			).toHaveLength( 1 );
		} );
	} );

	describe( 'emitting updates', () => {
		it( 'emits minimum as the typed date string', async () => {
			const wrapper = newWrapper();

			await enterBound( wrapper, MINIMUM, '2020-01-01' );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { minimum: '2020-01-01', maximum: undefined } ] );
		} );

		it( 'emits maximum as the typed date string', async () => {
			const wrapper = newWrapper();

			await enterBound( wrapper, MAXIMUM, '2030-12-31' );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { minimum: undefined, maximum: '2030-12-31' } ] );
		} );

		it( 'emits undefined minimum when the min input is cleared', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { minimum: '2020-01-01' } ),
			} );

			await enterBound( wrapper, MINIMUM, undefined );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { minimum: undefined, maximum: undefined } ] );
		} );

		it( 'emits undefined maximum when the max input is cleared', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { maximum: '2030-12-31' } ),
			} );

			await enterBound( wrapper, MAXIMUM, undefined );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { minimum: undefined, maximum: undefined } ] );
		} );
	} );
	describe( 'Constraint severity', () => {
		it( 'offers a severity only for a set bound, showing the current one', () => {
			const wrapper = newWrapper( {
				property: { ...newDateProperty( { maximum: '2030-12-31' } ), constraintSeverities: { maximum: 'error' } },
			} );

			const inputs = wrapper.findAllComponents( SeverityInput );
			expect( inputs ).toHaveLength( 1 );
			expect( inputs[ 0 ].props( 'modelValue' ) ).toBe( 'error' );
		} );

		it( 'emits the changed severity of a bound, keeping the other bound\'s', async () => {
			const wrapper = newWrapper( {
				property: { ...newDateProperty( { minimum: '2020-01-01', maximum: '2030-12-31' } ), constraintSeverities: { maximum: 'error' } },
			} );

			await wrapper.findAllComponents( SeverityInput )[ 0 ].vm.$emit( 'update:modelValue', 'error' );

			expect( wrapper.emitted( 'update:property' ) ).toEqual( [
				[ { constraintSeverities: { maximum: 'error', minimum: 'error' } } ],
			] );
		} );
	} );
} );
