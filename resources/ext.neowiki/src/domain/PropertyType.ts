import { PropertyDefinition } from '@/domain/PropertyDefinition';
import type { Value } from '@/domain/Value';
import { ValueType } from '@/domain/Value';

export abstract class BasePropertyType<P extends PropertyDefinition, V extends Value> {

	public static readonly valueType: ValueType;

	public static readonly typeName: string;

	public getTypeName(): string {
		return ( this.constructor as typeof BasePropertyType ).typeName;
	}

	public getValueType(): ValueType {
		return ( this.constructor as typeof BasePropertyType ).valueType;
	}

	public abstract getDisplayAttributeNames(): string[];

	public abstract createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): P;

	public abstract getExampleValue( property: P ): V;

}

export type PropertyType = BasePropertyType<PropertyDefinition, Value>;

export class PropertyTypeRegistry {

	private propertyTypes: Map<string, PropertyType> = new Map();

	public registerType( type: PropertyType ): void {
		this.propertyTypes.set( type.getTypeName(), type );
	}

	public getType( typeName: string ): PropertyType {
		const type = this.propertyTypes.get( typeName );

		if ( type === undefined ) {
			throw new Error( 'Unknown property type: ' + typeName );
		}

		return type;
	}

	public hasType( typeName: string ): boolean {
		return this.propertyTypes.has( typeName );
	}

	public getTypeNames(): string[] {
		return Array.from( this.propertyTypes.keys() );
	}

}
