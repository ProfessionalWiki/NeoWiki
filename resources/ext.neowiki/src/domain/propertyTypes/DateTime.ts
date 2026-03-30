import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@/domain/Value';
import { BasePropertyType, ValueValidationError } from '@/domain/PropertyType';

export interface DateTimeProperty extends PropertyDefinition {

	readonly minimum?: string;
	readonly maximum?: string;

}

export class DateTimeType extends BasePropertyType<DateTimeProperty, StringValue> {

	public static readonly valueType = ValueType.String;

	public static readonly typeName = 'dateTime';

	public getDisplayAttributeNames(): string[] {
		return [];
	}

	public getExampleValue(): StringValue {
		return newStringValue( '2026-01-01T12:00:00Z' );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): DateTimeProperty {
		return {
			...base,
			minimum: json.minimum,
			maximum: json.maximum,
		} as DateTimeProperty;
	}

	public validate( value: StringValue | undefined, property: DateTimeProperty ): ValueValidationError[] {
		const errors: ValueValidationError[] = [];

		if ( property.required && value === undefined ) {
			errors.push( { code: 'required' } );
			return errors;
		}

		if ( value !== undefined && value.parts.length > 0 ) {
			const dateString = value.parts[ 0 ];
			const timestamp = Date.parse( dateString );

			if ( isNaN( timestamp ) ) {
				errors.push( { code: 'invalid-datetime' } );
				return errors;
			}

			if ( property.minimum !== undefined && timestamp < Date.parse( property.minimum ) ) {
				errors.push( {
					code: 'min-value',
					args: [ property.minimum ],
				} );
			}

			if ( property.maximum !== undefined && timestamp > Date.parse( property.maximum ) ) {
				errors.push( {
					code: 'max-value',
					args: [ property.maximum ],
				} );
			}
		}

		return errors;
	}

}

type DateTimePropertyAttributes = Omit<Partial<DateTimeProperty>, 'name'> & {
	name?: string | PropertyName;
};

export function newDateTimeProperty( attributes: DateTimePropertyAttributes = {} ): DateTimeProperty {
	return {
		name: attributes.name instanceof PropertyName ? attributes.name : new PropertyName( attributes.name || 'DateTime' ),
		type: DateTimeType.typeName,
		description: attributes.description ?? '',
		required: attributes.required ?? false,
		default: attributes.default,
		minimum: attributes.minimum,
		maximum: attributes.maximum,
	};
}
