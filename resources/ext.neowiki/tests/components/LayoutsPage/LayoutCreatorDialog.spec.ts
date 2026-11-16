import { flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import LayoutCreatorDialog from '@/components/LayoutsPage/LayoutCreatorDialog.vue';
import LayoutCreator from '@/components/LayoutsPage/LayoutCreator.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import { Layout } from '@/domain/Layout.ts';
import { createI18nMock, pressHeaderClose, setupMwMock } from '../../VueTestHelpers.ts';

const LayoutCreatorStub = {
	template: '<div></div>',
	emits: [ 'change' ],
	methods: {
		validate: (): Promise<boolean> => new Promise( () => {
			// Left in flight at the check that the name is free, the first request a save waits on.
		} ),
		getLayout: (): Layout => new Layout( 'CompanyInfo', 'Company', 'infobox', '', [], {} ),
	},
};

describe( 'LayoutCreatorDialog', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'msg', 'notify' ] } );
	} );

	function mountComponent(): VueWrapper {
		return mount( LayoutCreatorDialog, {
			props: { open: true, onSave: vi.fn() },
			global: {
				mocks: { $i18n: createI18nMock() },
				stubs: {
					LayoutCreator: LayoutCreatorStub,
					SummaryAction: true,
					CloseConfirmationDialog: true,
				},
			},
		} );
	}

	it( 'asks before its header\'s Close throws away a change', async () => {
		const wrapper = mountComponent();
		await wrapper.findComponent( LayoutCreator ).vm.$emit( 'change' );

		await pressHeaderClose( wrapper );

		expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
		expect( wrapper.findComponent( CloseConfirmationDialog ).props( 'open' ) ).toBe( true );
	} );

	it( 'disables save while a save is in flight', async () => {
		const wrapper = mountComponent();
		await wrapper.findComponent( LayoutCreator ).vm.$emit( 'change' );

		wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
		await flushPromises();

		expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( true );
	} );
} );
