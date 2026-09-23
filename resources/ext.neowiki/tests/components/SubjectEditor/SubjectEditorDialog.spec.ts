import { mount, VueWrapper, DOMWrapper, flushPromises } from '@vue/test-utils';
import { inject, nextTick } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi, type Mock } from 'vitest';
import SubjectEditorDialog from '@/components/SubjectEditor/SubjectEditorDialog.vue';
import { Subject } from '@/domain/Subject.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { Statement } from '@/domain/Statement.ts';
import { PropertyName } from '@/domain/PropertyDefinition.ts';
import { newRelation, newStringValue, RelationValue } from '@/domain/Value.ts';
import { subjectDisplayName } from '@/presentation/subjectDisplayName.ts';
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
import OpenSubjectList from '@/components/SubjectEditor/OpenSubjectList.vue';
import PaneDivider from '@/components/common/PaneDivider.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import { CdxDialog, CdxMessage } from '@wikimedia/codex';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { ValidationFailedError } from '@/persistence/ValidationFailedError';
import type { SubjectViolation } from '@/domain/SubjectViolation';
import type { SaveBlocker } from '@/components/common/SaveBlocker.ts';
import { newSubject } from '@/TestHelpers.ts';
import { StubSubjectRepository } from '@/domain/SubjectRepository.ts';
import { SubjectCreationKey, type SubjectCreation } from '@/components/common/SubjectCreation.ts';
import { SubjectIdInUseError } from '@/persistence/SubjectIdInUseError';
import { PageIdentifiers } from '@/domain/PageIdentifiers.ts';
import type { SubjectWithContext } from '@/domain/SubjectWithContext.ts';

const $i18n = createI18nMock();

