import { Schema, type SchemaName } from '@/domain/Schema';
import { createPropertyDefinitionFromJson } from '@/domain/PropertyDefinition';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';
import type { SchemaRepository } from '@/application/SchemaRepository';
import { SchemaSerializer } from '@/persistence/SchemaSerializer.ts';
import { PageSaver } from '@/persistence/PageSaver.ts';

export function schemaFromJson( schemaName: SchemaName, schema: Record<string, any> ): Schema {
	return new Schema(
		schemaName,
		schema.description,
		propertyDefinitionsFromJson( schema.propertyDefinitions ),
	);
}

export function propertyDefinitionsFromJson( definitions: Record<string, any> ): PropertyDefinitionList {
	return new PropertyDefinitionList(
		Object.keys( definitions ).map( ( key ) => createPropertyDefinitionFromJson( key, definitions[ key ] ) ),
	);
}

export class RestSchemaRepository implements SchemaRepository {

	public constructor(
		private readonly mediaWikiRestApiUrl: string,
		private readonly httpClient: HttpClient,
		private readonly serializer: SchemaSerializer,
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

		return schemaFromJson( schemaName, schema );
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
