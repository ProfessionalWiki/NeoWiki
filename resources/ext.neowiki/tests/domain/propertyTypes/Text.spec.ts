import { describe, expect, it } from 'vitest';
import { newTextProperty, TextType } from '@/domain/propertyTypes/Text';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue } from '@/domain/Value';

describe( 'TextType', () => {

	const type = new TextType();

	describe( 'getTypeName', () => {

		it( 'returns "text"', () => {
			expect( type.getTypeName() ).toBe( 'text' );
		} );

	} );
} );

describe( 'newTextProperty', () => {
	it( 'creates property with default values when no options provided', () => {
		const property = newTextProperty();

		expect( property.name ).toEqual( new PropertyName( 'Text' ) );
		expect( property.type ).toBe( TextType.typeName );
		expect( property.description ).toBe( '' );
		expect( property.required ).toBe( false );
		expect( property.default ).toBeUndefined();
		expect( property.multiple ).toBe( false );
		expect( property.uniqueItems ).toBe( true );
		expect( property.maxLength ).toBeUndefined();
		expect( property.minLength ).toBeUndefined();
	} );

	it( 'creates property with custom name', () => {
		const property = newTextProperty( {
			name: 'CustomText',
		} );

		expect( property.name ).toEqual( new PropertyName( 'CustomText' ) );
	} );

	it( 'accepts PropertyName instance for name', () => {
		const propertyName = new PropertyName( 'customText' );
		const property = newTextProperty( {
			name: propertyName,
		} );

		expect( property.name ).toBe( propertyName );
	} );

	it( 'creates property with all optional fields', () => {
		const property = newTextProperty( {
			name: 'FullText',
			description: 'A text property',
			required: true,
			default: newStringValue( 'default text' ),
			multiple: true,
			uniqueItems: false,
			maxLength: 100,
			minLength: 10,
		} );

		expect( property.name ).toEqual( new PropertyName( 'FullText' ) );
		expect( property.type ).toBe( TextType.typeName );
		expect( property.description ).toBe( 'A text property' );
		expect( property.required ).toBe( true );
		expect( property.default ).toStrictEqual( newStringValue( 'default text' ) );
		expect( property.multiple ).toBe( true );
		expect( property.uniqueItems ).toBe( false );
		expect( property.maxLength ).toBe( 100 );
		expect( property.minLength ).toBe( 10 );
	} );

	it( 'creates property with some optional fields', () => {
		const property = newTextProperty( {
			name: 'PartialText',
			description: 'A partial text property',
			multiple: true,
			maxLength: 50,
		} );

		expect( property.name ).toEqual( new PropertyName( 'PartialText' ) );
		expect( property.type ).toBe( TextType.typeName );
		expect( property.description ).toBe( 'A partial text property' );
		expect( property.required ).toBe( false );
		expect( property.default ).toBeUndefined();
		expect( property.multiple ).toBe( true );
		expect( property.uniqueItems ).toBe( true );
		expect( property.maxLength ).toBe( 50 );
		expect( property.minLength ).toBeUndefined();
	} );
} );

describe( 'validate', () => {
	const textType = new TextType();

	it( 'returns no errors for empty value when optional', () => {
		const property = newTextProperty( {
			required: false,
		} );

		const errors = textType.validate( newStringValue(), property );

		expect( errors ).toEqual( [] );
	} );

	it( 'returns required error for required empty value', () => {
		const property = newTextProperty( {
			required: true,
		} );

		const errors = textType.validate( newStringValue(), property );

		expect( errors ).toEqual( [ { code: 'required' } ] );
	} );

	it( 'returns required error for required undefined value', () => {
		const property = newTextProperty( {
			required: true,
		} );

		const errors = textType.validate( undefined, property );

		expect( errors ).toEqual( [ { code: 'required' } ] );
	} );

	it( 'returns min-length error for text below minimum length', () => {
		const property = newTextProperty( {
			minLength: 5,
		} );

		const errors = textType.validate(
			newStringValue( [ 'abcd' ] ),
			property,
		);

		expect( errors ).toEqual( [ {
			code: 'min-length',
			args: [ 5 ],
			source: 'abcd',
		} ] );
	} );

	it( 'returns max-length error for text above maximum length', () => {
		const property = newTextProperty( {
			maxLength: 5,
		} );

		const errors = textType.validate(
			newStringValue( [ '123456' ] ),
			property,
		);

		expect( errors ).toEqual( [ {
			code: 'max-length',
			args: [ 5 ],
			source: '123456',
		} ] );
	} );

	it( 'returns errors for each text not meeting length requirements', () => {
		const property = newTextProperty( {
			minLength: 3,
			maxLength: 5,
		} );

		const errors = textType.validate(
			newStringValue( [ 'valid', 'a', 'VALID', 'ab', '123456' ] ),
			property,
		);

		expect( errors ).toEqual( [
			{ code: 'min-length', args: [ 3 ], source: 'a' },
			{ code: 'min-length', args: [ 3 ], source: 'ab' },
			{ code: 'max-length', args: [ 5 ], source: '123456' },
		] );
	} );

	it( 'returns no errors for for text at the length boundaries', () => {
		const property = newTextProperty( {
			minLength: 3,
			maxLength: 3,
		} );

		const errors = textType.validate( newStringValue( [ '123' ] ), property );

		expect( errors ).toEqual( [] );
	} );

	it( 'returns unique error for duplicate texts', () => {
		const property = newTextProperty( {
			uniqueItems: true,
		} );

		const errors = textType.validate(
			newStringValue( [
				'foo',
				'example',
				'bar',
				'example',
				'baz',
			] ),
			property,
		);

		expect( errors ).toEqual( [ { code: 'unique' } ] );
	} );

	it( 'returns no uniqueness errors for multiple distinct texts', () => {
		const property = newTextProperty( {
			uniqueItems: true,
		} );

		const errors = textType.validate(
			newStringValue( [ 'text1', 'text2', 'text3' ] ),
			property,
		);

		expect( errors ).toEqual( [] );
	} );
} );
