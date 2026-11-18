import { beforeEach, describe, expect, it } from 'vitest';
import { parseBundleFile, serializeBundleFile } from '../../src/persistence/bundleFile';
import type { JsonObject, ModelBundle, SubjectEntry } from '../../src/application/ModelBundle';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer';
import { setupMwMock } from '../../../tests/VueTestHelpers';
import { bundleOf, schemaJson, subjectEntry } from '../application/builders';

const ID_1 = 's1demo1aaaaaaa1';
const ID_2 = 's1demo1aaaaaaa2';
const ID_3 = 's1demo1aaaaaaa3';

function bundle( overrides: Partial<ModelBundle> = {} ): ModelBundle {
	return bundleOf( [], [], { metadata: null, ...overrides } );
}

function withSubject( entry: SubjectEntry, id = ID_1 ): ModelBundle {
	return bundle( { subjects: new Map( [ [ id, entry ] ] ) } );
}

function schema( name: string, json: JsonObject ): JsonObject {
	return schemaJson( new SchemaDeserializer().deserialize( name, json ) );
}

function parsedSubject( subject: JsonObject ): SubjectEntry | undefined {
	return parseBundleFile( JSON.stringify( { subjects: { [ ID_1 ]: subject } } ) ).subjects.get( ID_1 );
}

function written( file: ModelBundle ): any {
	return JSON.parse( serializeBundleFile( file ) );
}

function parseError( text: string ): string {
	try {
		parseBundleFile( text );
	} catch ( error ) {
		return ( error as Error ).message;
	}

	throw new Error( 'The file was read' );
}

describe( 'serializeBundleFile', () => {

	beforeEach( () => {
		setupMwMock();
	} );

	it( 'writes each object with its keys sorted, indented by four spaces, ending in a newline', () => {
		const file = bundle( {
			metadata: { name: 'Vocabularies', version: '1.0', description: '' },
			mappings: new Map( [ [ 'EDM', { version: 1, schemas: {}, prefixes: { edm: 'http://www.europeana.eu/schemas/edm/' } } ] ] ),
			subjects: new Map( [ [ ID_1, subjectEntry( {
				statements: { URL: { value: [ 'http://vocab.getty.edu/aat/300189557' ], propertyType: 'url' } },
			} ) ] ] ),
		} );

		expect( serializeBundleFile( file ) ).toBe( `{
    "bundle": {
        "name": "Vocabularies",
        "version": "1.0"
    },
    "mappings": {
        "EDM": {
            "prefixes": {
                "edm": "http://www.europeana.eu/schemas/edm/"
            },
            "schemas": {},
            "version": 1
        }
    },
    "schemas": {},
    "subjects": {
        "${ ID_1 }": {
            "label": "female",
            "schema": "Gender Types",
            "statements": {
                "URL": {
                    "propertyType": "url",
                    "value": [
                        "http://vocab.getty.edu/aat/300189557"
                    ]
                }
            }
        }
    }
}
` );
	} );

	it( 'writes the items of each section by key, whatever order the bundle holds them in', () => {
		const file = JSON.parse( serializeBundleFile( bundle( {
			layouts: new Map( [ [ 'Zebra card', { schema: 'Zebra', type: 'infobox' } ], [ 'Ant card', { schema: 'Ant', type: 'infobox' } ] ] ),
		} ) ) );

		expect( Object.keys( file.layouts ) ).toEqual( [ 'Ant card', 'Zebra card' ] );
	} );

	it( 'keeps a Schema\'s properties in their order', () => {
		const file = bundle( { schemas: new Map( [ [ 'Person', schema( 'Person', {
			propertyDefinitions: { Name: { type: 'text' }, Born: { type: 'date' }, Age: { type: 'number' } },
		} ) ] ] ) } );

		expect( Object.keys( written( file ).schemas.Person.propertyDefinitions ) ).toEqual( [ 'Name', 'Born', 'Age' ] );
	} );

	it( 'keeps the parts of a value in their order', () => {
		const file = withSubject( subjectEntry( {
			statements: { Authors: { propertyType: 'relation', value: [ { target: ID_3 }, { target: ID_2 } ] } },
		} ) );

		expect( written( file ).subjects[ ID_1 ].statements.Authors.value ).toEqual( [ { target: ID_3 }, { target: ID_2 } ] );
	} );

	it( 'leaves relation IDs out', () => {
		const file = withSubject( subjectEntry( {
			statements: { Metatype: { propertyType: 'relation', value: [ { id: 'r1demo1rrrrrrr1', target: ID_2 } ] } },
		} ) );

		expect( written( file ).subjects[ ID_1 ].statements.Metatype.value ).toEqual( [ { target: ID_2 } ] );
	} );

	it( 'writes the page title of a Subject that has one', () => {
		expect( written( withSubject( subjectEntry( { pageTitle: 'Female' } ) ) ).subjects[ ID_1 ].pageTitle ).toBe( 'Female' );
		expect( written( withSubject( subjectEntry() ) ).subjects[ ID_1 ] ).not.toHaveProperty( 'pageTitle' );
	} );

	it( 'lists Dependent Subjects by ID', () => {
		const file = withSubject( subjectEntry( { dependentSubjects: [
			{ id: ID_3, schema: 'Birth', statements: {} },
			{ id: ID_2, schema: 'Birth', statements: {} },
		] } ) );

		expect( written( file ).subjects[ ID_1 ].dependentSubjects.map( ( dependent: { id: string } ) => dependent.id ) )
			.toEqual( [ ID_2, ID_3 ] );
	} );

	it( 'writes a file without metadata as a plain export holding only what it has', () => {
		const file = bundle( { schemas: new Map( [ [ 'Person', schema( 'Person', { propertyDefinitions: {} } ) ] ] ) } );

		expect( Object.keys( written( file ) ) ).toEqual( [ 'schemas' ] );
	} );

} );

