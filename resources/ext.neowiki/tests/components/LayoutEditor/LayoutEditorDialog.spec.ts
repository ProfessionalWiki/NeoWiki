import { flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import LayoutEditorDialog, { type LayoutSaveHandler } from '@/components/LayoutEditor/LayoutEditorDialog.vue';
import LayoutEditor from '@/components/LayoutEditor/LayoutEditor.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import { Layout } from '@/domain/Layout.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

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
		setupMwMock( { functions: [ 'msg', 'notify' ] } );
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
} );
