import { mount, VueWrapper, DOMWrapper, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ref, nextTick } from 'vue';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import SchemaPicker from '@/components/common/SchemaPicker.vue';
import PagePicker from '@/components/common/PagePicker.vue';
import SchemaCreator from '@/components/SchemaCreator/SchemaCreator.vue';
import { createPinia, setActivePinia } from 'pinia';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { createI18nMock, openDialogTitles, setupMwMock } from '../../VueTestHelpers.ts';
import { newSchema, newSubject } from '@/TestHelpers.ts';
import { PageSubjects } from '@/domain/PageSubjects.ts';
import type { SubjectRepository } from '@/domain/SubjectRepository.ts';
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
import { PageTitleTakenError } from '@/persistence/PageTitleTakenError';
import { SubjectIdInUseError } from '@/persistence/SubjectIdInUseError';
import { InvalidPageTitleError } from '@/persistence/InvalidPageTitleError';
import type { SaveBlocker } from '@/components/common/SaveBlocker.ts';
import SubjectEditorDialog from '@/components/SubjectEditor/SubjectEditorDialog.vue';
import { Subject } from '@/domain/Subject.ts';
import type { InitialPage } from '@/components/SubjectCreator/InitialPage.ts';

const PAGE_ID = 123;
const PAGE_TITLE = 'Test Page';
// wgPageName carries the prefixed title with underscores, which is what the placeholder reads.
const PAGE_NAME = 'Test_Page';
const SCHEMA_NAME = 'TestSchema';
const NEW_SCHEMA_NAME = 'NewSchema';
// The id minted for the Subject being created, which the panes and the tree name it by.
const MINTED_ID = 'smintedAAAAAAA1';
const CREATED_PAGE_ID = 777;
const CREATED_PAGE_SUBJECT_ID = 's11111111111113';
// What the editor writes with when the user gave no summary of their own.
const DEFAULT_CREATE_SUMMARY = 'neowiki-subject-editor-summary-default-create';

vi.mock( '@/composables/useSchemaPermissions.ts' );

const canCreateSubjectPage = ref( true );
const checkCreateSubjectPagePermission = vi.fn();

vi.mock( '@/composables/useSubjectPermissions.ts', () => ( {
	useSubjectPermissions: () => ( {
		canCreateSubjectPage,
		checkCreateSubjectPagePermission,
	} ),
} ) );

interface Deferred<T> {
	promise: Promise<T>;
	resolve: ( value: T ) => void;
	reject: ( error: unknown ) => void;
}

const SchemaPickerStub = {
	template: '<div class="schema-lookup-stub"></div>',
	emits: [ 'select' ],
	setup() {
		return { focus: vi.fn() };
	},
};

// What the panes inside the stubbed editor dialog hold, which is what its save hands back. Reset
// per test by the beforeEach below.
let editedLabel: string | null = null;
let editedStatements = (): StatementList => new StatementList( [
	new Statement( new PropertyName( 'Color' ), TextType.typeName, newStringValue( 'Red' ) ),
] );
// Subjects the editing session invented alongside the one being created, which the editor writes
// as creations of their own. Each carries the page its pane was opened against.
let sessionDrafts: { subject: Subject; pageId: number }[] = [];
// What the last save through the stub threw, so a test can assert the save stopped there.
let lastSaveError: unknown = null;
// Stands in for the round trip the real write loop awaits before its first write — it flushes every
// dirty pane's validation. A test holds it open to get at the window in which the footer is still
// answerable while the save is under way.
let beforeFirstWrite: Promise<unknown> = Promise.resolve();

// The reason the stubbed creator reports for holding the schema back. Reset per test
// by the beforeEach below.
let schemaCreatorSaveBlocker: SaveBlocker | null = null;

const SchemaCreatorStub = {
	template: '<div class="schema-creator-stub"></div>',
	props: {
		initialSchema: { type: Object, default: undefined },
	},
	emits: [ 'change' ],
	setup() {
		let valid = true;
		const schema = new Schema( NEW_SCHEMA_NAME, 'A description', new PropertyDefinitionList( [] ) );

		const validate = vi.fn( async (): Promise<boolean> => valid );
		const getSchema = vi.fn( (): Schema | null => schema );
		const saveBlocker = (): SaveBlocker | null => schemaCreatorSaveBlocker;
		const reset = vi.fn();
		const focus = vi.fn();

		return {
			validate,
			getSchema,
			saveBlocker,
			reset,
			focus,
			setStubValid( v: boolean ) {
				valid = v;
			},
		};
	},
};

const SummaryActionStub = {
	name: 'SummaryAction',
	template: '<div class="edit-summary-stub"><button class="save-button" @click="$emit( \'save\', \'\' )">Save</button></div>',
	props: [ 'helpText', 'footerText', 'saveButtonLabel', 'saveDisabled' ],
	emits: [ 'save' ],
};

/**
 * Stands in for the editor dialog the creator opens on the Subject being created. It renders the
 * question the creator puts in its footer, and its save runs what the real write loop runs: the
 * Subject being created first, since its own write is what creates the page the rest are stored
 * on, then every Subject the session invented alongside it, then the report that the save is
 * through. A write that throws stops the loop, as it does there.
 */
const SubjectEditorDialogStub = {
	name: 'SubjectEditorDialog',
	components: { SummaryAction: SummaryActionStub },
	template: '<div class="subject-editor-dialog-stub">' +
		'<slot name="before-actions" :saving="saving" />' +
		'<SummaryAction :save-disabled="saveDisabled" @save="runSave" />' +
		'<button class="stub-close" @click="$emit( \'update:open\', false )">Close</button>' +
		'</div>',
	props: [ 'open', 'subject', 'schema', 'rootIsNew', 'saveDisabled', 'hostHasUnsavedChanges', 'onSave', 'onCreate', 'onSaveSchema', 'onSaved' ],
	emits: [ 'update:open' ],
	setup( props: Record<string, any> ) {
		const saving = ref( false );

		async function runSave( summary: string ): Promise<void> {
			// The real footer's button is disabled rather than ignored, which comes to the same
			// thing: nothing is written while the host still has a question outstanding.
			if ( props.saveDisabled ) {
				return;
			}

			const comment = summary || DEFAULT_CREATE_SUMMARY;
			lastSaveError = null;
			saving.value = true;

			try {
				await beforeFirstWrite;

				await props.onCreate( editedRoot( props.subject as Subject ), 0, comment );

				for ( const draft of sessionDrafts ) {
					await props.onCreate( draft.subject, draft.pageId, comment );
				}
			} catch ( error ) {
				lastSaveError = error;
				return;
			} finally {
				saving.value = false;
			}

			props.onSaved();
		}

		return { saving, runSave };
	},
};

// The Subject being created as its pane holds it: the one the creator handed down, under whatever
// has been typed into the pane since.
function editedRoot( subject: Subject ): Subject {
	return subject.withLabel( editedLabel ).withStatements( editedStatements() );
}

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

