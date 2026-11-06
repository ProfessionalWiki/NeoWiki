import { flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import LayoutEditorDialog, { type LayoutSaveHandler } from '@/components/LayoutEditor/LayoutEditorDialog.vue';
import LayoutEditor from '@/components/LayoutEditor/LayoutEditor.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import DialogHeader from '@/components/common/DialogHeader.vue';
import SchemaNameDisplay from '@/components/common/SchemaNameDisplay.vue';
import { Layout } from '@/domain/Layout.ts';
import { createI18nMock, pressHeaderClose, setupMwMock } from '../../VueTestHelpers.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';

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
	let pinia: ReturnType<typeof createPinia>;

	beforeEach( () => {
		onSave = vi.fn<LayoutSaveHandler>();
		// 'message' and 'util' for the Schema's name and link in the header, 'config' and 'language' for its label.
		setupMwMock( { functions: [ 'message', 'msg', 'notify', 'util', 'config', 'language' ] } );
		pinia = createPinia();
		setActivePinia( pinia );
		useSchemaStore().fetchAllSchemaSummaries = vi.fn().mockResolvedValue( [] );
	} );

	function mountComponent(): VueWrapper {
		return mount( LayoutEditorDialog, {
			props: {
				initialLayout: LAYOUT,
				open: true,
				onSave,
			},
			global: {
				plugins: [ pinia ],
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

	it( 'shows the Layout\'s Schema under its label in the reader\'s language', async () => {
		useSchemaStore().fetchAllSchemaSummaries = vi.fn().mockResolvedValue( [
			{ name: 'Article', description: '', propertyCount: 1, subjectsAreDependent: false, labels: { en: 'Story' } },
			{ name: 'Company', description: '', propertyCount: 2, subjectsAreDependent: false, labels: { en: 'Firm' } },
			{ name: 'Person', description: '', propertyCount: 3, subjectsAreDependent: false, labels: { en: 'Human' } },
		] );
		const wrapper = mountComponent();
		await flushPromises();

		expect( wrapper.findComponent( SchemaNameDisplay ).props( 'displayName' ) ).toBe( 'Firm' );
	} );

	it( 'shows the Layout\'s Schema under its name when the Schemas cannot be read', async () => {
		useSchemaStore().fetchAllSchemaSummaries = vi.fn().mockRejectedValue( new Error( 'Network down' ) );
		const wrapper = mountComponent();
		await flushPromises();

		expect( wrapper.findComponent( SchemaNameDisplay ).props( 'displayName' ) ).toBe( 'Company' );
	} );
} );
