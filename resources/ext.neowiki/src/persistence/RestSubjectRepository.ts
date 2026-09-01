import type { SubjectRepository, SubjectWithReferencedSubjects, SubjectWriteResult } from '@/domain/SubjectRepository';
import { SubjectId } from '@/domain/SubjectId';
import type { SubjectDeserializer } from '@/persistence/SubjectDeserializer';
import {
	PageSubjectsDeserializer,
	type DeserializedPageSubjects,
	type PageSubjectsJson,
} from '@/persistence/PageSubjectsDeserializer';
import { StatementList, statementsToJson } from '@/domain/StatementList';
import { type SchemaName } from '@/domain/Schema';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer';
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
	/** The stored label, null for a Subject that has none. */
	label: string | null;
	/** The stored label, or the fallback name the server derived when there is none. */
	displayName: string;
	statements: Record<string, unknown>;
	schema: string;
	pageId: number;
	pageTitle: string;
	requestedId: string;
	value?: unknown;
};

type SubjectBundleJson = {
	requestedId: string;
	subjects: Record<string, SubjectJson>;
};

type SubjectWriteResponseJson = {
	subject: SubjectJson;
	schema?: Record<string, unknown>;
};

export class RestSubjectRepository implements SubjectRepository {

	public constructor(
		private readonly mediaWikiRestApiUrl: string,
		private readonly httpClient: HttpClient,
		private readonly subjectDeserializer: SubjectDeserializer,
		private readonly schemaDeserializer: SchemaDeserializer,
		private readonly revisionId?: number,
	) {
	}

	/**
	 * A write answers with the Subject as persisted and the Schema it instantiates. The Schema
	 * name is not repeated in the schema body — it is the Subject's own `schema` field.
	 *
	 * The page identifiers are omitted when the server could not resolve the Subject's page.
	 * Deserializing that would mint a Subject whose page id is undefined, which anything
	 * rendering a link to it would then follow; report no Subject instead, so the caller keeps
	 * the copy it already had.
	 */
	private deserializeWriteResult( json: SubjectWriteResponseJson ): SubjectWriteResult {
		const hasPageContext = json.subject.pageId !== undefined && json.subject.pageTitle !== undefined;

		return {
			subjectId: new SubjectId( json.subject.id ),
			subject: hasPageContext ? this.subjectDeserializer.deserialize( json.subject ) : null,
			schema: json.schema === undefined ?
				null :
				this.schemaDeserializer.deserialize( json.subject.schema, json.schema ),
		};
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
		const bundle = await this.fetchSubjectBundle( id );

		return this.subjectDeserializer.deserialize( bundle.subjects[ bundle.requestedId ] );
	}

	/**
	 * The `expand=relations` response bundles the requested Subject together with
	 * every Subject its relations target, so a View can resolve relation labels
	 * without re-fetching each target individually.
	 *
	 * Referenced Subjects that fail to deserialize are skipped: a broken relation
	 * target should not prevent the requested Subject from loading.
	 */
	public async getSubjectWithReferencedSubjects( id: SubjectId ): Promise<SubjectWithReferencedSubjects> {
		const bundle = await this.fetchSubjectBundle( id );

		return {
			requestedSubject: this.subjectDeserializer.deserialize( bundle.subjects[ bundle.requestedId ] ),
			referencedSubjects: this.deserializeReferencedSubjects( bundle ),
		};
	}

	private deserializeReferencedSubjects( bundle: SubjectBundleJson ): Subject[] {
		return Object.entries( bundle.subjects )
			.filter( ( [ id ] ) => id !== bundle.requestedId )
			.map( ( [ , subjectData ] ) => this.deserializeOrNull( subjectData ) )
			.filter( ( subject ): subject is Subject => subject !== null );
	}

	private deserializeOrNull( subjectData: SubjectJson ): Subject|null {
		try {
			return this.subjectDeserializer.deserialize( subjectData );
		} catch ( _error ) {
			return null;
		}
	}

	private async fetchSubjectBundle( id: SubjectId ): Promise<SubjectBundleJson> {
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

		return { requestedId: data.requestedId, subjects: data.subjects };
	}

	public async createMainSubject(
		pageId: number,
		label: string | null,
		schemaName: SchemaName,
		statements: StatementList,
		comment?: string,
	): Promise<SubjectWriteResult> {
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

		return this.deserializeWriteResult( await response.json() as SubjectWriteResponseJson );
	}

	public async createChildSubject(
		pageId: number,
		label: string | null,
		schemaName: SchemaName,
		statements: StatementList,
		comment?: string,
		id?: SubjectId,
	): Promise<SubjectWriteResult> {
		const payload = {
			label: label,
			schema: schemaName,
			statements: statementsToJson( statements ),
			comment,
			id: id?.text,
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

		return this.deserializeWriteResult( await response.json() as SubjectWriteResponseJson );
	}

	public async mintSubjectIds( count: number ): Promise<SubjectId[]> {
		const response = await this.httpClient.post(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/subject-ids`,
			{ count },
			{
				headers: {
					'Content-Type': 'application/json',
				},
			},
		);

		if ( !response.ok ) {
			throw new Error( 'Error minting subject ids' );
		}

		const data = await response.json() as { subjectIds: string[] };

		return data.subjectIds.map( ( id ) => new SubjectId( id ) );
	}

	/**
	 * A full replacement: a null label clears whatever label the Subject had.
	 */
	public async updateSubject( id: SubjectId, label: string | null, statements: StatementList, comment?: string ): Promise<SubjectWriteResult> {
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

		return this.deserializeWriteResult( await response.json() as SubjectWriteResponseJson );
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
		label: string | null,
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
		label: string | null,
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
