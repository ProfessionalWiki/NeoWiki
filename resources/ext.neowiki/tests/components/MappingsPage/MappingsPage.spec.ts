import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import MappingsPage from '@/components/MappingsPage/MappingsPage.vue';
import MappingCreatorDialog from '@/components/MappingsPage/MappingCreatorDialog.vue';
import DeletePageDialog from '@/components/common/DeletePageDialog.vue';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { CdxButton } from '@wikimedia/codex';

interface MappingSummary {
	name: string;
	schemas: string[];
}

const canCreateMappingsRef = ref( false );
const canEditMappingRef = ref( false );
const canDeleteMappingRef = ref( false );
const checkCreatePermissionMock = vi.fn();
const checkEditPermissionMock = vi.fn();
const checkDeletePermissionMock = vi.fn();

let mappingsResponse: { mappings: MappingSummary[]; totalRows: number } = { mappings: [], totalRows: 0 };

vi.mock( '@/composables/useMappingPermissions.ts', () => ( {
	useMappingPermissions: () => ( {
		canCreateMappings: canCreateMappingsRef,
		canEditMapping: canEditMappingRef,
		canDeleteMapping: canDeleteMappingRef,
		checkCreatePermission: checkCreatePermissionMock,
		checkEditPermission: checkEditPermissionMock,
		checkDeletePermission: checkDeletePermissionMock,
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
					json: () => Promise.resolve( mappingsResponse ),
				} ),
			} ),
		} ),
	},
} ) );

const MappingCreatorDialogStub = {
	template: '<div class="mapping-creator-dialog-stub"></div>',
	props: [ 'open' ],
	emits: [ 'update:open', 'created' ],
};

function findCreateButton( wrapper: VueWrapper ): VueWrapper | undefined {
	return wrapper.findAllComponents( CdxButton )
		.find( ( btn ) => btn.text().includes( 'neowiki-mapping-creator-button' ) );
}

function findEditButtons( wrapper: VueWrapper ): VueWrapper[] {
	return wrapper.findAllComponents( CdxButton )
		.filter( ( btn ) => btn.attributes( 'aria-label' ) === 'neowiki-edit-mapping' );
}

function findDeleteButtons( wrapper: VueWrapper ): VueWrapper[] {
	return wrapper.findAllComponents( CdxButton )
		.filter( ( btn ) => btn.attributes( 'aria-label' ) === 'neowiki-mapping-delete' );
}

function mountComponent( summaries: MappingSummary[] = [] ): VueWrapper {
	mappingsResponse = {
		mappings: summaries,
		totalRows: summaries.length,
	};
	setupMwMock( {
		functions: [ 'msg', 'util', 'message', 'notify' ],
		messages: { 'neowiki-mappings-schema-count': ( count: string ) => `(${ count })` },
	} );

	return mount( MappingsPage, {
		global: {
			mocks: { $i18n: createI18nMock() },
			stubs: {
				MappingCreatorDialog: MappingCreatorDialogStub,
				DeletePageDialog: true,
				CdxIcon: true,
			},
		},
	} );
}

