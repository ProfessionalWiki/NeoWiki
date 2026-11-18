import { beforeEach, describe, expect, it } from 'vitest';
import { exportSchemas } from '@/components/SchemasPage/schemaExport.ts';
import { parseBundleFile } from '@/persistence/bundleFile.ts';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer.ts';
import { newSchema } from '@/TestHelpers.ts';
import { setupMwMock } from '../../VueTestHelpers.ts';
import { InMemoryDataModel } from '../../application/modelBundle/InMemoryDataModel.ts';

beforeEach( () => {
	setupMwMock( { functions: [ 'msg' ] } );
} );

describe( 'exportSchemas', () => {
	it( 'writes each named Schema under its name, as the JSON its page holds, sorted by name', async () => {
		const wiki = new InMemoryDataModel().withSchemas(
			newSchema( { title: 'Person', description: 'A human being' } ),
			newSchema( { title: 'Museum', description: '' } ),
		);

		const file = await exportSchemas( [ 'Person', 'Museum' ], wiki );

		expect( JSON.parse( file ) ).toEqual( {
			schemas: {
				Museum: { description: '', propertyDefinitions: {} },
				Person: { description: 'A human being', propertyDefinitions: {} },
			},
		} );
		expect( Object.keys( JSON.parse( file ).schemas ) ).toEqual( [ 'Museum', 'Person' ] );
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
					Employer: { type: 'relation', targetSchemas: [ 'Company' ], multiple: true },
					Signature: { type: 'notRegisteredHere', strokes: 3 },
				},
			} ),
			deserializer.deserialize( 'Company', { propertyDefinitions: {} } ),
		];

		const file = await exportSchemas( [ 'Person', 'Company' ], new InMemoryDataModel().withSchemas( ...schemas ) );

		const readBack = [ ...parseBundleFile( file ).schemas ].map( ( [ name, json ] ) => deserializer.deserialize( name, json ) );

		expect( readBack ).toEqual( [ schemas[ 1 ], schemas[ 0 ] ] );
	} );
} );
