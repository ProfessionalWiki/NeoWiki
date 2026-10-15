import { DOMWrapper, mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, onTestFinished, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import SchemaCreatorDialog from '@/components/SchemaCreator/SchemaCreatorDialog.vue';
import RelationAttributesEditor from '@/components/SchemaEditor/Property/RelationAttributesEditor.vue';
import TargetSchemasInput from '@/components/SchemaEditor/Property/TargetSchemasInput.vue';
import { newRelationProperty } from '@/domain/propertyTypes/Relation';
import SummaryAction from '@/components/common/SummaryAction.vue';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { CdxDialog } from '@wikimedia/codex';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Service } from '@/NeoWikiServices.ts';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { newTextProperty } from '@/domain/propertyTypes/Text.ts';
import { newNumberProperty } from '@/domain/propertyTypes/Number.ts';
import { NeoWikiTestServices } from '../../NeoWikiTestServices.ts';
import type { SaveBlocker } from '@/components/common/SaveBlocker.ts';
import { newSchema } from '@/TestHelpers.ts';

const NEW_SCHEMA_NAME = 'Company';

const COMPANY = newSchema( {
	title: 'Company',
	description: 'An organisation that sells things',
	properties: new PropertyDefinitionList( [ newTextProperty( { name: 'Name' } ), newNumberProperty( { name: 'Founded' } ) ] ),
} );

// The reason the stubbed creator reports for holding the save, and whether it was asked to
// reveal its errors. Reset per test by the beforeEach below.
let creatorSaveBlocker: SaveBlocker | null = null;
let creatorErrorsRevealed = false;

const SchemaCreatorStub = {
	template: '<div class="schema-creator-stub"></div>',
	props: { initialName: String, duplicateOf: Object, subjectsAreDependent: { type: [ Boolean, null ], default: null } },
	emits: [ 'change' ],
	setup() {
		let valid = true;
		const schema: Schema | null = newSchema( { title: NEW_SCHEMA_NAME, description: 'A description', properties: new PropertyDefinitionList( [] ) } );

		const validate = vi.fn( async (): Promise<boolean> => valid );
		const getSchema = vi.fn( (): Schema | null => schema );
		const saveBlocker = (): SaveBlocker | null => creatorSaveBlocker;
		const revealErrors = (): void => {
			creatorErrorsRevealed = true;
		};

		return {
			validate,
			getSchema,
			saveBlocker,
			revealErrors,
			setStubValid( v: boolean ) {
				valid = v;
			},
		};
	},
};

const SummaryActionStub = {
	template: '<div class="edit-summary-stub"><button class="save-button" @click="$emit( \'save\', \'\' )">Save</button></div>',
	props: [ 'helpText', 'saveButtonLabel', 'saveDisabled' ],
	emits: [ 'save' ],
};

const CdxDialogStub = {
	template: '<div class="cdx-dialog-stub"><slot /><slot name="footer" /></div>',
	props: [ 'open', 'title', 'useCloseButton' ],
	emits: [ 'update:open' ],
};

const CloseConfirmationDialogStub = {
	template: '<div class="close-confirmation-stub"></div>',
	props: [ 'open' ],
	emits: [ 'discard', 'keep-editing' ],
};

