import type { PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { type StringValue, ValueType } from '@/editor/domain/Value';
import type { ValueFormatInterface } from '@/editor/domain/ValueFormat';
import { ValidationResult } from '@/editor/domain/ValueFormat';

export interface DateTimeProperty extends PropertyDefinition {
}

export class DateTimeFormat implements ValueFormatInterface<DateTimeProperty, StringValue> {

	public readonly valueType = ValueType.String;
	public readonly name = 'dateTime';

	public validate( value: StringValue, property: DateTimeProperty ): ValidationResult {
		return new ValidationResult( [] ); // TODO
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): DateTimeProperty {
		return {
			...base
		} as DateTimeProperty;
	}

	public createFormField( value: StringValue | undefined, property: DateTimeProperty ): any {
		return new mw.widgets.datetime.DateTimeInputWidget( {
			value: value?.strings[ 0 ] ?? '', // TODO: handle multiple values?
			required: property.required
		} );
	}

	public formatValueAsHtml( value: StringValue, property: DateTimeProperty ): string {
		return value.strings.join( ', ' );
	}

}