describe( 'parseBundleFile', () => {

	beforeEach( () => {
		setupMwMock();
	} );

	it( 'reads back what serializeBundleFile writes', () => {
		const text = serializeBundleFile( bundle( {
			metadata: { name: 'Vocabularies', version: '2', description: 'Controlled lists' },
			schemas: new Map( [ [ 'Person', schema( 'Person', { propertyDefinitions: { Name: { type: 'text' } } } ) ] ] ),
			layouts: new Map( [ [ 'Card', { schema: 'Person', type: 'infobox', displayRules: [ { property: 'Name' } ] } ] ] ),
			mappings: new Map( [ [ 'EDM', { version: 1, schemas: {} } ] ] ),
			subjects: new Map( [ [ ID_1, subjectEntry( {
				pageTitle: 'Female',
				dependentSubjects: [ { id: ID_2, schema: 'Birth', statements: {} } ],
			} ) ] ] ),
		} ) );

		expect( serializeBundleFile( parseBundleFile( text ) ) ).toBe( text );
	} );

	it( 'reads a Schema export made before Model bundles', () => {
		const file = parseBundleFile( JSON.stringify( {
			schemas: { Person: { description: 'A human being', propertyDefinitions: { Name: { type: 'text' } } } },
		} ) );

		expect( file.metadata ).toBeNull();
		expect( [ ...file.schemas.keys() ] ).toEqual( [ 'Person' ] );
	} );

	it( 'reads a Layout as the Layout editor saves it', () => {
		const file = parseBundleFile( JSON.stringify( { layouts: { Card: {
			schema: 'Person', type: 'infobox', description: '', settings: {}, displayRules: [ { property: 'Name' } ],
		} } } ) );

		expect( file.layouts.get( 'Card' ) ).toEqual( { schema: 'Person', type: 'infobox', displayRules: [ { property: 'Name' } ] } );
	} );

	it( 'reads the Schema of a Subject and of its Dependent Subjects as the Schema\'s page is titled', () => {
		const subject = parsedSubject( {
			schema: 'city',
			statements: {},
			dependentSubjects: [ { id: ID_2, schema: 'city', statements: {} } ],
		} );

		expect( [ subject?.schema, subject?.dependentSubjects[ 0 ].schema ] ).toEqual( [ 'City', 'City' ] );
	} );

	it( 'reads a value given as a bare string as a list of one, in a Dependent Subject too', () => {
		const subject = parsedSubject( {
			schema: 'City',
			statements: { Country: { propertyType: 'text', value: 'Spain' } },
			dependentSubjects: [ { id: ID_2, schema: 'Founding', statements: { Founder: { propertyType: 'text', value: 'Hercules' } } } ],
		} );

		expect( [ subject?.statements.Country, subject?.dependentSubjects[ 0 ].statements.Founder ] ).toEqual( [
			{ propertyType: 'text', value: [ 'Spain' ] },
			{ propertyType: 'text', value: [ 'Hercules' ] },
		] );
	} );

	it( 'leaves out the Statements whose value holds nothing', () => {
		const subject = parsedSubject( { schema: 'City', statements: {
			Aliases: { propertyType: 'text', value: [] },
			Nicknames: { propertyType: 'text', value: [ '' ] },
			Population: { propertyType: 'number', value: 0 },
		} } );

		expect( subject?.statements ).toEqual( { Population: { propertyType: 'number', value: 0 } } );
	} );

	it( 'reads monolingual text trimmed, its language in lower case', () => {
		const subject = parsedSubject( { schema: 'City', statements: {
			Name: { propertyType: 'monolingualText', value: [ { text: ' Sevilla ', language: 'ES' } ] },
		} } );

		expect( subject?.statements ).toEqual( { Name: { propertyType: 'monolingualText', value: [ { text: 'Sevilla', language: 'es' } ] } } );
	} );

	it( 'reads the metadata of a Model bundle', () => {
		const file = parseBundleFile( JSON.stringify( {
			bundle: { name: ' Vocabularies ', version: '1.2' },
			schemas: { Person: { propertyDefinitions: {} } },
		} ) );

		expect( file.metadata ).toEqual( { name: 'Vocabularies', version: '1.2', description: '' } );
	} );

	it( 'names each item as its page is titled', () => {
		const file = parseBundleFile( JSON.stringify( { schemas: { person_of_interest: { propertyDefinitions: {} } } } ) );

		expect( [ ...file.schemas.keys() ] ).toEqual( [ 'Person of interest' ] );
	} );

	it( 'ignores keys it does not know', () => {
		const file = parseBundleFile( JSON.stringify( { catalog: [], schemas: { Person: { propertyDefinitions: {} } } } ) );

		expect( file.schemas.size ).toBe( 1 );
	} );

	it.each( [
		[ 'text that is not JSON', '{ "schemas": ', 'neowiki-bundle-file-not-json' ],
		[ 'a file without items', '{ "schemas": {} }', 'neowiki-bundle-file-empty' ],
		[ 'a section that is not an object', '{ "subjects": [] }', 'neowiki-bundle-file-section-not-objectsubjects' ],
		[ 'a Schema without properties', '{ "schemas": { "Person": {} } }', 'neowiki-bundle-file-schema-no-property-definitionsPerson' ],
		[
			'a Schema with a property it cannot read',
			'{ "schemas": { "Person": { "propertyDefinitions": { " ": { "type": "text" } } } } }',
			'neowiki-bundle-file-invalid-schemaPersonInvalid PropertyName',
		],
		[
			'a Layout without a View Type',
			'{ "layouts": { "Card": { "schema": "Person" } } }',
			'neowiki-bundle-file-invalid-layoutCardneowiki-bundle-file-layout-fields',
		],
		[
			'a Layout with a display rule it cannot read',
			'{ "layouts": { "Card": { "schema": "Person", "type": "infobox", "displayRules": [ { "property": " " } ] } } }',
			'neowiki-bundle-file-invalid-layoutCardInvalid PropertyName',
		],
		[ 'a Mapping that is not an object', '{ "mappings": { "EDM": 1 } }', 'neowiki-bundle-file-invalid-mappingEDM' ],
		[ 'a Subject without a Schema', `{ "subjects": { "${ ID_1 }": { "statements": {} } } }`, `neowiki-bundle-file-invalid-subject${ ID_1 }` ],
		[ 'a Subject under no Subject ID', '{ "subjects": { "female": { "schema": "Gender Types", "statements": {} } } }', 'neowiki-bundle-file-invalid-subjectfemale' ],
		[
			'a Statement without a value',
			`{ "subjects": { "${ ID_1 }": { "schema": "City", "statements": { "Name": { "propertyType": "text", "value": null } } } } }`,
			`neowiki-bundle-file-invalid-subject${ ID_1 }`,
		],
		[
			'a Statement without a Property Type',
			`{ "subjects": { "${ ID_1 }": { "schema": "City", "statements": { "Name": { "value": [ "Sevilla" ] } } } } }`,
			`neowiki-bundle-file-invalid-subject${ ID_1 }`,
		],
		[
			'a Dependent Subject under no Subject ID',
			`{ "subjects": { "${ ID_1 }": { "schema": "Person", "statements": {}, "dependentSubjects": [ { "id": "birth", "schema": "Birth", "statements": {} } ] } } }`,
			`neowiki-bundle-file-invalid-subject${ ID_1 }`,
		],
		[ 'metadata without a name', '{ "bundle": { "version": "1" }, "schemas": { "A": { "propertyDefinitions": {} } } }', 'neowiki-bundle-file-invalid-metadata' ],
		[ 'a bundle name no page can be titled with', '{ "bundle": { "name": "Vocab [EN]" }, "schemas": { "A": { "propertyDefinitions": {} } } }', 'neowiki-bundle-name-unusable' ],
		[
			'two spellings of one Schema name',
			'{ "schemas": { "Person": { "propertyDefinitions": {} }, "person": { "propertyDefinitions": {} } } }',
			'neowiki-bundle-file-duplicatePerson',
		],
	] )( 'refuses %s, saying why', ( _description, text, message ) => {
		expect( parseError( text ) ).toBe( message );
	} );

} );
