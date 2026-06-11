import type { SubjectRepository } from '@/domain/SubjectRepository';
import { SubjectId } from '@/domain/SubjectId';
import type { SubjectDeserializer } from '@/persistence/SubjectDeserializer';
import {
	PageSubjectsDeserializer,
	type DeserializedPageSubjects,
	type PageSubjectsJson,
} from '@/persistence/PageSubjectsDeserializer';
import { StatementList, statementsToJson } from '@/domain/StatementList';
import { type SchemaName } from '@/domain/Schema';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';
import type { Subject } from '@/domain/Subject';
import type { SubjectViolation } from '@/domain/SubjectViolation';
import { ValidationFailedError } from '@/persistence/ValidationFailedError';
import { parseViolations } from '@/persistence/violationParsing';

async function throwOn422IfPossible( response: Response ): Promise<void> {
	if ( response.status !== 422 ) {
		return;
	}
	let body: unknown;
	try {
		body = await response.clone().json();
	} catch {
		// Body wasn't valid JSON — fall through to the generic-error path.
		return;
	}
	const violations = parseViolations( body );
	if ( violations === null ) {
		console.error(
			'RestSubjectRepository: malformed 422 body, falling through to generic error',
			body,
		);
		return;
	}
	throw new ValidationFailedError( violations );
}

export type SubjectJson = {
	id: string;
	label: string;
	statements: Record<string, unknown>;
	schema: string;
	pageId: number;
	pageTitle: string;
	requestedId: string;
	value?: unknown;
};

export class RestSubjectRepository implements SubjectRepository {

	public constructor(
		private readonly mediaWikiRestApiUrl: string,
		private readonly httpClient: HttpClient,
		private readonly subjectDeserializer: SubjectDeserializer,
		private readonly revisionId?: number,
	) {
	}

	public async getPageSubjects( pageId: number ): Promise<DeserializedPageSubjects> {
		const response = await this.httpClient.get(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/page/${ pageId }/subjects?expand=schemas%7Crelations`,
		);

		if ( !response.ok ) {
			throw new Error( 'Error fetching page subjects' );
		}

		const data = await response.json() as PageSubjectsJson;
		return new PageSubjectsDeserializer( this.subjectDeserializer ).deserialize( data );
	}

	public async setMainSubject( pageId: number, subjectId: SubjectId | null, comment?: string ): Promise<void> {
		const response = await this.httpClient.put(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/page/${ pageId }/mainSubject`,
			{
				subjectId: subjectId === null ? null : subjectId.text,
				comment,
			},
			{
				headers: {
					'Content-Type': 'application/json',
				},
			},
		);

		if ( !response.ok ) {
			throw new Error( 'Error setting main subject' );
		}
	}

	public async setSubjectsOrdering(
		pageId: number,
		mainSubjectId: SubjectId | null,
		childSubjectIds: SubjectId[],
		comment?: string,
	): Promise<void> {
		const response = await this.httpClient.put(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/page/${ pageId }/subjectsOrdering`,
			{
				mainSubjectId: mainSubjectId === null ? null : mainSubjectId.text,
				childSubjectIds: childSubjectIds.map( ( id ) => id.text ),
				comment,
			},
			{
				headers: {
					'Content-Type': 'application/json',
				},
			},
		);

		if ( !response.ok ) {
			throw new Error( 'Error setting subjects ordering' );
		}
	}

	public async getSubject( id: SubjectId ): Promise<Subject> {
		let url = `${ this.mediaWikiRestApiUrl }/neowiki/v0/subject/${ id.text }?expand=page|relations`;

		if ( this.revisionId !== undefined ) {
			url += `&revisionId=${ this.revisionId }`;
		}

		const response = await this.httpClient.get( url );

		if ( !response.ok ) {
			throw new Error( 'Error fetching subject' );
		}

		const data = await response.json() as { requestedId?: string; subjects?: Record<string, SubjectJson> };

		if ( !data.requestedId || !data.subjects || !data.subjects[ data.requestedId ] ) {
			throw new Error( 'Subject not found' );
		}

		const subjectData = data.subjects[ data.requestedId ];

		return this.subjectDeserializer.deserialize( subjectData );
	}

	public async createMainSubject(
		pageId: number,
		label: string,
		schemaName: SchemaName,
		statements: StatementList,
		comment?: string,
	): Promise<SubjectId> {
		const payload = {
			label: label,
			schema: schemaName,
			statements: statementsToJson( statements ),
			comment,
		};

		const response = await this.httpClient.post(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/page/${ pageId }/mainSubject`,
			payload,
			{
				headers: {
					'Content-Type': 'application/json',
				},
			},
		);

		await throwOn422IfPossible( response );

		if ( !response.ok ) {
			throw new Error( 'Error creating main subject' );
		}

		const data = await response.json();
		return new SubjectId( data.subjectId );
	}

	public async createChildSubject(
		pageId: number,
		label: string,
		schemaName: SchemaName,
		statements: StatementList,
		comment?: string,
	): Promise<SubjectId> {
		const payload = {
			label: label,
			schema: schemaName,
			statements: statementsToJson( statements ),
			comment,
		};

		const response = await this.httpClient.post(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/page/${ pageId }/childSubjects`,
			payload,
			{
				headers: {
					'Content-Type': 'application/json',
				},
			},
		);

		await throwOn422IfPossible( response );

		if ( !response.ok ) {
			throw new Error( 'Error creating child subject' );
		}

		const data = await response.json();
		return new SubjectId( data.subjectId );
	}

	public async updateSubject( id: SubjectId, label: string, statements: StatementList, comment?: string ): Promise<object> {
		const response = await this.httpClient.put(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/subject/${ id.text }`,
			{
				label,
				statements: statementsToJson( statements ),
				comment,
			},
			{
				headers: {
					'Content-Type': 'application/json',
				},
			},
		);

		await throwOn422IfPossible( response );

		if ( !response.ok ) {
			throw new Error( 'Error updating subject' );
		}

		return await response.json();
	}

	public async deleteSubject( id: SubjectId, comment?: string ): Promise<boolean> {
		const response = await this.httpClient.delete(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/subject/${ id.text }`,
			{
				headers: {
					'Content-Type': 'application/json',
				},
				data: { comment },
			},
		);

		if ( !response.ok ) {
			throw new Error( 'Error deleting subject' );
		}

		return true;
	}

	public async validateSubject(
		label: string,
		schemaName: SchemaName,
		statements: StatementList,
	): Promise<SubjectViolation[]> {
		return this.runValidation(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/subject/validate`,
			{ schema: schemaName, label, statements: statementsToJson( statements ) },
		);
	}

	public async validateSubjectUpdate(
		id: SubjectId,
		label: string,
		statements: StatementList,
	): Promise<SubjectViolation[]> {
		return this.runValidation(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/subject/${ id.text }/validate`,
			{ label, statements: statementsToJson( statements ) },
		);
	}

	private async runValidation( url: string, payload: Record<string, unknown> ): Promise<SubjectViolation[]> {
		let response: Response;
		try {
			response = await this.httpClient.post( url, payload, { headers: { 'Content-Type': 'application/json' } } );
		} catch {
			// 400/404 (malformed input / missing schema) reject in ProductionHttpClient — no live feedback, fall back silently.
			return [];
		}

		if ( !response.ok ) {
			return [];
		}

		const violations = parseViolations( await response.json() );
		return violations ?? [];
	}

}
