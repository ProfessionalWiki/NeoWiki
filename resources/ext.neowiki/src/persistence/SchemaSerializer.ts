import { Schema } from '@/domain/Schema';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { PropertyDefinition } from '@/domain/PropertyDefinition';
import { valueToJson } from '@/domain/Value';

const CORE_FIELDS = new Set( [ 'name', 'type', 'description', 'required', 'default' ] );

const TOP_LEVEL_FIELDS: Record<string, Set<string>> = {
	relation: new Set( [ 'relation', 'targetSchema' ] ),
};

const DISPLAY_ATTRIBUTE_FIELDS: Record<string, Set<string>> = {
	number: new Set( [ 'precision' ] ),
};

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
		const topLevelFields = TOP_LEVEL_FIELDS[ property.type ] ?? new Set();
		const displayAttributeFields = DISPLAY_ATTRIBUTE_FIELDS[ property.type ] ?? new Set();
		const topLevel: Record<string, any> = {};
		const constraints: Record<string, any> = {};
		const displayAttributes: Record<string, any> = {};

		for ( const [ key, value ] of Object.entries( property ) ) {
			if ( CORE_FIELDS.has( key ) ) {
				continue;
			}

			if ( topLevelFields.has( key ) ) {
				topLevel[ key ] = value;
			} else if ( displayAttributeFields.has( key ) ) {
				displayAttributes[ key ] = value;
			} else {
				constraints[ key ] = value;
			}
		}

		return {
			type: property.type,
			description: property.description,
			required: property.required,
			default: property.default !== undefined ? valueToJson( property.default ) : undefined,
			...topLevel,
			constraints,
			displayAttributes,
		};
	}
}
