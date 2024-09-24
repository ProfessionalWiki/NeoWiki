import type { MultiStringProperty, PropertyDefinition } from '@neo/domain/PropertyDefinition';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@neo/domain/Value';
import { BaseValueFormat } from '@neo/domain/ValueFormat';

export interface TextProperty extends MultiStringProperty {
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

export function newTextProperty( name = 'MyTextProperty', multiple = false, format = TextFormat.formatName ): TextProperty {
	return {
		name: new PropertyName( name ),
		type: ValueType.String,
		format: format,
		description: '',
		required: false,
		default: '',
		multiple: multiple,
		uniqueItems: true
	};
}
