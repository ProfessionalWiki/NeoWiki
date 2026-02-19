import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import SchemasPage from '@/components/SchemasPage/SchemasPage.vue';
import SchemaCreatorDialog from '@/components/SchemasPage/SchemaCreatorDialog.vue';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { CdxButton } from '@wikimedia/codex';

const canCreateSchemasRef = ref( false );
const checkCreatePermissionMock = vi.fn();

let schemasResponse: { schemas: unknown[]; totalRows: number } = { schemas: [], totalRows: 0 };

vi.mock( '@/composables/useSchemaPermissions.ts', () => ( {
	useSchemaPermissions: () => ( {
		canCreateSchemas: canCreateSchemasRef,
		checkCreatePermission: checkCreatePermissionMock,
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

function findCreateButton( wrapper: VueWrapper ): VueWrapper | undefined {
	return wrapper.findAllComponents( CdxButton )
		.find( ( btn ) => btn.text().includes( 'neowiki-schema-creator-button' ) );
}

function mountComponent( summaries: unknown[] = [] ): VueWrapper {
	schemasResponse = {
		schemas: summaries,
		totalRows: summaries.length,
	};
	setupMwMock( { functions: [ 'msg', 'util' ] } );

	return mount( SchemasPage, {
		global: {
			mocks: { $i18n: createI18nMock() },
			stubs: {
				SchemaCreatorDialog: SchemaCreatorDialogStub,
				CdxIcon: true,
			},
		},
	} );
}

describe( 'SchemasPage', () => {
	beforeEach( () => {
		canCreateSchemasRef.value = false;
		checkCreatePermissionMock.mockClear();
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
} );
