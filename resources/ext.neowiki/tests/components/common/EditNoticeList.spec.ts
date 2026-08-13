import { describe, expect, it } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import EditNoticeList from '@/components/common/EditNoticeList.vue';
import type { EditNotice } from '@/domain/EditNotice';

function mountList( notices: EditNotice[] ): VueWrapper {
	return mount( EditNoticeList, { props: { notices } } );
}

describe( 'EditNoticeList', () => {

	it( 'renders nothing when there are no notices', () => {
		const wrapper = mountList( [] );

		expect( wrapper.find( '.ext-neowiki-edit-notice' ).exists() ).toBe( false );
		expect( wrapper.text() ).toBe( '' );
	} );

	it( 'renders one container per notice', () => {
		const wrapper = mountList( [
			{ key: 'neowiki-editnotice-0', html: '<p>Namespace</p>' },
			{ key: 'contentstabilization-approvalnotice', html: '<b>Needs review</b>' },
		] );

		expect( wrapper.findAll( '.ext-neowiki-edit-notice' ) ).toHaveLength( 2 );
	} );

	it( 'renders the notice markup rather than escaping it', () => {
		const wrapper = mountList( [
			{ key: 'neowiki-editnotice-0', html: '<b>Approval needed</b>' },
		] );

		expect( wrapper.html() ).toContain( '<b>Approval needed</b>' );
	} );

	it( 'imposes no styling of its own on the notice', () => {
		const wrapper = mountList( [
			{ key: 'neowiki-editnotice-0', html: '<div class="my-own-banner">Styled by the author</div>' },
		] );

		const notice = wrapper.find( '.ext-neowiki-edit-notice' );

		// A notice brings its own presentation, so the container adds a hook and nothing else. Wrapping
		// it in a message component would put a box inside a box and impose an icon the author did not ask for.
		expect( notice.element.children ).toHaveLength( 1 );
		expect( notice.element.firstElementChild?.className ).toBe( 'my-own-banner' );
	} );

	it( 'names each notice so it can be targeted by CSS', () => {
		const wrapper = mountList( [
			{ key: 'contentstabilization-approvalnotice', html: '<p>Needs review</p>' },
		] );

		expect( wrapper.find( '.ext-neowiki-edit-notice' ).attributes( 'data-mw-neowiki-editnotice-key' ) )
			.toBe( 'contentstabilization-approvalnotice' );
	} );

	it( 'keeps notices in the order they were given', () => {
		const wrapper = mountList( [
			{ key: 'first', html: '<p>Broadest</p>' },
			{ key: 'second', html: '<p>Narrowest</p>' },
		] );

		expect( wrapper.text().indexOf( 'Broadest' ) ).toBeLessThan( wrapper.text().indexOf( 'Narrowest' ) );
	} );

} );
