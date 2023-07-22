import type { MultiStringProperty, PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { type StringValue, ValueType } from '@/editor/domain/Value';
import { BaseValueFormat, createStringFormField, ValidationResult } from '@/editor/domain/ValueFormat';
import { Format, PropertyName } from '@/editor/domain/PropertyDefinition';

export interface TextProperty extends MultiStringProperty {
}

export class TextFormat extends BaseValueFormat<TextProperty, StringValue> {

	public readonly valueType = ValueType.String;
	public readonly name = 'text';

	public validate( value: StringValue, property: TextProperty ): ValidationResult {
		return new ValidationResult( [] );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): TextProperty {
		return {
			...base,
			multiple: json.multiple ?? false,
			uniqueItems: json.uniqueItems ?? true
		} as TextProperty;
	}

	public createFormField( value: StringValue | undefined, property: TextProperty ): any {
		return createStringFormField( value, property, 'text' );
	}

	public formatValueAsHtml( value: StringValue, property: TextProperty ): string {
		return value.strings.join( ', ' );
	}

}

export function newTextProperty( name = 'MyTextProperty' ): TextProperty {
	return {
		name: new PropertyName( name ),
		type: ValueType.String,
		format: 'text' as Format,
		description: '',
		required: false,
		default: '',
		multiple: false,
		uniqueItems: true
	};
}
