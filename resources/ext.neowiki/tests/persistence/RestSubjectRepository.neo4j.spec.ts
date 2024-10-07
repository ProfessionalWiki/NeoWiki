import { describe, expect, it } from 'vitest';
import { RestSubjectRepository } from '@/persistence/RestSubjectRepository';
import { SubjectId } from '@neo/domain/SubjectId';
import { Subject } from '@neo/domain/Subject';
import { PageIdentifiers } from '@neo/domain/PageIdentifiers';
import { StatementList } from '@neo/domain/StatementList';
import { Statement } from '@neo/domain/Statement';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { newStringValue } from '@neo/domain/Value';
import { TextFormat } from '@neo/domain/valueFormats/Text';
import { InMemoryHttpClient } from '@/infrastructure/HttpClient/InMemoryHttpClient';
import { UrlFormat } from '@neo/domain/valueFormats/Url';
import { NeoWikiExtension } from '@/NeoWikiExtension';

function newRepository( apiUrl: string, httpClient: InMemoryHttpClient ): RestSubjectRepository {
	return new RestSubjectRepository(
		apiUrl,
		httpClient,
		NeoWikiExtension.getInstance().getSubjectDeserializer()
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
			format: TextFormat.formatName
		},
		WorkUrl: {
			value: 'https://pro.wiki',
			format: UrlFormat.formatName
		}
	}
};

const mockResponse = {
	requestedId: 's33333333333333',
	subjects: {
		s33333333333333: subjectResponse
	}
};

