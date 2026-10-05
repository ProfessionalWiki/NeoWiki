import { describe, expect, it } from 'vitest';
import { RightsBasedSchemaPermissionHints } from '@/persistence/RightsBasedSchemaPermissionHints';
import { TestUserObjectBasedRightsFetcher } from './UserObjectBasedRightsFetcher.unit.spec';

function newHints( rights: string[] ): RightsBasedSchemaPermissionHints {
	return new RightsBasedSchemaPermissionHints( new TestUserObjectBasedRightsFetcher( rights ) );
}

describe( 'RightsBasedSchemaPermissionHints', () => {

	it( 'can delete a Schema with the delete right and the right to edit Schemas', async () => {
		expect( await newHints( [ 'edit', 'delete', 'neowiki-schema-edit' ] ).canDeleteSchema( 'Artist' ) ).toBe( true );
	} );

	it( 'cannot delete a Schema with the delete right alone', async () => {
		expect( await newHints( [ 'edit', 'delete', 'read' ] ).canDeleteSchema( 'Artist' ) ).toBe( false );
	} );

	it( 'cannot delete a Schema with only the right to edit Schemas', async () => {
		expect( await newHints( [ 'edit', 'neowiki-schema-edit', 'read' ] ).canDeleteSchema( 'Artist' ) ).toBe( false );
	} );

} );
