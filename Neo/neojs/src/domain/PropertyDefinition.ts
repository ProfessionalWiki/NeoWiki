import { Neo } from '@neo/Neo';
import { PropertyTypeRegistry } from '@neo/domain/PropertyType';
import { Value } from '@neo/domain/Value';
import { ValueDeserializer } from '@neo/persistence/ValueDeserializer';

export class PropertyName {

	private readonly name: string;

	/**
	 * @param name - The name of the property.
	 * @param placeholder - Whether the name is a placeholder, used when creating a new property.
	 */
	public constructor( name: string, placeholder: boolean = false ) {
		this.name = name.trim();

		if ( !PropertyName.isValid( name ) && !placeholder ) {
			throw new Error( 'Invalid PropertyName' );
		}
	}

	public toString(): string {
		return this.name;
	}

	public static isValid( name: string ): boolean {
		return name.trim() !== '';
	}

}

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
		private readonly valueDeserializer: ValueDeserializer
	) {}

	public propertyDefinitionFromJson( name: string | PropertyName, json: any ): PropertyDefinition {
		const propertyType = this.registry.getType( json.format );
		return propertyType.createPropertyDefinitionFromJson(
			{
				name: typeof name === 'string' ? new PropertyName( name ) : name,
				type: json.format as string,
				description: json.description ?? '',
				required: json.required ?? false,
				default: json.default ? this.valueDeserializer.deserialize( json.default, json.format ) : undefined
			} as PropertyDefinition,
			json
		);
	}

}
