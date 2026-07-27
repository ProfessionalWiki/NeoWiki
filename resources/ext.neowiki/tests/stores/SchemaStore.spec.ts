import { afterEach, beforeEach, describe, it, expect, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { normalizeSchemaName, useSchemaStore } from '@/stores/SchemaStore.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { newSchema } from '@/TestHelpers.ts';
import type { SchemaSummary, SchemaSummaryPage } from '@/application/SchemaLookup.ts';

interface Deferred<T> {
	promise: Promise<T>;
	resolve: ( value: T ) => void;
	reject: ( error: Error ) => void;
}

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

	function summary( name: string ): SchemaSummary {
		return { name, description: '', propertyCount: 0 };
	}

	function manySummaries( count: number, prefix: string ): SchemaSummary[] {
		return Array.from( { length: count }, ( _value, index ) => summary( `${ prefix }${ index }` ) );
	}

	function lastPage( summaries: SchemaSummary[] ): SchemaSummaryPage {
		return { schemas: summaries, nextCursor: null };
	}

	function deferred<T>(): Deferred<T> {
		let resolve!: ( value: T ) => void;
		let reject!: ( error: Error ) => void;

		const promise = new Promise<T>( ( resolveValue, rejectValue ) => {
			resolve = resolveValue;
			reject = rejectValue;
		} );

		return { promise, resolve, reject };
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
		await store.saveSchema( newSchema( { title: 'B' } ) );
		await store.getAllSchemaSummaries();

		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'does not cache the result of a request that a save invalidated while it ran', async () => {
		const beforeSave = [ summary( 'Alpha' ), summary( 'Beta' ) ];
		const afterSave = [ summary( 'Alpha' ), summary( 'Beta' ), summary( 'Gamma' ) ];
		const invalidatedPage = deferred<SchemaSummaryPage>();
		const getSchemaSummaries = vi.fn()
			.mockReturnValueOnce( invalidatedPage.promise )
			.mockResolvedValue( lastPage( afterSave ) );
		withRepository( { getSchemaSummaries, saveSchema: vi.fn().mockResolvedValue( undefined ) } );
		const store = useSchemaStore();

		const invalidatedRequest = store.getAllSchemaSummaries();
		await store.saveSchema( newSchema( { title: 'Gamma' } ) );
		invalidatedPage.resolve( lastPage( beforeSave ) );
		await invalidatedRequest;
		const reloaded = await store.getAllSchemaSummaries();

		expect( reloaded ).toEqual( afterSave );
		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'does not cache the result of a request that started while a save was still running', async () => {
		const beforeSave = [ summary( 'Alpha' ), summary( 'Beta' ) ];
		const afterSave = [ summary( 'Alpha' ), summary( 'Beta' ), summary( 'Gamma' ) ];
		const invalidatedPage = deferred<SchemaSummaryPage>();
		const save = deferred<void>();
		const getSchemaSummaries = vi.fn()
			.mockReturnValueOnce( invalidatedPage.promise )
			.mockResolvedValue( lastPage( afterSave ) );
		withRepository( { getSchemaSummaries, saveSchema: () => save.promise } );
		const store = useSchemaStore();

		const savingSchema = store.saveSchema( newSchema( { title: 'Gamma' } ) );
		const invalidatedRequest = store.getAllSchemaSummaries();
		save.resolve();
		await savingSchema;
		invalidatedPage.resolve( lastPage( beforeSave ) );
		await invalidatedRequest;
		const reloaded = await store.getAllSchemaSummaries();

		expect( reloaded ).toEqual( afterSave );
		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'lets later callers share the replacement request when an invalidated one fails', async () => {
		const afterSave = [ summary( 'Alpha' ), summary( 'Beta' ) ];
		const invalidatedPage = deferred<SchemaSummaryPage>();
		const currentPage = deferred<SchemaSummaryPage>();
		const getSchemaSummaries = vi.fn()
			.mockReturnValueOnce( invalidatedPage.promise )
			.mockReturnValueOnce( currentPage.promise )
			.mockResolvedValue( lastPage( [ summary( 'Redundant fetch' ) ] ) );
		withRepository( { getSchemaSummaries, saveSchema: vi.fn().mockResolvedValue( undefined ) } );
		const store = useSchemaStore();

		const invalidatedRequest = store.getAllSchemaSummaries();
		await store.saveSchema( newSchema( { title: 'Beta' } ) );
		const currentRequest = store.getAllSchemaSummaries();
		invalidatedPage.reject( new Error( 'load failed' ) );
		await expect( invalidatedRequest ).rejects.toThrow( 'load failed' );
		const laterRequest = store.getAllSchemaSummaries();
		currentPage.resolve( lastPage( afterSave ) );
		await currentRequest;

		expect( await laterRequest ).toEqual( afterSave );
		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 2 );
	} );

} );
