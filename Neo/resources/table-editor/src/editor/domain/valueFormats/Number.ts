import type { PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { PropertyName } from '@/editor/domain/PropertyDefinition';
import { type NumberValue, ValueType } from '@/editor/domain/Value';
import { BaseValueFormat, ValidationResult } from '@/editor/domain/ValueFormat';

export interface NumberProperty extends PropertyDefinition {

	readonly precision?: number;
	readonly minimum?: number;
	readonly maximum?: number;

}

export class NumberFormat extends BaseValueFormat<NumberProperty, NumberValue> {

	public static readonly valueType = ValueType.Number;
	public static readonly formatName = 'number';

	public validate( value: NumberValue, property: NumberProperty ): ValidationResult {
		return new ValidationResult( [] ); // TODO
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): NumberProperty {
		return {
			...base,
			precision: json.precision,
			minimum: json.minimum,
			maximum: json.maximum
		} as NumberProperty;
	}

	public createFormField( value: NumberValue | undefined, property: NumberProperty ): any {
		const options: any = {
			type: 'number',
			value: value === undefined ? '' : value.number.toString(),
			min: property.minimum,
			max: property.maximum,
			required: property.required
		};

		// FIXME: this does not work, and even without this code, NumberInputWidget is not allowing decimals
		if ( property.precision !== undefined ) {
			options.step = 1 / Math.pow( 10, property.precision );
		}

		const widget = new OO.ui.NumberInputWidget( options );

		setTimeout( () => {
			widget.setFlags( { invalid: false } );
		} );

		return widget;
	}

	public formatValueAsHtml( value: NumberValue, property: NumberProperty ): string {
		if ( value.number === undefined ) {
			// TODO: handle values that are not saved as NumberValue
			return '';
		}

		if ( property.precision !== undefined ) {
			return value.number.toFixed( property.precision );
		}

		return value.number.toString();
	}

}

// TODO: use or remove
export function isValidNumber( number: string, required = false ): boolean {
	if ( !required && number === '' ) {
		return true;
	}
	const pattern = /^\s*-?\d+(\.\d+)?\s*$/;
	return pattern.test( number );
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
