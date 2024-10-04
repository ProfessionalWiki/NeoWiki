import type { SubjectRepository } from '@neo/domain/SubjectRepository';
import { SubjectId } from '@neo/domain/SubjectId';
import type { SubjectDeserializer } from '@/persistence/SubjectDeserializer';
import { StatementList, statementsToJson } from '@neo/domain/StatementList';
import type { SchemaLookup } from '@/application/SchemaLookup';
import { Schema, type SchemaName } from '@neo/domain/Schema';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';
import type { Subject } from '@neo/domain/Subject';

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
		private readonly schemaLookup: SchemaLookup,
		private readonly subjectDeserializer: SubjectDeserializer
	) {
	}

	public async getSubject( id: SubjectId ): Promise<Subject> {
		const response = await this.httpClient.get(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/subject/${ id.text }?expand=page|relations`
		);

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

	private async getSchema( schemaName: string ): Promise<Schema> {
		try {
			return this.schemaLookup.getSchema( schemaName );
		} catch ( _error ) {
			return new Schema( schemaName, '', new PropertyDefinitionList( [] ) );
		}
	}

	public async createMainSubject(
		pageId: number,
		label: string,
		schemaName: SchemaName,
		statements: StatementList
	): Promise<SubjectId> {
		const payload = {
			label: label,
			schema: schemaName,
			statements: statementsToJson( statements )
		};

		const response = await this.httpClient.post(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/page/${ pageId }/mainSubject`,
			payload,
			{
				headers: {
					'Content-Type': 'application/json'
				}
			}
		);

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
		statements: StatementList
	): Promise<SubjectId> {
		const payload = {
			label: label,
			schema: schemaName,
			statements: statementsToJson( statements )
		};

		const response = await this.httpClient.post(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/page/${ pageId }/childSubjects`,
			payload,
			{
				headers: {
					'Content-Type': 'application/json'
				}
			}
		);

		if ( !response.ok ) {
			throw new Error( 'Error creating child subject' );
		}

		const data = await response.json();
		return new SubjectId( data.subjectId );
	}

	public async updateSubject( id: SubjectId, statements: StatementList ): Promise<object> {
		const response = await this.httpClient.patch(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/subject/${ id.text }`,
			{
				statements: statementsToJson( statements )
			},
			{
				headers: {
					'Content-Type': 'application/json'
				}
			}
		);

		if ( !response.ok ) {
			throw new Error( 'Error updating subject' );
		}

		return await response.json();
	}

	public async deleteSubject( id: SubjectId ): Promise<boolean> {
		const response = await this.httpClient.delete(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/subject/${ id.text }`,
			{
				headers: {
					'Content-Type': 'application/json'
				}
			}
		);

		if ( !response.ok ) {
			throw new Error( 'Error deleting subject' );
		}

		return true;
	}

}
