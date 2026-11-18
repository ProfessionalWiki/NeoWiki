import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CurrentStateReader } from '@/application/modelBundle/CurrentStateReader';
import { newSchema } from '@/TestHelpers';
import { InMemoryDataModel } from './InMemoryDataModel';

describe( 'CurrentStateReader', () => {

	let wiki: InMemoryDataModel;

	beforeEach( () => {
		wiki = new InMemoryDataModel().withSchemas( newSchema( { title: 'Person' } ) );
	} );

	it( 'lists the wiki\'s pages again after a listing that failed', async () => {
		vi.spyOn( wiki, 'getSchemaSummaries' ).mockRejectedValueOnce( new Error( 'The network is down' ) );
		const reader = new CurrentStateReader( wiki );
		await reader.read( 'schema', 'Museum' );

		expect( ( await reader.read( 'schema', 'Person' ) ).error ).toBeNull();
	} );

	it( 'reads an item again after a read that failed', async () => {
		wiki.unreadable.add( 'schema:Person' );
		const reader = new CurrentStateReader( wiki );
		await reader.read( 'schema', 'Person' );
		wiki.unreadable.clear();

		expect( ( await reader.read( 'schema', 'Person' ) ).error ).toBeNull();
	} );

} );
