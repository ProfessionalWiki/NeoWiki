import type { EdtfHumanizer } from '@/domain/EdtfHumanizer';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';

export class RestEdtfHumanizer implements EdtfHumanizer {

	public constructor(
		private readonly mediaWikiRestApiUrl: string,
		private readonly httpClient: HttpClient,
	) {
	}

	public async humanize( value: string, language: string ): Promise<string | null> {
		const params = new URLSearchParams( { value, language } );
		const response = await this.httpClient.get(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/edtf/humanize?${ params.toString() }`,
		);

		if ( !response.ok ) {
			throw new Error( 'Error humanizing EDTF value' );
		}

		const body = await response.json() as { humanized: string | null };

		return body.humanized;
	}

}
