import { beforeEach, describe, expect, it } from 'vitest';
import { RestDataModelRepository } from '../../src/persistence/RestDataModelRepository';
import { PageTitleTakenError } from '@/persistence/PageTitleTakenError';
import { InMemorySchemaRepository } from '@/application/SchemaRepository';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer';
import { SchemaSerializer } from '@/persistence/SchemaSerializer';
import type { PageRevisionSaver, RevisionSaveStatus } from '@/persistence/PageSaver';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';
import type { ActionApi } from '../../src/persistence/ActionApi';
import { subjectEntry } from '../application/builders';
import { setupMwMock } from '../../../tests/VueTestHelpers';
import { stubActionApi } from './stubActionApi';

const REST = '/rest.php';
const HOST = 's1demo1aaaaaaa1';
const DEPENDENT = 's1demo1aaaaaaa2';
const NESTED_DEPENDENT = 's1demo1aaaaaaa3';
const OTHER_HOST = 's1demo1aaaaaaa4';
const OTHER_DEPENDENT = 's1demo1aaaaaaa5';

interface SentRequest {
	method: string;
	url: string;
	body?: Record<string, unknown>;
}

/**
 * Answers each request by its method and URL, as the production client does: 2xx, 409 and 422 resolve,
 * anything else rejects with the response attached.
 */
class RoutedHttpClient implements HttpClient {

	public readonly sent: SentRequest[] = [];

	public constructor( private readonly routes: Record<string, [ number, unknown ]> ) {
	}

	public get( url: string ): Promise<Response> {
		return this.answer( 'GET', url );
	}

	public post( url: string, data?: Record<string, unknown> ): Promise<Response> {
		return this.answer( 'POST', url, data );
	}

	public patch( url: string, data?: Record<string, unknown> ): Promise<Response> {
		return this.answer( 'PATCH', url, data );
	}

	public put( url: string, data?: Record<string, unknown> ): Promise<Response> {
		return this.answer( 'PUT', url, data );
	}

	public delete( url: string, config?: Record<string, unknown> ): Promise<Response> {
		return this.answer( 'DELETE', url, config?.data as Record<string, unknown> );
	}

	private async answer( method: string, url: string, body?: Record<string, unknown> ): Promise<Response> {
		this.sent.push( { method, url, body } );
		const route = this.routes[ `${ method } ${ url }` ];

		if ( route === undefined ) {
			throw new Error( `No route for ${ method } ${ url }` );
		}

		const [ status, json ] = route;

		if ( status >= 300 && status !== 409 && status !== 422 ) {
			throw Object.assign( new Error( `Request failed with status code ${ status }` ), { response: { status, data: json } } );
		}

		return new Response( JSON.stringify( json ), { status } );
	}

}

/**
 * Records each page save, answering each with the status it was made with.
 */
class RecordingPageSaver implements PageRevisionSaver {

	public readonly saved: { pageName: string; contentModel: string; baseRevisionId: number | null }[] = [];

	public constructor( private readonly status: RevisionSaveStatus = { success: true, revisionId: 13, source: '' } ) {
	}

	public async saveRevision(
		pageName: string,
		_source: string,
		_comment: string,
		contentModel: string,
		baseRevisionId: number | null,
	): Promise<RevisionSaveStatus> {
		this.saved.push( { pageName, contentModel, baseRevisionId } );
		return this.status;
	}

}

function repository(
	http: HttpClient,
	api: ActionApi = stubActionApi(),
	pageSaver: PageRevisionSaver = new RecordingPageSaver(),
): RestDataModelRepository {
	return new RestDataModelRepository( REST, http, new InMemorySchemaRepository( [] ), pageSaver, api );
}

function subjectJson( id: string, overrides: Record<string, unknown> = {} ): Record<string, unknown> {
	return { id, label: null, schema: 'Person', statements: {}, pageId: 7, pageTitle: 'Ada Lovelace', pageNamespaceId: 0, ...overrides };
}

