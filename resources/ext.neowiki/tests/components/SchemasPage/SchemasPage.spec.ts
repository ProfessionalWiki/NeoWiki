import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import SchemasPage from '@/components/SchemasPage/SchemasPage.vue';
import SchemaCreatorDialog from '@/components/SchemasPage/SchemaCreatorDialog.vue';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { CdxButton } from '@wikimedia/codex';

const canCreateSchemasRef = ref( false );
const checkCreatePermissionMock = vi.fn();

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
					json: () => Promise.resolve( [] ),
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

function mountComponent(): VueWrapper {
	setupMwMock( { functions: [ 'msg' ] } );

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
} );
