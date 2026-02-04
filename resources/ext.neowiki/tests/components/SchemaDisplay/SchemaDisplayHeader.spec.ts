import { mount, VueWrapper } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import SchemaDisplayHeader from '@/components/SchemaDisplay/SchemaDisplayHeader.vue';
import { Schema } from '@/domain/Schema.ts';
import { setupMwMock, createI18nMock } from '../../VueTestHelpers.ts';
import { newSchema } from '@/TestHelpers.ts';
import { ref } from 'vue';

const checkPermissionMock = vi.fn();
const canEditSchemaRef = ref( false );

vi.mock( '@/composables/useSchemaPermissions.ts', () => ( {
	useSchemaPermissions: () => ( {
		canEditSchema: canEditSchemaRef,
		checkPermission: checkPermissionMock,
	} ),
} ) );

let mockGetUrl: ReturnType<typeof vi.fn>;

function mountComponent( schema: Schema ): VueWrapper {
	setupMwMock( { functions: [ 'msg' ] } );
	mockGetUrl = vi.fn( ( title: string ) => `/wiki/${ title }` );
	( globalThis as any ).mw.util = { getUrl: mockGetUrl };

	return mount( SchemaDisplayHeader, {
		props: { schema },
		global: {
			mocks: { $i18n: createI18nMock() },
			stubs: {
				CdxIcon: true,
			},
		},
	} );
}

describe( 'SchemaDisplayHeader', () => {
	beforeEach( () => {
		canEditSchemaRef.value = false;
		checkPermissionMock.mockClear();
	} );

	it( 'renders schema name and description', () => {
		const wrapper = mountComponent( newSchema( {
			title: 'Test schema',
			description: 'A schema for people',
		} ) );

		expect( wrapper.find( '.ext-neowiki-schema-display-header__title' ).text() ).toBe( 'Test schema' );
		expect( wrapper.find( '.ext-neowiki-schema-display-header__description' ).text() ).toBe( 'A schema for people' );
	} );

	it( 'hides description when schema has none', () => {
		const wrapper = mountComponent( newSchema( { description: '' } ) );

		expect( wrapper.find( '.ext-neowiki-schema-display-header__description' ).exists() ).toBe( false );
	} );

	it( 'checks permissions on mount', () => {
		mountComponent( newSchema( { title: 'Test Schema' } ) );

		expect( checkPermissionMock ).toHaveBeenCalledWith( 'Test Schema' );
	} );

	it( 'shows edit button when user has permission', () => {
		canEditSchemaRef.value = true;

		const wrapper = mountComponent( newSchema() );

		expect( wrapper.find( '.ext-neowiki-schema-display-header__actions button' ).exists() ).toBe( true );
	} );

	it( 'hides edit button when user lacks permission', () => {
		const wrapper = mountComponent( newSchema() );

		expect( wrapper.find( '.ext-neowiki-schema-display-header__actions button' ).exists() ).toBe( false );
	} );

	it( 'navigates to schema editor on edit button click', async () => {
		canEditSchemaRef.value = true;
		const wrapper = mountComponent( newSchema( { title: 'Company' } ) );

		await wrapper.find( '.ext-neowiki-schema-display-header__actions button' ).trigger( 'click' );

		expect( mockGetUrl ).toHaveBeenCalledWith( 'Schema:Company', { action: 'edit-schema' } );
	} );
} );
