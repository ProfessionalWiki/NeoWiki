import type { SubjectSummaries, SubjectSummaryLookup, SubjectSummaryQuery } from '@/application/SubjectSummaryLookup';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';

export class RestSubjectSummaryLookup implements SubjectSummaryLookup {

	public constructor(
		private readonly mediaWikiRestApiUrl: string,
		private readonly httpClient: HttpClient,
	) {
	}

	public async getSubjectSummaries( query: SubjectSummaryQuery ): Promise<SubjectSummaries> {
		const params = new URLSearchParams( {
			search: query.search,
			sort: query.sort,
			direction: query.direction,
			limit: String( query.limit ),
		} );

		// Absent means every Schema and the first page; an empty value would be a Schema or cursor named by nothing.
		if ( query.schema !== null ) {
			params.set( 'schema', query.schema );
		}

		if ( query.cursor !== null ) {
			params.set( 'cursor', query.cursor );
		}

		const response = await this.httpClient.get(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/subjects?${ params.toString() }`,
		);

		if ( !response.ok ) {
			throw new Error( 'Error fetching subject summaries' );
		}

		return await response.json();
	}

}
