import type { ValueType } from '@/editor/domain/Value';
import { NeoWikiExtension } from '@/NeoWikiExtension';

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
	readonly multiple?: boolean;
	readonly uniqueItems?: boolean;

}

// TODO: is this really the best way to have type safety for formats?
// export function isCurrencyProperty( property: PropertyDefinition ): property is CurrencyProperty {
// 	return property.format === ValueFormat.Currency;
// }

export function createPropertyDefinitionFromJson( id: string, json: any ): PropertyDefinition {
	const format = NeoWikiExtension.getInstance().getValueFormatRegistry().getFormat( json.format );

	return format.createPropertyDefinitionFromJson(
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