describe( 'SchemaCreatorDialog', () => {
	let pinia: ReturnType<typeof createPinia>;
	let schemaStore: ReturnType<typeof useSchemaStore>;

	function mountComponent( props: Record<string, unknown> = {} ): VueWrapper {
		return mount( SchemaCreatorDialog, {
			props: { open: true, ...props },
			global: {
				plugins: [ pinia ],
				stubs: {
					SchemaCreator: SchemaCreatorStub,
					SummaryAction: SummaryActionStub,
					CloseConfirmationDialog: CloseConfirmationDialogStub,
					CdxDialog: CdxDialogStub,
					CdxIcon: true,
					teleport: true,
				},
				provide: {
					[ Service.ComponentRegistry ]: NeoWikiExtension.getInstance().getTypeSpecificComponentRegistry(),
					[ Service.PropertyTypeRegistry ]: NeoWikiExtension.getInstance().getPropertyTypeRegistry(),
				},
				mocks: {
					$i18n: createI18nMock(),
				},
			},
		} );
	}

	beforeEach( () => {
		creatorSaveBlocker = null;
		creatorErrorsRevealed = false;

		setupMwMock( {
			functions: [ 'msg', 'notify' ],
		} );

		pinia = createPinia();
		setActivePinia( pinia );

		schemaStore = useSchemaStore();
		schemaStore.saveSchema = vi.fn().mockResolvedValue( undefined );
	} );

	it( 'hands the creator a decision on whether the Subjects are dependent', () => {
		const wrapper = mountComponent( { subjectsAreDependent: true } );

		expect( wrapper.findComponent( SchemaCreatorStub ).props( 'subjectsAreDependent' ) ).toBe( true );
	} );

	it( 'leaves whether the Subjects are dependent to the user where it was given no decision', () => {
		const wrapper = mountComponent();

		expect( wrapper.findComponent( SchemaCreatorStub ).props( 'subjectsAreDependent' ) ).toBeNull();
	} );

	it( 'starts the schema on the name it is given', () => {
		const wrapper = mountComponent( { initialName: 'Artwork' } );

		expect( wrapper.findComponent( SchemaCreatorStub ).props( 'initialName' ) ).toBe( 'Artwork' );
	} );

	describe( 'Offering the save', () => {
		function saveDisabled( wrapper: VueWrapper ): boolean {
			return wrapper.findComponent( SummaryAction ).props( 'saveDisabled' );
		}

		it( 'holds it back while nothing has been entered', () => {
			const wrapper = mountComponent();

			expect( saveDisabled( wrapper ) ).toBe( true );
		} );

		it( 'offers it once something is entered', async () => {
			const wrapper = mountComponent();

			await wrapper.findComponent( SchemaCreatorStub ).vm.$emit( 'change' );

			expect( saveDisabled( wrapper ) ).toBe( false );
		} );

		it( 'offers it at once for a schema it is given a name for', () => {
			const wrapper = mountComponent( { initialName: 'Artwork' } );

			expect( saveDisabled( wrapper ) ).toBe( false );
		} );
	} );

	it( 'does not save when validation fails', async () => {
		const wrapper = mountComponent();

		( wrapper.findComponent( SchemaCreatorStub ).vm as any ).setStubValid( false );

		await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
		await flushPromises();

		expect( schemaStore.saveSchema ).not.toHaveBeenCalled();
	} );

	it( 'saves schema and emits created on success', async () => {
		const wrapper = mountComponent();

		await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', 'My summary' );
		await flushPromises();

		expect( schemaStore.saveSchema ).toHaveBeenCalledWith(
			expect.any( Schema ),
			'My summary',
		);

		const createdEvents = wrapper.emitted( 'created' ) as Schema[][];
		expect( createdEvents ).toHaveLength( 1 );
		expect( createdEvents[ 0 ][ 0 ].getName() ).toBe( NEW_SCHEMA_NAME );

		expect( mw.notify ).toHaveBeenCalledWith(
			expect.any( String ),
			expect.objectContaining( { type: 'success' } ),
		);
	} );

	it( 'uses default summary when none provided', async () => {
		const wrapper = mountComponent();

		await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
		await flushPromises();

		expect( schemaStore.saveSchema ).toHaveBeenCalledWith(
			expect.any( Schema ),
			expect.any( String ),
		);

		const usedSummary = ( schemaStore.saveSchema as ReturnType<typeof vi.fn> ).mock.calls[ 0 ][ 1 ];
		expect( usedSummary ).not.toBe( '' );
	} );

	it( 'shows error notification on save failure', async () => {
		schemaStore.saveSchema = vi.fn().mockRejectedValue( new Error( 'Server error' ) );

		const wrapper = mountComponent();

		await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
		await flushPromises();

		expect( mw.notify ).toHaveBeenCalledWith(
			'Server error',
			expect.objectContaining( { type: 'error' } ),
		);

		expect( wrapper.emitted( 'created' ) ).toBeUndefined();
	} );

	it( 'closes dialog on emit update:open false', async () => {
		const wrapper = mountComponent();

		wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
		await flushPromises();

		expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
	} );

	it( 'is titled for creating a Schema', () => {
		expect( mountComponent().findComponent( CdxDialog ).props( 'title' ) ).toBe( 'neowiki-schema-creator-title' );
	} );

	// Special:Schemas keeps the dialog mounted, opening it on one Schema after another.
	it( 'is titled after the Schema it duplicates when it opens on one', async () => {
		const wrapper = mountComponent( { open: false } );

		await wrapper.setProps( { open: true, duplicateOf: COMPANY } );

		expect( wrapper.findComponent( CdxDialog ).props( 'title' ) ).toBe( 'neowiki-schema-creator-duplicate-titleCompany' );
	} );

	// The real creator and Schema editor, attached to the document for the focus they move.
	function mountWithRealCreator( props: { open: boolean; duplicateOf?: Schema }, dialogStubs: Record<string, unknown> ): VueWrapper {
		return mount( SchemaCreatorDialog, {
			attachTo: document.body,
			props,
			global: {
				plugins: [ pinia ],
				stubs: {
					SummaryAction: SummaryActionStub,
					CloseConfirmationDialog: CloseConfirmationDialogStub,
					CdxIcon: true,
					...dialogStubs,
				},
				provide: NeoWikiTestServices.getServices(),
				directives: { tooltip: {} },
				mocks: { $i18n: createI18nMock() },
			},
		} );
	}

	describe( 'Duplicating', () => {
		function mountDuplicating( schema: Schema ): VueWrapper {
			const wrapper = mountWithRealCreator( { open: true, duplicateOf: schema }, { CdxDialog: CdxDialogStub, teleport: true } );

			onTestFinished( () => wrapper.unmount() );
			return wrapper;
		}

		function findNameInput( wrapper: VueWrapper ): DOMWrapper<HTMLInputElement> {
			return wrapper.find<HTMLInputElement>( '.ext-neowiki-schema-creator__name-section input' );
		}

		function nameAndDescription( wrapper: VueWrapper ): string[] {
			return wrapper.findAll<HTMLInputElement>( '.ext-neowiki-schema-creator__name-section .cdx-text-input input' )
				.map( ( input ) => input.element.value );
		}

		function propertyNames( wrapper: VueWrapper ): string[] {
			return wrapper.findAll(
				'.ext-neowiki-property-list__item:not( .ext-neowiki-property-list__item--subject-label ) ' +
				'.ext-neowiki-property-list__item__text__label',
			).map( ( label ) => label.text() );
		}

		beforeEach( () => {
			setupMwMock();
			schemaStore.schemaNameExists = vi.fn().mockResolvedValue( false );
		} );

		it( 'opens on the description and property definitions of that Schema, with no name', () => {
			const wrapper = mountDuplicating( COMPANY );

			expect( nameAndDescription( wrapper ) ).toEqual( [ '', 'An organisation that sells things' ] );
			expect( propertyNames( wrapper ) ).toEqual( [ 'Name', 'Founded' ] );
		} );

		it( 'starts with the cursor in the name field', async () => {
			const wrapper = mountDuplicating( COMPANY );
			await flushPromises();

			expect( document.activeElement ).toBe( findNameInput( wrapper ).element );
		} );

		it( 'saves the duplicate under the name typed, with everything else of that Schema', async () => {
			const wrapper = mountDuplicating( COMPANY );

			await findNameInput( wrapper ).setValue( 'Group' );
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( schemaStore.saveSchema ).toHaveBeenCalledOnce();
			expect( schemaStore.saveSchema ).toHaveBeenCalledWith( COMPANY.withName( 'Group' ), expect.any( String ) );
		} );
	} );

	describe( 'Focus', () => {
		beforeEach( () => {
			setupMwMock();
		} );

		// Mounted open and removed on close, as the dialogs that stack it in front of themselves do.
		it( 'hands the focus back to where it was once closed', async () => {
			const opener = document.body.appendChild( document.createElement( 'input' ) );
			onTestFinished( () => opener.remove() );
			opener.focus();
			const wrapper = mountWithRealCreator( { open: true }, {} );
			await flushPromises();

			wrapper.unmount();
			await flushPromises();

			expect( document.activeElement ).toBe( opener );
		} );
	} );

	describe( 'Blocked save', () => {
		async function save( wrapper: VueWrapper ): Promise<void> {
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();
		}

		it( 'does not save while the creator reports a reason not to', async () => {
			const wrapper = mountComponent();
			creatorSaveBlocker = { propertyName: 'Score', message: 'neowiki-field-invalid-number' };

			await save( wrapper );

			expect( schemaStore.saveSchema ).not.toHaveBeenCalled();
			expect( wrapper.emitted( 'created' ) ).toBeUndefined();
			expect( mw.notify ).toHaveBeenCalledWith(
				'neowiki-field-invalid-number',
				{ title: 'Score', type: 'error' },
			);
		} );

		it( 'saves once the creator reports none', async () => {
			const wrapper = mountComponent();
			creatorSaveBlocker = { propertyName: 'Score', message: 'neowiki-field-invalid-number' };
			await save( wrapper );

			creatorSaveBlocker = null;
			await save( wrapper );

			expect( schemaStore.saveSchema ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'reveals the errors of the creator', async () => {
			const wrapper = mountComponent();
			creatorSaveBlocker = { propertyName: 'Maker', message: 'neowiki-property-editor-target-schema-required' };

			await save( wrapper );

			expect( creatorErrorsRevealed ).toBe( true );
		} );
	} );

	describe( 'Stacking the schema creator a relation property asks for', () => {
		async function mountWithRelationProperty(): Promise<VueWrapper> {
			const wrapper = mount( SchemaCreatorDialog, {
				props: { open: true },
				global: {
					plugins: [ pinia ],
					stubs: {
						SchemaCreator: {
							components: { RelationAttributesEditor },
							setup: () => ( { property: newRelationProperty( {} ) } ),
							template: '<RelationAttributesEditor :property="property" />',
						},
						TargetSchemasInput: { template: '<div></div>', emits: [ 'update:modelValue', 'create' ] },
						SeverityInput: true,
						SummaryAction: SummaryActionStub,
						CloseConfirmationDialog: CloseConfirmationDialogStub,
						CdxDialog: CdxDialogStub,
					},
					provide: {
						[ Service.SchemaPermissionHints ]: { canCreateSchemas: async () => true },
					},
					mocks: {
						$i18n: createI18nMock(),
					},
				},
			} );
			await flushPromises();

			return wrapper;
		}

		// Asked for from the Target schemas field of the dialog in front.
		async function askForSchema( wrapper: VueWrapper, name: string, subjectsAreDependent: boolean | null = null ): Promise<void> {
			const fields = wrapper.findAllComponents( TargetSchemasInput );
			fields[ fields.length - 1 ].vm.$emit( 'create', name, subjectsAreDependent );
			await flushPromises();
		}

		function stackedDialog( wrapper: VueWrapper ): VueWrapper<InstanceType<typeof SchemaCreatorDialog>> {
			return wrapper.findAllComponents( SchemaCreatorDialog ).filter( ( dialog ) => dialog.vm !== wrapper.vm )[ 0 ];
		}

		it( 'decides the stacked Schema\'s Subjects like those of the property\'s targets', async () => {
			const wrapper = await mountWithRelationProperty();

			await askForSchema( wrapper, 'Birth', true );

			expect( stackedDialog( wrapper ).props( 'subjectsAreDependent' ) ).toBe( true );
		} );

		it( 'leaves the stacked Schema\'s Subjects to the user where the property has no targets to go by', async () => {
			const wrapper = await mountWithRelationProperty();

			await askForSchema( wrapper, 'Birth', null );

			expect( stackedDialog( wrapper ).props( 'subjectsAreDependent' ) ).toBeNull();
		} );

		// From Codex 2.3 a dialog mounted inside another is teleported into it, where Escape closes both.
		it( 'stacks it next to its own dialog', async () => {
			const wrapper = await mountWithRelationProperty();

			await askForSchema( wrapper, 'Artist' );

			const stacked = wrapper.findAllComponents( SchemaCreatorDialog ).filter( ( dialog ) => dialog.vm !== wrapper.vm );
			expect( stacked.map( ( dialog ) => dialog.props( 'initialName' ) ) ).toEqual( [ 'Artist' ] );
			expect( wrapper.findComponent( CdxDialog ).findAllComponents( SchemaCreatorDialog ) ).toHaveLength( 0 );
		} );

		it( 'tells each dialog how many Schema dialogs stand behind it', async () => {
			const wrapper = await mountWithRelationProperty();

			await askForSchema( wrapper, 'Artist' );
			await askForSchema( wrapper, 'Workshop' );

			expect( wrapper.findAll<HTMLElement>( '.cdx-dialog-stub' ).map(
				( dialog ) => dialog.element.style.getPropertyValue( '--ext-neowiki-schema-dialog-stack-level' ),
			) ).toEqual( [ '0', '1', '2' ] );
		} );
	} );
} );
