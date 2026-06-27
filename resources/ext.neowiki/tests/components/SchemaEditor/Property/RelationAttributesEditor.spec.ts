import { mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { CdxTextInput } from '@wikimedia/codex';
import RelationAttributesEditor from '@/components/SchemaEditor/Property/RelationAttributesEditor.vue';
import { newRelationProperty, RelationProperty } from '@/domain/propertyTypes/Relation';
import { PropertyName } from '@/domain/PropertyDefinition.ts';
import { AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { createI18nMock, FieldProps, setupMwMock } from '../../../VueTestHelpers.ts';

const SchemaLookupStub = {
	props: [ 'selected' ],
	emits: [ 'select' ],
	template: '<div class="schema-lookup-stub"></div>',
};

function relationProperty( overrides: Partial<RelationProperty> = {} ): RelationProperty {
	return { ...newRelationProperty( {} ), relation: 'Has product', targetSchema: 'Product', ...overrides };
}

describe( 'RelationAttributesEditor', () => {

	beforeEach( () => {
		setupMwMock( {
			messages: {
				'neowiki-property-editor-relation-required': 'Relation type is required.',
				'neowiki-property-editor-target-schema-required': 'Target schema is required.',
			},
			functions: [ 'message' ],
		} );
	} );

	function newWrapper( props: Partial<AttributesEditorProps<RelationProperty>> = {} ): VueWrapper {
		return mount( RelationAttributesEditor, {
			props: {
				property: relationProperty(),
				...props,
			},
			global: {
				mocks: { $i18n: createI18nMock() },
				stubs: { SchemaLookup: SchemaLookupStub },
			},
		} );
	}

	function fieldProps( wrapper: VueWrapper, selector: string ): FieldProps {
		return ( wrapper.findComponent( selector ) as VueWrapper ).props() as unknown as FieldProps;
	}

	describe( 'rendering', () => {
		it( 'renders the relation, target-schema and multiple controls', () => {
			const wrapper = newWrapper();

			expect( wrapper.find( '.relation-attributes__relation' ).exists() ).toBe( true );
			expect( wrapper.findComponent( SchemaLookupStub ).exists() ).toBe( true );
			expect( wrapper.find( 'input[type="checkbox"]' ).exists() ).toBe( true );
		} );

		it( 'passes the current target schema to SchemaLookup', () => {
			const wrapper = newWrapper( {
				property: relationProperty( { targetSchema: 'Office' } ),
			} );

			expect( wrapper.findComponent( SchemaLookupStub ).props( 'selected' ) ).toBe( 'Office' );
		} );

		it( 'passes null to SchemaLookup when no target schema is set', () => {
			const wrapper = newWrapper( {
				property: relationProperty( { targetSchema: '' } ),
			} );

			expect( wrapper.findComponent( SchemaLookupStub ).props( 'selected' ) ).toBe( null );
		} );

		it( 'displays the stored relation in the input', () => {
			const wrapper = newWrapper( {
				property: relationProperty( { relation: 'Has gadget' } ),
			} );

			expect( wrapper.findComponent( CdxTextInput ).props( 'modelValue' ) ).toBe( 'Has gadget' );
		} );

		it( 'displays the property name when the relation is empty', () => {
			const wrapper = newWrapper( {
				property: relationProperty( { relation: '', name: new PropertyName( 'Main product' ) } ),
			} );

			expect( wrapper.findComponent( CdxTextInput ).props( 'modelValue' ) ).toBe( 'Main product' );
		} );

		it( 'clears the displayed relation when the stored relation is emptied', async () => {
			const wrapper = newWrapper( {
				property: relationProperty( { relation: 'Has product' } ),
			} );

			await wrapper.setProps( {
				property: relationProperty( { relation: '' } ),
			} );

			expect( wrapper.findComponent( CdxTextInput ).props( 'modelValue' ) ).toBe( '' );
		} );
	} );

	describe( 'relation default', () => {
		it( 'emits the property name as relation on mount when relation is empty', () => {
			const wrapper = newWrapper( {
				property: relationProperty( { relation: '', name: new PropertyName( 'Main product' ) } ),
			} );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { relation: 'Main product' } ] );
		} );

		it( 'does not emit a default when relation is already set', () => {
			const wrapper = newWrapper( {
				property: relationProperty( { relation: 'Has product' } ),
			} );

			expect( wrapper.emitted( 'update:property' ) ).toBeUndefined();
		} );
	} );

	describe( 'emitting updates', () => {
		it( 'emits relation when the relation input changes', async () => {
			const wrapper = newWrapper();

			await wrapper.findComponent( CdxTextInput ).vm.$emit( 'update:modelValue', 'Owns' );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { relation: 'Owns' } ] );
		} );

		it( 'emits the relation trimmed of surrounding whitespace', async () => {
			const wrapper = newWrapper();

			await wrapper.findComponent( CdxTextInput ).vm.$emit( 'update:modelValue', '  Owns  ' );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { relation: 'Owns' } ] );
		} );

		it( 'emits an empty relation when the field is cleared', async () => {
			const wrapper = newWrapper();

			await wrapper.findComponent( CdxTextInput ).vm.$emit( 'update:modelValue', '' );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { relation: '' } ] );
		} );

		it( 'emits targetSchema when the picker selects a schema', async () => {
			const wrapper = newWrapper();

			await wrapper.findComponent( SchemaLookupStub ).vm.$emit( 'select', 'Office' );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { targetSchema: 'Office' } ] );
		} );

		it( 'emits an empty target schema when the picker is cleared', async () => {
			const wrapper = newWrapper();

			await wrapper.findComponent( SchemaLookupStub ).vm.$emit( 'select', '' );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { targetSchema: '' } ] );
		} );

		it( 'emits multiple when the checkbox is toggled', async () => {
			const wrapper = newWrapper();

			await wrapper.find( 'input[type="checkbox"]' ).setValue( true );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { multiple: true } ] );
		} );
	} );

	describe( 'validation', () => {
		it( 'shows no errors when relation and target schema are set', () => {
			const wrapper = newWrapper();

			expect( fieldProps( wrapper, '.relation-attributes__relation' ).status ).toBe( 'default' );
			expect( fieldProps( wrapper, '.relation-attributes__target-schema' ).status ).toBe( 'default' );
		} );

		it( 'shows a required error when the relation is cleared', async () => {
			const wrapper = newWrapper();

			await wrapper.findComponent( CdxTextInput ).vm.$emit( 'update:modelValue', '' );

			const props = fieldProps( wrapper, '.relation-attributes__relation' );
			expect( props.status ).toBe( 'error' );
			expect( props.messages ).toEqual( { error: 'Relation type is required.' } );
		} );

		it( 'treats a whitespace-only relation as required', async () => {
			const wrapper = newWrapper();

			await wrapper.findComponent( CdxTextInput ).vm.$emit( 'update:modelValue', '   ' );

			const props = fieldProps( wrapper, '.relation-attributes__relation' );
			expect( props.status ).toBe( 'error' );
			expect( props.messages ).toEqual( { error: 'Relation type is required.' } );
		} );

		it( 'shows a required error when the target schema is empty', () => {
			const wrapper = newWrapper( {
				property: relationProperty( { targetSchema: '' } ),
			} );

			const props = fieldProps( wrapper, '.relation-attributes__target-schema' );
			expect( props.status ).toBe( 'error' );
			expect( props.messages ).toEqual( { error: 'Target schema is required.' } );
		} );
	} );

} );
