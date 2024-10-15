import type { MultiStringProperty, PropertyDefinition } from '@neo/domain/PropertyDefinition';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@neo/domain/Value';
import { BaseValueFormat } from '@neo/domain/ValueFormat';
import { UrlFormat } from '@neo/domain/valueFormats/Url';

export interface TextProperty extends MultiStringProperty {

	readonly maxLength?: number;
	readonly minLength?: number;

}

export class TextFormat extends BaseValueFormat<TextProperty, StringValue> {

	public static readonly valueType = ValueType.String;

	public static readonly formatName = 'text';

	public getExampleValue(): StringValue {
		return newStringValue( 'Some Text' );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): TextProperty {
		return {
			...base,
			multiple: json.multiple ?? false,
			uniqueItems: json.uniqueItems ?? true
		} as TextProperty;
	}

}

export function newTextProperty(
	options: string|Partial<TextProperty> = {},
	name = 'MyTextProperty',
	multiple = false,
	format = TextFormat.formatName
): TextProperty {
	if ( typeof options === 'string' ) { // TODO: remove deprecated form
		return {
			name: new PropertyName( name ),
			format: format,
			description: '',
			required: false,
			default: newStringValue( '' ),
			multiple: multiple,
			uniqueItems: true
		};
	}

	return {
		name: options.name instanceof PropertyName ? options.name : new PropertyName( options.name || 'text' ),
		format: UrlFormat.formatName,
		description: options.description || '',
		required: options.required || false,
		default: options.default || undefined,
		multiple: options.multiple || false,
		uniqueItems: options.uniqueItems ?? true,
		maxLength: options.maxLength || undefined,
		minLength: options.minLength || undefined
	};
}
