import { enableAutoUnmount, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import SchemaAbandonmentDialog from '@/components/SubjectCreator/SchemaAbandonmentDialog.vue';
import { CdxDialog } from '@wikimedia/codex';
import { createI18nMock, openDialogTitles, setupMwMock } from '../../VueTestHelpers.ts';

enableAutoUnmount( afterEach );

describe( 'SchemaAbandonmentDialog', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'msg' ] } );
	} );

	function mountComponent(): VueWrapper {
		return mount( SchemaAbandonmentDialog, {
			props: { open: true },
			global: {
				mocks: { $i18n: createI18nMock() },
				stubs: { teleport: true, CdxButton: { template: '<button><slot /></button>' } },
			},
		} );
	}

	// Each button throws away, keeps or saves the Schema the user drafted, so a swapped handler
	// destroys work the label promised to keep.
	it.each( [
		[ 'abandon', 'neowiki-schema-abandonment-abandon' ],
		[ 'save-schema', 'neowiki-schema-abandonment-save-schema' ],
		[ 'keep-editing', 'neowiki-schema-abandonment-keep-editing' ],
	] )( 'emits %s from the button labelled %s', async ( event, label ) => {
		const wrapper = mountComponent();

		await wrapper.findAll( 'button' ).find( ( button ) => button.text() === label )!.trigger( 'click' );

		expect( wrapper.emitted( event ) ).toHaveLength( 1 );
	} );

	it( 'emits keep-editing on backdrop/escape dismiss', () => {
		const wrapper = mountComponent();

		wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );

		expect( wrapper.emitted( 'keep-editing' ) ).toHaveLength( 1 );
	} );

	// The stacking rule this relies on is spelled out in CloseConfirmationDialog.spec.ts.
	describe( 'in front of the dialog it confirms', () => {
		// Deliberately the wrong way round: the question is written, and so mounts, first.
		const HostOpeningDialogsLater = {
			components: { SchemaAbandonmentDialog, CdxDialog },
			template: '<SchemaAbandonmentDialog :open="confirming" />' +
				'<CdxDialog :open="true" title="first-dialog" />' +
				'<CdxDialog v-if="secondOpen" :open="true" title="second-dialog" />',
			props: [ 'confirming', 'secondOpen' ],
		};

		function mountHost(): VueWrapper {
			return mount( HostOpeningDialogsLater, {
				props: { confirming: false, secondOpen: false },
				global: {
					mocks: { $i18n: createI18nMock() },
					stubs: { CdxButton: { template: '<button><slot /></button>' } },
				},
			} );
		}

		it( 'opens above a dialog that mounted after it', async () => {
			const wrapper = mountHost();

			await wrapper.setProps( { confirming: true } );

			expect( openDialogTitles() ).toEqual( [ 'first-dialog', 'neowiki-schema-abandonment-title' ] );
		} );

		// Asking a second time has to land on top of whatever is open by then, so the first answer
		// has to take the dialog out of the document again rather than leave it standing.
		it( 'opens above a dialog that mounted while it was closed', async () => {
			const wrapper = mountHost();
			await wrapper.setProps( { confirming: true } );
			await wrapper.setProps( { confirming: false } );
			await wrapper.setProps( { secondOpen: true } );

			await wrapper.setProps( { confirming: true } );

			expect( openDialogTitles() ).toEqual( [ 'first-dialog', 'second-dialog', 'neowiki-schema-abandonment-title' ] );
		} );
	} );
} );
