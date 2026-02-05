import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
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
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';

import { useSchemaPermissions } from '@/composables/useSchemaPermissions.ts';

const PAGE_ID = 123;
const PAGE_TITLE = 'Test Page';
const SCHEMA_NAME = 'TestSchema';
const NEW_SCHEMA_NAME = 'NewSchema';

vi.mock( '@/composables/useSchemaPermissions.ts' );

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

const SchemaEditorStub = {
	template: '<div class="schema-editor-stub"></div>',
	props: [ 'initialSchema' ],
	setup() {
		const getSchema = (): Schema => new Schema( '', '', new PropertyDefinitionList( [] ) );
		return { getSchema };
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

const CdxToggleButtonGroupStub = {
	name: 'CdxToggleButtonGroup',
	template: '<div class="cdx-toggle-button-group-stub"></div>',
	props: [ 'modelValue', 'buttons' ],
	emits: [ 'update:modelValue' ],
};

describe( 'SubjectCreatorDialog', () => {
	let pinia: ReturnType<typeof createPinia>;
	let subjectStore: ReturnType<typeof useSubjectStore>;
	let schemaStore: ReturnType<typeof useSchemaStore>;
	const canCreateSchemas = ref( true );

	const mountComponent = (
		stubs: Record<string, any> = {},
	): VueWrapper => (
		mount( SubjectCreatorDialog, {
			global: {
				plugins: [ pinia ],
				stubs: {
					SchemaLookup: SchemaLookupStub,
					SubjectEditor: SubjectEditorStub,
					SchemaEditor: SchemaEditorStub,
					EditSummary: EditSummaryStub,
					CdxButton: true,
					CdxDialog: CdxDialogStub,
					CdxToggleButtonGroup: CdxToggleButtonGroupStub,
					CdxMessage: true,
					CdxField: {
						template: '<div class="cdx-field-stub"><slot /><slot name="label" /><slot name="messages" /></div>',
					},
					CdxTextInput: {
						template: '<input class="cdx-text-input-stub" :value="modelValue" @input="$emit( \'update:modelValue\', $event.target.value )" />',
						props: [ 'modelValue', 'placeholder', 'status' ],
						emits: [ 'update:modelValue' ],
						methods: { focus: vi.fn() },
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

	async function switchToNewSchema( wrapper: VueWrapper ): Promise<void> {
		wrapper.findComponent( { name: 'CdxToggleButtonGroup' } )
			.vm.$emit( 'update:modelValue', 'new' );
		await flushPromises();
	}

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
		schemaStore.saveSchema = vi.fn().mockResolvedValue( undefined );

		canCreateSchemas.value = true;
		( useSchemaPermissions as any ).mockReturnValue( {
			canCreateSchemas,
			checkCreatePermission: vi.fn(),
		} );
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

	it( 'hides schema selector after schema selection', async () => {
		const wrapper = mountComponent();

		expect( wrapper.find( '.schema-lookup-stub' ).exists() ).toBe( true );
		expect( wrapper.find( '.cdx-toggle-button-group-stub' ).exists() ).toBe( true );

		await wrapper.findComponent( SchemaLookup ).vm.$emit( 'select', SCHEMA_NAME );
		await flushPromises();

		expect( wrapper.find( '.schema-lookup-stub' ).exists() ).toBe( false );
		expect( wrapper.find( '.cdx-toggle-button-group-stub' ).exists() ).toBe( false );
	} );

	it( 'hides schema creation option when user lacks permission', async () => {
		canCreateSchemas.value = false;
		const wrapper = mountComponent();

		await flushPromises();

		expect( wrapper.find( '.cdx-toggle-button-group-stub' ).exists() ).toBe( false );
		expect( wrapper.find( '.schema-lookup-stub' ).exists() ).toBe( true );
	} );

	it( 'does not show label input or SubjectEditor before schema selection', () => {
		const wrapper = mountComponent();

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

	describe( 'Create new schema flow', () => {
		it( 'shows schema name input and SchemaEditor when "Create new" is selected', async () => {
			const wrapper = mountComponent();

			await switchToNewSchema( wrapper );

			expect( wrapper.find( '.schema-editor-stub' ).exists() ).toBe( true );
			expect( wrapper.findAll( '.cdx-text-input-stub' ).length ).toBe( 1 );
			expect( wrapper.find( '.edit-summary-stub' ).exists() ).toBe( true );
		} );

		it( 'does not show SchemaLookup when "Create new" is selected', async () => {
			const wrapper = mountComponent();

			await switchToNewSchema( wrapper );

			expect( wrapper.find( '.schema-lookup-stub' ).exists() ).toBe( false );
		} );

		it( 'shows error when schema name is empty on create', async () => {
			const wrapper = mountComponent();

			await switchToNewSchema( wrapper );

			await wrapper.findComponent( EditSummary ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( schemaStore.saveSchema ).not.toHaveBeenCalled();
		} );

		it( 'shows error when schema name already exists', async () => {
			const wrapper = mountComponent();

			await switchToNewSchema( wrapper );

			const nameInput = wrapper.find( '.cdx-text-input-stub' );
			await nameInput.setValue( SCHEMA_NAME );
			await flushPromises();

			await wrapper.findComponent( EditSummary ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( schemaStore.saveSchema ).not.toHaveBeenCalled();
			expect( wrapper.find( '.schema-editor-stub' ).exists() ).toBe( true );
		} );

		it( 'hides schema selector after creating a new schema', async () => {
			schemaStore.getOrFetchSchema = vi.fn().mockRejectedValue( new Error( 'Not found' ) );
			const wrapper = mountComponent();

			await switchToNewSchema( wrapper );

			expect( wrapper.find( '.cdx-toggle-button-group-stub' ).exists() ).toBe( true );
			expect( wrapper.find( '.cdx-text-input-stub' ).exists() ).toBe( true );
			expect( wrapper.find( '.schema-editor-stub' ).exists() ).toBe( true );

			const nameInput = wrapper.find( '.cdx-text-input-stub' );
			await nameInput.setValue( NEW_SCHEMA_NAME );
			await flushPromises();

			await wrapper.findComponent( EditSummary ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( wrapper.find( '.cdx-toggle-button-group-stub' ).exists() ).toBe( false );
			expect( wrapper.find( '.schema-editor-stub' ).exists() ).toBe( false );
		} );

		it( 'saves schema and transitions to subject step on success', async () => {
			schemaStore.getOrFetchSchema = vi.fn().mockRejectedValue( new Error( 'Not found' ) );
			const wrapper = mountComponent();

			await switchToNewSchema( wrapper );

			const nameInput = wrapper.find( '.cdx-text-input-stub' );
			await nameInput.setValue( NEW_SCHEMA_NAME );
			await flushPromises();

			await wrapper.findComponent( EditSummary ).vm.$emit( 'save', 'Created schema' );
			await flushPromises();

			expect( schemaStore.saveSchema ).toHaveBeenCalledWith(
				expect.any( Schema ),
				'Created schema',
			);

			const savedSchema = ( schemaStore.saveSchema as ReturnType<typeof vi.fn> ).mock.calls[ 0 ][ 0 ] as Schema;
			expect( savedSchema.getName() ).toBe( NEW_SCHEMA_NAME );

			expect( mw.notify ).toHaveBeenCalledWith(
				expect.any( String ),
				expect.objectContaining( { type: 'success' } ),
			);

			expect( wrapper.find( '.subject-editor-stub' ).exists() ).toBe( true );
			expect( wrapper.find( '.schema-editor-stub' ).exists() ).toBe( false );
		} );

		it( 'defaults label to page title after schema creation', async () => {
			schemaStore.getOrFetchSchema = vi.fn().mockRejectedValue( new Error( 'Not found' ) );
			const wrapper = mountComponent();

			await switchToNewSchema( wrapper );

			const nameInput = wrapper.find( '.cdx-text-input-stub' );
			await nameInput.setValue( NEW_SCHEMA_NAME );
			await flushPromises();

			await wrapper.findComponent( EditSummary ).vm.$emit( 'save', '' );
			await flushPromises();

			const labelInput = wrapper.find( '.cdx-text-input-stub' );
			expect( ( labelInput.element as HTMLInputElement ).value ).toBe( PAGE_TITLE );
		} );

		it( 'stays on schema step when save fails', async () => {
			schemaStore.getOrFetchSchema = vi.fn().mockRejectedValue( new Error( 'Not found' ) );
			schemaStore.saveSchema = vi.fn().mockRejectedValue( new Error( 'Save failed' ) );
			const wrapper = mountComponent();

			await switchToNewSchema( wrapper );

			const nameInput = wrapper.find( '.cdx-text-input-stub' );
			await nameInput.setValue( NEW_SCHEMA_NAME );
			await flushPromises();

			await wrapper.findComponent( EditSummary ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( mw.notify ).toHaveBeenCalledWith(
				'Save failed',
				expect.objectContaining( { type: 'error' } ),
			);

			expect( wrapper.find( '.schema-editor-stub' ).exists() ).toBe( true );
			expect( wrapper.find( '.subject-editor-stub' ).exists() ).toBe( false );
		} );

		it( 'creates subject after schema creation', async () => {
			schemaStore.getOrFetchSchema = vi.fn().mockRejectedValue( new Error( 'Not found' ) );
			const wrapper = mountComponent();

			await switchToNewSchema( wrapper );

			const nameInput = wrapper.find( '.cdx-text-input-stub' );
			await nameInput.setValue( NEW_SCHEMA_NAME );
			await flushPromises();

			await wrapper.findComponent( EditSummary ).vm.$emit( 'save', '' );
			await flushPromises();

			await wrapper.findComponent( EditSummary ).vm.$emit( 'save', 'Created subject' );
			await flushPromises();

			expect( subjectStore.createMainSubject ).toHaveBeenCalledWith(
				PAGE_ID,
				PAGE_TITLE,
				NEW_SCHEMA_NAME,
				expect.any( StatementList ),
			);
		} );

		it( 'resets to schema step when dialog closes', async () => {
			schemaStore.getOrFetchSchema = vi.fn().mockRejectedValue( new Error( 'Not found' ) );
			const wrapper = mountComponent();

			await wrapper.find( '.ext-neowiki-subject-creator-trigger' ).trigger( 'click' );
			await switchToNewSchema( wrapper );

			const nameInput = wrapper.find( '.cdx-text-input-stub' );
			await nameInput.setValue( NEW_SCHEMA_NAME );
			await flushPromises();

			await wrapper.findComponent( EditSummary ).vm.$emit( 'save', '' );
			await flushPromises();

			wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
			await flushPromises();

			await wrapper.find( '.ext-neowiki-subject-creator-trigger' ).trigger( 'click' );
			await flushPromises();

			expect( wrapper.find( '.schema-lookup-stub' ).exists() ).toBe( true );
			expect( wrapper.find( '.subject-editor-stub' ).exists() ).toBe( false );
		} );
	} );
} );
