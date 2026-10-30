import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@/domain/Value';
import { BasePropertyType } from '@/domain/PropertyType';

export type EdtfProperty = PropertyDefinition;

export class EdtfType extends BasePropertyType<EdtfProperty, StringValue> {

	public static readonly valueType = ValueType.String;

	public static readonly typeName = 'edtf';

	public getDisplayAttributeNames(): string[] {
		return [];
	}

	public getExampleValue(): StringValue {
		return newStringValue( '15XX-12-25' );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition ): EdtfProperty {
		return { ...base } as EdtfProperty;
	}

}

type EdtfPropertyAttributes = Omit<Partial<EdtfProperty>, 'name'> & {
	name?: string | PropertyName;
};

export function newEdtfProperty( attributes: EdtfPropertyAttributes = {} ): EdtfProperty {
	return {
		name: attributes.name instanceof PropertyName ? attributes.name : new PropertyName( attributes.name || 'EDTF' ),
		type: EdtfType.typeName,
		description: attributes.description ?? '',
		required: attributes.required ?? false,
		default: attributes.default
	};
}