const PAGE = {
	pageId: 7,
	mainSubjectId: HOST,
	subjects: {
		[ HOST ]: subjectJson( HOST, {
			label: 'Ada Lovelace',
			statements: { Birth: { propertyType: 'relation', value: [ { id: 'r1demo1rrrrrrr1', target: DEPENDENT } ] } },
		} ),
		[ DEPENDENT ]: subjectJson( DEPENDENT, { schema: 'Birth', hostSubject: { id: HOST } } ),
		[ NESTED_DEPENDENT ]: subjectJson( NESTED_DEPENDENT, { schema: 'Place', hostSubject: { id: DEPENDENT, hostSubject: { id: HOST } } } ),
		[ OTHER_HOST ]: subjectJson( OTHER_HOST, { label: 'Charles Babbage' } ),
		[ OTHER_DEPENDENT ]: subjectJson( OTHER_DEPENDENT, { schema: 'Birth', hostSubject: { id: OTHER_HOST } } ),
	},
};

describe( 'RestDataModelRepository', () => {

	beforeEach( () => {
		setupMwMock( { messages: { 'comma-separator': ', ' } } );
	} );

	it( 'reads a Schema as NeoWiki saves it, so that one saved by hand compares alike', async () => {
		const handSaved = { propertyDefinitions: { Name: { type: 'text' } } };
		const http = new RoutedHttpClient( {
			[ `GET ${ REST }/v1/page/Schema:Person` ]: [ 200, { source: JSON.stringify( handSaved ), latest: { id: 3 } } ],
		} );

		const schema = await repository( http ).getSchema( 'Person' );

		expect( schema ).toEqual( {
			json: new SchemaSerializer().schemaToJson( new SchemaDeserializer().deserialize( 'Person', handSaved ) ),
			revisionId: 3,
		} );
	} );

	describe( 'getSubject', () => {

		it( 'answers null for an ID no Subject has', async () => {
			const http = new RoutedHttpClient( { [ `GET ${ REST }/neowiki/v0/subject/${ HOST }?latest=1&expand=page` ]: [ 200, { subject: null } ] } );

			expect( await repository( http ).getSubject( HOST ) ).toBeNull();
		} );

		it( 'reads a Subject with the Dependent Subjects whose chain of hosts starts at it', async () => {
			const http = new RoutedHttpClient( {
				[ `GET ${ REST }/neowiki/v0/subject/${ HOST }?latest=1&expand=page` ]: [ 200, { requestedId: HOST, subjects: { [ HOST ]: PAGE.subjects[ HOST ] } } ],
				[ `GET ${ REST }/neowiki/v0/page/7/subjects` ]: [ 200, PAGE ],
			} );

			const subject = await repository( http ).getSubject( HOST );

			expect( subject?.hostRootId ).toBeNull();
			expect( subject?.entry.label ).toBe( 'Ada Lovelace' );
			expect( subject?.entry.dependentSubjects.map( ( dependent ) => dependent.id ) ).toEqual( [ DEPENDENT, NESTED_DEPENDENT ] );
		} );

		it( 'tells which standalone Subject a Dependent Subject belongs to', async () => {
			const http = new RoutedHttpClient( {
				[ `GET ${ REST }/neowiki/v0/subject/${ NESTED_DEPENDENT }?latest=1&expand=page` ]: [ 200, {
					requestedId: NESTED_DEPENDENT,
					subjects: { [ NESTED_DEPENDENT ]: PAGE.subjects[ NESTED_DEPENDENT ] },
				} ],
			} );

			expect( ( await repository( http ).getSubject( NESTED_DEPENDENT ) )?.hostRootId ).toBe( HOST );
		} );

	} );

	describe( 'getSubjectsOfSchema', () => {

		it( 'reads the Schema\'s standalone Subjects with their Dependent Subjects', async () => {
			const http = new RoutedHttpClient( {
				[ `GET ${ REST }/neowiki/v0/subjects?schema=Person&limit=50` ]: [ 200, {
					subjects: [ { id: HOST, pageId: 7 }, { id: OTHER_HOST, pageId: 7 } ],
					nextCursor: null,
				} ],
				[ `GET ${ REST }/neowiki/v0/page/7/subjects` ]: [ 200, PAGE ],
			} );

			const subjects = await repository( http ).getSubjectsOfSchema( 'Person' );

			expect( [ ...subjects.keys() ] ).toEqual( [ HOST, OTHER_HOST ] );
			expect( subjects.get( OTHER_HOST )?.dependentSubjects.map( ( dependent ) => dependent.id ) ).toEqual( [ OTHER_DEPENDENT ] );
		} );

		it( 'leaves out the Subjects of other Schemas on the same pages', async () => {
			const http = new RoutedHttpClient( {
				[ `GET ${ REST }/neowiki/v0/subjects?schema=Person&limit=50` ]: [ 200, { subjects: [ { id: HOST, pageId: 7 } ], nextCursor: null } ],
				[ `GET ${ REST }/neowiki/v0/page/7/subjects` ]: [ 200, {
					...PAGE,
					subjects: { [ HOST ]: PAGE.subjects[ HOST ], [ OTHER_HOST ]: subjectJson( OTHER_HOST, { schema: 'Museum' } ) },
				} ],
			} );

			expect( [ ...( await repository( http ).getSubjectsOfSchema( 'Person' ) ).keys() ] ).toEqual( [ HOST ] );
		} );

		it( 'gives only the Main Subject of a main-namespace page its page title', async () => {
			const http = new RoutedHttpClient( {
				[ `GET ${ REST }/neowiki/v0/subjects?schema=Person&limit=50` ]: [ 200, {
					subjects: [ { id: HOST, pageId: 7 }, { id: OTHER_HOST, pageId: 7 }, { id: 's1demo1aaaaaaa6', pageId: 8 } ],
					nextCursor: null,
				} ],
				[ `GET ${ REST }/neowiki/v0/page/7/subjects` ]: [ 200, PAGE ],
				[ `GET ${ REST }/neowiki/v0/page/8/subjects` ]: [ 200, {
					pageId: 8,
					mainSubjectId: 's1demo1aaaaaaa6',
					subjects: { s1demo1aaaaaaa6: subjectJson( 's1demo1aaaaaaa6', { pageTitle: 'Help:Ada', pageNamespaceId: 12 } ) },
				} ],
			} );

			const subjects = await repository( http ).getSubjectsOfSchema( 'Person' );

			expect( [ ...subjects ].map( ( [ id, entry ] ) => [ id, entry.pageTitle ] ) ).toEqual( [
				[ HOST, 'Ada Lovelace' ],
				[ OTHER_HOST, null ],
				[ 's1demo1aaaaaaa6', null ],
			] );
		} );

	} );

	describe( 'hasSubjectsOutside', () => {

		const PERSON_ROWS: Record<string, [ number, unknown ]> = {
			[ `GET ${ REST }/neowiki/v0/subjects?schema=Person&limit=50` ]: [ 200, {
				subjects: [ { id: HOST, pageId: 7 }, { id: OTHER_HOST, pageId: 7 } ],
				nextCursor: null,
			} ],
		};

		it( 'finds a Subject of the Schema that is not among the IDs given', async () => {
			expect( await repository( new RoutedHttpClient( PERSON_ROWS ) ).hasSubjectsOutside( 'Person', new Set( [ HOST ] ) ) ).toBe( true );
		} );

		it( 'finds none when the IDs given cover every Subject of the Schema', async () => {
			expect( await repository( new RoutedHttpClient( PERSON_ROWS ) ).hasSubjectsOutside( 'Person', new Set( [ HOST, OTHER_HOST ] ) ) )
				.toBe( false );
		} );

	} );

	it.each( [
		[ 'Layout', ( wiki: RestDataModelRepository ) => wiki.deleteLayout( 'Card', 'Remove' ) ],
		[ 'Mapping', ( wiki: RestDataModelRepository ) => wiki.deleteMapping( 'Card', 'Remove' ) ],
	] )( 'deletes a %s by its page title', async ( namespace, deletion ) => {
		const asked: Record<string, unknown>[] = [];
		const api = stubActionApi( {
			postWithEditToken: async ( parameters ) => {
				asked.push( parameters );
				return {};
			},
		} );

		await deletion( repository( new RoutedHttpClient( {} ), api ) );

		expect( asked ).toEqual( [ { action: 'delete', title: `${ namespace }:Card`, reason: 'Remove' } ] );
	} );

	it( 'saves a page over the revision it was read from', async () => {
		const pageSaver = new RecordingPageSaver();

		await repository( new RoutedHttpClient( {} ), undefined, pageSaver ).saveSchema( 'Person', { propertyDefinitions: {} }, 'Install', 12 );

		expect( pageSaver.saved ).toEqual( [ { pageName: 'Schema:Person', contentModel: 'NeoWikiSchema', baseRevisionId: 12 } ] );
	} );

	it( 'fails a Mapping save the wiki refuses', async () => {
		const pageSaver = new RecordingPageSaver( { success: false, message: 'Edit conflict' } );

		const saving = repository( new RoutedHttpClient( {} ), undefined, pageSaver ).saveMapping( 'EDM', { version: 1, schemas: {} }, 'Install', null );

		await expect( saving ).rejects.toThrow( 'Edit conflict' );
	} );

	describe( 'Subject writes', () => {

		it( 'creates a Subject under its own ID and Schema, on the page title given', async () => {
			const http = new RoutedHttpClient( { [ `POST ${ REST }/neowiki/v0/subjects` ]: [ 201, {} ] } );

			await repository( http ).createSubject( HOST, subjectEntry( { schema: 'Person' } ), 'Ada Lovelace', 'Install' );

			expect( http.sent[ 0 ].body ).toMatchObject( { id: HOST, schema: 'Person', pageTitle: 'Ada Lovelace' } );
		} );

		it( 'reports a taken page title as such', async () => {
			const http = new RoutedHttpClient( {
				[ `POST ${ REST }/neowiki/v0/subjects` ]: [ 409, { message: 'A page named "Female" already exists', pageTitle: 'Female' } ],
			} );

			await expect( repository( http ).createSubject( HOST, subjectEntry(), 'Female', '' ) ).rejects.toBeInstanceOf( PageTitleTakenError );
		} );

		it( 'fails with the reason the wiki gives', async () => {
			const http = new RoutedHttpClient( { [ `POST ${ REST }/neowiki/v0/subjects` ]: [ 409, { message: 'Subject already exists' } ] } );

			await expect( repository( http ).createSubject( HOST, subjectEntry(), null, '' ) ).rejects.toThrow( 'Subject already exists' );
		} );

		it( 'fails with the violations of a refused write', async () => {
			const http = new RoutedHttpClient( { [ `PUT ${ REST }/neowiki/v0/subject/${ HOST }` ]: [ 422, { violations: [
				{ propertyName: 'URL', code: 'invalid-url', severity: 'error' },
				{ propertyName: null, code: 'schema-not-found', severity: 'error' },
			] } ] } );

			await expect( repository( http ).replaceSubject( HOST, subjectEntry(), '' ) )
				.rejects.toThrow( 'neowiki-bundle-write-violationsinvalid-url (URL), schema-not-found' );
		} );

		it( 'fails with the message of a rejected request', async () => {
			const http = new RoutedHttpClient( { [ `DELETE ${ REST }/neowiki/v0/subject/${ HOST }` ]: [ 403, {
				messageTranslations: { en: 'You may not edit this page' },
			} ] } );

			await expect( repository( http ).deleteSubject( HOST, '' ) ).rejects.toThrow( 'You may not edit this page' );
		} );

		describe( 'addSubjectToEmptyPage', () => {

			/**
			 * Answers a query for the title given with the page given, and for any other title with a missing page.
			 */
			function titleQuery( title: string, page: Record<string, unknown> ): ActionApi {
				return stubActionApi( {
					get: async ( parameters ) => ( { query: { pages: [ parameters.titles === title ? page : { title: parameters.titles, missing: true } ] } } ),
				} );
			}

			it( 'puts the Subject, with its ID, on the page titled by its ID when that page holds none', async () => {
				const http = new RoutedHttpClient( {
					[ `GET ${ REST }/neowiki/v0/page/9/subjects` ]: [ 200, { pageId: 9, mainSubjectId: null, subjects: [] } ],
					[ `POST ${ REST }/neowiki/v0/page/9/mainSubject` ]: [ 201, { status: 'created' } ],
				} );

				const added = await repository( http, titleQuery( HOST, { pageid: 9, title: HOST } ) )
					.addSubjectToEmptyPage( HOST, HOST, subjectEntry(), 'Install' );

				expect( added ).toBe( true );
				expect( http.sent.find( ( request ) => request.method === 'POST' )?.body ).toMatchObject( { id: HOST, schema: 'Gender Types' } );
			} );

			it( 'puts the Subject on the empty page of the title given, in whatever namespace', async () => {
				const http = new RoutedHttpClient( {
					[ `GET ${ REST }/neowiki/v0/page/9/subjects` ]: [ 200, { pageId: 9, mainSubjectId: null, subjects: [] } ],
					[ `POST ${ REST }/neowiki/v0/page/9/mainSubject` ]: [ 201, { status: 'created' } ],
				} );

				const added = await repository( http, titleQuery( `Subject:${ HOST }`, { pageid: 9, title: `Subject:${ HOST }` } ) )
					.addSubjectToEmptyPage( `Subject:${ HOST }`, HOST, subjectEntry(), 'Install' );

				expect( added ).toBe( true );
			} );

			it( 'leaves a redirect alone', async () => {
				const http = new RoutedHttpClient( {} );

				const added = await repository( http, titleQuery( HOST, { pageid: 9, title: HOST, redirect: true } ) )
					.addSubjectToEmptyPage( HOST, HOST, subjectEntry(), 'Install' );

				expect( added ).toBe( false );
				expect( http.sent ).toEqual( [] );
			} );

			it( 'leaves alone a page that holds Subjects', async () => {
				const http = new RoutedHttpClient( {
					[ `GET ${ REST }/neowiki/v0/page/9/subjects` ]: [ 200, { pageId: 9, mainSubjectId: OTHER_HOST, subjects: {
						[ OTHER_HOST ]: subjectJson( OTHER_HOST ),
					} } ],
				} );

				const added = await repository( http, titleQuery( HOST, { pageid: 9, title: HOST } ) )
					.addSubjectToEmptyPage( HOST, HOST, subjectEntry(), 'Install' );

				expect( added ).toBe( false );
				expect( http.sent.map( ( request ) => request.method ) ).toEqual( [ 'GET' ] );
			} );

		} );

		it( 'replaces a Subject together with its Dependent Subjects', async () => {
			const http = new RoutedHttpClient( { [ `PUT ${ REST }/neowiki/v0/subject/${ HOST }` ]: [ 200, {} ] } );
			const dependentSubjects = [ { id: DEPENDENT, schema: 'Birth', statements: { Year: { propertyType: 'number', value: 1815 } } } ];

			await repository( http ).replaceSubject( HOST, subjectEntry( { schema: 'Person', dependentSubjects } ), 'Update' );

			expect( http.sent[ 0 ].body?.dependentSubjects ).toEqual( dependentSubjects );
		} );

		it( 'creates a Subject together with its Dependent Subjects', async () => {
			const http = new RoutedHttpClient( { [ `POST ${ REST }/neowiki/v0/subjects` ]: [ 201, {} ] } );
			const dependentSubjects = [ { id: DEPENDENT, schema: 'Birth', statements: {} } ];

			await repository( http ).createSubject( HOST, subjectEntry( { schema: 'Person', dependentSubjects } ), null, 'Install' );

			expect( http.sent[ 0 ].body?.dependentSubjects ).toEqual( dependentSubjects );
		} );

		it( 'replaces a Subject with only the fields a replace takes', async () => {
			const http = new RoutedHttpClient( { [ `PUT ${ REST }/neowiki/v0/subject/${ HOST }` ]: [ 200, {} ] } );

			await repository( http ).replaceSubject( HOST, subjectEntry( { pageTitle: 'Female' } ), 'Update' );

			expect( Object.keys( http.sent[ 0 ].body ?? {} ).sort() ).toEqual( [ 'comment', 'label', 'statements' ] );
		} );

	} );

	describe( 'getUnavailablePageTitles', () => {

		/**
		 * Answers a title query as the Action API does, refusing a GET whose URL is longer than a web server takes.
		 */
		function titleQueryApi( answer: ( titles: string[] ) => unknown ): ActionApi {
			const query = async ( parameters: Record<string, unknown> ): Promise<unknown> => answer( String( parameters.titles ).split( '|' ) );

			return stubActionApi( {
				get: async ( parameters ) => {
					if ( new URLSearchParams( parameters as Record<string, string> ).toString().length > 8000 ) {
						throw new Error( 'Request-URI Too Long' );
					}

					return query( parameters );
				},
				post: query,
			} );
		}

		it( 'finds the titles a new page cannot take', async () => {
			const api = titleQueryApi( () => ( { query: {
				normalized: [ { from: 'female', to: 'Female' } ],
				pages: [
					{ title: 'Female', missing: true },
					{ title: 'Male' },
					{ title: 'Bad[title', invalid: true },
				],
			} } ) );

			const unavailable = await repository( new RoutedHttpClient( {} ), api )
				.getUnavailablePageTitles( [ 'female', 'Male', 'Bad[title', 'A|B' ] );

			expect( [ ...unavailable ].sort() ).toEqual( [ 'A|B', 'Bad[title', 'Male' ] );
		} );

		it( 'asks about many long titles 50 at a time, in the request body', async () => {
			const titles = Array.from( { length: 120 }, ( _value, index ) => `Бронзовый наконечник стрелы раннего железного века ${ index }` );
			const api = titleQueryApi( ( asked ) => {
				if ( asked.length > 50 ) {
					throw new Error( 'Too many values supplied for parameter "titles"' );
				}

				return { query: { pages: asked.map( ( title ) => ( { title, missing: true } ) ) } };
			} );

			expect( [ ...await repository( new RoutedHttpClient( {} ), api ).getUnavailablePageTitles( titles ) ] ).toEqual( [] );
		} );

	} );

	it( 'reads a Layout as the Layout editor saves it, with the revision it was read from', async () => {
		const http = new RoutedHttpClient( { [ `GET ${ REST }/v1/page/Layout:Card` ]: [ 200, {
			source: JSON.stringify( { schema: 'Person', type: 'infobox', description: '', settings: {}, displayRules: [ { property: 'Name' } ] } ),
			latest: { id: 12 },
		} ] } );

		expect( await repository( http ).getLayout( 'Card' ) ).toEqual( {
			json: { schema: 'Person', type: 'infobox', displayRules: [ { property: 'Name' } ] },
			revisionId: 12,
		} );
	} );

	it( 'pages through the Layouts', async () => {
		const http = new RoutedHttpClient( {
			[ `GET ${ REST }/neowiki/v0/layouts?limit=50` ]: [ 200, { layouts: [ { name: 'A', schema: 'Person', type: 'infobox' } ], nextCursor: 'c1' } ],
			[ `GET ${ REST }/neowiki/v0/layouts?limit=50&cursor=c1` ]: [ 200, { layouts: [ { name: 'B', schema: 'Place', type: 'infobox' } ], nextCursor: null } ],
		} );

		expect( await repository( http ).getLayoutSummaries() ).toEqual( [ { name: 'A', schema: 'Person' }, { name: 'B', schema: 'Place' } ] );
	} );

} );
