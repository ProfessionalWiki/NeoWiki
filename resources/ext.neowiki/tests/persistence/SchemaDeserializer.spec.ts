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

	it( 'deserializes a schema with no property definitions', () => {
		const schema = new SchemaDeserializer().deserialize( 'Empty', {
			description: 'Empty schema',
			propertyDefinitions: {},
		} );

		expect( schema.getName() ).toEqual( 'Empty' );
		expect( schema.getDescription() ).toEqual( 'Empty schema' );
		expect( schema.getPropertyDefinitions().asRecord() ).toEqual( {} );
	} );

	it( 'reads the declaration that the subjects are dependent', () => {
		const schema = new SchemaDeserializer().deserialize( 'Birth', {
			subjectsAreDependent: true,
			propertyDefinitions: {},
		} );

		expect( schema.subjectsAreDependent() ).toBe( true );
	} );

	it( 'reads the subjects of a schema without the declaration as standalone', () => {
		const schema = new SchemaDeserializer().deserialize( 'Place', {
			propertyDefinitions: {},
		} );

		expect( schema.subjectsAreDependent() ).toBe( false );
	} );

	it( 'deserializes an omitted description as an empty one', () => {
		const schema = new SchemaDeserializer().deserialize( 'Undescribed', {
			propertyDefinitions: {},
		} );

		expect( schema.getDescription() ).toEqual( '' );
	} );

	it( 'reads the labels trimmed, under lowercase tags, without empty ones', () => {
		const schema = new SchemaDeserializer().deserialize( 'Company', {
			labels: { 'zh-Hant': ' 公司 ', de: 'Unternehmen', nl: '  ', fr: 42 },
			propertyDefinitions: {},
		} );

		expect( schema.getLabels() ).toEqual( { 'zh-hant': '公司', de: 'Unternehmen' } );
	} );

	it( 'reads labels that are a text rather than one per language as none', () => {
		const schema = new SchemaDeserializer().deserialize( 'Company', {
			labels: 'Firm',
			propertyDefinitions: {},
		} );

		expect( schema.getLabels() ).toBeUndefined();
	} );

	it( 'reads a schema without labels as having none', () => {
		const schema = new SchemaDeserializer().deserialize( 'Company', {
			propertyDefinitions: {},
		} );

		expect( schema.getLabels() ).toBeUndefined();
	} );

} );
