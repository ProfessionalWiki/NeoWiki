import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent } from 'vue';
import { useSchemaPermissions, type SchemaPermissions } from '@/composables/useSchemaPermissions.ts';
import { Service } from '@/NeoWikiServices.ts';
import type { SchemaPermissionHints } from '@/application/SchemaPermissionHints.ts';

function permissionsFor( mayDelete: boolean ): SchemaPermissions {
	const hints: SchemaPermissionHints = {
		canCreateSchemas: async () => true,
		canEditSchema: async () => true,
		canDeleteSchema: async () => mayDelete,
	};
	let permissions: SchemaPermissions | undefined;

	mount( defineComponent( {
		setup() {
			permissions = useSchemaPermissions();
			return () => null;
		},
	} ), { global: { provide: { [ Service.SchemaPermissionHints ]: hints } } } );

	return permissions!;
}

describe( 'useSchemaPermissions', () => {

	it( 'lets a user delete Schemas when the hints say they may', async () => {
		const permissions = permissionsFor( true );

		await permissions.checkDeletePermission( 'Artist' );

		expect( permissions.canDeleteSchema.value ).toBe( true );
	} );

	it( 'keeps deleting from a user who may edit but not delete Schemas', async () => {
		const permissions = permissionsFor( false );

		await permissions.checkDeletePermission( 'Artist' );

		expect( permissions.canDeleteSchema.value ).toBe( false );
	} );

} );
