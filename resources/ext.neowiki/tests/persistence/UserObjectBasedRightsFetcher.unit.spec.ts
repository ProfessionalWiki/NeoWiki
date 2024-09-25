import { describe, expect, it } from 'vitest';
import { UserObjectBasedRightsFetcher } from '@/persistence/UserObjectBasedRightsFetcher';

export class TestUserObjectBasedRightsFetcher extends UserObjectBasedRightsFetcher {
	public constructor( rights: string[] ) {
		super();
		this.rights = new Promise<string[]>( ( resolve ) => {
			resolve( rights );
		} );
	}
}

describe( 'User Rights', () => {

	it( 'can return current user rights', async () => {
		const testRights = [ 'foo', 'edit', 'bar' ];
		const userRightsFetcher = new TestUserObjectBasedRightsFetcher( testRights );
		const resultRights = await userRightsFetcher.getRights();

		expect( resultRights ).toEqual( testRights );
		expect( await userRightsFetcher.getRights() ).toEqual( testRights );
	} );

	it( 'can update current user rights', async () => {
		const testRights = [ 'foo', 'edit', 'bar', 'writeapi', 'baz' ];
		const userRightsFetcher = new TestUserObjectBasedRightsFetcher( testRights );

		await userRightsFetcher.refreshRights();

		const resultRights = await userRightsFetcher.getRights();

		expect( resultRights ).not.toEqual( testRights );
	} );
} );
