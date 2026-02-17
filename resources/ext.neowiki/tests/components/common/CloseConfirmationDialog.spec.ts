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

	it( 'emits discard on primary action', () => {
		const wrapper = mountComponent();

		wrapper.findComponent( CdxDialog ).vm.$emit( 'primary' );

		expect( wrapper.emitted( 'discard' ) ).toHaveLength( 1 );
	} );

	it( 'emits keep-editing on default action', () => {
		const wrapper = mountComponent();

		wrapper.findComponent( CdxDialog ).vm.$emit( 'default' );

		expect( wrapper.emitted( 'keep-editing' ) ).toHaveLength( 1 );
	} );

	it( 'emits keep-editing on backdrop/escape dismiss', () => {
		const wrapper = mountComponent();

		wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );

		expect( wrapper.emitted( 'keep-editing' ) ).toHaveLength( 1 );
	} );
} );
