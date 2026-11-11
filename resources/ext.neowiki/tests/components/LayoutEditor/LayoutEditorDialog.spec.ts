import { flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import LayoutEditorDialog, { type LayoutSaveHandler } from '@/components/LayoutEditor/LayoutEditorDialog.vue';
import LayoutEditor from '@/components/LayoutEditor/LayoutEditor.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import DialogHeader from '@/components/common/DialogHeader.vue';
import SchemaNameDisplay from '@/components/common/SchemaNameDisplay.vue';
import { Layout } from '@/domain/Layout.ts';
import { createI18nMock, pressHeaderClose, setupMwMock } from '../../VueTestHelpers.ts';

const LAYOUT = new Layout( 'CompanyInfo', 'Company', 'infobox', '', [], {} );

const LayoutEditorStub = {
	template: '<div></div>',
	props: [ 'initialLayout' ],
	emits: [ 'change' ],
	methods: {
		getLayout: (): Layout => LAYOUT,
	},
};

describe( 'LayoutEditorDialog', () => {
	let onSave: ReturnType<typeof vi.fn<LayoutSaveHandler>>;

	beforeEach( () => {
		onSave = vi.fn<LayoutSaveHandler>();
		// 'message' and 'util' for the Schema's name and link in the header.
		setupMwMock( { functions: [ 'message', 'msg', 'notify', 'util' ] } );
	} );

	function mountComponent(): VueWrapper {
		return mount( LayoutEditorDialog, {
			props: {
				initialLayout: LAYOUT,
				open: true,
				onSave,
			},
			global: {
				mocks: { $i18n: createI18nMock() },
				stubs: {
					LayoutEditor: LayoutEditorStub,
					SummaryAction: true,
					CloseConfirmationDialog: true,
				},
			},
		} );
	}

	it( 'asks before its header\'s Close throws away a change', async () => {
		const wrapper = mountComponent();
		await wrapper.findComponent( LayoutEditor ).vm.$emit( 'change' );

		await pressHeaderClose( wrapper );

		expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
		expect( wrapper.findComponent( CloseConfirmationDialog ).props( 'open' ) ).toBe( true );
	} );

	it( 'disables save while a save is in flight', async () => {
		onSave.mockReturnValue( new Promise( () => {
			// Left in flight, so the dialog is observed while it saves.
		} ) );
		const wrapper = mountComponent();
		await wrapper.findComponent( LayoutEditor ).vm.$emit( 'change' );

		wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
		await flushPromises();

		expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( true );
	} );

	it( 'shows the Layout\'s Schema in the header', () => {
		const wrapper = mountComponent();

		expect( wrapper.findComponent( DialogHeader ).findComponent( SchemaNameDisplay ).props( 'schemaName' ) ).toBe( 'Company' );
	} );

	it( 'opens the Layout\'s Schema in a new tab, away from the unsaved Layout', () => {
		const wrapper = mountComponent();

		expect( wrapper.findComponent( SchemaNameDisplay ).props( 'link' ) ).toBe( 'new-tab' );
	} );
} );
