import { Schema } from '@neo/domain/Schema.ts';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList.ts';

export function createExampleSchemas(): Map<string, Schema> {
	const schemas = new Map<string, Schema>();

	schemas.set( 'Person', new Schema(
		'Person',
		'Information about an individual',
		new PropertyDefinitionList( [] )
	) );
	schemas.set( 'Organization', new Schema(
		'Organization',
		'Details about a company or institution',
		new PropertyDefinitionList( [] )
	) );
	schemas.set( 'Place', new Schema(
		'Place',
		'Geographic location or landmark',
		new PropertyDefinitionList( [] )
	) );

	return schemas;
}
