import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi, type Mock } from 'vitest';
import SubjectEditorDialog from '@/components/SubjectEditor/SubjectEditorDialog.vue';
import { Subject } from '@/domain/Subject.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { createPropertyDefinitionFromJson } from '@/domain/PropertyDefinition.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import { createPinia, setActivePinia } from 'pinia';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { Service } from '@/NeoWikiServices.ts';
import SchemaEditorDialog from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import { CdxDialog } from '@wikimedia/codex';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { ValidationFailedError } from '@/persistence/ValidationFailedError';
import type { SubjectViolation } from '@/domain/SubjectViolation';

const $i18n = createI18nMock();

const SubjectEditorStub = {
	template: '<div class="subject-editor-stub"></div>',
	props: [ 'statements', 'schema', 'serverViolations' ],
	emits: [ 'change', 'clear-server-violation' ],
	setup() {
		const getSubjectData = (): StatementList => new StatementList( [] );
		return { getSubjectData };
	},
};

const SummaryActionStub = {
	template: '<div class="edit-summary-stub"></div>',
	props: [ 'helpText', 'saveButtonLabel', 'saveDisabled' ],
	emits: [ 'save' ],
};

const CloseConfirmationDialogStub = {
	template: '<div class="close-confirmation-stub"></div>',
	props: [ 'open' ],
	emits: [ 'discard', 'keep-editing' ],
};

