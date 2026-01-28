import { Neo } from '@/Neo';
import { PropertyTypeRegistry } from '@/domain/PropertyType';
import { Value } from '@/domain/Value';
import { ValueDeserializer } from '@/persistence/ValueDeserializer';
import { PropertyName } from '@/domain/PropertyName';

export { PropertyName };

export interface PropertyDefinition {

	readonly name: PropertyName;
	readonly type: string;
	readonly description: string;
	readonly required: boolean;
	readonly default?: Value;

}

export interface MultiStringProperty extends PropertyDefinition {

	readonly multiple: boolean;
	readonly uniqueItems: boolean;

}

/**
 * In production code, prefer using an instance of PropertyDefinitionDeserializer
 */
export function createPropertyDefinitionFromJson( id: string, json: any ): PropertyDefinition {
	return Neo.getInstance().getPropertyDefinitionDeserializer().propertyDefinitionFromJson( id, json );
}

export class PropertyDefinitionDeserializer {

	public constructor(
		private readonly registry: PropertyTypeRegistry,
		private readonly valueDeserializer: ValueDeserializer,
	) {}

	public propertyDefinitionFromJson( name: string | PropertyName, json: any ): PropertyDefinition {
		const propertyType = this.registry.getType( json.type );
		return propertyType.createPropertyDefinitionFromJson(
			{
				name: typeof name === 'string' ? new PropertyName( name ) : name,
				type: json.type as string,
				description: json.description ?? '',
				required: json.required ?? false,
				default: json.default ? this.valueDeserializer.deserialize( json.default, json.type ) : undefined,
			} as PropertyDefinition,
			json,
		);
	}

}
