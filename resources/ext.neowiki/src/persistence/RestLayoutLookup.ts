import { Layout, type LayoutName, type DisplayRule } from '@/domain/Layout';
import { PropertyName } from '@/domain/PropertyDefinition';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';
import type { LayoutLookup } from '@/application/LayoutLookup';

export class RestLayoutLookup implements LayoutLookup {

	public constructor(
		private readonly mediaWikiRestApiUrl: string,
		private readonly httpClient: HttpClient,
	) {
	}

	public async getLayout( layoutName: LayoutName ): Promise<Layout> {
		const response = await this.httpClient.get(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/layout/${ encodeURIComponent( layoutName ) }`,
		);

		if ( !response.ok ) {
			throw new Error( 'Error fetching layout' );
		}

		const data = await response.json();

		if ( data.layout === null ) {
			throw new Error( `Layout ${ layoutName } not found` );
		}

		return new Layout(
			layoutName,
			data.layout.schema,
			data.layout.type,
			data.layout.description ?? '',
			this.deserializeDisplayRules( data.layout.displayRules ?? [] ),
			data.layout.settings ?? {},
		);
	}

	private deserializeDisplayRules( rules: any[] ): DisplayRule[] {
		return rules.map( ( rule: any ) => ( {
			property: new PropertyName( rule.property ),
			displayAttributes: rule.displayAttributes,
		} ) );
	}

}
