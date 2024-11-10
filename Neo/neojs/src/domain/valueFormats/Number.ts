import type { PropertyDefinition } from '@neo/domain/PropertyDefinition';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { newNumberValue, type NumberValue, ValueType } from '@neo/domain/Value';
import { BaseValueFormat, ValueValidationError } from '@neo/domain/ValueFormat';

export interface NumberProperty extends PropertyDefinition {

	readonly precision?: number;
	readonly minimum?: number;
	readonly maximum?: number;

}

export class NumberFormat extends BaseValueFormat<NumberProperty, NumberValue> {

	public static readonly valueType = ValueType.Number;

	public static readonly formatName = 'number';

	public getExampleValue(): NumberValue {
		return newNumberValue( 42 );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): NumberProperty {
		return {
			...base,
			precision: json.precision,
			minimum: json.minimum,
			maximum: json.maximum
		} as NumberProperty;
	}

	public validate( value: NumberValue | undefined, property: NumberProperty ): ValueValidationError[] {
		const errors: ValueValidationError[] = [];

		if ( property.required && value === undefined ) {
			errors.push( { code: 'required' } );
			return errors;
		}

		if ( value !== undefined ) {
			if ( property.minimum !== undefined && value.number < property.minimum ) {
				errors.push( {
					code: 'min-value',
					args: [ property.minimum ]
				} );
			}
			if ( property.maximum !== undefined && value.number > property.maximum ) {
				errors.push( {
					code: 'max-value',
					args: [ property.maximum ]
				} );
			}
		}

		return errors;
	}

}

type NumberPropertyAttributes = Omit<Partial<NumberProperty>, 'name'> & {
	name?: string | PropertyName;
};

export function newNumberProperty( attributes: NumberPropertyAttributes = {} ): NumberProperty {
	return {
		name: attributes.name instanceof PropertyName ? attributes.name : new PropertyName( attributes.name || 'Number' ),
		format: NumberFormat.formatName,
		description: attributes.description ?? '',
		required: attributes.required ?? false,
		default: attributes.default,
		precision: attributes.precision,
		minimum: attributes.minimum,
		maximum: attributes.maximum
	};
}
