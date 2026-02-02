import { mount, VueWrapper } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import SchemaEditor from '@/components/SchemaEditor/SchemaEditor.vue';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { createPropertyDefinitionFromJson } from '@/domain/PropertyDefinition.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';

function createWrapper( schema: Schema ): VueWrapper {
	return mount( SchemaEditor, {
		props: {
			initialSchema: schema,
		},
		global: {
			stubs: {
				PropertyList: true,
				PropertyDefinitionEditor: true,
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
} );
