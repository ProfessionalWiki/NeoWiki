import { mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import { CdxDialog } from '@wikimedia/codex';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

describe( 'CloseConfirmationDialog', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'msg' ] } );
	} );

	function mountComponent(): VueWrapper {
		return mount( CloseConfirmationDialog, {
			props: { open: true },
			global: {
				mocks: { $i18n: createI18nMock() },
				stubs: { teleport: true },
			},
		} );
	}

	it( 'emits discard when discard button is clicked', async () => {
		const wrapper = mountComponent();

		await wrapper.find( '.cdx-button--action-destructive' ).trigger( 'click' );

		expect( wrapper.emitted( 'discard' ) ).toHaveLength( 1 );
	} );

	it( 'emits keep-editing when keep-editing button is clicked', async () => {
		const wrapper = mountComponent();

		await wrapper.find( '.cdx-button--action-default' ).trigger( 'click' );

		expect( wrapper.emitted( 'keep-editing' ) ).toHaveLength( 1 );
	} );

	it( 'emits keep-editing on backdrop/escape dismiss', async () => {
		const wrapper = mountComponent();

		wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );

		expect( wrapper.emitted( 'keep-editing' ) ).toHaveLength( 1 );
	} );
} );
