import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import SchemaLookup from '@/components/SubjectCreator/SchemaLookup.vue';
import SchemaCreator from '@/components/SchemaCreator/SchemaCreator.vue';
import EditSummary from '@/components/common/EditSummary.vue';
import { createPinia, setActivePinia } from 'pinia';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { newSchema } from '@/TestHelpers.ts';
import { CdxDialog } from '@wikimedia/codex';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import SchemaAbandonmentDialog from '@/components/SubjectCreator/SchemaAbandonmentDialog.vue';
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
	emits: [ 'change' ],
	setup() {
		const getSubjectData = (): StatementList => new StatementList( [
			new Statement( new PropertyName( 'Color' ), TextType.typeName, newStringValue( 'Red' ) ),
		] );
		return { getSubjectData };
	},
};

const SchemaCreatorStub = {
	template: '<div class="schema-creator-stub"></div>',
	props: {
		initialSchema: { type: Object, default: undefined },
	},
	emits: [ 'change', 'overflow' ],
	setup() {
		let valid = true;
		const schema = new Schema( NEW_SCHEMA_NAME, 'A description', new PropertyDefinitionList( [] ) );

		const validate = vi.fn( async (): Promise<boolean> => valid );
		const getSchema = vi.fn( (): Schema | null => schema );
		const reset = vi.fn();
		const focus = vi.fn();

		return {
			validate,
			getSchema,
			reset,
			focus,
			setStubValid( v: boolean ) {
				valid = v;
			},
		};
	},
};

const EditSummaryStub = {
	template: '<div class="edit-summary-stub"><button class="save-button" @click="$emit( \'save\', \'\' )">Save</button></div>',
	props: [ 'helpText', 'saveButtonLabel', 'saveDisabled' ],
	emits: [ 'save' ],
};

const CdxDialogStub = {
	template: '<div class="cdx-dialog-stub"><slot name="header" /><slot /><slot name="footer" /></div>',
	props: [ 'open', 'title', 'useCloseButton' ],
	emits: [ 'update:open', 'default' ],
};

const CloseConfirmationDialogStub = {
	template: '<div class="close-confirmation-stub"></div>',
	props: [ 'open' ],
	emits: [ 'discard', 'keep-editing' ],
};

