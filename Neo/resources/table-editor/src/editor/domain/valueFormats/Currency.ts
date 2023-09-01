import type { PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { newNumberValue, type NumberValue, ValueType } from '@/editor/domain/Value';
import { CurrencyInputWidgetFactory } from '@/editor/presentation/Widgets/CurrencyWidgetFactory';
import type { CurrencyInputWidget } from '@/editor/presentation/Widgets/CurrencyWidgetFactory';
import { BaseValueFormat, ValidationResult, type ValidationError } from '@/editor/domain/ValueFormat';
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
		const errors: ValidationError[] = [];
		const boundsMessage = this.getBoundsMessage( value.number, property.minimum, property.maximum );

		if ( boundsMessage ) {
			errors.push( {
				message: boundsMessage,
				value: value
			} );
		}

		return new ValidationResult( errors );
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

	public async getFieldData( field: CurrencyInputWidget ): Promise<FieldData> {
		const isValid = ( field.$input[ 0 ] as HTMLInputElement ).checkValidity();

		return {
			value: newNumberValue( field.getValue() === '' ? NaN : Number( field.getValue() ) ),
			valid: isValid,
			errorMessage: isValid ? undefined : ( field.$input[ 0 ] as HTMLInputElement ).validationMessage
		};
	}
}
