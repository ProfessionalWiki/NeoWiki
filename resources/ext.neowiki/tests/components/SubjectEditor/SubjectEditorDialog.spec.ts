import { mount, VueWrapper, DOMWrapper, flushPromises } from '@vue/test-utils';
import { nextTick } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi, type Mock } from 'vitest';
import SubjectEditorDialog from '@/components/SubjectEditor/SubjectEditorDialog.vue';
import { Subject } from '@/domain/Subject.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { Statement } from '@/domain/Statement.ts';
import { PropertyName } from '@/domain/PropertyDefinition.ts';
import { newRelation, RelationValue } from '@/domain/Value.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { createPropertyDefinitionFromJson } from '@/domain/PropertyDefinition.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import { createPinia, setActivePinia } from 'pinia';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { Service } from '@/NeoWikiServices.ts';
import { NeoWikiTestServices } from '../../NeoWikiTestServices.ts';
import SchemaEditorDialog from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import SubjectEditPane from '@/components/SubjectEditor/SubjectEditPane.vue';
import SubjectTree from '@/components/SubjectEditor/SubjectTree.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import { CdxDialog, CdxMessage } from '@wikimedia/codex';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { ValidationFailedError } from '@/persistence/ValidationFailedError';
import type { SubjectViolation } from '@/domain/SubjectViolation';
import type { UnparseableInput } from '@/components/common/UnparseableInput.ts';
import { newSubject } from '@/TestHelpers.ts';
import { PageIdentifiers } from '@/domain/PageIdentifiers.ts';
import type { SubjectWithContext } from '@/domain/SubjectWithContext.ts';

const $i18n = createI18nMock();

// What the stubbed editor reports about fields holding text it cannot turn into
// a value. Reset per test by the beforeEach below.
let editorUnparseableInput: UnparseableInput | null = null;
// Keyed by Schema name, so a multi-pane test can make one pane's field unreadable while
// the others stay saveable.
let editorUnparseableInputBySchema: Record<string, UnparseableInput | null> = {};
// What the stubbed editor reports its fields hold, keyed by Schema name, so a test can make
// one pane's form yield a real relation. Empty means no values at all.
let editorStatementsBySchema: Record<string, Statement[]> = {};

const SubjectEditorStub = {
	template: '<div class="subject-editor-stub"></div>',
	props: [ 'statements', 'schema', 'serverViolations' ],
	emits: [ 'change', 'relation-change', 'clear-server-violation' ],
	setup( props: { schema?: Schema } ) {
		const getSubjectData = (): StatementList => new StatementList(
			editorStatementsBySchema[ props.schema?.getName() ?? '' ] ?? [],
		);
		const unparseableInput = (): UnparseableInput | null => {
			const schemaName = props.schema?.getName();
			if ( schemaName !== undefined && schemaName in editorUnparseableInputBySchema ) {
				return editorUnparseableInputBySchema[ schemaName ];
			}
			return editorUnparseableInput;
		};
		return { getSubjectData, unparseableInput };
	},
};

const SummaryActionStub = {
	template: '<div class="edit-summary-stub"></div>',
	props: [ 'helpText', 'footerText', 'saveButtonLabel', 'saveDisabled' ],
	emits: [ 'save' ],
};

const CloseConfirmationDialogStub = {
	template: '<div class="close-confirmation-stub"></div>',
	props: [ 'open' ],
	emits: [ 'discard', 'keep-editing' ],
};

