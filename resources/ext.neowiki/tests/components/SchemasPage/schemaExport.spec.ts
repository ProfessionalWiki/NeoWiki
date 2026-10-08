import { beforeEach, describe, expect, it } from 'vitest';
import { exportSchemas, parseSchemaExport } from '@/components/SchemasPage/schemaExport.ts';
import { InMemorySchemaLookup } from '@/application/SchemaLookup.ts';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer.ts';
import { newSchema } from '@/TestHelpers.ts';
import { setupMwMock } from '../../VueTestHelpers.ts';

function fileWithSchemas( schemas: Record<string, unknown> ): string {
	return JSON.stringify( { schemas } );
}

beforeEach( () => {
	setupMwMock( { functions: [ 'msg' ] } );
} );

describe( 'exportSchemas', () => {
	it( 'writes each named Schema under its name, as the JSON its page holds', async () => {
		const lookup = new InMemorySchemaLookup( [
			newSchema( { title: 'Person', description: 'A human being' } ),
			newSchema( { title: 'Museum', description: '' } ),
		] );

		const file = await exportSchemas( [ 'Person', 'Museum' ], lookup );

		expect( JSON.parse( file ) ).toEqual( {
			schemas: {
				Person: { description: 'A human being', propertyDefinitions: {} },
				Museum: { description: '', propertyDefinitions: {} },
			},
		} );
	} );

	it( 'writes a file that reads back as the Schemas it was written from', async () => {
		const deserializer = new SchemaDeserializer();
		const schemas = [
			deserializer.deserialize( 'Person', {
				description: 'A human being',
				propertyDefinitions: {
					Name: { type: 'text', required: { severity: 'error' }, maxLength: 80 },
					Age: { type: 'number', minimum: 0, precision: 0, default: 18 },
					Status: {
						type: 'select',
						options: [ { id: 'o1aaaaaaaaaaaa1', label: 'Alive' }, { id: 'o1aaaaaaaaaaaa2', label: 'Dead' } ],
					},
					Employer: { type: 'relation', relation: 'Works at', targetSchema: 'Company', multiple: true },
					Signature: { type: 'notRegisteredHere', strokes: 3 },
				},
			} ),
			deserializer.deserialize( 'Company', { propertyDefinitions: {} } ),
		];

		const file = await exportSchemas( [ 'Person', 'Company' ], new InMemorySchemaLookup( schemas ) );

		expect( parseSchemaExport( file ) ).toEqual( schemas );
	} );

	it( 'rejects naming the Schema that failed to load', async () => {
		const lookup = new InMemorySchemaLookup( [ newSchema( { title: 'Person' } ) ] );

		await expect( exportSchemas( [ 'Person', 'Museum' ], lookup ) )
			.rejects.toThrow( 'neowiki-schemas-export-error-unreadable-schemaMuseum' );
	} );
} );

describe( 'parseSchemaExport', () => {
	it( 'reads each Schema in the file under its name, in file order', () => {
		const schemas = parseSchemaExport( fileWithSchemas( {
			Person: { description: 'A human being', propertyDefinitions: { Name: { type: 'text' } } },
			Museum: { propertyDefinitions: {} },
		} ) );

		expect( schemas.map( ( schema ) => schema.getName() ) ).toEqual( [ 'Person', 'Museum' ] );
		expect( schemas[ 0 ].getDescription() ).toBe( 'A human being' );
		expect( schemas[ 0 ].getPropertyDefinition( 'Name' ).type ).toBe( 'text' );
	} );

	it( 'rejects text that is not JSON', () => {
		expect( () => parseSchemaExport( '{ "schemas": {' ) ).toThrow( 'neowiki-schemas-import-error-not-json' );
	} );

	it.each( [
		[ 'no schemas key', '{ "layouts": {} }' ],
		[ 'a list of schemas', '{ "schemas": [] }' ],
		[ 'null schemas', '{ "schemas": null }' ],
		[ 'null at the top', 'null' ],
	] )( 'rejects a file without a schemas object: %s', ( _case, text ) => {
		expect( () => parseSchemaExport( text ) ).toThrow( 'neowiki-schemas-import-error-no-schemas-object' );
	} );

	it( 'rejects a file without Schemas', () => {
		expect( () => parseSchemaExport( fileWithSchemas( {} ) ) ).toThrow( 'neowiki-schemas-import-error-empty' );
	} );

	it.each( [
		[ 'null', null ],
		[ 'no propertyDefinitions', { description: 'A place with art' } ],
		[ 'a list of propertyDefinitions', { propertyDefinitions: [] } ],
		[ 'null propertyDefinitions', { propertyDefinitions: null } ],
	] )( 'rejects an entry that is no Schema, naming it: %s', ( _case, entry ) => {
		const text = fileWithSchemas( {
			Person: { propertyDefinitions: {} },
			Museum: entry,
			Artist: { propertyDefinitions: {} },
		} );

		expect( () => parseSchemaExport( text ) ).toThrow( 'neowiki-schemas-import-error-no-property-definitionsMuseum' );
	} );

	it( 'rejects an entry it cannot read as a Schema, naming it and why', () => {
		const text = fileWithSchemas( {
			Person: { propertyDefinitions: {} },
			Museum: { propertyDefinitions: { Name: { type: 'text', required: { severity: 'fatal' } } } },
		} );

		expect( () => parseSchemaExport( text ) )
			.toThrow( 'neowiki-schemas-import-error-unreadable-schemaMuseumInvalid severity: "fatal"' );
	} );
} );
