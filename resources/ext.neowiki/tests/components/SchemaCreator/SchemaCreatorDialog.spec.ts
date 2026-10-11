import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import SchemaCreatorDialog from '@/components/SchemaCreator/SchemaCreatorDialog.vue';
import RelationAttributesEditor from '@/components/SchemaEditor/Property/RelationAttributesEditor.vue';
import SchemaPicker from '@/components/common/SchemaPicker.vue';
import { newRelationProperty } from '@/domain/propertyTypes/Relation';
import SummaryAction from '@/components/common/SummaryAction.vue';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { CdxDialog } from '@wikimedia/codex';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Service } from '@/NeoWikiServices.ts';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import type { SaveBlocker } from '@/components/common/SaveBlocker.ts';

const NEW_SCHEMA_NAME = 'Company';

// The reason the stubbed creator reports for holding the save. Reset per test by
// the beforeEach below.
let creatorSaveBlocker: SaveBlocker | null = null;

const SchemaCreatorStub = {
	template: '<div class="schema-creator-stub"></div>',
	props: [ 'initialName' ],
	emits: [ 'change' ],
	setup() {
		let valid = true;
		const schema: Schema | null = new Schema( NEW_SCHEMA_NAME, 'A description', new PropertyDefinitionList( [] ) );

		const validate = vi.fn( async (): Promise<boolean> => valid );
		const getSchema = vi.fn( (): Schema | null => schema );
		const saveBlocker = (): SaveBlocker | null => creatorSaveBlocker;

		return {
			validate,
			getSchema,
			saveBlocker,
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

		setupMwMock( {
			functions: [ 'msg', 'notify' ],
		} );

		pinia = createPinia();
		setActivePinia( pinia );

		schemaStore = useSchemaStore();
		schemaStore.saveSchema = vi.fn().mockResolvedValue( undefined );
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
	} );

	// From Codex 2.3 a dialog mounted inside another is teleported into it, where Escape closes both.
	it( 'stacks the schema creator a relation property asks for next to its own dialog', async () => {
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
					SchemaPicker: { template: '<div></div>', emits: [ 'select', 'create' ] },
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

		wrapper.findComponent( SchemaPicker ).vm.$emit( 'create', 'Artist' );
		await flushPromises();

		const stacked = wrapper.findAllComponents( SchemaCreatorDialog ).filter( ( dialog ) => dialog.vm !== wrapper.vm );
		expect( stacked.map( ( dialog ) => dialog.props( 'initialName' ) ) ).toEqual( [ 'Artist' ] );
		expect( wrapper.findComponent( CdxDialog ).findAllComponents( SchemaCreatorDialog ) ).toHaveLength( 0 );
	} );
} );
