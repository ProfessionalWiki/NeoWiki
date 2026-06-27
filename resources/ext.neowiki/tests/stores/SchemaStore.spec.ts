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

	it( 'pages through every schema summary across multiple pages', async () => {
		const getSchemaSummaries = vi.fn()
			.mockResolvedValueOnce( { schemas: manySummaries( 50, 'A' ), totalRows: 60 } )
			.mockResolvedValueOnce( { schemas: manySummaries( 10, 'B' ), totalRows: 60 } );
		withRepository( { getSchemaSummaries } );

		const result = await useSchemaStore().getAllSchemaSummaries();

		expect( result ).toHaveLength( 60 );
		expect( getSchemaSummaries ).toHaveBeenNthCalledWith( 1, 0, 50 );
		expect( getSchemaSummaries ).toHaveBeenNthCalledWith( 2, 50, 50 );
	} );

	it( 'advances by page size, not loaded count, when a page omits unloadable schemas', async () => {
		// The endpoint counts 60 schema pages in totalRows but can only load 49 in the
		// first window (one is restricted or malformed) and 10 in the second.
		const getSchemaSummaries = vi.fn()
			.mockResolvedValueOnce( { schemas: manySummaries( 49, 'A' ), totalRows: 60 } )
			.mockResolvedValueOnce( { schemas: manySummaries( 10, 'B' ), totalRows: 60 } );
		withRepository( { getSchemaSummaries } );

		const result = await useSchemaStore().getAllSchemaSummaries();

		expect( result ).toHaveLength( 59 );
		expect( getSchemaSummaries ).toHaveBeenNthCalledWith( 2, 50, 50 );
		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'caches the summaries and does not refetch on the next call', async () => {
		const getSchemaSummaries = vi.fn().mockResolvedValue( { schemas: [ summary( 'A' ) ], totalRows: 1 } );
		withRepository( { getSchemaSummaries } );
		const store = useSchemaStore();

		await store.getAllSchemaSummaries();
		await store.getAllSchemaSummaries();

		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'shares one in-flight request across concurrent callers', async () => {
		const getSchemaSummaries = vi.fn().mockResolvedValue( { schemas: [ summary( 'A' ) ], totalRows: 1 } );
		withRepository( { getSchemaSummaries } );
		const store = useSchemaStore();

		await Promise.all( [ store.getAllSchemaSummaries(), store.getAllSchemaSummaries() ] );

		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'releases the in-flight request after a failure so the next call retries', async () => {
		const getSchemaSummaries = vi.fn()
			.mockRejectedValueOnce( new Error( 'load failed' ) )
			.mockResolvedValueOnce( { schemas: [ summary( 'A' ) ], totalRows: 1 } );
		withRepository( { getSchemaSummaries } );
		const store = useSchemaStore();

		await expect( store.getAllSchemaSummaries() ).rejects.toThrow( 'load failed' );
		const result = await store.getAllSchemaSummaries();

		expect( result ).toEqual( [ summary( 'A' ) ] );
		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'refetches summaries after a schema is saved', async () => {
		const getSchemaSummaries = vi.fn().mockResolvedValue( { schemas: [ summary( 'A' ) ], totalRows: 1 } );
		const saveSchema = vi.fn().mockResolvedValue( undefined );
		withRepository( { getSchemaSummaries, saveSchema } );
		const store = useSchemaStore();

		await store.getAllSchemaSummaries();
		await store.saveSchema( new Schema( 'B', '', new PropertyDefinitionList( [] ) ) );
		await store.getAllSchemaSummaries();

		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 2 );
	} );

} );
