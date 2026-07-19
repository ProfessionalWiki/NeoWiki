import { Neo } from '@/Neo';
import { PropertyTypeRegistry } from '@/domain/PropertyType';
import { Value } from '@/domain/Value';
import { ValueDeserializer } from '@/persistence/ValueDeserializer';

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
		private readonly valueDeserializer: ValueDeserializer,
	) {}

	public propertyDefinitionFromJson( name: string | PropertyName, json: any ): PropertyDefinition {
		const base: PropertyDefinition = {
			name: typeof name === 'string' ? new PropertyName( name ) : name,
			type: json.type as string,
			description: json.description ?? '',
			required: json.required ?? false,
			default: json.default !== undefined && json.default !== null ?
				this.valueDeserializer.deserialize( json.default, json.type ) :
				undefined,
		};

		// A type owned by a disabled or failed extension is not registered. Degrade
		// to the base definition so the rest of the Schema still loads and renders.
		// Retain the original type-specific keys (constraints, display attributes)
		// so they are not silently dropped when the Schema is later re-saved.
		if ( !this.registry.hasType( json.type ) ) {
			return { ...json, ...base };
		}

		return this.registry.getType( json.type ).createPropertyDefinitionFromJson( base, json );
	}

}