describe( 'SubjectEditorDialog', () => {
	beforeEach( () => {
		editorUnparseableInput = null;
		editorUnparseableInputBySchema = {};
		editorStatementsBySchema = {};
		setupMwMock( {
			// 'util' for the relation fields and a nested pane's storage line: both call mw.util.getUrl.
			functions: [ 'message', 'msg', 'notify', 'config', 'util' ],
			// The one message the navigator renders as text of its own.
			messages: { 'neowiki-subject-tree-not-linked': 'Not linked here' },
			// Debounce 0 is blur-only mode: the dry-run fires on blur / pre-save
			// (via flush()), which runs synchronously in tests.
			config: { wgNeoWikiValidationDebounceMs: 0, wgArticleId: 42 },
		} );
	} );

	let pinia: ReturnType<typeof createPinia>;
	let schemaPermissionHints: any;

	const mockSchema = new Schema(
		'TestSchema',
		'A test schema',
		new PropertyDefinitionList( [] ),
	);

	const mockSubject = new Subject(
		new SubjectId( 's1demo5sssssss1' ),
		'Test Subject',
		'Test Subject',
		'TestSchema',
		new StatementList( [] ),
	);

	const labellessSubject = new Subject(
		new SubjectId( 's1demo5sssssss2' ),
		null,
		'Host Page',
		'TestSchema',
		new StatementList( [] ),
	);

	const rootSubjectId = mockSubject.getId().text;

	const mountComponent = (
		canEditSchema: boolean,
		stubs: Record<string, any>,
		onSave?: ( subject: any, comment: string ) => Promise<void>,
		schema: Schema = mockSchema,
		provide: Record<string, unknown> = {},
		subject: Subject = mockSubject,
		// Passed by the focus tests alone: an element must be in the document to hold focus.
		attachTo: Element | undefined = undefined,
	): VueWrapper => {
		schemaPermissionHints = {
			canEditSchema: vi.fn().mockResolvedValue( canEditSchema ),
		};

		return mount( SubjectEditorDialog, {
			attachTo,
			props: {
				subject,
				schema,
				onSave: onSave ?? vi.fn(),
				onSaveSchema: vi.fn(),
				open: true,
			},
			global: {
				mocks: {
					$i18n,
				},
				plugins: [ pinia ],
				provide: {
					...NeoWikiTestServices.getServices(),
					[ Service.ComponentRegistry ]: NeoWikiExtension.getInstance().getTypeSpecificComponentRegistry(),
					[ Service.SchemaPermissionHints ]: schemaPermissionHints,
					[ Service.PropertyTypeRegistry ]: NeoWikiExtension.getInstance().getPropertyTypeRegistry(),
					...provide,
				},
				stubs: {
					teleport: true,
					...stubs,
				},
			},
		} );
	};

	beforeEach( () => {
		pinia = createPinia();
		setActivePinia( pinia );

		// The dry-run validation runs alongside the live validators; stub it so
		// it does not reach the network and stays out of the way of these tests.
		useSubjectStore().validateSubjectUpdate = vi.fn().mockResolvedValue( [] );
	} );

	function schemaWithProperty( propertyName: string ): Schema {
		return new Schema(
			'TestSchema',
			'A test schema',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( propertyName, { type: TextType.typeName } ),
			] ),
		);
	}

	function editedPropertyNames( wrapper: VueWrapper ): string[] {
		const statements = wrapper.findComponent( SubjectEditor ).props( 'statements' ) as StatementList;
		return [ ...statements ].map( ( s ) => s.propertyName.toString() );
	}

	it( 'materialises one editable statement per property of the schema prop', async () => {
		const wrapper = mountComponent(
			false, { SubjectEditor: SubjectEditorStub }, undefined, schemaWithProperty( 'name' ),
		);
		await flushPromises();

		expect( editedPropertyNames( wrapper ) ).toEqual( [ 'name' ] );
	} );

	it( 'follows the schema prop when the host replaces it', async () => {
		const wrapper = mountComponent(
			false, { SubjectEditor: SubjectEditorStub }, undefined, schemaWithProperty( 'name' ),
		);
		await flushPromises();

		await wrapper.setProps( { schema: schemaWithProperty( 'nickname' ) } );

		expect( editedPropertyNames( wrapper ) ).toEqual( [ 'nickname' ] );
	} );

	it( 'follows a schema saved through the nested schema editor', async () => {
		const wrapper = mountComponent(
			false, { SubjectEditor: SubjectEditorStub, SchemaEditorDialog: true }, undefined,
			schemaWithProperty( 'name' ),
		);
		await flushPromises();

		wrapper.findComponent( SchemaEditorDialog ).vm.$emit( 'saved', schemaWithProperty( 'nickname' ) );
		await flushPromises();

		expect( editedPropertyNames( wrapper ) ).toEqual( [ 'nickname' ] );
	} );

	it( 'renders schema as a link when user has edit permissions', async () => {
		const wrapper = mountComponent( true, {} );
		await flushPromises();

		const schemaLink = wrapper.find( '.ext-neowiki-subject-editor-dialog-schema__link' );
		expect( schemaLink.exists() ).toBe( true );
		expect( schemaLink.text() ).toBe( 'TestSchema' );
	} );

	it( 'renders schema as plain text when user lacks edit permissions', async () => {
		const wrapper = mountComponent( false, {} );
		await flushPromises();

		const schemaLink = wrapper.find( '.ext-neowiki-subject-editor-dialog-schema__link' );
		expect( schemaLink.exists() ).toBe( false );

		const schemaName = wrapper.find( '.ext-neowiki-subject-editor-dialog-schema__name' );
		expect( schemaName.exists() ).toBe( true );
		expect( schemaName.text() ).toBe( 'TestSchema' );
	} );

	it( 'opens SchemaEditorDialog when schema link is clicked', async () => {
		const wrapper = mountComponent( true, {} );
		await flushPromises();

		const schemaLink = wrapper.find( 'a.ext-neowiki-subject-editor-dialog-schema__link' );
		await schemaLink.trigger( 'click' );

		const schemaEditorDialog = wrapper.findComponent( SchemaEditorDialog );
		expect( schemaEditorDialog.exists() ).toBe( true );
		expect( schemaEditorDialog.props( 'open' ) ).toBe( true );
	} );

	const saveButtonTestStubs = {
		SubjectEditor: SubjectEditorStub,
		SchemaEditorDialog: true,
		SummaryAction: SummaryActionStub,
	};

	describe( 'Save button', () => {
		it( 'disables save when there are no changes', async () => {
			const wrapper = mountComponent( true, saveButtonTestStubs );
			await flushPromises();

			expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( true );
		} );

		it( 'enables save after a change is made', async () => {
			const wrapper = mountComponent( true, saveButtonTestStubs );
			await flushPromises();

			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );

			expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( false );
		} );

		it( 'disables save again when dialog reopens', async () => {
			const wrapper = mountComponent( true, saveButtonTestStubs );
			await flushPromises();

			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( false );

			await wrapper.setProps( { open: false } );
			await wrapper.setProps( { open: true } );

			expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( true );
		} );

		it( 're-enables save after a change following a reopen, proving the pane re-registers', async () => {
			const wrapper = mountComponent( true, saveButtonTestStubs );
			await flushPromises();

			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( false );

			await wrapper.setProps( { open: false } );
			await wrapper.setProps( { open: true } );
			expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( true );

			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );

			expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( false );
			expect( ( wrapper.vm as any ).hasChanged ).toBe( true );
		} );
	} );

	it( 'has hasChanged false initially', async () => {
		const wrapper = mountComponent( true, { SubjectEditor: SubjectEditorStub } );
		await flushPromises();

		expect( ( wrapper.vm as any ).hasChanged ).toBe( false );
	} );

	it( 'has hasChanged true after SubjectEditor emits change', async () => {
		const wrapper = mountComponent( true, { SubjectEditor: SubjectEditorStub } );
		await flushPromises();

		const subjectEditor = wrapper.findComponent( SubjectEditor );
		await subjectEditor.vm.$emit( 'change' );

		expect( ( wrapper.vm as any ).hasChanged ).toBe( true );
	} );

	it( 'resets hasChanged when dialog reopens', async () => {
		const wrapper = mountComponent( true, { SubjectEditor: SubjectEditorStub } );
		await flushPromises();

		const subjectEditor = wrapper.findComponent( SubjectEditor );
		await subjectEditor.vm.$emit( 'change' );
		expect( ( wrapper.vm as any ).hasChanged ).toBe( true );

		await wrapper.setProps( { open: false } );
		await wrapper.setProps( { open: true } );

		expect( ( wrapper.vm as any ).hasChanged ).toBe( false );
	} );

	const confirmationTestStubs = {
		SubjectEditor: SubjectEditorStub,
		SchemaEditorDialog: true,
		CloseConfirmationDialog: CloseConfirmationDialogStub,
	};

	it( 'shows confirmation dialog when closing with unsaved changes', async () => {
		const wrapper = mountComponent( true, confirmationTestStubs );
		await flushPromises();

		await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
		wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
		await flushPromises();

		expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
		expect( wrapper.findComponent( CloseConfirmationDialog ).props( 'open' ) ).toBe( true );
	} );

	it( 'closes without confirmation when there are no unsaved changes', async () => {
		const wrapper = mountComponent( true, confirmationTestStubs );
		await flushPromises();

		wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
		await flushPromises();

		expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
	} );

	it( 'closes dialog when discard is clicked in confirmation', async () => {
		const wrapper = mountComponent( true, confirmationTestStubs );
		await flushPromises();

		await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
		wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
		await flushPromises();

		wrapper.findComponent( CloseConfirmationDialog ).vm.$emit( 'discard' );
		await flushPromises();

		expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
	} );

	it( 'keeps dialog open when keep-editing is clicked in confirmation', async () => {
		const wrapper = mountComponent( true, confirmationTestStubs );
		await flushPromises();

		await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
		wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
		await flushPromises();

		wrapper.findComponent( CloseConfirmationDialog ).vm.$emit( 'keep-editing' );
		await flushPromises();

		expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
		expect( wrapper.findComponent( CloseConfirmationDialog ).props( 'open' ) ).toBe( false );
	} );

	describe( 'handleSave', () => {
		it( 'does not call onSave or notify success when there are no dirty panes', async () => {
			const onSave = vi.fn();
			const wrapper = mountComponent( true, saveButtonTestStubs, onSave );
			await flushPromises();

			// Bypasses the disabled Save button: the handler must not lean on that gate.
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( onSave ).not.toHaveBeenCalled();
			expect( ( mw.notify as Mock ).mock.calls.some(
				( call ) => typeof call[ 0 ] === 'string' && call[ 0 ].includes( 'neowiki-subject-editor-success' ),
			) ).toBe( false );
			expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
		} );

		it( 'calls onSave once, notifies success once, and closes when a dirty pane is saved', async () => {
			const onSave = vi.fn().mockResolvedValue( undefined );
			const wrapper = mountComponent( true, saveButtonTestStubs, onSave );
			await flushPromises();

			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( onSave ).toHaveBeenCalledTimes( 1 );
			expect( ( mw.notify as Mock ).mock.calls ).toContainEqual( [
				expect.stringContaining( 'neowiki-subject-editor-success' ),
				{ type: 'success' },
			] );
			expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
		} );
	} );

	const validationTestStubs = {
		SubjectEditor: SubjectEditorStub,
		SchemaEditorDialog: true,
		SummaryAction: SummaryActionStub,
	};

	describe( 'ValidationFailedError handling', () => {
		it( 'flows server violations down to child inputs on ValidationFailedError', async () => {
			const violation: SubjectViolation = {
				propertyName: 'name',
				code: 'required',
				args: [],
				severity: 'error',
				valuePartIndex: null,
			};
			const onSave = vi.fn().mockRejectedValue( new ValidationFailedError( [ violation ] ) );
			const wrapper = mountComponent( true, validationTestStubs, onSave );
			await flushPromises();

			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await flushPromises();

			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			const passedViolations = wrapper.findComponent( SubjectEditor ).props( 'serverViolations' ) as SubjectViolation[];
			expect( passedViolations ).toHaveLength( 1 );
			expect( passedViolations[ 0 ].propertyName ).toBe( 'name' );
			expect( passedViolations[ 0 ].code ).toBe( 'required' );
		} );

		it( 'keeps dialog open on ValidationFailedError', async () => {
			const violation: SubjectViolation = {
				propertyName: 'name',
				code: 'required',
				args: [],
				severity: 'error',
				valuePartIndex: null,
			};
			const onSave = vi.fn().mockRejectedValue( new ValidationFailedError( [ violation ] ) );
			const wrapper = mountComponent( true, validationTestStubs, onSave );
			await flushPromises();

			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await flushPromises();

			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
		} );

		it( 'shows toast on ValidationFailedError', async () => {
			const violation: SubjectViolation = {
				propertyName: 'name',
				code: 'required',
				args: [],
				severity: 'error',
				valuePartIndex: null,
			};
			const onSave = vi.fn().mockRejectedValue( new ValidationFailedError( [ violation ] ) );
			const wrapper = mountComponent( true, validationTestStubs, onSave );
			await flushPromises();

			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await flushPromises();

			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( ( mw.notify as Mock ).mock.calls ).toContainEqual( [
				expect.stringContaining( 'neowiki-subject-editor-validation-failed' ),
				{ type: 'error' },
			] );
		} );

		it( 'renders form-level banner for null-propertyName violation', async () => {
			const violation: SubjectViolation = {
				propertyName: null,
				code: 'schema-not-found',
				args: [ 'Person' ],
				severity: 'error',
				valuePartIndex: null,
			};
			const onSave = vi.fn().mockRejectedValue( new ValidationFailedError( [ violation ] ) );
			const wrapper = mountComponent( true, validationTestStubs, onSave );
			await flushPromises();

			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await flushPromises();

			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( wrapper.find( '.ext-neowiki-violation-banners__list' ).exists() ).toBe( true );

			const passedViolations = wrapper.findComponent( SubjectEditor ).props( 'serverViolations' ) as SubjectViolation[];
			expect( passedViolations ).toHaveLength( 1 );
			expect( passedViolations[ 0 ].propertyName ).toBeNull();
		} );

		it( 'splits banner violations by severity into an error and a warning message', async () => {
			// Anchored to a property the schema no longer has, so there is no field to
			// render it against and it lands in the banner.
			const errorViolation: SubjectViolation = {
				propertyName: 'name',
				code: 'type-mismatch',
				args: [ 'text', 'number' ],
				severity: 'error',
				valuePartIndex: null,
			};
			const warningViolation: SubjectViolation = {
				propertyName: null,
				code: 'schema-not-found',
				args: [ 'Person' ],
				severity: 'warning',
				valuePartIndex: null,
			};
			const onSave = vi.fn().mockRejectedValue(
				new ValidationFailedError( [ warningViolation, errorViolation ] ),
			);
			const wrapper = mountComponent( true, validationTestStubs, onSave );
			await flushPromises();

			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			const banners = wrapper.findAllComponents( CdxMessage );
			expect( banners ).toHaveLength( 2 );
			expect( banners[ 0 ].props( 'type' ) ).toBe( 'error' );
			expect( banners[ 0 ].text() ).toContain( 'neowiki-field-type-mismatch' );
			expect( banners[ 1 ].props( 'type' ) ).toBe( 'warning' );
			expect( banners[ 1 ].text() ).toContain( 'neowiki-field-schema-not-found' );
		} );

		it( 'does not treat a warning-only dry-run result as blocking the save', async () => {
			useSubjectStore().validateSubjectUpdate = vi.fn().mockResolvedValue( [ {
				propertyName: null,
				code: 'schema-not-found',
				args: [ 'Person' ],
				severity: 'warning',
				valuePartIndex: null,
			} ] );
			const onSave = vi.fn().mockResolvedValue( undefined );
			const wrapper = mountComponent( true, validationTestStubs, onSave );
			await flushPromises();

			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( onSave ).toHaveBeenCalledTimes( 1 );
			expect( wrapper.emitted( 'update:open' )?.[ 0 ] ).toEqual( [ false ] );
		} );

		it( 'drops the matching entry on clear-server-violation event from child', async () => {
			const violation: SubjectViolation = {
				propertyName: 'name',
				code: 'required',
				args: [],
				severity: 'error',
				valuePartIndex: null,
			};
			const onSave = vi.fn().mockRejectedValue( new ValidationFailedError( [ violation ] ) );
			const wrapper = mountComponent( true, validationTestStubs, onSave );
			await flushPromises();

			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await flushPromises();

			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( ( wrapper.findComponent( SubjectEditor ).props( 'serverViolations' ) as SubjectViolation[] ) ).toHaveLength( 1 );

			await wrapper.findComponent( SubjectEditor ).vm.$emit(
				'clear-server-violation',
				{ propertyName: 'name', valuePartIndex: null },
			);
			await flushPromises();

			const passedViolations = wrapper.findComponent( SubjectEditor ).props( 'serverViolations' ) as SubjectViolation[];
			expect( passedViolations ).toHaveLength( 0 );
		} );

		it( 'falls back to existing generic-error path for non-ValidationFailedError throws', async () => {
			const onSave = vi.fn().mockRejectedValue( new Error( 'Boom' ) );
			const wrapper = mountComponent( true, validationTestStubs, onSave );
			await flushPromises();

			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await flushPromises();

			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( ( mw.notify as Mock ).mock.calls ).toContainEqual( [
				'Boom',
				expect.objectContaining( {
					title: expect.stringContaining( 'neowiki-subject-editor-error' ),
					type: 'error',
				} ),
			] );
		} );
	} );

	describe( 'Server-driven dry-run validation', () => {
		const dryRunViolation: SubjectViolation = {
			propertyName: 'name',
			code: 'max-length',
			args: [ 5 ],
			severity: 'error',
			valuePartIndex: null,
		};

		it( 'surfaces dry-run violations on blur after an edit', async () => {
			const validate = vi.fn().mockResolvedValue( [ dryRunViolation ] );
			useSubjectStore().validateSubjectUpdate = validate;
			const wrapper = mountComponent( true, validationTestStubs );
			await flushPromises();

			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'focusout' );
			await flushPromises();

			expect( validate ).toHaveBeenCalledWith(
				mockSubject.getId(),
				mockSubject.getLabel(),
				expect.any( StatementList ),
			);
			const passed = wrapper.findComponent( SubjectEditor ).props( 'serverViolations' ) as SubjectViolation[];
			expect( passed ).toHaveLength( 1 );
			expect( passed[ 0 ].propertyName ).toBe( 'name' );
		} );

		it( 'runs the dry-run before saving so its violations surface inline', async () => {
			useSubjectStore().validateSubjectUpdate = vi.fn().mockResolvedValue( [ dryRunViolation ] );
			// onSave never resolves, so the dialog stays open and we can inspect
			// the violations produced by the pre-save flush.
			const onSave = vi.fn().mockReturnValue( new Promise<void>( () => {
				// Intentionally never settles.
			} ) );
			const wrapper = mountComponent( true, validationTestStubs, onSave );
			await flushPromises();

			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			const passed = wrapper.findComponent( SubjectEditor ).props( 'serverViolations' ) as SubjectViolation[];
			expect( passed ).toHaveLength( 1 );
			expect( passed[ 0 ].propertyName ).toBe( 'name' );
		} );

		it( 'keeps editing working when the dry-run validation fails', async () => {
			useSubjectStore().validateSubjectUpdate = vi.fn().mockRejectedValue( new Error( 'network down' ) );
			const wrapper = mountComponent( true, validationTestStubs );
			await flushPromises();

			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'focusout' );
			await flushPromises();

			const passed = wrapper.findComponent( SubjectEditor ).props( 'serverViolations' ) as SubjectViolation[];
			expect( passed ).toHaveLength( 0 );
		} );

		it( 'surfaces required violations from the dry-run; an existing subject flags missing required', async () => {
			const requiredViolation: SubjectViolation = {
				propertyName: 'name', code: 'required', args: [], severity: 'error', valuePartIndex: null,
			};
			useSubjectStore().validateSubjectUpdate = vi.fn().mockResolvedValue( [ requiredViolation ] );
			const wrapper = mountComponent( true, validationTestStubs );
			await flushPromises();

			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'focusout' );
			await flushPromises();

			const passed = wrapper.findComponent( SubjectEditor ).props( 'serverViolations' ) as SubjectViolation[];
			expect( passed ).toEqual( [ requiredViolation ] );
		} );

		it( 'validates on open so an existing subject\'s violations surface without an edit', async () => {
			const existingViolation: SubjectViolation = {
				propertyName: 'name', code: 'required', args: [], severity: 'error', valuePartIndex: null,
			};
			const validate = vi.fn().mockResolvedValue( [ existingViolation ] );
			useSubjectStore().validateSubjectUpdate = validate;

			const wrapper = mountComponent( true, validationTestStubs );
			await flushPromises();

			// No @change / @focusout: the dialog validated the existing subject on open.
			expect( validate ).toHaveBeenCalled();
			const passed = wrapper.findComponent( SubjectEditor ).props( 'serverViolations' ) as SubjectViolation[];
			expect( passed ).toEqual( [ existingViolation ] );
		} );
	} );

	describe( 'Unparseable field input', () => {
		it( 'does not save while a field holds text that cannot be turned into a value', async () => {
			const onSave = vi.fn().mockResolvedValue( undefined );
			const wrapper = mountComponent( false, validationTestStubs, onSave );
			await flushPromises();
			editorUnparseableInput = { propertyName: 'Score', message: 'neowiki-field-invalid-number' };

			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( onSave ).not.toHaveBeenCalled();
			expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
			expect( mw.notify ).toHaveBeenCalledTimes( 1 );
			expect( mw.notify ).toHaveBeenCalledWith(
				'neowiki-field-invalid-number',
				{ title: 'Score', type: 'error' },
			);
		} );

		// The message comes from the field that is holding the text, so any input can
		// report one; a gate that reused the number-specific message would misreport it.
		it( 'names the offending field in the blocked-save notification', async () => {
			const wrapper = mountComponent( false, validationTestStubs );
			await flushPromises();
			editorUnparseableInput = { propertyName: 'Score', message: 'whatever the field shows' };

			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( mw.notify ).toHaveBeenCalledWith(
				'whatever the field shows',
				{ title: 'Score', type: 'error' },
			);
		} );

		it( 'saves once the text parses again', async () => {
			const onSave = vi.fn().mockResolvedValue( undefined );
			const wrapper = mountComponent( false, validationTestStubs, onSave );
			await flushPromises();
			editorUnparseableInput = { propertyName: 'Score', message: 'neowiki-field-invalid-number' };
			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			editorUnparseableInput = null;
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( onSave ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'still runs the pre-save dry-run, so the other fields report alongside the invalid number', async () => {
			const otherViolation: SubjectViolation = {
				propertyName: 'name', code: 'required', args: [], severity: 'error', valuePartIndex: null,
			};
			const onSave = vi.fn().mockResolvedValue( undefined );
			const wrapper = mountComponent( false, validationTestStubs, onSave );
			await flushPromises();

			useSubjectStore().validateSubjectUpdate = vi.fn().mockResolvedValue( [ otherViolation ] );
			editorUnparseableInput = { propertyName: 'Score', message: 'neowiki-field-invalid-number' };
			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( onSave ).not.toHaveBeenCalled();
			expect( wrapper.findComponent( SubjectEditor ).props( 'serverViolations' ) ).toEqual( [ otherViolation ] );
		} );
	} );

	function titleText( wrapper: VueWrapper ): string {
		return wrapper.find( '.ext-neowiki-editable-text__text' ).text();
	}

	async function editLabel( wrapper: VueWrapper, value: string ): Promise<void> {
		await wrapper.find( 'button[aria-label="neowiki-subject-editor-rename"]' ).trigger( 'click' );
		const input = wrapper.find( '.ext-neowiki-editable-text input' );
		await input.setValue( value );
		await input.trigger( 'keydown.enter' );
	}

	describe( 'Label editing', () => {
		it( 'shows the current label as the dialog title', async () => {
			const wrapper = mountComponent( false, validationTestStubs );
			await flushPromises();

			expect( titleText( wrapper ) ).toBe( 'Test Subject' );
		} );

		it( 'enables save after the label is edited', async () => {
			const wrapper = mountComponent( false, validationTestStubs );
			await flushPromises();

			await editLabel( wrapper, 'Renamed Subject' );

			expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( false );
		} );

		it( 'saves the subject with the edited label', async () => {
			const onSave = vi.fn().mockResolvedValue( undefined );
			const wrapper = mountComponent( false, validationTestStubs, onSave );
			await flushPromises();

			await editLabel( wrapper, 'Renamed Subject' );
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( ( onSave.mock.calls[ 0 ][ 0 ] as Subject ).getLabel() ).toBe( 'Renamed Subject' );
		} );

		it( 'trims the label on save', async () => {
			const onSave = vi.fn().mockResolvedValue( undefined );
			const wrapper = mountComponent( false, validationTestStubs, onSave );
			await flushPromises();

			await editLabel( wrapper, '  Renamed Subject  ' );
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( ( onSave.mock.calls[ 0 ][ 0 ] as Subject ).getLabel() ).toBe( 'Renamed Subject' );
		} );

		it( 'saves without a label when the label is blanked', async () => {
			const onSave = vi.fn().mockResolvedValue( undefined );
			const wrapper = mountComponent( false, validationTestStubs, onSave );
			await flushPromises();

			await editLabel( wrapper, '   ' );
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( ( onSave.mock.calls[ 0 ][ 0 ] as Subject ).getLabel() ).toBeNull();
		} );

		// PUT replaces the whole Subject, so an untouched label has to be sent back verbatim.
		// Sending the placeholder instead, or nothing at all, silently wipes every stored label.
		it( 'sends the stored label back unchanged when only a statement was edited', async () => {
			const onSave = vi.fn().mockResolvedValue( undefined );
			const wrapper = mountComponent( false, validationTestStubs, onSave );
			await flushPromises();

			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( ( onSave.mock.calls[ 0 ][ 0 ] as Subject ).getLabel() ).toBe( 'Test Subject' );
		} );

		it( 'keeps a label-less subject label-less when only a statement was edited', async () => {
			const onSave = vi.fn().mockResolvedValue( undefined );
			const wrapper = mountComponent( false, validationTestStubs, onSave );
			await wrapper.setProps( { subject: labellessSubject } );
			await flushPromises();

			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( ( onSave.mock.calls[ 0 ][ 0 ] as Subject ).getLabel() ).toBeNull();
		} );

		it( 'names a label-less subject by its display name when the save succeeds', async () => {
			const onSave = vi.fn().mockResolvedValue( undefined );
			const wrapper = mountComponent( false, validationTestStubs, onSave );
			await wrapper.setProps( { subject: labellessSubject } );
			await flushPromises();

			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( mw.notify ).toHaveBeenCalledWith(
				'neowiki-subject-editor-success' + labellessSubject.getDisplayName(),
				{ type: 'success' },
			);
		} );

		it( 'shows the display name as the placeholder for a label-less subject', async () => {
			const wrapper = mountComponent( false, validationTestStubs );
			await wrapper.setProps( { subject: labellessSubject } );
			await flushPromises();

			expect( titleText( wrapper ) ).toBe( 'Host Page' );

			await wrapper.find( 'button[aria-label="neowiki-subject-editor-rename"]' ).trigger( 'click' );

			const input = wrapper.find( '.ext-neowiki-editable-text input' );
			expect( input.attributes( 'placeholder' ) ).toBe( 'Host Page' );
			expect( ( input.element as HTMLInputElement ).value ).toBe( '' );
		} );

		// Codex takes the dialog's accessible name from the title prop whenever a header slot
		// replaces the rendered title.
		it( 'names a label-less subject by its display name in the dialog\'s accessible name', async () => {
			const wrapper = mountComponent(
				false, validationTestStubs, undefined, mockSchema, {}, labellessSubject,
			);
			await flushPromises();

			expect( wrapper.find( '.cdx-dialog' ).attributes( 'aria-label' ) )
				.toBe( 'neowiki-subject-editor-title' + labellessSubject.getDisplayName() );
		} );

		it( 'does not preview the removed label once a labelled subject is cleared', async () => {
			const wrapper = mountComponent( false, validationTestStubs );
			await flushPromises();

			await editLabel( wrapper, '' );
			await flushPromises();

			// The client cannot compute the name the server will fall back to, and the old label is
			// the one name it is certain to no longer be.
			expect( titleText( wrapper ) ).toBe( 'neowiki-subject-editor-label-field' );
		} );

		it( 'sends no label to the dry-run validation once the label is blanked', async () => {
			const validate = vi.fn().mockResolvedValue( [] );
			useSubjectStore().validateSubjectUpdate = validate;
			const wrapper = mountComponent( false, validationTestStubs );
			await flushPromises();

			await editLabel( wrapper, '   ' );
			await flushPromises();

			const lastCall = validate.mock.calls[ validate.mock.calls.length - 1 ];
			expect( lastCall[ 1 ] ).toBeNull();
		} );

		it( 'sends the edited label trimmed to the dry-run validation', async () => {
			const validate = vi.fn().mockResolvedValue( [] );
			useSubjectStore().validateSubjectUpdate = validate;
			const wrapper = mountComponent( false, validationTestStubs );
			await flushPromises();

			await editLabel( wrapper, '  Renamed Subject  ' );
			await flushPromises();

			const lastCall = validate.mock.calls[ validate.mock.calls.length - 1 ];
			expect( lastCall[ 1 ] ).toBe( 'Renamed Subject' );
		} );

		it( 'closes the rename input on a blank commit, the label being optional', async () => {
			const wrapper = mountComponent( false, validationTestStubs );
			await flushPromises();

			await editLabel( wrapper, '' );
			await flushPromises();

			expect( wrapper.find( '.ext-neowiki-editable-text input' ).exists() ).toBe( false );
		} );

		it( 'follows the label when the host replaces the subject', async () => {
			const wrapper = mountComponent( false, validationTestStubs );
			await flushPromises();

			await wrapper.setProps( {
				subject: new Subject(
					new SubjectId( 's1demo5sssssss1' ),
					'Renamed Subject',
					'Renamed Subject',
					'TestSchema',
					new StatementList( [] ),
				),
			} );

			expect( titleText( wrapper ) ).toBe( 'Renamed Subject' );
		} );

		it( 'resets the label to the stored one when the dialog reopens', async () => {
			const wrapper = mountComponent( false, validationTestStubs );
			await flushPromises();

			await editLabel( wrapper, 'Renamed Subject' );
			await wrapper.setProps( { open: false } );
			await wrapper.setProps( { open: true } );

			expect( titleText( wrapper ) ).toBe( 'Test Subject' );
		} );
	} );

	describe( 'Panes', () => {
		const personSchema = new Schema(
			'Person',
			'A person',
			new PropertyDefinitionList( [] ),
		);

		// The module-wide mockSchema declares no relation and mockSubject stores no target, so no
		// navigator is rendered anywhere in "Panes" except where these two fixtures are passed.
		const relationRootSchema = new Schema(
			'TestSchema',
			'A test schema',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'Colleague', { type: 'relation', targetSchema: 'Person' } ),
			] ),
		);

		function colleagueStatement( ...targetIds: string[] ): Statement {
			return new Statement(
				new PropertyName( 'Colleague' ),
				'relation',
				new RelationValue( targetIds.map( ( id ) => newRelation( undefined, id ) ) ),
			);
		}

		// mockSubject with the given Colleague targets stored, so the tree has a node for each.
		function rootSubjectWithTargets( ...targetIds: string[] ): Subject {
			return new Subject(
				mockSubject.getId(),
				mockSubject.getLabel(),
				mockSubject.getDisplayName(),
				'TestSchema',
				new StatementList( [ colleagueStatement( ...targetIds ) ] ),
			);
		}

		const relationRootSubject = rootSubjectWithTargets( 's22222222222222' );

		// Through the tree component rather than the root wrapper: the real Teleport moves the
		// dialog out of the wrapper's own element.
		function treeHasNode( wrapper: VueWrapper, id: string ): boolean {
			const tree = wrapper.findComponent( SubjectTree );
			return tree.exists() && tree.find( `[data-mw-neowiki-subject-id="${ id }"]` ).exists();
		}

		function edgeCaptions( wrapper: VueWrapper ): string[] {
			return wrapper.findComponent( SubjectTree )
				.findAll( '.ext-neowiki-tree__edge' )
				.map( ( caption ) => caption.text() );
		}

		// The node's own row, not its subtree, which would also hold a descendant's dot.
		function treeNodeHasDot( wrapper: VueWrapper, id: string ): boolean {
			if ( !treeHasNode( wrapper, id ) ) {
				return false;
			}
			const tree = wrapper.findComponent( SubjectTree );
			const node = tree.get( `[data-mw-neowiki-subject-id="${ id }"]` );
			return tree.get( `#${ node.attributes( 'id' ) }-name` )
				.find( '.ext-neowiki-unsaved-dot' ).exists();
		}

		// The node's own row: the root node contains every descendant's label as well.
		function treeNodeLabel( wrapper: VueWrapper, id: string ): string {
			const tree = wrapper.findComponent( SubjectTree );
			const node = tree.get( `[data-mw-neowiki-subject-id="${ id }"]` );
			return tree.get( `#${ node.attributes( 'id' ) }-name` )
				.get( '.ext-neowiki-tree__node-label' ).text();
		}

		// The repository stub answers every request with one target, so two panes can carry the
		// same Subject: a pane is located by its panel, not by the Subject id it reports.
		// Parameterised on purpose: a bare VueWrapper widens SubjectEditPane's props away, which
		// narrows props( 'editedCopy' ) to never.
		function paneFor( wrapper: VueWrapper, id: string ): VueWrapper<InstanceType<typeof SubjectEditPane>> {
			return wrapper.find( `#ext-neowiki-panel-${ id }` ).findComponent( SubjectEditPane );
		}

		const paneStackStubs = saveButtonTestStubs;

		function targetSubject( id: string, label: string ): SubjectWithContext {
			return newSubject( { id, label, schemaName: 'Person' } );
		}

		interface TargetReposMount {
			wrapper: VueWrapper;
			mockSubjectRepository: { getSubject: Mock };
			mockSchemaRepository: { getSchema: Mock };
			target: SubjectWithContext;
		}

		type SaveHandler = ( subject: any, comment: string ) => Promise<void>;

		interface PaneStackOptions {
			onSave?: SaveHandler;
			rootSchema?: Schema;
			rootSubject?: Subject;
			stubs?: Record<string, any>;
		}

		function mountWithTargetRepos(
			onSave?: SaveHandler,
			stubOverrides: Record<string, any> = {},
			rootSchema: Schema = mockSchema,
			rootSubject: Subject = mockSubject,
			attachTo: Element | undefined = undefined,
		): TargetReposMount {
			const target = targetSubject( 's22222222222222', 'Target subject' );
			const mockSubjectRepository = { getSubject: vi.fn().mockResolvedValue( target ) };
			const mockSchemaRepository = { getSchema: vi.fn().mockResolvedValue( personSchema ) };
			const wrapper = mountComponent(
				true,
				{ ...paneStackStubs, ...stubOverrides },
				onSave,
				rootSchema,
				{
					[ Service.SubjectRepository ]: mockSubjectRepository,
					[ Service.SchemaRepository ]: mockSchemaRepository,
				},
				rootSubject,
				attachTo,
			);
			return { wrapper, mockSubjectRepository, mockSchemaRepository, target };
		}

		async function mountWithSecondPaneOpen(
			{ onSave, rootSchema, rootSubject, stubs = {} }: PaneStackOptions = {},
		): Promise<TargetReposMount> {
			const result = mountWithTargetRepos( onSave, stubs, rootSchema, rootSubject );
			await flushPromises();
			result.wrapper.findComponent( SubjectEditPane ).vm.$emit( 'edit-relation-target', new SubjectId( 's22222222222222' ) );
			await flushPromises();
			return result;
		}

		// The last-opened target ends up on screen, so the root pane (index 0) is behind it.
		async function mountWithThreePanesOpen(
			{ onSave, rootSchema, rootSubject }: PaneStackOptions = {},
		): Promise<TargetReposMount> {
			const result = mountWithTargetRepos( onSave, {}, rootSchema, rootSubject );
			await flushPromises();
			result.wrapper.findComponent( SubjectEditPane ).vm.$emit( 'edit-relation-target', new SubjectId( 's22222222222222' ) );
			await flushPromises();
			result.wrapper.findComponent( SubjectEditPane ).vm.$emit( 'edit-relation-target', new SubjectId( 's33333333333333' ) );
			await flushPromises();
			return result;
		}

		async function makePaneDirty( wrapper: VueWrapper, paneIndex: number ): Promise<void> {
			wrapper.findAllComponents( SubjectEditPane )[ paneIndex ]
				.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await nextTick();
		}

		// Read off the prop: the SummaryAction stub renders nothing of its own.
		function footerTextOf( wrapper: VueWrapper ): string {
			return wrapper.findComponent( SummaryAction ).props( 'footerText' ) as string;
		}

		async function triggerSave( wrapper: VueWrapper, summary: string ): Promise<void> {
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', summary );
			await flushPromises();
		}

		// Every pane stays mounted behind v-show, so the one on screen is read off the panels.
		// From the inline style rather than isVisible(): jsdom's getComputedStyle serves a stale
		// answer once an element has been measured.
		function visibleSubjectId( wrapper: VueWrapper ): string {
			const panel = wrapper.findAll( '.ext-neowiki-subject-editor-dialog__panels > div' )
				.find( ( candidate ) => !( candidate.attributes( 'style' ) ?? '' ).includes( 'display: none' ) );
			return ( panel?.attributes( 'id' ) ?? '' ).replace( 'ext-neowiki-panel-', '' );
		}

		// Presses the row itself, so unlike selectInTree below this fails when no row is
		// rendered: it tests reachability rather than the dialog's handler.
		async function clickTreeNode( wrapper: VueWrapper, id: string ): Promise<void> {
			const tree = wrapper.findComponent( SubjectTree );
			const node = tree.get( `[data-mw-neowiki-subject-id="${ id }"]` );
			await tree.get( `#${ node.attributes( 'id' ) }-name` ).trigger( 'click' );
			await flushPromises();
		}

		// The same reading under the real Teleport, which moves the dialog to the document body:
		// a query rooted at the wrapper finds no panel, and one rooted at the document finds every
		// dialog this file has ever mounted. Each pane is reached through its own component.
		function teleportedVisibleSubjectId( wrapper: VueWrapper ): string {
			const panel = wrapper.findAllComponents( SubjectEditPane )
				.map( ( pane ) => pane.element.closest( '[id^="ext-neowiki-panel-"]' ) )
				.find( ( element ) => element !== null &&
					!( element.getAttribute( 'style' ) ?? '' ).includes( 'display: none' ) );
			return ( panel?.id ?? '' ).replace( 'ext-neowiki-panel-', '' );
		}

		async function selectInTree( wrapper: VueWrapper, id: string ): Promise<void> {
			wrapper.findComponent( SubjectTree ).vm.$emit( 'select', new SubjectId( id ) );
			await flushPromises();
		}

		it( 'keeps every open subject mounted so unsaved values survive switching subject', async () => {
			const { wrapper } = await mountWithSecondPaneOpen();

			// Both panes exist even though one is on screen: the hidden one is v-show'd.
			expect( wrapper.findAllComponents( SubjectEditPane ) ).toHaveLength( 2 );
			expect( wrapper.find( `#ext-neowiki-panel-${ rootSubjectId }` ).attributes( 'style' ) )
				.toContain( 'display: none' );
		} );

		it( 'shows a newly opened relation target', async () => {
			const { wrapper } = await mountWithSecondPaneOpen();

			expect( visibleSubjectId( wrapper ) ).toBe( 's22222222222222' );
		} );

		it( 'opens a second pane with the freshly fetched target subject', async () => {
			const { wrapper, mockSubjectRepository } = mountWithTargetRepos();
			await flushPromises();

			wrapper.findComponent( SubjectEditPane ).vm.$emit( 'edit-relation-target', new SubjectId( 's22222222222222' ) );
			await flushPromises();

			expect( mockSubjectRepository.getSubject ).toHaveBeenCalledTimes( 1 );
			expect( wrapper.findAllComponents( SubjectEditPane ) ).toHaveLength( 2 );
		} );

		it( 'does not duplicate a pane for an already-open subject', async () => {
			const { wrapper } = mountWithTargetRepos();
			await flushPromises();

			wrapper.findComponent( SubjectEditPane ).vm.$emit( 'edit-relation-target', new SubjectId( 's22222222222222' ) );
			await flushPromises();
			wrapper.findComponent( SubjectEditPane ).vm.$emit( 'edit-relation-target', new SubjectId( 's22222222222222' ) );
			await flushPromises();

			expect( wrapper.findAllComponents( SubjectEditPane ) ).toHaveLength( 2 );
		} );

		// teleport:false throughout: the teleport stub used elsewhere in this file re-creates
		// its subtree on every re-render of the teleporting component, where the real Teleport
		// patches in place. Under the stub these assertions would fail for a reason that cannot
		// occur outside the test.
		it( 'keeps the root pane\'s unsaved edit when a second pane is opened beside it', async () => {
			const onSave = vi.fn().mockResolvedValue( undefined );
			const { wrapper } = mountWithTargetRepos( onSave, { teleport: false } );
			await flushPromises();

			await makePaneDirty( wrapper, 0 );
			expect( ( wrapper.vm as any ).hasChanged ).toBe( true );

			wrapper.findComponent( SubjectEditPane ).vm.$emit( 'edit-relation-target', new SubjectId( 's22222222222222' ) );
			await flushPromises();

			expect( ( wrapper.vm as any ).hasChanged ).toBe( true );

			await triggerSave( wrapper, '' );

			expect( onSave ).toHaveBeenCalledTimes( 1 );
			expect( onSave.mock.calls[ 0 ][ 0 ].getId().text ).toBe( rootSubjectId );
		} );

		it( 'resets to a single visible pane for the new root when the host replaces the subject', async () => {
			const { wrapper } = await mountWithSecondPaneOpen();

			const newRoot = targetSubject( 's99999999999999', 'New root' );
			await wrapper.setProps( { subject: newRoot } );

			expect( wrapper.findAllComponents( SubjectEditPane ) ).toHaveLength( 1 );
			const panel = wrapper.find( '#ext-neowiki-panel-s99999999999999' );
			expect( panel.exists() ).toBe( true );
			expect( panel.isVisible() ).toBe( true );
		} );

		it( 'switches the subject on screen when a tree node is chosen, keeping every pane mounted', async () => {
			const { wrapper } = await mountWithThreePanesOpen( {
				rootSchema: relationRootSchema,
				rootSubject: relationRootSubject,
			} );
			expect( visibleSubjectId( wrapper ) ).toBe( 's33333333333333' );

			await selectInTree( wrapper, rootSubjectId );

			expect( visibleSubjectId( wrapper ) ).toBe( rootSubjectId );
			expect( wrapper.findAllComponents( SubjectEditPane ) ).toHaveLength( 3 );
		} );

		it( 'keeps a child subject\'s edit when the root is opened and the child returned to', async () => {
			const { wrapper } = await mountWithSecondPaneOpen( {
				rootSchema: relationRootSchema,
				rootSubject: relationRootSubject,
			} );
			( wrapper.findAllComponents( SubjectEditPane )[ 1 ].vm as any ).setLabel( 'Edited child' );
			await nextTick();

			await selectInTree( wrapper, rootSubjectId );
			await selectInTree( wrapper, 's22222222222222' );

			expect( visibleSubjectId( wrapper ) ).toBe( 's22222222222222' );
			expect( ( wrapper.findAllComponents( SubjectEditPane )[ 1 ].vm as any ).label )
				.toBe( 'Edited child' );
			expect( ( wrapper.vm as any ).hasChanged ).toBe( true );
		} );

		it( 'shows the tree\'s unsaved dot on a dirty subject and not on a clean sibling', async () => {
			useSubjectStore().setSubject( targetSubject( 's22222222222222', 'Target subject' ) );
			const { wrapper } = await mountWithSecondPaneOpen( {
				rootSchema: relationRootSchema,
				rootSubject: relationRootSubject,
			} );
			await makePaneDirty( wrapper, 1 );
			await flushPromises();

			expect( treeNodeHasDot( wrapper, 's22222222222222' ) ).toBe( true );
			expect( treeNodeHasDot( wrapper, rootSubjectId ) ).toBe( false );
		} );

		it( 'shows the tree\'s unsaved dot on the root subject too', async () => {
			useSubjectStore().setSubject( targetSubject( 's22222222222222', 'Target subject' ) );
			const { wrapper } = await mountWithSecondPaneOpen( {
				rootSchema: relationRootSchema,
				rootSubject: relationRootSubject,
			} );
			await makePaneDirty( wrapper, 0 );
			await flushPromises();

			expect( treeNodeHasDot( wrapper, rootSubjectId ) ).toBe( true );
		} );

		it( 'notifies and adds no pane when the target cannot be loaded', async () => {
			const { wrapper, mockSubjectRepository } = mountWithTargetRepos();
			await flushPromises();
			mockSubjectRepository.getSubject.mockRejectedValue( new Error( 'Error fetching subject' ) );

			wrapper.findComponent( SubjectEditPane ).vm.$emit( 'edit-relation-target', new SubjectId( 's22222222222222' ) );
			await flushPromises();

			expect( wrapper.findAllComponents( SubjectEditPane ) ).toHaveLength( 1 );
			expect( mw.notify ).toHaveBeenCalledWith( 'neowiki-subject-editor-target-load-error', { type: 'error' } );
		} );

		describe( 'Multi-pane save', () => {
			it( 'saves each dirty pane once, in order, and skips clean panes', async () => {
				const onSave = vi.fn().mockResolvedValue( undefined );
				const { wrapper } = await mountWithSecondPaneOpen( { onSave } );

				await makePaneDirty( wrapper, 0 );
				await makePaneDirty( wrapper, 1 );
				await triggerSave( wrapper, 'my summary' );

				expect( onSave ).toHaveBeenCalledTimes( 2 );
				expect( onSave.mock.calls[ 0 ][ 0 ].getId().text ).toBe( rootSubjectId );
				expect( onSave.mock.calls[ 1 ][ 0 ].getId().text ).toBe( 's22222222222222' );
			} );

			it( 'does not call onSave for a clean pane', async () => {
				const onSave = vi.fn().mockResolvedValue( undefined );
				const { wrapper } = await mountWithSecondPaneOpen( { onSave } );

				await makePaneDirty( wrapper, 1 );
				await triggerSave( wrapper, '' );

				expect( onSave ).toHaveBeenCalledTimes( 1 );
				expect( onSave.mock.calls[ 0 ][ 0 ].getId().text ).toBe( 's22222222222222' );
			} );

			it( 'notifies success with the saved pane\'s own label when only that pane was dirty', async () => {
				const onSave = vi.fn().mockResolvedValue( undefined );
				const { wrapper, target } = await mountWithSecondPaneOpen( { onSave } );

				await makePaneDirty( wrapper, 1 );
				await triggerSave( wrapper, '' );

				expect( ( mw.notify as Mock ).mock.calls ).toContainEqual( [
					'neowiki-subject-editor-success' + target.getDisplayName(),
					{ type: 'success' },
				] );
			} );

			// Two Subjects have no one name between them, so the message falls back to the root's.
			it( 'names the root subject by its display name when several panes were saved', async () => {
				const onSave = vi.fn().mockResolvedValue( undefined );
				const { wrapper } = await mountWithSecondPaneOpen( {
					onSave,
					rootSubject: labellessSubject,
				} );

				await makePaneDirty( wrapper, 0 );
				await makePaneDirty( wrapper, 1 );
				await triggerSave( wrapper, '' );

				expect( onSave ).toHaveBeenCalledTimes( 2 );
				expect( ( mw.notify as Mock ).mock.calls ).toContainEqual( [
					'neowiki-subject-editor-success' + labellessSubject.getDisplayName(),
					{ type: 'success' },
				] );
			} );

			it( 'routes a 422 into the failing pane, keeps the dialog open, and stops the sequence', async () => {
				const violation: SubjectViolation = {
					propertyName: 'Name', code: 'value-too-long', args: [], severity: 'error', valuePartIndex: null,
				};
				const onSave = vi.fn()
					.mockResolvedValueOnce( undefined )
					.mockRejectedValueOnce( new ValidationFailedError( [ violation ] ) );
				const { wrapper } = await mountWithSecondPaneOpen( { onSave } );

				await makePaneDirty( wrapper, 0 );
				await makePaneDirty( wrapper, 1 );
				await triggerSave( wrapper, '' );

				expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
				const secondPaneEditor = wrapper.findAllComponents( SubjectEditPane )[ 1 ].findComponent( SubjectEditor );
				expect( secondPaneEditor.props( 'serverViolations' ) ).toEqual( [ violation ] );
			} );

			it( 'retry after partial failure only re-saves the still-dirty pane', async () => {
				const onSave = vi.fn()
					.mockResolvedValueOnce( undefined )
					.mockRejectedValueOnce( new ValidationFailedError( [] ) )
					.mockResolvedValueOnce( undefined );
				const { wrapper } = await mountWithSecondPaneOpen( { onSave } );

				await makePaneDirty( wrapper, 0 );
				await makePaneDirty( wrapper, 1 );
				await triggerSave( wrapper, '' );
				await triggerSave( wrapper, '' );

				expect( onSave ).toHaveBeenCalledTimes( 3 );
				expect( onSave.mock.calls[ 2 ][ 0 ].getId().text ).toBe( 's22222222222222' );
			} );

			it( 'asks for close confirmation when the dirty pane is not the one on screen', async () => {
				const { wrapper } = await mountWithThreePanesOpen();
				await makePaneDirty( wrapper, 0 );

				await wrapper.find( '.cdx-dialog__header__close-button' ).trigger( 'click' );

				expect( wrapper.findComponent( CloseConfirmationDialog ).props( 'open' ) ).toBe( true );
			} );

			it( 'brings a background pane on screen when its ValidationFailedError is caught', async () => {
				const violation: SubjectViolation = {
					propertyName: 'Name', code: 'value-too-long', args: [], severity: 'error', valuePartIndex: null,
				};
				// Panes are saved in panes.value order, so the first mock response is the root's.
				const onSave = vi.fn()
					.mockRejectedValueOnce( new ValidationFailedError( [ violation ] ) )
					.mockResolvedValueOnce( undefined );
				const { wrapper } = await mountWithThreePanesOpen( { onSave } );

				expect( visibleSubjectId( wrapper ) ).not.toBe( rootSubjectId );

				await makePaneDirty( wrapper, 0 );
				await makePaneDirty( wrapper, 1 );
				await triggerSave( wrapper, '' );

				expect( visibleSubjectId( wrapper ) ).toBe( rootSubjectId );
				expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
				expect( onSave ).toHaveBeenCalledTimes( 1 );
				const rootPaneEditor = wrapper.findAllComponents( SubjectEditPane )[ 0 ].findComponent( SubjectEditor );
				expect( rootPaneEditor.props( 'serverViolations' ) ).toEqual( [ violation ] );
			} );

			// The pane's props.subject is the repository's pre-edit read, so only a recorded copy
			// can carry what the save wrote.
			it( 'records what it wrote, so the pane keeps showing it', async () => {
				const onSave = vi.fn()
					.mockResolvedValueOnce( undefined )
					.mockRejectedValueOnce( new ValidationFailedError( [] ) );
				const { wrapper } = await mountWithThreePanesOpen( { onSave } );
				( wrapper.findAllComponents( SubjectEditPane )[ 1 ].vm as any ).setLabel( 'Written child' );
				await nextTick();
				await makePaneDirty( wrapper, 2 );

				await triggerSave( wrapper, '' );

				expect( ( paneFor( wrapper, 's22222222222222' ).props( 'editedCopy' ) as Subject ).getLabel() )
					.toBe( 'Written child' );
			} );

			it( 'writes nothing when a later pane holds text that cannot be turned into a value', async () => {
				const onSave = vi.fn().mockResolvedValue( undefined );
				const { wrapper } = await mountWithSecondPaneOpen( { onSave } );

				await makePaneDirty( wrapper, 0 );
				await makePaneDirty( wrapper, 1 );
				editorUnparseableInputBySchema = {
					Person: { propertyName: 'Score', message: 'neowiki-field-invalid-number' },
				};

				await triggerSave( wrapper, '' );

				expect( onSave ).not.toHaveBeenCalled();
				expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
				expect( mw.notify ).toHaveBeenCalledWith(
					'neowiki-field-invalid-number',
					{ title: 'Score', type: 'error' },
				);
			} );

			// The gate runs across every dirty pane before any write, so a toast could otherwise name
			// a field on a form the user cannot see.
			it( 'shows the pane whose text cannot be turned into a value when it is off screen', async () => {
				const onSave = vi.fn().mockResolvedValue( undefined );
				const { wrapper } = await mountWithThreePanesOpen( { onSave } );
				// Both targets are on Person, so keying the unreadable field by schema names the root.
				await makePaneDirty( wrapper, 0 );
				editorUnparseableInputBySchema = {
					TestSchema: { propertyName: 'Score', message: 'neowiki-field-invalid-number' },
				};
				expect( visibleSubjectId( wrapper ) ).toBe( 's33333333333333' );

				await triggerSave( wrapper, '' );

				expect( onSave ).not.toHaveBeenCalled();
				expect( visibleSubjectId( wrapper ) ).toBe( rootSubjectId );
			} );
		} );

		describe( 'Written subjects', () => {
			// The fetched Subject a written pane would otherwise fall back to is a pre-edit read, so
			// the form would visibly revert under the user while the dialog is still open.
			it( 'keeps a written subject\'s values on screen when a later subject\'s save fails', async () => {
				const onSave = vi.fn()
					.mockResolvedValueOnce( undefined )
					.mockRejectedValueOnce( new ValidationFailedError( [] ) );
				const { wrapper } = await mountWithSecondPaneOpen( { onSave } );
				await editLabel( wrapper, 'Written root' );
				await makePaneDirty( wrapper, 1 );

				await triggerSave( wrapper, '' );

				const root = paneFor( wrapper, rootSubjectId );
				expect( ( root.vm as any ).label ).toBe( 'Written root' );
				expect( ( root.props( 'editedCopy' ) as Subject ).getLabel() ).toBe( 'Written root' );
			} );

			it( 'clears the tree\'s unsaved dot on a written subject and keeps the failed one\'s', async () => {
				useSubjectStore().setSubject( targetSubject( 's22222222222222', 'Target subject' ) );
				// The root's form holds the relation its stored Subject holds, so the write still
				// reaches the target whose dot this test reads.
				editorStatementsBySchema = { TestSchema: [ colleagueStatement( 's22222222222222' ) ] };
				const onSave = vi.fn()
					.mockResolvedValueOnce( undefined )
					.mockRejectedValueOnce( new ValidationFailedError( [] ) );
				const { wrapper } = await mountWithSecondPaneOpen( {
					onSave,
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
				} );
				await makePaneDirty( wrapper, 0 );
				await makePaneDirty( wrapper, 1 );
				expect( treeNodeHasDot( wrapper, rootSubjectId ) ).toBe( true );

				await triggerSave( wrapper, '' );

				expect( treeNodeHasDot( wrapper, rootSubjectId ) ).toBe( false );
				expect( treeNodeHasDot( wrapper, 's22222222222222' ) ).toBe( true );
			} );

			// Were a written pane treated as settled, the user could not correct it without
			// reopening the whole dialog.
			it( 're-saves a written subject when it is edited again after a partial failure', async () => {
				const onSave = vi.fn()
					.mockResolvedValueOnce( undefined )
					.mockRejectedValueOnce( new ValidationFailedError( [] ) )
					.mockResolvedValue( undefined );
				const { wrapper } = await mountWithSecondPaneOpen( { onSave } );
				await makePaneDirty( wrapper, 0 );
				await makePaneDirty( wrapper, 1 );
				await triggerSave( wrapper, '' );

				await makePaneDirty( wrapper, 0 );
				await triggerSave( wrapper, '' );

				const retried = onSave.mock.calls.slice( 2 ).map( ( call ) => call[ 0 ].getId().text );
				expect( retried ).toContain( rootSubjectId );
			} );

			// A Subject may store no label (ADR 31), so what a blanked field writes is no label
			// rather than an empty one.
			it( 'writes a blanked label as no label, without blocking the save', async () => {
				const onSave = vi.fn().mockResolvedValue( undefined );
				const { wrapper } = await mountWithThreePanesOpen( { onSave } );

				( wrapper.findAllComponents( SubjectEditPane )[ 1 ].vm as any ).setLabel( '   ' );
				await nextTick();
				await triggerSave( wrapper, '' );

				expect( onSave ).toHaveBeenCalledTimes( 1 );
				expect( ( onSave.mock.calls[ 0 ][ 0 ] as Subject ).getId().text ).toBe( 's22222222222222' );
				expect( ( onSave.mock.calls[ 0 ][ 0 ] as Subject ).getLabel() ).toBeNull();
			} );

			// Leaves the dialog open holding a written copy: the state each reset below clears.
			async function partiallySave( onSave: Mock ): Promise<VueWrapper> {
				const { wrapper } = await mountWithSecondPaneOpen( { onSave } );
				await editLabel( wrapper, 'Written root' );
				await makePaneDirty( wrapper, 1 );
				await triggerSave( wrapper, '' );

				expect( ( paneFor( wrapper, rootSubjectId ).props( 'editedCopy' ) as Subject ).getLabel() )
					.toBe( 'Written root' );
				expect( ( wrapper.vm as any ).hasChanged ).toBe( true );

				return wrapper;
			}

			function partialSaveHandler(): Mock {
				return vi.fn()
					.mockResolvedValueOnce( undefined )
					.mockRejectedValueOnce( new ValidationFailedError( [] ) );
			}

			it( 'discards what it wrote when the host replaces the root subject', async () => {
				const wrapper = await partiallySave( partialSaveHandler() );

				await wrapper.setProps( { subject: targetSubject( 's99999999999999', 'New root' ) } );
				// A written copy is keyed by Subject id alone, so without the reset it seeds a pane
				// of a session that never wrote it.
				wrapper.findComponent( SubjectEditPane ).vm.$emit( 'edit-relation-target', new SubjectId( rootSubjectId ) );
				await flushPromises();

				expect( ( wrapper.vm as any ).hasChanged ).toBe( false );
				expect( paneFor( wrapper, rootSubjectId ).props( 'editedCopy' ) ).toBeUndefined();
			} );

			it( 'discards what it wrote when the dialog reopens', async () => {
				const wrapper = await partiallySave( partialSaveHandler() );

				await wrapper.setProps( { open: false } );
				await wrapper.setProps( { open: true } );

				expect( ( wrapper.vm as any ).hasChanged ).toBe( false );
				expect( paneFor( wrapper, rootSubjectId ).props( 'editedCopy' ) ).toBeUndefined();
			} );
		} );

		describe( 'Partial save reporting', () => {
			function partialSaveLine( wrapper: VueWrapper ): DOMWrapper<Element> {
				return wrapper.find( '.ext-neowiki-subject-editor-dialog__partial-save' );
			}

			// By class rather than findComponent: the dialog renders other CdxMessages.
			function partialSaveMessage(
				wrapper: VueWrapper,
			): VueWrapper<InstanceType<typeof CdxMessage>> | undefined {
				return wrapper.findAllComponents( CdxMessage ).find( ( message ) =>
					message.classes().includes( 'ext-neowiki-subject-editor-dialog__partial-save' ) );
			}

			// The failure toast names only the Subject that failed, and then vanishes, while the
			// dialog stays open.
			it( 'reports how many subjects were written when a later one fails', async () => {
				const onSave = vi.fn()
					.mockResolvedValueOnce( undefined )
					.mockRejectedValueOnce( new ValidationFailedError( [] ) );
				const { wrapper } = await mountWithSecondPaneOpen( { onSave } );
				await makePaneDirty( wrapper, 0 );
				await makePaneDirty( wrapper, 1 );

				await triggerSave( wrapper, '' );

				expect( partialSaveLine( wrapper ).text() )
					.toBe( 'neowiki-subject-editor-partial-save12' );
				expect( partialSaveMessage( wrapper )?.props( 'type' ) ).toBe( 'warning' );
			} );

			// Two messages, not one slot they take turns in.
			it( 'leaves the note beside the save button to the scope of the next save', async () => {
				const onSave = vi.fn()
					.mockResolvedValueOnce( undefined )
					.mockRejectedValueOnce( new ValidationFailedError( [] ) );
				const { wrapper } = await mountWithThreePanesOpen( { onSave } );
				await makePaneDirty( wrapper, 0 );
				await makePaneDirty( wrapper, 1 );
				await makePaneDirty( wrapper, 2 );

				await triggerSave( wrapper, '' );

				// Two of the three are still dirty, and both targets are stored on one page.
				expect( footerTextOf( wrapper ) ).toBe( 'neowiki-subject-editor-save-scope21' );
			} );

			it( 'reports nothing when every subject was written', async () => {
				const onSave = vi.fn().mockResolvedValue( undefined );
				const { wrapper } = await mountWithSecondPaneOpen( { onSave } );
				await makePaneDirty( wrapper, 0 );
				await makePaneDirty( wrapper, 1 );

				await triggerSave( wrapper, '' );

				expect( partialSaveLine( wrapper ).exists() ).toBe( false );
				expect( ( mw.notify as Mock ).mock.calls.filter(
					( call ) => typeof call[ 0 ] === 'string' &&
						call[ 0 ].includes( 'neowiki-subject-editor-success' ),
				) ).toHaveLength( 1 );
				expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
			} );

			it( 'clears the report when the next attempt starts', async () => {
				const onSave = vi.fn()
					.mockResolvedValueOnce( undefined )
					.mockRejectedValueOnce( new ValidationFailedError( [] ) );
				const { wrapper } = await mountWithSecondPaneOpen( { onSave } );
				await makePaneDirty( wrapper, 0 );
				await makePaneDirty( wrapper, 1 );
				await triggerSave( wrapper, '' );
				expect( partialSaveLine( wrapper ).text() )
					.toBe( 'neowiki-subject-editor-partial-save12' );

				( wrapper.findAllComponents( SubjectEditPane )[ 0 ].vm as any ).setLabel( '' );
				await nextTick();
				await triggerSave( wrapper, '' );

				expect( partialSaveLine( wrapper ).exists() ).toBe( false );
			} );
		} );

		// An edit made on another Subject goes out with the Save, on that Subject's own page and
		// as its own revision. The note says so, and only when the screen does not.
		describe( 'Save scope', () => {
			// A root Subject that knows which page holds it, as the store's copy does.
			function rootOnPage( pageId: number, pageName: string ): SubjectWithContext {
				return newSubject( {
					id: rootSubjectId,
					label: mockSubject.getLabel(),
					schemaName: 'TestSchema',
					pageIdentifiers: new PageIdentifiers( pageId, pageName ),
				} );
			}

			it( 'says nothing while nothing is dirty', async () => {
				const { wrapper } = await mountWithSecondPaneOpen();

				expect( footerTextOf( wrapper ) ).toBe( '' );
			} );

			it( 'says nothing when the only dirty subject is the one on screen', async () => {
				const { wrapper } = await mountWithSecondPaneOpen();

				await makePaneDirty( wrapper, 1 );

				expect( footerTextOf( wrapper ) ).toBe( '' );
			} );

			it( 'names the scope when the one dirty subject is not on screen', async () => {
				const { wrapper } = await mountWithSecondPaneOpen();

				await makePaneDirty( wrapper, 0 );

				expect( footerTextOf( wrapper ) ).toBe( 'neowiki-subject-editor-save-scope11' );
			} );

			// Two Subjects on one page are one revision, so the count follows the pages.
			it( 'counts two dirty subjects stored on one page as a single page', async () => {
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSubject: rootOnPage( 0, 'TestSubjectPage' ),
				} );

				await makePaneDirty( wrapper, 0 );
				await makePaneDirty( wrapper, 1 );

				expect( footerTextOf( wrapper ) ).toBe( 'neowiki-subject-editor-save-scope21' );
			} );

			it( 'counts dirty subjects stored on different pages separately', async () => {
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSubject: rootOnPage( 7, 'Another page' ),
				} );

				await makePaneDirty( wrapper, 0 );
				await makePaneDirty( wrapper, 1 );

				expect( footerTextOf( wrapper ) ).toBe( 'neowiki-subject-editor-save-scope22' );
			} );

			it( 'leaves a clean subject out of both counts', async () => {
				const { wrapper } = await mountWithThreePanesOpen( {
					rootSubject: rootOnPage( 7, 'Another page' ),
				} );

				await makePaneDirty( wrapper, 0 );

				expect( footerTextOf( wrapper ) ).toBe( 'neowiki-subject-editor-save-scope11' );
			} );
		} );

		// Switching the Subject on screen hides the pane the control that caused it sits in, so
		// nothing may be left holding focus inside a display:none subtree. Mounted into the
		// document throughout: a detached element cannot take focus at all.
		describe( 'Focus on navigation', () => {
			let attached: VueWrapper | null = null;

			beforeEach( () => {
				// Tests that run the real Teleport leave their dialog behind in <body>, ids and all, and
				// focus is located by id here.
				document.body.innerHTML = '';
			} );

			afterEach( () => {
				attached?.unmount();
				attached = null;
			} );

			async function mountAttached(
				rootSchema: Schema = mockSchema,
				rootSubject: Subject = mockSubject,
			): Promise<VueWrapper> {
				const { wrapper } = mountWithTargetRepos(
					undefined, {}, rootSchema, rootSubject, document.body,
				);
				attached = wrapper;
				await flushPromises();
				return wrapper;
			}

			async function openTargetFromForm( wrapper: VueWrapper, id: string ): Promise<void> {
				wrapper.findComponent( SubjectEditPane ).vm.$emit( 'edit-relation-target', new SubjectId( id ) );
				await flushPromises();
			}

			it( 'focuses the newly shown panel when a form control opens a relation target', async () => {
				const wrapper = await mountAttached();

				await openTargetFromForm( wrapper, 's22222222222222' );

				expect( document.activeElement )
					.toBe( wrapper.find( '#ext-neowiki-panel-s22222222222222' ).element );
			} );

			// As the APG tree pattern expects: the node stays the single tab stop.
			it( 'leaves focus on the tree when a node opens a subject', async () => {
				const wrapper = await mountAttached( relationRootSchema, relationRootSubject );
				await openTargetFromForm( wrapper, 's22222222222222' );
				const node = wrapper.find( '.ext-neowiki-tree__node' ).element as HTMLElement;
				node.focus();

				await selectInTree( wrapper, rootSubjectId );

				expect( document.activeElement ).toBe( node );
			} );

		} );

		describe( 'Navigator', () => {
			it( 'renders no navigator when the root schema declares no relations', async () => {
				const { wrapper } = mountWithTargetRepos();
				await flushPromises();

				expect( wrapper.findComponent( SubjectTree ).exists() ).toBe( false );
			} );

			it( 'renders no navigator when the root subject fills none of its declared relations', async () => {
				const { wrapper } = mountWithTargetRepos( undefined, {}, relationRootSchema );
				await flushPromises();

				expect( wrapper.findComponent( SubjectTree ).exists() ).toBe( false );
			} );

			// A relation-change event on the pane is the one path by which a pick or a clear reaches
			// the tree. The root pane is found through the component tree, because a teleported
			// dialog puts its panel outside the wrapper's own element.
			async function setRootFormTargets( wrapper: VueWrapper, ...targetIds: string[] ): Promise<void> {
				editorStatementsBySchema = { TestSchema: [ colleagueStatement( ...targetIds ) ] };
				wrapper.findAllComponents( SubjectEditPane )[ 0 ]
					.findComponent( SubjectEditor ).vm.$emit( 'relation-change' );
				await flushPromises();
			}

			// The accepted cost of gating on the data: the navigator arrives under the user's cursor.
			// Until it does there is no route to the Subject the pick just made reachable.
			it( 'shows the navigator once the form picks the first relation target', async () => {
				const { wrapper } = mountWithTargetRepos(
					undefined, { teleport: false }, relationRootSchema,
				);
				await flushPromises();
				expect( wrapper.findComponent( SubjectTree ).exists() ).toBe( false );

				await setRootFormTargets( wrapper, 's22222222222222' );

				expect( wrapper.findComponent( SubjectTree ).exists() ).toBe( true );
			} );

			it( 'hides the navigator once the form clears the last relation target', async () => {
				const { wrapper } = mountWithTargetRepos(
					undefined, { teleport: false }, relationRootSchema, relationRootSubject,
				);
				await flushPromises();
				expect( wrapper.findComponent( SubjectTree ).exists() ).toBe( true );

				await setRootFormTargets( wrapper );

				expect( wrapper.findComponent( SubjectTree ).exists() ).toBe( false );
			} );

			// A pane holds its values in its inputs' own refs, so an unmount destroys unsaved work.
			// The gate can only flip while the root is the one Subject open — a second pane holds the
			// navigator on by itself — so the root pane is the one at risk.
			it( 'unmounts no pane when a picked target brings the navigator in', async () => {
				const { wrapper } = mountWithTargetRepos(
					undefined, { teleport: false }, relationRootSchema,
				);
				await flushPromises();
				( wrapper.findComponent( SubjectEditPane ).vm as any ).setLabel( 'Edited root' );
				await nextTick();
				expect( wrapper.findComponent( SubjectTree ).exists() ).toBe( false );

				await setRootFormTargets( wrapper, 's22222222222222' );

				expect( wrapper.findComponent( SubjectTree ).exists() ).toBe( true );
				expect( wrapper.findAllComponents( SubjectEditPane ) ).toHaveLength( 1 );
				expect( ( wrapper.findComponent( SubjectEditPane ).vm as any ).label )
					.toBe( 'Edited root' );
			} );

			it( 'unmounts no pane when a cleared target takes the navigator away', async () => {
				const { wrapper } = mountWithTargetRepos(
					undefined, { teleport: false }, relationRootSchema, relationRootSubject,
				);
				await flushPromises();
				( wrapper.findComponent( SubjectEditPane ).vm as any ).setLabel( 'Edited root' );
				await nextTick();
				expect( wrapper.findComponent( SubjectTree ).exists() ).toBe( true );

				await setRootFormTargets( wrapper );

				expect( wrapper.findComponent( SubjectTree ).exists() ).toBe( false );
				expect( wrapper.findAllComponents( SubjectEditPane ) ).toHaveLength( 1 );
				expect( ( wrapper.findComponent( SubjectEditPane ).vm as any ).label )
					.toBe( 'Edited root' );
			} );

			it( 'renders the navigator while only the root subject is open', async () => {
				const { wrapper } = mountWithTargetRepos(
					undefined, {}, relationRootSchema, relationRootSubject,
				);
				await flushPromises();

				expect( wrapper.findComponent( SubjectTree ).exists() ).toBe( true );
			} );

			// The root's last target is cleared while the Subject it led to is still open and dirty.
			// That pane is still written on Save, so hiding the navigator would leave the user no way
			// to review or correct what the next Save writes.
			it( 'keeps the navigator when the last relation target is cleared while another subject is open', async () => {
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
					stubs: { teleport: false },
				} );
				( wrapper.findAllComponents( SubjectEditPane )[ 1 ].vm as any ).setLabel( 'Edited child' );
				await nextTick();
				await selectInTree( wrapper, rootSubjectId );

				await setRootFormTargets( wrapper );

				expect( wrapper.findComponent( SubjectTree ).exists() ).toBe( true );
			} );

			it( 'keeps the dirty subject reachable once the relation that led to it is cleared', async () => {
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
					stubs: { teleport: false },
				} );
				( wrapper.findAllComponents( SubjectEditPane )[ 1 ].vm as any ).setLabel( 'Edited child' );
				await nextTick();
				await selectInTree( wrapper, rootSubjectId );
				await setRootFormTargets( wrapper );

				await clickTreeNode( wrapper, 's22222222222222' );

				expect( teleportedVisibleSubjectId( wrapper ) ).toBe( 's22222222222222' );
				expect( ( wrapper.findAllComponents( SubjectEditPane )[ 1 ].vm as any ).label )
					.toBe( 'Edited child' );
			} );

			// The gate is answered from the root's own statements, never from a fetch, so the
			// navigator is there on the first paint or not at all. The never-settling label fetch
			// below is what tells that apart from a gate settled one fetch later.
			it( 'renders the navigator before any label fetch resolves', async () => {
				useSubjectStore().getOrFetchSubject = vi.fn( () => new Promise<never>( () => {
					// Intentionally left pending.
				} ) );

				const { wrapper } = mountWithTargetRepos(
					undefined, { teleport: false }, relationRootSchema, relationRootSubject,
				);

				expect( wrapper.findComponent( SubjectTree ).exists() ).toBe( true );
				await nextTick();
				expect( wrapper.findComponent( SubjectTree ).exists() ).toBe( true );
				await flushPromises();
				expect( wrapper.findComponent( SubjectTree ).exists() ).toBe( true );
			} );

			it( 'carries --wide once the root subject reaches a relation target', async () => {
				const { wrapper } = mountWithTargetRepos(
					undefined, {}, relationRootSchema, relationRootSubject,
				);
				await flushPromises();

				expect( wrapper.find( '.cdx-dialog' ).classes() ).toContain( 'ext-neowiki-subject-editor-dialog--wide' );
			} );

			// The width follows the navigator's own gate.
			it( 'drops --wide when a schema edit takes the navigator away', async () => {
				const { wrapper } = mountWithTargetRepos(
					undefined, {}, relationRootSchema, relationRootSubject,
				);
				await flushPromises();
				expect( wrapper.find( '.cdx-dialog' ).classes() ).toContain( 'ext-neowiki-subject-editor-dialog--wide' );

				// mockSchema declares no relation property at all.
				wrapper.findComponent( SchemaEditorDialog ).vm.$emit( 'saved', mockSchema );
				await flushPromises();

				expect( wrapper.find( '.cdx-dialog' ).classes() )
					.not.toContain( 'ext-neowiki-subject-editor-dialog--wide' );
			} );

			// A second open Subject holds the navigator on, and with it the width, however the
			// root's own relations end up.
			it( 'keeps --wide while a second subject is open without the root reaching one', async () => {
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
				} );

				wrapper.findComponent( SchemaEditorDialog ).vm.$emit( 'saved', mockSchema );
				await flushPromises();

				expect( wrapper.findAllComponents( SubjectEditPane ) ).toHaveLength( 2 );
				expect( wrapper.find( '.cdx-dialog' ).classes() )
					.toContain( 'ext-neowiki-subject-editor-dialog--wide' );
			} );

			// The tree remembers which fetches failed for the life of its mount, which is only safe
			// because the tree is keyed on the dialog's open epoch. The tests below are the two
			// halves of that one contract.
			describe( 'Failed tree fetches', () => {
				function failingTreeFetch(): Mock {
					const failing = vi.fn().mockRejectedValue( new Error( 'Target is gone' ) );
					useSubjectStore().getOrFetchSubject = failing;
					return failing;
				}

				// teleport: false throughout: these tests turn on whether the tree kept its mount, and
				// the stubbed teleport rebuilds its children on every re-render of the dialog.

				// The key Vue rendered the tree with, read off its vnode: nothing about it reaches the DOM.
				function treeKey( wrapper: VueWrapper ): unknown {
					return ( wrapper.findComponent( SubjectTree ).vm.$ as any ).vnode.key;
				}

				async function mountWithFailingTarget(): Promise<{ wrapper: VueWrapper; failing: Mock }> {
					const failing = failingTreeFetch();
					const { wrapper } = mountWithTargetRepos(
						undefined, { teleport: false }, relationRootSchema, relationRootSubject,
					);
					await flushPromises();
					return { wrapper, failing };
				}

				it( 'attempts a failing target fetch once for the life of one opening', async () => {
					const { wrapper, failing } = await mountWithFailingTarget();
					expect( failing ).toHaveBeenCalledTimes( 1 );

					// A second pane changes the copies the tree walks, so it re-walks: the recompute that
					// would re-issue the failing request.
					wrapper.findComponent( SubjectEditPane )
						.vm.$emit( 'edit-relation-target', new SubjectId( 's33333333333333' ) );
					await flushPromises();

					expect( failing ).toHaveBeenCalledTimes( 1 );
				} );

				it( 'retries a failed target fetch when the dialog is reopened', async () => {
					const { wrapper, failing } = await mountWithFailingTarget();
					expect( failing ).toHaveBeenCalledTimes( 1 );

					await wrapper.setProps( { open: false } );
					await wrapper.setProps( { open: true } );
					await flushPromises();

					expect( failing ).toHaveBeenCalledTimes( 2 );
				} );

				// The retry above passes without our key, because Codex's own v-if unmounts the tree on
				// close whichever way it is bound. What that retry means to prove is asserted here, on
				// the binding itself.
				it( 'keys the tree on a fresh epoch each time the dialog opens', async () => {
					const { wrapper } = mountWithTargetRepos(
						undefined, { teleport: false }, relationRootSchema, relationRootSubject,
					);
					await flushPromises();
					const firstKey = treeKey( wrapper );

					await wrapper.setProps( { open: false } );
					await wrapper.setProps( { open: true } );
					await flushPromises();

					// Greater, not merely different: a key bound to any constant is still a number.
					expect( treeKey( wrapper ) as number ).toBeGreaterThan( firstKey as number );
				} );

				it( 'keys the tree on a fresh epoch when the host replaces the root subject', async () => {
					const { wrapper } = mountWithTargetRepos(
						undefined, { teleport: false }, relationRootSchema, relationRootSubject,
					);
					await flushPromises();
					const firstKey = treeKey( wrapper );

					await wrapper.setProps( {
						subject: new Subject(
							new SubjectId( 's99999999999999' ),
							'New root',
							'New root',
							'TestSchema',
							new StatementList( [ colleagueStatement( 's22222222222222' ) ] ),
						),
					} );
					await flushPromises();

					expect( treeKey( wrapper ) ).not.toBe( firstKey );
				} );

				// Asserted on the label the node ends up showing rather than on a call count: the new
				// root's form holds the same relation and its picker fetches that target too, so a count
				// cannot tell the tree's request from the form's.
				it( 'resolves a previously failed target when the host replaces the root subject', async () => {
					const { wrapper, failing } = await mountWithFailingTarget();
					expect( treeNodeLabel( wrapper, 's22222222222222' ) ).toBe( 's22222222222222' );

					// The fetch that failed now succeeds, so only a tree that forgot the failure shows
					// the label.
					failing.mockResolvedValue( targetSubject( 's22222222222222', 'Target subject' ) );

					await wrapper.setProps( {
						subject: new Subject(
							new SubjectId( 's99999999999999' ),
							'New root',
							'New root',
							'TestSchema',
							new StatementList( [ colleagueStatement( 's22222222222222' ) ] ),
						),
					} );
					await flushPromises();

					expect( treeNodeLabel( wrapper, 's22222222222222' ) ).toBe( 'Target subject' );
				} );
			} );

			// Auto-placement puts the two surfaces in their columns and `__surface + __surface` draws
			// the rule between them, so both rest on this order. jsdom resolves no layout: this pins
			// the structure, and the placement itself is verified in the browser.
			it( 'renders the navigator and the pane region as sibling surfaces, navigator first', async () => {
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
				} );

				const content = wrapper.find( '.ext-neowiki-subject-editor-dialog__content' );

				expect( Array.from( content.element.children ).map( ( child ) => child.className ) )
					.toEqual( [
						'ext-neowiki-subject-editor-dialog__surface',
						'ext-neowiki-subject-editor-dialog__surface',
					] );
			} );

			it( 'renders the navigator inside the first surface', async () => {
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
				} );

				const surfaces = wrapper.findAll(
					'.ext-neowiki-subject-editor-dialog__content > .ext-neowiki-subject-editor-dialog__surface',
				);

				expect( surfaces[ 0 ].find( '.ext-neowiki-subject-tree' ).exists() ).toBe( true );
				expect( surfaces[ 1 ].find( '.ext-neowiki-subject-editor-dialog__panels' ).exists() ).toBe( true );
			} );

			it( 'renders only the form surface when there is no navigator', async () => {
				const wrapper = mountComponent( false, {} );
				await flushPromises();

				const surfaces = wrapper.findAll(
					'.ext-neowiki-subject-editor-dialog__content > .ext-neowiki-subject-editor-dialog__surface',
				);

				expect( surfaces ).toHaveLength( 1 );
				expect( surfaces[ 0 ].find( '.ext-neowiki-subject-tree' ).exists() ).toBe( false );
				expect( surfaces[ 0 ].find( '.ext-neowiki-subject-editor-dialog__panels' ).exists() ).toBe( true );
			} );

			it( 'opens the selected subject when a tree node is chosen', async () => {
				const { wrapper, mockSubjectRepository } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
				} );
				mockSubjectRepository.getSubject.mockClear();

				wrapper.findComponent( SubjectTree ).vm.$emit( 'select', new SubjectId( 's33333333333333' ) );
				await flushPromises();

				expect( mockSubjectRepository.getSubject ).toHaveBeenCalledTimes( 1 );
				expect( wrapper.findAllComponents( SubjectEditPane ) ).toHaveLength( 3 );
			} );

			// The gate can only flip while the root is the one Subject open, so the pane at risk
			// from a schema-driven flip is the root's own.
			it( 'keeps a dirty pane mounted when a schema edit brings the navigator in', async () => {
				const onSave = vi.fn().mockResolvedValue( undefined );
				const { wrapper } = mountWithTargetRepos(
					onSave, { teleport: false }, mockSchema, relationRootSubject,
				);
				await flushPromises();
				await makePaneDirty( wrapper, 0 );
				expect( wrapper.findComponent( SubjectTree ).exists() ).toBe( false );
				expect( ( wrapper.vm as any ).hasChanged ).toBe( true );

				wrapper.findComponent( SchemaEditorDialog ).vm.$emit( 'saved', relationRootSchema );
				await flushPromises();

				expect( wrapper.findComponent( SubjectTree ).exists() ).toBe( true );
				expect( wrapper.findAllComponents( SubjectEditPane ) ).toHaveLength( 1 );
				expect( ( wrapper.vm as any ).hasChanged ).toBe( true );

				await triggerSave( wrapper, '' );

				expect( onSave ).toHaveBeenCalledTimes( 1 );
				expect( onSave.mock.calls[ 0 ][ 0 ].getId().text ).toBe( rootSubjectId );
			} );

			// Only the tree may go: an unmounted pane takes its unsaved values with it, since they
			// live in its inputs' refs.
			it( 'keeps a dirty pane mounted when a schema edit takes the navigator away', async () => {
				const onSave = vi.fn().mockResolvedValue( undefined );
				const { wrapper } = mountWithTargetRepos(
					onSave, { teleport: false }, relationRootSchema, relationRootSubject,
				);
				await flushPromises();
				await makePaneDirty( wrapper, 0 );
				expect( wrapper.findComponent( SubjectTree ).exists() ).toBe( true );
				expect( ( wrapper.vm as any ).hasChanged ).toBe( true );

				// mockSchema declares no relation property, so saving it deletes the last one.
				wrapper.findComponent( SchemaEditorDialog ).vm.$emit( 'saved', mockSchema );
				await flushPromises();

				expect( wrapper.findComponent( SubjectTree ).exists() ).toBe( false );
				expect( wrapper.findAllComponents( SubjectEditPane ) ).toHaveLength( 1 );
				expect( ( wrapper.vm as any ).hasChanged ).toBe( true );

				await triggerSave( wrapper, '' );

				expect( onSave ).toHaveBeenCalledTimes( 1 );
				expect( onSave.mock.calls[ 0 ][ 0 ].getId().text ).toBe( rootSubjectId );
			} );

			// Asserted on the rendered dot, not on the unsavedIds prop: that prop restates what the
			// dialog already knows and says nothing about whether the tree has a node to hang the
			// dot on.
			it( 'keeps the tree\'s unsaved dot for a subject that is no longer on screen', async () => {
				useSubjectStore().setSubject( targetSubject( 's22222222222222', 'Target subject' ) );
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
				} );
				await makePaneDirty( wrapper, 1 );

				await selectInTree( wrapper, rootSubjectId );

				expect( visibleSubjectId( wrapper ) ).toBe( rootSubjectId );
				expect( treeNodeHasDot( wrapper, 's22222222222222' ) ).toBe( true );
			} );

			// A target picked in this session lives only in the form, so a tree walking the stored
			// data has no node for it and the dot has nowhere to render.
			it( 'shows the tree\'s unsaved dot for a target picked in this session and edited', async () => {
				useSubjectStore().setSubject( targetSubject( 's22222222222222', 'Target subject' ) );
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					stubs: { teleport: false },
				} );
				// The stored root reaches nothing, so the open Subject starts outside the walk.
				expect( edgeCaptions( wrapper ) ).toEqual( [ 'Not linked here' ] );

				await setRootFormTargets( wrapper, 's22222222222222' );

				expect( edgeCaptions( wrapper ) ).toEqual( [ 'Colleague' ] );
				expect( treeHasNode( wrapper, 's22222222222222' ) ).toBe( true );

				await makePaneDirty( wrapper, 1 );
				await flushPromises();

				expect( treeNodeHasDot( wrapper, 's22222222222222' ) ).toBe( true );
			} );

			// Once the relation is gone the walk cannot reach the Subject at all, yet the save still
			// writes it. The root keeps a second target throughout, because a root reaching nothing
			// has no navigator for the dot to live in.
			it( 'keeps the dot after the relation to an edited subject is removed from the form', async () => {
				useSubjectStore().setSubject( targetSubject( 's22222222222222', 'Target subject' ) );
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: rootSubjectWithTargets( 's22222222222222', 's33333333333333' ),
				} );
				await makePaneDirty( wrapper, 1 );
				await flushPromises();
				expect( treeNodeHasDot( wrapper, 's22222222222222' ) ).toBe( true );

				// The harvest drops a statement with no value, so the form no longer links the target.
				await setRootFormTargets( wrapper, 's33333333333333' );

				expect( treeNodeHasDot( wrapper, 's22222222222222' ) ).toBe( true );
			} );

			// The cost of the live tree: a walk per relation pick is affordable, one per keystroke
			// is not.
			it( 'refreshes the tree\'s subjects on a relation change and not on an ordinary edit', async () => {
				useSubjectStore().setSubject( targetSubject( 's22222222222222', 'Target subject' ) );
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
				} );
				const rootEditor = paneFor( wrapper, rootSubjectId ).findComponent( SubjectEditor );
				const before = wrapper.findComponent( SubjectTree ).props( 'editedSubjects' );

				rootEditor.vm.$emit( 'change' );
				await nextTick();

				// The same object, so the computed did not recompute and no walk ran.
				expect( wrapper.findComponent( SubjectTree ).props( 'editedSubjects' ) ).toBe( before );

				rootEditor.vm.$emit( 'relation-change' );
				await nextTick();

				expect( wrapper.findComponent( SubjectTree ).props( 'editedSubjects' ) ).not.toBe( before );
			} );

			// The live label lives on the pane, not on the copy the tree walks, which is refreshed
			// on relation changes alone.
			it( 'renames the tree\'s root node when the subject is renamed in the header', async () => {
				useSubjectStore().setSubject( targetSubject( 's22222222222222', 'Target subject' ) );
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
				} );
				expect( treeNodeLabel( wrapper, rootSubjectId ) ).toBe( 'Test Subject' );

				await editLabel( wrapper, 'Renamed Subject' );

				expect( treeNodeLabel( wrapper, rootSubjectId ) ).toBe( 'Renamed Subject' );
			} );

			// A cleared field means no label, not an empty one, so the node falls back to a name
			// rather than rendering a blank row the user cannot read or aim at.
			it( 'keeps a name on the tree\'s root node when its label is cleared', async () => {
				useSubjectStore().setSubject( targetSubject( 's22222222222222', 'Target subject' ) );
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
				} );

				await editLabel( wrapper, '' );

				expect( treeNodeLabel( wrapper, rootSubjectId ) ).toBe( 'Test Subject' );
			} );

			// A child Subject has no rename control of its own, but the pane that edits it already
			// owns its label.
			it( 'renames the tree\'s node for a child subject its pane renames', async () => {
				useSubjectStore().setSubject( targetSubject( 's22222222222222', 'Target subject' ) );
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
				} );
				expect( treeNodeLabel( wrapper, 's22222222222222' ) ).toBe( 'Target subject' );

				( wrapper.findAllComponents( SubjectEditPane )[ 1 ].vm as any ).setLabel( 'Renamed child' );
				await nextTick();

				expect( treeNodeLabel( wrapper, 's22222222222222' ) ).toBe( 'Renamed child' );
			} );
		} );
	} );

	describe( 'Progressive disclosure', () => {
		const relationSchema = new Schema( 'Person', 'A person', new PropertyDefinitionList( [
			createPropertyDefinitionFromJson( 'Birth event', { type: 'relation', targetSchema: 'Birth' } ),
		] ) );
		const plainSchema = new Schema( 'Attendance', 'A count', new PropertyDefinitionList( [
			createPropertyDefinitionFromJson( 'Visitors', { type: 'number' } ),
		] ) );

		// One Birth event target stored, so the tree has a node beyond the root to show.
		const relatedSubject = new Subject(
			mockSubject.getId(),
			mockSubject.getLabel(),
			mockSubject.getDisplayName(),
			'TestSchema',
			new StatementList( [ new Statement(
				new PropertyName( 'Birth event' ),
				'relation',
				new RelationValue( [ newRelation( undefined, 's44444444444444' ) ] ),
			) ] ),
		);

		function navigatorRendered( wrapper: VueWrapper ): boolean {
			return wrapper.findComponent( SubjectTree ).exists();
		}

		// The navigator's own width.
		function reservesNavigatorWidth( wrapper: VueWrapper ): boolean {
			return wrapper.find( '.cdx-dialog' ).classes()
				.includes( 'ext-neowiki-subject-editor-dialog--wide' );
		}

		it( 'renders no navigator for a schema that declares no relations', async () => {
			const wrapper = mountComponent( false, saveButtonTestStubs, undefined, plainSchema );
			await flushPromises();

			expect( navigatorRendered( wrapper ) ).toBe( false );
		} );

		// The gate is on the data, not the Schema: a declared relation with nothing in it would
		// give the user a panel holding its own root node and navigating nowhere.
		it( 'renders no navigator for a declared relation with no target', async () => {
			const wrapper = mountComponent( false, saveButtonTestStubs, undefined, relationSchema );
			await flushPromises();

			expect( navigatorRendered( wrapper ) ).toBe( false );
			expect( reservesNavigatorWidth( wrapper ) ).toBe( false );
		} );

		it( 'renders the navigator for a declared relation with a target', async () => {
			const wrapper = mountComponent(
				false, saveButtonTestStubs, undefined, relationSchema, {}, relatedSubject,
			);
			await flushPromises();

			expect( navigatorRendered( wrapper ) ).toBe( true );
			expect( reservesNavigatorWidth( wrapper ) ).toBe( true );
		} );

		// A statement whose property the Schema does not declare is not read at all.
		it( 'shows the navigator once a schema edit declares the property a target is stored under', async () => {
			const wrapper = mountComponent(
				false, saveButtonTestStubs, undefined, plainSchema, {}, relatedSubject,
			);
			await flushPromises();
			expect( navigatorRendered( wrapper ) ).toBe( false );

			wrapper.findComponent( SchemaEditorDialog ).vm.$emit( 'saved', relationSchema );
			await flushPromises();

			expect( navigatorRendered( wrapper ) ).toBe( true );
		} );
	} );

	// Codex draws the rules under the header and above the footer only for a dialog whose own
	// body scrolls, which this one's never does, so the variant is asked for by name.
	it( 'asks Codex for the dividers variant, navigator or no navigator', async () => {
		const wrapper = mountComponent( false, saveButtonTestStubs );
		await flushPromises();

		expect( wrapper.findComponent( SubjectTree ).exists() ).toBe( false );
		expect( wrapper.find( '.cdx-dialog' ).classes() ).toContain( 'cdx-dialog--dividers' );
	} );

	describe( 'Edit notices', () => {
		it( 'loads the viewed page\'s notices when the dialog opens', async () => {
			const getNotices = vi.fn().mockResolvedValue( [] );
			const repository = vi.spyOn( NeoWikiExtension.getInstance(), 'getEditNoticeRepository' )
				.mockReturnValue( { getNotices } as never );

			mountComponent( false, saveButtonTestStubs );
			await flushPromises();

			// Keyed on the page being viewed and the root Subject's Schema: a pane opened on a
			// target stored elsewhere still shows this page's notices.
			expect( getNotices ).toHaveBeenCalledWith( 42, 'TestSchema' );

			repository.mockRestore();
		} );

		// The notices take the grid's first row across both columns and the panes row takes what
		// they leave, so a notice rendered anywhere else is height that row does not account for.
		// jsdom resolves no layout: this pins the structure, and the sizing is verified in the
		// browser.
		it( 'renders the notices as the grid\'s first child, above the panes row', async () => {
			const repository = vi.spyOn( NeoWikiExtension.getInstance(), 'getEditNoticeRepository' )
				.mockReturnValue( {
					getNotices: vi.fn().mockResolvedValue( [ { key: 'editnotice-0', html: '<p>Heads up</p>' } ] ),
				} as never );

			const wrapper = mountComponent( false, saveButtonTestStubs );
			await flushPromises();

			const content = wrapper.find( '.ext-neowiki-subject-editor-dialog__content' );
			const children = Array.from( content.element.children );

			expect( children.map( ( child ) => child.className ) ).toEqual( [
				'ext-neowiki-edit-notices',
				'ext-neowiki-subject-editor-dialog__surface',
			] );

			repository.mockRestore();
		} );
	} );
} );
