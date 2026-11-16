import { mount, VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import { h, nextTick, type Component } from 'vue';
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

describe( 'DialogHeader supporting text on scroll', () => {

	// A dialog as Codex renders one, with a body holding two panes and a textarea of their content.
	const Dialog: Component = {
		props: { collapses: { type: Boolean, default: true } },
		setup( props ) {
			return () => h( 'div', { class: 'cdx-dialog' }, [
				h( DialogHeader, { title: 'Editing Museum schema', collapsesSupportingText: props.collapses }, {
					'supporting-text': () => h( 'button', { class: 'description' }, 'A museum.' ),
				} ),
				h( 'div', { class: 'cdx-dialog__body' }, [
					h( 'div', { class: 'ext-neowiki-editor-panes__pane list' } ),
					h( 'div', { class: 'ext-neowiki-editor-panes__pane form' }, [ h( 'textarea' ), h( 'button', { class: 'field' } ) ] ),
				] ),
			] );
		},
	};

	function mountDialog( collapses = true ): VueWrapper {
		const wrapper = mount( Dialog, {
			props: { collapses },
			attachTo: document.body,
			global: { mocks: { $i18n: createI18nMock() } },
		} );
		givenSupportingTextHeight( wrapper, 40 );
		return wrapper;
	}

	// jsdom resolves no layout. The height is the row's: what collapsing the supporting text hands the panes.
	function givenSupportingTextHeight( wrapper: VueWrapper, height: number ): void {
		Object.defineProperty( supportingText( wrapper ), 'offsetHeight', { configurable: true, value: height } );
	}

	function supportingText( wrapper: VueWrapper ): Element {
		return wrapper.get( '.description' ).element.closest( '.ext-neowiki-dialog-header__supporting-text' ) as Element;
	}

	function collapsed( wrapper: VueWrapper ): boolean {
		const element = supportingText( wrapper );
		return element.hasAttribute( 'inert' ) && element.classList.contains( 'ext-neowiki-dialog-header__supporting-text--collapsed' );
	}

	function expanded( wrapper: VueWrapper ): boolean {
		const element = supportingText( wrapper );
		return !element.hasAttribute( 'inert' ) && !element.classList.contains( 'ext-neowiki-dialog-header__supporting-text--collapsed' );
	}

	// jsdom resolves no layout, so the scroller is given the room it has: by default 700px more than it shows.
	async function scrolled( wrapper: VueWrapper, selector: string, scrollTop: number, scrollHeight = 1000 ): Promise<void> {
		const scroller = wrapper.get( selector ).element;
		Object.defineProperty( scroller, 'scrollHeight', { configurable: true, value: scrollHeight } );
		Object.defineProperty( scroller, 'clientHeight', { configurable: true, value: 300 } );
		Object.defineProperty( scroller, 'scrollTop', { configurable: true, writable: true, value: scrollTop } );
		scroller.dispatchEvent( new Event( 'scroll' ) );
		await nextTick();
	}

	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'collapses the supporting text once a pane is scrolled down', async () => {
		const wrapper = mountDialog();

		await scrolled( wrapper, '.form', 50 );

		expect( collapsed( wrapper ) ).toBe( true );
	} );

	it( 'keeps the supporting text while a pane is within 8px of its top', async () => {
		const wrapper = mountDialog();

		await scrolled( wrapper, '.form', 8 );

		expect( expanded( wrapper ) ).toBe( true );
	} );

	it( 'brings the supporting text back once the pane scrolled last is back up, though another is still down', async () => {
		const wrapper = mountDialog();
		await scrolled( wrapper, '.list', 50 );
		await scrolled( wrapper, '.form', 50 );

		await scrolled( wrapper, '.form', 0 );

		expect( expanded( wrapper ) ).toBe( true );
	} );

	// Collapsing it would hand the pane the 40px it took, leaving 16px to scroll: within 8px of the
	// top it would expand again.
	it( 'keeps the supporting text where collapsing it would leave too little to scroll', async () => {
		const wrapper = mountDialog();

		await scrolled( wrapper, '.form', 12, 356 );

		expect( expanded( wrapper ) ).toBe( true );
	} );

	it( 'collapses the supporting text for the dialog body\'s own scrolling', async () => {
		const wrapper = mountDialog();

		await scrolled( wrapper, '.cdx-dialog__body', 50 );

		expect( collapsed( wrapper ) ).toBe( true );
	} );

	it( 'keeps the supporting text when something inside a pane scrolls', async () => {
		const wrapper = mountDialog();

		await scrolled( wrapper, 'textarea', 50 );

		expect( expanded( wrapper ) ).toBe( true );
	} );

	it( 'keeps the supporting text while it holds the focus', async () => {
		const wrapper = mountDialog();
		( wrapper.get( '.description' ).element as HTMLElement ).focus();

		await scrolled( wrapper, '.form', 50 );

		expect( expanded( wrapper ) ).toBe( true );
	} );

	// Collapsing as the focus leaves would move the pane's content under a press that took it there.
	it( 'leaves the supporting text expanded as the focus leaves it, until a pane is scrolled again', async () => {
		const wrapper = mountDialog();
		( wrapper.get( '.description' ).element as HTMLElement ).focus();
		await scrolled( wrapper, '.form', 50 );

		( wrapper.get( '.field' ).element as HTMLElement ).focus();
		await nextTick();

		expect( expanded( wrapper ) ).toBe( true );
	} );

	// A pane stops scrolling where the panes stack below desktop width, which no scroll event reports.
	it( 'brings the supporting text back once a resize leaves the pane scrolled last at its top', async () => {
		const wrapper = mountDialog();
		await scrolled( wrapper, '.form', 50 );
		Object.defineProperty( wrapper.get( '.form' ).element, 'scrollTop', { configurable: true, writable: true, value: 0 } );

		window.dispatchEvent( new Event( 'resize' ) );
		await nextTick();

		expect( expanded( wrapper ) ).toBe( true );
	} );

	it( 'keeps the supporting text in a dialog that does not ask for it to collapse', async () => {
		const wrapper = mountDialog( false );

		await scrolled( wrapper, '.form', 50 );

		expect( expanded( wrapper ) ).toBe( true );
	} );
} );