describe( 'RestSubjectRepository', () => {

	describe( 'getSubject', () => {

		it( 'throws error when the API call fails', async () => {
			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/subject/s11111111111111?expand=page|relations':
					new Response( JSON.stringify( { httpCode: 404, httpReason: 'Not Found' } ), { status: 404 } )
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			try {
				await repository.getSubject( new SubjectId( 's11111111111111' ) );
			} catch ( error ) {
				expect( error ).toEqual( new Error( 'Error fetching subject' ) );
			}
		} );

		it( 'returns existing subject', async () => {
			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/subject/s11111111111111?expand=page|relations':
					new Response( JSON.stringify( mockResponse ), { status: 200 } )
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			const subject = await repository.getSubject( new SubjectId( 's11111111111111' ) );

			expect( subject ).toEqual( new Subject(
				new SubjectId( subjectResponse.id ),
				subjectResponse.label,
				subjectResponse.schema,
				new StatementList( [
					new Statement( new PropertyName( 'label' ), 'text', newStringValue( 'John Doe' ) ),
					new Statement( new PropertyName( 'WorkUrl' ), 'url', newStringValue( 'https://pro.wiki' ) )
				] ),
				new PageIdentifiers( subjectResponse.pageId, subjectResponse.pageTitle )
			) );
			expect( subject.getLabel() ).toEqual( 'John Doe' );
		} );

		it( 'throws an error when getSubject is called with a missing subject', async () => {
			const ID = 's22222222222222';
			const url = `https://example.com/rest.php/neowiki/v0/subject/${ ID }?expand=page|relations`;
			const inMemoryHttpClient = new InMemoryHttpClient( {
				url: new Response( JSON.stringify( {} ), { status: 200 } )
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			expect( repository.getSubject( new SubjectId( ID ) ) )
				.rejects.toThrow( 'No response found for URL: ' + url );
		} );

	} );

	describe( 'createMainSubject', () => {

		it( 'throws error when the API call fails', async () => {
			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/page/42/mainSubject':
					new Response( JSON.stringify( { httpCode: 404, httpReason: 'Not Found' } ), { status: 404 } )
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			await expect(
				() => repository.createMainSubject( 42, 'Foo', 'Bar', new StatementList( [] ) )
			).rejects.toThrowError( 'Error creating main subject' );
		} );

		it( 'creates new main subject', async () => {
			const mockSubjectResponse = {
				subject: {
					id: 's33333333333333',
					label: 'John Doe',
					schema: 'Employee',
					properties: new StatementList( [
						new Statement( new PropertyName( 'label' ), TextFormat.formatName, newStringValue( 'John Doe' ) ),
						new Statement( new PropertyName( 'WorkUrl' ), UrlFormat.formatName, newStringValue( 'https://pro.wiki' ) )
					] )
				}
			};

			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/page/42/mainSubject':
					new Response( JSON.stringify( { subjectId: 's33333333333333' } ), { status: 200 } )
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			const subjectId = await repository.createMainSubject(
				42,
				mockSubjectResponse.subject.label,
				mockSubjectResponse.subject.schema,
				mockSubjectResponse.subject.properties
			);

			expect( subjectId.text ).toEqual( mockSubjectResponse.subject.id );

			// TODO: check that it is actually the main subject?
		} );

	} );

	describe( 'updateSubject', () => {

		it( 'throws error when the API call fails', async () => {
			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/subject/s11111111111111':
					new Response( JSON.stringify( { httpCode: 404, httpReason: 'Not Found' } ), { status: 404 } )
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			await expect(
				() => repository.updateSubject( new SubjectId( 's11111111111111' ), new StatementList( [] ) )
			).rejects.toThrowError( 'Error updating subject' );
		} );

		it( 'returns original request', async () => {
			const mockUpdateResponse = {
				properties: new StatementList( [
					new Statement( new PropertyName( 'label' ), TextFormat.formatName, newStringValue( 'John Doe' ) ),
					new Statement( new PropertyName( 'WorkUrl' ), UrlFormat.formatName, newStringValue( 'https://pro.wiki' ) )
				] )
			};

			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/subject/s11111111111111':
					new Response( JSON.stringify( mockUpdateResponse ), { status: 200 } )
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			const response = await repository.updateSubject(
				new SubjectId( 's11111111111111' ),
				mockUpdateResponse.properties
			);

			expect( response ).toEqual( mockUpdateResponse );
		} );

	} );

	describe( 'deleteSubject', () => {

		it( 'throws error when the API call fails', async () => {
			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/subject/s11111111111111':
					new Response( JSON.stringify( { httpCode: 404, httpReason: 'Not Found' } ), { status: 404 } )
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			await expect(
				() => repository.deleteSubject( new SubjectId( 's11111111111111' ) )
			).rejects.toThrowError( 'Error deleting subject' );
		} );

		it( 'deletes the subject', async () => {
			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/subject/s11111111111111':
					new Response( JSON.stringify( {} ), { status: 200 } )
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			const response = await repository.deleteSubject(
				new SubjectId( 's11111111111111' )
			);

			expect( response ).toEqual( true );
		} );

	} );

	describe( 'createChildSubject', () => {

		it( 'throws error when the API call fails', async () => {
			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/page/42/childSubjects':
					new Response( JSON.stringify( { httpCode: 404, httpReason: 'Not Found' } ), { status: 404 } )
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			await expect(
				() => repository.createChildSubject( 42, 'Foo', 'Bar', new StatementList( [] ) )
			).rejects.toThrowError( 'Error creating child subject' );
		} );

		it( 'creates new child subject', async () => {
			const mockSubjectResponse = {
				subject: {
					id: 's33333333333333',
					label: 'John Doe',
					schema: 'Employee',
					properties: new StatementList( [
						new Statement( new PropertyName( 'label' ), TextFormat.formatName, newStringValue( 'John Doe' ) ),
						new Statement( new PropertyName( 'WorkUrl' ), UrlFormat.formatName, newStringValue( 'https://pro.wiki' ) )
					] )
				}
			};

			const inMemoryHttpClient = new InMemoryHttpClient( {
				'https://example.com/rest.php/neowiki/v0/page/42/childSubjects':
					new Response( JSON.stringify( { subjectId: 's33333333333333' } ), { status: 200 } )
			} );

			const repository = newRepository( 'https://example.com/rest.php', inMemoryHttpClient );

			const subjectId = await repository.createChildSubject(
				42,
				mockSubjectResponse.subject.label,
				mockSubjectResponse.subject.schema,
				mockSubjectResponse.subject.properties
			);

			expect( subjectId.text ).toEqual( mockSubjectResponse.subject.id );

			// TODO: check that it is not the main subject?
		} );

	} );

} );
