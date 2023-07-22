import { test, describe, expect, it } from 'vitest';
import { isValidUrl, UrlFormat, UrlFormatter, type UrlProperty } from '../Url';
import { newStringValue, type StringValue, ValueType } from '../../Value';
import { Format, PropertyName } from '../../PropertyDefinition';

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
			return ( new UrlFormat() ).formatValueAsHtml( urlValue, newUrlProperty() );
		}

		it( 'returns empty string for empty string value', () => {
			expect(
				formatUrl( newStringValue() )
			).toBe( '' );
		} );

		it( 'handles multiple URLs', () => {
			expect(
				formatUrl( newStringValue( 'https://pro.wiki/blog', 'https://pro.wiki/pricing' ) )
			).toBe( '<a href="https://pro.wiki/blog">pro.wiki/blog</a>, <a href="https://pro.wiki/pricing">pro.wiki/pricing</a>' );
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

function newUrlProperty(): UrlProperty {
	return {
		name: new PropertyName( 'url' ),
		type: ValueType.String,
		format: Format.Url,
		description: 'URL',
		required: false
	};
}
