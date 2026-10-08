import type { SubjectCountLookup } from '@/application/SubjectCountLookup';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';

export class RestSubjectCountLookup implements SubjectCountLookup {

	public constructor(
		private readonly mediaWikiRestApiUrl: string,
		private readonly httpClient: HttpClient,
	) {
	}

	public async getSubjectCounts(): Promise<Map<string, number>> {
		const response = await this.httpClient.get( `${ this.mediaWikiRestApiUrl }/neowiki/v0/subject-counts` );

		if ( !response.ok ) {
			throw new Error( 'Error fetching subject counts' );
		}

		const { counts }: { counts: Record<string, number> } = await response.json();

		// A Map rather than the object, whose inherited properties would answer for a Schema named "constructor".
		return new Map( Object.entries( counts ) );
	}

}
