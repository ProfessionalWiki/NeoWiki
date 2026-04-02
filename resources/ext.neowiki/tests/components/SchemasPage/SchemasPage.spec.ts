import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import SchemasPage from '@/components/SchemasPage/SchemasPage.vue';
import SchemaCreatorDialog from '@/components/SchemasPage/SchemaCreatorDialog.vue';
import SchemaEditorDialog from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { CdxButton, CdxDialog } from '@wikimedia/codex';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';

const canCreateSchemasRef = ref( false );
const canEditSchemaRef = ref( false );
const checkCreatePermissionMock = vi.fn();
const checkEditPermissionMock = vi.fn();

let schemasResponse: { schemas: unknown[]; totalRows: number } = { schemas: [], totalRows: 0 };

vi.mock( '@/composables/useSchemaPermissions.ts', () => ( {
	useSchemaPermissions: () => ( {
		canCreateSchemas: canCreateSchemasRef,
		canEditSchema: canEditSchemaRef,
		checkCreatePermission: checkCreatePermissionMock,
		checkEditPermission: checkEditPermissionMock,
	} ),
} ) );

const fetchSchemaMock = vi.fn();
const getSchemaMock = vi.fn();

vi.mock( '@/stores/SchemaStore.ts', () => ( {
	useSchemaStore: () => ( {
		fetchSchema: fetchSchemaMock,
		getSchema: getSchemaMock,
		saveSchema: vi.fn(),
	} ),
} ) );

vi.mock( '@/NeoWikiExtension.ts', () => ( {
	NeoWikiExtension: {
		getInstance: () => ( {
			getMediaWiki: () => ( {
				util: { wikiScript: () => '/rest.php' },
			} ),
			newHttpClient: () => ( {
				get: vi.fn().mockResolvedValue( {
					ok: true,
					json: () => Promise.resolve( schemasResponse ),
				} ),
			} ),
		} ),
	},
} ) );

const SchemaCreatorDialogStub = {
	template: '<div class="schema-creator-dialog-stub"></div>',
	props: [ 'open' ],
	emits: [ 'update:open', 'created' ],
};

const SchemaEditorDialogStub = {
	template: '<div class="schema-editor-dialog-stub"></div>',
	props: [ 'open', 'initialSchema', 'onSave' ],
	emits: [ 'update:open', 'saved' ],
};

function findCreateButton( wrapper: VueWrapper ): VueWrapper | undefined {
	return wrapper.findAllComponents( CdxButton )
		.find( ( btn ) => btn.text().includes( 'neowiki-schema-creator-button' ) );
}

function findEditButtons( wrapper: VueWrapper ): VueWrapper[] {
	return wrapper.findAllComponents( CdxButton )
		.filter( ( btn ) => btn.attributes( 'aria-label' ) === 'neowiki-edit-schema' );
}

function findDeleteButtons( wrapper: VueWrapper ): VueWrapper[] {
	return wrapper.findAllComponents( CdxButton )
		.filter( ( btn ) => btn.attributes( 'aria-label' ) === 'neowiki-schema-delete' );
}

function mountComponent( summaries: unknown[] = [] ): VueWrapper {
	schemasResponse = {
		schemas: summaries,
		totalRows: summaries.length,
	};
	setupMwMock( { functions: [ 'msg', 'util', 'message', 'notify' ] } );

	return mount( SchemasPage, {
		global: {
			mocks: { $i18n: createI18nMock() },
			stubs: {
				SchemaCreatorDialog: SchemaCreatorDialogStub,
				SchemaEditorDialog: SchemaEditorDialogStub,
				EditSummary: true,
				I18nSlot: true,
				CdxIcon: true,
			},
		},
	} );
}

