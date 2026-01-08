import { test, expect, describe, it } from 'vitest';
import { newUrlProperty, UrlType, isValidUrl, UrlFormatter } from '@/domain/propertyTypes/Url';
import { newStringValue } from '@/domain/Value';
import { PropertyName } from '@/domain/PropertyDefinition';

test.each( [
	[ '', false ],
	[ 'https://example.com?query=value', true ],
	[ 'https://example.com#fragment', true ],
	[ 'https://example.com/path?query=value#fragment', true ],
	[ 'https://example.com/path with spaces', false ],
	[ 'ftp://example.com', false ],
	[ 'www.example.com', true ],
	[ 'http://localhost:8080', true ],
	[ 'http://192.168.1.1', true ],
	[ 'file:///path/to/file', false ],
	[ 'https://example.com', true ],
	[ 'http://example.com', true ],
	[ 'https://example.com/path', true ],
	[ 'example', false ],
	[ 'example.com', true ],
	[ 'invalid_url', false ],
	[ 'http://invalid_url', false ],
	[ '123', false ],
	[ 'abc', false ]
] )( 'isValidUrl should return %s for URL: %s', ( url: string, expected: boolean ) => {
	const isValid = isValidUrl( url );
	expect( isValid ).toBe( expected );
} );

describe( 'UrlFormatter', () => {

	describe( 'formatUrlAsHtml', () => {

		it( 'returns empty as-is', () => {
			expect(
				( new UrlFormatter( newUrlProperty() ) ).formatUrlAsHtml( '' )
			).toBe( '' );
		} );

		it( 'formats valid URLs', () => {
			expect(
				( new UrlFormatter( newUrlProperty() ) ).formatUrlAsHtml( 'https://pro.wiki/pricing' )
			).toBe( '<a href="https://pro.wiki/pricing">pro.wiki/pricing</a>' );
		} );

		it( 'sanitizes evil URLs', () => {
			expect(
				( new UrlFormatter( newUrlProperty() ) ).formatUrlAsHtml( 'https://pro.wiki/pricing <script>alert("xss");</script>' )
			).toBe( '<a href="https://pro.wiki/pricing%20%3Cscript%3Ealert(%22xss%22);%3C/script%3E">pro.wiki/pricing%20%3Cscript%3Ealert(%22xss%22);%3C/script%3E</a>' ); // TODO: verify this is safe
		} );

		it( 'sanitizes HTML inputs', () => {
			expect(
				( new UrlFormatter( newUrlProperty() ) ).formatUrlAsHtml( 'evil <strong>bold</strong>' )
			).toBe( 'evil <strong>bold</strong>' ); // FIXME: this is NOT what we want. EVERYTHING should be escaped, and nothing should be omitted.
		} );

		it( 'returns invalid URLs as they are', () => {
			expect(
				( new UrlFormatter( newUrlProperty() ) ).formatUrlAsHtml( '~[,,_,,]:3' )
			).toBe( '~[,,_,,]:3' );
		} );

		it( 'does not add tailing slashes in the text', () => {
			expect(
				( new UrlFormatter( newUrlProperty() ) ).formatUrlAsHtml( 'https://pro.wiki' )
			).toBe( '<a href="https://pro.wiki/">pro.wiki</a>' ); // Tailing slash only added in the link

			expect(
				( new UrlFormatter( newUrlProperty() ) ).formatUrlAsHtml( 'https://pro.wiki/pricing' )
			).toBe( '<a href="https://pro.wiki/pricing">pro.wiki/pricing</a>' );
		} );

		it( 'returns link with HTTPS stripped from the text', () => {
			expect(
				( new UrlFormatter( newUrlProperty() ) ).formatUrlAsHtml( 'https://professional.wiki/en/mediawiki-development#anchor' )
			).toBe( '<a href="https://professional.wiki/en/mediawiki-development#anchor">professional.wiki/en/mediawiki-development#anchor</a>' );
		} );

	} );

	describe( 'formatUrlArrayAsHtml', () => {

		it( 'formats multiple URLs', () => {
			expect(
				( new UrlFormatter( newUrlProperty() ) ).formatUrlArrayAsHtml( [
					'https://pro.wiki/blog',
					'https://pro.wiki/pricing'
				] )
			).toBe( '<a href="https://pro.wiki/blog">pro.wiki/blog</a>, <a href="https://pro.wiki/pricing">pro.wiki/pricing</a>' );
		} );

		it( 'returns empty string for empty array', () => {
			expect(
				( new UrlFormatter( newUrlProperty() ) ).formatUrlArrayAsHtml( [] )
			).toBe( '' );
		} );

		it( 'omits empty values', () => {
			expect(
				( new UrlFormatter( newUrlProperty() ) ).formatUrlArrayAsHtml( [
					'https://pro.wiki/blog',
					'',
					' ',
					'https://pro.wiki/pricing'
				] )
			).toBe( '<a href="https://pro.wiki/blog">pro.wiki/blog</a>, <a href="https://pro.wiki/pricing">pro.wiki/pricing</a>' );
		} );

	} );

} );

