import type { PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@/editor/domain/Value';
import { BaseValueFormat, ValidationResult } from '@/editor/domain/ValueFormat';
import type { FieldData } from '@/editor/presentation/SchemaForm';

export interface DateProperty extends PropertyDefinition {
}

export class DateFormat extends BaseValueFormat<DateProperty, StringValue, OO.ui.InputWidget> {

	public static readonly valueType = ValueType.String;
	public static readonly formatName = 'date';

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

	public async getFieldData( field: OO.ui.InputWidget ): Promise<FieldData> {
		return {
			value: newStringValue( field.getValue() ?? '' ),
			valid: true,
			errorMessage: undefined
		};
	}
}
