import type { PropertyDefinition } from '@neo/domain/PropertyDefinition';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { newNumberValue, type NumberValue, ValueType } from '@neo/domain/Value';
import { BaseValueFormat } from '@neo/domain/ValueFormat';
import { UrlFormat } from '@neo/domain/valueFormats/Url';

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

type NumberPropertyAttributes = Omit<Partial<NumberProperty>, 'name'> & {
	name?: string | PropertyName;
};

export function newNumberProperty( attributes: string|NumberPropertyAttributes = {} ): NumberProperty {
	if ( typeof attributes === 'string' ) { // TODO: remove deprecated form
		return {
			name: new PropertyName( attributes ),
			format: NumberFormat.formatName,
			description: '',
			required: false
		};
	}

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
