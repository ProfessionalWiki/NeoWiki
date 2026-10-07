import { describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { importSchemas, planSchemaImport, type SchemaImportItem } from '@/components/SchemasPage/schemaImport.ts';
import type { Schema } from '@/domain/Schema.ts';
import { newSchema } from '@/TestHelpers.ts';

function item( name: string, replacesExisting = false ): SchemaImportItem {
	return { schema: newSchema( { title: name } ), replacesExisting };
}

describe( 'planSchemaImport', () => {
	it( 'marks the Schemas the wiki has as replacing them, and only those', () => {
		const plan = planSchemaImport(
			[ newSchema( { title: 'Artist' } ), newSchema( { title: 'Person' } ), newSchema( { title: 'Museum' } ) ],
			[ 'Company', 'Person' ],
		);

		expect( plan.map( ( planned ) => [ planned.schema.getName(), planned.replacesExisting ] ) ).toEqual( [
			[ 'Artist', false ],
			[ 'Person', true ],
			[ 'Museum', false ],
		] );
	} );

	it( 'recognises a Schema the wiki has under another spelling of its page title', () => {
		const plan = planSchemaImport( [ newSchema( { title: 'person_of interest' } ) ], [ 'Person of interest' ] );

		expect( plan[ 0 ].replacesExisting ).toBe( true );
	} );

	it( 'marks a Schema the file names twice, under two spellings, as replacing the first', () => {
		const plan = planSchemaImport(
			[ newSchema( { title: 'foo_bar' } ), newSchema( { title: 'Museum' } ), newSchema( { title: 'Foo bar' } ) ],
			[],
		);

		expect( plan.map( ( planned ) => planned.replacesExisting ) ).toEqual( [ false, false, true ] );
	} );
} );

describe( 'importSchemas', () => {
	it( 'reports the Schemas it saved as created or replaced', async () => {
		const outcome = await importSchemas(
			[ item( 'Artist' ), item( 'Person', true ), item( 'Museum' ) ],
			vi.fn().mockResolvedValue( undefined ),
		);

		expect( outcome ).toEqual( { created: [ 'Artist', 'Museum' ], replaced: [ 'Person' ], failed: [] } );
	} );

	it( 'reports a Schema that failed to save with the reason, and saves the rest', async () => {
		const save = vi.fn()
			.mockResolvedValueOnce( undefined )
			.mockRejectedValueOnce( new Error( 'The page is protected' ) )
			.mockResolvedValueOnce( undefined );

		const outcome = await importSchemas( [ item( 'Artist' ), item( 'Person', true ), item( 'Museum' ) ], save );

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
