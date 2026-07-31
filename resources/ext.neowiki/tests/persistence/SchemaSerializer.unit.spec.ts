import { describe, expect, it } from 'vitest';
import { SchemaSerializer } from '@/persistence/SchemaSerializer';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer';
import { Schema } from '@/domain/Schema';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import { newUrlProperty } from '@/domain/propertyTypes/Url';
import { newNumberProperty } from '@/domain/propertyTypes/Number';
import { newRelationProperty } from '@/domain/propertyTypes/Relation';

describe( 'SchemaSerializer', () => {
	const serializer = new SchemaSerializer();

	describe( 'serializeSchema', () => {
		it( 'serializes a schema with no properties', () => {
			const schema = new Schema(
				'TestSchema',
				'Test Description',
				new PropertyDefinitionList( [] ),
			);

			const serialized = serializer.serializeSchema( schema );
			const parsed = JSON.parse( serialized );

			expect( parsed ).toEqual( {
				description: 'Test Description',
				propertyDefinitions: {},
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
						uniqueItems: false,
					} ),
					newUrlProperty( {
						name: new PropertyName( 'urlProperty' ),
						description: 'URL property',
						required: false,
						multiple: false,
						uniqueItems: true,
					} ),
					newNumberProperty( {
						name: new PropertyName( 'numberProperty' ),
						description: 'Number property',
						required: true,
						precision: 2,
						minimum: 0,
						maximum: 100,
					} ),
					newRelationProperty( {
						name: new PropertyName( 'relationProperty' ),
						description: 'Relation property',
						required: false,
						relation: 'TestRelation',
						targetSchema: 'TestTargetSchema',
						multiple: true,
					} ),
				] ),
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
						uniqueItems: false,
					},
					urlProperty: {
						type: 'url',
						description: 'URL property',
						required: false,
						multiple: false,
						uniqueItems: true,
					},
					numberProperty: {
						type: 'number',
						description: 'Number property',
						required: true,
						precision: 2,
						minimum: 0,
						maximum: 100,
					},
					relationProperty: {
						type: 'relation',
						description: 'Relation property',
						required: false,
						relation: 'TestRelation',
						targetSchema: 'TestTargetSchema',
						multiple: true,
					},
				},
			} );
		} );
	} );

	describe( 'unregistered-type round-trip', () => {
		it( 'serializes a property of an unregistered type back to its original JSON', () => {
			const json = {
				description: 'Schema with an unregistered type',
				propertyDefinitions: {
					Swatch: {
						type: 'zzz-color',
						description: 'Brand colour',
						required: false,
						default: { hex: '#ff5733' },
						palette: 'warm',
					},
				},
			};

			const schema = new SchemaDeserializer().deserialize( 'Test', json );

			expect( JSON.parse( serializer.serializeSchema( schema ) ) ).toEqual( json );
		} );
	} );

	describe( 'Constraint severity round-trip', () => {
		it( 'round-trips error-annotated Constraints back to the object form', () => {
			const json = {
				description: 'Schema with annotated Constraints',
				propertyDefinitions: {
					Score: {
						type: 'number',
						description: '',
						required: { severity: 'error' },
						minimum: 0,
						maximum: { value: 100, severity: 'error' },
					},
				},
			};

			const schema = new SchemaDeserializer().deserialize( 'Test', json );

			expect( JSON.parse( serializer.serializeSchema( schema ) ) ).toEqual( json );
		} );

		it( 'emits the shorthand for a warning-annotated Constraint', () => {
			const schema = new SchemaDeserializer().deserialize( 'Test', {
				description: '',
				propertyDefinitions: {
					Name: {
						type: 'text',
						description: '',
						required: false,
						multiple: false,
						uniqueItems: false,
						minLength: { value: 2, severity: 'warning' },
					},
				},
			} );

			const parsed = JSON.parse( serializer.serializeSchema( schema ) );

			expect( parsed.propertyDefinitions.Name.minLength ).toBe( 2 );
		} );

		it( 'serializes an unchecked core boolean Constraint as bare false, dropping its annotation', () => {
			// The schema editor can uncheck an annotated required/uniqueItems while its
			// severity entry survives on the domain object. The object form cannot carry
			// false, so serialization must emit the bare scalar the backend accepts.
			const schema = new SchemaDeserializer().deserialize( 'Test', {
				description: '',
				propertyDefinitions: {
					Name: {
						type: 'text',
						description: '',
						required: { severity: 'error' },
						multiple: true,
						uniqueItems: { severity: 'error' },
					},
				},
			} );

			const unchecked = new Schema(
				schema.getName(),
				schema.getDescription(),
				new PropertyDefinitionList(
					[ ...schema.getPropertyDefinitions() ].map( ( property ) => ( {
						...property,
						required: false,
						uniqueItems: false,
					} ) ),
				),
			);

			const parsed = JSON.parse( serializer.serializeSchema( unchecked ) );

			expect( parsed.propertyDefinitions.Name.required ).toBe( false );
			expect( parsed.propertyDefinitions.Name.uniqueItems ).toBe( false );
		} );

		it( 'round-trips an annotated key of an unregistered type', () => {
			const json = {
				description: '',
				propertyDefinitions: {
					Swatch: {
						type: 'zzz-color',
						description: '',
						required: false,
						palette: { value: 'warm', severity: 'error' },
					},
				},
			};

			const schema = new SchemaDeserializer().deserialize( 'Test', json );

			expect( JSON.parse( serializer.serializeSchema( schema ) ) ).toEqual( json );
		} );
	} );

	describe( 'serialization formatting', () => {
		it( 'uses 4 spaces for indentation', () => {
			const schema = new Schema(
				'TestSchema',
				'Test Description',
				new PropertyDefinitionList( [] ),
			);

			const serialized = serializer.serializeSchema( schema );

			expect( serialized ).toMatch( /{\n {4}"description": / );
		} );
	} );
} );
