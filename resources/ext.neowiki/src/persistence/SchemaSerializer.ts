import { Schema } from '@/domain/Schema';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { PropertyDefinition } from '@/domain/PropertyDefinition';
import { applySeverities } from '@/domain/SeverityNormalizer';
import { valueToJson } from '@/domain/Value';

export class SchemaSerializer {

	public serializeSchema( schema: Schema ): string {
		return JSON.stringify( this.schemaToJson( schema ), null, 4 );
	}

	public schemaToJson( schema: Schema ): Record<string, unknown> {
		return {
			description: schema.getDescription(),
			...( schema.subjectsAreDependent() ? { subjectsAreDependent: true } : {} ),
			propertyDefinitions: this.serializePropertyDefinitions( schema.getPropertyDefinitions() ),
		};
	}

	private serializePropertyDefinitions( propertyDefinitions: PropertyDefinitionList ): Record<string, any> {
		return Object.fromEntries(
			[ ...propertyDefinitions ].map(
				( property ) => [ property.name.toString(), this.serializePropertyDefinition( property ) ],
			),
		);
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
