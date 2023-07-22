import type { PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { type StringValue, ValueType } from '@/editor/domain/Value';
import { BaseValueFormat, ValidationResult } from '@/editor/domain/ValueFormat';

export interface DateProperty extends PropertyDefinition {
}

export class DateFormat extends BaseValueFormat<DateProperty, StringValue> {

	public readonly valueType = ValueType.String;
	public readonly name = 'date';

	public validate( value: StringValue, property: DateProperty ): ValidationResult {
		return new ValidationResult( [] ); // TODO
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): DateProperty {
		return {
			...base
		} as DateProperty;
	}

	public createFormField( value: StringValue | undefined, property: DateProperty ): any {
		return new mw.widgets.DateInputWidget( { // TODO: handle multiple values?
			displayFormat: 'Do [of] MMMM, YYYY',
			value: value?.strings[ 0 ] ?? '',
			required: property.required
		} );
	}

	public formatValueAsHtml( value: StringValue, property: DateProperty ): string {
		return value.strings.join( ', ' );
	}

}
