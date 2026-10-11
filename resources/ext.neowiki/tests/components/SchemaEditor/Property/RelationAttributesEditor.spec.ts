import { mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import RelationAttributesEditor from '@/components/SchemaEditor/Property/RelationAttributesEditor.vue';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';
import { newRelationProperty, RelationProperty } from '@/domain/propertyTypes/Relation';
import { AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { createI18nMock, setupMwMock } from '../../../VueTestHelpers.ts';

const TargetSchemasInputStub = {
	props: [ 'modelValue' ],
	emits: [ 'update:modelValue' ],
	template: '<div class="target-schemas-input-stub"></div>',
};

function relationProperty( overrides: Partial<RelationProperty> = {} ): RelationProperty {
	return { ...newRelationProperty( {} ), targetSchemas: [ 'Product' ], ...overrides };
}

describe( 'RelationAttributesEditor', () => {

	beforeEach( () => {
		setupMwMock( {
			messages: {
				'neowiki-property-editor-target-schema-required': 'At least one target schema is required.',
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
				stubs: { TargetSchemasInput: TargetSchemasInputStub },
			},
		} );
	}

	describe( 'rendering', () => {
		it( 'renders the target-schemas and multiple controls', () => {
			const wrapper = newWrapper();

			expect( wrapper.findComponent( TargetSchemasInputStub ).exists() ).toBe( true );
			expect( wrapper.find( 'input[type="checkbox"]' ).exists() ).toBe( true );
		} );

		it( 'passes the current target schemas to the target-schemas field', () => {
			const targetSchemas = [ 'Office', { source: 'otherwiki', name: 'Person' } ];
			const wrapper = newWrapper( {
				property: relationProperty( { targetSchemas } ),
			} );

			expect( wrapper.findComponent( TargetSchemasInputStub ).props( 'modelValue' ) ).toEqual( targetSchemas );
		} );
	} );

	describe( 'emitting updates', () => {
		it( 'emits the target schemas the field reports', async () => {
			const wrapper = newWrapper();

			await wrapper.findComponent( TargetSchemasInputStub ).vm.$emit( 'update:modelValue', [ 'Product', 'Office' ] );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { targetSchemas: [ 'Product', 'Office' ] } ] );
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

	// Codex shows a field's message only while the field is in that message's status, so the text
	// on screen also stands for the status.
	describe( 'validation', () => {
		it( 'shows no error when a target schema is set', () => {
			const wrapper = newWrapper();

			expect( wrapper.text() ).not.toContain( 'At least one target schema is required.' );
		} );

		it( 'shows a required error while no target schema is chosen', () => {
			const wrapper = newWrapper( {
				property: relationProperty( { targetSchemas: [] } ),
			} );

			expect( wrapper.text() ).toContain( 'At least one target schema is required.' );
		} );
	} );

} );
