import { BasePropertyType } from '@/domain/PropertyType';
import type { ValueValidationError } from '@/domain/PropertyType';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import type { Value, ValueType } from '@/domain/Value';
import type { PropertyTypeRegistration } from '@/domain/PropertyTypeRegistration';

/**
 * Wraps a plain-object PropertyTypeRegistration from a frontend extension hook
 * into a BasePropertyType instance for use in NeoWiki's internal registries.
 *
 * BasePropertyType relies on a static `typeName` field read as
 * `( this.constructor as typeof BasePropertyType ).typeName`. A single
 * adapter class cannot satisfy that for many registrations, so getTypeName()
 * and getValueType() are overridden per-instance from the registration data.
 */
export class PropertyTypeAdapter extends BasePropertyType<PropertyDefinition, Value> {

	public constructor( private readonly registration: PropertyTypeRegistration ) {
		super();
	}

	public override getTypeName(): string {
		return this.registration.typeName;
	}

	public override getValueType(): ValueType {
		return this.registration.valueType;
	}

	public getDisplayAttributeNames(): string[] {
		return this.registration.displayAttributeNames;
	}

	// eslint-disable-next-line @typescript-eslint/no-explicit-any -- matches the abstract signature in BasePropertyType; the underlying registration uses `unknown`
	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): PropertyDefinition {
		return this.registration.createPropertyDefinitionFromJson( base, json );
	}

	public getExampleValue( property: PropertyDefinition ): Value {
		return this.registration.getExampleValue( property );
	}

	public validate( value: Value | undefined, property: PropertyDefinition ): ValueValidationError[] {
		return this.registration.validate( value, property );
	}

}
