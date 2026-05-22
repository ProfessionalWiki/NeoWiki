import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { type BooleanValue, newBooleanValue, ValueType } from '@/domain/Value';
import { BasePropertyType, ValueValidationError } from '@/domain/PropertyType';

export type BooleanProperty = PropertyDefinition;

export class BooleanType extends BasePropertyType<BooleanProperty, BooleanValue> {

	public static readonly valueType = ValueType.Boolean;

	public static readonly typeName = 'boolean';

	public getDisplayAttributeNames(): string[] {
		return [];
	}

	public getExampleValue(): BooleanValue {
		return newBooleanValue( true );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition ): BooleanProperty {
		return { ...base } as BooleanProperty;
	}

	public validate( value: BooleanValue | undefined, property: BooleanProperty ): ValueValidationError[] {
		const errors: ValueValidationError[] = [];

		if ( property.required && value === undefined ) {
			errors.push( { code: 'required' } );
		}

		return errors;
	}

}

type BooleanPropertyAttributes = Omit<Partial<BooleanProperty>, 'name'> & {
	name?: string | PropertyName;
};

export function newBooleanProperty( attributes: BooleanPropertyAttributes = {} ): BooleanProperty {
	return {
		name: attributes.name instanceof PropertyName ? attributes.name : new PropertyName( attributes.name || 'Boolean' ),
		type: BooleanType.typeName,
		description: attributes.description ?? '',
		required: attributes.required ?? false,
		default: attributes.default,
	};
}
