import type { MultiStringProperty, PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@/domain/Value';
import { BasePropertyType } from '@/domain/PropertyType';
import type { Constraint } from '@/domain/Constraint';

export interface TextProperty extends MultiStringProperty {

	readonly maxLength?: number;
	readonly minLength?: number;

}

export class TextType extends BasePropertyType<TextProperty, StringValue> {

	public static readonly valueType = ValueType.String;

	public static readonly typeName = 'text';

	public getDisplayAttributeNames(): string[] {
		return [];
	}

	public getExampleValue(): StringValue {
		return newStringValue( 'Some Text' );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): TextProperty {
		return {
			...base,
			multiple: json.multiple ?? false,
			uniqueItems: json.uniqueItems ?? true,
			minLength: json.minLength,
			maxLength: json.maxLength,
		} as TextProperty;
	}

	public getConstraints( property: TextProperty ): Constraint[] {
		const constraints: Constraint[] = [];
		if ( property.required ) {
			constraints.push( { kind: 'required' } );
		}
		if ( property.minLength !== undefined ) {
			constraints.push( { kind: 'minLength', value: property.minLength } );
		}
		if ( property.maxLength !== undefined ) {
			constraints.push( { kind: 'maxLength', value: property.maxLength } );
		}
		if ( property.uniqueItems ) {
			constraints.push( { kind: 'uniqueItems' } );
		}
		// TODO: check property.multiple
		return constraints;
	}

}

type TextPropertyAttributes = Omit<Partial<TextProperty>, 'name'> & {
	name?: string | PropertyName;
};

export function newTextProperty( attributes: TextPropertyAttributes = {} ): TextProperty {
	return {
		name: attributes.name instanceof PropertyName ? attributes.name : new PropertyName( attributes.name || 'Text' ),
		type: TextType.typeName,
		description: attributes.description ?? '',
		required: attributes.required ?? false,
		default: attributes.default,
		multiple: attributes.multiple ?? false,
		uniqueItems: attributes.uniqueItems ?? true,
		maxLength: attributes.maxLength,
		minLength: attributes.minLength,
	};
}
