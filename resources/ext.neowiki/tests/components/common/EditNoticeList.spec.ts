import { describe, expect, it } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { CdxMessage } from '@wikimedia/codex';
import EditNoticeList from '@/components/common/EditNoticeList.vue';
import type { EditNotice } from '@/domain/EditNotice';

function mountList( notices: EditNotice[] ): VueWrapper {
	return mount( EditNoticeList, { props: { notices } } );
}

describe( 'EditNoticeList', () => {

	it( 'renders nothing when there are no notices', () => {
		const wrapper = mountList( [] );

		expect( wrapper.findComponent( CdxMessage ).exists() ).toBe( false );
		expect( wrapper.text() ).toBe( '' );
	} );

	it( 'renders one message per notice', () => {
		const wrapper = mountList( [
			{ key: 'neowiki-editnotice-0', html: '<p>Namespace</p>' },
			{ key: 'contentstabilization-approvalnotice', html: '<b>Needs review</b>' },
		] );

		expect( wrapper.findAllComponents( CdxMessage ) ).toHaveLength( 2 );
	} );

	it( 'renders the notice markup rather than escaping it', () => {
		const wrapper = mountList( [
			{ key: 'neowiki-editnotice-0', html: '<b>Approval needed</b>' },
		] );

		expect( wrapper.html() ).toContain( '<b>Approval needed</b>' );
		expect( wrapper.text() ).toContain( 'Approval needed' );
	} );

	it( 'keeps notices in the order they were given', () => {
		const wrapper = mountList( [
			{ key: 'first', html: '<p>Broadest</p>' },
			{ key: 'second', html: '<p>Narrowest</p>' },
		] );

		expect( wrapper.text().indexOf( 'Broadest' ) ).toBeLessThan( wrapper.text().indexOf( 'Narrowest' ) );
	} );

} );