describe( 'SchemasPage', () => {
	beforeEach( () => {
		canCreateSchemasRef.value = false;
		canEditSchemaRef.value = false;
		checkCreatePermissionMock.mockClear();
		checkEditPermissionMock.mockClear();
		fetchSchemaMock.mockClear();
		getSchemaMock.mockClear();
		schemasResponse = { schemas: [], totalRows: 0 };
	} );

	it( 'shows create button when user has create permission', async () => {
		canCreateSchemasRef.value = true;
		const wrapper = mountComponent();
		await flushPromises();

		expect( findCreateButton( wrapper ) ).toBeDefined();
	} );

	it( 'hides create button when user lacks permission', async () => {
		canCreateSchemasRef.value = false;
		const wrapper = mountComponent();
		await flushPromises();

		expect( findCreateButton( wrapper ) ).toBeUndefined();
	} );

	it( 'opens SchemaCreatorDialog when button is clicked', async () => {
		canCreateSchemasRef.value = true;
		const wrapper = mountComponent();
		await flushPromises();

		expect( wrapper.findComponent( SchemaCreatorDialog ).props( 'open' ) ).toBe( false );

		await findCreateButton( wrapper )!.trigger( 'click' );

		expect( wrapper.findComponent( SchemaCreatorDialog ).props( 'open' ) ).toBe( true );
	} );

	it( 'does not render SchemaCreatorDialog when user lacks permission', async () => {
		canCreateSchemasRef.value = false;
		const wrapper = mountComponent();
		await flushPromises();

		expect( wrapper.findComponent( SchemaCreatorDialog ).exists() ).toBe( false );
	} );

	it( 'shows empty value indicator for schemas without a description', async () => {
		const wrapper = mountComponent( [
			{ name: 'Person', description: '', propertyCount: 3 },
		] );
		await flushPromises();

		const emptyValue = wrapper.find( '.ext-neowiki-schemas-page__empty-value' );

		expect( emptyValue.exists() ).toBe( true );
		expect( emptyValue.text() ).toBe( '-' );
	} );

	it( 'does not show empty value indicator when description is present', async () => {
		const wrapper = mountComponent( [
			{ name: 'Person', description: 'A human being', propertyCount: 3 },
		] );
		await flushPromises();

		expect( wrapper.find( '.ext-neowiki-schemas-page__empty-value' ).exists() ).toBe( false );
		expect( wrapper.text() ).toContain( 'A human being' );
	} );

	it( 'shows edit and delete buttons when user has edit permission', async () => {
		canEditSchemaRef.value = true;
		const wrapper = mountComponent( [
			{ name: 'Person', description: '', propertyCount: 3 },
			{ name: 'Company', description: '', propertyCount: 2 },
		] );
		await flushPromises();

		expect( findEditButtons( wrapper ) ).toHaveLength( 2 );
		expect( findDeleteButtons( wrapper ) ).toHaveLength( 2 );
	} );

	it( 'hides edit and delete buttons when user lacks edit permission', async () => {
		canEditSchemaRef.value = false;
		const wrapper = mountComponent( [
			{ name: 'Person', description: '', propertyCount: 3 },
		] );
		await flushPromises();

		expect( findEditButtons( wrapper ) ).toHaveLength( 0 );
		expect( findDeleteButtons( wrapper ) ).toHaveLength( 0 );
	} );

	it( 'opens editor dialog when edit button is clicked', async () => {
		canEditSchemaRef.value = true;
		const mockSchema = new Schema( 'Person', '', new PropertyDefinitionList( [] ) );
		getSchemaMock.mockReturnValue( mockSchema );

		const wrapper = mountComponent( [
			{ name: 'Person', description: '', propertyCount: 3 },
		] );
		await flushPromises();

		await findEditButtons( wrapper )[ 0 ].trigger( 'click' );
		await flushPromises();

		expect( fetchSchemaMock ).toHaveBeenCalledWith( 'Person' );
		expect( wrapper.findComponent( SchemaEditorDialog ).props( 'open' ) ).toBe( true );
	} );

	it( 'does not render SchemaEditorDialog when user lacks edit permission', async () => {
		canEditSchemaRef.value = true;
		const mockSchema = new Schema( 'Person', '', new PropertyDefinitionList( [] ) );
		getSchemaMock.mockReturnValue( mockSchema );

		const wrapper = mountComponent( [
			{ name: 'Person', description: '', propertyCount: 3 },
		] );
		await flushPromises();

		await findEditButtons( wrapper )[ 0 ].trigger( 'click' );
		await flushPromises();

		expect( wrapper.findComponent( SchemaEditorDialog ).exists() ).toBe( true );

		canEditSchemaRef.value = false;
		await flushPromises();

		expect( wrapper.findComponent( SchemaEditorDialog ).exists() ).toBe( false );
	} );

	it( 'opens delete confirmation when delete button is clicked', async () => {
		canEditSchemaRef.value = true;
		const wrapper = mountComponent( [
			{ name: 'Person', description: '', propertyCount: 3 },
		] );
		await flushPromises();

		await findDeleteButtons( wrapper )[ 0 ].trigger( 'click' );

		const dialog = wrapper.findComponent( CdxDialog );
		expect( dialog.props( 'open' ) ).toBe( true );
	} );
} );
