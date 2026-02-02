import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import SchemaLookup from '@/components/SubjectCreator/SchemaLookup.vue';
import EditSummary from '@/components/common/EditSummary.vue';
import { createPinia, setActivePinia } from 'pinia';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { newSchema } from '@/TestHelpers.ts';
import { CdxDialog } from '@wikimedia/codex';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Service } from '@/NeoWikiServices.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { Statement } from '@/domain/Statement.ts';
import { PropertyName } from '@/domain/PropertyDefinition.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import { newStringValue } from '@/domain/Value.ts';

const PAGE_ID = 123;
const PAGE_TITLE = 'Test Page';
const SCHEMA_NAME = 'TestSchema';

const SchemaLookupStub = {
	template: '<div class="schema-lookup-stub"></div>',
	emits: [ 'select' ],
	methods: {
		focus: vi.fn(),
	},
};

const SubjectEditorStub = {
	template: '<div class="subject-editor-stub"></div>',
	props: [ 'schemaStatements', 'schemaProperties' ],
	setup() {
		const getSubjectData = (): StatementList => new StatementList( [
			new Statement( new PropertyName( 'Color' ), TextType.typeName, newStringValue( 'Red' ) ),
		] );
		return { getSubjectData };
	},
};

const EditSummaryStub = {
	template: '<div class="edit-summary-stub"><button class="save-button" @click="$emit( \'save\', \'\' )">Save</button></div>',
	props: [ 'helpText', 'saveButtonLabel' ],
	emits: [ 'save' ],
};

const CdxDialogStub = {
	template: '<div class="cdx-dialog-stub"><slot /><slot name="footer" /></div>',
	props: [ 'open', 'title', 'useCloseButton' ],
	emits: [ 'update:open', 'default' ],
};

