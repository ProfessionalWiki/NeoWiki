import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import UrlDisplay from '@/components/Value/UrlDisplay.vue';
import { newNumberValue, newStringValue } from '@neo/domain/Value';

describe( 'UrlValue', () => {
	it( 'renders a single URL correctly', () => {
		const wrapper = mount( UrlDisplay, {
			props: {
				value: newStringValue( 'https://example.com' )
			}
		} );

		const link = wrapper.find( 'a' );
		expect( link.attributes( 'href' ) ).toBe( 'https://example.com' );
		expect( link.text() ).toBe( 'https://example.com' );
	} );

	it( 'renders multiple URLs correctly', () => {

		const wrapper = mount( UrlDisplay, {
			props: {
				value: newStringValue( 'https://foo.com/example', 'https://bar.com/example' )
			}
		} );

		const links = wrapper.findAll( 'a' );
		expect( links ).toHaveLength( 2 );
		expect( links[ 0 ].attributes( 'href' ) ).toBe( 'https://foo.com/example' );
		expect( links[ 0 ].text() ).toBe( 'https://foo.com/example' );
		expect( links[ 1 ].attributes( 'href' ) ).toBe( 'https://bar.com/example' );
		expect( links[ 1 ].text() ).toBe( 'https://bar.com/example' );
	} );

	it( 'renders nothing when a single empty URL is present', () => {
		const wrapper = mount( UrlDisplay, {
			props: {
				value: newStringValue( '' )
			}
		} );

		expect( wrapper.find( 'a' ).exists() ).toBe( false );
	} );

	it( 'skips empty URLs when rendering multiple URLs', () => {
		const wrapper = mount( UrlDisplay, {
			props: {
				value: newStringValue( 'https://foo.com/example', '', 'https://bar.com/example', '  ' )
			}
		} );

		const links = wrapper.findAll( 'a' );
		expect( links ).toHaveLength( 2 );
		expect( links[ 0 ].attributes( 'href' ) ).toBe( 'https://foo.com/example' );
		expect( links[ 1 ].attributes( 'href' ) ).toBe( 'https://bar.com/example' );
	} );

	it( 'renders nothing when all URLs are empty', () => {
		const wrapper = mount( UrlDisplay, {
			props: {
				value: newStringValue( '', '  ', '' )
			}
		} );

		expect( wrapper.find( 'a' ).exists() ).toBe( false );
	} );

	it( 'returns no links for wrong value type', () => {
		const wrapper = mount( UrlDisplay, {
			props: {
				value: newNumberValue( 42 )
			}
		} );
		expect( wrapper.findAll( 'a' ) ).toHaveLength( 0 );
	} );
} );
