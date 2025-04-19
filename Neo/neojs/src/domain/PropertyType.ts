import { PropertyDefinition } from '@neo/domain/PropertyDefinition';
import type { Value } from '@neo/domain/Value';
import { ValueType } from '@neo/domain/Value';

export abstract class BaseValueFormat<P extends PropertyDefinition, V extends Value> {

	public static readonly valueType: ValueType;

	public static readonly formatName: string;

	public getFormatName(): string {
		return ( this.constructor as typeof BaseValueFormat ).formatName;
	}

	public getValueType(): ValueType {
		return ( this.constructor as typeof BaseValueFormat ).valueType;
	}

	public abstract createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): P;

	public abstract getExampleValue( property: P ): V;

	// TODO: do we need to allow undefined for value?
	public abstract validate( value: V | undefined, property: P ): ValueValidationError[];

}

export interface ValueValidationError {

	/**
	 * Can be used to construct a message key for i18n by prefixing it with 'neowiki-field-'
	 */
	code: string;

	/**
	 * Arguments for the message
	 */
	args?: unknown[];

	/**
	 * The source/cause of the error
	 */
	source?: unknown;

}

export type PropertyType = BaseValueFormat<PropertyDefinition, Value>;

export class PropertyTypeRegistry {

	private propertyTypes: Map<string, PropertyType> = new Map();

	public registerType( format: PropertyType ): void {
		this.propertyTypes.set( format.getFormatName(), format );
	}

	public getType( typeName: string ): PropertyType {
		const type = this.propertyTypes.get( typeName );

		if ( type === undefined ) {
			throw new Error( 'Unknown property type: ' + typeName );
		}

		return type;
	}

	public getTypeNames(): string[] {
		return Array.from( this.propertyTypes.keys() );
	}

	public getTypes(): PropertyType[] {
		return Array.from( this.propertyTypes.values() );
	}

}