describe( 'SubjectCreatorDialog', () => {
	let pinia: ReturnType<typeof createPinia>;
	let subjectStore: ReturnType<typeof useSubjectStore>;
	let schemaStore: ReturnType<typeof useSchemaStore>;

	const mountComponent = (
		stubs: Record<string, any> = {},
	): VueWrapper => (
		mount( SubjectCreatorDialog, {
			global: {
				plugins: [ pinia ],
				stubs: {
					SchemaLookup: SchemaLookupStub,
					SubjectEditor: SubjectEditorStub,
					EditSummary: EditSummaryStub,
					CdxButton: true,
					CdxDialog: CdxDialogStub,
					CdxToggleButtonGroup: true,
					CdxField: {
						template: '<div class="cdx-field-stub"><slot /><slot name="label" /></div>',
					},
					CdxTextInput: {
						template: '<input class="cdx-text-input-stub" :value="modelValue" @input="$emit( \'update:modelValue\', $event.target.value )" />',
						props: [ 'modelValue', 'placeholder' ],
						emits: [ 'update:modelValue' ],
					},
					teleport: true,
					...stubs,
				},
				provide: {
					[ Service.ComponentRegistry ]: NeoWikiExtension.getInstance().getTypeSpecificComponentRegistry(),
					[ Service.PropertyTypeRegistry ]: NeoWikiExtension.getInstance().getPropertyTypeRegistry(),
				},
				mocks: {
					$i18n: createI18nMock(),
				},
			},
		} )
	);

	beforeEach( () => {
		setupMwMock( {
			functions: [ 'msg', 'notify', 'config' ],
			config: {
				wgArticleId: PAGE_ID,
				wgTitle: PAGE_TITLE,
			},
		} );

		pinia = createPinia();
		setActivePinia( pinia );

		subjectStore = useSubjectStore();
		subjectStore.createMainSubject = vi.fn().mockResolvedValue( new SubjectId( 's11111111111111' ) );

		schemaStore = useSchemaStore();
		schemaStore.getOrFetchSchema = vi.fn().mockResolvedValue( newSchema( { title: SCHEMA_NAME } ) );
	} );

	it( 'opens dialog when button is clicked', async () => {
		const wrapper = mountComponent();
		const button = wrapper.find( '.ext-neowiki-subject-creator-trigger' );
		expect( button.exists() ).toBe( true );

		await button.trigger( 'click' );

		const dialog = wrapper.findComponent( CdxDialog );
		expect( dialog.props( 'open' ) ).toBe( true );
	} );

	it( 'shows schema search in the existing-schema block', () => {
		const wrapper = mountComponent();

		expect( wrapper.find( '.schema-lookup-stub' ).exists() ).toBe( true );
	} );

	it( 'does not show label input or SubjectEditor before schema selection', () => {
		const wrapper = mountComponent();

		expect( wrapper.find( '.cdx-text-input-stub' ).exists() ).toBe( false );
		expect( wrapper.find( '.subject-editor-stub' ).exists() ).toBe( false );
		expect( wrapper.find( '.edit-summary-stub' ).exists() ).toBe( false );
	} );

	it( 'shows label input and SubjectEditor after schema selection', async () => {
		const wrapper = mountComponent();

		await wrapper.findComponent( SchemaLookup ).vm.$emit( 'select', SCHEMA_NAME );
		await flushPromises();

		expect( wrapper.find( '.cdx-text-input-stub' ).exists() ).toBe( true );
		expect( wrapper.find( '.subject-editor-stub' ).exists() ).toBe( true );
		expect( wrapper.find( '.edit-summary-stub' ).exists() ).toBe( true );
	} );

	it( 'defaults label to page title', async () => {
		const wrapper = mountComponent();

		await wrapper.findComponent( SchemaLookup ).vm.$emit( 'select', SCHEMA_NAME );
		await flushPromises();

		const labelInput = wrapper.find( '.cdx-text-input-stub' );
		expect( ( labelInput.element as HTMLInputElement ).value ).toBe( PAGE_TITLE );
	} );

	it( 'calls createMainSubject on save with correct arguments', async () => {
		const wrapper = mountComponent();

		await wrapper.findComponent( SchemaLookup ).vm.$emit( 'select', SCHEMA_NAME );
		await flushPromises();

		await wrapper.findComponent( EditSummary ).vm.$emit( 'save', 'test summary' );
		await flushPromises();

		expect( subjectStore.createMainSubject ).toHaveBeenCalledWith(
			PAGE_ID,
			PAGE_TITLE,
			SCHEMA_NAME,
			expect.any( StatementList ),
		);
	} );

	it( 'shows success notification and closes dialog after save', async () => {
		const wrapper = mountComponent();

		await wrapper.find( '.ext-neowiki-subject-creator-trigger' ).trigger( 'click' );
		await wrapper.findComponent( SchemaLookup ).vm.$emit( 'select', SCHEMA_NAME );
		await flushPromises();

		await wrapper.findComponent( EditSummary ).vm.$emit( 'save', '' );
		await flushPromises();

		expect( mw.notify ).toHaveBeenCalledWith(
			expect.any( String ),
			expect.objectContaining( { type: 'success' } ),
		);

		const dialog = wrapper.findComponent( CdxDialog );
		expect( dialog.props( 'open' ) ).toBe( false );
	} );

	it( 'shows error notification on save failure and keeps dialog open', async () => {
		subjectStore.createMainSubject = vi.fn().mockRejectedValue( new Error( 'Server error' ) );

		const wrapper = mountComponent();

		await wrapper.find( '.ext-neowiki-subject-creator-trigger' ).trigger( 'click' );
		await wrapper.findComponent( SchemaLookup ).vm.$emit( 'select', SCHEMA_NAME );
		await flushPromises();

		await wrapper.findComponent( EditSummary ).vm.$emit( 'save', '' );
		await flushPromises();

		expect( mw.notify ).toHaveBeenCalledWith(
			'Server error',
			expect.objectContaining( { type: 'error' } ),
		);

		const dialog = wrapper.findComponent( CdxDialog );
		expect( dialog.props( 'open' ) ).toBe( true );
	} );

	it( 'does not save when label is empty', async () => {
		const wrapper = mountComponent();

		await wrapper.findComponent( SchemaLookup ).vm.$emit( 'select', SCHEMA_NAME );
		await flushPromises();

		const labelInput = wrapper.find( '.cdx-text-input-stub' );
		await labelInput.setValue( '' );
		await flushPromises();

		await wrapper.findComponent( EditSummary ).vm.$emit( 'save', '' );
		await flushPromises();

		expect( subjectStore.createMainSubject ).not.toHaveBeenCalled();
		expect( mw.notify ).toHaveBeenCalledWith(
			expect.any( String ),
			expect.objectContaining( { type: 'error' } ),
		);
	} );
} );
