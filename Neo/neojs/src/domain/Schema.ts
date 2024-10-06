import type { PropertyDefinition } from '@neo/domain/PropertyDefinition';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import type { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList';

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

}
