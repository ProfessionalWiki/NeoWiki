import type { PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { type StringValue, ValueType } from '@/editor/domain/Value';
import { BaseValueFormat } from '@/editor/domain/ValueFormat';
import { ValidationResult } from '@/editor/domain/ValueFormat';

export interface DateTimeProperty extends PropertyDefinition {
}

export class DateTimeFormat extends BaseValueFormat<DateTimeProperty, StringValue> {

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
		const widget = new mw.widgets.datetime.DateTimeInputWidget( {
			value: value?.strings[ 0 ] ?? '', // TODO: handle multiple values?
			required: property.required
		} );

		widget.setFlags( { invalid: false } );

		return widget;
	}

	public formatValueAsHtml( value: StringValue, property: DateTimeProperty ): string {
		return value.strings.join( ', ' );
	}

}
