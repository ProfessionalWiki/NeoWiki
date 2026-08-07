import { Schema } from '@/domain/Schema';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { PropertyDefinition } from '@/domain/PropertyDefinition';
import { applySeverities } from '@/domain/SeverityNormalizer';
import { valueToJson } from '@/domain/Value';

export class SchemaSerializer {

	public serializeSchema( schema: Schema ): string {
		return JSON.stringify(
			{
				description: schema.getDescription(),
				propertyDefinitions: this.serializePropertyDefinitions( schema.getPropertyDefinitions() ),
			},
			null,
			4,
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
		const { name, constraintSeverities, ...propertyWithoutName } = property;
		return applySeverities(
			{
				...propertyWithoutName,
				default: property.default !== undefined ? valueToJson( property.default ) : undefined,
			},
			constraintSeverities ?? {},
		);
	}
}
