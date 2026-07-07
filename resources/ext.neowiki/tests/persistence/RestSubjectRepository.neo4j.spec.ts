import { describe, expect, it, vi } from 'vitest';
import { RestSubjectRepository } from '@/persistence/RestSubjectRepository';
import { SubjectId } from '@/domain/SubjectId';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import { StatementList } from '@/domain/StatementList';
import { Statement } from '@/domain/Statement';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue } from '@/domain/Value';
import { TextType } from '@/domain/propertyTypes/Text';
import { RelationType } from '@/domain/propertyTypes/Relation';
import { InMemoryHttpClient } from '@/infrastructure/HttpClient/InMemoryHttpClient';
import { UrlType } from '@/domain/propertyTypes/Url';
import { NeoWikiExtension } from '@/NeoWikiExtension';
import { SubjectWithContext } from '@/domain/SubjectWithContext.ts';
import { ValidationFailedError } from '@/persistence/ValidationFailedError';

function newRepository( apiUrl: string, httpClient: InMemoryHttpClient ): RestSubjectRepository {
	return new RestSubjectRepository(
		apiUrl,
		httpClient,
		NeoWikiExtension.getInstance().getSubjectDeserializer(),
	);
}

const subjectResponse = {
	id: 's33333333333333',
	label: 'John Doe',
	schema: 'Employee',
	pageId: 42,
	pageTitle: 'John Doe (Employee)',
	statements: {
		label: {
			value: 'John Doe',
			type: TextType.typeName,
		},
		WorkUrl: {
			value: 'https://pro.wiki',
			type: UrlType.typeName,
		},
	},
};

const mockResponse = {
	requestedId: 's33333333333333',
	subjects: {
		s33333333333333: subjectResponse,
	},
};

