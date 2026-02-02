import { Schema, type SchemaName } from '@/domain/Schema';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';
import type { SchemaRepository } from '@/application/SchemaRepository';
import { SchemaSerializer } from '@/persistence/SchemaSerializer.ts';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer.ts';
import { PageSaver } from '@/persistence/PageSaver.ts';

export class RestSchemaRepository implements SchemaRepository {

	public constructor(
		private readonly mediaWikiRestApiUrl: string,
		private readonly httpClient: HttpClient,
		private readonly serializer: SchemaSerializer,
		private readonly deserializer: SchemaDeserializer,
		private readonly pageSaver: PageSaver,
	) {
	}

	public async getSchema( schemaName: SchemaName ): Promise<Schema> {
		const response = await this.httpClient.get(
			`${ this.mediaWikiRestApiUrl }/v1/page/Schema:${ schemaName }`,
		);

		if ( !response.ok ) {
			throw new Error( 'Error fetching schema' );
		}

		const data = await response.json();
		const schema = JSON.parse( data.source );

		if ( schema.propertyDefinitions === undefined ) {
			throw new Error( 'Schema propertyDefinitions is undefined' );
		}

		return this.deserializer.deserialize( schemaName, schema );
	}

	public async getSchemaNames( search = '' ): Promise<string[]> {
		const response = await this.httpClient.get(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/schema-names/${ search }`,
		);

		if ( !response.ok ) {
			throw new Error( 'Error fetching schemas' );
		}

		return await response.json();
	}

	public async saveSchema( schema: Schema, comment?: string ): Promise<void> {
		const status = await this.pageSaver.savePage(
			`Schema:${ encodeURIComponent( schema.getName() ) }`,
			this.serializeSchema( schema ),
			comment || 'Update schema via NeoWiki REST API',
			'NeoWikiSchema',
		);

		if ( !status.success ) {
			throw new Error( `Error saving schema: ${ status.message }` );
		}
	}

	private serializeSchema( schema: Schema ): string {
		return this.serializer.serializeSchema( schema );
	}

}
