import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import SchemaCreatorDialog from '@/components/SchemasPage/SchemaCreatorDialog.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { CdxDialog } from '@wikimedia/codex';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Service } from '@/NeoWikiServices.ts';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import type { UnparseableInput } from '@/components/common/UnparseableInput.ts';

const NEW_SCHEMA_NAME = 'Company';

// What the stubbed creator reports about a field holding text it cannot turn
// into a value. Reset per test by the beforeEach below.
let creatorUnparseableInput: UnparseableInput | null = null;

const SchemaCreatorStub = {
	template: '<div class="schema-creator-stub"></div>',
	emits: [ 'change' ],
	setup() {
		let valid = true;
		const schema: Schema | null = new Schema( NEW_SCHEMA_NAME, 'A description', new PropertyDefinitionList( [] ) );

		const validate = vi.fn( async (): Promise<boolean> => valid );
		const getSchema = vi.fn( (): Schema | null => schema );
		const unparseableInput = (): UnparseableInput | null => creatorUnparseableInput;
		const reset = vi.fn();
		const focus = vi.fn();

		return {
			validate,
			getSchema,
			unparseableInput,
			reset,
			focus,
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

	function mountComponent( open = true ): VueWrapper {
		return mount( SchemaCreatorDialog, {
			props: { open },
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
		creatorUnparseableInput = null;

		setupMwMock( {
			functions: [ 'msg', 'notify' ],
		} );

		pinia = createPinia();
		setActivePinia( pinia );

		schemaStore = useSchemaStore();
		schemaStore.saveSchema = vi.fn().mockResolvedValue( undefined );
	} );

	// This creator's body is the scroller, so Codex adds the rules itself whenever the
	// schema is long enough. The class is asked for by name to cover the case it misses:
	// a schema short enough not to scroll, where the desktop rule between the columns is
	// still drawn and needs one to run into at each end.
	it( 'asks Codex for the dividers variant', async () => {
		const wrapper = mountComponent();
		await flushPromises();

		expect( wrapper.find( '.cdx-dialog-stub' ).classes() ).toContain( 'cdx-dialog--dividers' );
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

	describe( 'Unparseable field input', () => {
		async function save( wrapper: VueWrapper ): Promise<void> {
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();
		}

		it( 'does not save while the initial-value field holds text that cannot be turned into a value', async () => {
			const wrapper = mountComponent();
			creatorUnparseableInput = { propertyName: 'Score', message: 'neowiki-field-invalid-number' };

			await save( wrapper );

			expect( schemaStore.saveSchema ).not.toHaveBeenCalled();
			expect( wrapper.emitted( 'created' ) ).toBeUndefined();
			expect( mw.notify ).toHaveBeenCalledWith(
				'neowiki-field-invalid-number',
				{ title: 'Score', type: 'error' },
			);
		} );

		it( 'saves once the text parses again', async () => {
			const wrapper = mountComponent();
			creatorUnparseableInput = { propertyName: 'Score', message: 'neowiki-field-invalid-number' };
			await save( wrapper );

			creatorUnparseableInput = null;
			await save( wrapper );

			expect( schemaStore.saveSchema ).toHaveBeenCalledTimes( 1 );
		} );
	} );
} );
