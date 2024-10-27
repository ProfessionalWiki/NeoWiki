import type { MultiStringProperty, PropertyDefinition } from '@neo/domain/PropertyDefinition';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@neo/domain/Value';
import { BaseValueFormat } from '@neo/domain/ValueFormat';
import { UrlFormat } from '@neo/domain/valueFormats/Url';
import { NumberProperty } from '@neo/domain/valueFormats/Number';

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

type TextPropertyAttributes = Omit<Partial<TextProperty>, 'name'> & {
	name?: string | PropertyName;
};

export function newTextProperty(
	attributes: string|TextPropertyAttributes = {},
	name = 'MyTextProperty',
	multiple = false,
	format = TextFormat.formatName
): TextProperty {
	if ( typeof attributes === 'string' ) { // TODO: remove deprecated form
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
		name: attributes.name instanceof PropertyName ? attributes.name : new PropertyName( attributes.name || 'Text' ),
		format: TextFormat.formatName,
		description: attributes.description ?? '',
		required: attributes.required ?? false,
		default: attributes.default,
		multiple: attributes.multiple ?? false,
		uniqueItems: attributes.uniqueItems ?? true,
		maxLength: attributes.maxLength,
		minLength: attributes.minLength
	};
}
