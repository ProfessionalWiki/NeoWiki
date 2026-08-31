import { mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { CdxTextInput } from '@wikimedia/codex';
import RelationAttributesEditor from '@/components/SchemaEditor/Property/RelationAttributesEditor.vue';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';
import { newRelationProperty, RelationProperty } from '@/domain/propertyTypes/Relation';
import { PropertyName } from '@/domain/PropertyDefinition.ts';
import { AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { createI18nMock, FieldProps, setupMwMock } from '../../../VueTestHelpers.ts';

const SchemaPickerStub = {
	props: [ 'selected' ],
	emits: [ 'select', 'blur' ],
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
			functions: [ 'config', 'message' ],
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
				stubs: { SchemaPicker: SchemaPickerStub },
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
			expect( wrapper.findComponent( SchemaPickerStub ).exists() ).toBe( true );
			expect( wrapper.find( 'input[type="checkbox"]' ).exists() ).toBe( true );
		} );

		it( 'passes the current target schema to SchemaPicker', () => {
			const wrapper = newWrapper( {
				property: relationProperty( { targetSchema: 'Office' } ),
			} );

			expect( wrapper.findComponent( SchemaPickerStub ).props( 'selected' ) ).toBe( 'Office' );
		} );

		it( 'passes null to SchemaPicker when no target schema is set', () => {
			const wrapper = newWrapper( {
				property: relationProperty( { targetSchema: '' } ),
			} );

			expect( wrapper.findComponent( SchemaPickerStub ).props( 'selected' ) ).toBe( null );
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

		it( 'emits an empty relation for whitespace-only input', async () => {
			const wrapper = newWrapper();

			await wrapper.findComponent( CdxTextInput ).vm.$emit( 'update:modelValue', '   ' );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { relation: '' } ] );
		} );

		it( 'emits targetSchema when the picker selects a schema', async () => {
			const wrapper = newWrapper();

			await wrapper.findComponent( SchemaPickerStub ).vm.$emit( 'select', 'Office' );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { targetSchema: 'Office' } ] );
		} );

		it( 'emits multiple when the checkbox is toggled', async () => {
			const wrapper = newWrapper();

			await wrapper.find( 'input[type="checkbox"]' ).setValue( true );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { multiple: true } ] );
		} );
	} );

	describe( 'Constraint severity', () => {
		function multipleSeverityInput( wrapper: VueWrapper ): VueWrapper<InstanceType<typeof SeverityInput>> {
			return ( wrapper.findComponent( '.relation-attributes__multiple' ) as VueWrapper ).findComponent( SeverityInput );
		}

		it( 'offers a severity for the single-value rule while multiple values are not allowed', () => {
			const wrapper = newWrapper( { property: relationProperty( { multiple: false } ) } );

			expect( multipleSeverityInput( wrapper ).exists() ).toBe( true );
		} );

		it( 'offers no severity for the single-value rule once multiple values are allowed', () => {
			const wrapper = newWrapper( { property: relationProperty( { multiple: true } ) } );

			expect( multipleSeverityInput( wrapper ).exists() ).toBe( false );
		} );

		it( 'names the single-value rule rather than the checkbox that switches it off', () => {
			const wrapper = newWrapper( { property: relationProperty( { multiple: false } ) } );

			expect( multipleSeverityInput( wrapper ).props( 'constraint' ) )
				.toBe( 'neowiki-property-editor-single-value' );
		} );

		it( 'shows the current severity of the single-value rule', () => {
			const wrapper = newWrapper( {
				property: { ...relationProperty( { multiple: false } ), constraintSeverities: { multiple: 'error' } },
			} );

			expect( multipleSeverityInput( wrapper ).props( 'modelValue' ) ).toBe( 'error' );
		} );

		it( 'emits the changed severity of the single-value rule', async () => {
			const wrapper = newWrapper( { property: relationProperty( { multiple: false } ) } );

			await multipleSeverityInput( wrapper ).vm.$emit( 'update:modelValue', 'error' );

			expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { constraintSeverities: { multiple: 'error' } } ] ] );
		} );

		it( 'removes the annotation of the single-value rule when it goes back to warning', async () => {
			const wrapper = newWrapper( {
				property: { ...relationProperty( { multiple: false } ), constraintSeverities: { multiple: 'error' } },
			} );

			await multipleSeverityInput( wrapper ).vm.$emit( 'update:modelValue', 'warning' );

			expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { constraintSeverities: undefined } ] ] );
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

		it( 'does not show the target schema error before the field is touched', () => {
			const wrapper = newWrapper( {
				property: relationProperty( { targetSchema: '' } ),
			} );

			expect( fieldProps( wrapper, '.relation-attributes__target-schema' ).status ).toBe( 'default' );
		} );

		it( 'offers no local selection for a target schema from another Source', () => {
			const wrapper = newWrapper( {
				property: relationProperty( { targetSchema: { source: 'otherwiki', name: 'Person' } } ),
			} );

			expect( wrapper.findComponent( SchemaPickerStub ).props( 'selected' ) ).toBeNull();
		} );

		it( 'shows a required error after the empty target schema field is blurred', async () => {
			const wrapper = newWrapper( {
				property: relationProperty( { targetSchema: '' } ),
			} );

			await wrapper.findComponent( SchemaPickerStub ).vm.$emit( 'blur' );

			const props = fieldProps( wrapper, '.relation-attributes__target-schema' );
			expect( props.status ).toBe( 'error' );
			expect( props.messages ).toEqual( { error: 'Target schema is required.' } );
		} );
	} );

} );
