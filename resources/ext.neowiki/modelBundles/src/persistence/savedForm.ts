import { LayoutDeserializer, LayoutSerializer, SchemaDeserializer, SchemaSerializer } from 'ext.neowiki';
import type { ItemType, JsonObject } from '../application/ModelBundle';

/**
 * As NeoWiki saves the item, so that two that read alike compare alike.
 */
export function savedForm( type: Exclude<ItemType, 'subject'>, name: string, item: JsonObject ): JsonObject {
	switch ( type ) {
		case 'schema':
			return new SchemaSerializer().schemaToJson( new SchemaDeserializer().deserialize( name, item ) );
		case 'layout':
			return new LayoutSerializer().layoutToJson( new LayoutDeserializer().deserialize( name, item ) );
		case 'mapping':
			return item;
	}
}
