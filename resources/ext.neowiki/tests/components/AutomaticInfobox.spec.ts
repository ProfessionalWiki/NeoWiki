import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import AutomaticInfobox from '@/components/AutomaticInfobox.vue';

describe( 'AutomaticInfobox', () => {
	it( 'renders the title correctly', () => {
		const wrapper = mount( AutomaticInfobox, {
			props: {
				title: 'Test Title'
			}
		} );

		expect( wrapper.find( '.infobox-title' ).text() ).toBe( 'Test Title' );
	} );

	it( 'renders statements correctly', () => {
		const statements = [
			{ property: 'Name', value: 'John Doe' },
			{ property: 'Age', value: '30' }
		];

		const wrapper = mount( AutomaticInfobox, {
			props: {
				title: 'Test Title',
				statements
			}
		} );

		const statementElements = wrapper.findAll( '.infobox-statement' );
		expect( statementElements ).toHaveLength( 2 );

		expect( statementElements[ 0 ].find( '.infobox-statement-property' ).text() ).toBe( 'Name' );
		expect( statementElements[ 0 ].find( '.infobox-statement-value' ).text() ).toBe( 'John Doe' );

		expect( statementElements[ 1 ].find( '.infobox-statement-property' ).text() ).toBe( 'Age' );
		expect( statementElements[ 1 ].find( '.infobox-statement-value' ).text() ).toBe( '30' );
	} );

	it( 'renders without statements when not provided', () => {
		const wrapper = mount( AutomaticInfobox, {
			props: {
				title: 'Test Title'
			}
		} );

		expect( wrapper.find( '.infobox-statements' ).exists() ).toBe( true );
		expect( wrapper.findAll( '.infobox-statement' ) ).toHaveLength( 0 );
	} );
} );
