import { mount } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';
import AutomaticInfobox from '@/components/AutomaticInfobox.vue';

const $i18n = vi.fn().mockImplementation( ( key ) => ( {
	text: () => key
} ) );

describe( 'AutomaticInfobox', () => {
	it( 'renders the title correctly', () => {
		const wrapper = mount( AutomaticInfobox, {
			props: {
				title: 'Test Title',
				statements: []
			},
			global: {
				mocks: {
					$i18n
				}
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
			},
			global: {
				mocks: {
					$i18n
				}
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
			},
			global: {
				mocks: {
					$i18n
				}
			}
		} );

		expect( wrapper.findAll( '.infobox-statement' ) ).toHaveLength( 0 );
	} );

	it( 'renders the edit link correctly', () => {
		const wrapper = mount( AutomaticInfobox, {
			props: {
				title: 'Test Title',
				statements: []
			},
			global: {
				mocks: {
					$i18n
				}
			}
		} );

		const editLink = wrapper.find( '.infobox-edit a' );
		expect( editLink.exists() ).toBe( true );
		expect( editLink.text() ).toBe( 'neowiki-infobox-edit-link' );
	} );
} );
