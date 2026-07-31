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

describe( 'SchemaStore fetchAllSchemaSummaries', () => {

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

		const result = await useSchemaStore().fetchAllSchemaSummaries();

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

		const result = await useSchemaStore().fetchAllSchemaSummaries();

		expect( result ).toHaveLength( 59 );
		expect( getSchemaSummaries ).toHaveBeenNthCalledWith( 2, 'cursor-1', 50 );
		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'shares one in-flight request across concurrent callers', async () => {
		const getSchemaSummaries = vi.fn().mockResolvedValue( { schemas: [ summary( 'A' ) ], nextCursor: null } );
		withRepository( { getSchemaSummaries } );
		const store = useSchemaStore();

		await Promise.all( [ store.fetchAllSchemaSummaries(), store.fetchAllSchemaSummaries() ] );

		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'releases the in-flight request after a failure so the next call retries', async () => {
		const getSchemaSummaries = vi.fn()
			.mockRejectedValueOnce( new Error( 'load failed' ) )
			.mockResolvedValueOnce( { schemas: [ summary( 'A' ) ], nextCursor: null } );
		withRepository( { getSchemaSummaries } );
		const store = useSchemaStore();

		await expect( store.fetchAllSchemaSummaries() ).rejects.toThrow( 'load failed' );
		const result = await store.fetchAllSchemaSummaries();

		expect( result ).toEqual( [ summary( 'A' ) ] );
		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'fetches fresh summaries for each new caller cohort', async () => {
		const getSchemaSummaries = vi.fn().mockResolvedValue( { schemas: [ summary( 'A' ) ], nextCursor: null } );
		withRepository( { getSchemaSummaries } );
		const store = useSchemaStore();

		await store.fetchAllSchemaSummaries();
		await store.fetchAllSchemaSummaries();

		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'detaches the in-flight request on save so the next caller fetches fresh', async () => {
		const preSavePage = deferred<SchemaSummaryPage>();
		const getSchemaSummaries = vi.fn()
			.mockReturnValueOnce( preSavePage.promise )
			.mockResolvedValue( lastPage( [ summary( 'Alpha' ), summary( 'Gamma' ) ] ) );
		withRepository( { getSchemaSummaries, saveSchema: vi.fn().mockResolvedValue( undefined ) } );
		const store = useSchemaStore();

		const preSaveRequest = store.fetchAllSchemaSummaries();
		await store.saveSchema( newSchema( { title: 'Gamma' } ) );
		const postSaveRequest = store.fetchAllSchemaSummaries();
		preSavePage.resolve( lastPage( [ summary( 'Alpha' ) ] ) );

		expect( await preSaveRequest ).toEqual( [ summary( 'Alpha' ) ] );
		expect( await postSaveRequest ).toEqual( [ summary( 'Alpha' ), summary( 'Gamma' ) ] );
		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'detaches the in-flight request even when the save rejects', async () => {
		const preSavePage = deferred<SchemaSummaryPage>();
		const getSchemaSummaries = vi.fn()
			.mockReturnValueOnce( preSavePage.promise )
			.mockResolvedValue( lastPage( [ summary( 'Alpha' ) ] ) );
		withRepository( { getSchemaSummaries, saveSchema: vi.fn().mockRejectedValue( new Error( 'save failed' ) ) } );
		const store = useSchemaStore();

		const preSaveRequest = store.fetchAllSchemaSummaries();
		await expect( store.saveSchema( newSchema( { title: 'Beta' } ) ) ).rejects.toThrow( 'save failed' );
		const retryRequest = store.fetchAllSchemaSummaries();
		preSavePage.resolve( lastPage( [] ) );

		await preSaveRequest;
		expect( await retryRequest ).toEqual( [ summary( 'Alpha' ) ] );
		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 2 );
	} );

} );

describe( 'SchemaStore saveSchema', () => {

	beforeEach( () => {
		setActivePinia( createPinia() );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	it( 'writes the saved schema into the registry', async () => {
		const saved = newSchema( { title: 'Person' } );
		withRepository( { saveSchema: vi.fn().mockResolvedValue( undefined ) } );
		const store = useSchemaStore();

		await store.saveSchema( saved );

		expect( store.getSchema( 'Person' ) ).toStrictEqual( saved );
	} );

	it( 'does not write through when the repository rejects', async () => {
		withRepository( { saveSchema: vi.fn().mockRejectedValue( new Error( 'save failed' ) ) } );
		const store = useSchemaStore();

		await expect( store.saveSchema( newSchema( { title: 'Person' } ) ) ).rejects.toThrow( 'save failed' );

		expect( () => store.getSchema( 'Person' ) ).toThrow( 'Unknown schema: Person' );
	} );

	it( 'clears the in-flight summaries request even when the save rejects', async () => {
		const summariesDeferred = deferred<SchemaSummaryPage>();
		withRepository( {
			getSchemaSummaries: vi.fn().mockReturnValueOnce( summariesDeferred.promise ),
			saveSchema: vi.fn().mockRejectedValue( new Error( 'save failed' ) ),
		} );
		const store = useSchemaStore();

		const summariesRequest = store.fetchAllSchemaSummaries();
		await expect( store.saveSchema( newSchema( { title: 'Person' } ) ) ).rejects.toThrow( 'save failed' );

		expect( store.summariesRequest ).toBeNull();

		summariesDeferred.resolve( lastPage( [] ) );
		await summariesRequest;
	} );

} );

describe( 'SchemaStore removeSchema', () => {

	beforeEach( () => {
		setActivePinia( createPinia() );
	} );

	it( 'removes the schema from the registry', () => {
		const store = useSchemaStore();
		store.setSchema( 'Person', newSchema( { title: 'Person' } ) );

		store.removeSchema( 'Person' );

		expect( () => store.getSchema( 'Person' ) ).toThrow( 'Unknown schema: Person' );
	} );

} );
