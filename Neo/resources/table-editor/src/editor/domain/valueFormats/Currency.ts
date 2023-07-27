import type { PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { newNumberValue, type NumberValue, ValueType } from '@/editor/domain/Value';
import { CurrencyInputWidgetFactory } from '@/editor/presentation/Widgets/CurrencyWidgetFactory';
import type { CurrencyInputWidget } from '@/editor/presentation/Widgets/CurrencyWidgetFactory';
import { BaseValueFormat, ValidationResult } from '@/editor/domain/ValueFormat';
import type { FieldData } from '@/editor/presentation/SchemaForm';

export interface CurrencyProperty extends PropertyDefinition {

	readonly currencyCode: string;
	readonly precision: number;
	readonly minimum?: number;
	readonly maximum?: number;

}

export class CurrencyFormat extends BaseValueFormat<CurrencyProperty, NumberValue, CurrencyInputWidget> {

	public static readonly valueType = ValueType.Number;
	public static readonly formatName = 'currency';

	public validate( value: NumberValue, property: CurrencyProperty ): ValidationResult {
		return new ValidationResult( [] ); // TODO
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): CurrencyProperty {
		return {
			...base,
			currencyCode: json.currencyCode,
			precision: json.precision,
			minimum: json.minimum,
			maximum: json.maximum
		} as CurrencyProperty;
	}

	public createFormField( value: NumberValue | undefined, property: CurrencyProperty ): CurrencyInputWidget {
		return CurrencyInputWidgetFactory.create( {
			value: value === undefined ? '' : value.number.toString(),
			currency: property.currencyCode,
			precision: property.precision,
			min: property.minimum,
			max: property.maximum,
			required: property.required
		} );
	}

	public async getFieldData( field: CurrencyInputWidget ): Promise<FieldData> {
		return {
			value: newNumberValue( Number( field.getValue() ) ),
			valid: true,
			errorMessage: undefined
		};
	}
}
