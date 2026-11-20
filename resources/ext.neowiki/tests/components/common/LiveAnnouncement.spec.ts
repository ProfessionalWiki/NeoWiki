import { flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import LiveAnnouncement, { type LiveAnnouncementExposes } from '@/components/common/LiveAnnouncement.vue';

describe( 'LiveAnnouncement', () => {

	function announce( wrapper: VueWrapper, message: string ): Promise<void> {
		return ( wrapper.vm as unknown as LiveAnnouncementExposes ).announce( message );
	}

	// What the live region holds after each change to it, which is when a screen reader speaks.
	async function textsDuring( wrapper: VueWrapper, action: () => Promise<void> ): Promise<string[]> {
		const texts: string[] = [];
		const observer = new MutationObserver( () => texts.push( wrapper.element.textContent ?? '' ) );
		observer.observe( wrapper.element, { childList: true, characterData: true, subtree: true } );

		await action();
		await flushPromises();
		observer.disconnect();

		return texts;
	}

	it( 'holds the message it was given in a polite live region', async () => {
		const wrapper = mount( LiveAnnouncement );

		await announce( wrapper, 'Draft moved to position 2 of 5.' );

		expect( wrapper.attributes( 'aria-live' ) ).toBe( 'polite' );
		expect( wrapper.text() ).toBe( 'Draft moved to position 2 of 5.' );
	} );

	it( 'changes again for a message that repeats the one before it', async () => {
		const wrapper = mount( LiveAnnouncement );
		await announce( wrapper, 'Option removed.' );

		const texts = await textsDuring( wrapper, () => announce( wrapper, 'Option removed.' ) );

		expect( texts ).toContain( 'Option removed.' );
	} );

} );