const CdxRadioStub = {
	name: 'CdxRadio',
	template: '<label class="cdx-radio-stub" :data-value="inputValue">' +
		'<input type="radio" :checked="modelValue === inputValue" :disabled="disabled"' +
		' @change="$emit( \'update:modelValue\', inputValue )">' +
		'<slot /></label>',
	props: [ 'modelValue', 'inputValue', 'name', 'inline', 'disabled' ],
	emits: [ 'update:modelValue' ],
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
	const getSchemaMock = vi.fn();
	let getPageSubjectsMock: ReturnType<typeof vi.fn>;
	let mintSubjectIdMock: ReturnType<typeof vi.fn>;
	let repositorySpy: ReturnType<typeof vi.spyOn>;

	const mountComponent = (
		stubs: Record<string, any> = {},
		props: Record<string, any> = {},
		options: Record<string, any> = {},
	): VueWrapper => (
		mount( SubjectCreatorDialog, {
			props: { open: false, hostPage: { hasMainSubject: false }, ...props },
			...options,
			global: {
				plugins: [ pinia ],
				stubs: {
					SchemaPicker: SchemaPickerStub,
					SubjectEditorDialog: SubjectEditorDialogStub,
					SchemaCreator: SchemaCreatorStub,
					SummaryAction: SummaryActionStub,
					CloseConfirmationDialog: CloseConfirmationDialogStub,
					SchemaAbandonmentDialog: SchemaAbandonmentDialogStub,
					CdxButton: true,
					CdxDialog: CdxDialogStub,
					CdxToggleButtonGroup: CdxToggleButtonGroupStub,
					CdxRadio: CdxRadioStub,
					CdxMessage: true,
					CdxField: {
						template: '<div class="cdx-field-stub"><slot /><slot name="label" /><slot name="messages" /></div>',
					},
					CdxTextInput: {
						template: '<input class="cdx-text-input-stub" :value="modelValue" :placeholder="placeholder" @input="$emit( \'update:modelValue\', $event.target.value )" />',
						props: [ 'modelValue', 'placeholder', 'status' ],
						emits: [ 'update:modelValue' ],
						// Codex exposes focus(), which focuses the input; here that is the root.
						methods: {
							focus(): void {
								( this.$el as HTMLInputElement ).focus();
							},
						},
					},
					teleport: true,
					...stubs,
				},
				provide: {
					[ Service.ComponentRegistry ]: NeoWikiExtension.getInstance().getTypeSpecificComponentRegistry(),
					[ Service.PropertyTypeRegistry ]: NeoWikiExtension.getInstance().getPropertyTypeRegistry(),
					[ Service.SchemaRepository ]: { getSchema: getSchemaMock },
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

	/** Closes whichever step is showing, the way its own close button does. */
	async function requestClose( wrapper: VueWrapper ): Promise<void> {
		const editor = wrapper.findComponent( SubjectEditorDialog );

		if ( editor.exists() ) {
			editor.vm.$emit( 'update:open', false );
		} else {
			wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
		}

		await flushPromises();
	}

	async function clickContinue( wrapper: VueWrapper ): Promise<void> {
		await wrapper.find( '.ext-neowiki-subject-creator-continue cdx-button-stub' ).trigger( 'click' );
		await flushPromises();
	}

	let reloadMock: ReturnType<typeof vi.fn>;

	beforeEach( () => {
		editedLabel = null;
		editedStatements = (): StatementList => new StatementList( [
			new Statement( new PropertyName( 'Color' ), TextType.typeName, newStringValue( 'Red' ) ),
		] );
		sessionDrafts = [];
		lastSaveError = null;
		beforeFirstWrite = Promise.resolve();
		schemaCreatorSaveBlocker = null;
		reloadMock = vi.fn();
		vi.stubGlobal( 'location', { href: '', reload: reloadMock } );

		setupMwMock( {
			functions: [ 'msg', 'notify', 'config', 'storage', 'util' ],
			config: {
				wgArticleId: PAGE_ID,
				wgTitle: PAGE_TITLE,
				wgPageName: PAGE_NAME,
				// Debounce 0 is blur-only mode: the dry-run fires on blur / pre-save
				// (via flush()), which runs synchronously in tests.
				wgNeoWikiValidationDebounceMs: 0,
			},
		} );

		pinia = createPinia();
		setActivePinia( pinia );

		subjectStore = useSubjectStore();
		subjectStore.createMainSubject = vi.fn().mockResolvedValue( new SubjectId( 's11111111111111' ) );
		subjectStore.createOtherSubject = vi.fn().mockResolvedValue( new SubjectId( 's11111111111112' ) );
		// The dry-run validation runs alongside the live validators; stub it so
		// it does not reach the network and stays out of the way of these tests.
		subjectStore.validateSubject = vi.fn().mockResolvedValue( [] );

		schemaStore = useSchemaStore();
		schemaStore.saveSchema = vi.fn().mockResolvedValue( undefined );

		// The dialog reads the picked schema through the injected repository, not the store.
		getSchemaMock.mockReset().mockResolvedValue( newSchema( { title: SCHEMA_NAME } ) );

		// Picking a Schema mints the id the Subject being created is held under, so every path
		// through the dialog reaches these.
		mintSubjectIdMock = vi.fn().mockResolvedValue( new SubjectId( MINTED_ID ) );
		getPageSubjectsMock = vi.fn().mockResolvedValue( {
			pageSubjects: new PageSubjects( PAGE_ID, null, [] ),
			referencedSubjects: [],
			schemas: [],
		} );
		repositorySpy = vi.spyOn( NeoWikiExtension.getInstance(), 'getSubjectRepository' ).mockReturnValue(
			{ getPageSubjects: getPageSubjectsMock, mintSubjectId: mintSubjectIdMock } as unknown as SubjectRepository,
		);

		canCreateSubjectPage.value = true;
		canCreateSchemas.value = true;
		( useSchemaPermissions as any ).mockReturnValue( {
			canCreateSchemas,
			checkCreatePermission: vi.fn(),
		} );
	} );

	afterEach( () => {
		repositorySpy.mockRestore();
		vi.unstubAllGlobals();
	} );

	it( 'renders the dialog closed by default', () => {
		const wrapper = mountComponent();
		expect( wrapper.findComponent( CdxDialog ).props( 'open' ) ).toBe( false );
	} );

	it( 'renders the dialog open when the open prop is set', async () => {
		const wrapper = mountComponent();
		await wrapper.setProps( { open: true } );
		await flushPromises();
		expect( wrapper.findComponent( CdxDialog ).props( 'open' ) ).toBe( true );
	} );

	it( 'asks to be closed when the dialog is closed without changes', async () => {
		const wrapper = mountComponent( {}, { open: true } );
		await flushPromises();

		wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
		await flushPromises();

		expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
	} );

	it( 'shows schema search in the existing-schema block', () => {
		const wrapper = mountComponent();

		expect( wrapper.find( '.schema-lookup-stub' ).exists() ).toBe( true );
	} );

	it( 'hides schema selector after schema selection', async () => {
		const wrapper = mountComponent();

		expect( wrapper.find( '.schema-lookup-stub' ).exists() ).toBe( true );
		expect( wrapper.find( '.cdx-toggle-button-group-stub' ).exists() ).toBe( true );

		await wrapper.findComponent( SchemaPicker ).vm.$emit( 'select', SCHEMA_NAME );
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

		expect( wrapper.find( '.subject-editor-dialog-stub' ).exists() ).toBe( false );
		expect( wrapper.find( '.edit-summary-stub' ).exists() ).toBe( false );
	} );

	it( 'loads the picked schema through the repository', async () => {
		const picked = newSchema( { title: SCHEMA_NAME, description: 'from the repository' } );
		getSchemaMock.mockResolvedValue( picked );
		const wrapper = mountComponent();

		await wrapper.findComponent( SchemaPicker ).vm.$emit( 'select', SCHEMA_NAME );
		await flushPromises();

		expect( getSchemaMock ).toHaveBeenCalledWith( SCHEMA_NAME );
		expect( wrapper.findComponent( SubjectEditorDialog ).props( 'schema' ) ).toStrictEqual( picked );
	} );

	it( 'opens the editor on a Subject the wiki does not hold yet after schema selection', async () => {
		const wrapper = mountComponent();

		await wrapper.findComponent( SchemaPicker ).vm.$emit( 'select', SCHEMA_NAME );
		await flushPromises();

		const editor = wrapper.findComponent( SubjectEditorDialog );

		expect( editor.exists() ).toBe( true );
		expect( editor.props( 'rootIsNew' ) ).toBe( true );
		expect( ( editor.props( 'subject' ) as Subject ).getSchemaName() ).toBe( SCHEMA_NAME );
		expect( ( editor.props( 'subject' ) as Subject ).getId().text ).toBe( MINTED_ID );
	} );

	// Nobody has named it yet, so it is shown under its Schema name and marked as a stand-in —
	// what the server derives for a label-less Subject (ADR 31).
	it( 'hands the editor a Subject shown under its schema name, marked as a stand-in', async () => {
		const wrapper = mountComponent();

		await wrapper.findComponent( SchemaPicker ).vm.$emit( 'select', SCHEMA_NAME );
		await flushPromises();

		const subject = wrapper.findComponent( SubjectEditorDialog ).props( 'subject' ) as Subject;

		expect( subject.getLabel() ).toBeNull();
		expect( subject.getDisplayName() ).toBe( SCHEMA_NAME );
		expect( subject.hasGeneratedDisplayName() ).toBe( true );
	} );

	// A further Subject on the page is not the Main Subject, so the server will name it after its Schema -
	// a name nobody chose, and the preview says so.
	it( 'leaves save reachable once a schema is picked, even with the label untouched', async () => {
		const wrapper = mountComponent();

		expect( wrapper.find( '.edit-summary-stub' ).exists() ).toBe( false );

		await wrapper.findComponent( SchemaPicker ).vm.$emit( 'select', SCHEMA_NAME );
		await flushPromises();

		expect( wrapper.findComponent( { name: 'SummaryAction' } ).props( 'saveDisabled' ) ).toBe( false );
	} );

	it( 'keeps continue disabled until the new schema has been given something', async () => {
		const wrapper = mountComponent();

		await switchToNewSchema( wrapper );

		const continueButton = wrapper.find( '.ext-neowiki-subject-creator-continue cdx-button-stub' );
		expect( continueButton.attributes( 'disabled' ) ).toBe( 'true' );

		wrapper.findComponent( SchemaCreator ).vm.$emit( 'change' );
		await flushPromises();

		expect(
			wrapper.find( '.ext-neowiki-subject-creator-continue cdx-button-stub' ).attributes( 'disabled' ),
		).toBe( 'false' );
	} );

	it( 'calls createMainSubject on save with correct arguments', async () => {
		const wrapper = mountComponent();

		await wrapper.findComponent( SchemaPicker ).vm.$emit( 'select', SCHEMA_NAME );
		await flushPromises();

		editedLabel = 'Typed label';

		await wrapper.findComponent( { name: 'SummaryAction' } ).vm.$emit( 'save', 'test summary' );
		await flushPromises();

		expect( subjectStore.createMainSubject ).toHaveBeenCalledWith(
			PAGE_ID,
			'Typed label',
			SCHEMA_NAME,
			expect.any( StatementList ),
			'test summary',
		);
	} );

	it( 'sends no label when the field was left empty', async () => {
		const wrapper = mountComponent();

		await wrapper.findComponent( SchemaPicker ).vm.$emit( 'select', SCHEMA_NAME );
		await flushPromises();

		await wrapper.findComponent( { name: 'SummaryAction' } ).vm.$emit( 'save', 'test summary' );
		await flushPromises();

		expect( subjectStore.createMainSubject ).toHaveBeenCalledWith(
			PAGE_ID,
			null,
			SCHEMA_NAME,
			expect.any( StatementList ),
			'test summary',
		);
	} );

	it( 'does not pass summary when it is empty', async () => {
		const wrapper = mountComponent();

		await wrapper.findComponent( SchemaPicker ).vm.$emit( 'select', SCHEMA_NAME );
		await flushPromises();

		await wrapper.findComponent( { name: 'SummaryAction' } ).vm.$emit( 'save', '' );
		await flushPromises();

		expect( subjectStore.createMainSubject ).toHaveBeenCalledWith(
			PAGE_ID,
			null,
			SCHEMA_NAME,
			expect.any( StatementList ),
			DEFAULT_CREATE_SUMMARY,
		);
	} );

	it( 'calls createOtherSubject when the page already has a main subject', async () => {
		const wrapper = mountComponent( {}, { hostPage: { hasMainSubject: true } } );

		await wrapper.findComponent( SchemaPicker ).vm.$emit( 'select', SCHEMA_NAME );
		await flushPromises();

		editedLabel = 'Typed label';

		await wrapper.findComponent( { name: 'SummaryAction' } ).vm.$emit( 'save', 'test summary' );
		await flushPromises();

		expect( subjectStore.createOtherSubject ).toHaveBeenCalledWith(
			PAGE_ID,
			'Typed label',
			SCHEMA_NAME,
			expect.any( StatementList ),
			'test summary',
			new SubjectId( MINTED_ID ),
		);
		expect( subjectStore.createMainSubject ).not.toHaveBeenCalled();
	} );

	it( 'reloads page after successful save', async () => {
		const wrapper = mountComponent();

		await wrapper.setProps( { open: true } );
		await flushPromises();
		await wrapper.findComponent( SchemaPicker ).vm.$emit( 'select', SCHEMA_NAME );
		await flushPromises();

		await wrapper.findComponent( { name: 'SummaryAction' } ).vm.$emit( 'save', '' );
		await flushPromises();

		expect( mw.storage.session.set ).toHaveBeenCalledWith( 'neowiki-subject-creator-success', '1' );
		expect( reloadMock ).toHaveBeenCalled();
	} );

	it( 'lets a refused write reach the editor, and stays open', async () => {
		subjectStore.createMainSubject = vi.fn().mockRejectedValue( new Error( 'Server error' ) );

		const wrapper = mountComponent();

		await wrapper.setProps( { open: true } );
		await flushPromises();
		await wrapper.findComponent( SchemaPicker ).vm.$emit( 'select', SCHEMA_NAME );
		await flushPromises();

		await wrapper.findComponent( { name: 'SummaryAction' } ).vm.$emit( 'save', '' );
		await flushPromises();

		expect( ( lastSaveError as Error ).message ).toBe( 'Server error' );
		expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
	} );

	describe( 'with an initial schema', () => {
		it( 'opens on the second step with the initial schema loaded', async () => {
			const wrapper = mountComponent( {}, { initialSchemaName: SCHEMA_NAME } );

			await wrapper.setProps( { open: true } );
			await flushPromises();

			expect( getSchemaMock ).toHaveBeenCalledWith( SCHEMA_NAME );
			expect( wrapper.findComponent( SchemaPicker ).exists() ).toBe( false );
			expect( wrapper.findComponent( SubjectEditorDialog ).exists() ).toBe( true );
		} );

		it( 'falls back to the picker when the initial schema cannot be loaded', async () => {
			getSchemaMock.mockRejectedValue( new Error( 'No such schema' ) );
			const wrapper = mountComponent( {}, { initialSchemaName: 'Missing' } );

			await wrapper.setProps( { open: true } );
			await flushPromises();

			expect( wrapper.findComponent( SchemaPicker ).exists() ).toBe( true );
			expect( wrapper.findComponent( SubjectEditorDialog ).exists() ).toBe( false );
		} );

		it( 'stays on the pinned schema when reopened before the first open\'s schema arrived', async () => {
			let resolveFirst!: ( schema: Schema ) => void;
			let resolveSecond!: ( schema: Schema ) => void;
			getSchemaMock
				.mockReturnValueOnce( new Promise<Schema>( ( resolve ) => {
					resolveFirst = resolve;
				} ) )
				.mockReturnValueOnce( new Promise<Schema>( ( resolve ) => {
					resolveSecond = resolve;
				} ) );

			const wrapper = mountComponent( {}, { initialSchemaName: SCHEMA_NAME } );

			await wrapper.setProps( { open: true } );
			await nextTick();
			await wrapper.setProps( { open: false } );
			await nextTick();
			await wrapper.setProps( { open: true } );
			await nextTick();

			resolveFirst( newSchema( { title: SCHEMA_NAME } ) );
			await flushPromises();
			resolveSecond( newSchema( { title: SCHEMA_NAME } ) );
			await flushPromises();

			expect( wrapper.findComponent( SchemaPicker ).exists() ).toBe( false );
			expect( wrapper.findComponent( SubjectEditorDialog ).exists() ).toBe( true );
		} );

		it( 'leaves save reachable at once: the Subject is what the save exists to write', async () => {
			const wrapper = mountComponent( {}, { initialSchemaName: SCHEMA_NAME } );

			await wrapper.setProps( { open: true } );
			await flushPromises();

			expect( wrapper.findComponent( { name: 'SummaryAction' } ).props( 'saveDisabled' ) ).toBe( false );
		} );
	} );

	describe( 'the page choice', () => {
		const EXISTING_PAGE_ID = 12;
		const OTHER_PAGE_ID = 13;
		const MAIN_ID = 's11111111111taa';
		// Renders the field's error and its help text so the page-choice failures below are
		// visible as text.
		const CdxFieldWithMessagesStub = {
			template: '<div class="cdx-field-stub"><slot name="label" /><slot />' +
				'<span>{{ messages?.error }}</span><slot name="help-text" /></div>',
			props: [ 'status', 'messages', 'optional', 'isFieldset' ],
		};

		const I18nSlotStub = {
			template: '<span>{{ messageKey }}<slot /></span>',
			props: [ 'messageKey' ],
		};

		// Carries an input of its own, so that the picker being focused can be told from any
		// other field being focused.
		const PagePickerStub = {
			name: 'PagePicker',
			template: '<div class="page-picker-stub"><input ref="fieldRef" /></div>',
			props: [ 'excludedPageId', 'existingPagesOnly', 'disabled', 'ariaLabel' ],
			emits: [ 'update:selected' ],
			setup() {
				const fieldRef = ref<HTMLInputElement | null>( null );

				return { fieldRef, focus: (): void => fieldRef.value?.focus() };
			},
		};

		function mountDialog(
			props: Record<string, any> = {},
			options: Record<string, any> = {},
		): VueWrapper {
			return mountComponent(
				{ PagePicker: PagePickerStub, CdxField: CdxFieldWithMessagesStub, I18nSlot: I18nSlotStub },
				props,
				options,
			);
		}

		/** The dialog as Special:CreateSubject and a Schema page mount it: with no page of its own. */
		function mountWithoutHostPage( props: Record<string, any> = {} ): VueWrapper {
			return mountDialog( { hostPage: null, ...props } );
		}

		async function pickSchema( wrapper: VueWrapper ): Promise<void> {
			await wrapper.setProps( { open: true } );
			await flushPromises();
			await wrapper.findComponent( SchemaPicker ).vm.$emit( 'select', SCHEMA_NAME );
			await flushPromises();
		}

		function offeredChoices( wrapper: VueWrapper ): ( string | undefined )[] {
			return wrapper.findAll( '.cdx-radio-stub' ).map( ( radio ) => radio.attributes( 'data-value' ) );
		}

		function offeredLabels( wrapper: VueWrapper ): string[] {
			return wrapper.findAll( '.cdx-radio-stub' ).map( ( radio ) => radio.text() );
		}

		function chosenOption( wrapper: VueWrapper ): string | undefined {
			return wrapper.findAll( '.cdx-radio-stub' )
				.find( ( radio ) => ( radio.find( 'input' ).element as HTMLInputElement ).checked )
				?.attributes( 'data-value' );
		}

		async function choose( wrapper: VueWrapper, value: string ): Promise<void> {
			await wrapper.find( `.cdx-radio-stub[data-value="${ value }"] input` ).trigger( 'change' );
			await flushPromises();
		}

		async function pickPage( wrapper: VueWrapper, choice: unknown ): Promise<void> {
			wrapper.findComponent( PagePicker ).vm.$emit( 'update:selected', choice );
			await flushPromises();
		}

		async function typeLabel( _wrapper: VueWrapper, label: string ): Promise<void> {
			editedLabel = label;
			await flushPromises();
		}

		function sectionIsOpen( wrapper: VueWrapper ): boolean {
			return wrapper.find( '.ext-neowiki-subject-creator-page-section' )
				.attributes( 'open' ) !== undefined;
		}

		function sectionHeader( wrapper: VueWrapper ): string {
			return wrapper.find( '.ext-neowiki-subject-creator-page-section summary' ).text();
		}

		function shownChoice( wrapper: VueWrapper ): string {
			return wrapper.find( '.ext-neowiki-subject-creator-page-section__choice' ).text();
		}

		/** Opened the way a user opens it: the browser toggles the element and reports it. */
		async function openSection( wrapper: VueWrapper ): Promise<void> {
			const section = wrapper.find( '.ext-neowiki-subject-creator-page-section' );
			( section.element as HTMLDetailsElement ).open = true;
			await section.trigger( 'toggle' );
			await flushPromises();
		}

		function pageTitleField( wrapper: VueWrapper ): DOMWrapper<Element> {
			return wrapper.find( '.ext-neowiki-subject-creator-page-title-field' );
		}

		function pageTitleInput( wrapper: VueWrapper ): DOMWrapper<Element> {
			return wrapper.find( '.ext-neowiki-subject-creator-page-title-field .cdx-text-input-stub' );
		}

		async function typePageTitle( wrapper: VueWrapper, title: string ): Promise<void> {
			await pageTitleInput( wrapper ).setValue( title );
			await flushPromises();
		}

		async function save( wrapper: VueWrapper, summary = '' ): Promise<void> {
			await wrapper.findComponent( { name: 'SummaryAction' } ).vm.$emit( 'save', summary );
			await flushPromises();
		}

		function deferred<T>(): Deferred<T> {
			let resolve!: ( value: T ) => void;
			let reject!: ( error: unknown ) => void;
			const promise = new Promise<T>( ( resolvePromise, rejectPromise ) => {
				resolve = resolvePromise;
				reject = rejectPromise;
			} );

			return { promise, resolve, reject };
		}

		function pageWithMainSubject( name: string ): unknown {
			return {
				pageSubjects: new PageSubjects( EXISTING_PAGE_ID, new SubjectId( MAIN_ID ), [
					newSubject( { id: MAIN_ID, label: name } ),
				] ),
				referencedSubjects: [],
				schemas: [],
			};
		}

		async function pickPageWithMainSubject( wrapper: VueWrapper ): Promise<void> {
			getPageSubjectsMock.mockResolvedValue( pageWithMainSubject( 'ACME Inc' ) );
			await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );
		}

		beforeEach( () => {
			subjectStore.createSubjectPage = vi.fn().mockResolvedValue( {
				subjectId: new SubjectId( CREATED_PAGE_SUBJECT_ID ),
				pageTitle: 'New Person',
				pageId: CREATED_PAGE_ID,
			} );

			getPageSubjectsMock.mockResolvedValue( {
				pageSubjects: new PageSubjects( EXISTING_PAGE_ID, null, [] ),
				referencedSubjects: [],
				schemas: [],
			} );
		} );

		describe( 'opened on a page', () => {
			it( 'offers that page, another page, and a new one', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );

				expect( offeredChoices( wrapper ) ).toEqual( [ 'thisPage', 'anotherPage', 'newPage' ] );
			} );

			it( 'names the page picked another one, the page being opened on being the first', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );

				expect( offeredLabels( wrapper ) ).toEqual( [
					'neowiki-subject-creator-page-this',
					'neowiki-subject-creator-page-another',
					'neowiki-subject-creator-page-new',
				] );
			} );

			it( 'starts on that page', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );

				expect( chosenOption( wrapper ) ).toBe( 'thisPage' );
			} );

			it( 'shows no page picker until another page is chosen', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );

				expect( wrapper.findComponent( PagePicker ).exists() ).toBe( false );

				await choose( wrapper, 'anotherPage' );

				expect( wrapper.findComponent( PagePicker ).exists() ).toBe( true );
			} );

			it( 'saves onto that page and reloads it', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );

				await save( wrapper, 'why' );

				expect( subjectStore.createMainSubject ).toHaveBeenCalledWith(
					PAGE_ID, null, SCHEMA_NAME, expect.any( StatementList ), 'why',
				);
				expect( reloadMock ).toHaveBeenCalled();
			} );
		} );

		describe( 'opened without a page', () => {
			it( 'offers a new page first, then an existing one', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );

				expect( offeredChoices( wrapper ) ).toEqual( [ 'newPage', 'anotherPage' ] );
			} );

			it( 'names the page picked an existing one: there is no page to call it another of', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );

				expect( offeredLabels( wrapper ) ).toEqual( [
					'neowiki-subject-creator-page-new',
					'neowiki-subject-creator-page-existing',
				] );
			} );

			it( 'starts on a new page', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );

				expect( chosenOption( wrapper ) ).toBe( 'newPage' );
			} );

			it( 'requests no edit notices: there is no page whose notices would apply', async () => {
				const getNotices = vi.fn().mockResolvedValue( [] );
				const noticeRepositorySpy = vi.spyOn( NeoWikiExtension.getInstance(), 'getEditNoticeRepository' )
					.mockReturnValue( { getNotices } as never );

				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );

				expect( getNotices ).not.toHaveBeenCalled();

				noticeRepositorySpy.mockRestore();
			} );

			/** The page it goes to shows the subject just created, so no notice is left to restate it. */
			it( 'goes to the subject\'s own page, not to the new page created for it', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await typeLabel( wrapper, 'New Person' );

				await save( wrapper );

				expect( location.href ).toBe( '/wiki/Special:Subject/s11111111111113' );
				expect( mw.storage.session.set )
					.not.toHaveBeenCalledWith( 'neowiki-subject-creator-success', '1' );
				expect( reloadMock ).not.toHaveBeenCalled();
			} );

			it( 'goes to the subject\'s own page, not to the existing page it was saved onto', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await choose( wrapper, 'anotherPage' );
				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				await save( wrapper );

				expect( location.href ).toBe( '/wiki/Special:Subject/s11111111111111' );
				expect( mw.storage.session.set )
					.not.toHaveBeenCalledWith( 'neowiki-subject-creator-success', '1' );
			} );

			it( 'goes to the subject\'s own page where it joined a page that has a main subject', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await choose( wrapper, 'anotherPage' );
				await pickPageWithMainSubject( wrapper );

				await save( wrapper );

				expect( location.href ).toBe( '/wiki/Special:Subject/s11111111111112' );
			} );
		} );

		/**
		 * Which options there are is a server answer, so offering some before it lands would change
		 * the choice, and drop the page picked with it, under a user who had already answered.
		 */
		it( 'offers no page choice until it knows which options there are', async () => {
			let answer!: () => void;
			checkCreateSubjectPagePermission.mockImplementationOnce(
				() => new Promise<void>( ( resolve ) => {
					answer = () => {
						canCreateSubjectPage.value = true;
						resolve();
					};
				} ),
			);
			canCreateSubjectPage.value = false;
			const wrapper = mountWithoutHostPage();
			await pickSchema( wrapper );

			expect( offeredChoices( wrapper ) ).toEqual( [] );

			answer();
			await flushPromises();

			expect( offeredChoices( wrapper ) ).toEqual( [ 'newPage', 'anotherPage' ] );
		} );

		describe( 'without the right to create pages', () => {
			beforeEach( () => {
				canCreateSubjectPage.value = false;
			} );

			it( 'offers no new page', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );

				expect( offeredChoices( wrapper ) ).toEqual( [ 'thisPage', 'anotherPage' ] );
			} );

			it( 'starts on another page where there is no page of its own', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );

				expect( chosenOption( wrapper ) ).toBe( 'anotherPage' );
			} );
		} );

		describe( 'on a new page', () => {
			async function chooseNewPage( wrapper: VueWrapper ): Promise<void> {
				await pickSchema( wrapper );
				await choose( wrapper, 'newPage' );
			}

			it( 'creates the subject together with its page', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await typeLabel( wrapper, 'New Person' );

				await save( wrapper, 'why' );

				expect( subjectStore.createSubjectPage ).toHaveBeenCalledWith(
					'New Person', SCHEMA_NAME, expect.any( StatementList ), 'why', undefined,
				);
				expect( subjectStore.createMainSubject ).not.toHaveBeenCalled();
			} );

			it( 'creates the subject with no label when none was typed', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );

				await save( wrapper );

				expect( subjectStore.createSubjectPage ).toHaveBeenCalledWith(
					null, SCHEMA_NAME, expect.any( StatementList ), DEFAULT_CREATE_SUMMARY, undefined,
				);
			} );

			it( 'navigates to the page the server created when opened on a page', async () => {
				const wrapper = mountDialog();
				await chooseNewPage( wrapper );
				await typeLabel( wrapper, 'New Person' );

				await save( wrapper );

				expect( mw.storage.session.set ).toHaveBeenCalledWith( 'neowiki-subject-creator-success', '1' );
				expect( location.href ).toBe( '/wiki/New Person' );
				expect( reloadMock ).not.toHaveBeenCalled();
			} );

			/**
			 * The page the server titles from a label is not the label: it normalizes titles, and
			 * a label that titles no page gets one named after the Subject instead.
			 */
			it( 'navigates to the title the server reported, not the label typed', async () => {
				( subjectStore.createSubjectPage as any ).mockResolvedValue( {
					subjectId: new SubjectId( 's11111111111113' ),
					pageTitle: 'S11111111111113',
				} );
				const wrapper = mountDialog();
				await chooseNewPage( wrapper );
				await typeLabel( wrapper, 'Help:Not a page' );

				await save( wrapper );

				expect( location.href ).toBe( '/wiki/S11111111111113' );
			} );

			it( 'reports a title already taken at the page choice', async () => {
				( subjectStore.createSubjectPage as any ).mockRejectedValue( new PageTitleTakenError( 'Amsterdam' ) );
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await typeLabel( wrapper, 'Amsterdam' );

				await save( wrapper );

				expect( wrapper.text() ).toContain( 'neowiki-subject-creator-page-takenAmsterdam' );
				expect( location.href ).toBe( '' );
			} );

			it( 'leaves save reachable after a title already taken, so another label can answer it', async () => {
				( subjectStore.createSubjectPage as any ).mockRejectedValueOnce( new PageTitleTakenError( 'Amsterdam' ) );
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await typeLabel( wrapper, 'Amsterdam' );
				await save( wrapper );

				expect( wrapper.text() ).toContain( 'neowiki-subject-creator-page-taken' );
				expect( wrapper.findComponent( { name: 'SummaryAction' } ).props( 'saveDisabled' ) ).toBe( false );
			} );

		} );

		describe( 'the section holding it', () => {
			it( 'starts collapsed on the page the dialog was opened on, saying so', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );

				expect( sectionIsOpen( wrapper ) ).toBe( false );
				expect( sectionHeader( wrapper ) ).toContain( 'neowiki-subject-creator-page-section' );
				expect( shownChoice( wrapper ) ).toBe( 'neowiki-subject-creator-page-section-this' );
			} );

			it( 'starts collapsed on a new page where the dialog was opened on none', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );

				expect( sectionIsOpen( wrapper ) ).toBe( false );
				expect( shownChoice( wrapper ) ).toBe( 'neowiki-subject-creator-page-section-new' );
			} );

			it( 'names the page picked', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );
				await choose( wrapper, 'anotherPage' );
				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				expect( shownChoice( wrapper ) ).toBe( 'neowiki-subject-creator-page-section-pickedACME Inc' );
			} );

			it( 'names no page before one is picked', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );
				await choose( wrapper, 'anotherPage' );

				expect( shownChoice( wrapper ) ).toBe( 'neowiki-subject-creator-page-section-another' );
			} );

			it( 'names the title typed for the page to create', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await typePageTitle( wrapper, 'Delft' );

				expect( shownChoice( wrapper ) ).toBe( 'neowiki-subject-creator-page-section-new-titledDelft' );
			} );

			/** Open, the options say which one is chosen themselves. */
			it( 'stops naming the choice once it is open', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );

				await openSection( wrapper );

				expect( wrapper.find( '.ext-neowiki-subject-creator-page-section__choice' ).exists() ).toBe( false );
			} );
		} );

		describe( 'focus in the section', () => {
			let attached: VueWrapper | undefined;

			/** Attached to the document, since that is where what has focus is read from. */
			function mountAttached( props: Record<string, any> = {} ): VueWrapper {
				attached = mountDialog( props, { attachTo: document.body } );

				return attached;
			}

			afterEach( () => {
				attached?.unmount();
				attached = undefined;
			} );

			function pickerInput( wrapper: VueWrapper ): Element {
				return wrapper.find( '.page-picker-stub input' ).element;
			}

			function optionInput( wrapper: VueWrapper, value: string ): Element {
				return wrapper.find( `.cdx-radio-stub[data-value="${ value }"] input` ).element;
			}

			it( 'moves into the title of the page to create when the section is opened', async () => {
				const wrapper = mountAttached( { hostPage: null } );
				await pickSchema( wrapper );

				await openSection( wrapper );

				expect( document.activeElement ).toBe( pageTitleInput( wrapper ).element );
			} );

			it( 'moves into the page picker when the section is opened on an existing page', async () => {
				const wrapper = mountAttached();
				await pickSchema( wrapper );
				await choose( wrapper, 'anotherPage' );

				await openSection( wrapper );

				expect( document.activeElement ).toBe( pickerInput( wrapper ) );
			} );

			it( 'moves onto the choice made when the section is opened on a page already named', async () => {
				const wrapper = mountAttached();
				await pickSchema( wrapper );

				await openSection( wrapper );

				expect( document.activeElement ).toBe( optionInput( wrapper, 'thisPage' ) );
			} );

			it( 'follows the choice to the title of the page to create', async () => {
				const wrapper = mountAttached();
				await pickSchema( wrapper );
				await openSection( wrapper );

				await choose( wrapper, 'newPage' );

				expect( document.activeElement ).toBe( pageTitleInput( wrapper ).element );
			} );

			it( 'follows the choice to the page picker', async () => {
				const wrapper = mountAttached();
				await pickSchema( wrapper );
				await openSection( wrapper );

				await choose( wrapper, 'anotherPage' );

				expect( document.activeElement ).toBe( pickerInput( wrapper ) );
			} );

			it( 'leaves focus alone for a choice with nothing left to fill in', async () => {
				const wrapper = mountAttached();
				await pickSchema( wrapper );
				await openSection( wrapper );
				await choose( wrapper, 'anotherPage' );
				const elsewhere = wrapper.find( '.save-button' ).element as HTMLButtonElement;
				elsewhere.focus();

				await choose( wrapper, 'thisPage' );

				expect( document.activeElement ).toBe( elsewhere );
			} );
		} );

		describe( 'the title of the page to create', () => {
			it( 'is asked for where a page is to be created', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );

				await choose( wrapper, 'newPage' );

				expect( pageTitleField( wrapper ).exists() ).toBe( true );
			} );

			it( 'is not asked for where the page already exists', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );

				await choose( wrapper, 'anotherPage' );

				expect( pageTitleField( wrapper ).exists() ).toBe( false );
			} );

			it( 'says what titles the page when left empty, whether or not anything is named', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );

				expect( pageTitleField( wrapper ).text() ).toContain( 'neowiki-subject-creator-page-title-help' );

				await typeLabel( wrapper, 'Delft' );

				expect( pageTitleField( wrapper ).text() ).toContain( 'neowiki-subject-creator-page-title-help' );
			} );

			it( 'titles the page created', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await typeLabel( wrapper, 'Delft Blue' );
				await typePageTitle( wrapper, 'Delft' );

				await save( wrapper, 'why' );

				expect( subjectStore.createSubjectPage ).toHaveBeenCalledWith(
					'Delft Blue', SCHEMA_NAME, expect.any( StatementList ), 'why', 'Delft',
				);
			} );

			it( 'leaves the title to the server where none was typed', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await typeLabel( wrapper, 'Delft Blue' );

				await save( wrapper );

				expect( subjectStore.createSubjectPage ).toHaveBeenCalledWith(
					'Delft Blue', SCHEMA_NAME, expect.any( StatementList ), DEFAULT_CREATE_SUMMARY, undefined,
				);
			} );
		} );

		describe( 'a page the server refused', () => {
			it( 'opens the section on a title already taken, complaining at the title', async () => {
				( subjectStore.createSubjectPage as any ).mockRejectedValue( new PageTitleTakenError( 'Amsterdam' ) );
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await typePageTitle( wrapper, 'Amsterdam' );

				await save( wrapper );

				expect( sectionIsOpen( wrapper ) ).toBe( true );
				expect( pageTitleField( wrapper ).text() ).toContain( 'neowiki-subject-creator-page-takenAmsterdam' );
			} );

			it( 'opens the section on a title that titles no page, complaining at the title', async () => {
				( subjectStore.createSubjectPage as any )
					.mockRejectedValue( new InvalidPageTitleError( 'Help:Amsterdam' ) );
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await typePageTitle( wrapper, 'Help:Amsterdam' );

				await save( wrapper );

				expect( sectionIsOpen( wrapper ) ).toBe( true );
				expect( pageTitleField( wrapper ).text() )
					.toContain( 'neowiki-subject-creator-page-title-invalidHelp:Amsterdam' );
				expect( location.href ).toBe( '' );
			} );

			it( 'takes a changed title as the answer to one that titles no page', async () => {
				( subjectStore.createSubjectPage as any )
					.mockRejectedValueOnce( new InvalidPageTitleError( 'Help:Amsterdam' ) );
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await typePageTitle( wrapper, 'Help:Amsterdam' );
				await save( wrapper );

				await typePageTitle( wrapper, 'Amsterdam' );

				expect( wrapper.text() ).not.toContain( 'neowiki-subject-creator-page-title-invalid' );
				expect( wrapper.findComponent( { name: 'SummaryAction' } ).props( 'saveDisabled' ) ).toBe( false );
			} );

			it( 'opens the section on a page whose subjects could not be read', async () => {
				getPageSubjectsMock.mockRejectedValue( new Error( 'Graph store unavailable' ) );
				const wrapper = mountDialog();
				await pickSchema( wrapper );
				await choose( wrapper, 'anotherPage' );

				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				expect( sectionIsOpen( wrapper ) ).toBe( true );
			} );
		} );

		describe( 'on another page', () => {
			async function chooseAnotherPage( wrapper: VueWrapper ): Promise<void> {
				await pickSchema( wrapper );
				await choose( wrapper, 'anotherPage' );
			}

			it( 'offers only pages that exist', async () => {
				const wrapper = mountDialog();
				await chooseAnotherPage( wrapper );

				expect( wrapper.findComponent( PagePicker ).props( 'existingPagesOnly' ) ).toBe( true );
			} );

			it( 'keeps save unavailable until a page is picked', async () => {
				const wrapper = mountDialog();
				await chooseAnotherPage( wrapper );

				expect( wrapper.findComponent( { name: 'SummaryAction' } ).props( 'saveDisabled' ) ).toBe( true );

				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				expect( wrapper.findComponent( { name: 'SummaryAction' } ).props( 'saveDisabled' ) ).toBe( false );
			} );

			it( 'makes the subject the main subject of a page that has none, and goes there', async () => {
				const wrapper = mountDialog();
				await chooseAnotherPage( wrapper );
				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				await save( wrapper );

				expect( subjectStore.createMainSubject ).toHaveBeenCalledWith(
					EXISTING_PAGE_ID, null, SCHEMA_NAME, expect.any( StatementList ), DEFAULT_CREATE_SUMMARY,
				);
				expect( location.href ).toBe( '/wiki/ACME Inc' );
				expect( mw.storage.session.set ).toHaveBeenCalledWith( 'neowiki-subject-creator-success', '1' );
			} );

			it( 'adds the subject alongside an existing main subject, under the label given', async () => {
				const wrapper = mountDialog();
				await chooseAnotherPage( wrapper );
				await pickPageWithMainSubject( wrapper );

				await typeLabel( wrapper, 'Second opinion' );
				await save( wrapper );

				expect( subjectStore.createOtherSubject ).toHaveBeenCalledWith(
					EXISTING_PAGE_ID,
					'Second opinion',
					SCHEMA_NAME,
					expect.any( StatementList ),
					DEFAULT_CREATE_SUMMARY,
					new SubjectId( MINTED_ID ),
				);
				expect( subjectStore.createMainSubject ).not.toHaveBeenCalled();
			} );

			it( 'names the main subject the new one will sit beside', async () => {
				const wrapper = mountDialog();
				await chooseAnotherPage( wrapper );

				await pickPageWithMainSubject( wrapper );

				expect( wrapper.text() ).toContain( 'neowiki-subject-creator-page-has-main-subject' );
				expect( wrapper.text() ).toContain( 'ACME Inc' );
				expect( wrapper.text() ).not.toContain( 'neowiki-subject-creator-page-picked' );
			} );

			it( 'says the subject joins the page picked, which has no main subject to name it', async () => {
				const wrapper = mountDialog();
				await chooseAnotherPage( wrapper );

				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				expect( wrapper.text() ).toContain( 'neowiki-subject-creator-page-pickedACME Inc' );
			} );

			/**
			 * A page created for a Subject that nobody named is titled after that Subject, and an id
			 * is not a name, so the Subject taking it over is not named by it either. The rule the
			 * server applies once the Subject exists; this is the same rule, previewed.
			 */
			/**
			 * Where the Subject lands depends on what the page holds, so the note that says where it
			 * lands cannot be shown while that is still being read.
			 */
			it( 'names no page until what that page holds is known', async () => {
				const slowRead = deferred<unknown>();
				getPageSubjectsMock.mockReturnValueOnce( slowRead.promise );
				const wrapper = mountDialog();
				await chooseAnotherPage( wrapper );

				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				expect( wrapper.text() ).not.toContain( 'neowiki-subject-creator-page-picked' );

				slowRead.resolve( {
					pageSubjects: new PageSubjects( EXISTING_PAGE_ID, null, [] ),
					referencedSubjects: [],
					schemas: [],
				} );
				await flushPromises();

				expect( wrapper.text() ).toContain( 'neowiki-subject-creator-page-pickedACME Inc' );
			} );

			it( 'names no page whose subjects could not be read', async () => {
				getPageSubjectsMock.mockRejectedValue( new Error( 'Graph store unavailable' ) );
				const wrapper = mountDialog();
				await chooseAnotherPage( wrapper );

				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				expect( wrapper.text() ).not.toContain( 'neowiki-subject-creator-page-picked' );
			} );

			/**
			 * Only the label answers a title already taken; a page that would not read is still
			 * unread whatever else is typed, and saving onto it would guess at where the Subject goes.
			 */
			it( 'keeps a page that could not be read blocking while the rest of the form is edited', async () => {
				getPageSubjectsMock.mockRejectedValue( new Error( 'Graph store unavailable' ) );
				const wrapper = mountDialog();
				await chooseAnotherPage( wrapper );
				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				wrapper.findComponent( SubjectEditorDialog ).vm.$emit( 'change' );
				await flushPromises();

				expect( wrapper.text() ).toContain( 'neowiki-subject-creator-page-read-error' );
				expect( wrapper.findComponent( { name: 'SummaryAction' } ).props( 'saveDisabled' ) ).toBe( true );
			} );

			it( 'takes no further page while the save is in flight', async () => {
				// The write loop flushes every pane's validation before its first write, and that
				// round trip is the window in which the footer would otherwise still be answerable.
				const flush = deferred<void>();
				beforeFirstWrite = flush.promise;
				const wrapper = mountDialog();
				await chooseAnotherPage( wrapper );
				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				const saved = save( wrapper );
				await flushPromises();

				expect( wrapper.findComponent( PagePicker ).props( 'disabled' ) ).toBe( true );
				expect( wrapper.find( '.cdx-radio-stub input' ).attributes( 'disabled' ) ).toBeDefined();

				flush.resolve();
				await saved;
				await flushPromises();

				expect( wrapper.findComponent( PagePicker ).props( 'disabled' ) ).toBe( false );
			} );

			// The freeze is the first guard; this is the second. The write's own awaits — the draft
			// Schema's save above all — sit past it, so the whole answer is read once rather than
			// again on the far side of each one: the page the write lands on, and the page the
			// dialog then says it landed on, cannot come apart.
			it( 'creates and reports the page that was answered when the write began', async () => {
				const schemaSave = deferred<void>();
				( schemaStore.saveSchema as any ).mockReturnValue( schemaSave.promise );
				const wrapper = mountDialog();
				await switchToNewSchema( wrapper );
				await clickContinue( wrapper );
				await choose( wrapper, 'anotherPage' );
				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				const saved = save( wrapper );
				await flushPromises();
				await pickPage( wrapper, null );
				schemaSave.resolve();
				await saved;
				await flushPromises();

				expect( subjectStore.createMainSubject ).toHaveBeenCalledWith(
					EXISTING_PAGE_ID, null, NEW_SCHEMA_NAME, expect.any( StatementList ), DEFAULT_CREATE_SUMMARY,
				);
				expect( location.href ).toContain( 'ACME Inc' );
				expect( reloadMock ).not.toHaveBeenCalled();
			} );

			it( 'blocks saving when the chosen page could not be read', async () => {
				getPageSubjectsMock.mockRejectedValue( new Error( 'Graph store unavailable' ) );
				const wrapper = mountDialog();
				await chooseAnotherPage( wrapper );
				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				expect( wrapper.findComponent( { name: 'SummaryAction' } ).props( 'saveDisabled' ) ).toBe( true );
				expect( wrapper.text() ).toContain( 'neowiki-subject-creator-page-read-error' );

				await save( wrapper );

				expect( subjectStore.createMainSubject ).not.toHaveBeenCalled();
			} );

			it( 'ignores the main-subject answer for a page the user has moved off', async () => {
				const slowRead = deferred<unknown>();
				getPageSubjectsMock.mockReturnValueOnce( slowRead.promise );
				const wrapper = mountDialog();
				await chooseAnotherPage( wrapper );
				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				await pickPage( wrapper, { pageId: OTHER_PAGE_ID, title: 'Other Page' } );
				slowRead.resolve( pageWithMainSubject( 'ACME Inc' ) );
				await flushPromises();

				expect( wrapper.text() ).not.toContain( 'ACME Inc' );

				await save( wrapper );

				expect( subjectStore.createMainSubject ).toHaveBeenCalledWith(
					OTHER_PAGE_ID, null, SCHEMA_NAME, expect.any( StatementList ), DEFAULT_CREATE_SUMMARY,
				);
			} );

			it( 'drops the page picked once the choice moves back to a new page', async () => {
				const wrapper = mountDialog();
				await chooseAnotherPage( wrapper );
				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				await choose( wrapper, 'newPage' );
				await save( wrapper );

				expect( subjectStore.createSubjectPage ).toHaveBeenCalled();
				expect( subjectStore.createMainSubject ).not.toHaveBeenCalled();
			} );
		} );

		function closeWouldDiscard( wrapper: VueWrapper ): boolean {
			return wrapper.findComponent( SubjectEditorDialog ).props( 'hostHasUnsavedChanges' ) as boolean;
		}

		it( 'reports nothing to discard once the page picked has been taken back', async () => {
			const wrapper = mountWithoutHostPage( { initialSchemaName: SCHEMA_NAME } );
			await wrapper.setProps( { open: true } );
			await flushPromises();

			await choose( wrapper, 'anotherPage' );
			await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );
			await pickPage( wrapper, null );

			expect( closeWouldDiscard( wrapper ) ).toBe( false );
		} );

		it( 'reports nothing to discard once the title typed for a new page has been taken back', async () => {
			const wrapper = mountWithoutHostPage( { initialSchemaName: SCHEMA_NAME } );
			await wrapper.setProps( { open: true } );
			await flushPromises();

			await typePageTitle( wrapper, 'Delft' );
			await typePageTitle( wrapper, '' );

			expect( closeWouldDiscard( wrapper ) ).toBe( false );
		} );

		it( 'reports a title still typed as something a close would discard', async () => {
			const wrapper = mountWithoutHostPage( { initialSchemaName: SCHEMA_NAME } );
			await wrapper.setProps( { open: true } );
			await flushPromises();

			await typePageTitle( wrapper, 'Delft' );

			expect( closeWouldDiscard( wrapper ) ).toBe( true );
		} );

		it( 'reports a page still picked as something a close would discard', async () => {
			const wrapper = mountWithoutHostPage( { initialSchemaName: SCHEMA_NAME } );
			await wrapper.setProps( { open: true } );
			await flushPromises();

			await choose( wrapper, 'anotherPage' );
			await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

			expect( closeWouldDiscard( wrapper ) ).toBe( true );
		} );

		describe( 'initialPage', () => {
			// Renders its slot, so the error text is assertable.
			const CdxMessageStub = {
				template: '<div class="cdx-message-stub"><slot /></div>',
				props: [ 'type', 'inline' ],
			};

			function mountWithInitialPage( initialPage: InitialPage, props: Record<string, any> = {} ): VueWrapper {
				return mountComponent(
					{
						PagePicker: PagePickerStub,
						CdxField: CdxFieldWithMessagesStub,
						I18nSlot: I18nSlotStub,
						CdxMessage: CdxMessageStub,
					},
					{ initialSchemaName: SCHEMA_NAME, initialPage, ...props },
				);
			}

			async function open( wrapper: VueWrapper ): Promise<void> {
				await wrapper.setProps( { open: true } );
				await flushPromises();
				// Second flush: the page is filled in after the choice watcher runs.
				await flushPromises();
				await wrapper.vm.$nextTick();
			}

			function fixedSection( wrapper: VueWrapper ): DOMWrapper<Element> {
				return wrapper.find( '.ext-neowiki-subject-creator-page-summary' );
			}

			it( 'states a fixed this page and hides the choice', async () => {
				const wrapper = mountWithInitialPage( { choice: 'thisPage', fixed: true } );
				await open( wrapper );

				expect( fixedSection( wrapper ).exists() ).toBe( true );
				expect( shownChoice( wrapper ) ).toBe( 'neowiki-subject-creator-page-section-this' );
				expect( wrapper.findAll( '.cdx-radio-stub' ) ).toHaveLength( 0 );
				expect( pageTitleField( wrapper ).exists() ).toBe( false );
			} );

			it( 'saves a fixed new page under the title it was given', async () => {
				const wrapper = mountWithInitialPage( {
					choice: 'newPage',
					page: { pageId: null, title: 'Ada Lovelace' },
					fixed: true,
				} );
				await open( wrapper );

				expect( shownChoice( wrapper ) )
					.toBe( 'neowiki-subject-creator-page-section-new-titledAda Lovelace' );

				await save( wrapper );

				expect( subjectStore.createSubjectPage ).toHaveBeenCalledWith(
					null, SCHEMA_NAME, expect.any( StatementList ), DEFAULT_CREATE_SUMMARY, 'Ada Lovelace',
				);
			} );

			it( 'saves a fixed new page without a title', async () => {
				const wrapper = mountWithInitialPage( { choice: 'newPage', fixed: true } );
				await open( wrapper );

				expect( shownChoice( wrapper ) ).toBe( 'neowiki-subject-creator-page-section-new' );

				await save( wrapper );

				expect( subjectStore.createSubjectPage ).toHaveBeenCalledWith(
					null, SCHEMA_NAME, expect.any( StatementList ), DEFAULT_CREATE_SUMMARY, undefined,
				);
			} );

			it( 'saves onto a fixed page without a main Subject as its main Subject', async () => {
				const wrapper = mountWithInitialPage( {
					choice: 'anotherPage',
					page: { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' },
					fixed: true,
				} );
				await open( wrapper );

				expect( shownChoice( wrapper ) ).toBe( 'neowiki-subject-creator-page-section-pickedACME Inc' );

				await save( wrapper );

				expect( subjectStore.createMainSubject ).toHaveBeenCalledWith(
					EXISTING_PAGE_ID, null, SCHEMA_NAME, expect.any( StatementList ), DEFAULT_CREATE_SUMMARY,
				);
			} );

			it( 'reads the fixed page only once opened', async () => {
				const wrapper = mountWithInitialPage( {
					choice: 'anotherPage',
					page: { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' },
					fixed: true,
				} );
				await flushPromises();

				expect( getPageSubjectsMock ).not.toHaveBeenCalled();

				await open( wrapper );

				expect( getPageSubjectsMock ).toHaveBeenCalledWith( EXISTING_PAGE_ID );
			} );

			it( 'saves onto a fixed page with a main Subject as another Subject', async () => {
				getPageSubjectsMock.mockResolvedValue( pageWithMainSubject( 'ACME Inc' ) );
				const wrapper = mountWithInitialPage( {
					choice: 'anotherPage',
					page: { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' },
					fixed: true,
				} );
				await open( wrapper );

				await save( wrapper );

				expect( subjectStore.createOtherSubject ).toHaveBeenCalledWith(
					EXISTING_PAGE_ID,
					null,
					SCHEMA_NAME,
					expect.any( StatementList ),
					DEFAULT_CREATE_SUMMARY,
					new SubjectId( MINTED_ID ),
				);
				expect( subjectStore.createMainSubject ).not.toHaveBeenCalled();
			} );

			it( 'shows an error and blocks saving when the fixed page cannot be read', async () => {
				getPageSubjectsMock.mockRejectedValue( new Error( 'network down' ) );
				const wrapper = mountWithInitialPage( {
					choice: 'anotherPage',
					page: { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' },
					fixed: true,
				} );
				await open( wrapper );

				expect( wrapper.text() ).toContain( 'neowiki-subject-creator-page-read-errorACME Inc' );
				expect( wrapper.findComponent( { name: 'SummaryAction' } ).props( 'saveDisabled' ) ).toBe( true );
			} );

			it( 'closes without confirming when only the fixed page was filled in', async () => {
				const wrapper = mountWithInitialPage( {
					choice: 'anotherPage',
					page: { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' },
					fixed: true,
				} );
				await open( wrapper );

				// The page is filled in, which answering it by hand would report as a change.
				expect( shownChoice( wrapper ) ).toBe( 'neowiki-subject-creator-page-section-pickedACME Inc' );
				expect( wrapper.findComponent( SubjectEditorDialog ).props( 'hostHasUnsavedChanges' ) ).toBe( false );

				await requestClose( wrapper );

				expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
			} );

			it( 'preselects an unfixed page but still offers the choice', async () => {
				const wrapper = mountWithInitialPage( { choice: 'newPage', fixed: false } );
				await open( wrapper );

				expect( fixedSection( wrapper ).exists() ).toBe( false );
				expect( chosenOption( wrapper ) ).toBe( 'newPage' );
				expect( offeredChoices( wrapper ) ).toEqual( [ 'thisPage', 'anotherPage', 'newPage' ] );
			} );

			it( 'asks for a different label when the page titled after it is taken', async () => {
				( subjectStore.createSubjectPage as any ).mockRejectedValue( new PageTitleTakenError( 'Paris' ) );
				const wrapper = mountWithInitialPage( { choice: 'newPage', fixed: true } );
				await open( wrapper );
				await typeLabel( wrapper, 'Paris' );

				await save( wrapper );

				expect( fixedSection( wrapper ).text() ).toContain( 'neowiki-subject-creator-page-taken-fixedParis' );
			} );

			it( 'reports a taken title of the fixed page\'s own as before', async () => {
				( subjectStore.createSubjectPage as any ).mockRejectedValue( new PageTitleTakenError( 'Ada Lovelace' ) );
				const wrapper = mountWithInitialPage( {
					choice: 'newPage',
					page: { pageId: null, title: 'Ada Lovelace' },
					fixed: true,
				} );
				await open( wrapper );
				await typeLabel( wrapper, 'Someone' );

				await save( wrapper );

				expect( fixedSection( wrapper ).text() ).toContain( 'neowiki-subject-creator-page-takenAda Lovelace' );
			} );

		} );
	} );

	describe( 'Existing schema flow', () => {
		it( 'focuses SchemaPicker when the dialog opens', async () => {
			const wrapper = mountComponent();

			await wrapper.setProps( { open: true } );
			await flushPromises();

			const pickerVm = wrapper.findComponent( SchemaPicker ).vm as any;
			expect( pickerVm.focus ).toHaveBeenCalled();
		} );

		it( 'focuses SchemaPicker when switching back to "Use existing"', async () => {
			const wrapper = mountComponent();
			await switchToNewSchema( wrapper );

			wrapper.findComponent( { name: 'CdxToggleButtonGroup' } )
				.vm.$emit( 'update:modelValue', 'existing' );
			await flushPromises();

			const pickerVm = wrapper.findComponent( SchemaPicker ).vm as any;
			expect( pickerVm.focus ).toHaveBeenCalled();
		} );
	} );

	describe( 'Create new schema flow', () => {
		it( 'shows SchemaCreator when "Create new" is selected', async () => {
			const wrapper = mountComponent();

			await switchToNewSchema( wrapper );

			expect( wrapper.find( '.schema-creator-stub' ).exists() ).toBe( true );
			expect( wrapper.find( '.ext-neowiki-subject-creator-continue' ).exists() ).toBe( true );
		} );

		it( 'does not show SchemaPicker when "Create new" is selected', async () => {
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

		// Continue is the only point on this route where the schema can still be held
		// back: it captures the draft and tears the creator down.
		it( 'does not continue while the creator reports a reason not to', async () => {
			const wrapper = mountComponent();
			await switchToNewSchema( wrapper );
			schemaCreatorSaveBlocker = { propertyName: 'Score', message: 'neowiki-field-invalid-number' };

			await clickContinue( wrapper );

			expect( wrapper.find( '.schema-creator-stub' ).exists() ).toBe( true );
			expect( schemaStore.saveSchema ).not.toHaveBeenCalled();
			expect( mw.notify ).toHaveBeenCalledWith(
				'neowiki-field-invalid-number',
				{ title: 'Score', type: 'error' },
			);
		} );

		it( 'continues once the creator reports none', async () => {
			const wrapper = mountComponent();
			await switchToNewSchema( wrapper );
			schemaCreatorSaveBlocker = { propertyName: 'Score', message: 'neowiki-field-invalid-number' };
			await clickContinue( wrapper );

			schemaCreatorSaveBlocker = null;
			await clickContinue( wrapper );

			expect( wrapper.find( '.schema-creator-stub' ).exists() ).toBe( false );
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
			expect( wrapper.find( '.subject-editor-dialog-stub' ).exists() ).toBe( true );
			expect( wrapper.find( '.schema-creator-stub' ).exists() ).toBe( false );
		} );

		it( 'saves schema and creates subject on final save', async () => {
			const wrapper = mountComponent();

			await switchToNewSchema( wrapper );

			await clickContinue( wrapper );

			await wrapper.findComponent( { name: 'SummaryAction' } ).vm.$emit( 'save', 'Created subject' );
			await flushPromises();

			expect( schemaStore.saveSchema ).toHaveBeenCalledWith(
				expect.any( Schema ),
				'Created subject',
			);

			const savedSchema = ( schemaStore.saveSchema as ReturnType<typeof vi.fn> ).mock.calls[ 0 ][ 0 ] as Schema;
			expect( savedSchema.getName() ).toBe( NEW_SCHEMA_NAME );

			expect( subjectStore.createMainSubject ).toHaveBeenCalledWith(
				PAGE_ID,
				null,
				NEW_SCHEMA_NAME,
				expect.any( StatementList ),
				'Created subject',
			);
		} );

		it( 'passes edit summary to saveSchema on final save', async () => {
			const wrapper = mountComponent();

			await switchToNewSchema( wrapper );
			await clickContinue( wrapper );

			await wrapper.findComponent( { name: 'SummaryAction' } ).vm.$emit( 'save', 'My edit summary' );
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

			await wrapper.findComponent( { name: 'SummaryAction' } ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( schemaStore.saveSchema ).toHaveBeenCalledWith(
				expect.any( Schema ),
				DEFAULT_CREATE_SUMMARY,
			);
		} );

		// The editor offers the Schema editor from the root pane, and the Schema it saves there is
		// on the wiki. Writing the draft over it afterwards would drop whatever was added.
		it( 'stops treating the schema as a draft once it is saved from inside the editor', async () => {
			const wrapper = mountComponent();

			await wrapper.setProps( { open: true } );
			await flushPromises();
			await switchToNewSchema( wrapper );
			await clickContinue( wrapper );

			const edited = new Schema( NEW_SCHEMA_NAME, 'With another property', new PropertyDefinitionList( [] ) );
			await ( wrapper.findComponent( SubjectEditorDialog ).props( 'onSaveSchema' ) as any )( edited, 'from the editor' );
			await flushPromises();

			await wrapper.findComponent( { name: 'SummaryAction' } ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( schemaStore.saveSchema ).toHaveBeenCalledTimes( 1 );
			expect( schemaStore.saveSchema ).toHaveBeenCalledWith( edited, 'from the editor' );
		} );

		// The Schema is on the wiki now, so there is nothing left to abandon.
		it( 'asks nothing about the schema on close once it has been saved from inside the editor', async () => {
			const wrapper = mountComponent();

			await wrapper.setProps( { open: true } );
			await flushPromises();
			await switchToNewSchema( wrapper );
			await clickContinue( wrapper );

			const edited = new Schema( NEW_SCHEMA_NAME, 'With another property', new PropertyDefinitionList( [] ) );
			await ( wrapper.findComponent( SubjectEditorDialog ).props( 'onSaveSchema' ) as any )( edited, 'from the editor' );
			await flushPromises();

			await requestClose( wrapper );

			expect( wrapper.findComponent( SchemaAbandonmentDialog ).props( 'open' ) ).toBe( false );
			expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
		} );

		it( 'creates no subject when the schema it uses could not be saved', async () => {
			schemaStore.saveSchema = vi.fn().mockRejectedValue( new Error( 'Schema save failed' ) );
			const wrapper = mountComponent();

			await wrapper.setProps( { open: true } );
			await flushPromises();
			await switchToNewSchema( wrapper );

			await clickContinue( wrapper );

			await wrapper.findComponent( { name: 'SummaryAction' } ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( ( lastSaveError as Error ).message ).toBe( 'Schema save failed' );
			expect( subjectStore.createMainSubject ).not.toHaveBeenCalled();
			expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
		} );

		it( 'resets to schema step when dialog closes', async () => {
			const wrapper = mountComponent();

			await wrapper.setProps( { open: true } );
			await flushPromises();
			await switchToNewSchema( wrapper );

			await clickContinue( wrapper );

			await requestClose( wrapper );

			wrapper.findComponent( SchemaAbandonmentDialog ).vm.$emit( 'abandon' );
			await flushPromises();

			await wrapper.setProps( { open: false } );
			await wrapper.setProps( { open: true } );
			await flushPromises();

			expect( wrapper.find( '.schema-lookup-stub' ).exists() ).toBe( true );
			expect( wrapper.find( '.subject-editor-dialog-stub' ).exists() ).toBe( false );
		} );
	} );

	describe( 'Close confirmation', () => {
		it( 'shows confirmation when closing with a schema half written', async () => {
			const wrapper = mountComponent();

			await wrapper.setProps( { open: true } );
			await flushPromises();
			await switchToNewSchema( wrapper );

			wrapper.findComponent( SchemaCreator ).vm.$emit( 'change' );
			await flushPromises();

			await requestClose( wrapper );

			expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
			expect( wrapper.findComponent( CloseConfirmationDialog ).props( 'open' ) ).toBe( true );
		} );

		it( 'closes without confirmation when there are no unsaved changes', async () => {
			const wrapper = mountComponent();

			await wrapper.setProps( { open: true } );
			await flushPromises();

			await requestClose( wrapper );

			expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
		} );

		it( 'closes dialog when discard is clicked in confirmation', async () => {
			const wrapper = mountComponent();

			await wrapper.setProps( { open: true } );
			await flushPromises();
			await switchToNewSchema( wrapper );

			wrapper.findComponent( SchemaCreator ).vm.$emit( 'change' );
			await flushPromises();

			await requestClose( wrapper );

			wrapper.findComponent( CloseConfirmationDialog ).vm.$emit( 'discard' );
			await flushPromises();

			expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
		} );

		it( 'keeps dialog open when keep-editing is clicked in confirmation', async () => {
			const wrapper = mountComponent();

			await wrapper.setProps( { open: true } );
			await flushPromises();
			await switchToNewSchema( wrapper );

			wrapper.findComponent( SchemaCreator ).vm.$emit( 'change' );
			await flushPromises();

			await requestClose( wrapper );

			wrapper.findComponent( CloseConfirmationDialog ).vm.$emit( 'keep-editing' );
			await flushPromises();

			expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
			expect( wrapper.findComponent( CloseConfirmationDialog ).props( 'open' ) ).toBe( false );
		} );

		it( 'does not ask again after closing when a close was requested while the picked schema loaded', async () => {
			let resolveSchema!: ( schema: Schema ) => void;
			getSchemaMock.mockReturnValue( new Promise<Schema>( ( resolve ) => {
				resolveSchema = resolve;
			} ) );
			const wrapper = mountComponent();
			await wrapper.setProps( { open: true } );
			await flushPromises();
			wrapper.findComponent( SchemaPicker ).vm.$emit( 'select', SCHEMA_NAME );
			await flushPromises();
			await requestClose( wrapper );
			resolveSchema( newSchema( { title: SCHEMA_NAME } ) );
			await flushPromises();
			await requestClose( wrapper );

			await wrapper.setProps( { open: false } );
			await flushPromises();

			expect( wrapper.findComponent( CloseConfirmationDialog ).props( 'open' ) ).toBe( false );
		} );
	} );

	describe( 'Close confirmation with draft schema', () => {
		it( 'shows three-option dialog when closing with draft schema on subject step', async () => {
			const wrapper = mountComponent();

			await wrapper.setProps( { open: true } );
			await flushPromises();
			await switchToNewSchema( wrapper );

			await clickContinue( wrapper );

			await requestClose( wrapper );

			expect( wrapper.findComponent( SchemaAbandonmentDialog ).props( 'open' ) ).toBe( true );
		} );

		it( 'closes without saving on abandon', async () => {
			const wrapper = mountComponent();

			await wrapper.setProps( { open: true } );
			await flushPromises();
			await switchToNewSchema( wrapper );

			await clickContinue( wrapper );

			await requestClose( wrapper );

			wrapper.findComponent( SchemaAbandonmentDialog ).vm.$emit( 'abandon' );
			await flushPromises();

			expect( schemaStore.saveSchema ).not.toHaveBeenCalled();
			expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
		} );

		it( 'saves schema and closes on save-schema', async () => {
			const wrapper = mountComponent();

			await wrapper.setProps( { open: true } );
			await flushPromises();
			await switchToNewSchema( wrapper );

			await clickContinue( wrapper );

			await requestClose( wrapper );

			wrapper.findComponent( SchemaAbandonmentDialog ).vm.$emit( 'save-schema' );
			await flushPromises();

			expect( schemaStore.saveSchema ).toHaveBeenCalledWith(
				expect.any( Schema ),
			);
			expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
		} );

		it( 'keeps dialog open on keep-editing', async () => {
			const wrapper = mountComponent();

			await wrapper.setProps( { open: true } );
			await flushPromises();
			await switchToNewSchema( wrapper );

			await clickContinue( wrapper );

			await requestClose( wrapper );

			wrapper.findComponent( SchemaAbandonmentDialog ).vm.$emit( 'keep-editing' );
			await flushPromises();

			expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
			expect( wrapper.findComponent( SchemaAbandonmentDialog ).props( 'open' ) ).toBe( false );
		} );

		it( 'asks nothing of its own once the editor reports a close', async () => {
			const wrapper = mountComponent();

			await wrapper.setProps( { open: true } );
			await flushPromises();
			await wrapper.findComponent( SchemaPicker ).vm.$emit( 'select', SCHEMA_NAME );
			await flushPromises();

			await requestClose( wrapper );

			expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
		} );

		it( 'uses standard close confirmation on schema editor step without draft', async () => {
			const wrapper = mountComponent();

			await wrapper.setProps( { open: true } );
			await flushPromises();
			await switchToNewSchema( wrapper );

			wrapper.findComponent( SchemaCreator ).vm.$emit( 'change' );
			await flushPromises();

			await requestClose( wrapper );

			expect( wrapper.findComponent( CloseConfirmationDialog ).props( 'open' ) ).toBe( true );
		} );

		it( 'shows error and keeps dialog open when save-schema fails', async () => {
			schemaStore.saveSchema = vi.fn().mockRejectedValue( new Error( 'Save failed' ) );
			const wrapper = mountComponent();

			await wrapper.setProps( { open: true } );
			await flushPromises();
			await switchToNewSchema( wrapper );

			await clickContinue( wrapper );

			await requestClose( wrapper );

			wrapper.findComponent( SchemaAbandonmentDialog ).vm.$emit( 'save-schema' );
			await flushPromises();

			expect( mw.notify ).toHaveBeenCalledWith(
				'Save failed',
				expect.objectContaining( { type: 'error' } ),
			);
			expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
		} );
	} );

	// Run on Codex's real dialogs rather than stubs, so that the order they stand in the document is
	// the order the user sees them stacked in.
	describe( 'Confirmation stacking', () => {
		const SubjectEditorDialogWithCodexDialog = {
			name: 'SubjectEditorDialog',
			components: { CdxDialog },
			template: '<CdxDialog :open="open" title="subject-editor" />',
			props: [ 'open', 'subject', 'schema', 'rootIsNew', 'saveDisabled', 'hostHasUnsavedChanges', 'onSave', 'onCreate', 'onSaveSchema', 'onSaved' ],
			emits: [ 'update:open' ],
		};

		let wrapper: VueWrapper | undefined;

		function mountWithCodexDialogs(): VueWrapper {
			wrapper = mountComponent( {
				CdxDialog: false,
				CloseConfirmationDialog: false,
				SchemaAbandonmentDialog: false,
				SubjectEditorDialog: SubjectEditorDialogWithCodexDialog,
				teleport: false,
			} );

			return wrapper;
		}

		afterEach( () => {
			wrapper?.unmount();
			wrapper = undefined;
		} );

		/** Closes the Schema step by its own dialog, which here is not the only real one in the tree. */
		async function closeSchemaStep( dialog: VueWrapper ): Promise<void> {
			dialog.findAllComponents( CdxDialog )
				.find( ( cdxDialog ) => cdxDialog.props( 'title' ) === 'neowiki-subject-creator-title' )!
				.vm.$emit( 'update:open', false );
			await flushPromises();
		}

		// The Schema and Subject steps here, and the reopening, are the test: they are what puts the
		// Schema step's dialog into the document later than the confirmation that covers it.
		it( 'shows the discard confirmation above a schema step shown again', async () => {
			const dialog = mountWithCodexDialogs();
			await dialog.setProps( { open: true } );
			await flushPromises();
			await dialog.findComponent( SchemaPicker ).vm.$emit( 'select', SCHEMA_NAME );
			await flushPromises();
			await requestClose( dialog );
			await dialog.setProps( { open: false } );
			await dialog.setProps( { open: true } );
			await flushPromises();
			await switchToNewSchema( dialog );
			dialog.findComponent( SchemaCreator ).vm.$emit( 'change' );
			await flushPromises();

			await closeSchemaStep( dialog );

			expect( openDialogTitles() ).toEqual( [ 'neowiki-subject-creator-title', 'neowiki-close-confirmation-title' ] );
		} );

		it( 'shows the schema abandonment question above the subject step', async () => {
			const dialog = mountWithCodexDialogs();
			await dialog.setProps( { open: true } );
			await flushPromises();
			await switchToNewSchema( dialog );
			await new DOMWrapper( document.querySelector( '.ext-neowiki-subject-creator-continue cdx-button-stub' )! ).trigger( 'click' );
			await flushPromises();

			await requestClose( dialog );

			expect( openDialogTitles() ).toEqual( [ 'subject-editor', 'neowiki-schema-abandonment-title' ] );
		} );
	} );

	// Subjects invented while filling in a relation field, which the editor hands back as
	// creations of their own once the save runs.
	describe( 'the Subjects created alongside', () => {
		const DRAFT_ID = 's1draftAAAAAAA1';
		const OWN_PAGE_ID = 99;

		function draft(): Subject {
			return newSubject( { id: DRAFT_ID, label: 'A colleague', schemaName: 'Colleague' } );
		}

		beforeEach( () => {
			subjectStore.createSubject = vi.fn().mockResolvedValue( new SubjectId( DRAFT_ID ) );
			subjectStore.createSubjectPage = vi.fn().mockResolvedValue( {
				subjectId: new SubjectId( CREATED_PAGE_SUBJECT_ID ),
				pageTitle: 'New Person',
				pageId: CREATED_PAGE_ID,
			} );
			subjectStore.updateSubject = vi.fn().mockResolvedValue( undefined );
		} );

		async function openOn( props: Record<string, any> = {} ): Promise<VueWrapper> {
			const wrapper = mountComponent( {}, { initialSchemaName: SCHEMA_NAME, ...props } );
			await wrapper.setProps( { open: true } );
			await flushPromises();
			return wrapper;
		}

		async function save( wrapper: VueWrapper ): Promise<void> {
			await wrapper.findComponent( { name: 'SummaryAction' } ).vm.$emit( 'save', '' );
			await flushPromises();
		}

		// The page does not exist while the dialog is open, so nothing can say where the draft
		// goes until the write that creates it has answered.
		it( 'stores one on the page the Subject\'s own write created', async () => {
			sessionDrafts = [ { subject: draft(), pageId: 0 } ];
			const wrapper = await openOn( { hostPage: null } );

			await save( wrapper );

			expect( subjectStore.createSubject ).toHaveBeenCalledWith(
				expect.any( Subject ), CREATED_PAGE_ID, DEFAULT_CREATE_SUMMARY,
			);
		} );

		it( 'stores one on the page answered in the footer', async () => {
			sessionDrafts = [ { subject: draft(), pageId: 0 } ];
			const wrapper = await openOn();

			await save( wrapper );

			expect( subjectStore.createSubject ).toHaveBeenCalledWith(
				expect.any( Subject ), PAGE_ID, DEFAULT_CREATE_SUMMARY,
			);
		} );

		// One created while drilled into another Subject belongs beside that Subject, not beside
		// the one being created, and the editor has already resolved the page it is stored on.
		it( 'stores one made against another Subject on that Subject\'s own page', async () => {
			sessionDrafts = [ { subject: draft(), pageId: OWN_PAGE_ID } ];
			const wrapper = await openOn( { hostPage: null } );

			await save( wrapper );

			expect( subjectStore.createSubject ).toHaveBeenCalledWith(
				expect.any( Subject ), OWN_PAGE_ID, DEFAULT_CREATE_SUMMARY,
			);
		} );

		// A second pass over a root the first one created would make a second Subject, or be
		// refused outright by a page title that is now taken.
		it( 'updates rather than creates the Subject again after a save that stopped part way', async () => {
			( subjectStore.createSubject as any ).mockRejectedValueOnce( new Error( 'Server error' ) );
			sessionDrafts = [ { subject: draft(), pageId: 0 } ];
			const wrapper = await openOn();

			await save( wrapper );
			await save( wrapper );

			expect( subjectStore.createMainSubject ).toHaveBeenCalledTimes( 1 );
			expect( subjectStore.updateSubject ).toHaveBeenCalledWith(
				expect.any( Subject ), DEFAULT_CREATE_SUMMARY,
			);
		} );

		it( 'keeps the id the server gave the Subject when it writes it again', async () => {
			( subjectStore.createSubject as any ).mockRejectedValueOnce( new Error( 'Server error' ) );
			( subjectStore.createMainSubject as any ).mockResolvedValue( new SubjectId( CREATED_PAGE_SUBJECT_ID ) );
			sessionDrafts = [ { subject: draft(), pageId: 0 } ];
			const wrapper = await openOn();

			await save( wrapper );
			await save( wrapper );

			const written = ( subjectStore.updateSubject as any ).mock.calls[ 0 ][ 0 ] as Subject;
			expect( written.getId().text ).toBe( CREATED_PAGE_SUBJECT_ID );
		} );

		// The id was minted for this Subject alone, so the server holding it means this very create
		// landed and only its answer was lost. Treating that as a failure leaves the dialog with no
		// record of the Subject it made, and every retry repeats the refusal.
		it( 'takes an id the server already holds as the Subject having been created', async () => {
			( subjectStore.createOtherSubject as any ).mockRejectedValueOnce(
				new SubjectIdInUseError( MINTED_ID ),
			);
			const wrapper = await openOn( { hostPage: { hasMainSubject: true } } );

			await save( wrapper );

			expect( lastSaveError ).toBeNull();
			expect( reloadMock ).toHaveBeenCalled();
		} );

		it( 'writes the Subjects alongside onto the page that create had already reached', async () => {
			( subjectStore.createOtherSubject as any ).mockRejectedValueOnce(
				new SubjectIdInUseError( MINTED_ID ),
			);
			sessionDrafts = [ { subject: draft(), pageId: 0 } ];
			const wrapper = await openOn( { hostPage: { hasMainSubject: true } } );

			await save( wrapper );

			expect( subjectStore.createSubject ).toHaveBeenCalledWith(
				expect.any( Subject ), PAGE_ID, DEFAULT_CREATE_SUMMARY,
			);
		} );

		// The whole save is through before anyone leaves the page: a Subject created alongside is
		// written after the one that points at it, so navigating on that first write would take
		// the rest of the save with it.
		it( 'leaves for the created Subject only once every write is through', async () => {
			sessionDrafts = [ { subject: draft(), pageId: 0 } ];
			const wrapper = await openOn( { hostPage: null } );

			await save( wrapper );

			expect( subjectStore.createSubject ).toHaveBeenCalled();
			expect( location.href ).toContain( CREATED_PAGE_SUBJECT_ID );
		} );
	} );
} );