describe( 'MappingsPage', () => {
	beforeEach( () => {
		canCreateMappingsRef.value = false;
		canEditMappingRef.value = false;
		canDeleteMappingRef.value = false;
		checkCreatePermissionMock.mockClear();
		checkEditPermissionMock.mockClear();
		checkDeletePermissionMock.mockClear();
		mappingsResponse = { mappings: [], totalRows: 0 };
	} );

	it( 'links each mapped schema name to its Schema page and shows the count', async () => {
		const wrapper = mountComponent( [
			{ name: 'EDM', schemas: [ 'Artist', 'Artwork', 'City', 'Person' ] },
		] );
		await flushPromises();

		const firstSchema = wrapper.find( 'a[href="/wiki/Schema:Artist"]' );
		expect( firstSchema.exists() ).toBe( true );
		expect( firstSchema.text() ).toBe( 'Artist' );
		expect( wrapper.find( 'a[href="/wiki/Schema:Person"]' ).exists() ).toBe( true );
		expect( wrapper.text() ).toContain( '(4)' );
		expect( wrapper.text() ).toContain( 'Artist, Artwork' );
	} );

	it( 'renders "None" instead of a count for a mapping with no mapped schemas', async () => {
		const wrapper = mountComponent( [
			{ name: 'Empty', schemas: [] },
		] );
		await flushPromises();

		const none = wrapper.find( '.ext-neowiki-mappings-page__empty-value' );
		expect( none.exists() ).toBe( true );
		expect( none.text() ).toBe( 'neowiki-mappings-schemas-none' );
		expect( wrapper.text() ).not.toContain( '(0)' );
	} );

	it( 'links the mapping name to its Mapping page', async () => {
		const wrapper = mountComponent( [
			{ name: 'EDM', schemas: [ 'Person' ] },
		] );
		await flushPromises();

		const link = wrapper.find( 'a[href="/wiki/Mapping:EDM"]' );
		expect( link.exists() ).toBe( true );
		expect( link.text() ).toBe( 'EDM' );
	} );

	it( 'renders one row per mapping when several are returned', async () => {
		const wrapper = mountComponent( [
			{ name: 'EDM', schemas: [ 'Person' ] },
			{ name: 'Dublin Core', schemas: [ 'Manuscript' ] },
		] );
		await flushPromises();

		expect( wrapper.find( 'a[href="/wiki/Mapping:EDM"]' ).exists() ).toBe( true );
		expect( wrapper.find( 'a[href="/wiki/Mapping:Dublin Core"]' ).exists() ).toBe( true );
	} );

	it( 'shows the empty state when there are no mappings', async () => {
		const wrapper = mountComponent( [] );
		await flushPromises();

		expect( wrapper.text() ).toContain( 'neowiki-mappings-empty' );
	} );

	it( 'shows the create button when the user may create mappings', async () => {
		canCreateMappingsRef.value = true;
		const wrapper = mountComponent();
		await flushPromises();

		expect( findCreateButton( wrapper ) ).toBeDefined();
	} );

	it( 'hides the create button without create permission', async () => {
		canCreateMappingsRef.value = false;
		const wrapper = mountComponent();
		await flushPromises();

		expect( findCreateButton( wrapper ) ).toBeUndefined();
	} );

	it( 'opens the creator dialog when the create button is clicked', async () => {
		canCreateMappingsRef.value = true;
		const wrapper = mountComponent();
		await flushPromises();

		expect( wrapper.findComponent( MappingCreatorDialog ).props( 'open' ) ).toBe( false );

		await findCreateButton( wrapper )!.trigger( 'click' );

		expect( wrapper.findComponent( MappingCreatorDialog ).props( 'open' ) ).toBe( true );
	} );

	it( 'does not render the creator dialog without create permission', async () => {
		canCreateMappingsRef.value = false;
		const wrapper = mountComponent();
		await flushPromises();

		expect( wrapper.findComponent( MappingCreatorDialog ).exists() ).toBe( false );
	} );

	it( 'shows edit and delete buttons when the user may edit and delete mappings', async () => {
		canEditMappingRef.value = true;
		canDeleteMappingRef.value = true;
		const wrapper = mountComponent( [
			{ name: 'EDM', schemas: [ 'Person' ] },
			{ name: 'Dublin Core', schemas: [ 'Manuscript' ] },
		] );
		await flushPromises();

		expect( findEditButtons( wrapper ) ).toHaveLength( 2 );
		expect( findDeleteButtons( wrapper ) ).toHaveLength( 2 );
	} );

	it( 'hides the delete button when the user cannot delete, even with edit permission', async () => {
		canEditMappingRef.value = true;
		canDeleteMappingRef.value = false;
		const wrapper = mountComponent( [
			{ name: 'EDM', schemas: [ 'Person' ] },
		] );
		await flushPromises();

		expect( findEditButtons( wrapper ) ).toHaveLength( 1 );
		expect( findDeleteButtons( wrapper ) ).toHaveLength( 0 );
	} );

	it( 'shows the delete button on delete permission independently of edit permission', async () => {
		canEditMappingRef.value = false;
		canDeleteMappingRef.value = true;
		const wrapper = mountComponent( [
			{ name: 'EDM', schemas: [ 'Person' ] },
		] );
		await flushPromises();

		expect( findEditButtons( wrapper ) ).toHaveLength( 0 );
		expect( findDeleteButtons( wrapper ) ).toHaveLength( 1 );
	} );

	it( 'hides edit and delete buttons without edit permission', async () => {
		canEditMappingRef.value = false;
		const wrapper = mountComponent( [
			{ name: 'EDM', schemas: [ 'Person' ] },
		] );
		await flushPromises();

		expect( findEditButtons( wrapper ) ).toHaveLength( 0 );
		expect( findDeleteButtons( wrapper ) ).toHaveLength( 0 );
	} );

	it( 'opens the delete confirmation for the clicked mapping', async () => {
		canDeleteMappingRef.value = true;
		const wrapper = mountComponent( [
			{ name: 'EDM', schemas: [ 'Person' ] },
		] );
		await flushPromises();

		await findDeleteButtons( wrapper )[ 0 ].trigger( 'click' );

		const dialog = wrapper.findComponent( DeletePageDialog );
		expect( dialog.props( 'open' ) ).toBe( true );
		expect( dialog.props( 'pageTitle' ) ).toBe( 'Mapping:EDM' );
		expect( dialog.props( 'displayName' ) ).toBe( 'EDM' );
	} );

	it( 'navigates to the raw-JSON edit view when the edit button is clicked', async () => {
		const hrefSetter = vi.fn();
		Object.defineProperty( window, 'location', {
			configurable: true,
			value: { set href( value: string ) {
				hrefSetter( value );
			} },
		} );

		canEditMappingRef.value = true;
		const wrapper = mountComponent( [
			{ name: 'EDM', schemas: [ 'Person' ] },
		] );
		await flushPromises();

		await findEditButtons( wrapper )[ 0 ].trigger( 'click' );

		expect( hrefSetter ).toHaveBeenCalledWith( '/wiki/Mapping:EDM' );
	} );
} );
