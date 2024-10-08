import { Schema } from '@neo/domain/Schema';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList';
import { PropertyDefinition } from '@neo/domain/PropertyDefinition';
import { valueToJson } from '@neo/domain/Value';

export class SchemaSerializer {

	public serializeSchema( schema: Schema ): string {
		return JSON.stringify(
			{
				description: schema.getDescription(),
				propertyDefinitions: this.serializePropertyDefinitions( schema.getPropertyDefinitions() )
			},
			null,
			4
		);
	}

	private serializePropertyDefinitions( propertyDefinitions: PropertyDefinitionList ): Record<string, any> {
		const serializedDefinitions: Record<string, any> = {};
		for ( const property of propertyDefinitions ) {
			serializedDefinitions[ property.name.toString() ] = this.serializePropertyDefinition( property );
		}
		return serializedDefinitions;
	}

	private serializePropertyDefinition( property: PropertyDefinition ): any {
		const { name, ...propertyWithoutName } = property;
		return {
			...propertyWithoutName,
			default: property.default ? valueToJson( property.default ) : undefined
		};
	}
}
