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

	it( 'pages through every schema summary', async () => {
		const getSchemaSummaries = vi.fn()
			.mockResolvedValueOnce( { schemas: [ summary( 'A' ), summary( 'B' ) ], totalRows: 3 } )
			.mockResolvedValueOnce( { schemas: [ summary( 'C' ) ], totalRows: 3 } );
		withRepository( { getSchemaSummaries } );

		const result = await useSchemaStore().getAllSchemaSummaries();

		expect( result.map( ( item ) => item.name ) ).toEqual( [ 'A', 'B', 'C' ] );
		expect( getSchemaSummaries ).toHaveBeenNthCalledWith( 2, 2, 50 );
	} );

	it( 'caches the summaries and does not refetch on the next call', async () => {
		const getSchemaSummaries = vi.fn().mockResolvedValue( { schemas: [ summary( 'A' ) ], totalRows: 1 } );
		withRepository( { getSchemaSummaries } );
		const store = useSchemaStore();

		await store.getAllSchemaSummaries();
		await store.getAllSchemaSummaries();

		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 1 );
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
