import { enableAutoUnmount, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import { CdxDialog } from '@wikimedia/codex';
import { createI18nMock, openDialogTitles, pressHeaderClose, setupMwMock } from '../../VueTestHelpers.ts';

enableAutoUnmount( afterEach );

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

	it( 'emits keep-editing on its header\'s Close', async () => {
		const wrapper = mountComponent();

		await pressHeaderClose( wrapper );

		expect( wrapper.emitted( 'keep-editing' ) ).toHaveLength( 1 );
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

	// Which action discards is the whole question the dialog asks, and the two are told apart by
	// their labels alone: swapped, the destructive button would be the one that keeps editing.
	it( 'offers discarding as the destructive action and keeping the edits as the default one', () => {
		const dialog = mountComponent().findComponent( CdxDialog );

		expect( dialog.props( 'primaryAction' ) ).toEqual( {
			label: 'neowiki-close-confirmation-discard',
			actionType: 'destructive',
		} );
		expect( dialog.props( 'defaultAction' ) ).toEqual( {
			label: 'neowiki-close-confirmation-keep-editing',
		} );
	} );

	// Behind its host, the question is invisible under a dialog Codex has marked inert, so the close
	// button appears to do nothing.
	describe( 'in front of the dialog it confirms', () => {
		// Deliberately the wrong way round: the confirmation is written, and so mounts, first.
		const HostOpeningDialogsLater = {
			components: { CloseConfirmationDialog, CdxDialog },
			template: '<CloseConfirmationDialog :open="confirming" />' +
				'<CdxDialog :open="true" title="first-dialog" />' +
				'<CdxDialog v-if="secondOpen" :open="true" title="second-dialog" />',
			props: [ 'confirming', 'secondOpen' ],
		};

		function mountHost(): VueWrapper {
			return mount( HostOpeningDialogsLater, {
				props: { confirming: false, secondOpen: false },
				global: { mocks: { $i18n: createI18nMock() } },
			} );
		}

		it( 'opens above a dialog that mounted after it', async () => {
			const wrapper = mountHost();

			await wrapper.setProps( { confirming: true } );

			expect( openDialogTitles() ).toEqual( [ 'first-dialog', 'neowiki-close-confirmation-title' ] );
		} );

		// Asking a second time has to land on top of whatever is open by then, so the first answer
		// has to take the dialog out of the document again rather than leave it standing.
		it( 'opens above a dialog that mounted while it was closed', async () => {
			const wrapper = mountHost();
			await wrapper.setProps( { confirming: true } );
			await wrapper.setProps( { confirming: false } );
			await wrapper.setProps( { secondOpen: true } );

			await wrapper.setProps( { confirming: true } );

			expect( openDialogTitles() ).toEqual( [ 'first-dialog', 'second-dialog', 'neowiki-close-confirmation-title' ] );
		} );
	} );
} );
