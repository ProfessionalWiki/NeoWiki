import type { MultiStringProperty, PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { type MonolingualTextValue, newMonolingualTextValue, ValueType } from '@/domain/Value';
import { BasePropertyType } from '@/domain/PropertyType';

export interface MonolingualTextProperty extends MultiStringProperty {

	readonly maxLength?: number;
	readonly minLength?: number;

}

export class MonolingualTextType extends BasePropertyType<MonolingualTextProperty, MonolingualTextValue> {

	public static readonly valueType = ValueType.MonolingualText;

	public static readonly typeName = 'monolingualText';

	public getDisplayAttributeNames(): string[] {
		return [];
	}

	public getExampleValue(): MonolingualTextValue {
		return newMonolingualTextValue( [ { text: 'Some Text', language: 'en' } ] );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): MonolingualTextProperty {
		return {
			...base,
			multiple: json.multiple ?? false,
			uniqueItems: json.uniqueItems ?? false,
			minLength: json.minLength ?? undefined,
			maxLength: json.maxLength ?? undefined,
		} as MonolingualTextProperty;
	}

}

type MonolingualTextPropertyAttributes = Omit<Partial<MonolingualTextProperty>, 'name'> & {
	name?: string | PropertyName;
};

export function newMonolingualTextProperty(
	attributes: MonolingualTextPropertyAttributes = {},
): MonolingualTextProperty {
	return {
		name: attributes.name instanceof PropertyName ?
			attributes.name :
			new PropertyName( attributes.name || 'Monolingual text' ),
		type: MonolingualTextType.typeName,
		description: attributes.description ?? '',
		required: attributes.required ?? false,
		default: attributes.default,
		multiple: attributes.multiple ?? false,
		uniqueItems: attributes.uniqueItems ?? true,
		maxLength: attributes.maxLength,
		minLength: attributes.minLength,
	};
}