describe( 'SubjectEditorDialog', () => {
	beforeEach( () => {
		setupMwMock( {
			functions: [ 'message', 'msg', 'notify', 'config' ],
			// Debounce 0 is blur-only mode: the dry-run fires on blur / pre-save
			// (via flush()), which runs synchronously in tests.
			config: { wgNeoWikiValidationDebounceMs: 0 },
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
		'TestSchema',
		new StatementList( [] ),
	);

	const mountComponent = (
		canEditSchema: boolean,
		stubs: Record<string, any>,
		onSave?: ( subject: any, comment: string ) => Promise<void>,
		schema: Schema = mockSchema,
	): VueWrapper => {
		schemaPermissionHints = {
			canEditSchema: vi.fn().mockResolvedValue( canEditSchema ),
		};

		return mount( SubjectEditorDialog, {
			props: {
				subject: mockSubject,
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
					[ Service.ComponentRegistry ]: NeoWikiExtension.getInstance().getTypeSpecificComponentRegistry(),
					[ Service.SchemaPermissionHints ]: schemaPermissionHints,
					[ Service.PropertyTypeRegistry ]: NeoWikiExtension.getInstance().getPropertyTypeRegistry(),
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
				valuePartIndex: null,
			};
			const onSave = vi.fn().mockRejectedValue( new ValidationFailedError( [ violation ] ) );
			const wrapper = mountComponent( true, validationTestStubs, onSave );
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
				valuePartIndex: null,
			};
			const onSave = vi.fn().mockRejectedValue( new ValidationFailedError( [ violation ] ) );
			const wrapper = mountComponent( true, validationTestStubs, onSave );
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
				valuePartIndex: null,
			};
			const onSave = vi.fn().mockRejectedValue( new ValidationFailedError( [ violation ] ) );
			const wrapper = mountComponent( true, validationTestStubs, onSave );
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
				valuePartIndex: null,
			};
			const onSave = vi.fn().mockRejectedValue( new ValidationFailedError( [ violation ] ) );
			const wrapper = mountComponent( true, validationTestStubs, onSave );
			await flushPromises();

			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( wrapper.find( '.ext-neowiki-subject-editor__form-errors' ).exists() ).toBe( true );

			const passedViolations = wrapper.findComponent( SubjectEditor ).props( 'serverViolations' ) as SubjectViolation[];
			expect( passedViolations ).toHaveLength( 1 );
			expect( passedViolations[ 0 ].propertyName ).toBeNull();
		} );

		it( 'drops the matching entry on clear-server-violation event from child', async () => {
			const violation: SubjectViolation = {
				propertyName: 'name',
				code: 'required',
				args: [],
				valuePartIndex: null,
			};
			const onSave = vi.fn().mockRejectedValue( new ValidationFailedError( [ violation ] ) );
			const wrapper = mountComponent( true, validationTestStubs, onSave );
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
				propertyName: 'name', code: 'required', args: [], valuePartIndex: null,
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
				propertyName: 'name', code: 'required', args: [], valuePartIndex: null,
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

	describe( 'Label editing', () => {
		function titleText( wrapper: VueWrapper ): string {
			return wrapper.find( '.ext-neowiki-editable-text__text' ).text();
		}

		async function editLabel( wrapper: VueWrapper, value: string ): Promise<void> {
			await wrapper.find( 'button[aria-label="neowiki-subject-editor-rename"]' ).trigger( 'click' );
			const input = wrapper.find( '.ext-neowiki-editable-text input' );
			await input.setValue( value );
			await input.trigger( 'keydown.enter' );
		}

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

		it( 'does not save when the label is blank', async () => {
			const onSave = vi.fn().mockResolvedValue( undefined );
			const wrapper = mountComponent( false, validationTestStubs, onSave );
			await flushPromises();

			await editLabel( wrapper, '   ' );
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( onSave ).not.toHaveBeenCalled();
			expect( ( mw.notify as Mock ).mock.calls ).toContainEqual( [
				expect.stringContaining( 'neowiki-field-label-required' ),
				{ type: 'error' },
			] );
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

		it( 'points assistive technology at the label error', async () => {
			const labelRequired: SubjectViolation = {
				propertyName: null, code: 'label-required', args: [], valuePartIndex: null,
			};
			useSubjectStore().validateSubjectUpdate = vi.fn().mockResolvedValue( [ labelRequired ] );
			const wrapper = mountComponent( false, validationTestStubs );
			await flushPromises();

			await wrapper.find( 'button[aria-label="neowiki-subject-editor-rename"]' ).trigger( 'click' );

			const error = wrapper.find( '.ext-neowiki-subject-editor-dialog__label-error' );
			const input = wrapper.find( '.ext-neowiki-editable-text input' );
			expect( error.attributes( 'role' ) ).toBe( 'alert' );
			expect( input.attributes( 'aria-invalid' ) ).toBe( 'true' );
		} );

		it( 'keeps the rename input open in error state when the label is committed blank', async () => {
			const labelRequired: SubjectViolation = {
				propertyName: null, code: 'label-required', args: [], valuePartIndex: null,
			};
			useSubjectStore().validateSubjectUpdate = vi.fn().mockResolvedValue( [ labelRequired ] );
			const wrapper = mountComponent( false, validationTestStubs );
			await flushPromises();

			await editLabel( wrapper, '' );
			await flushPromises();

			expect( wrapper.find( '.ext-neowiki-editable-text input' ).exists() ).toBe( true );
			expect( wrapper.find( '.cdx-text-input--status-error' ).exists() ).toBe( true );
		} );

		it( 'routes the label-required violation to the title area instead of the banner', async () => {
			const labelRequired: SubjectViolation = {
				propertyName: null, code: 'label-required', args: [], valuePartIndex: null,
			};
			useSubjectStore().validateSubjectUpdate = vi.fn().mockResolvedValue( [ labelRequired ] );
			const wrapper = mountComponent( false, validationTestStubs );
			await flushPromises();

			expect( wrapper.find( '.ext-neowiki-subject-editor-dialog__label-error' ).text() )
				.toContain( 'neowiki-field-label-required' );
			expect( wrapper.find( '.ext-neowiki-subject-editor__form-errors' ).exists() ).toBe( false );
		} );

		it( 'follows the label when the host replaces the subject', async () => {
			const wrapper = mountComponent( false, validationTestStubs );
			await flushPromises();

			await wrapper.setProps( {
				subject: new Subject(
					new SubjectId( 's1demo5sssssss1' ),
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
} );
