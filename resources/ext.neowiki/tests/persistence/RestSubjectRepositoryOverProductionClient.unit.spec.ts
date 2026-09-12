import { describe, expect, it } from 'vitest';
import { RestSubjectRepository } from '@/persistence/RestSubjectRepository';
import { ProductionHttpClient } from '@/infrastructure/HttpClient/ProductionHttpClient';
import { SubjectId } from '@/domain/SubjectId';
import { StatementList } from '@/domain/StatementList';
import { PageTitleTakenError } from '@/persistence/PageTitleTakenError';
import { SubjectIdInUseError } from '@/persistence/SubjectIdInUseError';
import type { SubjectDeserializer } from '@/persistence/SubjectDeserializer';
import type { SchemaDeserializer } from '@/persistence/SchemaDeserializer';

/**
 * What a refused write looks like to the caller with the client production uses, rather than with a
 * double that hands every status back as a readable Response. Which statuses that client resolves
 * decides whether the body reaches the caller at all, and the errors below are built from it.
 *
 * The adapter stands in for the network: axios leaves applying validateStatus to the adapter, so
 * this one applies the client's own, and rejects the way axios does when it says no.
 */
function repositoryAnswering( status: number, body: unknown ): RestSubjectRepository {
	const httpClient = new ProductionHttpClient();

	( httpClient.getAxiosInstance() as any ).defaults.adapter = ( config: any ): Promise<unknown> => {
		const response = { data: body, status, statusText: '', headers: {}, config };

		if ( config.validateStatus( status ) ) {
			return Promise.resolve( response );
		}

		return Promise.reject( Object.assign(
			new Error( `Request failed with status code ${ status }` ),
			{ response, config, isAxiosError: true },
		) );
	};

	return new RestSubjectRepository(
		'/rest.php',
		httpClient,
		{} as SubjectDeserializer,
		{} as SchemaDeserializer,
	);
}

describe( 'RestSubjectRepository over the production HTTP client', () => {

	it( 'names the page in the way when a title is taken', async () => {
		const repository = repositoryAnswering( 409, {
			status: 'error',
			message: 'A page named "John Doe" already exists',
			pageTitle: 'John Doe',
		} );

		const error = await repository
			.createSubjectPage( 'John Doe', 'Employee', new StatementList( [] ) )
			.catch( ( thrown: unknown ) => thrown );

		expect( error ).toBeInstanceOf( PageTitleTakenError );
		expect( ( error as PageTitleTakenError ).pageTitle ).toBe( 'John Doe' );
	} );

	it( 'names the id in the way when a minted Subject id is taken', async () => {
		const repository = repositoryAnswering( 409, {
			status: 'error',
			message: 'A subject with this ID already exists',
		} );

		const error = await repository.createChildSubject(
			42,
			'John Doe',
			'Employee',
			new StatementList( [] ),
			undefined,
			new SubjectId( 's44444444444444' ),
		).catch( ( thrown: unknown ) => thrown );

		expect( error ).toBeInstanceOf( SubjectIdInUseError );
		expect( ( error as SubjectIdInUseError ).subjectId ).toBe( 's44444444444444' );
	} );

	it( 'carries the reason a write that failed gave', async () => {
		const repository = repositoryAnswering( 500, {
			status: 'error',
			message: 'The page "John Doe" could not be created: the database is locked',
		} );

		await expect(
			repository.createSubjectPage( 'John Doe', 'Employee', new StatementList( [] ) ),
		).rejects.toThrowError( 'the database is locked' );
	} );

} );
