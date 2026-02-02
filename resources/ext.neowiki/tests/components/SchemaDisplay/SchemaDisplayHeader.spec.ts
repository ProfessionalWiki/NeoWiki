import { mount, VueWrapper } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
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

function mountComponent( schema: Schema ): VueWrapper {
	setupMwMock( { functions: [ 'msg' ] } );

	return mount( SchemaDisplayHeader, {
		props: { schema },
		global: {
			mocks: { $i18n: createI18nMock() },
			stubs: {
				CdxIcon: true,
				CdxButton: true,
			},
		},
	} );
}

describe( 'SchemaDisplayHeader', () => {
	it( 'renders schema name and description', () => {
		const schema = newSchema( {
			title: 'Test schema',
			description: 'Description for test schema',
		} );

		const wrapper = mountComponent( schema );

		expect( wrapper.find( '.ext-neowiki-schema-display-header__title' ).text() ).toBe( 'Test schema' );
		expect( wrapper.find( '.ext-neowiki-schema-display-header__description' ).text() ).toBe( 'Description for test schema' );
	} );

	it( 'renders schema name only when no description provided', () => {
		const schema = newSchema( {
			title: 'Test schema',
			description: '',
		} );

		const wrapper = mountComponent( schema );

		expect( wrapper.find( '.ext-neowiki-schema-display-header__title' ).text() ).toBe( 'Test schema' );
		expect( wrapper.find( '.ext-neowiki-schema-display-header__description' ).exists() ).toBe( false );
	} );

	it( 'checks permissions on mount', () => {
		const schema = newSchema( { title: 'Test Schema' } );
		checkPermissionMock.mockClear();

		mountComponent( schema );

		expect( checkPermissionMock ).toHaveBeenCalledWith( 'Test Schema' );
	} );

	it( 'shows edit button when user has permission', async () => {
		const schema = newSchema( { title: 'Test Schema' } );
		canEditSchemaRef.value = true;

		const wrapper = mountComponent( schema );

		expect( wrapper.findComponent( { name: 'CdxButton', props: { 'aria-label': 'neowiki-edit-schema' } } ).exists() ).toBeTruthy();
	} );

	it( 'hides edit button when user lacks permission', async () => {
		const schema = newSchema( { title: 'Test Schema' } );
		canEditSchemaRef.value = false;

		const wrapper = mountComponent( schema );

		expect( wrapper.findComponent( { name: 'CdxButton', props: { 'aria-label': 'neowiki-edit-schema' } } ).exists() ).toBe( false );
	} );
} );
