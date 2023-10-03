import type { PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { newNumberValue, type NumberValue, ValueType } from '@/editor/domain/Value';
import { CurrencyInputWidgetFactory } from '@/editor/presentation/Widgets/CurrencyWidgetFactory';
import type { CurrencyInputWidget } from '@/editor/presentation/Widgets/CurrencyWidgetFactory';
import { BaseValueFormat, ValidationResult } from '@/editor/domain/ValueFormat';
import type { PropertyAttributes } from '@/editor/domain/PropertyDefinitionAttributes';
import { PropertyName } from '@/editor/domain/PropertyDefinition';

export interface CurrencyProperty extends PropertyDefinition {

	readonly currencyCode: string;
	readonly precision: number;
	readonly minimum?: number;
	readonly maximum?: number;

}

interface CurrencyAttributes extends PropertyAttributes {
	readonly currencyCode?: string;
	readonly precision?: number;
	readonly minimum?: number;
	readonly maximum?: number;
}

export class CurrencyFormat extends BaseValueFormat<CurrencyProperty, NumberValue, CurrencyInputWidget, CurrencyAttributes> {

	public static readonly valueType = ValueType.Number;
	public static readonly formatName = 'currency';

	public getExampleValue(): NumberValue {
		return newNumberValue( 42 );
	}

	private getBoundsMessage( value: number, minimum: number | undefined, maximum: number | undefined ): string | null {
		if ( minimum !== undefined && maximum !== undefined ) {
			if ( value < minimum || value > maximum ) {
				return `Value should be within the allowed range (${minimum} - ${maximum}).`;
			}
		}

		if ( minimum !== undefined && value < minimum ) {
			return `Value should be greater than or equal to ${minimum}.`;
		}

		if ( maximum !== undefined && value > maximum ) {
			return `Value should be less than or equal to ${maximum}.`;
		}

		return null;
	}

	public validate( value: NumberValue, property: CurrencyProperty ): ValidationResult {
		const boundsMessage = this.getBoundsMessage( value.number, property.minimum, property.maximum );
		if ( boundsMessage ) {
			return new ValidationResult( [ {
				message: boundsMessage,
				value: value
			} ] );
		}

		return new ValidationResult( [] );
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
			min: property.minimum,
			max: property.maximum,
			required: property.required
		} );
	}

	public getFieldData( field: CurrencyInputWidget ): NumberValue {
		return newNumberValue( field.getValue() === '' ? NaN : Number( field.getValue() ) );
	}

	public getAttributes( base: PropertyAttributes ): CurrencyAttributes {
		return {
			...base,
			minimum: 0,
			maximum: 100,
			precision: 0,
			currencyCode: 'EUR',
			default: 0
		};
	}

	public getFieldElement( field: CurrencyInputWidget ): HTMLInputElement {
		return field.$input[ 0 ] as HTMLInputElement;
	}
}

export function newCurrencyProperty(
	name = 'MyCurrencyProperty',
	currencyCode = 'EUR',
	precision = 0
): CurrencyProperty {
	return {
		name: new PropertyName( name ),
		type: ValueType.Number,
		format: CurrencyFormat.formatName,
		precision: precision,
		currencyCode: currencyCode,
		description: '',
		required: false
	};
}
