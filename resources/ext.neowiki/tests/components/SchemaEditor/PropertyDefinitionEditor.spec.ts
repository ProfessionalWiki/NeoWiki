import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { CdxSelect } from '@wikimedia/codex';
import PropertyDefinitionEditor, { type PropertyDefinitionEditorExposes } from '@/components/SchemaEditor/PropertyDefinitionEditor.vue';
import NumberInput from '@/components/Value/NumberInput.vue';
import { newTextProperty, TextProperty } from '@/domain/propertyTypes/Text';
import { newNumberProperty } from '@/domain/propertyTypes/Number';
import TextAttributesEditor from '@/components/SchemaEditor/Property/TextAttributesEditor.vue';
import { SelectProperty } from '@/domain/propertyTypes/Select';
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
			constraintSeverities: { minLength: 'error' },
		} );

		await changeTypeTo( wrapper, 'select' );

		expect( lastEmittedProperty( wrapper ).constraintSeverities ).toBeUndefined();
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
