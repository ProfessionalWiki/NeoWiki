import { expect, type APIRequestContext } from '@playwright/test';
import { randomUUID } from 'node:crypto';

export function uniqueName( prefix: string ): string {
	return `${ prefix } ${ randomUUID().slice( 0, 8 ) }`;
}

export async function createSchema(
	request: APIRequestContext,
	name: string,
	propertyDefinitions: Record<string, object>
): Promise<void> {
	await savePage( request, `Schema:${ name }`, JSON.stringify( { propertyDefinitions } ) );
}

/**
 * @return The id of the Subject created.
 */
export async function createSubjectPage(
	request: APIRequestContext,
	subject: { pageTitle: string; label: string; schema: string; statements: Record<string, object> }
): Promise<string> {
	const response = await request.post( 'rest.php/neowiki/v0/subjects', {
		data: subject,
		headers: { 'X-CSRF-TOKEN': await csrfToken( request ) }
	} );

	await expect( response ).toBeOK();

	return ( await response.json() ).subjectId;
}

/**
 * Writes one Statement on the Subject as it is now, as another editor saving meanwhile does.
 */
export async function setStatement(
	request: APIRequestContext,
	subjectId: string,
	propertyName: string,
	statement: object
): Promise<void> {
	const read = await request.get( `rest.php/neowiki/v0/subject/${ subjectId }`, { params: { latest: '1' } } );
	await expect( read ).toBeOK();

	const response = await request.put(
		`rest.php/neowiki/v0/subject/${ subjectId }/statements/${ encodeURIComponent( propertyName ) }`,
		{
			data: { statement, baseRevisionId: ( await read.json() ).revisionId },
			headers: { 'X-CSRF-TOKEN': await csrfToken( request ) }
		}
	);

	await expect( response ).toBeOK();
}

async function savePage( request: APIRequestContext, title: string, text: string ): Promise<void> {
	const response = await request.post( 'api.php', {
		form: { action: 'edit', format: 'json', title, text, token: await csrfToken( request ) }
	} );

	expect( ( await response.json() ).edit?.result ).toBe( 'Success' );
}

async function csrfToken( request: APIRequestContext ): Promise<string> {
	const response = await request.get( 'api.php?action=query&meta=tokens&format=json' );
	return ( await response.json() ).query.tokens.csrftoken;
}

export async function pageJson( request: APIRequestContext, title: string ): Promise<unknown> {
	const response = await request.get( 'index.php', { params: { title, action: 'raw' } } );
	await expect( response ).toBeOK();
	return response.json();
}
