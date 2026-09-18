import type { SubjectLabelSearch, SubjectLabelResult } from '@/domain/SubjectLabelSearch';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';

export class RestSubjectLabelSearch implements SubjectLabelSearch {

	public constructor(
		private readonly mediaWikiRestApiUrl: string,
		private readonly httpClient: HttpClient,
	) {
	}

	public async searchSubjectLabels( search: string, schema?: string ): Promise<SubjectLabelResult[]> {
		// The endpoint reads an absent parameter as every Schema; an empty one would be a Schema
		// named by nothing, which matches no Subject.
		const params = new URLSearchParams( schema === undefined ? { search } : { search, schema } );
		const response = await this.httpClient.get(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/subject-labels?${ params.toString() }`,
		);

		if ( !response.ok ) {
			throw new Error( 'Error searching subject labels' );
		}

		return await response.json();
	}

}
