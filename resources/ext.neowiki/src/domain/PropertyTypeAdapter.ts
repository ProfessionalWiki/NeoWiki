import { BasePropertyType } from '@/domain/PropertyType';
import type { ValueValidationError } from '@/domain/PropertyType';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import type { Value, ValueType } from '@/domain/Value';
import type { PropertyTypeRegistration } from '@/domain/PropertyTypeRegistration';

export class PropertyTypeAdapter extends BasePropertyType<PropertyDefinition, Value> {

	public static readonly typeName: string = '';

	public static readonly valueType: ValueType = '' as ValueType;

	public constructor( private readonly registration: PropertyTypeRegistration ) {
		super();
	}

	public getTypeName(): string {
		return this.registration.typeName;
	}

	public getValueType(): ValueType {
		return this.registration.valueType as ValueType;
	}

	public getDisplayAttributeNames(): string[] {
		return this.registration.displayAttributeNames;
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): PropertyDefinition {
		return this.registration.createPropertyDefinition( base, json );
	}

	public getExampleValue( property: PropertyDefinition ): Value {
		return this.registration.getExampleValue( property );
	}

	public validate( value: Value | undefined, property: PropertyDefinition ): ValueValidationError[] {
		return this.registration.validate( value, property );
	}

}
