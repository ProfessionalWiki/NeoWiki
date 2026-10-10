import { enableAutoUnmount, mount, VueWrapper, DOMWrapper, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ref, nextTick } from 'vue';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import SchemaPicker from '@/components/common/SchemaPicker.vue';
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
import type { PinnedPage } from '@/composables/usePageSearch.ts';
import { WriteRefusedError } from '@/components/SubjectEditor/WriteRefusedError.ts';

const PAGE_ID = 123;
const PAGE_TITLE = 'Test Page';
// wgPageName carries the prefixed title with underscores, which is what names the "this page" shortcut.
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

// The focus and dialog-stacking tests read the document, where a dialog an earlier test left
// mounted would still be.
enableAutoUnmount( afterEach );

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

// What the panes inside the stubbed editor dialog hold, which is what its save hands back; the label
// is also what the editor reports while the Subject is being edited. Reset per test by the
// beforeEach below.
const editedLabel = ref<string | null>( null );
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

/** What the real summary field would hold when the action is used. */
let typedSummary = '';

const SummaryActionStub = {
	name: 'SummaryAction',
	template: '<div class="edit-summary-stub">' +
		'<slot name="action" :save="save">' +
		'<button class="save-button" @click="save">Save</button>' +
		'</slot></div>',
	props: [ 'helpText', 'footerText', 'saveButtonLabel', 'saveDisabled' ],
	emits: [ 'save' ],
	methods: {
		save( this: { $emit: ( event: string, summary: string ) => void } ): void {
			this.$emit( 'save', typedSummary );
		},
	},
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
		'<SummaryAction :save-disabled="saveDisabled" @save="runSave">' +
		'<template #action="{ save }">' +
		'<slot name="action" :save="save" :saving="saving" :disabled="saveDisabled || saving" />' +
		'</template>' +
		'</SummaryAction>' +
		'<button class="stub-close" @click="$emit( \'update:open\', false )">Close</button>' +
		'</div>',
	props: [ 'open', 'subject', 'schema', 'rootIsNew', 'saveDisabled', 'hostHasUnsavedChanges', 'onSave', 'onCreate', 'onSaveSchema', 'onSaved' ],
	emits: [ 'update:open' ],
	setup( props: Record<string, any> ) {
		const saving = ref( false );

		// As the real write loop does, an id the server already holds counts as a create that landed.
		async function create( subject: Subject, pageId: number, comment: string ): Promise<void> {
			try {
				await props.onCreate( subject, pageId, comment );
			} catch ( error ) {
				if ( !( error instanceof SubjectIdInUseError ) ) {
					throw error;
				}
			}
		}

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

				await create( editedRoot( props.subject as Subject ), 0, comment );

				for ( const draft of sessionDrafts ) {
					await create( draft.subject, draft.pageId, comment );
				}
			} catch ( error ) {
				lastSaveError = error;
				return;
			} finally {
				saving.value = false;
			}

			props.onSaved();
		}

		return { saving, runSave, rootLabel: editedLabel };
	},
};

