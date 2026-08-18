import { mount, VueWrapper } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import SchemaEditor, { type SchemaEditorExposes } from '@/components/SchemaEditor/SchemaEditor.vue';
import NumberInput from '@/components/Value/NumberInput.vue';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { createPropertyDefinitionFromJson, PropertyName } from '@/domain/PropertyDefinition.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import { newNumberProperty } from '@/domain/propertyTypes/Number.ts';
import { createI18nMock, reportUnparseableNumber } from '../../VueTestHelpers.ts';
import { NeoWikiTestServices } from '../../NeoWikiTestServices.ts';

function createWrapper( schema: Schema, description = '' ): VueWrapper {
	return mount( SchemaEditor, {
		props: {
			initialSchema: schema,
			description,
		},
		global: {
			mocks: {
				$i18n: createI18nMock(),
			},
			stubs: {
				PropertyList: true,
				PropertyDefinitionEditor: true,
			},
		},
	} );
}

function createWrapperWithPropertyEditor( schema: Schema ): VueWrapper {
	return mount( SchemaEditor, {
		props: {
			initialSchema: schema,
		},
		global: {
			provide: NeoWikiTestServices.getServices(),
			directives: {
				tooltip: {},
			},
			mocks: {
				$i18n: createI18nMock(),
			},
			stubs: {
				PropertyList: true,
			},
		},
	} );
}

