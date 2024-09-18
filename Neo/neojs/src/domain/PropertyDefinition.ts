import type { ValueType } from '@/domain/Value';
import { Neo } from '@/Neo';

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
	readonly type: ValueType;
	readonly format: string;
	readonly description: string;
	readonly required: boolean;
	readonly default?: unknown;

}

export interface MultiStringProperty extends PropertyDefinition {

	readonly type: ValueType.String;
	readonly multiple: boolean;
	readonly uniqueItems: boolean;

}

// FIXME: avoid global access by putting this on the global factory or ValueFormatRegistry
export function createPropertyDefinitionFromJson( id: string, json: any ): PropertyDefinition {
	return Neo.getInstance().getValueFormatRegistry().getFormat( json.format ).createPropertyDefinitionFromJson(
		{
			name: new PropertyName( id ),
			type: json.type as ValueType,
			format: json.format as string,
			description: json.description ?? '',
			required: json.required ?? false,
			default: json.default
		} as PropertyDefinition,
		json
	);
}
