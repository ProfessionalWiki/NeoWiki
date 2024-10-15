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

export function newNumberProperty( options: string|Partial<NumberProperty> = {} ): NumberProperty {
	if ( typeof options === 'string' ) { // TODO: remove deprecated form
		return {
			name: new PropertyName( options ),
			format: NumberFormat.formatName,
			description: '',
			required: false
		};
	}

	return {
		name: options.name instanceof PropertyName ? options.name : new PropertyName( options.name || 'number' ),
		format: UrlFormat.formatName,
		description: options.description || '',
		required: options.required || false,
		default: options.default || undefined,
		precision: options.precision || undefined,
		minimum: options.minimum || undefined,
		maximum: options.maximum || undefined
	};
}