const SchemaAbandonmentDialogStub = {
	template: '<div class="schema-abandonment-stub"></div>',
	props: [ 'open' ],
	emits: [ 'abandon', 'save-schema', 'keep-editing' ],
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
					SchemaCreator: SchemaCreatorStub,
					EditSummary: EditSummaryStub,
					CloseConfirmationDialog: CloseConfirmationDialogStub,
					SchemaAbandonmentDialog: SchemaAbandonmentDialogStub,
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

	async function clickContinue( wrapper: VueWrapper ): Promise<void> {
		await wrapper.find( '.ext-neowiki-subject-creator-continue cdx-button-stub' ).trigger( 'click' );
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
		it( 'shows SchemaCreator when "Create new" is selected', async () => {
			const wrapper = mountComponent();

			await switchToNewSchema( wrapper );

			expect( wrapper.find( '.schema-creator-stub' ).exists() ).toBe( true );
			expect( wrapper.find( '.ext-neowiki-subject-creator-continue' ).exists() ).toBe( true );
		} );

		it( 'does not show SchemaLookup when "Create new" is selected', async () => {
			const wrapper = mountComponent();

			await switchToNewSchema( wrapper );

			expect( wrapper.find( '.schema-lookup-stub' ).exists() ).toBe( false );
		} );

		it( 'focuses SchemaCreator when "Create new" is selected', async () => {
			const wrapper = mountComponent();

			await switchToNewSchema( wrapper );

			const creatorVm = wrapper.findComponent( SchemaCreator ).vm as any;
			expect( creatorVm.focus ).toHaveBeenCalled();
		} );

		it( 'does not save schema when validation fails', async () => {
			const wrapper = mountComponent();

			await switchToNewSchema( wrapper );

			( wrapper.findComponent( SchemaCreator ).vm as any ).setStubValid( false );

			await clickContinue( wrapper );

			expect( schemaStore.saveSchema ).not.toHaveBeenCalled();
		} );

		it( 'hides schema selector after creating a new schema', async () => {
			const wrapper = mountComponent();

			await switchToNewSchema( wrapper );

			expect( wrapper.find( '.cdx-toggle-button-group-stub' ).exists() ).toBe( true );
			expect( wrapper.find( '.schema-creator-stub' ).exists() ).toBe( true );

			await clickContinue( wrapper );

			expect( wrapper.find( '.cdx-toggle-button-group-stub' ).exists() ).toBe( false );
			expect( wrapper.find( '.schema-creator-stub' ).exists() ).toBe( false );
		} );

		it( 'transitions to subject step without saving schema', async () => {
			const wrapper = mountComponent();
			await switchToNewSchema( wrapper );

			await clickContinue( wrapper );

			expect( schemaStore.saveSchema ).not.toHaveBeenCalled();
			expect( wrapper.find( '.subject-editor-stub' ).exists() ).toBe( true );
			expect( wrapper.find( '.schema-creator-stub' ).exists() ).toBe( false );
		} );

		it( 'defaults label to page title after schema creation', async () => {
			const wrapper = mountComponent();

			await switchToNewSchema( wrapper );

			await clickContinue( wrapper );

			const labelInput = wrapper.find( '.cdx-text-input-stub' );
			expect( ( labelInput.element as HTMLInputElement ).value ).toBe( PAGE_TITLE );
		} );

		it( 'saves schema and creates subject on final save', async () => {
			const wrapper = mountComponent();

			await switchToNewSchema( wrapper );

			await clickContinue( wrapper );

			await wrapper.findComponent( EditSummary ).vm.$emit( 'save', 'Created subject' );
			await flushPromises();

			expect( schemaStore.saveSchema ).toHaveBeenCalledWith(
				expect.any( Schema ),
				'Created subject',
			);

			const savedSchema = ( schemaStore.saveSchema as ReturnType<typeof vi.fn> ).mock.calls[ 0 ][ 0 ] as Schema;
			expect( savedSchema.getName() ).toBe( NEW_SCHEMA_NAME );

			expect( subjectStore.createMainSubject ).toHaveBeenCalledWith(
				PAGE_ID,
				PAGE_TITLE,
				NEW_SCHEMA_NAME,
				expect.any( StatementList ),
			);
		} );

		it( 'passes edit summary to saveSchema on final save', async () => {
			const wrapper = mountComponent();

			await switchToNewSchema( wrapper );
			await clickContinue( wrapper );

			await wrapper.findComponent( EditSummary ).vm.$emit( 'save', 'My edit summary' );
			await flushPromises();

			expect( schemaStore.saveSchema ).toHaveBeenCalledWith(
				expect.any( Schema ),
				'My edit summary',
			);
		} );

		it( 'does not pass empty edit summary to saveSchema', async () => {
			const wrapper = mountComponent();

			await switchToNewSchema( wrapper );
			await clickContinue( wrapper );

			await wrapper.findComponent( EditSummary ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( schemaStore.saveSchema ).toHaveBeenCalledWith(
				expect.any( Schema ),
				undefined,
			);
		} );

		it( 'shows error and does not create subject when schema save fails', async () => {
			schemaStore.saveSchema = vi.fn().mockRejectedValue( new Error( 'Schema save failed' ) );
			const wrapper = mountComponent();

			await wrapper.find( '.ext-neowiki-subject-creator-trigger' ).trigger( 'click' );
			await switchToNewSchema( wrapper );

			await clickContinue( wrapper );

			await wrapper.findComponent( EditSummary ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( mw.notify ).toHaveBeenCalledWith(
				'Schema save failed',
				expect.objectContaining( { type: 'error' } ),
			);
			expect( subjectStore.createMainSubject ).not.toHaveBeenCalled();

			const dialog = wrapper.findComponent( CdxDialog );
			expect( dialog.props( 'open' ) ).toBe( true );
		} );

		it( 'resets to schema step when dialog closes', async () => {
			const wrapper = mountComponent();

			await wrapper.find( '.ext-neowiki-subject-creator-trigger' ).trigger( 'click' );
			await switchToNewSchema( wrapper );

			await clickContinue( wrapper );

			wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
			await flushPromises();

			wrapper.findComponent( SchemaAbandonmentDialog ).vm.$emit( 'abandon' );
			await flushPromises();

			await wrapper.find( '.ext-neowiki-subject-creator-trigger' ).trigger( 'click' );
			await flushPromises();

			expect( wrapper.find( '.schema-lookup-stub' ).exists() ).toBe( true );
			expect( wrapper.find( '.subject-editor-stub' ).exists() ).toBe( false );
		} );
	} );

	describe( 'Back button', () => {
		it( 'does not show back button on schema selection step', () => {
			const wrapper = mountComponent();

			expect( wrapper.find( '.ext-neowiki-subject-creator-back-button' ).exists() ).toBe( false );
		} );

		it( 'shows back button after selecting a schema', async () => {
			const wrapper = mountComponent();

			await wrapper.findComponent( SchemaLookup ).vm.$emit( 'select', SCHEMA_NAME );
			await flushPromises();

			expect( wrapper.find( '.ext-neowiki-subject-creator-back-button' ).exists() ).toBe( true );
		} );

		it( 'returns to schema selection when back button is clicked', async () => {
			const wrapper = mountComponent();

			await wrapper.findComponent( SchemaLookup ).vm.$emit( 'select', SCHEMA_NAME );
			await flushPromises();

			expect( wrapper.find( '.subject-editor-stub' ).exists() ).toBe( true );

			await wrapper.find( '.ext-neowiki-subject-creator-back-button' ).trigger( 'click' );
			await flushPromises();

			expect( wrapper.find( '.schema-lookup-stub' ).exists() ).toBe( true );
			expect( wrapper.find( '.subject-editor-stub' ).exists() ).toBe( false );
			expect( wrapper.find( '.ext-neowiki-subject-creator-back-button' ).exists() ).toBe( false );
		} );

		it( 'shows back button after creating a new schema', async () => {
			const wrapper = mountComponent();

			await switchToNewSchema( wrapper );

			await clickContinue( wrapper );

			expect( wrapper.find( '.ext-neowiki-subject-creator-back-button' ).exists() ).toBe( true );
		} );

		it( 'returns to schema editor with draft when clicking back after creating schema', async () => {
			const wrapper = mountComponent();
			await switchToNewSchema( wrapper );

			await clickContinue( wrapper );

			await wrapper.find( '.ext-neowiki-subject-creator-back-button' ).trigger( 'click' );
			await flushPromises();

			expect( wrapper.find( '.schema-creator-stub' ).exists() ).toBe( true );
			expect( wrapper.find( '.subject-editor-stub' ).exists() ).toBe( false );
			expect( wrapper.find( '.schema-lookup-stub' ).exists() ).toBe( false );
		} );

		it( 'passes draft schema to SchemaCreator when going back', async () => {
			const wrapper = mountComponent();
			await switchToNewSchema( wrapper );

			await clickContinue( wrapper );

			await wrapper.find( '.ext-neowiki-subject-creator-back-button' ).trigger( 'click' );
			await flushPromises();

			const creator = wrapper.findComponent( SchemaCreator );
			const initialSchema = creator.props( 'initialSchema' ) as Schema;
			expect( initialSchema ).toBeTruthy();
			expect( initialSchema.getName() ).toBe( NEW_SCHEMA_NAME );
		} );

		it( 'returns to schema selector when clicking back after selecting existing schema', async () => {
			const wrapper = mountComponent();

			await wrapper.findComponent( SchemaLookup ).vm.$emit( 'select', SCHEMA_NAME );
			await flushPromises();

			await wrapper.find( '.ext-neowiki-subject-creator-back-button' ).trigger( 'click' );
			await flushPromises();

			expect( wrapper.find( '.schema-lookup-stub' ).exists() ).toBe( true );
			expect( wrapper.find( '.schema-creator-stub' ).exists() ).toBe( false );
		} );
	} );

	describe( 'Close confirmation', () => {
		it( 'shows confirmation when closing with unsaved changes', async () => {
			const wrapper = mountComponent();

			await wrapper.find( '.ext-neowiki-subject-creator-trigger' ).trigger( 'click' );
			await wrapper.findComponent( SchemaLookup ).vm.$emit( 'select', SCHEMA_NAME );
			await flushPromises();

			const labelInput = wrapper.find( '.cdx-text-input-stub' );
			await labelInput.setValue( 'Something' );
			await labelInput.trigger( 'input' );
			await flushPromises();

			wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
			await flushPromises();

			const dialog = wrapper.findComponent( CdxDialog );
			expect( dialog.props( 'open' ) ).toBe( true );
			expect( wrapper.findComponent( CloseConfirmationDialog ).props( 'open' ) ).toBe( true );
		} );

		it( 'closes without confirmation when there are no unsaved changes', async () => {
			const wrapper = mountComponent();

			await wrapper.find( '.ext-neowiki-subject-creator-trigger' ).trigger( 'click' );

			wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
			await flushPromises();

			const dialog = wrapper.findComponent( CdxDialog );
			expect( dialog.props( 'open' ) ).toBe( false );
		} );

		it( 'closes dialog when discard is clicked in confirmation', async () => {
			const wrapper = mountComponent();

			await wrapper.find( '.ext-neowiki-subject-creator-trigger' ).trigger( 'click' );
			await wrapper.findComponent( SchemaLookup ).vm.$emit( 'select', SCHEMA_NAME );
			await flushPromises();

			const labelInput = wrapper.find( '.cdx-text-input-stub' );
			await labelInput.setValue( 'Something' );
			await labelInput.trigger( 'input' );
			await flushPromises();

			wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
			await flushPromises();

			wrapper.findComponent( CloseConfirmationDialog ).vm.$emit( 'discard' );
			await flushPromises();

			const dialog = wrapper.findComponent( CdxDialog );
			expect( dialog.props( 'open' ) ).toBe( false );
		} );

		it( 'keeps dialog open when keep-editing is clicked in confirmation', async () => {
			const wrapper = mountComponent();

			await wrapper.find( '.ext-neowiki-subject-creator-trigger' ).trigger( 'click' );
			await wrapper.findComponent( SchemaLookup ).vm.$emit( 'select', SCHEMA_NAME );
			await flushPromises();

			const labelInput = wrapper.find( '.cdx-text-input-stub' );
			await labelInput.setValue( 'Something' );
			await labelInput.trigger( 'input' );
			await flushPromises();

			wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
			await flushPromises();

			wrapper.findComponent( CloseConfirmationDialog ).vm.$emit( 'keep-editing' );
			await flushPromises();

			const dialog = wrapper.findComponent( CdxDialog );
			expect( dialog.props( 'open' ) ).toBe( true );
			expect( wrapper.findComponent( CloseConfirmationDialog ).props( 'open' ) ).toBe( false );
		} );
	} );

	describe( 'Close confirmation with draft schema', () => {
		it( 'shows three-option dialog when closing with draft schema on subject step', async () => {
			const wrapper = mountComponent();

			await wrapper.find( '.ext-neowiki-subject-creator-trigger' ).trigger( 'click' );
			await switchToNewSchema( wrapper );

			await clickContinue( wrapper );

			wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
			await flushPromises();

			expect( wrapper.findComponent( SchemaAbandonmentDialog ).props( 'open' ) ).toBe( true );
			expect( wrapper.findComponent( CloseConfirmationDialog ).props( 'open' ) ).toBe( false );
		} );

		it( 'closes without saving on abandon', async () => {
			const wrapper = mountComponent();

			await wrapper.find( '.ext-neowiki-subject-creator-trigger' ).trigger( 'click' );
			await switchToNewSchema( wrapper );

			await clickContinue( wrapper );

			wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
			await flushPromises();

			wrapper.findComponent( SchemaAbandonmentDialog ).vm.$emit( 'abandon' );
			await flushPromises();

			expect( schemaStore.saveSchema ).not.toHaveBeenCalled();
			expect( wrapper.findComponent( CdxDialog ).props( 'open' ) ).toBe( false );
		} );

		it( 'saves schema and closes on save-schema', async () => {
			const wrapper = mountComponent();

			await wrapper.find( '.ext-neowiki-subject-creator-trigger' ).trigger( 'click' );
			await switchToNewSchema( wrapper );

			await clickContinue( wrapper );

			wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
			await flushPromises();

			wrapper.findComponent( SchemaAbandonmentDialog ).vm.$emit( 'save-schema' );
			await flushPromises();

			expect( schemaStore.saveSchema ).toHaveBeenCalledWith(
				expect.any( Schema ),
			);
			expect( wrapper.findComponent( CdxDialog ).props( 'open' ) ).toBe( false );
		} );

		it( 'keeps dialog open on keep-editing', async () => {
			const wrapper = mountComponent();

			await wrapper.find( '.ext-neowiki-subject-creator-trigger' ).trigger( 'click' );
			await switchToNewSchema( wrapper );

			await clickContinue( wrapper );

			wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
			await flushPromises();

			wrapper.findComponent( SchemaAbandonmentDialog ).vm.$emit( 'keep-editing' );
			await flushPromises();

			expect( wrapper.findComponent( CdxDialog ).props( 'open' ) ).toBe( true );
			expect( wrapper.findComponent( SchemaAbandonmentDialog ).props( 'open' ) ).toBe( false );
		} );

		it( 'uses standard close confirmation when closing with existing schema', async () => {
			const wrapper = mountComponent();

			await wrapper.find( '.ext-neowiki-subject-creator-trigger' ).trigger( 'click' );
			await wrapper.findComponent( SchemaLookup ).vm.$emit( 'select', SCHEMA_NAME );
			await flushPromises();

			const labelInput = wrapper.find( '.cdx-text-input-stub' );
			await labelInput.setValue( 'Something' );
			await labelInput.trigger( 'input' );
			await flushPromises();

			wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
			await flushPromises();

			expect( wrapper.findComponent( CloseConfirmationDialog ).props( 'open' ) ).toBe( true );
		} );

		it( 'uses standard close confirmation on schema editor step without draft', async () => {
			const wrapper = mountComponent();

			await wrapper.find( '.ext-neowiki-subject-creator-trigger' ).trigger( 'click' );
			await switchToNewSchema( wrapper );

			wrapper.findComponent( SchemaCreator ).vm.$emit( 'change' );
			await flushPromises();

			wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
			await flushPromises();

			expect( wrapper.findComponent( CloseConfirmationDialog ).props( 'open' ) ).toBe( true );
		} );

		it( 'shows three-option dialog on schema editor step when draft exists', async () => {
			const wrapper = mountComponent();

			await wrapper.find( '.ext-neowiki-subject-creator-trigger' ).trigger( 'click' );
			await switchToNewSchema( wrapper );

			await clickContinue( wrapper );

			await wrapper.find( '.ext-neowiki-subject-creator-back-button' ).trigger( 'click' );
			await flushPromises();

			wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
			await flushPromises();

			expect( wrapper.findComponent( SchemaAbandonmentDialog ).props( 'open' ) ).toBe( true );
			expect( wrapper.findComponent( CloseConfirmationDialog ).props( 'open' ) ).toBe( false );
		} );

		it( 'shows error and keeps dialog open when save-schema fails', async () => {
			schemaStore.saveSchema = vi.fn().mockRejectedValue( new Error( 'Save failed' ) );
			const wrapper = mountComponent();

			await wrapper.find( '.ext-neowiki-subject-creator-trigger' ).trigger( 'click' );
			await switchToNewSchema( wrapper );

			await clickContinue( wrapper );

			wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
			await flushPromises();

			wrapper.findComponent( SchemaAbandonmentDialog ).vm.$emit( 'save-schema' );
			await flushPromises();

			expect( mw.notify ).toHaveBeenCalledWith(
				'Save failed',
				expect.objectContaining( { type: 'error' } ),
			);
			expect( wrapper.findComponent( CdxDialog ).props( 'open' ) ).toBe( true );
		} );
	} );
} );
