import type { PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { newNumberValue, type NumberValue, ValueType } from '@/editor/domain/Value';
import { PropertyName } from '@/editor/domain/PropertyDefinition';
import type { FieldData } from '@/editor/presentation/SchemaForm';
import { BaseValueFormat, ValidationResult, type ValidationError } from '@/editor/domain/ValueFormat';
import { NumberInputWidgetFactory, type NumberInputWidget } from '@/editor/presentation/Widgets/NumberInputWidgetFactory';
import type { PropertyAttributes } from '@/editor/domain/PropertyDefinitionAttributes';

export interface NumberProperty extends PropertyDefinition {

	readonly precision?: number;
	readonly minimum?: number;
	readonly maximum?: number;

}

export interface NumberAttributes extends PropertyAttributes {
	readonly precision?: number;
	readonly minimum?: number;
	readonly maximum?: number;
}

export class NumberFormat extends BaseValueFormat<NumberProperty, NumberValue, NumberInputWidget, NumberAttributes> {

	public static readonly valueType = ValueType.Number;
	public static readonly formatName = 'number';

	public getExampleValue(): number {
		return 42;
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

	public validate( value: NumberValue, property: NumberProperty ): ValidationResult {
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

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): NumberProperty {
		return {
			...base,
			precision: json.precision,
			minimum: json.minimum,
			maximum: json.maximum
		} as NumberProperty;
	}

	public createFormField( value: NumberValue | undefined, property: NumberProperty ): NumberInputWidget {
		return NumberInputWidgetFactory.create( {
			value: value === undefined ? '' : value.number?.toString(),
			min: property.minimum,
			max: property.maximum,
			required: property.required
		} );
	}

	public async getFieldData( field: NumberInputWidget ): Promise<FieldData> {
		const isValid = ( field.$input[ 0 ] as HTMLInputElement ).checkValidity();

		return {
			value: newNumberValue( field.getNumericValue() ),
			valid: isValid,
			errorMessage: isValid ? undefined : ( field.$input[ 0 ] as HTMLInputElement ).validationMessage
		};
	}

	public getAttributes( base: PropertyAttributes ): NumberAttributes {
		return {
			...base,
			minimum: 0,
			maximum: 100,
			precision: 0
		};
	}

}

export function newNumberProperty( name = 'MyNumberProperty' ): NumberProperty {
	return {
		name: new PropertyName( name ),
		type: ValueType.Number,
		format: NumberFormat.formatName,
		description: '',
		required: false
	};
}