// The reason the stubbed editor reports for holding the save. Reset per test by the
// beforeEach below.
let editorSaveBlocker: SaveBlocker | null = null;
// Keyed by Schema name, so a multi-pane test can block one pane while the others stay
// saveable.
let editorSaveBlockerBySchema: Record<string, SaveBlocker | null> = {};
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
		const saveBlocker = (): SaveBlocker | null => {
			const schemaName = props.schema?.getName();
			if ( schemaName !== undefined && schemaName in editorSaveBlockerBySchema ) {
				return editorSaveBlockerBySchema[ schemaName ];
			}
			return editorSaveBlocker;
		};
		return { getSubjectData, saveBlocker };
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
		editorSaveBlocker = null;
		editorSaveBlockerBySchema = {};
		editorStatementsBySchema = {};
		setupMwMock( {
			// 'util' for the relation fields and a nested pane's storage line: both call mw.util.getUrl.
			functions: [ 'message', 'msg', 'notify', 'config', 'util' ],
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
		null,
	);

	const mockSubject = new Subject(
		new SubjectId( 's1demo5sssssss1' ),
		'Test Subject',
		'Test Subject',
		false,
		'TestSchema',
		new StatementList( [] ),
	);

	const labellessSubject = new Subject(
		new SubjectId( 's1demo5sssssss2' ),
		null,
		'Host Page',
		false,
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
		// Left out by every host that cannot create Subjects, which is what the dialog reads
		// to decide whether the relation fields are offered creation at all.
		onCreate: ( ( subject: any, pageId: number, comment: string ) => Promise<void> ) | undefined = undefined,
		extraProps: Record<string, unknown> = {},
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
				onCreate,
				open: true,
				...extraProps,
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
			null,
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

	// The Schema is shown by the pane that uses it, not by a header that names the root's
	// whichever pane is on screen.
	it( 'shows the schema in the pane rather than the dialog header', async () => {
		const wrapper = mountComponent( true, {} );
		await flushPromises();

		expect( wrapper.find( '.cdx-dialog__header__subtitle' ).exists() ).toBe( false );
		expect( wrapper.get( '.ext-neowiki-subject-edit-pane__meta .ext-neowiki-schema-name__text' ).text() )
			.toBe( 'TestSchema' );
	} );

	// The badge is a link to the Schema page whoever is looking, so it carries its own
	// interactive styling; only the click is taken over, and only for someone who may edit.
	it( 'lets the schema link navigate for a user who cannot edit it', async () => {
		const wrapper = mountComponent( false, {} );
		await flushPromises();

		const badge = wrapper.get( '.ext-neowiki-subject-edit-pane__meta a.ext-neowiki-schema-name' );
		const event = new MouseEvent( 'click', { button: 0, bubbles: true, cancelable: true } );
		badge.element.dispatchEvent( event );
		await flushPromises();

		expect( event.defaultPrevented ).toBe( false );
		expect( wrapper.findComponent( SchemaEditorDialog ).props( 'open' ) ).toBe( false );
	} );

	it( 'leaves a modified click to the browser, so the Schema page stays reachable', async () => {
		const wrapper = mountComponent( true, {} );
		await flushPromises();

		const badge = wrapper.get( '.ext-neowiki-subject-edit-pane__meta a.ext-neowiki-schema-name' );
		const event = new MouseEvent( 'click', { button: 0, ctrlKey: true, bubbles: true, cancelable: true } );
		badge.element.dispatchEvent( event );
		await flushPromises();

		expect( event.defaultPrevented ).toBe( false );
		expect( wrapper.findComponent( SchemaEditorDialog ).props( 'open' ) ).toBe( false );
	} );

	it( 'opens SchemaEditorDialog from the pane\'s schema badge', async () => {
		const wrapper = mountComponent( true, {} );
		await flushPromises();

		const schemaBadge = wrapper.get( '.ext-neowiki-subject-edit-pane__meta a.ext-neowiki-schema-name' );
		const event = new MouseEvent( 'click', { button: 0, bubbles: true, cancelable: true } );
		schemaBadge.element.dispatchEvent( event );
		await flushPromises();

		const schemaEditorDialog = wrapper.findComponent( SchemaEditorDialog );
		expect( schemaEditorDialog.exists() ).toBe( true );
		expect( schemaEditorDialog.props( 'open' ) ).toBe( true );
		// Asserted with the opening: without it the editor opens AND the link is followed,
		// which in a dialog holding unsaved panes is the worse half of the pair.
		expect( event.defaultPrevented ).toBe( true );
	} );

	// Every gesture a browser uses to open a link somewhere of the reader's choosing. Cmd is
	// the one that matters most and the one a ctrl-only guard would silently drop on macOS.
	it.each( [
		[ 'a middle click', { button: 1 } ],
		[ 'a ctrl click', { button: 0, ctrlKey: true } ],
		[ 'a cmd click', { button: 0, metaKey: true } ],
		[ 'a shift click', { button: 0, shiftKey: true } ],
		[ 'an alt click', { button: 0, altKey: true } ],
	] )( 'leaves %s to the browser rather than opening the schema editor', async ( _name, init ) => {
		const wrapper = mountComponent( true, {} );
		await flushPromises();

		const schemaBadge = wrapper.get( '.ext-neowiki-subject-edit-pane__meta a.ext-neowiki-schema-name' );
		const event = new MouseEvent( 'click', { bubbles: true, cancelable: true, ...init } );
		schemaBadge.element.dispatchEvent( event );
		await flushPromises();

		expect( event.defaultPrevented ).toBe( false );
		expect( wrapper.findComponent( SchemaEditorDialog ).props( 'open' ) ).toBe( false );
	} );

	// The dialog holds unsaved edits for every open pane and nothing guards a navigation away
	// from it, so following this link must never replace the page the dialog is on.
	it( 'opens the schema page in a new tab', async () => {
		const wrapper = mountComponent( true, {} );
		await flushPromises();

		const badge = wrapper.get( '.ext-neowiki-subject-edit-pane__meta a.ext-neowiki-schema-name' );

		expect( badge.attributes( 'target' ) ).toBe( '_blank' );
		expect( badge.attributes( 'rel' ) ).toBe( 'noopener' );
	} );

	it( 'renders the dialog title as a visible heading', async () => {
		const wrapper = mountComponent( true, {} );
		await flushPromises();

		expect( wrapper.get( '.cdx-dialog__header__title' ).text() )
			.toBe( 'neowiki-subject-editor-title' );
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

		it( 'drops a violation the schema saved through the nested schema editor resolves', async () => {
			useSubjectStore().validateSubjectUpdate = vi.fn()
				.mockResolvedValueOnce( [ dryRunViolation ] )
				.mockResolvedValue( [] );
			const wrapper = mountComponent( true, validationTestStubs );
			await flushPromises();
			expect( wrapper.findComponent( SubjectEditor ).props( 'serverViolations' ) ).toEqual( [ dryRunViolation ] );

			wrapper.findComponent( SchemaEditorDialog ).vm.$emit( 'saved', schemaWithProperty( 'name' ) );
			await flushPromises();

			expect( wrapper.findComponent( SubjectEditor ).props( 'serverViolations' ) ).toEqual( [] );
		} );

		it( 'surfaces a violation the schema saved through the nested schema editor introduces', async () => {
			useSubjectStore().validateSubjectUpdate = vi.fn()
				.mockResolvedValueOnce( [] )
				.mockResolvedValue( [ dryRunViolation ] );
			const wrapper = mountComponent( true, validationTestStubs );
			await flushPromises();
			expect( wrapper.findComponent( SubjectEditor ).props( 'serverViolations' ) ).toEqual( [] );

			wrapper.findComponent( SchemaEditorDialog ).vm.$emit( 'saved', schemaWithProperty( 'name' ) );
			await flushPromises();

			expect( wrapper.findComponent( SubjectEditor ).props( 'serverViolations' ) ).toEqual( [ dryRunViolation ] );
		} );

		it( 'leaves a schema change alone while the dialog is closed', async () => {
			const validate = vi.fn().mockResolvedValue( [] );
			useSubjectStore().validateSubjectUpdate = validate;
			const wrapper = mountComponent( true, validationTestStubs );
			await flushPromises();
			await wrapper.setProps( { open: false } );
			validate.mockClear();

			await wrapper.setProps( { schema: schemaWithProperty( 'nickname' ) } );
			await flushPromises();

			expect( validate ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'Unparseable field input', () => {
		it( 'does not save while a field holds text that cannot be turned into a value', async () => {
			const onSave = vi.fn().mockResolvedValue( undefined );
			const wrapper = mountComponent( false, validationTestStubs, onSave );
			await flushPromises();
			editorSaveBlocker = { propertyName: 'Score', message: 'neowiki-field-invalid-number' };

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
			editorSaveBlocker = { propertyName: 'Score', message: 'whatever the field shows' };

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
			editorSaveBlocker = { propertyName: 'Score', message: 'neowiki-field-invalid-number' };
			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			editorSaveBlocker = null;
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
			editorSaveBlocker = { propertyName: 'Score', message: 'neowiki-field-invalid-number' };
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

		it( 'names a subject its Schema\'s template names by the label its fields give when the save succeeds', async () => {
			const titledSchema = new Schema(
				'TestSchema',
				'A test schema',
				new PropertyDefinitionList( [ createPropertyDefinitionFromJson( 'Name', { type: 'text' } ) ] ),
				'{Name}',
			);
			const namedAda = new Subject(
				mockSubject.getId(),
				null,
				'Ada',
				false,
				'TestSchema',
				new StatementList( [ new Statement( new PropertyName( 'Name' ), 'text', newStringValue( 'Ada' ) ) ] ),
			);
			const onSave = vi.fn().mockResolvedValue( undefined );
			const wrapper = mountComponent( false, validationTestStubs, onSave, titledSchema );
			await wrapper.setProps( { subject: namedAda } );
			await flushPromises();

			editorStatementsBySchema = { TestSchema: [ new Statement( new PropertyName( 'Name' ), 'text', newStringValue( 'Grace' ) ) ] };
			await wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( mw.notify ).toHaveBeenCalledWith( 'neowiki-subject-editor-successGrace', { type: 'success' } );
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
		// replaces the rendered title. It names the task, not a Subject: the dialog edits
		// several, and a name fixed at open would be wrong the moment another pane is shown.
		it( 'names the dialog after the task rather than after a subject', async () => {
			const wrapper = mountComponent(
				false, validationTestStubs, undefined, mockSchema, {}, labellessSubject,
			);
			await flushPromises();

			// Codex points the dialog at its own heading when nothing slots a header over it,
			// so the announced name is the visible one rather than a parallel string.
			const heading = wrapper.get( '.cdx-dialog__header__title' );
			expect( wrapper.get( '.cdx-dialog' ).attributes( 'aria-labelledby' ) )
				.toBe( heading.attributes( 'id' ) );
			expect( heading.text() ).toBe( 'neowiki-subject-editor-title' );
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
					false,
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
			null,
		);

		// The module-wide mockSchema declares no relation and mockSubject stores no target, so no
		// navigator is rendered anywhere in "Panes" except where these two fixtures are passed.
		const relationRootSchema = new Schema(
			'TestSchema',
			'A test schema',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'Colleague', { type: 'relation', targetSchema: 'Person' } ),
			] ),
			null,
		);

		function colleagueStatement( ...targetIds: string[] ): Statement {
			return new Statement(
				new PropertyName( 'Colleague' ),
				'relation',
				new RelationValue( targetIds.map( ( id ) => newRelation( undefined, id ) ) ),
			);
		}

		// mockSubject with the given Colleague targets stored.
		function rootSubjectWithTargets( ...targetIds: string[] ): Subject {
			return new Subject(
				mockSubject.getId(),
				mockSubject.getLabel(),
				mockSubject.getDisplayName(),
				false,
				'TestSchema',
				new StatementList( [ colleagueStatement( ...targetIds ) ] ),
			);
		}

		const relationRootSubject = rootSubjectWithTargets( 's22222222222222' );

		function nameStatement( name: string ): Statement {
			return new Statement( new PropertyName( 'Name' ), 'text', newStringValue( name ) );
		}

		// Names its Subjects by their Name, so a root Subject without a label of its own is named
		// by what its form holds.
		const templatedRootSchema = new Schema(
			'TestSchema',
			'A test schema',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'Colleague', { type: 'relation', targetSchema: 'Person' } ),
				createPropertyDefinitionFromJson( 'Name', { type: 'text' } ),
			] ),
			'{Name}',
		);

		const templateNamedRootSubject = new Subject(
			mockSubject.getId(),
			null,
			'Alice',
			false,
			'TestSchema',
			new StatementList( [ nameStatement( 'Alice' ), colleagueStatement( 's22222222222222' ) ] ),
		);

		// Through the list component rather than the root wrapper: the real Teleport moves the
		// dialog out of the wrapper's own element.
		function listRow( wrapper: VueWrapper, id: string ): Omit<DOMWrapper<Element>, 'exists'> {
			return wrapper.findComponent( OpenSubjectList )
				.get( `[data-mw-neowiki-subject-id="${ id }"]` );
		}

		function listHasRow( wrapper: VueWrapper, id: string ): boolean {
			const list = wrapper.findComponent( OpenSubjectList );
			return list.exists() && list.find( `[data-mw-neowiki-subject-id="${ id }"]` ).exists();
		}

		// In printed order, which is the order the subjects were opened in.
		function listedSubjectIds( wrapper: VueWrapper ): string[] {
			return wrapper.findComponent( OpenSubjectList ).findAll( '[role="option"]' )
				.map( ( row ) => row.attributes( 'data-mw-neowiki-subject-id' ) as string );
		}

		// Asserted false means "listed, and clean". A row that is not there at all is a different
		// failure — an unmounted pane loses unsaved work — so it throws rather than reading false.
		function listRowHasDot( wrapper: VueWrapper, id: string ): boolean {
			expect( listHasRow( wrapper, id ) ).toBe( true );
			return listRow( wrapper, id ).find( '.ext-neowiki-unsaved-dot' ).exists();
		}

		function listRowLabel( wrapper: VueWrapper, id: string ): string {
			return listRow( wrapper, id ).get( '.ext-neowiki-open-subject-list__name' ).text();
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
			mockSubjectRepository: { getSubjectForEditing: Mock; mintSubjectId: Mock };
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
			onCreate: Mock | undefined = undefined,
			extraProps: Record<string, unknown> = {},
		): TargetReposMount {
			const target = targetSubject( 's22222222222222', 'Target subject' );
			const mockSubjectRepository = {
				getSubjectForEditing: vi.fn().mockResolvedValue( target ),
				// The real stub, so the ids these tests expect are the ones a Subject
				// repository actually mints.
				mintSubjectId: vi.fn( () => new StubSubjectRepository( [] ).mintSubjectId() ),
			};
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
				onCreate,
				extraProps,
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

		// Presses the row itself, so unlike selectInList below this fails when no row is
		// rendered: it tests reachability rather than the dialog's handler.
		async function clickListRow( wrapper: VueWrapper, id: string ): Promise<void> {
			await listRow( wrapper, id ).trigger( 'click' );
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

		async function selectInList( wrapper: VueWrapper, id: string ): Promise<void> {
			wrapper.findComponent( OpenSubjectList ).vm.$emit( 'select', new SubjectId( id ) );
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

			expect( mockSubjectRepository.getSubjectForEditing ).toHaveBeenCalledTimes( 1 );
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

		// The open-panes check above cannot catch this click: its pane does not exist yet.
		it( 'does not duplicate a pane when a second click lands while the target is still loading', async () => {
			const { wrapper, mockSubjectRepository, target } = mountWithTargetRepos();
			await flushPromises();
			let resolveFetch!: ( subject: Subject ) => void;
			mockSubjectRepository.getSubjectForEditing.mockImplementationOnce(
				() => new Promise( ( resolve ) => {
					resolveFetch = resolve;
				} ),
			);

			wrapper.findComponent( SubjectEditPane ).vm.$emit( 'edit-relation-target', new SubjectId( 's22222222222222' ) );
			await nextTick();
			wrapper.findComponent( SubjectEditPane ).vm.$emit( 'edit-relation-target', new SubjectId( 's22222222222222' ) );
			await nextTick();

			resolveFetch( target );
			await flushPromises();

			expect( mockSubjectRepository.getSubjectForEditing ).toHaveBeenCalledTimes( 1 );
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

		it( 'switches the subject on screen when one is chosen from the list, keeping every pane mounted', async () => {
			const { wrapper } = await mountWithThreePanesOpen( {
				rootSchema: relationRootSchema,
				rootSubject: relationRootSubject,
			} );
			expect( visibleSubjectId( wrapper ) ).toBe( 's33333333333333' );

			await selectInList( wrapper, rootSubjectId );

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

			await selectInList( wrapper, rootSubjectId );
			await selectInList( wrapper, 's22222222222222' );

			expect( visibleSubjectId( wrapper ) ).toBe( 's22222222222222' );
			expect( ( wrapper.findAllComponents( SubjectEditPane )[ 1 ].vm as any ).label )
				.toBe( 'Edited child' );
			expect( ( wrapper.vm as any ).hasChanged ).toBe( true );
		} );

		it( 'shows the unsaved dot on a dirty subject and not on a clean one', async () => {
			useSubjectStore().setSubject( targetSubject( 's22222222222222', 'Target subject' ) );
			const { wrapper } = await mountWithSecondPaneOpen( {
				rootSchema: relationRootSchema,
				rootSubject: relationRootSubject,
			} );
			await makePaneDirty( wrapper, 1 );
			await flushPromises();

			expect( listRowHasDot( wrapper, 's22222222222222' ) ).toBe( true );
			expect( listRowHasDot( wrapper, rootSubjectId ) ).toBe( false );
		} );

		it( 'shows the unsaved dot on the root subject too', async () => {
			useSubjectStore().setSubject( targetSubject( 's22222222222222', 'Target subject' ) );
			const { wrapper } = await mountWithSecondPaneOpen( {
				rootSchema: relationRootSchema,
				rootSubject: relationRootSubject,
			} );
			await makePaneDirty( wrapper, 0 );
			await flushPromises();

			expect( listRowHasDot( wrapper, rootSubjectId ) ).toBe( true );
		} );

		it( 'notifies and adds no pane when the target cannot be loaded', async () => {
			const { wrapper, mockSubjectRepository } = mountWithTargetRepos();
			await flushPromises();
			mockSubjectRepository.getSubjectForEditing.mockRejectedValue( new Error( 'Error fetching subject' ) );

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

			// The root is left clean here, so a toast naming it would name a Subject not written.
			it( 'counts the subjects written when several panes were saved', async () => {
				const onSave = vi.fn().mockResolvedValue( undefined );
				const { wrapper } = await mountWithThreePanesOpen( { onSave } );

				await makePaneDirty( wrapper, 1 );
				await makePaneDirty( wrapper, 2 );
				await triggerSave( wrapper, '' );

				expect( onSave ).toHaveBeenCalledTimes( 2 );
				expect( ( mw.notify as Mock ).mock.calls ).toContainEqual( [
					'neowiki-subject-editor-success-multiple2',
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

			// A refusal names the Subject in a toast that vanishes; the pane it refers to has to
			// be the one left on screen, whatever the refusal was.
			it( 'brings a background pane on screen when its write is refused for a reason other than validation', async () => {
				const onSave = vi.fn()
					.mockResolvedValueOnce( undefined )
					.mockRejectedValueOnce( new Error( 'Boom' ) );
				const { wrapper, target } = await mountWithSecondPaneOpen( { onSave } );
				await makePaneDirty( wrapper, 0 );
				await makePaneDirty( wrapper, 1 );
				await selectInList( wrapper, mockSubject.getId().text );
				expect( visibleSubjectId( wrapper ) ).toBe( mockSubject.getId().text );

				await triggerSave( wrapper, '' );

				expect( visibleSubjectId( wrapper ) ).toBe( target.getId().text );
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

			// The loop harvests every dirty pane up front and marks each one clean as its write
			// lands, so anything typed meanwhile would be reverted and unguarded, and a second
			// Save would write the not-yet-reset panes again.
			describe( 'while a save is in flight', () => {
				function deferredSave(): { onSave: Mock; settle: ( error?: Error ) => void } {
					let resolveSave!: () => void;
					let rejectSave!: ( error: Error ) => void;
					const onSave = vi.fn( () => new Promise<void>( ( resolve, reject ) => {
						resolveSave = resolve;
						rejectSave = reject;
					} ) );
					return { onSave, settle: ( error?: Error ) => error === undefined ? resolveSave() : rejectSave( error ) };
				}

				function content( wrapper: VueWrapper ): Omit<DOMWrapper<Element>, 'exists'> {
					return wrapper.get( '.ext-neowiki-subject-editor-dialog__content' );
				}

				it( 'ignores a second Save', async () => {
					const { onSave, settle } = deferredSave();
					const { wrapper } = await mountWithSecondPaneOpen( { onSave } );
					await makePaneDirty( wrapper, 0 );

					await triggerSave( wrapper, '' );
					await triggerSave( wrapper, '' );
					settle();
					await flushPromises();

					expect( onSave ).toHaveBeenCalledTimes( 1 );
				} );

				it( 'reports the Save button disabled', async () => {
					const { onSave, settle } = deferredSave();
					const { wrapper } = await mountWithSecondPaneOpen( { onSave } );
					await makePaneDirty( wrapper, 0 );

					await triggerSave( wrapper, '' );
					expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( true );

					settle( new Error( 'Boom' ) );
					await flushPromises();
					expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( false );
				} );

				// The header's close button sits outside both inert regions, so this early
				// return is the only thing between a mid-write click and a discard
				// confirmation raised over Subjects the loop is still writing.
				it( 'ignores the close button while a save is writing', async () => {
					const { onSave } = deferredSave();
					const { wrapper } = await mountWithSecondPaneOpen( { onSave } );
					await makePaneDirty( wrapper, 0 );
					await triggerSave( wrapper, '' );

					await wrapper.find( '.cdx-dialog__header__close-button' ).trigger( 'click' );

					expect( wrapper.findComponent( CloseConfirmationDialog ).props( 'open' ) ).toBe( false );
					expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
				} );

				// The Schema editor is handed the root's Schema, so a nested pane's badge must
				// not open it or it would edit a Schema the reader did not point at.
				it( 'does not open the schema editor from a nested pane', async () => {
					const { wrapper } = await mountWithSecondPaneOpen();

					const badges = wrapper.findAll(
						'.ext-neowiki-subject-edit-pane__meta a.ext-neowiki-schema-name',
					);
					expect( badges.length ).toBeGreaterThan( 1 );

					const event = new MouseEvent( 'click', { button: 0, bubbles: true, cancelable: true } );
					badges[ badges.length - 1 ].element.dispatchEvent( event );
					await flushPromises();

					expect( event.defaultPrevented ).toBe( false );
					expect( wrapper.findComponent( SchemaEditorDialog ).props( 'open' ) ).toBe( false );
				} );

				it( 'makes the form inert until the save settles', async () => {
					const { onSave, settle } = deferredSave();
					const { wrapper } = await mountWithSecondPaneOpen( { onSave } );
					await makePaneDirty( wrapper, 0 );

					await triggerSave( wrapper, '' );
					expect( content( wrapper ).attributes( 'inert' ) ).toBeDefined();

					settle( new Error( 'Boom' ) );
					await flushPromises();
					expect( content( wrapper ).attributes( 'inert' ) ).toBeUndefined();
				} );
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

			it( 'writes nothing when a later pane holds text that cannot be turned into a value', async () => {
				const onSave = vi.fn().mockResolvedValue( undefined );
				const { wrapper } = await mountWithSecondPaneOpen( { onSave } );

				await makePaneDirty( wrapper, 0 );
				await makePaneDirty( wrapper, 1 );
				editorSaveBlockerBySchema = {
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
				editorSaveBlockerBySchema = {
					TestSchema: { propertyName: 'Score', message: 'neowiki-field-invalid-number' },
				};
				expect( visibleSubjectId( wrapper ) ).toBe( 's33333333333333' );

				await triggerSave( wrapper, '' );

				expect( onSave ).not.toHaveBeenCalled();
				expect( visibleSubjectId( wrapper ) ).toBe( rootSubjectId );
			} );
		} );

		describe( 'Written subjects', () => {
			// A written pane's values live in its inputs, so they survive exactly as long as the
			// form stays mounted; the label lives beside them.
			it( 'keeps a written subject\'s form mounted, with its label, when a later subject\'s save fails', async () => {
				const onSave = vi.fn()
					.mockResolvedValueOnce( undefined )
					.mockRejectedValueOnce( new ValidationFailedError( [] ) );
				const { wrapper } = await mountWithSecondPaneOpen( { onSave } );
				await editLabel( wrapper, 'Written root' );
				await makePaneDirty( wrapper, 1 );
				const formBefore = paneFor( wrapper, rootSubjectId ).findComponent( SubjectEditor ).element;

				await triggerSave( wrapper, '' );

				const root = paneFor( wrapper, rootSubjectId );
				expect( root.findComponent( SubjectEditor ).element ).toBe( formBefore );
				expect( ( root.vm as any ).label ).toBe( 'Written root' );
				expect( ( root.vm as any ).hasChanged ).toBe( false );
			} );

			it( 'clears the unsaved dot on a written subject and keeps the failed one\'s', async () => {
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
				expect( listRowHasDot( wrapper, rootSubjectId ) ).toBe( true );

				await triggerSave( wrapper, '' );

				expect( listRowHasDot( wrapper, rootSubjectId ) ).toBe( false );
				expect( listRowHasDot( wrapper, 's22222222222222' ) ).toBe( true );
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

			// Leaves the dialog open with one pane written and one still dirty: the state each
			// reset below must clear.
			async function partiallySave( onSave: Mock ): Promise<VueWrapper> {
				const { wrapper } = await mountWithSecondPaneOpen( { onSave } );
				await editLabel( wrapper, 'Written root' );
				await makePaneDirty( wrapper, 1 );
				await triggerSave( wrapper, '' );

				expect( ( wrapper.vm as any ).hasChanged ).toBe( true );

				return wrapper;
			}

			function partialSaveHandler(): Mock {
				return vi.fn()
					.mockResolvedValueOnce( undefined )
					.mockRejectedValueOnce( new ValidationFailedError( [] ) );
			}

			it( 'starts clean when the host replaces the root subject', async () => {
				const wrapper = await partiallySave( partialSaveHandler() );

				await wrapper.setProps( { subject: targetSubject( 's99999999999999', 'New root' ) } );
				// The old root, opened again as a target, is the fetched Subject and not the pane
				// the last session left behind.
				wrapper.findComponent( SubjectEditPane ).vm.$emit( 'edit-relation-target', new SubjectId( rootSubjectId ) );
				await flushPromises();

				expect( ( wrapper.vm as any ).hasChanged ).toBe( false );
				expect( ( paneFor( wrapper, rootSubjectId ).vm as any ).label ).not.toBe( 'Written root' );
			} );

			it( 'starts clean when the dialog reopens', async () => {
				const wrapper = await partiallySave( partialSaveHandler() );

				await wrapper.setProps( { open: false } );
				await wrapper.setProps( { open: true } );

				expect( ( wrapper.vm as any ).hasChanged ).toBe( false );
				expect( ( paneFor( wrapper, rootSubjectId ).vm as any ).label ).not.toBe( 'Written root' );
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

			// As the listbox pattern expects: the row the reader chose stays the tab stop, so the
			// next Tab leaves the navigator rather than restarting inside it.
			it( 'leaves focus on the list when a row shows a subject', async () => {
				const wrapper = await mountAttached( relationRootSchema, relationRootSubject );
				await openTargetFromForm( wrapper, 's22222222222222' );
				const row = wrapper.find( '.ext-neowiki-open-subject-list__item' ).element as HTMLElement;
				row.focus();

				await selectInList( wrapper, rootSubjectId );

				expect( document.activeElement ).toBe( row );
			} );

		} );

		describe( 'Navigator', () => {
			// A relation-change event on the pane is the one path by which a pick or a clear reaches
			// the dialog. The root pane is found through the component tree, because a teleported
			// dialog puts its panel outside the wrapper's own element.
			async function setRootFormTargets( wrapper: VueWrapper, ...targetIds: string[] ): Promise<void> {
				editorStatementsBySchema = { TestSchema: [ colleagueStatement( ...targetIds ) ] };
				wrapper.findAllComponents( SubjectEditPane )[ 0 ]
					.findComponent( SubjectEditor ).vm.$emit( 'relation-change' );
				await flushPromises();

				// Every caller below asserts that something did NOT change, so a relation change
				// that never reached the dialog would satisfy all of them. Checked here once.
				expect( rootFormTargets( wrapper ) ).toEqual( targetIds );
			}

			// The root's relation targets as its own pane now holds them. Through the component
			// tree, not the panel's id: these callers run the real Teleport, which moves the
			// dialog out of the wrapper's own element.
			function rootFormTargets( wrapper: VueWrapper ): string[] {
				const statements = ( wrapper.findAllComponents( SubjectEditPane )[ 0 ].vm as any )
					.editedSubject.getStatements() as StatementList;

				if ( !statements.has( new PropertyName( 'Colleague' ) ) ) {
					return [];
				}

				const value = statements.get( new PropertyName( 'Colleague' ) ).value;
				return value instanceof RelationValue ?
					value.relations.map( ( relation ) => relation.target.text ) :
					[];
			}

			// Pointing at a Subject is not opening it: the panel would otherwise arrive under the
			// reader's cursor listing the one Subject they can already see.
			it( 'renders no navigator when the form picks a relation target', async () => {
				const { wrapper } = mountWithTargetRepos(
					undefined, { teleport: false }, relationRootSchema,
				);
				await flushPromises();

				await setRootFormTargets( wrapper, 's22222222222222' );

				expect( wrapper.findComponent( OpenSubjectList ).exists() ).toBe( false );
			} );

			// A pane holds its values in its inputs' own refs, so an unmount destroys unsaved work.
			// The gate flips as the second pane arrives, which is the only moment it can flip at all.
			it( 'unmounts no pane when a second subject brings the navigator in', async () => {
				const { wrapper } = mountWithTargetRepos(
					undefined, { teleport: false }, relationRootSchema, relationRootSubject,
				);
				await flushPromises();
				( wrapper.findComponent( SubjectEditPane ).vm as any ).setLabel( 'Edited root' );
				await nextTick();
				expect( wrapper.findComponent( OpenSubjectList ).exists() ).toBe( false );

				wrapper.findComponent( SubjectEditPane )
					.vm.$emit( 'edit-relation-target', new SubjectId( 's22222222222222' ) );
				await flushPromises();

				expect( wrapper.findComponent( OpenSubjectList ).exists() ).toBe( true );
				expect( ( wrapper.findAllComponents( SubjectEditPane )[ 0 ].vm as any ).label )
					.toBe( 'Edited root' );
			} );

			// The list names the Subjects the dialog holds, so one Subject is a list of one: a
			// panel restating what the form beside it already says.
			it( 'renders no navigator while only the root subject is open, whatever it points at', async () => {
				const { wrapper } = mountWithTargetRepos(
					undefined, {}, relationRootSchema, relationRootSubject,
				);
				await flushPromises();

				expect( wrapper.findComponent( OpenSubjectList ).exists() ).toBe( false );
				// The second column is declared with the navigator, so without this the dialog
				// would hold a navigator-wide void open beside the form.
				expect( wrapper.find( '.cdx-dialog' ).classes() )
					.not.toContain( 'ext-neowiki-subject-editor-dialog--wide' );
			} );

			it( 'renders the navigator once a second subject is open', async () => {
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
				} );

				expect( wrapper.findComponent( OpenSubjectList ).exists() ).toBe( true );
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
				await selectInList( wrapper, rootSubjectId );

				await setRootFormTargets( wrapper );

				expect( wrapper.findComponent( OpenSubjectList ).exists() ).toBe( true );
			} );

			it( 'keeps the dirty subject reachable once the relation that led to it is cleared', async () => {
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
					stubs: { teleport: false },
				} );
				( wrapper.findAllComponents( SubjectEditPane )[ 1 ].vm as any ).setLabel( 'Edited child' );
				await nextTick();
				await selectInList( wrapper, rootSubjectId );
				await setRootFormTargets( wrapper );

				await clickListRow( wrapper, 's22222222222222' );

				expect( teleportedVisibleSubjectId( wrapper ) ).toBe( 's22222222222222' );
				expect( ( wrapper.findAllComponents( SubjectEditPane )[ 1 ].vm as any ).label )
					.toBe( 'Edited child' );
			} );

			// The list names the panes, so it needs nothing the dialog has not already got. A store
			// that never answers would leave a list built on fetched Subjects empty or unnamed.
			it( 'lists the open subjects by name while the subject store answers nothing', async () => {
				useSubjectStore().getOrFetchSubject = vi.fn( () => new Promise<never>( () => {
					// Intentionally left pending.
				} ) );

				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
					stubs: { teleport: false },
				} );

				expect( listedSubjectIds( wrapper ) ).toEqual( [ rootSubjectId, 's22222222222222' ] );
				expect( listRowLabel( wrapper, 's22222222222222' ) ).toBe( 'Target subject' );
			} );

			it( 'carries --wide once a second subject is open', async () => {
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
				} );

				expect( wrapper.find( '.cdx-dialog' ).classes() ).toContain( 'ext-neowiki-subject-editor-dialog--wide' );
			} );

			// The width follows the navigator's own gate.
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

			// Both hosts keep the dialog mounted after it closes, so a fetch started in one
			// opening settles in the next unless it is told which opening it belongs to.
			describe( 'A target fetch outliving its opening', () => {
				const TARGET_ID = 's22222222222222';

				function mountWithPendingTargetFetch(): TargetReposMount & { land: () => Promise<void> } {
					const mounted = mountWithTargetRepos();
					let resolveTarget!: ( subject: Subject ) => void;
					mounted.mockSubjectRepository.getSubjectForEditing.mockImplementation(
						() => new Promise( ( resolve ) => {
							resolveTarget = resolve;
						} ),
					);
					return {
						...mounted,
						land: async () => {
							resolveTarget( mounted.target );
							await flushPromises();
						},
					};
				}

				async function clickEditTarget( wrapper: VueWrapper ): Promise<void> {
					wrapper.findComponent( SubjectEditPane ).vm.$emit( 'edit-relation-target', new SubjectId( TARGET_ID ) );
					await flushPromises();
				}

				it( 'does not open a pane in the next opening of the dialog', async () => {
					const { wrapper, land } = mountWithPendingTargetFetch();
					await flushPromises();
					await clickEditTarget( wrapper );

					await wrapper.setProps( { open: false } );
					await wrapper.setProps( { open: true } );
					await land();

					expect( wrapper.findAllComponents( SubjectEditPane ) ).toHaveLength( 1 );
					expect( teleportedVisibleSubjectId( wrapper ) ).toBe( mockSubject.getId().text );
				} );

				it( 'does not open a pane under a root the host replaced', async () => {
					const { wrapper, land } = mountWithPendingTargetFetch();
					await flushPromises();
					await clickEditTarget( wrapper );

					const otherRoot = targetSubject( 's33333333333333', 'Other root' );
					await wrapper.setProps( { subject: otherRoot } );
					await land();

					expect( wrapper.findAllComponents( SubjectEditPane ) ).toHaveLength( 1 );
					expect( teleportedVisibleSubjectId( wrapper ) ).toBe( otherRoot.getId().text );
				} );

				it( 'does not stand in for the same target clicked in the next opening', async () => {
					const { wrapper, mockSubjectRepository } = mountWithPendingTargetFetch();
					await flushPromises();
					await clickEditTarget( wrapper );

					await wrapper.setProps( { open: false } );
					await wrapper.setProps( { open: true } );
					await clickEditTarget( wrapper );

					expect( mockSubjectRepository.getSubjectForEditing ).toHaveBeenCalledTimes( 2 );
				} );
			} );

			// Auto-placement puts each child in its own column, and the divider between them draws
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
						'ext-neowiki-pane-divider ext-neowiki-subject-editor-dialog__divider',
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

				expect( surfaces[ 0 ].find( '.ext-neowiki-open-subject-list' ).exists() ).toBe( true );
				expect( surfaces[ 1 ].find( '.ext-neowiki-subject-editor-dialog__panels' ).exists() ).toBe( true );
			} );

			// The track list and the divider turn on together: a divider left behind would
			// hold a track open for a column that is not rendered.
			it( 'renders no divider when there is no navigator', async () => {
				const wrapper = mountComponent( false, {} );
				await flushPromises();

				expect( wrapper.findComponent( PaneDivider ).exists() ).toBe( false );
			} );

			it( 'gives the navigator the width the divider asks for', async () => {
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
				} );

				wrapper.findComponent( PaneDivider ).vm.$emit( 'resize', 500 );
				await nextTick();

				expect( wrapper.find( '.ext-neowiki-subject-editor-dialog__content' ).attributes( 'style' ) )
					.toContain( '--ext-neowiki-pane-size: 500px' );
				expect( wrapper.findComponent( PaneDivider ).props( 'size' ) ).toBe( 500 );
			} );

			it( 'tells the divider the bounds it may move between', async () => {
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
				} );

				const divider = wrapper.findComponent( PaneDivider );

				expect( divider.props( 'min' ) ).toBe( 256 );
				// jsdom measures nothing, so the bound is the current width and the divider still moves.
				expect( divider.props( 'max' ) ).toBe( 384 );
				expect( divider.props( 'disabled' ) ).toBe( false );
			} );

			it( 'remembers the navigator width once the gesture ends, under its own key', async () => {
				const global = globalThis as unknown as { mw?: Record<string, unknown> };
				const before = global.mw;
				const storage = { get: vi.fn( () => null ), set: vi.fn( () => true ) };
				global.mw = { ...before, storage };

				try {
					const { wrapper } = await mountWithSecondPaneOpen( {
						rootSchema: relationRootSchema,
						rootSubject: relationRootSubject,
					} );
					wrapper.findComponent( PaneDivider ).vm.$emit( 'resize', 500 );
					wrapper.findComponent( PaneDivider ).vm.$emit( 'commit' );
					await nextTick();

					expect( storage.set ).toHaveBeenCalledWith( 'neowiki-subject-editor-pane-size', '500' );
				} finally {
					global.mw = before;
				}
			} );

			it( 'points the divider at the navigator it sizes', async () => {
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
				} );

				const navigator = wrapper.find(
					'.ext-neowiki-subject-editor-dialog__content > .ext-neowiki-subject-editor-dialog__surface',
				);

				expect( navigator.attributes( 'id' ) ).toBeTruthy();
				expect( wrapper.findComponent( PaneDivider ).props( 'controls' ) )
					.toBe( navigator.attributes( 'id' ) );
			} );

			it( 'renders only the form surface when there is no navigator', async () => {
				const wrapper = mountComponent( false, {} );
				await flushPromises();

				const surfaces = wrapper.findAll(
					'.ext-neowiki-subject-editor-dialog__content > .ext-neowiki-subject-editor-dialog__surface',
				);

				expect( surfaces ).toHaveLength( 1 );
				expect( surfaces[ 0 ].find( '.ext-neowiki-open-subject-list' ).exists() ).toBe( false );
				expect( surfaces[ 0 ].find( '.ext-neowiki-subject-editor-dialog__panels' ).exists() ).toBe( true );
			} );

			// Every listed Subject is a pane already, so a choice is a switch and never a load.
			it( 'shows a chosen subject without fetching it again', async () => {
				const { wrapper, mockSubjectRepository } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
				} );
				mockSubjectRepository.getSubjectForEditing.mockClear();

				await selectInList( wrapper, rootSubjectId );

				expect( mockSubjectRepository.getSubjectForEditing ).not.toHaveBeenCalled();
				expect( wrapper.findAllComponents( SubjectEditPane ) ).toHaveLength( 2 );
				expect( teleportedVisibleSubjectId( wrapper ) ).toBe( rootSubjectId );
			} );

			// Asserted on the rendered dot rather than on the unsavedIds prop: that prop restates
			// what the dialog already knows and says nothing about whether the list has a row to
			// hang the dot on.
			it( 'keeps the unsaved dot for a subject that is no longer on screen', async () => {
				useSubjectStore().setSubject( targetSubject( 's22222222222222', 'Target subject' ) );
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
				} );
				await makePaneDirty( wrapper, 1 );

				await selectInList( wrapper, rootSubjectId );

				expect( visibleSubjectId( wrapper ) ).toBe( rootSubjectId );
				expect( listRowHasDot( wrapper, 's22222222222222' ) ).toBe( true );
			} );

			// The root stores no target here, so the open Subject is one nothing points at. It is
			// listed all the same: the list is the panes, not the relations between them.
			it( 'lists a subject the stored root does not point at, dot and all', async () => {
				useSubjectStore().setSubject( targetSubject( 's22222222222222', 'Target subject' ) );
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					stubs: { teleport: false },
				} );
				expect( listHasRow( wrapper, 's22222222222222' ) ).toBe( true );

				await makePaneDirty( wrapper, 1 );
				await flushPromises();

				expect( listRowHasDot( wrapper, 's22222222222222' ) ).toBe( true );
			} );

			// The relation is gone and the save still writes the Subject, so the row that leads to
			// it stays, dot and all.
			it( 'keeps the dot after the relation to an edited subject is removed from the form', async () => {
				useSubjectStore().setSubject( targetSubject( 's22222222222222', 'Target subject' ) );
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
				} );
				await makePaneDirty( wrapper, 1 );
				await flushPromises();
				expect( listRowHasDot( wrapper, 's22222222222222' ) ).toBe( true );

				// The harvest drops a statement with no value, so the form no longer links the target.
				await setRootFormTargets( wrapper, 's33333333333333' );

				expect( listRowHasDot( wrapper, 's22222222222222' ) ).toBe( true );
			} );

			// The live label lives on the pane, not on the copy the dialog harvests from it, which
			// is refreshed on relation changes alone.
			it( 'renames the root subject\'s row when it is renamed in the header', async () => {
				useSubjectStore().setSubject( targetSubject( 's22222222222222', 'Target subject' ) );
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
				} );
				expect( listRowLabel( wrapper, rootSubjectId ) ).toBe( 'Test Subject' );

				await editLabel( wrapper, 'Renamed Subject' );

				expect( listRowLabel( wrapper, rootSubjectId ) ).toBe( 'Renamed Subject' );
			} );

			// A cleared field means no label, not an empty one, so the node falls back to a name
			// rather than rendering a blank row the user cannot read or aim at.
			it( 'keeps a name on the root subject\'s row when its label is cleared', async () => {
				useSubjectStore().setSubject( targetSubject( 's22222222222222', 'Target subject' ) );
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
				} );

				await editLabel( wrapper, '' );

				expect( listRowLabel( wrapper, rootSubjectId ) ).toBe( 'Test Subject' );
			} );

			it( 'names the root subject\'s row by the label its template reads from the form', async () => {
				useSubjectStore().setSubject( targetSubject( 's22222222222222', 'Target subject' ) );
				editorStatementsBySchema = { TestSchema: [ ...templateNamedRootSubject.getStatements() ] };
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: templatedRootSchema,
					rootSubject: templateNamedRootSubject,
				} );
				expect( listRowLabel( wrapper, rootSubjectId ) ).toBe( 'Alice' );

				editorStatementsBySchema = { TestSchema: [ nameStatement( 'Bob' ), colleagueStatement( 's22222222222222' ) ] };
				wrapper.findComponent( SubjectEditPane ).findComponent( SubjectEditorStub ).vm.$emit( 'change' );
				await nextTick();

				expect( listRowLabel( wrapper, rootSubjectId ) ).toBe( 'Bob' );
			} );

			// A child Subject has no rename control of its own, but the pane that edits it already
			// owns its label.
			it( 'renames the row of a child subject its pane renames', async () => {
				useSubjectStore().setSubject( targetSubject( 's22222222222222', 'Target subject' ) );
				const { wrapper } = await mountWithSecondPaneOpen( {
					rootSchema: relationRootSchema,
					rootSubject: relationRootSubject,
				} );
				expect( listRowLabel( wrapper, 's22222222222222' ) ).toBe( 'Target subject' );

				( wrapper.findAllComponents( SubjectEditPane )[ 1 ].vm as any ).setLabel( 'Renamed child' );
				await nextTick();

				expect( listRowLabel( wrapper, 's22222222222222' ) ).toBe( 'Renamed child' );
			} );
		} );

		// The relation picker that offers creation is stubbed out of these tests, so the
		// injection it reads is driven directly: it is the whole contract between this dialog
		// and SubjectPicker.
		describe( 'Creating a relation target', () => {
			const CreationAwareSubjectEditorStub = {
				...SubjectEditorStub,
				setup( props: { schema?: Schema } ) {
					return {
						...SubjectEditorStub.setup( props ),
						subjectCreation: inject<SubjectCreation | null>( SubjectCreationKey, null ),
					};
				},
			};

			const hostPage = new PageIdentifiers( 42, 'Host page' );
			const otherPage = new PageIdentifiers( 77, 'Other page' );

			// What StubSubjectRepository mints for a single id.
			const mintedId = 'smintedAAAAAAA1';
			// Where a test needs to tell two drafts apart.
			const firstDraftId = 's1draftAAAAAAA1';
			const secondDraftId = 's1draftBBBBBBB1';

			// The schemas the drafts of these tests are edited against. Person carries a relation
			// of its own, so a draft can point at another draft; Employer ends the chain.
			const personCreationSchema = new Schema( 'Person', 'A person', new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'Colleague', { type: 'relation', targetSchema: 'Employer' } ),
			] ), null );
			const employerSchema = new Schema( 'Employer', 'An employer', new PropertyDefinitionList( [] ), null );
			// Names its Subjects by their Name field, which is where a typed name then goes.
			const painterSchema = new Schema( 'Painter', 'A painter', new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'Name', { type: 'text' } ),
			] ), 'Painter {Name}' );
			const creationSchemas: Record<string, Schema> = {
				Person: personCreationSchema,
				Employer: employerSchema,
				Painter: painterSchema,
			};

			// mockSubject is a bare Subject, which is a Subject with nowhere to store one made
			// beside it. The tests that expect a draft to be created need a page.
			const rootOnHostPage = newSubject( {
				id: rootSubjectId,
				label: mockSubject.getLabel(),
				pageIdentifiers: hostPage,
			} );

			interface CreationMountOptions {
				onSave?: Mock;
				onCreate?: Mock;
				rootSubject?: Subject;
				extraProps?: Record<string, unknown>;
			}

			function mountForCreation( {
				onSave, onCreate, rootSubject, extraProps,
			}: CreationMountOptions = {} ): TargetReposMount {
				const mounted = mountWithTargetRepos(
					onSave ?? vi.fn().mockResolvedValue( undefined ),
					{ SubjectEditor: CreationAwareSubjectEditorStub },
					// Declares the relation a created draft is picked into, so a draft the
					// requesting field then holds is one the root really points at.
					relationRootSchema,
					rootSubject ?? rootOnHostPage,
					undefined,
					onCreate ?? vi.fn().mockResolvedValue( undefined ),
					extraProps,
				);
				mounted.mockSchemaRepository.getSchema.mockImplementation(
					( name: string ) => Promise.resolve( creationSchemas[ name ] ?? personSchema ),
				);
				return mounted;
			}

			async function mountReadyForCreation(
				options: CreationMountOptions = {},
			): Promise<TargetReposMount> {
				const result = mountForCreation( options );
				await flushPromises();
				return result;
			}

			// Null where the host passed no create handler, which is what a field reading the
			// injection sees.
			function providedCreation( wrapper: VueWrapper ): SubjectCreation | null {
				return ( wrapper.findComponent( SubjectEditor ).vm as unknown as {
					subjectCreation: SubjectCreation | null;
				} ).subjectCreation;
			}

			interface CreateTargetOptions {
				label?: string | null;
				schemaName?: string;
			}

			async function createTarget(
				wrapper: VueWrapper,
				{ label = 'New colleague', schemaName = 'Person' }: CreateTargetOptions = {},
			): Promise<Subject | null> {
				const created = await providedCreation( wrapper )?.create( schemaName, label );
				await flushPromises();
				return created ?? null;
			}

			// What the stubbed editor of one pane reports its relation fields hold. Stands in for
			// the pick a picker records, which is what makes a draft something the session still
			// owes the wiki.
			async function reportsRelationTo(
				wrapper: VueWrapper, paneIndex: number, targetIds: readonly string[],
			): Promise<void> {
				const pane = wrapper.findAllComponents( SubjectEditPane )[ paneIndex ];
				const schemaName = ( pane.props( 'schema' ) as Schema ).getName();
				editorStatementsBySchema[ schemaName ] = targetIds.length === 0 ?
					[] :
					[ colleagueStatement( ...targetIds ) ];
				pane.findComponent( SubjectEditor ).vm.$emit( 'relation-change' );
				await nextTick();
			}

			// A creation the requesting field then holds, which is the only way a draft reaches
			// the wiki: one nothing points at is left behind on purpose.
			async function createReferencedTarget(
				wrapper: VueWrapper,
				{ from = 0, schemaName = 'Person' }: { from?: number; schemaName?: string } = {},
			): Promise<Subject | null> {
				const created = await createTarget( wrapper, { schemaName } );
				await reportsRelationTo(
					wrapper, from, created === null ? [] : [ created.getId().text ],
				);
				return created;
			}

			async function openStoredTarget( wrapper: VueWrapper ): Promise<void> {
				wrapper.findComponent( SubjectEditPane ).vm.$emit( 'edit-relation-target', new SubjectId( 's22222222222222' ) );
				await flushPromises();
			}

			function subjectIdsPassedTo( handler: Mock ): string[] {
				return handler.mock.calls.map( ( call ) => ( call[ 0 ] as Subject ).getId().text );
			}

			// A label would outrank the template for good, a second way of naming a Subject its
			// Schema already names. How the name is placed is the domain's; this is its wiring.
			it( 'names a target of a Schema its template names by the field the template reads', async () => {
				const { wrapper } = await mountReadyForCreation();

				const created = await createTarget( wrapper, { schemaName: 'Painter', label: 'Rembrandt' } ) as Subject;

				expect( created.getLabel() ).toBeNull();
				expect( subjectDisplayName( created ) ).toBe( 'Painter Rembrandt' );
			} );

			beforeEach( () => {
				// A Subject the server has never seen is validated as a creation, which the
				// panes of these tests do on mount; keep it off the network like the update
				// dry-run the outer setup stubs.
				useSubjectStore().validateSubject = vi.fn().mockResolvedValue( [] );
			} );

			it( 'offers no creation to the relation fields when the host cannot create subjects', async () => {
				const { wrapper } = mountWithTargetRepos(
					undefined,
					{ SubjectEditor: CreationAwareSubjectEditorStub },
					relationRootSchema,
					rootOnHostPage,
				);
				await flushPromises();

				expect( providedCreation( wrapper ) ).toBeNull();
			} );

			it( 'offers creation to the relation fields when the host can create subjects', async () => {
				const { wrapper } = await mountReadyForCreation();

				expect( typeof providedCreation( wrapper )?.create ).toBe( 'function' );
			} );

			it( 'answers with a draft carrying the minted id, the typed label and the requested schema', async () => {
				const { wrapper } = await mountReadyForCreation();

				const created = await createTarget( wrapper, { label: 'Ada Lovelace' } );

				expect( created?.getId().text ).toBe( mintedId );
				expect( created?.getLabel() ).toBe( 'Ada Lovelace' );
				expect( created?.getSchemaName() ).toBe( 'Person' );
			} );

			it( 'opens the created draft as a pane of its own', async () => {
				const { wrapper } = await mountReadyForCreation();

				await createTarget( wrapper );

				expect( wrapper.findAllComponents( SubjectEditPane ) ).toHaveLength( 2 );
				expect( wrapper.find( `#ext-neowiki-panel-${ mintedId }` ).exists() ).toBe( true );
			} );

			it( 'brings the created draft on screen', async () => {
				const { wrapper } = await mountReadyForCreation();

				await createTarget( wrapper );

				expect( visibleSubjectId( wrapper ) ).toBe( mintedId );
			} );

			it( 'edits the created draft against the schema it was asked for', async () => {
				const { wrapper, mockSchemaRepository } = await mountReadyForCreation();

				await createTarget( wrapper );

				expect( mockSchemaRepository.getSchema ).toHaveBeenCalledWith( 'Person' );
				expect( paneFor( wrapper, mintedId ).props( 'schema' ) ).toBe( personCreationSchema );
			} );

			it( 'renders the navigator once a target has been created', async () => {
				const { wrapper } = await mountReadyForCreation();
				expect( wrapper.findComponent( OpenSubjectList ).exists() ).toBe( false );

				await createTarget( wrapper );

				expect( wrapper.findComponent( OpenSubjectList ).exists() ).toBe( true );
			} );

			// Nobody has to type into a draft for the relation pointing at it to need something
			// to point at, so an untouched one still has to be written.
			it( 'enables Save for an untouched draft', async () => {
				const { wrapper } = await mountReadyForCreation();

				await createReferencedTarget( wrapper );

				expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( false );
			} );

			it( 'marks an untouched draft unsaved in the navigator', async () => {
				const { wrapper } = await mountReadyForCreation();

				await createReferencedTarget( wrapper );

				expect( listRowHasDot( wrapper, mintedId ) ).toBe( true );
			} );

			it( 'creates the draft with the id minted for it, on the page it was opened against', async () => {
				const onCreate = vi.fn().mockResolvedValue( undefined );
				const { wrapper } = await mountReadyForCreation( { onCreate } );

				await createReferencedTarget( wrapper );
				await triggerSave( wrapper, 'my summary' );

				expect( onCreate ).toHaveBeenCalledTimes( 1 );
				expect( ( onCreate.mock.calls[ 0 ][ 0 ] as Subject ).getId().text ).toBe( mintedId );
				expect( onCreate.mock.calls[ 0 ][ 1 ] ).toBe( hostPage.getPageId() );
			} );

			// The Subject whose relation is being filled in is the one the new Subject belongs
			// beside, and panes routinely span pages.
			it( 'creates the draft on the page of the subject being edited, not the dialog\'s root', async () => {
				const onCreate = vi.fn().mockResolvedValue( undefined );
				const { wrapper, mockSubjectRepository } = await mountReadyForCreation( { onCreate } );
				mockSubjectRepository.getSubjectForEditing.mockResolvedValue( newSubject( {
					id: 's22222222222222',
					label: 'Target subject',
					schemaName: 'Person',
					pageIdentifiers: otherPage,
				} ) );
				await openStoredTarget( wrapper );

				await createReferencedTarget( wrapper, { from: 1 } );
				await triggerSave( wrapper, '' );

				expect( onCreate.mock.calls[ 0 ][ 1 ] ).toBe( otherPage.getPageId() );
			} );

			it( 'updates the subject that refers to the draft with the host\'s save handler', async () => {
				const onSave = vi.fn().mockResolvedValue( undefined );
				const { wrapper } = await mountReadyForCreation( { onSave } );

				await createReferencedTarget( wrapper );
				await makePaneDirty( wrapper, 0 );
				await triggerSave( wrapper, '' );

				expect( subjectIdsPassedTo( onSave ) ).toEqual( [ rootSubjectId ] );
			} );

			// Or the update would name a target the wiki does not have yet.
			it( 'creates the draft before updating the subject that refers to it', async () => {
				const onSave = vi.fn().mockResolvedValue( undefined );
				const onCreate = vi.fn().mockResolvedValue( undefined );
				const { wrapper } = await mountReadyForCreation( { onSave, onCreate } );

				await createReferencedTarget( wrapper );
				await makePaneDirty( wrapper, 0 );
				await triggerSave( wrapper, '' );

				expect( onCreate.mock.invocationCallOrder[ 0 ] )
					.toBeLessThan( onSave.mock.invocationCallOrder[ 0 ] );
			} );

			// The create landed; only the write after it was refused. Offering the same id a
			// second time would ask the wiki for a Subject it already has.
			it( 'updates rather than re-creates a draft the first attempt already wrote', async () => {
				const onSave = vi.fn()
					.mockRejectedValueOnce( new Error( 'Boom' ) )
					.mockResolvedValue( undefined );
				const onCreate = vi.fn().mockResolvedValue( undefined );
				const { wrapper } = await mountReadyForCreation( { onSave, onCreate } );
				await createReferencedTarget( wrapper );
				await makePaneDirty( wrapper, 0 );
				await triggerSave( wrapper, '' );

				await makePaneDirty( wrapper, 1 );
				await triggerSave( wrapper, '' );

				expect( onCreate ).toHaveBeenCalledTimes( 1 );
				expect( subjectIdsPassedTo( onSave ) ).toContain( mintedId );
			} );

			// A draft that points at another draft is written after it, or its own write would
			// name a target the wiki does not have yet.
			it( 'creates a draft before the draft that points at it, and both before the update', async () => {
				const onSave = vi.fn().mockResolvedValue( undefined );
				const onCreate = vi.fn().mockResolvedValue( undefined );
				const { wrapper, mockSubjectRepository } = await mountReadyForCreation( { onSave, onCreate } );
				mockSubjectRepository.mintSubjectId
					.mockResolvedValueOnce( new SubjectId( firstDraftId ) )
					.mockResolvedValueOnce( new SubjectId( secondDraftId ) );
				await createReferencedTarget( wrapper );
				await createReferencedTarget( wrapper, { from: 1, schemaName: 'Employer' } );
				await makePaneDirty( wrapper, 0 );

				await triggerSave( wrapper, '' );

				expect( subjectIdsPassedTo( onCreate ) ).toEqual( [ secondDraftId, firstDraftId ] );
				expect( onCreate.mock.invocationCallOrder[ 1 ] )
					.toBeLessThan( onSave.mock.invocationCallOrder[ 0 ] );
			} );

			// The relation that justified it is gone, so writing it would leave exactly the
			// debris the editor promises not to.
			it( 'does not create a draft the user has pointed away from', async () => {
				const onCreate = vi.fn().mockResolvedValue( undefined );
				const { wrapper } = await mountReadyForCreation( { onCreate } );
				await createReferencedTarget( wrapper );
				await makePaneDirty( wrapper, 0 );

				await reportsRelationTo( wrapper, 0, [] );
				await triggerSave( wrapper, '' );

				expect( onCreate ).not.toHaveBeenCalled();
			} );

			it( 'stops counting a draft the user has pointed away from as unsaved', async () => {
				const { wrapper } = await mountReadyForCreation();
				await createReferencedTarget( wrapper );

				await reportsRelationTo( wrapper, 0, [] );

				expect( listRowHasDot( wrapper, mintedId ) ).toBe( false );
			} );

			it( 'creates a draft the user has pointed back at', async () => {
				const onCreate = vi.fn().mockResolvedValue( undefined );
				const { wrapper } = await mountReadyForCreation( { onCreate } );
				await createReferencedTarget( wrapper );
				await reportsRelationTo( wrapper, 0, [] );

				await reportsRelationTo( wrapper, 0, [ mintedId ] );
				await triggerSave( wrapper, '' );

				expect( subjectIdsPassedTo( onCreate ) ).toEqual( [ mintedId ] );
			} );

			// A Subject added while the write loop runs would be named by a Subject already
			// written and never written itself.
			it( 'refuses to create a draft while a save is running', async () => {
				const { wrapper } = await mountReadyForCreation();
				await makePaneDirty( wrapper, 0 );
				let releaseValidation!: () => void;
				useSubjectStore().validateSubjectUpdate = vi.fn( (): Promise<SubjectViolation[]> => new Promise( ( resolve ) => {
					releaseValidation = () => resolve( [] );
				} ) );
				await triggerSave( wrapper, '' );

				const created = await createTarget( wrapper );

				expect( created ).toBeNull();
				expect( wrapper.findAllComponents( SubjectEditPane ) ).toHaveLength( 1 );
				releaseValidation();
				await flushPromises();
			} );

			// The create landed and only its answer was lost, so the save has no reason to stop.
			it( 'carries on saving when the host reports the draft id as already in use', async () => {
				const onSave = vi.fn().mockResolvedValue( undefined );
				const onCreate = vi.fn().mockRejectedValue( new SubjectIdInUseError( mintedId ) );
				const { wrapper } = await mountReadyForCreation( { onSave, onCreate } );
				await createReferencedTarget( wrapper );
				await makePaneDirty( wrapper, 0 );

				await triggerSave( wrapper, '' );

				expect( subjectIdsPassedTo( onSave ) ).toEqual( [ rootSubjectId ] );
			} );

			// Offering the same id again would be refused forever.
			it( 'updates a draft the host reported as already in use when it is saved again', async () => {
				const onSave = vi.fn().mockResolvedValue( undefined );
				const onCreate = vi.fn().mockRejectedValue( new SubjectIdInUseError( mintedId ) );
				const { wrapper } = await mountReadyForCreation( { onSave, onCreate } );
				await createReferencedTarget( wrapper );
				await triggerSave( wrapper, '' );

				await makePaneDirty( wrapper, 1 );
				await triggerSave( wrapper, '' );

				expect( subjectIdsPassedTo( onSave ) ).toEqual( [ mintedId ] );
				expect( onCreate ).toHaveBeenCalledTimes( 1 );
			} );

			it( 'notifies and adds no pane when no id can be minted', async () => {
				const { wrapper, mockSubjectRepository } = await mountReadyForCreation();
				mockSubjectRepository.mintSubjectId.mockRejectedValue( new Error( 'Minting failed' ) );

				await createTarget( wrapper );

				expect( wrapper.findAllComponents( SubjectEditPane ) ).toHaveLength( 1 );
				expect( mw.notify ).toHaveBeenCalledWith(
					'neowiki-subject-editor-create-target-error', { type: 'error' },
				);
			} );

			it( 'notifies and adds no pane when the target schema cannot be fetched', async () => {
				const { wrapper, mockSchemaRepository } = await mountReadyForCreation();
				mockSchemaRepository.getSchema.mockRejectedValue( new Error( 'Schema fetch failed' ) );

				await createTarget( wrapper );

				expect( wrapper.findAllComponents( SubjectEditPane ) ).toHaveLength( 1 );
				expect( mw.notify ).toHaveBeenCalledWith(
					'neowiki-subject-editor-create-target-error', { type: 'error' },
				);
			} );

			// Its panes are gone, so the failure would report itself over whatever the dialog
			// is editing now.
			it( 'reports nothing when a creation fails after the dialog was reopened', async () => {
				const { wrapper, mockSubjectRepository } = await mountReadyForCreation();
				let failMinting!: ( error: Error ) => void;
				mockSubjectRepository.mintSubjectId.mockReturnValue( new Promise( ( _resolve, reject ) => {
					failMinting = reject;
				} ) );
				const creation = providedCreation( wrapper )?.create( 'Person', 'Ada Lovelace' );

				await wrapper.setProps( { open: false } );
				await wrapper.setProps( { open: true } );
				await flushPromises();
				failMinting( new Error( 'Minting failed' ) );
				await creation;

				expect( mw.notify ).not.toHaveBeenCalled();
			} );

			it( 'answers with nothing when the subject being edited has no page to store a draft on', async () => {
				const { wrapper } = await mountReadyForCreation( { rootSubject: mockSubject } );

				const created = await createTarget( wrapper );

				expect( created ).toBeNull();
				expect( mw.notify ).toHaveBeenCalledWith(
					'neowiki-subject-editor-create-target-error', { type: 'error' },
				);
			} );

			// A subject-first wiki gives the draft a page of its own, so the pane it was created
			// from needs none to store it on (ADR 33).
			it( 'drafts a target for a pageless subject on a subject-first wiki', async () => {
				setupMwMock( {
					functions: [ 'message', 'msg', 'notify', 'config', 'util' ],
					config: {
						wgNeoWikiValidationDebounceMs: 0,
						wgArticleId: 42,
						wgNeoWikiSubjectFirst: true,
					},
				} );
				const { wrapper } = await mountReadyForCreation( { rootSubject: mockSubject } );

				const created = await createTarget( wrapper );

				expect( created?.getId().text ).toBe( mintedId );
				expect( mw.notify ).not.toHaveBeenCalled();
			} );

			// A relation naming a draft is sound in the editor and unresolvable to the server,
			// so every pane has to know which ids to withhold that complaint for.
			it( 'tells every pane which target ids the session has yet to write', async () => {
				const { wrapper } = await mountReadyForCreation();
				await openStoredTarget( wrapper );

				await createTarget( wrapper );

				expect( wrapper.findAllComponents( SubjectEditPane )
					.map( ( pane ) => pane.props( 'unsavedTargetIds' ) ) )
					.toEqual( [ [ mintedId ], [ mintedId ], [ mintedId ] ] );
			} );

			// A draft made in one field is offered by every field with the same target schema,
			// and by no other.
			it( 'offers a field only the drafts of the schema it asks for', async () => {
				const { wrapper, mockSubjectRepository } = await mountReadyForCreation();
				mockSubjectRepository.mintSubjectId
					.mockResolvedValueOnce( new SubjectId( firstDraftId ) )
					.mockResolvedValueOnce( new SubjectId( secondDraftId ) );
				await createTarget( wrapper );
				await createTarget( wrapper, { schemaName: 'Employer' } );

				const offered = providedCreation( wrapper )?.drafts( 'Person' );

				expect( offered?.map( ( draft ) => draft.getId().text ) ).toEqual( [ firstDraftId ] );
			} );

			// Nothing was written, so the next opening starts from what the wiki holds.
			it( 'drops the draft when the dialog is reopened', async () => {
				const { wrapper } = await mountReadyForCreation();
				await createReferencedTarget( wrapper );

				await wrapper.setProps( { open: false } );
				await wrapper.setProps( { open: true } );
				await flushPromises();

				expect( wrapper.findAllComponents( SubjectEditPane ) ).toHaveLength( 1 );
				expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( true );
			} );

			// What the subject creator opens: the Subject at the root of the dialog is one the
			// wiki does not hold either, so the save creates it alongside whatever it points at.
			describe( 'when the root is new too', () => {
				const rootSchemaName = rootOnHostPage.getSchemaName();

				// A root bound for a page that its own write creates.
				const rootOnPageToCome = newSubject( {
					id: rootSubjectId,
					label: 'New company',
					pageIdentifiers: PageIdentifiers.notYetCreated(),
				} );

				function mountCreating( options: CreationMountOptions = {} ): Promise<TargetReposMount> {
					return mountReadyForCreation( {
						...options,
						extraProps: { rootIsNew: true, ...options.extraProps },
					} );
				}

				it( 'writes the root as a creation rather than an update', async () => {
					const onSave = vi.fn().mockResolvedValue( undefined );
					const onCreate = vi.fn().mockResolvedValue( undefined );
					const { wrapper } = await mountCreating( { onSave, onCreate } );

					await triggerSave( wrapper, 'a summary' );

					expect( subjectIdsPassedTo( onCreate ) ).toEqual( [ rootSubjectId ] );
					expect( onSave ).not.toHaveBeenCalled();
				} );

				// The root anchors the walk itself. Without that there is nothing the wiki holds
				// to justify a draft, and the whole save would come to nothing.
				it( 'writes a draft the root points at, although the wiki holds nothing that does', async () => {
					const onCreate = vi.fn().mockResolvedValue( undefined );
					const { wrapper } = await mountCreating( { onCreate } );

					const created = await createReferencedTarget( wrapper );
					await triggerSave( wrapper, '' );

					expect( subjectIdsPassedTo( onCreate ) ).toContain( created?.getId().text );
				} );

				it( 'leaves save reachable although nothing has been typed into the root', async () => {
					const { wrapper } = await mountCreating();

					expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( false );
				} );

				// In the write set is not the same as worth keeping: nobody would be sorry to lose
				// a Subject they have put nothing into.
				it( 'asks nothing on close while the root is untouched', async () => {
					const { wrapper } = await mountCreating();

					wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
					await flushPromises();

					expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
				} );

				// The write that landed is what the amber line records, and closing takes that line
				// with it. Nothing else is left to say a Subject was made.
				it( 'asks on close once a save has written something and stopped', async () => {
					const onCreate = vi.fn()
						.mockResolvedValueOnce( undefined )
						.mockRejectedValueOnce( new Error( 'refused' ) );
					const { wrapper } = await mountCreating( { onCreate, rootSubject: rootOnPageToCome } );

					await createReferencedTarget( wrapper );
					await triggerSave( wrapper, '' );
					await reportsRelationTo( wrapper, 0, [] );

					wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
					await flushPromises();

					expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
					expect( wrapper.findComponent( CloseConfirmationDialog ).props( 'open' ) ).toBe( true );
				} );

				it( 'asks on close once the host reports something of its own', async () => {
					const { wrapper } = await mountCreating( { extraProps: { hostHasUnsavedChanges: true } } );

					wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
					await flushPromises();

					expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
					expect( wrapper.findComponent( CloseConfirmationDialog ).props( 'open' ) ).toBe( true );
				} );

				it( 'withholds save while the host has a question outstanding', async () => {
					const { wrapper } = await mountCreating( { extraProps: { saveDisabled: true } } );

					expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( true );
				} );

				// Its own write is what settles the page the Subjects beside it are stored on, so it
				// cannot wait for them the way relations would otherwise have it.
				it( 'writes the root before the Subjects it points at', async () => {
					const onCreate = vi.fn().mockResolvedValue( undefined );
					const { wrapper } = await mountCreating( { onCreate } );

					const created = await createReferencedTarget( wrapper );
					await triggerSave( wrapper, '' );

					expect( subjectIdsPassedTo( onCreate ) ).toEqual( [ rootSubjectId, created?.getId().text ] );
				} );

				it( 'writes the root first whatever page it carries', async () => {
					const onCreate = vi.fn().mockResolvedValue( undefined );
					const { wrapper } = await mountCreating( { onCreate, rootSubject: rootOnPageToCome } );

					const created = await createReferencedTarget( wrapper );
					await triggerSave( wrapper, '' );

					expect( subjectIdsPassedTo( onCreate ) ).toEqual( [ rootSubjectId, created?.getId().text ] );
				} );

				// Two of the three ways a root is created have the server mint its id, so a
				// relation recorded against the one held here could name an id it never gets.
				it( 'leaves the root out of the drafts a relation field may point at', async () => {
					const { wrapper } = await mountCreating();

					const created = await createTarget( wrapper, { schemaName: rootSchemaName } );

					expect( providedCreation( wrapper )?.drafts( rootSchemaName )
						.map( ( draft ) => draft.getId().text ) ).toEqual( [ created?.getId().text ] );
				} );

				it( 'reports the save to the host in place of closing itself', async () => {
					const onSaved = vi.fn();
					const { wrapper } = await mountCreating( { extraProps: { onSaved } } );

					await triggerSave( wrapper, '' );

					expect( onSaved ).toHaveBeenCalled();
					expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
				} );

				// The host leaves the page on it, so a report before the last write lands would
				// take the rest of the save with it.
				it( 'reports the save only once every write is through', async () => {
					const order: string[] = [];
					const onSaved = vi.fn( () => {
						order.push( 'saved' );
					} );
					const onCreate = vi.fn( async ( subject: Subject ) => {
						order.push( subject.getId().text );
					} );
					const { wrapper } = await mountCreating( { onCreate, extraProps: { onSaved } } );

					const created = await createReferencedTarget( wrapper );
					await triggerSave( wrapper, '' );

					expect( order ).toEqual( [ rootSubjectId, created?.getId().text, 'saved' ] );
				} );

				it( 'names itself and its button for a creation', async () => {
					const { wrapper } = await mountCreating();

					expect( wrapper.findComponent( CdxDialog ).props( 'title' ) )
						.toBe( 'neowiki-subject-creator-title' );
					expect( wrapper.findComponent( SummaryAction ).props( 'saveButtonLabel' ) )
						.toBe( 'neowiki-subject-creator-save' );
				} );

				it( 'writes with the create summary where the user gave none', async () => {
					const onCreate = vi.fn().mockResolvedValue( undefined );
					const { wrapper } = await mountCreating( { onCreate } );

					await triggerSave( wrapper, '' );

					expect( onCreate ).toHaveBeenCalledWith(
						expect.anything(),
						expect.any( Number ),
						'neowiki-subject-editor-summary-default-create',
					);
				} );
			} );
		} );
	} );

	// Codex draws the rules under the header and above the footer only for a dialog whose own
	// body scrolls, which this one's never does, so the variant is asked for by name.
	it( 'asks Codex for the dividers variant, navigator or no navigator', async () => {
		const wrapper = mountComponent( false, saveButtonTestStubs );
		await flushPromises();

		expect( wrapper.findComponent( OpenSubjectList ).exists() ).toBe( false );
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
