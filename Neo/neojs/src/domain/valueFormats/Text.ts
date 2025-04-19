import type { MultiStringProperty, PropertyDefinition } from '@neo/domain/PropertyDefinition';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@neo/domain/Value';
import { BasePropertyType, ValueValidationError } from '@neo/domain/PropertyType';

export interface TextProperty extends MultiStringProperty {

	readonly maxLength?: number;
	readonly minLength?: number;

}

export class TextFormat extends BasePropertyType<TextProperty, StringValue> {

	public static readonly valueType = ValueType.String;

	public static readonly typeName = 'text';

	public getExampleValue(): StringValue {
		return newStringValue( 'Some Text' );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): TextProperty {
		return {
			...base,
			multiple: json.multiple ?? false,
			uniqueItems: json.uniqueItems ?? true
		} as TextProperty;
	}

	public validate( value: StringValue | undefined, property: TextProperty ): ValueValidationError[] {
		const errors: ValueValidationError[] = [];
		value = value === undefined ? newStringValue() : value;

		if ( property.required && value.strings.length === 0 ) {
			errors.push( { code: 'required' } );
			return errors;
		}

		// TODO: check property.multiple

		for ( const part of value.strings ) {
			if ( property.minLength !== undefined && part.trim().length < property.minLength ) {
				errors.push( {
					code: 'min-length',
					args: [ property.minLength ],
					source: part
				} );
			}

			if ( property.maxLength !== undefined && part.trim().length > property.maxLength ) {
				errors.push( {
					code: 'max-length',
					args: [ property.maxLength ],
					source: part
				} );
			}
		}

		if ( property.uniqueItems && new Set( value.strings ).size !== value.strings.length ) {
			errors.push( { code: 'unique' } );  // TODO: add source
		}

		return errors;
	}

}

type TextPropertyAttributes = Omit<Partial<TextProperty>, 'name'> & {
	name?: string | PropertyName;
};

export function newTextProperty( attributes: TextPropertyAttributes = {} ): TextProperty {
	return {
		name: attributes.name instanceof PropertyName ? attributes.name : new PropertyName( attributes.name || 'Text' ),
		format: TextFormat.typeName,
		description: attributes.description ?? '',
		required: attributes.required ?? false,
		default: attributes.default,
		multiple: attributes.multiple ?? false,
		uniqueItems: attributes.uniqueItems ?? true,
		maxLength: attributes.maxLength,
		minLength: attributes.minLength
	};
}
