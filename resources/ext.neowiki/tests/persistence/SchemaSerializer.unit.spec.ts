import { describe, expect, it } from 'vitest';
import { SchemaSerializer } from '@/persistence/SchemaSerializer';
import { Schema } from '@neo/domain/Schema';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList';
import { PropertyDefinition, PropertyName } from '@neo/domain/PropertyDefinition';
import { ValueType } from '@neo/domain/Value';
import { TextFormat, TextProperty } from '@neo/domain/valueFormats/Text';
import { UrlFormat, UrlProperty } from '@neo/domain/valueFormats/Url';
import { NumberFormat, NumberProperty } from '@neo/domain/valueFormats/Number';
import { RelationFormat, RelationProperty } from '@neo/domain/valueFormats/Relation';

describe( 'SchemaSerializer', () => {
	const serializer = new SchemaSerializer();

	describe( 'serializeSchema', () => {
		it( 'serializes a schema with no properties', () => {
			const schema = new Schema(
				'TestSchema',
				'Test Description',
				new PropertyDefinitionList( [] )
			);

			const serialized = serializer.serializeSchema( schema );
			const parsed = JSON.parse( serialized );

			expect( parsed ).toEqual( {
				description: 'Test Description',
				propertyDefinitions: {}
			} );
		} );

		it( 'serializes a schema with all property types', () => {
			const propertyDefinitions = new PropertyDefinitionList( [
				{
					name: new PropertyName( 'textProperty' ),
					type: ValueType.String,
					format: TextFormat.formatName,
					description: 'Text property',
					required: true,
					multiple: true,
					uniqueItems: false
				} as TextProperty,
				{
					name: new PropertyName( 'urlProperty' ),
					type: ValueType.String,
					format: UrlFormat.formatName,
					description: 'URL property',
					required: false,
					multiple: false,
					uniqueItems: true
				} as UrlProperty,
				{
					name: new PropertyName( 'numberProperty' ),
					type: ValueType.Number,
					format: NumberFormat.formatName,
					description: 'Number property',
					required: true,
					precision: 2,
					minimum: 0,
					maximum: 100
				} as NumberProperty,
				{
					name: new PropertyName( 'relationProperty' ),
					type: ValueType.Relation,
					format: RelationFormat.formatName,
					description: 'Relation property',
					required: false,
					relation: 'TestRelation',
					targetSchema: 'TestTargetSchema',
					multiple: true
				} as RelationProperty
			] );

			const schema = new Schema(
				'TestSchema',
				'Test Description',
				propertyDefinitions
			);

			const serialized = serializer.serializeSchema( schema );
			const parsed = JSON.parse( serialized );

			expect( parsed ).toEqual( {
				description: 'Test Description',
				propertyDefinitions: {
					textProperty: {
						type: 'string',
						format: 'text',
						description: 'Text property',
						required: true,
						multiple: true,
						uniqueItems: false
					},
					urlProperty: {
						type: 'string',
						format: 'url',
						description: 'URL property',
						required: false,
						multiple: false,
						uniqueItems: true
					},
					numberProperty: {
						type: 'number',
						format: 'number',
						description: 'Number property',
						required: true,
						precision: 2,
						minimum: 0,
						maximum: 100
					},
					relationProperty: {
						type: 'relation',
						format: 'relation',
						description: 'Relation property',
						required: false,
						relation: 'TestRelation',
						targetSchema: 'TestTargetSchema',
						multiple: true
					}
				}
			} );
		} );
	} );

	describe( 'serialization formatting', () => {
		it( 'uses 4 spaces for indentation', () => {
			const schema = new Schema(
				'TestSchema',
				'Test Description',
				new PropertyDefinitionList( [] )
			);

			const serialized = serializer.serializeSchema( schema );

			expect( serialized ).toMatch( /{\n {4}"description": / );
		} );
	} );
} );
