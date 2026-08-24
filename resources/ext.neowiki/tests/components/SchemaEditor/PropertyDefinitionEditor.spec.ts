import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { CdxCheckbox, CdxSelect } from '@wikimedia/codex';
import PropertyDefinitionEditor, { type PropertyDefinitionEditorExposes } from '@/components/SchemaEditor/PropertyDefinitionEditor.vue';
import NumberInput from '@/components/Value/NumberInput.vue';
import { newTextProperty, TextProperty } from '@/domain/propertyTypes/Text';
import { newNumberProperty } from '@/domain/propertyTypes/Number';
import TextAttributesEditor from '@/components/SchemaEditor/Property/TextAttributesEditor.vue';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';
import { newSelectProperty, SelectProperty } from '@/domain/propertyTypes/Select';
import SelectAttributesEditor from '@/components/SchemaEditor/Property/SelectAttributesEditor.vue';
import { PropertyDefinition } from '@/domain/PropertyDefinition';
import { newNumberValue, newStringValue } from '@/domain/Value';
import { createTestWrapper, reportUnparseableNumber, setupMwMock } from '../../VueTestHelpers.ts';

describe( 'PropertyDefinitionEditor', () => {
	beforeEach( () => {
		setupMwMock();
	} );

	function newWrapper( property: PropertyDefinition ): VueWrapper {
		return createTestWrapper( PropertyDefinitionEditor, { property } );
	}

	function lastEmittedProperty( wrapper: VueWrapper ): PropertyDefinition {
		const emitted = wrapper.emitted( 'update:property-definition' );
		return emitted![ emitted!.length - 1 ][ 0 ] as PropertyDefinition;
	}

	async function changeTypeTo( wrapper: VueWrapper, type: string ): Promise<void> {
		await wrapper.findComponent( CdxSelect ).vm.$emit( 'update:selected', type );
	}

	it( 'initializes the type-specific fields when the type changes to Select', async () => {
		const wrapper = newWrapper( newTextProperty( { name: 'Status' } ) );

		await changeTypeTo( wrapper, 'select' );

		const property = lastEmittedProperty( wrapper ) as SelectProperty;
		expect( property.type ).toBe( 'select' );
		expect( property.options ).toEqual( [] );
		expect( property.multiple ).toBe( false );
	} );

	it( 'preserves the shared fields when the type changes', async () => {
		const wrapper = newWrapper( newTextProperty( { name: 'Status', required: true } ) );

		await changeTypeTo( wrapper, 'select' );

		const property = lastEmittedProperty( wrapper );
		expect( property.name.toString() ).toBe( 'Status' );
		expect( property.required ).toBe( true );
	} );

	it( 'clears the now-invalid default value when the type changes', async () => {
		const wrapper = newWrapper( newTextProperty( { name: 'Status', default: newStringValue( 'draft' ) } ) );

		await changeTypeTo( wrapper, 'select' );

		expect( lastEmittedProperty( wrapper ).default ).toBeUndefined();
	} );

	it( 'keeps Constraint severities when an attribute is edited', async () => {
		const wrapper = newWrapper( {
			...newTextProperty( { name: 'Status', minLength: 2 } ),
			constraintSeverities: { minLength: 'error' },
		} );

		await wrapper.findComponent( TextAttributesEditor ).vm.$emit( 'update:property', { minLength: 5 } );

		const property = lastEmittedProperty( wrapper ) as TextProperty;
		expect( property.minLength ).toBe( 5 );
		expect( property.constraintSeverities ).toEqual( { minLength: 'error' } );
	} );

	it( 'drops Constraint severities when the type changes, like the other Constraint fields', async () => {
		const wrapper = newWrapper( {
			...newTextProperty( { name: 'Status' } ),
			constraintSeverities: { minLength: 'error', multiple: 'error' },
		} );

		await changeTypeTo( wrapper, 'select' );

		expect( lastEmittedProperty( wrapper ).constraintSeverities ).toBeUndefined();
	} );

	it( 'keeps the severity of required when the type changes, since required is kept too', async () => {
		const wrapper = newWrapper( {
			...newTextProperty( { name: 'Status', required: true, minLength: 2 } ),
			constraintSeverities: { minLength: 'error', required: 'error' },
		} );

		await changeTypeTo( wrapper, 'select' );

		expect( lastEmittedProperty( wrapper ).constraintSeverities ).toEqual( { required: 'error' } );
	} );

	it( 'does not carry the type-specific attributes onto the new type', async () => {
		const wrapper = newWrapper( newTextProperty( { name: 'Status', minLength: 2, maxLength: 8 } ) );

		await changeTypeTo( wrapper, 'url' );

		const property = lastEmittedProperty( wrapper );
		expect( property ).not.toHaveProperty( 'minLength' );
		expect( property ).not.toHaveProperty( 'maxLength' );
	} );

	it( 'restores the type-specific attributes when the type changes back', async () => {
		const wrapper = newWrapper( newTextProperty( { name: 'Status', minLength: 2, maxLength: 8 } ) );

		await changeTypeTo( wrapper, 'url' );
		await changeTypeTo( wrapper, 'text' );

		const property = lastEmittedProperty( wrapper ) as TextProperty;
		expect( property.minLength ).toBe( 2 );
		expect( property.maxLength ).toBe( 8 );
	} );

	it( 'restores the severities of the type-specific Constraints when the type changes back', async () => {
		const wrapper = newWrapper( {
			...newTextProperty( { name: 'Status', minLength: 2 } ),
			constraintSeverities: { minLength: 'error' },
		} );

		await changeTypeTo( wrapper, 'url' );
		await changeTypeTo( wrapper, 'text' );

		expect( lastEmittedProperty( wrapper ).constraintSeverities ).toEqual( { minLength: 'error' } );
	} );

	describe( 'unsetting a Constraint', () => {
		it( 'keeps the severity of a bound that is cleared, since a bound being typed reads as cleared', async () => {
			const wrapper = newWrapper( {
				...newTextProperty( { name: 'Status', minLength: 2, maxLength: 40 } ),
				constraintSeverities: { minLength: 'error', maxLength: 'error' },
			} );

			await wrapper.findComponent( TextAttributesEditor ).vm.$emit( 'update:property', { minLength: undefined } );

			expect( lastEmittedProperty( wrapper ).constraintSeverities ).toEqual( { minLength: 'error', maxLength: 'error' } );
		} );

		it( 'drops the severity of unique values when they are no longer required', async () => {
			const wrapper = newWrapper( {
				...newTextProperty( { name: 'Status', multiple: true, uniqueItems: true } ),
				constraintSeverities: { uniqueItems: 'error' },
			} );

			await wrapper.findComponent( TextAttributesEditor ).vm.$emit( 'update:property', { uniqueItems: false } );

			expect( lastEmittedProperty( wrapper ).constraintSeverities ).toBeUndefined();
		} );

		it( 'drops the severity of the options when the last one is removed', async () => {
			const wrapper = newWrapper( {
				...newSelectProperty( { name: 'Status', options: [ { id: 'open', label: 'Open' } ] } ),
				constraintSeverities: { options: 'error' },
			} );

			await wrapper.findComponent( SelectAttributesEditor ).vm.$emit( 'update:property', { options: [] } );

			expect( lastEmittedProperty( wrapper ).constraintSeverities ).toBeUndefined();
		} );

		it( 'keeps the severity of the single-value rule while multiple values are toggled on and off', async () => {
			const wrapper = newWrapper( {
				...newSelectProperty( { name: 'Status', multiple: false } ),
				constraintSeverities: { multiple: 'error' },
			} );
			const attributesEditor = wrapper.findComponent( SelectAttributesEditor );

			await attributesEditor.vm.$emit( 'update:property', { multiple: true } );
			await attributesEditor.vm.$emit( 'update:property', { multiple: false } );

			expect( lastEmittedProperty( wrapper ).constraintSeverities ).toEqual( { multiple: 'error' } );
		} );

		it( 'drops the severity of required when a value is no longer required', async () => {
			const wrapper = newWrapper( {
				...newTextProperty( { name: 'Status', required: true } ),
				constraintSeverities: { required: 'error' },
			} );

			await wrapper.findComponent( '.ext-neowiki-property-editor__required' ).findComponent( CdxCheckbox ).vm.$emit( 'update:modelValue', false );

			const property = lastEmittedProperty( wrapper );
			expect( property.required ).toBe( false );
			expect( property.constraintSeverities ).toBeUndefined();
		} );
	} );
	describe( 'required Constraint severity', () => {
		function requiredSeverityInput( wrapper: VueWrapper ): VueWrapper<InstanceType<typeof SeverityInput>> {
			return ( wrapper.findComponent( '.ext-neowiki-property-editor__required' ) as VueWrapper ).findComponent( SeverityInput );
		}

		it( 'shows the current severity of required', () => {
			const wrapper = newWrapper( { ...newTextProperty( { name: 'Status', required: true } ), constraintSeverities: { required: 'error' } } );

			expect( requiredSeverityInput( wrapper ).props( 'modelValue' ) ).toBe( 'error' );
		} );

		it( 'offers no severity while a value is not required', () => {
			const wrapper = newWrapper( newTextProperty( { name: 'Status', required: false } ) );

			expect( requiredSeverityInput( wrapper ).exists() ).toBe( false );
		} );

		it( 'applies the changed severity of required, keeping the other annotations', async () => {
			const wrapper = newWrapper( {
				...newTextProperty( { name: 'Status', required: true, minLength: 2 } ),
				constraintSeverities: { minLength: 'error' },
			} );

			await requiredSeverityInput( wrapper ).vm.$emit( 'update:modelValue', 'error' );

			expect( lastEmittedProperty( wrapper ).constraintSeverities ).toEqual( { minLength: 'error', required: 'error' } );
		} );

		it( 'drops the annotation when required goes back to warning', async () => {
			const wrapper = newWrapper( {
				...newTextProperty( { name: 'Status', required: true } ),
				constraintSeverities: { required: 'error' },
			} );

			await requiredSeverityInput( wrapper ).vm.$emit( 'update:modelValue', 'warning' );

			expect( lastEmittedProperty( wrapper ).constraintSeverities ).toBeUndefined();
		} );
	} );

	describe( 'Unparseable initial value', () => {
		/**
		 * Puts the initial-value field in the state a browser leaves it in for text
		 * like "5foo": the reported value is empty while validity.badInput is set.
		 * jsdom neither keeps such text nor sets the flag, so the flag is faked.
		 * The Initial value input is found through NumberInput because the attributes
		 * editor renders Minimum, Maximum and Precision inputs ahead of it.
		 */
		function unparseableInputMessage( wrapper: VueWrapper ): string | null {
			return ( wrapper.vm as unknown as PropertyDefinitionEditorExposes ).unparseableInputMessage();
		}

		it( 'reports nothing while the initial value can be read', () => {
			const wrapper = newWrapper( newNumberProperty( { name: 'Score', default: newNumberValue( 5 ) } ) );

			expect( unparseableInputMessage( wrapper ) ).toBeNull();
		} );

		it( 'reports the field message when the initial-value field holds text it cannot turn into a value', async () => {
			const wrapper = newWrapper( newNumberProperty( { name: 'Score' } ) );

			await reportUnparseableNumber( wrapper.findComponent( NumberInput ).find( 'input' ) );

			expect( unparseableInputMessage( wrapper ) ).toBe( 'neowiki-field-invalid-number' );
		} );

		it( 'reports nothing for a type whose input cannot reach that state', () => {
			const wrapper = newWrapper( newTextProperty( { name: 'Status' } ) );

			expect( unparseableInputMessage( wrapper ) ).toBeNull();
		} );

		// The removal happens as the bad character is typed, not on save: this is the
		// gap the save gates hide rather than close.
		it( 'has already dropped the initial value while the field still shows the text', async () => {
			const wrapper = newWrapper( newNumberProperty( { name: 'Score', default: newNumberValue( 5 ) } ) );

			await reportUnparseableNumber( wrapper.findComponent( NumberInput ).find( 'input' ) );

			expect( lastEmittedProperty( wrapper ).default ).toBeUndefined();
		} );
	} );
} );