describe( 'SchemaEditor', () => {

	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str ) => ( {
				text: () => str,
				parse: () => str,
			} ) ),
		} );
	} );

	it( 'selects the first property by default when properties exist', () => {
		const schema = new Schema(
			'TestSchema',
			'Description',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName } ),
				createPropertyDefinitionFromJson( 'secondProp', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = createWrapper( schema );

		expect( wrapper.classes() ).toContain( 'ext-neowiki-schema-editor--has-selected-property' );
		expect( wrapper.findComponent( { name: 'PropertyList' } ).props( 'selectedPropertyName' ) ).toBe( 'firstProp' );
		expect( wrapper.findComponent( { name: 'PropertyDefinitionEditor' } ).props( 'property' ).name.toString() ).toBe( 'firstProp' );
	} );

	it( 'does not select any property if schema has no properties', () => {
		const schema = new Schema(
			'EmptySchema',
			'Description',
			new PropertyDefinitionList( [] ),
		);

		const wrapper = createWrapper( schema );

		expect( wrapper.classes() ).not.toContain( 'ext-neowiki-schema-editor--has-selected-property' );
		expect( wrapper.findComponent( { name: 'PropertyList' } ).props( 'selectedPropertyName' ) ).toBe( undefined );
		expect( wrapper.findComponent( { name: 'PropertyDefinitionEditor' } ).exists() ).toBe( false );
	} );

	it( 'removes property when propertyDeleted event is emitted', async () => {
		const schema = new Schema(
			'TestSchema',
			'Description',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName } ),
				createPropertyDefinitionFromJson( 'secondProp', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = createWrapper( schema );
		const propertyList = wrapper.findComponent( { name: 'PropertyList' } );

		await propertyList.vm.$emit( 'propertyDeleted', schema.getPropertyDefinition( 'firstProp' ).name );

		const updatedSchema = ( wrapper.vm as any ).getSchema();
		expect( updatedSchema.getPropertyDefinitions().has( schema.getPropertyDefinition( 'firstProp' ).name ) ).toBe( false );
		expect( updatedSchema.getPropertyDefinitions().has( schema.getPropertyDefinition( 'secondProp' ).name ) ).toBe( true );
	} );

	it( 'updates selection when selected property is deleted', async () => {
		const schema = new Schema(
			'TestSchema',
			'Description',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName } ),
				createPropertyDefinitionFromJson( 'secondProp', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = createWrapper( schema );
		const propertyList = wrapper.findComponent( { name: 'PropertyList' } );

		await propertyList.vm.$emit( 'propertyDeleted', schema.getPropertyDefinition( 'firstProp' ).name );

		expect( wrapper.findComponent( { name: 'PropertyList' } ).props( 'selectedPropertyName' ) ).toBe( 'secondProp' );
	} );

	it( 'maintains selection when non-selected property is deleted', async () => {
		const schema = new Schema(
			'TestSchema',
			'Description',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName } ),
				createPropertyDefinitionFromJson( 'secondProp', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = createWrapper( schema );
		const propertyList = wrapper.findComponent( { name: 'PropertyList' } );

		await propertyList.vm.$emit( 'propertySelected', schema.getPropertyDefinition( 'secondProp' ).name );
		await propertyList.vm.$emit( 'propertyDeleted', schema.getPropertyDefinition( 'firstProp' ).name );

		expect( wrapper.findComponent( { name: 'PropertyList' } ).props( 'selectedPropertyName' ) ).toBe( 'secondProp' );
	} );

	it( 'builds the schema with the description supplied by the host', () => {
		const schema = new Schema(
			'TestSchema',
			'The description the editor was handed',
			new PropertyDefinitionList( [] ),
		);

		const wrapper = createWrapper( schema, 'The description the host now holds' );

		const built = ( wrapper.vm as any ).getSchema() as Schema;
		expect( built.getDescription() ).toBe( 'The description the host now holds' );
		expect( built.getName() ).toBe( 'TestSchema' );
	} );

	it( 'keeps the schema its own description when the host presents none', () => {
		const schema = new Schema(
			'TestSchema',
			'The description it arrived with',
			new PropertyDefinitionList( [] ),
		);

		const wrapper = mount( SchemaEditor, {
			props: { initialSchema: schema },
			global: {
				mocks: { $i18n: createI18nMock() },
				stubs: { PropertyList: true, PropertyDefinitionEditor: true },
			},
		} );

		expect( ( ( wrapper.vm as any ).getSchema() as Schema ).getDescription() )
			.toBe( 'The description it arrived with' );
	} );

	it( 'keeps the host description when a property is edited', async () => {
		const schema = new Schema(
			'TestSchema',
			'',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = createWrapper( schema, 'Held by the host' );
		const editor = wrapper.findComponent( { name: 'PropertyDefinitionEditor' } );
		const updatedProperty = createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName, description: 'Updated' } );
		await editor.vm.$emit( 'update:propertyDefinition', updatedProperty );

		expect( ( ( wrapper.vm as any ).getSchema() as Schema ).getDescription() ).toBe( 'Held by the host' );
	} );

	it( 'emits change when a property is created', async () => {
		const schema = new Schema(
			'TestSchema',
			'Description',
			new PropertyDefinitionList( [] ),
		);

		const wrapper = createWrapper( schema );
		const propertyList = wrapper.findComponent( { name: 'PropertyList' } );

		const newProperty = createPropertyDefinitionFromJson( 'newProp', { type: TextType.typeName } );
		await propertyList.vm.$emit( 'propertyCreated', newProperty );

		expect( wrapper.emitted( 'change' ) ).toHaveLength( 1 );
	} );

	it( 'emits change when a property is deleted', async () => {
		const schema = new Schema(
			'TestSchema',
			'Description',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName } ),
				createPropertyDefinitionFromJson( 'secondProp', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = createWrapper( schema );
		const propertyList = wrapper.findComponent( { name: 'PropertyList' } );

		await propertyList.vm.$emit( 'propertyDeleted', schema.getPropertyDefinition( 'firstProp' ).name );

		expect( wrapper.emitted( 'change' ) ).toHaveLength( 1 );
	} );

	it( 'emits change when a property definition is updated', async () => {
		const schema = new Schema(
			'TestSchema',
			'Description',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = createWrapper( schema );
		const editor = wrapper.findComponent( { name: 'PropertyDefinitionEditor' } );

		const updatedProperty = createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName, description: 'Updated' } );
		await editor.vm.$emit( 'update:propertyDefinition', updatedProperty );

		expect( wrapper.emitted( 'change' ) ).toHaveLength( 1 );
	} );

	it( 'reorders properties when propertyReordered event is emitted', async () => {
		const schema = new Schema(
			'TestSchema',
			'Description',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName } ),
				createPropertyDefinitionFromJson( 'secondProp', { type: TextType.typeName } ),
				createPropertyDefinitionFromJson( 'thirdProp', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = createWrapper( schema );
		const propertyList = wrapper.findComponent( { name: 'PropertyList' } );

		await propertyList.vm.$emit( 'propertyReordered', [
			new PropertyName( 'thirdProp' ),
			new PropertyName( 'firstProp' ),
			new PropertyName( 'secondProp' ),
		] );

		const updatedSchema = ( wrapper.vm as any ).getSchema();
		const propertyNames = Object.keys( updatedSchema.getPropertyDefinitions().asRecord() );
		expect( propertyNames ).toEqual( [ 'thirdProp', 'firstProp', 'secondProp' ] );
	} );

	it( 'emits change when properties are reordered', async () => {
		const schema = new Schema(
			'TestSchema',
			'Description',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName } ),
				createPropertyDefinitionFromJson( 'secondProp', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = createWrapper( schema );
		const propertyList = wrapper.findComponent( { name: 'PropertyList' } );

		await propertyList.vm.$emit( 'propertyReordered', [
			new PropertyName( 'secondProp' ),
			new PropertyName( 'firstProp' ),
		] );

		expect( wrapper.emitted( 'change' ) ).toHaveLength( 1 );
	} );

	it( 'reinitializes state when initialSchema prop changes', async () => {
		const schema = new Schema(
			'TestSchema',
			'Original',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = createWrapper( schema );

		expect( wrapper.findComponent( { name: 'PropertyList' } ).props( 'selectedPropertyName' ) ).toBe( 'firstProp' );

		const newSchema = new Schema(
			'UpdatedSchema',
			'Updated description',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'alphaProperty', { type: TextType.typeName } ),
				createPropertyDefinitionFromJson( 'betaProperty', { type: TextType.typeName } ),
			] ),
		);

		await wrapper.setProps( { initialSchema: newSchema } );

		expect( ( ( wrapper.vm as any ).getSchema() as Schema ).getName() ).toBe( 'UpdatedSchema' );
		expect( wrapper.findComponent( { name: 'PropertyList' } ).props( 'selectedPropertyName' ) ).toBe( 'alphaProperty' );
	} );

	it( 'does not emit change when a property is selected', async () => {
		const schema = new Schema(
			'TestSchema',
			'Description',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName } ),
				createPropertyDefinitionFromJson( 'secondProp', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = createWrapper( schema );
		const propertyList = wrapper.findComponent( { name: 'PropertyList' } );

		await propertyList.vm.$emit( 'propertySelected', schema.getPropertyDefinition( 'secondProp' ).name );

		expect( wrapper.emitted( 'change' ) ).toBeUndefined();
	} );

	describe( 'Unparseable initial value', () => {
		function schemaWithScore(): Schema {
			return new Schema(
				'TestSchema',
				'Description',
				new PropertyDefinitionList( [ newNumberProperty( { name: 'Score' } ) ] ),
			);
		}

		/**
		 * Puts the selected property's Initial value field in the state a browser
		 * leaves it in for text like "5foo": the reported value is empty while
		 * validity.badInput is set. jsdom neither keeps such text nor sets the flag.
		 */
		function unparseableInput( wrapper: VueWrapper ): ReturnType<SchemaEditorExposes['unparseableInput']> {
			return ( wrapper.vm as unknown as SchemaEditorExposes ).unparseableInput();
		}

		it( 'reports nothing while the selected property editor reports nothing', () => {
			const wrapper = createWrapperWithPropertyEditor( schemaWithScore() );

			expect( unparseableInput( wrapper ) ).toBeNull();
		} );

		it( 'names the selected property when its editor holds text it cannot turn into a value', async () => {
			const wrapper = createWrapperWithPropertyEditor( schemaWithScore() );

			await reportUnparseableNumber( wrapper.findComponent( NumberInput ).find( 'input' ) );

			expect( unparseableInput( wrapper ) ).toEqual( {
				propertyName: 'Score',
				message: 'neowiki-field-invalid-number',
			} );
		} );

		it( 'reports nothing when no property is selected', () => {
			const wrapper = createWrapperWithPropertyEditor( new Schema(
				'EmptySchema',
				'Description',
				new PropertyDefinitionList( [] ),
			) );

			expect( unparseableInput( wrapper ) ).toBeNull();
		} );
	} );
} );
