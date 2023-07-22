import type { PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { type NumberValue, ValueType } from '@/editor/domain/Value';
import type { CurrencyInputWidget } from '@/editor/presentation/Widgets/CurrencyWidgetFactory';
import { CurrencyInputWidgetFactory } from '@/editor/presentation/Widgets/CurrencyWidgetFactory';
import { BaseValueFormat, ValidationResult } from '@/editor/domain/ValueFormat';

export interface CurrencyProperty extends PropertyDefinition {

	readonly currencyCode: string;
	readonly precision: number;
	readonly minimum?: number;
	readonly maximum?: number;

}

export class CurrencyFormat extends BaseValueFormat<CurrencyProperty, NumberValue> {

	public readonly valueType = ValueType.Number;
	public readonly name = 'currency';

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

	public formatValueAsHtml( value: NumberValue, property: CurrencyProperty ): string {
		// TODO: Format number according to precision
		// TODO: limit to minimum and maximum? How do we want to deal with invalid values during display?
		return property.currencyCode + ' ' + value.number;
	}

}
