import { Schema, type SchemaName } from '@/domain/Schema';
import { createPropertyDefinitionFromJson } from '@/domain/PropertyDefinition';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';

export class SchemaDeserializer {

	public deserialize( schemaName: SchemaName, schema: Record<string, any> ): Schema {
		return new Schema(
			schemaName,
			schema.description,
			this.deserializePropertyDefinitions( schema.propertyDefinitions ),
		);
	}

	private deserializePropertyDefinitions( definitions: Record<string, any> ): PropertyDefinitionList {
		return new PropertyDefinitionList(
			Object.keys( definitions ).map( ( key ) => createPropertyDefinitionFromJson( key, definitions[ key ] ) ),
		);
	}

}
