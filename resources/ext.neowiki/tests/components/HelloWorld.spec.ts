import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HelloWorld from '@/components/HelloWorld.vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';

// Mock the $i18n function
const $i18n = vi.fn( () => ( {
	text: () => 'Mocked i18n text'
} ) );

describe( 'HelloWorld', () => {
	it( 'renders properly', () => {
		const wrapper = mount( HelloWorld, {
			props: {
				msg: 'Hello Vitest'
			},
			global: {
				mocks: {
					$i18n
				},
				stubs: {
					CdxButton,
					CdxIcon
				}
			}
		} );

		expect( wrapper.text() ).toContain( 'Hello Vitest' );
		expect( wrapper.text() ).toContain( 'Mocked i18n text' );
	} );

	it( 'increments count when button is clicked', async () => {
		const wrapper = mount( HelloWorld, {
			props: {
				msg: 'Hello Vitest'
			},
			global: {
				mocks: {
					$i18n
				},
				stubs: {
					CdxButton,
					CdxIcon
				}
			}
		} );

		const button = wrapper.findComponent( CdxButton );
		expect( button.exists() ).toBe( true );

		await button.trigger( 'click' );

		expect( wrapper.text() ).toContain( 'count is 1' );
	} );

	it( 'contains the read-the-docs paragraph', () => {
		const wrapper = mount( HelloWorld, {
			props: {
				msg: 'Hello Vitest'
			},
			global: {
				mocks: {
					$i18n
				},
				stubs: {
					CdxButton,
					CdxIcon
				}
			}
		} );

		const paragraph = wrapper.find( '.read-the-docs' );
		expect( paragraph.exists() ).toBe( true );
		expect( paragraph.text() ).toContain( 'Click on the Vite and Vue logos to learn more' );
	} );
} );
