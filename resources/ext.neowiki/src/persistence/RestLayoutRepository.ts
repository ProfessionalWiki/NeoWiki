import type { Layout, LayoutName } from '@/domain/Layout';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';
import type { LayoutRepository } from '@/application/LayoutRepository';
import { LayoutSerializer } from '@/persistence/LayoutSerializer.ts';
import { LayoutDeserializer } from '@/persistence/LayoutDeserializer.ts';
import type { PageSaver } from '@/persistence/PageSaver.ts';

export class RestLayoutRepository implements LayoutRepository {

	public constructor(
		private readonly mediaWikiRestApiUrl: string,
		private readonly httpClient: HttpClient,
		private readonly serializer: LayoutSerializer,
		private readonly deserializer: LayoutDeserializer,
		private readonly pageSaver: PageSaver,
	) {
	}

	public async getLayout( layoutName: LayoutName ): Promise<Layout> {
		const response = await this.httpClient.get(
			`${ this.mediaWikiRestApiUrl }/v1/page/Layout:${ encodeURIComponent( layoutName ) }`,
		);

		if ( !response.ok ) {
			throw new Error( 'Error fetching layout' );
		}

		const data = await response.json();
		const layoutJson = JSON.parse( data.source );

		return this.deserializer.deserialize( layoutName, layoutJson );
	}

	public async saveLayout( layout: Layout, comment?: string ): Promise<void> {
		const status = await this.pageSaver.savePage(
			`Layout:${ encodeURIComponent( layout.getName() ) }`,
			this.serializer.serializeLayout( layout ),
			comment || 'Update layout via NeoWiki UI',
			'NeoWikiLayout',
		);

		if ( !status.success ) {
			throw new Error( status.message );
		}
	}

}
