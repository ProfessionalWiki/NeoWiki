import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newNumberValue, type NumberValue, ValueType } from '@/domain/Value';
import { BasePropertyType } from '@/domain/PropertyType';

export interface NumberProperty extends PropertyDefinition {

	readonly precision?: number;
	readonly minimum?: number;
	readonly maximum?: number;

}

export class NumberType extends BasePropertyType<NumberProperty, NumberValue> {

	public static readonly valueType = ValueType.Number;

	public static readonly typeName = 'number';

	public getDisplayAttributeNames(): string[] {
		return [ 'precision' ];
	}

	public getExampleValue(): NumberValue {
		return newNumberValue( 42 );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): NumberProperty {
		return {
			...base,
			precision: json.precision ?? undefined,
			minimum: json.minimum ?? undefined,
			maximum: json.maximum ?? undefined,
		} as NumberProperty;
	}

}

type NumberPropertyAttributes = Omit<Partial<NumberProperty>, 'name'> & {
	name?: string | PropertyName;
};

export function newNumberProperty( attributes: NumberPropertyAttributes = {} ): NumberProperty {
	return {
		name: attributes.name instanceof PropertyName ? attributes.name : new PropertyName( attributes.name || 'Number' ),
		type: NumberType.typeName,
		description: attributes.description ?? '',
		required: attributes.required ?? false,
		default: attributes.default,
		precision: attributes.precision,
		minimum: attributes.minimum,
		maximum: attributes.maximum,
	};
}
