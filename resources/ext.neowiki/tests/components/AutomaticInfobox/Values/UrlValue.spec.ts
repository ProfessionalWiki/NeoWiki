import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import UrlValue from '@/components/AutomaticInfobox/Values/UrlValue.vue';
import { Statement } from '@neo/domain/Statement';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { UrlFormat } from '@neo/domain/valueFormats/Url';
import { newStringValue } from '@neo/domain/Value';

describe( 'UrlValue', () => {
	it( 'renders a single URL correctly', () => {
		const statement = new Statement(
			new PropertyName( 'website' ),
			UrlFormat.formatName,
			newStringValue( 'https://example.com' )
		);

		const wrapper = mount( UrlValue, {
			props: { statement }
		} );

		const link = wrapper.find( 'a' );
		expect( link.attributes( 'href' ) ).toBe( 'https://example.com' );
		expect( link.text() ).toBe( 'https://example.com' );
	} );

	it( 'renders multiple URLs correctly', () => {
		const statement = new Statement(
			new PropertyName( 'socialMedia' ),
			UrlFormat.formatName,
			newStringValue( 'https://foo.com/example', 'https://bar.com/example' )
		);

		const wrapper = mount( UrlValue, {
			props: { statement }
		} );

		const links = wrapper.findAll( 'a' );
		expect( links ).toHaveLength( 2 );
		expect( links[ 0 ].attributes( 'href' ) ).toBe( 'https://foo.com/example' );
		expect( links[ 0 ].text() ).toBe( 'https://foo.com/example' );
		expect( links[ 1 ].attributes( 'href' ) ).toBe( 'https://bar.com/example' );
		expect( links[ 1 ].text() ).toBe( 'https://bar.com/example' );
	} );

	it( 'renders nothing when a single empty URL is present', () => {
		const statement = new Statement(
			new PropertyName( 'website' ),
			UrlFormat.formatName,
			newStringValue( '' )
		);

		const wrapper = mount( UrlValue, {
			props: { statement }
		} );

		expect( wrapper.find( 'a' ).exists() ).toBe( false );
	} );

	it( 'skips empty URLs when rendering multiple URLs', () => {
		const statement = new Statement(
			new PropertyName( 'socialMedia' ),
			UrlFormat.formatName,
			newStringValue( 'https://foo.com/example', '', 'https://bar.com/example', '  ' )
		);

		const wrapper = mount( UrlValue, {
			props: { statement }
		} );

		const links = wrapper.findAll( 'a' );
		expect( links ).toHaveLength( 2 );
		expect( links[ 0 ].attributes( 'href' ) ).toBe( 'https://foo.com/example' );
		expect( links[ 1 ].attributes( 'href' ) ).toBe( 'https://bar.com/example' );
	} );

	it( 'renders nothing when all URLs are empty', () => {
		const statement = new Statement(
			new PropertyName( 'socialMedia' ),
			UrlFormat.formatName,
			newStringValue( '', '  ', '' )
		);

		const wrapper = mount( UrlValue, {
			props: { statement }
		} );

		expect( wrapper.find( 'a' ).exists() ).toBe( false );
	} );
} );
