import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { type MonolingualTextValue, newMonolingualTextValue, ValueType } from '@/domain/Value';
import { BasePropertyType } from '@/domain/PropertyType';

export interface MonolingualTextProperty extends PropertyDefinition {

	readonly multiple: boolean;
	readonly uniqueLanguages: boolean;
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
			uniqueLanguages: json.uniqueLanguages ?? false,
			minLength: json.minLength ?? undefined,
			maxLength: json.maxLength ?? undefined,
		} as MonolingualTextProperty;
	}

	public override createPropertyDefinition( base: PropertyDefinition ): MonolingualTextProperty {
		return this.createPropertyDefinitionFromJson( base, { multiple: true, uniqueLanguages: true } );
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
		uniqueLanguages: attributes.uniqueLanguages ?? false,
		maxLength: attributes.maxLength,
		minLength: attributes.minLength,
	};
}
