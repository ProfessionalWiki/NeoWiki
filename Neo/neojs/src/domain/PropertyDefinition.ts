import { Neo } from '@neo/Neo';
import { ValueFormatRegistry } from '@neo/domain/ValueFormat';

export class PropertyName {

	private readonly name: string;

	public constructor( name: string ) {
		if ( name === '' ) {
			throw new Error( 'Invalid PropertyName' );
		}
		this.name = name;
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
	readonly default?: unknown;

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
		private readonly registry: ValueFormatRegistry
	) {}

	public propertyDefinitionFromJson( name: string | PropertyName, json: any ): PropertyDefinition {
		const format = this.registry.getFormat( json.format );
		return format.createPropertyDefinitionFromJson(
			{
				name: typeof name === 'string' ? new PropertyName( name ) : name,
				format: json.format as string,
				description: json.description ?? '',
				required: json.required ?? false,
				default: json.default
			} as PropertyDefinition,
			json
		);
	}

}
