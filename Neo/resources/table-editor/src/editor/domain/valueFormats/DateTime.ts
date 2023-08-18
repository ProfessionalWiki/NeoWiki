import type { PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@/editor/domain/Value';
import { BaseValueFormat, ValidationResult } from '@/editor/domain/ValueFormat';
import type { FieldData } from '@/editor/presentation/SchemaForm';

export interface DateTimeProperty extends PropertyDefinition {
}

export class DateTimeFormat extends BaseValueFormat<DateTimeProperty, StringValue, OO.ui.InputWidget> {

	public static readonly valueType = ValueType.String;
	public static readonly formatName = 'dateTime';

	public validate( value: StringValue, property: DateTimeProperty ): ValidationResult {
		return new ValidationResult( [] ); // TODO
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): DateTimeProperty {
		return {
			...base
		} as DateTimeProperty;
	}

	public createFormField( value: StringValue | undefined, property: DateTimeProperty ): OO.ui.Widget {
		const widget = new mw.widgets.datetime.DateTimeInputWidget( {
			value: value?.strings[ 0 ] ?? '', // TODO: handle multiple values?
			required: property.required
		} );
		widget.setFlags( { invalid: false } );

		return widget;
	}

	public async getFieldData( field: OO.ui.InputWidget ): Promise<FieldData> {
		const value = field.getValue();

		return {
			value: value !== '' ? newStringValue( value ) : newStringValue(),
			valid: true,
			errorMessage: undefined
		};
	}
}
