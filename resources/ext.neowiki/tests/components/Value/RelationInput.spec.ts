import { mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { CdxField, CdxIcon } from '@wikimedia/codex';
import RelationInput from '@/components/Value/RelationInput.vue';
import SubjectLookup from '@/components/common/SubjectLookup.vue';
import NeoMultiLookupInput from '@/components/common/NeoMultiLookupInput.vue';
import { RelationValue, newRelation } from '@/domain/Value';
import { newRelationProperty, RelationProperty } from '@/domain/propertyTypes/Relation';
import { ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract';
import { NeoWikiTestServices } from '../../NeoWikiTestServices';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers';

describe( 'RelationInput', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'message' ] } );
	} );

	function newWrapper( props: Partial<ValueInputProps<RelationProperty>> = {} ): VueWrapper {
		return mount( RelationInput, {
			props: {
				modelValue: undefined,
				label: 'Test Relation',
				property: newRelationProperty( { targetSchema: 'Company' } ),
				...props,
			},
			global: {
				provide: NeoWikiTestServices.getServices(),
				directives: { tooltip: {} },
				mocks: { $i18n: createI18nMock() },
				stubs: {
					SubjectLookup: true,
					NeoMultiLookupInput: true,
				},
			},
		} );
	}

	describe( 'rendering', () => {
		it( 'renders SubjectLookup for single mode', () => {
			const wrapper = newWrapper();

			expect( wrapper.findComponent( SubjectLookup ).exists() ).toBe( true );
			expect( wrapper.findComponent( NeoMultiLookupInput ).exists() ).toBe( false );
		} );

		it( 'renders NeoMultiLookupInput for multiple mode', () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { multiple: true } ),
			} );

			expect( wrapper.findComponent( NeoMultiLookupInput ).exists() ).toBe( true );
			expect( wrapper.findComponent( SubjectLookup ).exists() ).toBe( false );
		} );

		it( 'renders description icon when property has description', () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { description: 'Pick a company' } ),
			} );

			expect( wrapper.findComponent( CdxIcon ).exists() ).toBe( true );
		} );

		it( 'does not render description icon without description', () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { description: '' } ),
			} );

			expect( wrapper.findComponent( CdxIcon ).exists() ).toBe( false );
		} );
	} );

	describe( 'single mode', () => {
		it( 'passes subject ID from initial value to SubjectLookup', () => {
			const wrapper = newWrapper( {
				modelValue: new RelationValue( [ newRelation( undefined, 's1demo1aaaaaaa1' ) ] ),
			} );

			expect( wrapper.findComponent( SubjectLookup ).props( 'selected' ) ).toBe( 's1demo1aaaaaaa1' );
		} );

		it( 'passes null when no initial value', () => {
			const wrapper = newWrapper();

			expect( wrapper.findComponent( SubjectLookup ).props( 'selected' ) ).toBeNull();
		} );

		it( 'passes targetSchema to SubjectLookup', () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { targetSchema: 'Person' } ),
			} );

			expect( wrapper.findComponent( SubjectLookup ).props( 'targetSchema' ) ).toBe( 'Person' );
		} );

		it( 'emits RelationValue when subject is selected', async () => {
			const wrapper = newWrapper();

			wrapper.findComponent( SubjectLookup ).vm.$emit( 'update:selected', 's1demo1aaaaaaa1' );
			await wrapper.vm.$nextTick();

			const emitted = wrapper.emitted( 'update:modelValue' )!;
			expect( emitted ).toHaveLength( 1 );

			const value = emitted[ 0 ][ 0 ] as RelationValue;
			expect( value.relations ).toHaveLength( 1 );
			expect( value.relations[ 0 ].target.text ).toBe( 's1demo1aaaaaaa1' );
		} );

		it( 'emits undefined when selection is cleared', async () => {
			const wrapper = newWrapper( {
				modelValue: new RelationValue( [ newRelation( undefined, 's1demo1aaaaaaa1' ) ] ),
			} );

			wrapper.findComponent( SubjectLookup ).vm.$emit( 'update:selected', null );
			await wrapper.vm.$nextTick();

			expect( wrapper.emitted( 'update:modelValue' )![ 0 ][ 0 ] ).toBeUndefined();
		} );

		it( 'hides validation error before blur for required empty field', () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { required: true } ),
			} );
			const field = wrapper.findComponent( CdxField );

			expect( field.props( 'messages' ) ).toEqual( {} );
			expect( field.props( 'status' ) ).toBe( 'default' );
		} );

		it( 'passes field status to SubjectLookup', () => {
			const clean = newWrapper( {
				property: newRelationProperty( { name: 'Owner', targetSchema: 'Company' } ),
			} );
			expect( clean.findComponent( SubjectLookup ).props( 'status' ) ).toBe( 'default' );

			const withViolation = newWrapper( {
				property: newRelationProperty( { name: 'Owner', targetSchema: 'Company' } ),
				serverViolations: [
					{ propertyName: 'Owner', code: 'required', args: [], valuePartIndex: null },
				],
			} );
			expect( withViolation.findComponent( SubjectLookup ).props( 'status' ) ).toBe( 'error' );
		} );

		it( 'suppresses required error when SubjectLookup reports unmatched text', async () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { required: true } ),
			} );

			wrapper.findComponent( SubjectLookup ).vm.$emit( 'blur', true );
			await wrapper.vm.$nextTick();

			const field = wrapper.findComponent( CdxField );
			expect( field.props( 'messages' ) ).toEqual( {} );
			expect( field.props( 'status' ) ).toBe( 'default' );
		} );

		it( 'surfaces a server violation on an untouched single relation', () => {
			const wrapper = newWrapper( {
				// The message can only originate from the server-sourced violation.
				property: newRelationProperty( { name: 'Owner', targetSchema: 'Company' } ),
				serverViolations: [
					{ propertyName: 'Owner', code: 'type-mismatch', args: [], valuePartIndex: null },
				],
			} );

			const field = wrapper.findComponent( CdxField );
			expect( field.props( 'messages' ) ).toEqual( { error: 'neowiki-field-type-mismatch' } );
			expect( field.props( 'status' ) ).toBe( 'error' );
		} );

		it( 'keeps a server violation suppressed while the lookup reports unmatched text', async () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { name: 'Owner', targetSchema: 'Company' } ),
				serverViolations: [
					{ propertyName: 'Owner', code: 'type-mismatch', args: [], valuePartIndex: null },
				],
			} );

			wrapper.findComponent( SubjectLookup ).vm.$emit( 'blur', true );
			await wrapper.vm.$nextTick();

			const field = wrapper.findComponent( CdxField );
			expect( field.props( 'messages' ) ).toEqual( {} );
			expect( field.props( 'status' ) ).toBe( 'default' );
		} );
	} );

	describe( 'multiple mode', () => {
		it( 'passes selected IDs to NeoMultiLookupInput', () => {
			const wrapper = newWrapper( {
				modelValue: new RelationValue( [
					newRelation( undefined, 's1demo1aaaaaaa1' ),
					newRelation( undefined, 's1demo5sssssss1' ),
				] ),
				property: newRelationProperty( { multiple: true } ),
			} );

			expect( wrapper.findComponent( NeoMultiLookupInput ).props( 'modelValue' ) ).toEqual(
				[ 's1demo1aaaaaaa1', 's1demo5sssssss1' ],
			);
		} );

		it( 'passes empty array when no initial value', () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { multiple: true } ),
			} );

			expect( wrapper.findComponent( NeoMultiLookupInput ).props( 'modelValue' ) ).toEqual( [] );
		} );

		it( 'emits RelationValue when selections change', async () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { multiple: true } ),
			} );

			wrapper.findComponent( NeoMultiLookupInput ).vm.$emit(
				'update:modelValue', [ 's1demo1aaaaaaa1', 's1demo5sssssss1' ],
			);
			await wrapper.vm.$nextTick();

			const emitted = wrapper.emitted( 'update:modelValue' )!;
			expect( emitted ).toHaveLength( 1 );

			const value = emitted[ 0 ][ 0 ] as RelationValue;
			expect( value.relations ).toHaveLength( 2 );
			expect( value.relations[ 0 ].target.text ).toBe( 's1demo1aaaaaaa1' );
			expect( value.relations[ 1 ].target.text ).toBe( 's1demo5sssssss1' );
		} );

		it( 'emits undefined when all selections are null', async () => {
			const wrapper = newWrapper( {
				modelValue: new RelationValue( [ newRelation( undefined, 's1demo1aaaaaaa1' ) ] ),
				property: newRelationProperty( { multiple: true } ),
			} );

			wrapper.findComponent( NeoMultiLookupInput ).vm.$emit( 'update:modelValue', [ null ] );
			await wrapper.vm.$nextTick();

			expect( wrapper.emitted( 'update:modelValue' )![ 0 ][ 0 ] ).toBeUndefined();
		} );

		it( 'surfaces a server field-level violation for a multiple relation', () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { name: 'Owners', required: true, multiple: true } ),
				serverViolations: [
					{ propertyName: 'Owners', code: 'required', args: [], valuePartIndex: null },
				],
			} );

			expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toEqual(
				{ error: 'neowiki-field-required' },
			);
		} );

		it( 'keeps field status default for a multiple relation even with a violation', () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { name: 'Owners', required: true, multiple: true } ),
				serverViolations: [
					{ propertyName: 'Owners', code: 'required', args: [], valuePartIndex: null },
				],
			} );

			expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'default' );
		} );
	} );

	describe( 'getCurrentValue', () => {
		it( 'returns initial value', () => {
			const value = new RelationValue( [ newRelation( undefined, 's1demo1aaaaaaa1' ) ] );
			const wrapper = newWrapper( { modelValue: value } );

			expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() ).toStrictEqual( value );
		} );

		it( 'returns undefined for no value', () => {
			const wrapper = newWrapper();

			expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() ).toBeUndefined();
		} );

		it( 'returns updated value after selection', async () => {
			const wrapper = newWrapper();

			wrapper.findComponent( SubjectLookup ).vm.$emit( 'update:selected', 's1demo1aaaaaaa1' );
			await wrapper.vm.$nextTick();

			const currentValue = ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() as RelationValue;
			expect( currentValue.relations ).toHaveLength( 1 );
			expect( currentValue.relations[ 0 ].target.text ).toBe( 's1demo1aaaaaaa1' );
		} );
	} );
} );
