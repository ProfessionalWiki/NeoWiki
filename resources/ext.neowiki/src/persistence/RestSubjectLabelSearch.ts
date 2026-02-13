import type { SubjectLabelSearch, SubjectLabelResult } from '@/domain/SubjectLabelSearch';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';

export class RestSubjectLabelSearch implements SubjectLabelSearch {

	public constructor(
		private readonly mediaWikiRestApiUrl: string,
		private readonly httpClient: HttpClient,
	) {
	}

	public async searchSubjectLabels( search: string, schema: string ): Promise<SubjectLabelResult[]> {
		const params = new URLSearchParams( { search, schema } );
		const response = await this.httpClient.get(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/subject-labels?${ params.toString() }`,
		);

		if ( !response.ok ) {
			throw new Error( 'Error searching subject labels' );
		}

		return await response.json();
	}

}
