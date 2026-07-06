import { test, expect, describe, it } from 'vitest';
import { newUrlProperty, UrlType, isValidUrl, formatUrlForDisplay } from '@/domain/propertyTypes/Url';
import { newStringValue } from '@/domain/Value';
import { PropertyName } from '@/domain/PropertyDefinition';

describe( 'UrlType', () => {

	it( 'has no display attributes', () => {
		expect( new UrlType().getDisplayAttributeNames() ).toEqual( [] );
	} );

} );

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
	[ 'abc', false ],
] )( 'isValidUrl should return %s for URL: %s', ( url: string, expected: boolean ) => {
	const isValid = isValidUrl( url );
	expect( isValid ).toBe( expected );
} );

describe( 'formatUrlForDisplay', () => {

	it( 'strips https protocol', () => {
		expect( formatUrlForDisplay( 'https://pro.wiki/pricing' ) ).toBe( 'pro.wiki/pricing' );
	} );

	it( 'strips http protocol', () => {
		expect( formatUrlForDisplay( 'http://pro.wiki/pricing' ) ).toBe( 'pro.wiki/pricing' );
	} );

	it( 'does not strip non-http protocols', () => {
		expect( formatUrlForDisplay( 'ftp://files.example.com/data' ) ).toBe( 'ftp://files.example.com/data' );
	} );

	it( 'does not add trailing slash for root URL', () => {
		expect( formatUrlForDisplay( 'https://pro.wiki' ) ).toBe( 'pro.wiki' );
	} );

	it( 'preserves query and hash for short URLs', () => {
		expect( formatUrlForDisplay( 'https://example.com/path?q=1#top' ) ).toBe( 'example.com/path?q=1#top' );
	} );

	it( 'returns non-URL strings as-is', () => {
		expect( formatUrlForDisplay( 'not a url' ) ).toBe( 'not a url' );
	} );

	it( 'does not truncate short URLs', () => {
		expect( formatUrlForDisplay( 'https://pro.wiki/short' ) ).toBe( 'pro.wiki/short' );
	} );

	it( 'does not truncate at exactly the limit', () => {
		const url = 'https://example.com/exactly';
		const stripped = 'example.com/exactly';
		expect( formatUrlForDisplay( url, stripped.length ) ).toBe( stripped );
	} );

	it( 'drops query string when URL is too long', () => {
		expect( formatUrlForDisplay(
			'https://example.com/page?session=abc123&tracking=xyz789&ref=campaign',
			20,
		) ).toBe( 'example.com/page' );
	} );

	it( 'drops fragment when URL is too long', () => {
		expect( formatUrlForDisplay(
			'https://example.com/page#very-long-section-name-here',
			20,
		) ).toBe( 'example.com/page' );
	} );

	it( 'collapses middle path segments for multi-segment URLs', () => {
		expect( formatUrlForDisplay(
			'https://www.mediawiki.org/wiki/Extension:NeoWiki/Documentation/Getting_Started',
		) ).toBe( 'www.mediawiki.org/wiki/\u2026/Getting_Started' );
	} );

	it( 'keeps maximum front segments that fit', () => {
		expect( formatUrlForDisplay(
			'https://example.com/alpha/bravo/charlie/delta/echo/foxtrot/target',
		) ).toBe( 'example.com/alpha/bravo/charlie/delta/\u2026/target' );
	} );

	it( 'falls back to character truncation for single long path segment', () => {
		const result = formatUrlForDisplay( 'https://example.com/a-very-long-single-path-segment-name-here', 30 );

		expect( result.length ).toBe( 30 );
		expect( result ).toContain( '\u2026' );
		expect( result ).toContain( 'example.com/' );
	} );

	it( 'falls back to character truncation when segment collapse does not fit', () => {
		const result = formatUrlForDisplay(
			'https://very-long-domain-name.example.com/path/to/very-long-last-segment-name',
		);

		expect( result.length ).toBe( 50 );
		expect( result ).toContain( '\u2026' );
	} );

	it( 'respects custom maxLength', () => {
		const result = formatUrlForDisplay( 'https://example.com/a-very-long-path-that-exceeds', 30 );

		expect( result.length ).toBe( 30 );
		expect( result ).toContain( '\u2026' );
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
			name: 'CustomUrl',
		} );

		expect( property.name ).toEqual( new PropertyName( 'CustomUrl' ) );
	} );

	it( 'accepts PropertyName instance for name', () => {
		const propertyName = new PropertyName( 'customUrl' );
		const property = newUrlProperty( {
			name: propertyName,
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
			uniqueItems: false,
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
			multiple: true,
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

describe( 'createPropertyDefinitionFromJson', () => {
	const type = new UrlType();

	it( 'defaults uniqueItems to false when omitted, matching the backend', () => {
		const property = type.createPropertyDefinitionFromJson(
			{ name: new PropertyName( 'Links' ), type: 'url', description: '', required: false },
			{ type: 'url' },
		);

		expect( property.uniqueItems ).toBe( false );
	} );

	it( 'preserves an explicit uniqueItems value', () => {
		const property = type.createPropertyDefinitionFromJson(
			{ name: new PropertyName( 'Links' ), type: 'url', description: '', required: false },
			{ type: 'url', uniqueItems: true },
		);

		expect( property.uniqueItems ).toBe( true );
	} );
} );
