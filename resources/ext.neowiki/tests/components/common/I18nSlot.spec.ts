import { describe, expect, it, beforeEach } from 'vitest';
import I18nSlot from '@/components/common/I18nSlot.vue';
import { setupMwMock } from '../../VueTestHelpers.ts';
import { mount, VueWrapper } from '@vue/test-utils';

function createWrapper( params: {
	messageKey: string;
	slot?: string;
	textClass?: string;
} ): VueWrapper {
	return mount( I18nSlot, {
		props: {
			messageKey: params.messageKey,
			textClass: params.textClass,
		},
		slots: params.slot ? {
			default: params.slot,
		} : {},
	} );
}

describe( 'I18nSlot', () => {
	beforeEach( () => {
		setupMwMock( {
			messages: {
				'simple-key': 'Simple Text',
				'middle-key': ( param ) => `Prefix ${ param } Suffix`,
				'start-key': ( param ) => `${ param } Suffix`,
				'end-key': ( param ) => `Prefix ${ param }`,
			},
			functions: [ 'message' ],
		} );
	} );

	it( 'renders simple text correctly', () => {
		const wrapper = createWrapper( {
			messageKey: 'simple-key',
			slot: 'Simple Text',
		} );

		expect( wrapper.text() ).toBe( 'Simple Text' );
		expect( wrapper.findAll( 'span' ) ).toHaveLength( 2 ); // Root span + text span
	} );

	it( 'splits text and inserts slot correctly', () => {
		const wrapper = createWrapper( {
			messageKey: 'middle-key',
			slot: '<button>Some HTML</button>',
		} );

		expect( wrapper.text() ).toBe( 'Prefix Some HTML Suffix' );
		expect( wrapper.find( 'button' ).exists() ).toBe( true );
		expect( wrapper.find( 'button' ).text() ).toBe( 'Some HTML' );
	} );

	it( 'applies textClass to text parts', () => {
		const wrapper = createWrapper( {
			messageKey: 'middle-key',
			slot: 'SLOT',
			textClass: 'text-class',
		} );

		const textSpans = wrapper.findAll( '.text-class' );
		expect( textSpans ).toHaveLength( 2 );
		expect( textSpans[ 0 ].text() ).toBe( 'Prefix' );
		expect( textSpans[ 1 ].text() ).toBe( 'Suffix' );
	} );

	it( 'handles slot at the start', () => {
		const wrapper = createWrapper( {
			messageKey: 'start-key',
			slot: '<button>Some HTML</button>',
		} );

		expect( wrapper.text() ).toBe( 'Some HTML Suffix' );
	} );

	it( 'handles slot at the end', () => {
		const wrapper = createWrapper( {
			messageKey: 'end-key',
			slot: '<button>Some HTML</button>',
		} );

		expect( wrapper.text() ).toBe( 'Prefix Some HTML' );
	} );
} );
