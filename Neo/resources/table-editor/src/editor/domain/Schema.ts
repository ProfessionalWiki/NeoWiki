import type { PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { PropertyName } from '@/editor/domain/PropertyDefinition';
import type { PropertyDefinitionList } from '@/editor/domain/PropertyDefinitionList';
import type { ValueType } from '@/editor/domain/Value';

export type SchemaName = string;

export class Schema {

	public constructor(
		private readonly name: SchemaName,
		private readonly description: string,
		private readonly properties: PropertyDefinitionList
	) {
	}

	public getName(): SchemaName {
		return this.name;
	}

	public getDescription(): string {
		return this.description;
	}

	public getPropertyDefinitions(): PropertyDefinitionList {
		return this.properties;
	}

	public getPropertyDefinition( propertyName: string|PropertyName ): PropertyDefinition {
		return this.properties.get(
			propertyName instanceof PropertyName ? propertyName : new PropertyName( propertyName )
		);
	}

	public getTypeOf( propertyName: PropertyName ): ValueType|undefined {
		return this.properties.has( propertyName ) ? this.properties.get( propertyName ).type : undefined;
	}

}
