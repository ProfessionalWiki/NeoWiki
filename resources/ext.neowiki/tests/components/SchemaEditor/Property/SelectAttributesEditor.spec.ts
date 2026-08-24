import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { CdxChipInput } from '@wikimedia/codex';
import SelectAttributesEditor from '@/components/SchemaEditor/Property/SelectAttributesEditor.vue';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';
import { newSelectProperty, SelectProperty } from '@/domain/propertyTypes/Select';
import { AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { createTestWrapper, setupMwMock } from '../../../VueTestHelpers.ts';

describe( 'SelectAttributesEditor', () => {
	beforeEach( () => {
		setupMwMock( {
			messages: {
				'neowiki-property-editor-options-unique': 'Options must be unique.',
			},
			functions: [ 'config', 'message' ],
		} );
	} );

	function newWrapper( props: Partial<AttributesEditorProps<SelectProperty>> = {} ): VueWrapper {
		return createTestWrapper( SelectAttributesEditor, {
			property: newSelectProperty( {} ),
			...props,
		} );
	}

	it( 'displays existing options as chips', () => {
		const wrapper = newWrapper( {
			property: newSelectProperty( {
				options: [
					{ id: 'open', label: 'Open' },
					{ id: 'closed', label: 'Closed' },
				],
			} ),
		} );

		expect( wrapper.findComponent( CdxChipInput ).props( 'inputChips' ) ).toEqual( [
			{ value: 'Open' },
			{ value: 'Closed' },
		] );
	} );

	it( 'emits options when the chips change', async () => {
		const wrapper = newWrapper();

		await wrapper.findComponent( CdxChipInput ).vm.$emit( 'update:input-chips', [
			{ value: 'Draft' },
			{ value: 'Final' },
		] );

		expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ {
			options: [
				{ id: 'Draft', label: 'Draft' },
				{ id: 'Final', label: 'Final' },
			],
		} ] );
	} );

	it( 'emits multiple when the checkbox is toggled', async () => {
		const wrapper = newWrapper();

		await wrapper.find( 'input[type="checkbox"]' ).setValue( true );

		expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { multiple: true } ] );
	} );
	describe( 'Constraint severity', () => {
		function severityInputIn( wrapper: VueWrapper, selector: string ): VueWrapper<InstanceType<typeof SeverityInput>> {
			return ( wrapper.findComponent( selector ) as VueWrapper ).findComponent( SeverityInput );
		}

		it( 'shows the current severity of the options', () => {
			const wrapper = newWrapper( {
				property: { ...newSelectProperty( { options: [ { id: 'open', label: 'Open' } ] } ), constraintSeverities: { options: 'error' } },
			} );

			expect( severityInputIn( wrapper, '.select-attributes__options' ).props( 'modelValue' ) ).toBe( 'error' );
		} );

		it( 'offers no severity while there are no options', () => {
			const wrapper = newWrapper( { property: newSelectProperty( { options: [] } ) } );

			expect( severityInputIn( wrapper, '.select-attributes__options' ).exists() ).toBe( false );
		} );

		it( 'emits the changed severity of the options', async () => {
			const wrapper = newWrapper( { property: newSelectProperty( { options: [ { id: 'open', label: 'Open' } ] } ) } );

			await severityInputIn( wrapper, '.select-attributes__options' ).vm.$emit( 'update:modelValue', 'error' );

			expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { constraintSeverities: { options: 'error' } } ] ] );
		} );

		it( 'offers a severity for the single-value rule while multiple values are not allowed', () => {
			const wrapper = newWrapper( { property: newSelectProperty( { multiple: false } ) } );

			expect( severityInputIn( wrapper, '.select-attributes__multiple' ).exists() ).toBe( true );
		} );

		it( 'offers no severity for the single-value rule once multiple values are allowed', () => {
			const wrapper = newWrapper( { property: newSelectProperty( { multiple: true } ) } );

			expect( severityInputIn( wrapper, '.select-attributes__multiple' ).exists() ).toBe( false );
		} );

		it( 'names the single-value rule rather than the checkbox that switches it off', () => {
			const wrapper = newWrapper( { property: newSelectProperty( { multiple: false } ) } );

			expect( severityInputIn( wrapper, '.select-attributes__multiple' ).props( 'constraint' ) )
				.toBe( 'neowiki-property-editor-single-value' );
		} );

		it( 'shows the current severity of the single-value rule', () => {
			const wrapper = newWrapper( {
				property: { ...newSelectProperty( { multiple: false } ), constraintSeverities: { multiple: 'error' } },
			} );

			expect( severityInputIn( wrapper, '.select-attributes__multiple' ).props( 'modelValue' ) ).toBe( 'error' );
		} );

		it( 'emits the changed severity of the single-value rule, keeping the options\' annotation', async () => {
			const wrapper = newWrapper( {
				property: { ...newSelectProperty( { options: [ { id: 'open', label: 'Open' } ] } ), constraintSeverities: { options: 'error' } },
			} );

			await severityInputIn( wrapper, '.select-attributes__multiple' ).vm.$emit( 'update:modelValue', 'error' );

			expect( wrapper.emitted( 'update:property' ) ).toEqual( [
				[ { constraintSeverities: { options: 'error', multiple: 'error' } } ],
			] );
		} );

		it( 'removes the annotation of the single-value rule when it goes back to warning', async () => {
			const wrapper = newWrapper( {
				property: { ...newSelectProperty( { multiple: false } ), constraintSeverities: { multiple: 'error' } },
			} );

			await severityInputIn( wrapper, '.select-attributes__multiple' ).vm.$emit( 'update:modelValue', 'warning' );

			expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { constraintSeverities: undefined } ] ] );
		} );
	} );
} );
