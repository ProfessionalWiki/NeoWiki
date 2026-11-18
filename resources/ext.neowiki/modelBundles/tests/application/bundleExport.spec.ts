import { beforeEach, describe, expect, it } from 'vitest';
import { dependentSchemasOf, exportBundle, type ExportChoice } from '../../src/application/bundleExport';
import { newSchema } from '@/TestHelpers';
import { setupMwMock } from '../../../tests/VueTestHelpers';
import { InMemoryDataModel } from './InMemoryDataModel';
import { subjectEntry } from './builders';

const ID_1 = 's1demo1aaaaaaa1';
const ID_2 = 's1demo1aaaaaaa2';
const ID_3 = 's1demo1aaaaaaa3';

let wiki: InMemoryDataModel;

function choice( overrides: Partial<ExportChoice> = {} ): ExportChoice {
	return {
		schemas: [ { name: 'Gender Types', withSubjects: false } ],
		layouts: true,
		mappings: [ 'EDM' ],
		metadata: null,
		...overrides,
	};
}

describe( 'exportBundle', () => {

	beforeEach( () => {
		setupMwMock();
		wiki = new InMemoryDataModel().withSchemas( newSchema( { title: 'Gender Types' } ), newSchema( { title: 'Person' } ) );
		wiki.layouts.set( 'Gender card', { schema: 'Gender Types', type: 'infobox' } );
		wiki.layouts.set( 'Person card', { schema: 'Person', type: 'infobox' } );
		wiki.mappings.set( 'EDM', { version: 1, schemas: {} } );
		wiki.mappings.set( 'CIDOC-CRM', { version: 1, schemas: {} } );
		wiki.withSubject( ID_1, subjectEntry() ).withSubject( ID_2, subjectEntry( { schema: 'Person', label: 'Ada' } ) );
	} );

	it( 'holds the chosen Schemas, their Layouts and the chosen Mappings', async () => {
		const bundle = await exportBundle( choice(), wiki );

		expect( [ ...bundle.schemas.keys() ] ).toEqual( [ 'Gender Types' ] );
		expect( [ ...bundle.layouts.keys() ] ).toEqual( [ 'Gender card' ] );
		expect( [ ...bundle.mappings.keys() ] ).toEqual( [ 'EDM' ] );
	} );

	it( 'holds the Subjects of the Schemas they were chosen for', async () => {
		const bundle = await exportBundle( choice( { schemas: [
			{ name: 'Gender Types', withSubjects: true },
			{ name: 'Person', withSubjects: false },
		] } ), wiki );

		expect( [ ...bundle.subjects.keys() ] ).toEqual( [ ID_1 ] );
	} );

	it( 'holds the Schema of the Dependent Subjects it holds', async () => {
		wiki.withSchemas( newSchema( { title: 'Birth', subjectsAreDependent: true } ) );
		wiki.withSubject( ID_2, subjectEntry( { schema: 'Person', label: 'Ada', dependentSubjects: [ { id: ID_3, schema: 'Birth', statements: {} } ] } ) );

		const bundle = await exportBundle( choice( { schemas: [ { name: 'Person', withSubjects: true } ] } ), wiki );

		expect( [ ...bundle.schemas.keys() ] ).toEqual( [ 'Person', 'Birth' ] );
	} );

	it( 'leaves out Layouts and Mappings when they are not chosen', async () => {
		const bundle = await exportBundle( choice( { layouts: false, mappings: [] } ), wiki );

		expect( bundle.layouts.size + bundle.mappings.size ).toBe( 0 );
	} );

	it( 'names what it could not read', async () => {
		wiki.unreadable.add( 'layout:Gender card' );

		await expect( exportBundle( choice(), wiki ) ).rejects.toThrow( 'neowiki-bundle-export-unreadable-pageLayout:Gender card' );
	} );

} );

describe( 'dependentSchemasOf', () => {

	it( 'names the dependent Schemas that a Schema\'s relations point at', () => {
		const schema = { propertyDefinitions: {
			Name: { type: 'text' },
			Birth: { type: 'relation', targetSchemas: [ 'birth' ] },
			Home: { type: 'relation', targetSchemas: [ 'City' ] },
		} };

		expect( dependentSchemasOf( schema, new Set( [ 'Birth' ] ) ) ).toEqual( [ 'Birth' ] );
	} );

} );
