import { mount, VueWrapper } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import DialogHeader from '@/components/common/DialogHeader.vue';
import { createI18nMock } from '../../VueTestHelpers.ts';

function mountHeader(
	props: Partial<InstanceType<typeof DialogHeader>[ '$props' ]> = {},
	slots: Record<string, string> = {},
): VueWrapper {
	return mount( DialogHeader, {
		props: { title: 'Edit subject', ...props },
		slots,
		global: {
			mocks: { $i18n: createI18nMock() },
		},
	} );
}

function closeButton( wrapper: VueWrapper ): Element {
	return wrapper.get( 'button[aria-label="cdx-dialog-close-button-label"]' ).element;
}

// For elements neither of which holds the other.
function precedes( first: Element, second: Element ): boolean {
	return first.compareDocumentPosition( second ) === Node.DOCUMENT_POSITION_FOLLOWING;
}

describe( 'DialogHeader', () => {

	it( 'puts the metadata on the title\'s line', () => {
		const wrapper = mountHeader( {}, { metadata: '<span class="metadata">Company</span>' } );

		const titleLine = wrapper.get( 'h2' ).element.parentElement as Element;

		expect( titleLine.contains( wrapper.get( '.metadata' ).element ) ).toBe( true );
	} );

	it( 'shows the supporting text below the title\'s line', () => {
		const wrapper = mountHeader( {}, { 'supporting-text': '<p class="supporting">A company.</p>' } );

		const titleLine = wrapper.get( 'h2' ).element.parentElement as Element;

		expect( precedes( titleLine, wrapper.get( '.supporting' ).element ) ).toBe( true );
	} );

	it( 'puts the start actions before the title', () => {
		const wrapper = mountHeader( {}, { 'start-actions': '<button class="back">Back</button>' } );

		expect( precedes( wrapper.get( '.back' ).element, wrapper.get( 'h2' ).element ) ).toBe( true );
	} );

	it( 'puts the end actions after the title and before Close', () => {
		const wrapper = mountHeader( {}, { 'end-actions': '<button class="more">More</button>' } );

		const more = wrapper.get( '.more' ).element;

		expect( precedes( wrapper.get( 'h2' ).element, more ) ).toBe( true );
		expect( precedes( more, closeButton( wrapper ) ) ).toBe( true );
	} );

	it( 'asks to close the dialog on Close', async () => {
		const wrapper = mountHeader( { useCloseButton: true } );

		( closeButton( wrapper ) as HTMLElement ).click();

		expect( wrapper.emitted( 'close' ) ).toHaveLength( 1 );
	} );

	it( 'offers Close on every screen when the dialog uses it', () => {
		const wrapper = mountHeader( { useCloseButton: true } );

		expect( closeButton( wrapper ).classList ).not.toContain( 'ext-neowiki-dialog-header__close--phones-only' );
	} );

	it( 'still offers Close on phones when the dialog does not use it, as Codex 2 does', () => {
		const wrapper = mountHeader( { useCloseButton: false } );

		expect( closeButton( wrapper ).classList ).toContain( 'ext-neowiki-dialog-header__close--phones-only' );
	} );
} );
