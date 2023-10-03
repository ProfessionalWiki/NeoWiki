import type { PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@/editor/domain/Value';
import { BaseValueFormat, ValidationResult } from '@/editor/domain/ValueFormat';
import type { DateWidgetFactory } from '@/editor/presentation/Widgets/DateWidgets/DateWidgetFactory';
import type { PropertyAttributes } from '@/editor/domain/PropertyDefinitionAttributes';

export interface DateProperty extends PropertyDefinition {
}

interface DateAttributes extends PropertyAttributes {
}

export class DateFormat extends BaseValueFormat<DateProperty, StringValue, OO.ui.InputWidget, DateAttributes> {

	public static readonly valueType = ValueType.String;
	public static readonly formatName = 'date';

	public constructor( private readonly dateWidgetFactory: DateWidgetFactory ) {
		super();
	}

	public getExampleValue(): StringValue {
		return newStringValue( '2021-04-19' );
	}

	public validate( value: StringValue, property: DateProperty ): ValidationResult {
		return new ValidationResult( [] );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): DateProperty {
		return {
			...base
		} as DateProperty;
	}

	public createFormField( value: StringValue | undefined, property: DateProperty ): OO.ui.Widget {
		return this.dateWidgetFactory.create( { // TODO: handle multiple values?
			displayFormat: 'Do [of] MMMM, YYYY',
			value: value?.strings[ 0 ] ?? '',
			required: property.required
		} );
	}

	public getFieldData( field: OO.ui.InputWidget ): StringValue {
		const value = field.getValue();
		return value !== '' ? newStringValue( value ) : newStringValue();
	}

	public getAttributes( base: PropertyAttributes ): DateAttributes {
		return {
			...base
		};
	}

	public getFieldElement( field: OO.ui.InputWidget ): HTMLInputElement {
		return field.$input[ 0 ] as HTMLInputElement;
	}
}