describe( 'RestSubjectRepository', () => {

	describe( 'getSubject', () => {

		it( 'throws error when the API call fails', async () => {
			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/subject/s11111111111111?expand=page|relations':
					new Response( JSON.stringify( { httpCode: 404, httpReason: 'Not Found' } ), { status: 404 } ),
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			try {
				await repository.getSubject( new SubjectId( 's11111111111111' ) );
			} catch ( error ) {
				expect( error ).toEqual( new Error( 'Error fetching subject' ) );
			}
		} );

		it( 'percent-encodes qualified subject ids in the request URL', async () => {
			const qualifiedId = 'enwiki:Q42';
			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/subject/enwiki%3AQ42?expand=page|relations':
					new Response( JSON.stringify( {
						requestedId: qualifiedId,
						subjects: {
							[ qualifiedId ]: { ...subjectResponse, id: qualifiedId },
						},
					} ), { status: 200 } ),
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			const subject = await repository.getSubject( new SubjectId( qualifiedId ) );

			expect( subject?.getId().text ).toBe( qualifiedId );
		} );

		it( 'returns existing subject', async () => {
			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/subject/s11111111111111?expand=page|relations':
					new Response( JSON.stringify( mockResponse ), { status: 200 } ),
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			const subject = await repository.getSubject( new SubjectId( 's11111111111111' ) );

			expect( subject ).toEqual( new SubjectWithContext(
				new SubjectId( subjectResponse.id ),
				subjectResponse.label,
				subjectResponse.schema,
				new StatementList( [
					new Statement( new PropertyName( 'label' ), 'text', newStringValue( 'John Doe' ) ),
					new Statement( new PropertyName( 'WorkUrl' ), 'url', newStringValue( 'https://pro.wiki' ) ),
				] ),
				new PageIdentifiers( subjectResponse.pageId, subjectResponse.pageTitle ),
			) );
			expect( subject.getLabel() ).toEqual( 'John Doe' );
		} );

		it( 'throws an error when getSubject is called with a missing subject', async () => {
			const ID = 's22222222222222';
			const url = `https://example.com/rest.php/neowiki/v0/subject/${ ID }?expand=page|relations`;
			const inMemoryHttpClient = new InMemoryHttpClient( {
				url: new Response( JSON.stringify( {} ), { status: 200 } ),
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			await expect( repository.getSubject( new SubjectId( ID ) ) )
				.rejects.toThrow( 'No response found for URL: ' + url );
		} );

	} );

	describe( 'getSubjectWithReferencedSubjects', () => {

		const requestedId = 's11111111111111';
		const referencedId1 = 's22222222222222';
		const referencedId2 = 's33333333333333';

		const bundleResponse = {
			requestedId: requestedId,
			subjects: {
				[ referencedId1 ]: {
					id: referencedId1,
					label: 'Product One',
					schema: 'Product',
					pageId: 2,
					pageTitle: 'Product One',
					statements: {},
				},
				[ requestedId ]: {
					id: requestedId,
					label: 'Main',
					schema: 'Company',
					pageId: 1,
					pageTitle: 'Main',
					statements: {
						Products: {
							value: [ { target: referencedId1 }, { target: referencedId2 } ],
							type: RelationType.typeName,
						},
					},
				},
				[ referencedId2 ]: {
					id: referencedId2,
					label: 'Product Two',
					schema: 'Product',
					pageId: 3,
					pageTitle: 'Product Two',
					statements: {},
				},
			},
		};

		function repositoryReturning( response: object ): RestSubjectRepository {
			return newRepository( 'https://example.com/rest.php', new InMemoryHttpClient( {
				[ `https://example.com/rest.php/neowiki/v0/subject/${ requestedId }?expand=page|relations` ]:
					new Response( JSON.stringify( response ), { status: 200 } ),
			} ) );
		}

		it( 'returns the requested Subject together with every bundled referenced Subject', async () => {
			const repository = repositoryReturning( bundleResponse );

			const { requestedSubject, referencedSubjects } =
				await repository.getSubjectWithReferencedSubjects( new SubjectId( requestedId ) );

			expect( requestedSubject.getLabel() ).toBe( 'Main' );
			expect( referencedSubjects.map( ( subject ) => subject.getLabel() ) ).toEqual(
				[ 'Product One', 'Product Two' ],
			);
		} );

		it( 'deserializes referenced Subjects with their page context', async () => {
			const repository = repositoryReturning( bundleResponse );

			const { referencedSubjects } =
				await repository.getSubjectWithReferencedSubjects( new SubjectId( requestedId ) );

			const referenced = referencedSubjects[ 0 ] as SubjectWithContext;
			expect( referenced.getPageIdentifiers().getPageName() ).toBe( 'Product One' );
		} );

		it( 'issues a single request for the whole bundle', async () => {
			const inMemoryHttpClient = new InMemoryHttpClient( {
				[ `https://example.com/rest.php/neowiki/v0/subject/${ requestedId }?expand=page|relations` ]:
					new Response( JSON.stringify( bundleResponse ), { status: 200 } ),
			} );
			const getSpy = vi.spyOn( inMemoryHttpClient, 'get' );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			await repository.getSubjectWithReferencedSubjects( new SubjectId( requestedId ) );

			expect( getSpy ).toHaveBeenCalledOnce();
		} );

		it( 'throws when the requested Subject is missing from the response', async () => {
			const repository = repositoryReturning( {} );

			await expect( repository.getSubjectWithReferencedSubjects( new SubjectId( requestedId ) ) )
				.rejects.toThrow( 'Subject not found' );
		} );

		function subjectWithUnregisteredPropertyType( id: string ): object {
			return {
				id: id,
				label: 'Broken',
				schema: 'Product',
				pageId: 4,
				pageTitle: 'Broken',
				statements: {
					Mystery: {
						value: 'x',
						type: 'unregistered-property-type',
					},
				},
			};
		}

		function undeserializableSubject( id: string ): object {
			return {
				id: id,
				label: 'Broken',
				schema: 'Product',
				pageId: 4,
				pageTitle: 'Broken',
				statements: {
					// Missing the required `type`, so the statement cannot be deserialized.
					Mystery: {
						value: 'x',
					},
				},
			};
		}

		it( 'includes referenced Subjects that use an unregistered property type instead of skipping them', async () => {
			const repository = repositoryReturning( {
				requestedId: requestedId,
				subjects: {
					[ referencedId1 ]: subjectWithUnregisteredPropertyType( referencedId1 ),
					[ requestedId ]: bundleResponse.subjects[ requestedId ],
					[ referencedId2 ]: bundleResponse.subjects[ referencedId2 ],
				},
			} );

			const { referencedSubjects } =
				await repository.getSubjectWithReferencedSubjects( new SubjectId( requestedId ) );

			expect( referencedSubjects.map( ( subject ) => subject.getId().text ) )
				.toEqual( [ referencedId1, referencedId2 ] );
		} );

		it( 'skips referenced Subjects that fail to deserialize', async () => {
			const repository = repositoryReturning( {
				requestedId: requestedId,
				subjects: {
					[ referencedId1 ]: undeserializableSubject( referencedId1 ),
					[ requestedId ]: bundleResponse.subjects[ requestedId ],
					[ referencedId2 ]: bundleResponse.subjects[ referencedId2 ],
				},
			} );

			const { requestedSubject, referencedSubjects } =
				await repository.getSubjectWithReferencedSubjects( new SubjectId( requestedId ) );

			expect( requestedSubject.getLabel() ).toBe( 'Main' );
			expect( referencedSubjects.map( ( subject ) => subject.getId().text ) ).toEqual( [ referencedId2 ] );
		} );

		it( 'throws when the requested Subject fails to deserialize', async () => {
			const repository = repositoryReturning( {
				requestedId: requestedId,
				subjects: {
					[ requestedId ]: undeserializableSubject( requestedId ),
				},
			} );

			await expect( repository.getSubjectWithReferencedSubjects( new SubjectId( requestedId ) ) )
				.rejects.toThrow( 'Invalid statement JSON' );
		} );

	} );

	describe( 'createMainSubject', () => {

		it( 'throws error when the API call fails', async () => {
			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/page/42/mainSubject':
					new Response( JSON.stringify( { httpCode: 404, httpReason: 'Not Found' } ), { status: 404 } ),
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			await expect(
				() => repository.createMainSubject( 42, 'Foo', 'Bar', new StatementList( [] ) ),
			).rejects.toThrowError( 'Error creating main subject' );
		} );

		it( 'creates new main subject', async () => {
			const mockSubjectResponse = {
				subject: {
					id: 's33333333333333',
					label: 'John Doe',
					schema: 'Employee',
					properties: new StatementList( [
						new Statement( new PropertyName( 'label' ), TextType.typeName, newStringValue( 'John Doe' ) ),
						new Statement( new PropertyName( 'WorkUrl' ), UrlType.typeName, newStringValue( 'https://pro.wiki' ) ),
					] ),
				},
			};

			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/page/42/mainSubject':
					new Response( JSON.stringify( { subjectId: 's33333333333333' } ), { status: 200 } ),
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			const subjectId = await repository.createMainSubject(
				42,
				mockSubjectResponse.subject.label,
				mockSubjectResponse.subject.schema,
				mockSubjectResponse.subject.properties,
			);

			expect( subjectId.text ).toEqual( mockSubjectResponse.subject.id );

			// TODO: check that it is actually the main subject?
		} );

	} );

	describe( 'updateSubject', () => {

		it( 'throws error when the API call fails', async () => {
			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/subject/s11111111111111':
					new Response( JSON.stringify( { httpCode: 404, httpReason: 'Not Found' } ), { status: 404 } ),
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			await expect(
				() => repository.updateSubject( new SubjectId( 's11111111111111' ), 'Updated Label', new StatementList( [] ) ),
			).rejects.toThrowError( 'Error updating subject' );
		} );

		it( 'returns original request', async () => {
			const mockUpdateResponse = {
				properties: new StatementList( [
					new Statement( new PropertyName( 'label' ), TextType.typeName, newStringValue( 'John Doe' ) ),
					new Statement( new PropertyName( 'WorkUrl' ), UrlType.typeName, newStringValue( 'https://pro.wiki' ) ),
				] ),
				comment: 'Edit comment',
			};

			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/subject/s11111111111111':
					new Response( JSON.stringify( mockUpdateResponse ), { status: 200 } ),
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			const response = await repository.updateSubject(
				new SubjectId( 's11111111111111' ),
				'Subject label',
				mockUpdateResponse.properties,
				mockUpdateResponse.comment,
			);

			expect( response ).toEqual( mockUpdateResponse );
		} );

		it( 'sends a PUT request', async () => {
			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/subject/s11111111111111':
					new Response( JSON.stringify( {} ), { status: 200 } ),
			} );
			const putSpy = vi.spyOn( inMemoryHttpClient, 'put' );
			const patchSpy = vi.spyOn( inMemoryHttpClient, 'patch' );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			await repository.updateSubject(
				new SubjectId( 's11111111111111' ),
				'Subject label',
				new StatementList( [] ),
			);

			expect( putSpy ).toHaveBeenCalledOnce();
			expect( patchSpy ).not.toHaveBeenCalled();
		} );

	} );

	describe( 'deleteSubject', () => {

		it( 'throws error when the API call fails', async () => {
			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/subject/s11111111111111':
					new Response( JSON.stringify( { httpCode: 404, httpReason: 'Not Found' } ), { status: 404 } ),
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			await expect(
				() => repository.deleteSubject( new SubjectId( 's11111111111111' ) ),
			).rejects.toThrowError( 'Error deleting subject' );
		} );

		it( 'deletes the subject', async () => {
			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/subject/s11111111111111':
					new Response( JSON.stringify( {} ), { status: 200 } ),
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			const response = await repository.deleteSubject(
				new SubjectId( 's11111111111111' ),
			);

			expect( response ).toEqual( true );
		} );

	} );

	describe( 'createChildSubject', () => {

		it( 'throws error when the API call fails', async () => {
			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/page/42/childSubjects':
					new Response( JSON.stringify( { httpCode: 404, httpReason: 'Not Found' } ), { status: 404 } ),
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			await expect(
				() => repository.createChildSubject( 42, 'Foo', 'Bar', new StatementList( [] ) ),
			).rejects.toThrowError( 'Error creating child subject' );
		} );

		it( 'creates new child subject', async () => {
			const mockSubjectResponse = {
				subject: {
					id: 's33333333333333',
					label: 'John Doe',
					schema: 'Employee',
					properties: new StatementList( [
						new Statement( new PropertyName( 'label' ), TextType.typeName, newStringValue( 'John Doe' ) ),
						new Statement( new PropertyName( 'WorkUrl' ), UrlType.typeName, newStringValue( 'https://pro.wiki' ) ),
					] ),
				},
			};

			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/page/42/childSubjects':
					new Response( JSON.stringify( { subjectId: 's33333333333333' } ), { status: 200 } ),
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			const subjectId = await repository.createChildSubject(
				42,
				mockSubjectResponse.subject.label,
				mockSubjectResponse.subject.schema,
				mockSubjectResponse.subject.properties,
			);

			expect( subjectId.text ).toEqual( mockSubjectResponse.subject.id );

			// TODO: check that it is not the main subject?
		} );

	} );

	describe( 'updateSubject 422 handling', () => {

		function make422Response( body: unknown ): Response {
			return new Response( JSON.stringify( body ), {
				status: 422,
				headers: { 'Content-Type': 'application/json' },
			} );
		}

		function make500Response( body: unknown = {} ): Response {
			return new Response( JSON.stringify( body ), { status: 500 } );
		}

		const subjectUrl = 'https://example.com/rest.php/neowiki/v0/subject/s11111111111111';

		function repoWith( response: Response ): RestSubjectRepository {
			return newRepository( 'https://example.com/rest.php', new InMemoryHttpClient( {
				[ subjectUrl ]: response,
			} ) );
		}

		it( 'throws ValidationFailedError with parsed violations on well-formed 422', async () => {
			const body = {
				status: 'error',
				message: 'Validation failed',
				violations: [
					{ propertyName: 'Status', code: 'required', args: [], valuePartIndex: null },
					{ propertyName: 'Website', code: 'invalid-url', args: [], valuePartIndex: 1 },
					{ propertyName: null, code: 'schema-not-found', args: [ 'Person' ], valuePartIndex: null },
				],
			};

			const promise = repoWith( make422Response( body ) )
				.updateSubject( new SubjectId( 's11111111111111' ), 'Label', new StatementList( [] ) );

			const error = await promise.catch( ( e ) => e );
			expect( error ).toBeInstanceOf( ValidationFailedError );
			expect( ( error as ValidationFailedError ).violations ).toEqual( [
				{ propertyName: 'Status', code: 'required', args: [], valuePartIndex: null },
				{ propertyName: 'Website', code: 'invalid-url', args: [], valuePartIndex: 1 },
				{ propertyName: null, code: 'schema-not-found', args: [ 'Person' ], valuePartIndex: null },
			] );
		} );

		it( 'throws generic Error (not ValidationFailedError) when 422 body has no violations field', async () => {
			const promise = repoWith( make422Response( { status: 'error', message: 'Validation failed' } ) )
				.updateSubject( new SubjectId( 's11111111111111' ), 'Label', new StatementList( [] ) );

			await expect( promise ).rejects.toSatisfy(
				( err ) => err instanceof Error && !( err instanceof ValidationFailedError ),
			);
			await expect( promise ).rejects.toThrowError( 'Error updating subject' );
		} );

		it( 'throws generic Error when 422 body has violations as a non-array', async () => {
			const promise = repoWith( make422Response( { violations: { propertyName: 'Foo', code: 'required' } } ) )
				.updateSubject( new SubjectId( 's11111111111111' ), 'Label', new StatementList( [] ) );

			await expect( promise ).rejects.toSatisfy(
				( err ) => err instanceof Error && !( err instanceof ValidationFailedError ),
			);
		} );

		it( 'throws generic Error when a violation entry has a non-string code', async () => {
			const promise = repoWith( make422Response( {
				violations: [ { propertyName: 'Foo', code: 123, args: [], valuePartIndex: null } ],
			} ) ).updateSubject( new SubjectId( 's11111111111111' ), 'Label', new StatementList( [] ) );

			await expect( promise ).rejects.toSatisfy(
				( err ) => err instanceof Error && !( err instanceof ValidationFailedError ),
			);
		} );

		it( 'resolves normally on 200 (existing behaviour preserved)', async () => {
			const mockResponse = { label: 'Updated' };
			const response = await newRepository( 'https://example.com/rest.php', new InMemoryHttpClient( {
				[ subjectUrl ]: new Response( JSON.stringify( mockResponse ), { status: 200 } ),
			} ) ).updateSubject( new SubjectId( 's11111111111111' ), 'Updated', new StatementList( [] ) );

			expect( response ).toEqual( mockResponse );
		} );

		it( 'throws generic Error on 500', async () => {
			const promise = repoWith( make500Response() )
				.updateSubject( new SubjectId( 's11111111111111' ), 'Label', new StatementList( [] ) );

			await expect( promise ).rejects.toThrowError( 'Error updating subject' );
			await expect( promise ).rejects.toSatisfy(
				( err ) => !( err instanceof ValidationFailedError ),
			);
		} );

	} );

	describe( 'createMainSubject 422 handling', () => {

		it( 'throws ValidationFailedError on well-formed 422', async () => {
			const body = {
				violations: [
					{ propertyName: 'Status', code: 'required', args: [], valuePartIndex: null },
				],
			};
			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/page/42/mainSubject':
					new Response( JSON.stringify( body ), { status: 422, headers: { 'Content-Type': 'application/json' } } ),
			} );

			await expect(
				newRepository( 'https://example.com/rest.php', inMemoryHttpClient )
					.createMainSubject( 42, 'Label', 'Schema', new StatementList( [] ) ),
			).rejects.toBeInstanceOf( ValidationFailedError );
		} );

	} );

	describe( 'createChildSubject 422 handling', () => {

		it( 'throws ValidationFailedError on well-formed 422', async () => {
			const body = {
				violations: [
					{ propertyName: 'Status', code: 'required', args: [], valuePartIndex: null },
				],
			};
			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/page/42/childSubjects':
					new Response( JSON.stringify( body ), { status: 422, headers: { 'Content-Type': 'application/json' } } ),
			} );

			await expect(
				newRepository( 'https://example.com/rest.php', inMemoryHttpClient )
					.createChildSubject( 42, 'Label', 'Schema', new StatementList( [] ) ),
			).rejects.toBeInstanceOf( ValidationFailedError );
		} );

	} );

} );
