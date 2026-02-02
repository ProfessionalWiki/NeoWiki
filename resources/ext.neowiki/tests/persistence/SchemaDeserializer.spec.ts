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
				uniqueItems: true,
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

	it( 'deserializes a schema with no property definitions', () => {
		const schema = new SchemaDeserializer().deserialize( 'Empty', {
			description: 'Empty schema',
			propertyDefinitions: {},
		} );

		expect( schema.getName() ).toEqual( 'Empty' );
		expect( schema.getDescription() ).toEqual( 'Empty schema' );
		expect( schema.getPropertyDefinitions().asRecord() ).toEqual( {} );
	} );

} );
