import { describe, expect, it } from 'vitest';
import { newTextProperty, TextFormat } from '@neo/domain/valueFormats/Text';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { newStringValue } from '@neo/domain/Value';

describe( 'TextFormat', () => {

	const format = new TextFormat();

	describe( 'getFormatName', () => {

		it( 'returns "text"', () => {
			expect( format.getFormatName() ).toBe( 'text' );
		} );

	} );
} );

describe( 'newTextProperty', () => {
	it( 'creates property with default values when no options provided', () => {
		const property = newTextProperty();

		expect( property.name ).toEqual( new PropertyName( 'Text' ) );
		expect( property.format ).toBe( TextFormat.formatName );
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
			name: 'CustomText'
		} );

		expect( property.name ).toEqual( new PropertyName( 'CustomText' ) );
	} );

	it( 'accepts PropertyName instance for name', () => {
		const propertyName = new PropertyName( 'customText' );
		const property = newTextProperty( {
			name: propertyName
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
			minLength: 10
		} );

		expect( property.name ).toEqual( new PropertyName( 'FullText' ) );
		expect( property.format ).toBe( TextFormat.formatName );
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
			maxLength: 50
		} );

		expect( property.name ).toEqual( new PropertyName( 'PartialText' ) );
		expect( property.format ).toBe( TextFormat.formatName );
		expect( property.description ).toBe( 'A partial text property' );
		expect( property.required ).toBe( false );
		expect( property.default ).toBeUndefined();
		expect( property.multiple ).toBe( true );
		expect( property.uniqueItems ).toBe( true );
		expect( property.maxLength ).toBe( 50 );
		expect( property.minLength ).toBeUndefined();
	} );
} );
