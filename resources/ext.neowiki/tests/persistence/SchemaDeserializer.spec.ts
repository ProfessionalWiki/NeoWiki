import { SchemaDeserializer } from '@/persistence/SchemaDeserializer';
import { describe, expect, it } from 'vitest';
import { PropertyName } from '@/domain/PropertyDefinition';
import { TextType } from '@/domain/propertyTypes/Text';
import { NumberType } from '@/domain/propertyTypes/Number';

describe( 'SchemaDeserializer', () => {

	it( 'deserializes a schema with property definitions', () => {
		const schema = new SchemaDeserializer().deserialize( 'Employee', {
			description: 'An employee',
			propertyDefinitions: {
				Name: { type: TextType.typeName, required: true },
				Age: { type: NumberType.typeName, required: false },
			},
		} );

		expect( schema.getName() ).toEqual( 'Employee' );
		expect( schema.getDescription() ).toEqual( 'An employee' );
		expect( schema.getPropertyDefinitions().asRecord() ).toEqual( {
			Name: {
				name: new PropertyName( 'Name' ),
				type: TextType.typeName,
				description: '',
				required: true,
				multiple: false,
				uniqueItems: false,
			},
			Age: {
				name: new PropertyName( 'Age' ),
				type: NumberType.typeName,
				description: '',
				required: false,
				minimum: undefined,
				maximum: undefined,
			},
		} );
	} );

	it( 'reads the label template', () => {
		const schema = new SchemaDeserializer().deserialize( 'Artwork', {
			labelTemplate: '{Title}',
			propertyDefinitions: {},
		} );

		expect( schema.getLabelTemplate() ).toBe( '{Title}' );
	} );

	it( 'reads a blank label template as none', () => {
		const schema = new SchemaDeserializer().deserialize( 'Artwork', {
			labelTemplate: '  ',
			propertyDefinitions: {},
		} );

		expect( schema.getLabelTemplate() ).toBeNull();
	} );

	it( 'deserializes a schema with no property definitions', () => {
		const schema = new SchemaDeserializer().deserialize( 'Empty', {
			description: 'Empty schema',
			propertyDefinitions: {},
		} );

		expect( schema.getName() ).toEqual( 'Empty' );
		expect( schema.getDescription() ).toEqual( 'Empty schema' );
		expect( schema.getPropertyDefinitions().asRecord() ).toEqual( {} );
	} );

	it( 'deserializes an omitted description as an empty one', () => {
		const schema = new SchemaDeserializer().deserialize( 'Undescribed', {
			propertyDefinitions: {},
		} );

		expect( schema.getDescription() ).toEqual( '' );
	} );

} );
