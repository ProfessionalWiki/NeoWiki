import { afterEach, beforeEach, describe, it, expect, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { normalizeSchemaName, useSchemaStore } from '@/stores/SchemaStore.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';

describe( 'normalizeSchemaName', () => {
	it( 'upper-cases the first character', () => {
		expect( normalizeSchemaName( 'person' ) ).toBe( 'Person' );
	} );

	it( 'only capitalises the first character, not later words', () => {
		expect( normalizeSchemaName( 'person of interest' ) ).toBe( 'Person of interest' );
	} );

	it( 'turns underscores into spaces and collapses runs of whitespace', () => {
		expect( normalizeSchemaName( 'Foo_Bar' ) ).toBe( 'Foo Bar' );
		expect( normalizeSchemaName( 'Foo   Bar' ) ).toBe( 'Foo Bar' );
	} );

	it( 'trims surrounding whitespace', () => {
		expect( normalizeSchemaName( '  Person  ' ) ).toBe( 'Person' );
	} );

	it( 'leaves an already-canonical name unchanged', () => {
		expect( normalizeSchemaName( 'Validation Demo' ) ).toBe( 'Validation Demo' );
	} );
} );

describe( 'SchemaStore getAllSchemaSummaries', () => {

	function summary( name: string ): { name: string; description: string; propertyCount: number } {
		return { name, description: '', propertyCount: 0 };
	}

	function manySummaries( count: number, prefix: string ): ReturnType<typeof summary>[] {
		return Array.from( { length: count }, ( _value, index ) => summary( `${ prefix }${ index }` ) );
	}

	function withRepository( repository: Record<string, unknown> ): void {
		vi.spyOn( NeoWikiExtension, 'getInstance' ).mockReturnValue(
			{ getSchemaRepository: () => repository } as unknown as NeoWikiExtension,
		);
	}

	beforeEach( () => {
		setActivePinia( createPinia() );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	it( 'pages through every schema summary by following the cursor', async () => {
		const getSchemaSummaries = vi.fn()
			.mockResolvedValueOnce( { schemas: manySummaries( 50, 'A' ), nextCursor: 'cursor-1' } )
			.mockResolvedValueOnce( { schemas: manySummaries( 10, 'B' ), nextCursor: null } );
		withRepository( { getSchemaSummaries } );

		const result = await useSchemaStore().getAllSchemaSummaries();

		expect( result ).toHaveLength( 60 );
		expect( getSchemaSummaries ).toHaveBeenNthCalledWith( 1, null, 50 );
		expect( getSchemaSummaries ).toHaveBeenNthCalledWith( 2, 'cursor-1', 50 );
	} );

	it( 'keeps following the cursor when a page omits unloadable schemas', async () => {
		// A page can come back shorter than requested when a readable schema fails to load
		// (malformed); the cursor, not the page length, decides whether more pages follow.
		const getSchemaSummaries = vi.fn()
			.mockResolvedValueOnce( { schemas: manySummaries( 49, 'A' ), nextCursor: 'cursor-1' } )
			.mockResolvedValueOnce( { schemas: manySummaries( 10, 'B' ), nextCursor: null } );
		withRepository( { getSchemaSummaries } );

		const result = await useSchemaStore().getAllSchemaSummaries();

		expect( result ).toHaveLength( 59 );
		expect( getSchemaSummaries ).toHaveBeenNthCalledWith( 2, 'cursor-1', 50 );
		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'caches the summaries and does not refetch on the next call', async () => {
		const getSchemaSummaries = vi.fn().mockResolvedValue( { schemas: [ summary( 'A' ) ], nextCursor: null } );
		withRepository( { getSchemaSummaries } );
		const store = useSchemaStore();

		await store.getAllSchemaSummaries();
		await store.getAllSchemaSummaries();

		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'shares one in-flight request across concurrent callers', async () => {
		const getSchemaSummaries = vi.fn().mockResolvedValue( { schemas: [ summary( 'A' ) ], nextCursor: null } );
		withRepository( { getSchemaSummaries } );
		const store = useSchemaStore();

		await Promise.all( [ store.getAllSchemaSummaries(), store.getAllSchemaSummaries() ] );

		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'releases the in-flight request after a failure so the next call retries', async () => {
		const getSchemaSummaries = vi.fn()
			.mockRejectedValueOnce( new Error( 'load failed' ) )
			.mockResolvedValueOnce( { schemas: [ summary( 'A' ) ], nextCursor: null } );
		withRepository( { getSchemaSummaries } );
		const store = useSchemaStore();

		await expect( store.getAllSchemaSummaries() ).rejects.toThrow( 'load failed' );
		const result = await store.getAllSchemaSummaries();

		expect( result ).toEqual( [ summary( 'A' ) ] );
		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'refetches summaries after a schema is saved', async () => {
		const getSchemaSummaries = vi.fn().mockResolvedValue( { schemas: [ summary( 'A' ) ], nextCursor: null } );
		const saveSchema = vi.fn().mockResolvedValue( undefined );
		withRepository( { getSchemaSummaries, saveSchema } );
		const store = useSchemaStore();

		await store.getAllSchemaSummaries();
		await store.saveSchema( new Schema( 'B', '', new PropertyDefinitionList( [] ) ) );
		await store.getAllSchemaSummaries();

		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 2 );
	} );

} );
