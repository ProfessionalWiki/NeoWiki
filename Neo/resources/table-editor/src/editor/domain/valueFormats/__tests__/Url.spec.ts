import { test, describe, expect, it } from 'vitest';
import { isValidUrl, UrlFormat } from '../Url';
import { newStringValue, type StringValue } from '../../Value';
import { newTextProperty } from '../Text';

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

describe( 'UrlFormat', () => {

	describe( 'formatValueAsHtml', () => {

		function formatUrl( urlValue: StringValue ): string {
			return ( new UrlFormat() ).formatValueAsHtml( urlValue, newTextProperty() );
		}

		it( 'returns empty string for empty string value', () => {
			expect(
				formatUrl( newStringValue() )
			).toBe( '' );
		} );

		it( 'returns link with HTTPS stripped from the text', () => {
			expect(
				formatUrl( newStringValue( 'https://professional.wiki/en/mediawiki-development#anchor' ) )
			).toBe( '<a href="https://professional.wiki/en/mediawiki-development#anchor">professional.wiki/en/mediawiki-development#anchor</a>' );
		} );

		it( 'handles multiple URLs', () => {
			expect(
				formatUrl( newStringValue( 'https://pro.wiki/blog', 'https://pro.wiki/pricing' ) )
			).toBe( '<a href="https://pro.wiki/blog">pro.wiki/blog</a>, <a href="https://pro.wiki/pricing">pro.wiki/pricing</a>' );
		} );

		it( 'does not add tailing slashes in the text', () => {
			expect(
				formatUrl( newStringValue( 'https://pro.wiki', 'https://pro.wiki/pricing' ) )
			).toBe( '<a href="https://pro.wiki/">pro.wiki</a>, <a href="https://pro.wiki/pricing">pro.wiki/pricing</a>' );
		} );

		it( 'returns invalid URLs as they are', () => {
			expect(
				formatUrl( newStringValue( 'https://pro.wiki', '~[,,_,,]:3', 'https://pro.wiki/pricing', 'invalid' ) )
			).toBe( '<a href="https://pro.wiki/">pro.wiki</a>, ~[,,_,,]:3, <a href="https://pro.wiki/pricing">pro.wiki/pricing</a>, invalid' );
		} );

		it( 'sanitizes evil URLs', () => {
			expect(
				formatUrl( newStringValue( 'evil <script>alert("xss");</script>', '<strong>bold</strong>' ) )
			).toBe( 'evil , <strong>bold</strong>' ); // FIXME: this is NOT what we want. EVERYTHING should be escaped, and nothing should be omitted.
		} );

	} );

} );
