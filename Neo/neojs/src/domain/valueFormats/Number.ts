import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newNumberValue, type NumberValue, ValueType } from '@/domain/Value';
import { BaseValueFormat } from '@/domain/ValueFormat';

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
