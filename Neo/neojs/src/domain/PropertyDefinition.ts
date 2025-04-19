import { Neo } from '@neo/Neo';
import { ValueFormatRegistry } from '@neo/domain/PropertyType';
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

		if ( this.name === '' && !placeholder ) {
			throw new Error( 'Invalid PropertyName' );
		}
	}

	public toString(): string {
		return this.name;
	}

}

export interface PropertyDefinition {

	readonly name: PropertyName;
	readonly format: string;
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
		private readonly registry: ValueFormatRegistry,
		private readonly valueDeserializer: ValueDeserializer
	) {}

	public propertyDefinitionFromJson( name: string | PropertyName, json: any ): PropertyDefinition {
		const format = this.registry.getFormat( json.format );
		return format.createPropertyDefinitionFromJson(
			{
				name: typeof name === 'string' ? new PropertyName( name ) : name,
				format: json.format as string,
				description: json.description ?? '',
				required: json.required ?? false,
				default: json.default ? this.valueDeserializer.deserialize( json.default, json.format ) : undefined
			} as PropertyDefinition,
			json
		);
	}

}