describe( 'newUrlProperty', () => {
	it( 'creates property with default values when no attributes provided', () => {
		const property = newUrlProperty();

		expect( property.name ).toEqual( new PropertyName( 'Url' ) );
		expect( property.type ).toBe( UrlType.typeName );
		expect( property.description ).toBe( '' );
		expect( property.required ).toBe( false );
		expect( property.default ).toBeUndefined();
		expect( property.multiple ).toBe( false );
		expect( property.uniqueItems ).toBe( true );
	} );

	it( 'creates property with custom name as string', () => {
		const property = newUrlProperty( {
			name: 'CustomUrl'
		} );

		expect( property.name ).toEqual( new PropertyName( 'CustomUrl' ) );
	} );

	it( 'accepts PropertyName instance for name', () => {
		const propertyName = new PropertyName( 'customUrl' );
		const property = newUrlProperty( {
			name: propertyName
		} );

		expect( property.name ).toBe( propertyName );
	} );

	it( 'creates property with all optional fields', () => {
		const property = newUrlProperty( {
			name: 'FullUrl',
			description: 'A URL property',
			required: true,
			default: newStringValue( 'https://example.com' ),
			multiple: true,
			uniqueItems: false
		} );

		expect( property.name ).toEqual( new PropertyName( 'FullUrl' ) );
		expect( property.type ).toBe( UrlType.typeName );
		expect( property.description ).toBe( 'A URL property' );
		expect( property.required ).toBe( true );
		expect( property.default ).toStrictEqual( newStringValue( 'https://example.com' ) );
		expect( property.multiple ).toBe( true );
		expect( property.uniqueItems ).toBe( false );
	} );

	it( 'creates property with some optional fields', () => {
		const property = newUrlProperty( {
			name: 'PartialUrl',
			description: 'A partial URL property',
			multiple: true
		} );

		expect( property.name ).toEqual( new PropertyName( 'PartialUrl' ) );
		expect( property.type ).toBe( UrlType.typeName );
		expect( property.description ).toBe( 'A partial URL property' );
		expect( property.required ).toBe( false );
		expect( property.default ).toBeUndefined();
		expect( property.multiple ).toBe( true );
		expect( property.uniqueItems ).toBe( true );
	} );
} );

describe( 'validate', () => {
	const urlType = new UrlType();

	it( 'returns no errors for empty value when optional', () => {
		const property = newUrlProperty( {
			required: false
		} );

		const errors = urlType.validate( newStringValue(), property );

		expect( errors ).toEqual( [] );
	} );

	it( 'returns required error for required empty value', () => {
		const property = newUrlProperty( {
			required: true
		} );

		const errors = urlType.validate( newStringValue(), property );

		expect( errors ).toEqual( [ { code: 'required' } ] );
	} );

	it( 'returns required error for required undefined value', () => {
		const property = newUrlProperty( {
			required: true
		} );

		const errors = urlType.validate( undefined, property );

		expect( errors ).toEqual( [ { code: 'required' } ] );
	} );

	it( 'returns no errors for valid URL', () => {
		const property = newUrlProperty();

		const errors = urlType.validate(
			newStringValue( [ 'https://example.com' ] ),
			property
		);

		expect( errors ).toEqual( [] );
	} );

	it( 'returns invalid-url error for malformed URL', () => {
		const property = newUrlProperty();

		const errors = urlType.validate(
			newStringValue( [ 'not-a-url' ] ),
			property
		);

		expect( errors[ 0 ].code ).toEqual( 'invalid-url' );
	} );

	it( 'returns error for each invalid URL', () => {
		const property = newUrlProperty();

		const errors = urlType.validate(
			newStringValue( [ 'https://example1.com', 'invalid-1', 'https://example2.com', 'invalid-2', 'https://example3.com' ] ),
			property
		);

		expect( errors ).toEqual( [
			{ code: 'invalid-url', source: 'invalid-1' },
			{ code: 'invalid-url', source: 'invalid-2' }
		] );
	} );

	it( 'returns unique error for duplicate URLs', () => {
		const property = newUrlProperty( {
			uniqueItems: true
		} );

		const errors = urlType.validate(
			newStringValue( [
				'https://foo.com',
				'https://example.com',
				'https://bar.com',
				'https://example.com',
				'https://baz.com'
			] ),
			property
		);

		expect( errors ).toEqual( [ { code: 'unique' } ] );
	} );

	it( 'returns no uniquerness errors for multiple distinct URLs', () => {
		const property = newUrlProperty( {
			uniqueItems: true
		} );

		const errors = urlType.validate(
			newStringValue( [ 'https://example1.com', 'https://example2.com', 'https://example3.com' ] ),
			property
		);

		expect( errors ).toEqual( [] );
	} );
} );
