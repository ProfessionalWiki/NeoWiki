import { describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import {
	importSchemas,
	planSchemaImport,
	type SchemaImportItem,
	type SchemaImportStatus,
} from '@/components/SchemasPage/schemaImport.ts';
import { InMemorySchemaLookup } from '@/application/SchemaLookup.ts';
import type { Schema } from '@/domain/Schema.ts';
import { newSchema } from '@/TestHelpers.ts';

function item( name: string, status: SchemaImportStatus = 'new' ): SchemaImportItem {
	return { schema: newSchema( { title: name } ), status };
}

function statuses( plan: SchemaImportItem[] ): [ string, SchemaImportStatus ][] {
	return plan.map( ( planned ) => [ planned.schema.getName(), planned.status ] );
}

describe( 'planSchemaImport', () => {
	it( 'marks each Schema as new, changed or unchanged against the wiki', async () => {
		const wiki = new InMemorySchemaLookup( [
			newSchema( { title: 'Person', description: 'A human being' } ),
			newSchema( { title: 'Museum', description: 'A place with art' } ),
		] );

		const plan = await planSchemaImport(
			[
				newSchema( { title: 'Artist', description: 'A maker of art' } ),
				newSchema( { title: 'Person', description: 'A person' } ),
				newSchema( { title: 'Museum', description: 'A place with art' } ),
			],
			[ 'Person', 'Museum' ],
			wiki,
		);

		expect( statuses( plan ) ).toEqual( [
			[ 'Artist', 'new' ],
			[ 'Person', 'changed' ],
			[ 'Museum', 'unchanged' ],
		] );
	} );

	it( 'recognises a Schema the wiki has under another spelling of its page title', async () => {
		const wiki = new InMemorySchemaLookup( [ newSchema( { title: 'Person of interest', description: 'Old' } ) ] );

		const plan = await planSchemaImport(
			[ newSchema( { title: 'person_of interest', description: 'New' } ) ],
			[ 'Person of interest' ],
			wiki,
		);

		expect( plan[ 0 ].status ).toBe( 'changed' );
	} );

	it( 'marks a Schema the file names twice, under two spellings, as changed the second time', async () => {
		const plan = await planSchemaImport(
			[ newSchema( { title: 'foo_bar' } ), newSchema( { title: 'Museum' } ), newSchema( { title: 'Foo bar' } ) ],
			[],
			new InMemorySchemaLookup( [] ),
		);

		expect( plan.map( ( planned ) => planned.status ) ).toEqual( [ 'new', 'new', 'changed' ] );
	} );

	it( 'marks a Schema as changed when the wiki\'s version fails to load', async () => {
		const plan = await planSchemaImport(
			[ newSchema( { title: 'Person' } ) ],
			[ 'Person' ],
			new InMemorySchemaLookup( [] ),
		);

		expect( plan[ 0 ].status ).toBe( 'changed' );
	} );
} );

describe( 'importSchemas', () => {
	it( 'reports the Schemas it saved as created or replaced', async () => {
		const outcome = await importSchemas(
			[ item( 'Artist' ), item( 'Person', 'changed' ), item( 'Museum' ) ],
			vi.fn().mockResolvedValue( undefined ),
		);

		expect( outcome ).toEqual( { created: [ 'Artist', 'Museum' ], replaced: [ 'Person' ], failed: [] } );
	} );

	it( 'reports a Schema that failed to save with the reason, and saves the rest', async () => {
		const save = vi.fn()
			.mockResolvedValueOnce( undefined )
			.mockRejectedValueOnce( new Error( 'The page is protected' ) )
			.mockResolvedValueOnce( undefined );

		const outcome = await importSchemas( [ item( 'Artist' ), item( 'Person', 'changed' ), item( 'Museum' ) ], save );

		expect( outcome ).toEqual( {
			created: [ 'Artist', 'Museum' ],
			replaced: [],
			failed: [ { name: 'Person', reason: 'The page is protected' } ],
		} );
	} );

	it( 'saves one Schema at a time, in the order given', async () => {
		const started: string[] = [];
		const finishSave: ( () => void )[] = [];
		const save = ( schema: Schema ): Promise<void> => {
			started.push( schema.getName() );
			return new Promise( ( resolve ) => {
				finishSave.push( resolve );
			} );
		};

		const run = importSchemas( [ item( 'Museum' ), item( 'Artist' ) ], save );
		await flushPromises();

		expect( started ).toEqual( [ 'Museum' ] );

		finishSave[ 0 ]();
		await flushPromises();

		expect( started ).toEqual( [ 'Museum', 'Artist' ] );

		finishSave[ 1 ]();
		await run;
	} );
} );
