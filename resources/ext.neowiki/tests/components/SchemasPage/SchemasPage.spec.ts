import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import SchemasPage from '@/components/SchemasPage/SchemasPage.vue';
import SchemaCreatorDialog from '@/components/SchemasPage/SchemaCreatorDialog.vue';
import SchemaEditorDialog from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import DeletePageDialog from '@/components/common/DeletePageDialog.vue';
import { createI18nMock, findNextPageButton, setupMwMock } from '../../VueTestHelpers.ts';
import { CdxButton } from '@wikimedia/codex';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { Service } from '@/NeoWikiServices.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { newSchema } from '@/TestHelpers.ts';

const canCreateSchemasRef = ref( false );
const canEditSchemaRef = ref( false );
const checkCreatePermissionMock = vi.fn();
const checkEditPermissionMock = vi.fn();

let schemasResponse: { schemas: unknown[]; nextCursor: string | null } = { schemas: [], nextCursor: null };
let pinia: ReturnType<typeof createPinia>;
let schemaStore: ReturnType<typeof useSchemaStore>;

vi.mock( '@/composables/useSchemaPermissions.ts', () => ( {
	useSchemaPermissions: () => ( {
		canCreateSchemas: canCreateSchemasRef,
		canEditSchema: canEditSchemaRef,
		checkCreatePermission: checkCreatePermissionMock,
		checkEditPermission: checkEditPermissionMock,
	} ),
} ) );

// The store is real (backed by Pinia) so removeSchema/getSchema exercise their actual
// semantics. The editor reads through the repository, which is mocked here so the edit
// path never reaches the network.
const getSchemaMock = vi.fn();

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

function mountComponent( summaries: unknown[] = [], nextCursor: string | null = null ): VueWrapper {
	schemasResponse = {
		schemas: summaries,
		nextCursor: nextCursor,
	};
	setupMwMock( { functions: [ 'msg', 'util', 'message', 'notify' ] } );

	return mount( SchemasPage, {
		global: {
			plugins: [ pinia ],
			mocks: { $i18n: createI18nMock() },
			provide: {
				[ Service.SchemaRepository ]: { getSchema: getSchemaMock },
			},
			stubs: {
				SchemaCreatorDialog: SchemaCreatorDialogStub,
				SchemaEditorDialog: SchemaEditorDialogStub,
				DeletePageDialog: true,
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
		getSchemaMock.mockReset();
		schemasResponse = { schemas: [], nextCursor: null };

		pinia = createPinia();
		setActivePinia( pinia );
		schemaStore = useSchemaStore();
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

	it( 'disables next when a full page ends the listing', async () => {
		// A listing that ends exactly on a page boundary returns a full page with a null
		// cursor. CdxTable's indeterminate mode would keep next enabled (its heuristic is a
		// short page), so the component must switch the table to a known total.
		const wrapper = mountComponent( Array.from( { length: 10 }, ( _value, index ) => (
			{ name: `Schema${ index }`, description: '', propertyCount: 1 }
		) ) );
		await flushPromises();

		const nextButton = findNextPageButton( wrapper );

		expect( nextButton.attributes( 'disabled' ) ).toBeDefined();
		expect( wrapper.text() ).toContain( 'of 10' );
	} );

	it( 'keeps next enabled while the listing continues', async () => {
		// A full page with a non-null cursor means more rows follow. The component must leave
		// totalRows undefined so CdxTable stays in its indeterminate mode (next enabled, "of many"
		// label); a known total here would wrongly disable next and hide the remaining pages.
		const wrapper = mountComponent( Array.from( { length: 10 }, ( _value, index ) => (
			{ name: `Schema${ index }`, description: '', propertyCount: 1 }
		) ), 'next-page-cursor' );
		await flushPromises();

		const nextButton = findNextPageButton( wrapper );

		expect( nextButton.attributes( 'disabled' ) ).toBeUndefined();
		expect( wrapper.text() ).toContain( 'of many' );
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

	it( 'opens the editor on the schema fetched from the repository', async () => {
		canEditSchemaRef.value = true;
		// A description the store copy does not have, so the assertion can only pass if the
		// dialog received the repository's schema rather than a registry read.
		const fetched = new Schema( 'Person', 'from the repository', new PropertyDefinitionList( [] ) );
		getSchemaMock.mockResolvedValue( fetched );
		schemaStore.setSchema( 'Person', new Schema( 'Person', 'stale', new PropertyDefinitionList( [] ) ) );

		const wrapper = mountComponent( [
			{ name: 'Person', description: '', propertyCount: 3 },
		] );
		await flushPromises();

		await findEditButtons( wrapper )[ 0 ].trigger( 'click' );
		await flushPromises();

		expect( getSchemaMock ).toHaveBeenCalledWith( 'Person' );
		const dialog = wrapper.findComponent( SchemaEditorDialog );
		expect( dialog.props( 'open' ) ).toBe( true );
		expect( dialog.props( 'initialSchema' ) ).toStrictEqual( fetched );
	} );

	it( 'reports a failed schema fetch instead of opening the editor', async () => {
		canEditSchemaRef.value = true;
		getSchemaMock.mockRejectedValue( new Error( 'Unknown schema: Person' ) );

		const wrapper = mountComponent( [
			{ name: 'Person', description: '', propertyCount: 3 },
		] );
		await flushPromises();

		await findEditButtons( wrapper )[ 0 ].trigger( 'click' );
		await flushPromises();

		expect( wrapper.findComponent( SchemaEditorDialog ).exists() ).toBe( false );
		expect( mw.notify ).toHaveBeenCalledWith( 'Unknown schema: Person', { type: 'error' } );
	} );

	it( 'does not render SchemaEditorDialog when user lacks edit permission', async () => {
		canEditSchemaRef.value = true;
		getSchemaMock.mockResolvedValue( new Schema( 'Person', '', new PropertyDefinitionList( [] ) ) );

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

	it( 'opens the delete confirmation for the clicked schema', async () => {
		canEditSchemaRef.value = true;
		const wrapper = mountComponent( [
			{ name: 'Person', description: '', propertyCount: 3 },
		] );
		await flushPromises();

		await findDeleteButtons( wrapper )[ 0 ].trigger( 'click' );

		const dialog = wrapper.findComponent( DeletePageDialog );
		expect( dialog.props( 'open' ) ).toBe( true );
		expect( dialog.props( 'pageTitle' ) ).toBe( 'Schema:Person' );
		expect( dialog.props( 'displayName' ) ).toBe( 'Person' );
		expect( dialog.props( 'typeLabel' ) ).toBe( 'neowiki-schema-noun' );
	} );

	it( 'removes the deleted schema from the store and refetches the list', async () => {
		canEditSchemaRef.value = true;
		const wrapper = mountComponent( [
			{ name: 'Person', description: '', propertyCount: 3 },
		] );
		await flushPromises();

		schemaStore.setSchema( 'Person', newSchema( { title: 'Person' } ) );

		await findDeleteButtons( wrapper )[ 0 ].trigger( 'click' );

		// A different fixture than the initial mount proves the @deleted handler
		// actually refetched rather than just closing the dialog.
		schemasResponse = { schemas: [ { name: 'Company', description: '', propertyCount: 1 } ], nextCursor: null };

		wrapper.findComponent( DeletePageDialog ).vm.$emit( 'deleted' );
		await flushPromises();

		expect( () => schemaStore.getSchema( 'Person' ) ).toThrow();
		expect( wrapper.text() ).toContain( 'Company' );
		expect( wrapper.text() ).not.toContain( 'Person' );
	} );
} );
