import { View, type ViewName } from '@/domain/View';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';
import type { ViewLookup } from '@/application/ViewLookup';

export class RestViewLookup implements ViewLookup {

	public constructor(
		private readonly mediaWikiRestApiUrl: string,
		private readonly httpClient: HttpClient,
	) {
	}

	public async getView( viewName: ViewName ): Promise<View> {
		const response = await this.httpClient.get(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/view/${ encodeURIComponent( viewName ) }`,
		);

		if ( !response.ok ) {
			throw new Error( 'Error fetching view' );
		}

		const data = await response.json();

		if ( data.view === null ) {
			throw new Error( `View ${ viewName } not found` );
		}

		return new View(
			viewName,
			data.view.schema,
			data.view.type,
			data.view.description ?? '',
			data.view.displayRules ?? [],
			data.view.settings ?? {},
		);
	}

}
