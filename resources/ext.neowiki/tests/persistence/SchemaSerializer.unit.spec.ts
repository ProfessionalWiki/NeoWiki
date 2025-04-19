import { describe, expect, it } from 'vitest';
import { SchemaSerializer } from '@/persistence/SchemaSerializer';
import { Schema } from '@neo/domain/Schema';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { newTextProperty } from '@neo/domain/propertyTypes/Text';
import { newUrlProperty } from '@neo/domain/propertyTypes/Url';
import { newNumberProperty } from '@neo/domain/propertyTypes/Number';
import { newRelationProperty } from '@neo/domain/propertyTypes/Relation';

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
			const schema = new Schema(
				'TestSchema',
				'Test Description',
				new PropertyDefinitionList( [
					newTextProperty( {
						name: 'textProperty',
						description: 'Text property',
						required: true,
						multiple: true,
						uniqueItems: false
					} ),
					newUrlProperty( {
						name: new PropertyName( 'urlProperty' ),
						description: 'URL property',
						required: false,
						multiple: false,
						uniqueItems: true
					} ),
					newNumberProperty( {
						name: new PropertyName( 'numberProperty' ),
						description: 'Number property',
						required: true,
						precision: 2,
						minimum: 0,
						maximum: 100
					} ),
					newRelationProperty( {
						name: new PropertyName( 'relationProperty' ),
						description: 'Relation property',
						required: false,
						relation: 'TestRelation',
						targetSchema: 'TestTargetSchema',
						multiple: true
					} )
				] )
			);

			const serialized = serializer.serializeSchema( schema );
			const parsed = JSON.parse( serialized );

			expect( parsed ).toEqual( {
				description: 'Test Description',
				propertyDefinitions: {
					textProperty: {
						type: 'text',
						description: 'Text property',
						required: true,
						multiple: true,
						uniqueItems: false
					},
					urlProperty: {
						type: 'url',
						description: 'URL property',
						required: false,
						multiple: false,
						uniqueItems: true
					},
					numberProperty: {
						type: 'number',
						description: 'Number property',
						required: true,
						precision: 2,
						minimum: 0,
						maximum: 100
					},
					relationProperty: {
						type: 'relation',
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