// The Subject being created as its pane holds it: the one the creator handed down, under whatever
// has been typed into the pane since.
function editedRoot( subject: Subject ): Subject {
	return subject.withLabel( editedLabel.value ).withStatements( editedStatements() );
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

	// Every route that makes the new Subject a page's Main Subject creates it under the id minted
	// for it.
	function expectMainSubjectCreated( pageId: number, label: string | null, schemaName: string, comment: string ): void {
		expect( subjectStore.createMainSubject ).toHaveBeenCalledWith(
			pageId, label, schemaName, expect.any( StatementList ), comment, new SubjectId( MINTED_ID ),
		);
	}

	// Re-callable inside a test that needs another wiki configuration; the mount below reads it
	// lazily, so re-stubbing before mounting is enough.
	function stubMw( config: Record<string, unknown> = {} ): void {
		setupMwMock( {
			functions: [ 'msg', 'notify', 'config', 'storage', 'util' ],
			config: {
				wgArticleId: PAGE_ID,
				wgTitle: PAGE_TITLE,
				wgPageName: PAGE_NAME,
				// Debounce 0 is blur-only mode: the dry-run fires on blur / pre-save
				// (via flush()), which runs synchronously in tests.
				wgNeoWikiValidationDebounceMs: 0,
				...config,
			},
		} );
	}

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
					CdxMessage: true,
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
		// Codex's button is auto-stubbed as a <cdx-button-stub> element in most of this file, and
		// as one rendering its own content where the footer's words are asserted.
		await wrapper.find(
			'.ext-neowiki-subject-creator-continue cdx-button-stub, .ext-neowiki-subject-creator-continue .cdx-button-stub',
		).trigger( 'click' );
		await flushPromises();
	}

	/** Switching to a new Schema and giving it something, which is what unlocks Continue. */
	async function draftNewSchema( wrapper: VueWrapper ): Promise<void> {
		await switchToNewSchema( wrapper );
		wrapper.findComponent( SchemaCreator ).vm.$emit( 'change' );
		await flushPromises();
	}

	let reloadMock: ReturnType<typeof vi.fn>;

	beforeEach( () => {
		typedSummary = '';
		editedLabel.value = null;
		editedStatements = (): StatementList => new StatementList( [
			new Statement( new PropertyName( 'Color' ), TextType.typeName, newStringValue( 'Red' ) ),
		] );
		sessionDrafts = [];
		lastSaveError = null;
		beforeFirstWrite = Promise.resolve();
		schemaCreatorSaveBlocker = null;
		reloadMock = vi.fn();
		vi.stubGlobal( 'location', { href: '', reload: reloadMock } );

		stubMw();

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

	// Nobody has named it yet, so it carries what the server derives for a label-less Subject
	// (ADR 31): its Schema name, flagged as one nobody chose.
	it( 'hands the editor a Subject whose name nobody chose', async () => {
		const wrapper = mountComponent();

		await wrapper.findComponent( SchemaPicker ).vm.$emit( 'select', SCHEMA_NAME );
		await flushPromises();

		const subject = wrapper.findComponent( SubjectEditorDialog ).props( 'subject' ) as Subject;

		expect( subject.getLabel() ).toBeNull();
		expect( subject.getDisplayName() ).toBe( SCHEMA_NAME );
		expect( subject.hasGeneratedDisplayName() ).toBe( true );
	} );

	// A further Subject on the page is not the Main Subject, so it needs no label.
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

		editedLabel.value = 'Typed label';

		await wrapper.findComponent( { name: 'SummaryAction' } ).vm.$emit( 'save', 'test summary' );
		await flushPromises();

		expectMainSubjectCreated( PAGE_ID, 'Typed label', SCHEMA_NAME, 'test summary' );
	} );

	it( 'sends no label when the field was left empty', async () => {
		const wrapper = mountComponent();

		await wrapper.findComponent( SchemaPicker ).vm.$emit( 'select', SCHEMA_NAME );
		await flushPromises();

		await wrapper.findComponent( { name: 'SummaryAction' } ).vm.$emit( 'save', 'test summary' );
		await flushPromises();

		expectMainSubjectCreated( PAGE_ID, null, SCHEMA_NAME, 'test summary' );
	} );

	it( 'does not pass summary when it is empty', async () => {
		const wrapper = mountComponent();

		await wrapper.findComponent( SchemaPicker ).vm.$emit( 'select', SCHEMA_NAME );
		await flushPromises();

		await wrapper.findComponent( { name: 'SummaryAction' } ).vm.$emit( 'save', '' );
		await flushPromises();

		expectMainSubjectCreated( PAGE_ID, null, SCHEMA_NAME, DEFAULT_CREATE_SUMMARY );
	} );

	it( 'calls createOtherSubject when the page already has a main subject', async () => {
		const wrapper = mountComponent( {}, { hostPage: { hasMainSubject: true } } );

		await wrapper.findComponent( SchemaPicker ).vm.$emit( 'select', SCHEMA_NAME );
		await flushPromises();

		editedLabel.value = 'Typed label';

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
		const I18nSlotStub = {
			template: '<span>{{ messageKey }}<slot /></span>',
			props: [ 'messageKey' ],
		};

		const PagePickerPanelStub = {
			name: 'PagePickerPanel',
			template: '<div class="page-picker-panel-stub"></div>',
			props: [ 'pinnedPages', 'existingPagesOnly', 'newPageOnly', 'ariaLabel', 'error' ],
			emits: [ 'update:selected' ],
		};

		// Renders its slot, so what the page question complains about is assertable as text.
		const CdxMessageWithSlotStub = {
			name: 'CdxMessage',
			template: '<div class="cdx-message-stub"><slot /></div>',
			props: [ 'type', 'inline' ],
		};

		// Renders what it is given, so the footer button's own words are assertable.
		const CdxButtonWithSlotStub = {
			name: 'CdxButton',
			template: '<button class="cdx-button-stub" :disabled="disabled"><slot /></button>',
			props: [ 'action', 'weight', 'disabled' ],
		};

		function mountDialog(
			props: Record<string, any> = {},
			options: Record<string, any> = {},
		): VueWrapper {
			return mountComponent(
				{
					PagePickerPanel: PagePickerPanelStub,
					I18nSlot: I18nSlotStub,
					CdxMessage: CdxMessageWithSlotStub,
					CdxButton: CdxButtonWithSlotStub,
				},
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

		function destinationPanel( wrapper: VueWrapper ): VueWrapper<any> {
			return wrapper.findComponent( { name: 'PagePickerPanel' } ) as VueWrapper<any>;
		}

		/** The shortcuts the popover offers above its search, in the order it offers them. */
		function pinnedDestinations( wrapper: VueWrapper ): PinnedPage[] {
			return destinationPanel( wrapper ).props( 'pinnedPages' ) as PinnedPage[];
		}

		/** Which destination each shortcut is: the page being viewed, or a page to create. */
		function pinnedKinds( wrapper: VueWrapper ): string[] {
			return pinnedDestinations( wrapper ).map( ( pinned ) =>
				pinned.page.pageId === null ? 'newPage' : 'thisPage' );
		}

		/** The page each shortcut leads to, named as the results are. */
		function pinnedTitles( wrapper: VueWrapper ): string[] {
			return pinnedDestinations( wrapper ).map( ( pinned ) => pinned.label );
		}

		function pinnedNewPage( wrapper: VueWrapper ): PinnedPage | undefined {
			return pinnedDestinations( wrapper ).find( ( pinned ) => pinned.page.pageId === null );
		}

		/** Why the save was refused over the page to create, as the popover says it. */
		function statedRefusal( wrapper: VueWrapper ): string | null {
			return destinationPanel( wrapper ).props( 'error' ) as string | null;
		}

		/** The title the popover says a page of the Subject's own will be given. */
		function statedNewPageTitle( wrapper: VueWrapper ): string | undefined {
			return pinnedNewPage( wrapper )?.label;
		}

		/** The button that creates the Subject, whichever shape the footer has taken. */
		function createButton( wrapper: VueWrapper ): DOMWrapper<Element> {
			return wrapper.find( '.edit-summary-stub button, .edit-summary-stub cdx-button-stub' );
		}

		/** What that button says it will do, which is where the Subject is going. */
		function statedDestination( wrapper: VueWrapper ): string {
			return createButton( wrapper ).text();
		}

		function destinationToggle( wrapper: VueWrapper ): DOMWrapper<Element> {
			return wrapper.find( '.ext-neowiki-split-button__toggle' );
		}

		/** Whether the destination is the user's to change here at all. */
		function destinationIsOffered( wrapper: VueWrapper ): boolean {
			return destinationToggle( wrapper ).exists();
		}

		/** Opening the popover, which is when it reads the label to name the page it would create. */
		async function openDestination( wrapper: VueWrapper ): Promise<void> {
			await destinationToggle( wrapper ).trigger( 'click' );
			await flushPromises();
		}

		/** Answering the page question the way the popover answers it: by picking a destination. */
		async function pickPage( wrapper: VueWrapper, choice: unknown ): Promise<void> {
			destinationPanel( wrapper ).vm.$emit( 'update:selected', choice );
			await flushPromises();
		}

		/**
		 * The answers the dialog holds underneath, each reached the way the popover reaches it.
		 * Another page is reached by picking it.
		 */
		async function choose( wrapper: VueWrapper, value: 'thisPage' | 'newPage' ): Promise<void> {
			if ( value === 'thisPage' ) {
				await pickPage( wrapper, { pageId: PAGE_ID, title: PAGE_TITLE } );
			}

			if ( value === 'newPage' ) {
				await pickPage( wrapper, { pageId: null, title: '' } );
			}
		}

		async function typeLabel( _wrapper: VueWrapper, label: string ): Promise<void> {
			editedLabel.value = label;
			await flushPromises();
		}

		/** A title for the page to create, which the popover takes as the page to create. */
		async function typePageTitle( wrapper: VueWrapper, title: string ): Promise<void> {
			await pickPage( wrapper, { pageId: null, title } );
		}

		/**
		 * Saving the way a user saves: through the button the creator put in the footer, so that a
		 * button wired to nothing fails these rather than passing on an emit of their own.
		 */
		async function save( wrapper: VueWrapper, summary = '' ): Promise<void> {
			typedSummary = summary;
			await createButton( wrapper ).trigger( 'click' );
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

		describe( 'the popover the chevron opens', () => {
			function isOpen( wrapper: VueWrapper ): boolean {
				return destinationToggle( wrapper ).attributes( 'aria-expanded' ) === 'true';
			}

			it( 'opens on the chevron', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );

				await openDestination( wrapper );

				expect( isOpen( wrapper ) ).toBe( true );
			} );

			// The question is answered, so the panel has nothing left to ask.
			it( 'closes once a destination is picked', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );
				await openDestination( wrapper );

				await pickPage( wrapper, { pageId: OTHER_PAGE_ID, title: 'ACME Inc' } );

				expect( isOpen( wrapper ) ).toBe( false );
			} );

			// It stands over the footer, so a save that fails would report itself underneath it.
			it( 'closes when the Subject is created from under it', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );
				await openDestination( wrapper );

				await save( wrapper );

				expect( isOpen( wrapper ) ).toBe( false );
			} );

			/** Opening it is left a task, until the save has let go of the footer. */
			async function afterTheSaveLetsGo(): Promise<void> {
				await new Promise( ( resolve ) => {
					setTimeout( resolve );
				} );
				await flushPromises();
			}

			// It is where a title is given, so a save that wants one leads there.
			it( 'opens when a save is refused for want of a title', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );

				await save( wrapper );
				await afterTheSaveLetsGo();

				expect( isOpen( wrapper ) ).toBe( true );
			} );

			it( 'opens when a save is refused for a title already taken', async () => {
				( subjectStore.createSubjectPage as any ).mockRejectedValue( new PageTitleTakenError( 'Amsterdam' ) );
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await typeLabel( wrapper, 'Amsterdam' );

				await save( wrapper );
				await afterTheSaveLetsGo();

				expect( isOpen( wrapper ) ).toBe( true );
			} );

			// Said once, in the popover, since the popover stands over where the footer says it.
			it( 'says there why the save was refused', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );

				await save( wrapper );
				await afterTheSaveLetsGo();

				expect( statedRefusal( wrapper ) ).toBe( 'neowiki-subject-creator-page-title-required' );
				expect( wrapper.find( '.cdx-message-stub' ).exists() ).toBe( false );
			} );

			it( 'leaves why to the footer once it is closed', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await save( wrapper );
				await afterTheSaveLetsGo();

				await openDestination( wrapper );

				expect( wrapper.find( '.cdx-message-stub' ).text() )
					.toBe( 'neowiki-subject-creator-page-title-required' );
			} );
		} );

		describe( 'opened on a page', () => {
			// Any other page is reached by searching, which is the picker's whole job; what is
			// worth a shortcut past it is the page being viewed and a page of the Subject's own.
			it( 'offers that page and a new one as shortcuts past searching', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );

				expect( pinnedKinds( wrapper ) ).toEqual( [ 'thisPage', 'newPage' ] );
			} );

			/**
			 * The page a shortcut leads to is named where the results name theirs, so that one
			 * column of the list reads as page names throughout; which destination it is goes
			 * underneath.
			 */
			it( 'names the page each shortcut leads to where the results are named', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );
				await typeLabel( wrapper, 'Ada Lovelace' );
				await openDestination( wrapper );

				expect( pinnedTitles( wrapper ) ).toEqual( [ PAGE_TITLE, 'Ada Lovelace' ] );
			} );

			it( 'starts on that page', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );

				expect( statedDestination( wrapper ) )
					.toBe( 'neowiki-subject-creator-save-on-this-page' );
			} );

			it( 'states the page picked instead of it', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );

				await pickPage( wrapper, { pageId: OTHER_PAGE_ID, title: 'ACME Inc' } );

				expect( statedDestination( wrapper ) )
					.toBe( 'neowiki-subject-creator-save-on-pageACME Inc' );
			} );

			it( 'saves onto that page and reloads it', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );

				await save( wrapper, 'why' );

				expectMainSubjectCreated( PAGE_ID, null, SCHEMA_NAME, 'why' );
				expect( reloadMock ).toHaveBeenCalled();
			} );
		} );

		describe( 'opened without a page', () => {
			// There is no page being viewed to offer a shortcut to.
			it( 'offers a new page as its only shortcut', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );

				expect( pinnedKinds( wrapper ) ).toEqual( [ 'newPage' ] );
			} );

			it( 'starts on a new page', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );

				expect( statedDestination( wrapper ) )
					.toBe( 'neowiki-subject-creator-save-on-a-new-page' );
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

			// A page-first wiki is about pages, so the reader lands on the page the Subject went on
			// even where the dialog was opened on none.
			it( 'goes to the new page created for it', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await typeLabel( wrapper, 'New Person' );

				await save( wrapper );

				expect( location.href ).toBe( '/wiki/New Person' );
				expect( mw.storage.session.set )
					.toHaveBeenCalledWith( 'neowiki-subject-creator-success', '1' );
				expect( reloadMock ).not.toHaveBeenCalled();
			} );

			it( 'goes to the existing page it was saved onto', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				await save( wrapper );

				expect( location.href ).toBe( '/wiki/ACME Inc' );
				expect( mw.storage.session.set )
					.toHaveBeenCalledWith( 'neowiki-subject-creator-success', '1' );
			} );

			it( 'goes to the page it joined where that page has a main subject', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await pickPageWithMainSubject( wrapper );

				await save( wrapper );

				expect( location.href ).toBe( '/wiki/ACME Inc' );
			} );
		} );

		// On a subject-first wiki the Subject is the entity, so the dialog neither asks which page
		// it goes on nor leaves for one (ADR 33).
		describe( 'on a subject-first wiki', () => {

			function mountSubjectFirst( props: Record<string, any> = {} ): VueWrapper {
				stubMw( { wgNeoWikiSubjectFirst: true } );
				return mountDialog( props );
			}

			it( 'asks no page question', async () => {
				const wrapper = mountSubjectFirst();
				await pickSchema( wrapper );

				expect( destinationIsOffered( wrapper ) ).toBe( false );
			} );

			it( 'gives the Subject a page of its own rather than the page it was opened on', async () => {
				const wrapper = mountSubjectFirst();
				await pickSchema( wrapper );

				await save( wrapper, 'why' );

				expect( subjectStore.createSubjectPage ).toHaveBeenCalledWith(
					null, SCHEMA_NAME, expect.any( StatementList ), 'why', undefined, new SubjectId( MINTED_ID ),
				);
				expect( subjectStore.createMainSubject ).not.toHaveBeenCalled();
			} );

			// The wiki titles a Subject's own page by its id, so a caller asking which page
			// still leaves nothing to title.
			it( 'asks nothing of a caller that preselected a new page but named none', async () => {
				const wrapper = mountSubjectFirst( {
					initialPage: { choice: 'newPage', fixed: false } as InitialPage,
				} );
				await pickSchema( wrapper );

				expect( destinationIsOffered( wrapper ) ).toBe( false );
			} );

			// The wiki titles a page of the Subject's own, so there is no name to state.
			it( 'offers to create the Subject without naming a page', async () => {
				const wrapper = mountSubjectFirst();
				await pickSchema( wrapper );

				expect( statedDestination( wrapper ) ).toBe( 'neowiki-subject-creator-save' );
			} );

			// A caller that named a page is answering the question, so it is asked.
			it( 'asks where a caller named a page', async () => {
				const wrapper = mountSubjectFirst( {
					initialPage: { choice: 'anotherPage', page: { pageId: 13, title: 'ACME Inc' } } as InitialPage,
				} );
				await pickSchema( wrapper );

				expect( destinationIsOffered( wrapper ) ).toBe( true );
			} );

			it( 'sends no title for a new page where a caller asks which page', async () => {
				const wrapper = mountSubjectFirst( {
					initialPage: { choice: 'newPage', fixed: false } as InitialPage,
				} );
				await pickSchema( wrapper );
				await typeLabel( wrapper, 'Amsterdam' );

				await save( wrapper );

				expect( subjectStore.createSubjectPage ).toHaveBeenCalledWith(
					'Amsterdam', SCHEMA_NAME, expect.any( StatementList ), DEFAULT_CREATE_SUMMARY, undefined, new SubjectId( MINTED_ID ),
				);
			} );

			it( 'goes to the Subject itself, leaving no notice for a page to show', async () => {
				const wrapper = mountSubjectFirst();
				await pickSchema( wrapper );

				await save( wrapper );

				expect( location.href ).toBe( '/wiki/Special:Subject/' + CREATED_PAGE_SUBJECT_ID );
				expect( mw.storage.session.set )
					.not.toHaveBeenCalledWith( 'neowiki-subject-creator-success', '1' );
				expect( reloadMock ).not.toHaveBeenCalled();
			} );

			// The parser function names the page, and naming one is answering the question.
			it( 'states the page the caller named all the same', async () => {
				const wrapper = mountSubjectFirst( {
					initialPage: { choice: 'thisPage', fixed: true } as InitialPage,
				} );
				await pickSchema( wrapper );

				expect( statedDestination( wrapper ) )
					.toBe( 'neowiki-subject-creator-save-on-this-page' );
			} );

			// Nothing would come of hiding the question from someone who cannot be given a new page.
			it( 'asks the page question of a user who may not create pages', async () => {
				canCreateSubjectPage.value = false;
				const wrapper = mountSubjectFirst();
				await pickSchema( wrapper );

				expect( destinationIsOffered( wrapper ) ).toBe( true );
			} );

			it( 'saves the drafted Schema with the Subject\'s own page', async () => {
				const wrapper = mountSubjectFirst();
				await wrapper.setProps( { open: true } );
				await flushPromises();
				await draftNewSchema( wrapper );
				await clickContinue( wrapper );

				await save( wrapper );

				expect( schemaStore.saveSchema ).toHaveBeenCalledOnce();
				expect( subjectStore.createSubjectPage ).toHaveBeenCalledOnce();
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

			expect( destinationIsOffered( wrapper ) ).toBe( false );

			answer();
			await flushPromises();

			expect( destinationIsOffered( wrapper ) ).toBe( true );
			expect( statedDestination( wrapper ) )
				.toBe( 'neowiki-subject-creator-save-on-a-new-page' );
		} );

		describe( 'without the right to create pages', () => {
			beforeEach( () => {
				canCreateSubjectPage.value = false;
			} );

			it( 'disables a new page, saying why', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );
				await openDestination( wrapper );

				const newPage = pinnedNewPage( wrapper );

				expect( newPage?.disabled ).toBe( true );
				expect( newPage?.description ).toBe( 'neowiki-subject-creator-page-new-denied' );
			} );

			it( 'offers no page to create out of what is typed either', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );

				expect( destinationPanel( wrapper ).props( 'existingPagesOnly' ) ).toBe( true );
			} );

			/**
			 * With no page of its own and none to create, the answer is a page yet to be picked -
			 * so the create button waits on one while the picker that answers it stays reachable.
			 */
			it( 'waits for a page to be picked, keeping the picker reachable', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );

				expect( createButton( wrapper ).attributes( 'disabled' ) ).toBeDefined();
				expect( destinationToggle( wrapper ).attributes( 'disabled' ) ).toBeUndefined();
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
					'New Person', SCHEMA_NAME, expect.any( StatementList ), 'why', 'New Person', new SubjectId( MINTED_ID ),
				);
				expect( subjectStore.createMainSubject ).not.toHaveBeenCalled();
			} );

			// Here the page is the entity, so it is never titled after a Subject id: with nothing to
			// title it, the question comes back rather than a page nobody can read the name of.
			it( 'refuses a new page with nothing to title it after, writing nothing', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );

				await save( wrapper );

				expect( statedRefusal( wrapper ) ).toBe( 'neowiki-subject-creator-page-title-required' );
				expect( subjectStore.createSubjectPage ).not.toHaveBeenCalled();
			} );

			// The dialog says why where the title is given, so the editor need not say it again.
			it( 'stops the save as a refusal it reports itself', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );

				await save( wrapper );

				expect( lastSaveError ).toBeInstanceOf( WriteRefusedError );
			} );

			// Every row needs a name, so with no title to show the shortcut falls back to saying
			// which destination it is - and promises nothing the save would refuse.
			it( 'promises no title where there is no label to take one from', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );

				await openDestination( wrapper );

				expect( statedNewPageTitle( wrapper ) ).toBe( 'neowiki-subject-creator-page-new' );
			} );

			it( 'says nothing under a shortcut named by what it is', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );

				await openDestination( wrapper );

				expect( pinnedNewPage( wrapper )?.description ).toBeUndefined();
			} );

			it( 'saves the drafted Schema before the new page that uses it', async () => {
				const wrapper = mountWithoutHostPage();
				await wrapper.setProps( { open: true } );
				await flushPromises();
				await draftNewSchema( wrapper );
				await clickContinue( wrapper );
				await typeLabel( wrapper, 'New Person' );

				await save( wrapper );

				const schemaSaved = ( schemaStore.saveSchema as ReturnType<typeof vi.fn> ).mock.invocationCallOrder[ 0 ];
				const pageCreated = ( subjectStore.createSubjectPage as ReturnType<typeof vi.fn> ).mock.invocationCallOrder[ 0 ];
				expect( schemaSaved ).toBeLessThan( pageCreated );
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

			// The server normalizes the title it is sent, so the page may not carry it verbatim.
			it( 'navigates to the title the server reported, not the one sent', async () => {
				( subjectStore.createSubjectPage as any ).mockResolvedValue( {
					subjectId: new SubjectId( 's11111111111113' ),
					pageTitle: 'Delft Blue',
				} );
				const wrapper = mountDialog();
				await chooseNewPage( wrapper );
				await typeLabel( wrapper, 'delft Blue' );

				await save( wrapper );

				expect( location.href ).toBe( '/wiki/Delft Blue' );
			} );

			it( 'reports a title already taken at the page choice', async () => {
				( subjectStore.createSubjectPage as any ).mockRejectedValue( new PageTitleTakenError( 'Amsterdam' ) );
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await typeLabel( wrapper, 'Amsterdam' );

				await save( wrapper );

				expect( statedRefusal( wrapper ) ).toBe( 'neowiki-subject-creator-page-takenAmsterdam' );
				expect( location.href ).toBe( '' );
			} );

			it( 'leaves save reachable after a title already taken, so another label can answer it', async () => {
				( subjectStore.createSubjectPage as any ).mockRejectedValueOnce( new PageTitleTakenError( 'Amsterdam' ) );
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await typeLabel( wrapper, 'Amsterdam' );
				await save( wrapper );

				expect( statedRefusal( wrapper ) ).toBe( 'neowiki-subject-creator-page-takenAmsterdam' );
				expect( wrapper.findComponent( { name: 'SummaryAction' } ).props( 'saveDisabled' ) ).toBe( false );
			} );

		} );

		describe( 'the title of the page to create', () => {
			it( 'is stated where a page is to be created', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );

				await typePageTitle( wrapper, 'Delft' );

				expect( statedDestination( wrapper ) )
					.toBe( 'neowiki-subject-creator-save-on-new-pageDelft' );
			} );

			// A page that exists is joined under the name it already has.
			it( 'is not stated where the page already exists', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );

				await pickPage( wrapper, { pageId: OTHER_PAGE_ID, title: 'ACME Inc' } );

				expect( statedDestination( wrapper ) )
					.toBe( 'neowiki-subject-creator-save-on-pageACME Inc' );
			} );

			it( 'is filled in from the label', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );

				await typeLabel( wrapper, 'Delft Blue' );
				await openDestination( wrapper );

				expect( statedNewPageTitle( wrapper ) ).toBe( 'Delft Blue' );
			} );

			it( 'keeps a title asked for after the label filled it in', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await typeLabel( wrapper, 'Delft Blue' );
				await typePageTitle( wrapper, 'Delft' );

				await typeLabel( wrapper, 'Delft Blue pottery' );

				expect( statedDestination( wrapper ) )
					.toBe( 'neowiki-subject-creator-save-on-new-pageDelft' );
			} );

			// Picking the shortcut titles the page after the label, so that is what it is named after.
			it( 'names the shortcut after the label rather than a title asked for since', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await typeLabel( wrapper, 'Delft Blue' );
				await typePageTitle( wrapper, 'Delft' );

				await openDestination( wrapper );

				expect( statedNewPageTitle( wrapper ) ).toBe( 'Delft Blue' );
			} );

			// Picking the shortcut is asking for no title of its own, which hands the naming back.
			it( 'follows the label again once the shortcut is picked', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await typeLabel( wrapper, 'Delft Blue' );
				await typePageTitle( wrapper, 'Delft' );

				await choose( wrapper, 'newPage' );

				expect( statedDestination( wrapper ) )
					.toBe( 'neowiki-subject-creator-save-on-new-pageDelft Blue' );
			} );

			it( 'follows the label again once the dialog is reopened', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await typePageTitle( wrapper, 'Delft' );
				await wrapper.setProps( { open: false } );
				await pickSchema( wrapper );

				await typeLabel( wrapper, 'Amsterdam' );

				expect( statedDestination( wrapper ) )
					.toBe( 'neowiki-subject-creator-save-on-new-pageAmsterdam' );
			} );

			it( 'titles the page created', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await typeLabel( wrapper, 'Delft Blue' );
				await typePageTitle( wrapper, 'Delft' );

				await save( wrapper, 'why' );

				expect( subjectStore.createSubjectPage ).toHaveBeenCalledWith(
					'Delft Blue', SCHEMA_NAME, expect.any( StatementList ), 'why', 'Delft', new SubjectId( MINTED_ID ),
				);
			} );

			it( 'titles the page after the label where none was asked for', async () => {
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await typeLabel( wrapper, 'Delft Blue' );

				await save( wrapper );

				expect( subjectStore.createSubjectPage ).toHaveBeenCalledWith(
					'Delft Blue', SCHEMA_NAME, expect.any( StatementList ), DEFAULT_CREATE_SUMMARY, 'Delft Blue', new SubjectId( MINTED_ID ),
				);
			} );
		} );

		describe( 'a page the server refused', () => {
			it( 'complains about a title already taken', async () => {
				( subjectStore.createSubjectPage as any ).mockRejectedValue( new PageTitleTakenError( 'Amsterdam' ) );
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await typePageTitle( wrapper, 'Amsterdam' );

				await save( wrapper );

				expect( statedRefusal( wrapper ) ).toBe( 'neowiki-subject-creator-page-takenAmsterdam' );
			} );

			it( 'complains about a title that titles no page', async () => {
				( subjectStore.createSubjectPage as any )
					.mockRejectedValue( new InvalidPageTitleError( 'Help:Amsterdam' ) );
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );
				await typePageTitle( wrapper, 'Help:Amsterdam' );

				await save( wrapper );

				expect( statedRefusal( wrapper ) ).toBe( 'neowiki-subject-creator-page-title-invalidHelp:Amsterdam' );
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

				expect( statedRefusal( wrapper ) ).toBeNull();
				expect( wrapper.findComponent( { name: 'SummaryAction' } ).props( 'saveDisabled' ) ).toBe( false );
			} );

			it( 'leaves the picker reachable on a page whose subjects could not be read', async () => {
				getPageSubjectsMock.mockRejectedValue( new Error( 'Graph store unavailable' ) );
				const wrapper = mountDialog();
				await pickSchema( wrapper );

				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				expect( wrapper.find( '.cdx-message-stub' ).text() )
					.toContain( 'neowiki-subject-creator-page-read-errorACME Inc' );
				expect( destinationToggle( wrapper ).attributes( 'disabled' ) ).toBeUndefined();
				expect( createButton( wrapper ).attributes( 'disabled' ) ).toBeDefined();
			} );
		} );

		describe( 'on another page', () => {
			it( 'keeps save unavailable until a page is picked', async () => {
				canCreateSubjectPage.value = false;
				const wrapper = mountWithoutHostPage();
				await pickSchema( wrapper );

				expect( wrapper.findComponent( { name: 'SummaryAction' } ).props( 'saveDisabled' ) ).toBe( true );

				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				expect( wrapper.findComponent( { name: 'SummaryAction' } ).props( 'saveDisabled' ) ).toBe( false );
			} );

			it( 'makes the subject the main subject of a page that has none, and goes there', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );
				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				await save( wrapper );

				expectMainSubjectCreated( EXISTING_PAGE_ID, null, SCHEMA_NAME, DEFAULT_CREATE_SUMMARY );
				expect( location.href ).toBe( '/wiki/ACME Inc' );
				expect( mw.storage.session.set ).toHaveBeenCalledWith( 'neowiki-subject-creator-success', '1' );
			} );

			it( 'adds the subject alongside an existing main subject, under the label given', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );
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
				await pickSchema( wrapper );

				await pickPageWithMainSubject( wrapper );

				expect( wrapper.text() ).toContain( 'neowiki-subject-creator-page-has-main-subject' );
				expect( wrapper.text() ).toContain( 'ACME Inc' );
				expect( wrapper.text() ).not.toContain( 'neowiki-subject-creator-page-picked' );
			} );

			it( 'says the subject joins the page picked, which has no main subject to name it', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );

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
				await pickSchema( wrapper );

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
				await pickSchema( wrapper );

				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				expect( wrapper.text() ).not.toContain( 'neowiki-subject-creator-page-picked' );
			} );

			/**
			 * Another title answers a title already taken; a page that would not read is still
			 * unread whatever else is typed, and saving onto it would guess at where the Subject goes.
			 */
			it( 'keeps a page that could not be read blocking while the rest of the form is edited', async () => {
				getPageSubjectsMock.mockRejectedValue( new Error( 'Graph store unavailable' ) );
				const wrapper = mountDialog();
				await pickSchema( wrapper );
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
				await pickSchema( wrapper );
				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				const saved = save( wrapper );
				await flushPromises();

				expect( destinationToggle( wrapper ).attributes( 'disabled' ) ).toBeDefined();

				flush.resolve();
				await saved;
				await flushPromises();

				expect( destinationToggle( wrapper ).attributes( 'disabled' ) ).toBeUndefined();
			} );

			// The freeze is the first guard; this is the second. The write's own awaits — the draft
			// Schema's save above all — sit past it, so the whole answer is read once rather than
			// again on the far side of each one: the page the write lands on, and the page the
			// dialog then says it landed on, cannot come apart.
			it( 'creates and reports the page that was answered when the write began', async () => {
				const schemaSave = deferred<void>();
				( schemaStore.saveSchema as any ).mockReturnValue( schemaSave.promise );
				const wrapper = mountDialog();
				await draftNewSchema( wrapper );
				await clickContinue( wrapper );
				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				const saved = save( wrapper );
				await flushPromises();
				await pickPage( wrapper, { pageId: OTHER_PAGE_ID, title: 'Other Page' } );
				schemaSave.resolve();
				await saved;
				await flushPromises();

				expectMainSubjectCreated( EXISTING_PAGE_ID, null, NEW_SCHEMA_NAME, DEFAULT_CREATE_SUMMARY );
				expect( location.href ).toContain( 'ACME Inc' );
				expect( reloadMock ).not.toHaveBeenCalled();
			} );

			it( 'blocks saving when the chosen page could not be read', async () => {
				getPageSubjectsMock.mockRejectedValue( new Error( 'Graph store unavailable' ) );
				const wrapper = mountDialog();
				await pickSchema( wrapper );
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
				await pickSchema( wrapper );
				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				await pickPage( wrapper, { pageId: OTHER_PAGE_ID, title: 'Other Page' } );
				slowRead.resolve( pageWithMainSubject( 'ACME Inc' ) );
				await flushPromises();

				expect( wrapper.text() ).not.toContain( 'ACME Inc' );

				await save( wrapper );

				expectMainSubjectCreated( OTHER_PAGE_ID, null, SCHEMA_NAME, DEFAULT_CREATE_SUMMARY );
			} );

			it( 'drops the page picked once the choice moves back to a new page', async () => {
				const wrapper = mountDialog();
				await pickSchema( wrapper );
				await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

				await choose( wrapper, 'newPage' );
				await typeLabel( wrapper, 'New Person' );
				await save( wrapper );

				expect( subjectStore.createSubjectPage ).toHaveBeenCalled();
				expect( subjectStore.createMainSubject ).not.toHaveBeenCalled();
			} );
		} );

		function closeWouldDiscard( wrapper: VueWrapper ): boolean {
			return wrapper.findComponent( SubjectEditorDialog ).props( 'hostHasUnsavedChanges' ) as boolean;
		}

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

		// What fills it in is the Subject's label, an edit the editor reports itself.
		it( 'reports nothing to discard for a title filled in from the label', async () => {
			const wrapper = mountWithoutHostPage( { initialSchemaName: SCHEMA_NAME } );
			await wrapper.setProps( { open: true } );
			await flushPromises();

			await typeLabel( wrapper, 'Delft' );

			expect( closeWouldDiscard( wrapper ) ).toBe( false );
		} );

		it( 'reports a page still picked as something a close would discard', async () => {
			const wrapper = mountWithoutHostPage( { initialSchemaName: SCHEMA_NAME } );
			await wrapper.setProps( { open: true } );
			await flushPromises();

			await pickPage( wrapper, { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' } );

			expect( closeWouldDiscard( wrapper ) ).toBe( true );
		} );

		describe( 'initialPage', () => {
			function mountWithInitialPage( initialPage: InitialPage, props: Record<string, any> = {} ): VueWrapper {
				return mountDialog( { initialSchemaName: SCHEMA_NAME, initialPage, ...props } );
			}

			async function open( wrapper: VueWrapper ): Promise<void> {
				await wrapper.setProps( { open: true } );
				await flushPromises();
				// Second flush: the page is filled in after the choice watcher runs.
				await flushPromises();
				await wrapper.vm.$nextTick();
			}

			it( 'states a fixed this page', async () => {
				const wrapper = mountWithInitialPage( { choice: 'thisPage', fixed: true } );
				await open( wrapper );

				expect( statedDestination( wrapper ) )
					.toBe( 'neowiki-subject-creator-save-on-this-page' );
			} );

			it( 'offers no way to change a fixed page', async () => {
				const wrapper = mountWithInitialPage( { choice: 'thisPage', fixed: true } );
				await open( wrapper );

				expect( destinationIsOffered( wrapper ) ).toBe( false );
			} );

			it( 'states the title a fixed new page was given', async () => {
				const wrapper = mountWithInitialPage( {
					choice: 'newPage',
					page: { pageId: null, title: 'Ada Lovelace' },
					fixed: true,
				} );
				await open( wrapper );

				expect( statedDestination( wrapper ) )
					.toBe( 'neowiki-subject-creator-save-on-new-pageAda Lovelace' );
			} );

			it( 'refuses a fixed new page with nothing to title it after', async () => {
				const wrapper = mountWithInitialPage( { choice: 'newPage', fixed: true } );
				await open( wrapper );

				await save( wrapper );

				expect( statedRefusal( wrapper ) ).toBe( 'neowiki-subject-creator-page-title-required' );
				expect( subjectStore.createSubjectPage ).not.toHaveBeenCalled();
			} );

			/**
			 * A caller that fixed a page of the Subject's own fixed where the Subject goes, not what
			 * the page is called, so the popover opens on the title alone: no shortcut to another
			 * destination, and no page searched for, since picking one would unfix what was fixed.
			 */
			describe( 'a fixed new page', () => {
				function mountFixedNewPage(): VueWrapper {
					return mountWithInitialPage( { choice: 'newPage', fixed: true } );
				}

				it( 'offers the title to be given', async () => {
					const wrapper = mountFixedNewPage();
					await open( wrapper );

					expect( destinationIsOffered( wrapper ) ).toBe( true );
				} );

				it( 'names that as what the chevron changes', async () => {
					const wrapper = mountFixedNewPage();
					await open( wrapper );

					expect( destinationToggle( wrapper ).attributes( 'aria-label' ) )
						.toBe( 'neowiki-subject-creator-page-retitle' );
				} );

				it( 'offers no other destination in it', async () => {
					const wrapper = mountFixedNewPage();
					await open( wrapper );

					expect( pinnedDestinations( wrapper ) ).toEqual( [] );
					expect( destinationPanel( wrapper ).props( 'newPageOnly' ) ).toBe( true );
				} );

				// Choosing the page that has the title is no answer where only the title may change.
				it( 'asks for another title where the one given is taken', async () => {
					( subjectStore.createSubjectPage as any ).mockRejectedValue( new PageTitleTakenError( 'Ada Lovelace' ) );
					const wrapper = mountFixedNewPage();
					await open( wrapper );
					await typeLabel( wrapper, 'Ada Lovelace' );

					await save( wrapper );

					expect( statedRefusal( wrapper ) ).toBe( 'neowiki-subject-creator-page-taken-retitleAda Lovelace' );
				} );

				it( 'takes the title given there', async () => {
					const wrapper = mountFixedNewPage();
					await open( wrapper );

					await typePageTitle( wrapper, 'Ada Lovelace' );
					await save( wrapper );

					expect( subjectStore.createSubjectPage ).toHaveBeenCalledWith(
						null, SCHEMA_NAME, expect.any( StatementList ), DEFAULT_CREATE_SUMMARY,
						'Ada Lovelace', new SubjectId( MINTED_ID ),
					);
				} );
			} );

			it( 'saves onto a fixed page without a main Subject as its main Subject', async () => {
				const wrapper = mountWithInitialPage( {
					choice: 'anotherPage',
					page: { pageId: EXISTING_PAGE_ID, title: 'ACME Inc' },
					fixed: true,
				} );
				await open( wrapper );

				expect( statedDestination( wrapper ) )
					.toBe( 'neowiki-subject-creator-save-on-pageACME Inc' );

				await save( wrapper );

				expectMainSubjectCreated( EXISTING_PAGE_ID, null, SCHEMA_NAME, DEFAULT_CREATE_SUMMARY );
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
				expect( statedDestination( wrapper ) )
					.toBe( 'neowiki-subject-creator-save-on-pageACME Inc' );
				expect( wrapper.findComponent( SubjectEditorDialog ).props( 'hostHasUnsavedChanges' ) ).toBe( false );

				await requestClose( wrapper );

				expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
			} );

			it( 'closes without confirming when only the fixed new page\'s title was given', async () => {
				const wrapper = mountWithInitialPage( {
					choice: 'newPage',
					page: { pageId: null, title: 'Ada Lovelace' },
					fixed: true,
				} );
				await open( wrapper );

				expect( closeWouldDiscard( wrapper ) ).toBe( false );
			} );

			it( 'preselects an unfixed page but still offers the choice', async () => {
				const wrapper = mountWithInitialPage( { choice: 'newPage', fixed: false } );
				await open( wrapper );

				expect( statedDestination( wrapper ) )
					.toBe( 'neowiki-subject-creator-save-on-a-new-page' );
				expect( destinationIsOffered( wrapper ) ).toBe( true );
				expect( pinnedKinds( wrapper ) ).toEqual( [ 'thisPage', 'newPage' ] );
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

				expect( wrapper.find( '.cdx-message-stub' ).text() )
					.toContain( 'neowiki-subject-creator-page-takenAda Lovelace' );
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

			expectMainSubjectCreated( PAGE_ID, null, NEW_SCHEMA_NAME, 'Created subject' );
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

		function mountWithCodexDialogs(): VueWrapper {
			return mountComponent( {
				CdxDialog: false,
				CloseConfirmationDialog: false,
				SchemaAbandonmentDialog: false,
				SubjectEditorDialog: SubjectEditorDialogWithCodexDialog,
				teleport: false,
			} );
		}

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
			// The routes below that make a page of their own need something to title it with.
			editedLabel.value = 'New Person';
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

		// A second pass over a root the first one created would be refused: its id, and any page
		// title it was given, are taken now.
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

		it( 'writes them on a subject-first wiki even when the answer to its own create was lost', async () => {
			stubMw( { wgNeoWikiSubjectFirst: true } );
			( subjectStore.createSubjectPage as any ).mockRejectedValueOnce( new SubjectIdInUseError( MINTED_ID ) );
			sessionDrafts = [ { subject: draft(), pageId: 0 } ];
			const wrapper = await openOn();

			await save( wrapper );

			expect( lastSaveError ).toBeNull();
			expect( subjectStore.createSubject ).toHaveBeenCalledOnce();
		} );

		// The whole save is through before anyone leaves the page: a Subject created alongside is
		// written after the one that points at it, so navigating on that first write would take
		// the rest of the save with it.
		it( 'leaves only once every write is through', async () => {
			sessionDrafts = [ { subject: draft(), pageId: 0 } ];
			const wrapper = await openOn( { hostPage: null } );

			await save( wrapper );

			expect( subjectStore.createSubject ).toHaveBeenCalled();
			expect( location.href ).toBe( '/wiki/New Person' );
		} );
	} );
} );
