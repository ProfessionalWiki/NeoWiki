import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@/domain/Value';
import { BasePropertyType, ValueValidationError } from '@/domain/PropertyType';

export interface SelectProperty extends PropertyDefinition {

	readonly options: string[];
	readonly multiple: boolean;

}

export class SelectType extends BasePropertyType<SelectProperty, StringValue> {

	public static readonly valueType = ValueType.String;

	public static readonly typeName = 'select';

	public getDisplayAttributeNames(): string[] {
		return [];
	}

	public getExampleValue( property: SelectProperty ): StringValue {
		return newStringValue( property.options[ 0 ] ?? '' );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): SelectProperty {
		return {
			...base,
			options: json.options ?? [],
			multiple: json.multiple ?? false,
		} as SelectProperty;
	}

	public validate( value: StringValue | undefined, property: SelectProperty ): ValueValidationError[] {
		const errors: ValueValidationError[] = [];
		value = value === undefined ? newStringValue() : value;

		if ( property.required && value.parts.length === 0 ) {
			errors.push( { code: 'required' } );
			return errors;
		}

		for ( const part of value.parts ) {
			if ( !property.options.includes( part ) ) {
				errors.push( {
					code: 'invalid-option',
					args: [ part ],
					source: part,
				} );
			}
		}

		if ( !property.multiple && value.parts.length > 1 ) {
			errors.push( { code: 'single-value-only' } );
		}

		return errors;
	}

}

type SelectPropertyAttributes = Omit<Partial<SelectProperty>, 'name'> & {
	name?: string | PropertyName;
};

export function newSelectProperty( attributes: SelectPropertyAttributes = {} ): SelectProperty {
	return {
		name: attributes.name instanceof PropertyName ? attributes.name : new PropertyName( attributes.name || 'Select' ),
		type: SelectType.typeName,
		description: attributes.description ?? '',
		required: attributes.required ?? false,
		default: attributes.default,
		options: attributes.options ?? [],
		multiple: attributes.multiple ?? false,
	};
}
