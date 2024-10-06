import { PropertyDefinition } from '@neo/domain/PropertyDefinition';
import type { Value } from '@neo/domain/Value';
import { ValueType } from '@neo/domain/Value';

export abstract class BaseValueFormat<T extends PropertyDefinition, V extends Value> {

	public static readonly valueType: ValueType;

	public static readonly formatName: string;

	public getFormatName(): string {
		return ( this.constructor as typeof BaseValueFormat ).formatName;
	}

	public getValueType(): ValueType {
		return ( this.constructor as typeof BaseValueFormat ).valueType;
	}

	public abstract createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): T;

	public abstract getExampleValue( property: T ): V;

}

export type ValueFormat = BaseValueFormat<PropertyDefinition, Value>;

export class ValueFormatRegistry {

	private propertyTypes: Map<string, ValueFormat> = new Map();

	public registerFormat( format: ValueFormat ): void {
		this.propertyTypes.set( format.getFormatName(), format );
	}

	public getFormat( formatName: string ): ValueFormat {
		const format = this.propertyTypes.get( formatName );

		if ( format === undefined ) {
			throw new Error( 'Unknown value format: ' + formatName );
		}

		return format;
	}

	public getFormatNames(): string[] {
		return Array.from( this.propertyTypes.keys() );
	}

	public getFormats(): ValueFormat[] {
		return Array.from( this.propertyTypes.values() );
	}

}
