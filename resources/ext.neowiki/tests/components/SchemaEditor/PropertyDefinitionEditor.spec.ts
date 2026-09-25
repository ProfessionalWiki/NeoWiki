import { flushPromises, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { CdxCheckbox, CdxSelect } from '@wikimedia/codex';
import PropertyDefinitionEditor, { type PropertyDefinitionEditorExposes } from '@/components/SchemaEditor/PropertyDefinitionEditor.vue';
import NumberInput from '@/components/Value/NumberInput.vue';
import { newTextProperty, TextProperty } from '@/domain/propertyTypes/Text';
import { newNumberProperty } from '@/domain/propertyTypes/Number';
import { newDateProperty } from '@/domain/propertyTypes/Date';
import DateAttributesEditor from '@/components/SchemaEditor/Property/DateAttributesEditor.vue';
import TextAttributesEditor from '@/components/SchemaEditor/Property/TextAttributesEditor.vue';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';
import { newSelectProperty, SelectProperty } from '@/domain/propertyTypes/Select';
import { newRelationProperty, RelationProperty } from '@/domain/propertyTypes/Relation';
import SelectAttributesEditor from '@/components/SchemaEditor/Property/SelectAttributesEditor.vue';
import { PropertyDefinition } from '@/domain/PropertyDefinition';
import { newNumberValue, newStringValue } from '@/domain/Value';
import { createTestWrapper, FieldProps, findPropertyNameInput, reportUnparseableNumber, selectedText, setupMwMock } from '../../VueTestHelpers.ts';

describe( 'PropertyDefinitionEditor', () => {
	beforeEach( () => {
		// The relation attributes editor reaches SchemaPicker, which calls useSchemaStore() at setup.
		setActivePinia( createPinia() );
		setupMwMock( { config: { wgUserLanguage: 'en' } } );
	} );

	function newWrapper( property: PropertyDefinition, props: { selectName?: boolean; otherPropertyNames?: string[] } = {} ): VueWrapper {
		return createTestWrapper( PropertyDefinitionEditor, { property, otherPropertyNames: [], ...props } );
	}

	function unparseableInputMessage( wrapper: VueWrapper ): string | null {
		return ( wrapper.vm as unknown as PropertyDefinitionEditorExposes ).unparseableInputMessage();
	}

	function lastEmittedProperty( wrapper: VueWrapper ): PropertyDefinition {
		const emitted = wrapper.emitted( 'update:property-definition' );
		return emitted![ emitted!.length - 1 ][ 0 ] as PropertyDefinition;
	}

	async function changeTypeTo( wrapper: VueWrapper, type: string ): Promise<void> {
		await wrapper.findComponent( CdxSelect ).vm.$emit( 'update:selected', type );
	}

	it( 'keeps the definition when its own type is picked again', async () => {
		const wrapper = newWrapper( newDateProperty( { name: 'Born', minimum: '1990' } ) );

		await changeTypeTo( wrapper, 'date' );

		expect( wrapper.findComponent( DateAttributesEditor ).props( 'property' ) )
			.toEqual( expect.objectContaining( { minimum: '1990' } ) );
	} );

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

	// The relation type is the graph edge label, which the editor no longer shows. It is set
	// here, where a property first becomes a relation property, and never derived again.
	describe( 'relation type', () => {
		it( 'names the relation type after the property when the type changes to Relation', async () => {
			const wrapper = newWrapper( newTextProperty( { name: 'Director' } ) );

			await changeTypeTo( wrapper, 'relation' );

			expect( ( lastEmittedProperty( wrapper ) as RelationProperty ).relation ).toBe( 'Director' );
		} );

		it( 'keeps the stored relation type of an existing relation property when it is renamed', async () => {
			const wrapper = newWrapper( newRelationProperty( { name: 'Director', relation: 'directed by' } ) );

			await findPropertyNameInput( wrapper ).setValue( 'Maker' );
			await flushPromises();

			const property = lastEmittedProperty( wrapper ) as RelationProperty;
			expect( property.name.toString() ).toBe( 'Maker' );
			expect( property.relation ).toBe( 'directed by' );
		} );
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
		it( 'reports nothing while the initial value can be read', () => {
			const wrapper = newWrapper( newNumberProperty( { name: 'Score', default: newNumberValue( 5 ) } ) );

			expect( unparseableInputMessage( wrapper ) ).toBeNull();
		} );

		it( 'reports the field message when the initial-value field holds text it cannot turn into a value', async () => {
			const wrapper = newWrapper( newNumberProperty( { name: 'Score' } ) );

			await reportUnparseableNumber( wrapper.findComponent( NumberInput ).find( 'input' ) );

			expect( unparseableInputMessage( wrapper ) ).toBe( 'neowiki-field-invalid-number' );
		} );

		it( 'reports the message of an attribute the definition cannot take', async () => {
			const wrapper = newWrapper( newDateProperty( { name: 'Born', minimum: '1990' } ) );

			await wrapper.find( '.date-attributes__minimum .ext-neowiki-date-text-input__text input' ).setValue( '198x' );

			expect( unparseableInputMessage( wrapper ) ).toBe( 'neowiki-field-unreadable-date198x' );
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

	describe( 'name input', () => {
		it( 'has the whole name selected when the editor opens with selectName, so typing replaces it', async () => {
			const wrapper = newWrapper( newTextProperty( { name: 'New Property 1' } ), { selectName: true } );
			await flushPromises();

			expect( selectedText( findPropertyNameInput( wrapper ).element ) ).toBe( 'New Property 1' );
		} );

		it( 'leaves the name unselected when the editor opens without selectName', async () => {
			const wrapper = newWrapper( newTextProperty( { name: 'Status' } ) );
			await flushPromises();

			expect( selectedText( findPropertyNameInput( wrapper ).element ) ).toBe( '' );
		} );

		async function typeName( wrapper: VueWrapper, name: string ): Promise<void> {
			await findPropertyNameInput( wrapper ).setValue( name );
			await flushPromises();
		}

		function nameFieldProps( wrapper: VueWrapper ): FieldProps {
			return ( wrapper.findComponent( '.ext-neowiki-property-editor__name' ) as VueWrapper ).props() as FieldProps;
		}

		it( 'renames the property to the name typed', async () => {
			const wrapper = newWrapper( newTextProperty( { name: 'Status' } ), { otherPropertyNames: [ 'Title' ] } );

			await typeName( wrapper, 'State' );

			expect( lastEmittedProperty( wrapper ).name.toString() ).toBe( 'State' );
		} );

		it( 'says the name is taken when another property has it, even with spaces around it', async () => {
			const wrapper = newWrapper( newTextProperty( { name: 'Status' } ), { otherPropertyNames: [ 'Title' ] } );

			await typeName( wrapper, ' Title ' );

			expect( nameFieldProps( wrapper ).status ).toBe( 'error' );
			expect( nameFieldProps( wrapper ).messages ).toEqual( { error: 'neowiki-property-editor-name-takenTitle' } );
		} );

		it( 'keeps the last name it could take while the name typed is taken', async () => {
			const wrapper = newWrapper( newTextProperty( { name: 'Status' } ), { otherPropertyNames: [ 'Title' ] } );

			await typeName( wrapper, 'Titl' );
			await typeName( wrapper, 'Title' );

			expect( lastEmittedProperty( wrapper ).name.toString() ).toBe( 'Titl' );
		} );

		it( 'takes the name typed once no other property has it', async () => {
			const wrapper = newWrapper( newTextProperty( { name: 'Status' } ), { otherPropertyNames: [ 'Title' ] } );
			await typeName( wrapper, 'Title' );

			await wrapper.setProps( { otherPropertyNames: [] } );
			await flushPromises();

			expect( lastEmittedProperty( wrapper ).name.toString() ).toBe( 'Title' );
			expect( nameFieldProps( wrapper ).status ).toBe( 'default' );
		} );

		it( 'asks for a name when the name is cleared', async () => {
			const wrapper = newWrapper( newTextProperty( { name: 'Status' } ) );

			await typeName( wrapper, '  ' );

			expect( nameFieldProps( wrapper ).status ).toBe( 'error' );
			expect( nameFieldProps( wrapper ).messages ).toEqual( { error: 'neowiki-property-editor-name-required' } );
		} );

		it( 'reports the name field message as input the save has to wait for', async () => {
			const wrapper = newWrapper( newTextProperty( { name: 'Status' } ), { otherPropertyNames: [ 'Title' ] } );

			await typeName( wrapper, 'Title' );

			expect( unparseableInputMessage( wrapper ) ).toBe( 'neowiki-property-editor-name-takenTitle' );
		} );
	} );
} );
