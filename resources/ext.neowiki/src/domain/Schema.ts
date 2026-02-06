import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';

export type SchemaName = string;

export class Schema {

	public constructor(
		private readonly name: SchemaName,
		private readonly description: string,
		private readonly properties: PropertyDefinitionList,
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
			propertyName instanceof PropertyName ? propertyName : new PropertyName( propertyName ),
		);
	}

	public withName( name: SchemaName ): Schema {
		return new Schema( name, this.description, this.properties );
	}

	public withDescription( description: string ): Schema {
		return new Schema( this.name, description, this.properties );
	}

	public withAddedPropertyDefinition( property: PropertyDefinition ): Schema {
		return new Schema(
			this.name,
			this.description,
			new PropertyDefinitionList( [ ...this.properties, property ] ),
		);
	}

	public withRemovedPropertyDefinition( propertyName: PropertyName ): Schema {
		return new Schema(
			this.name,
			this.description,
			this.properties.withoutNames( [ propertyName ] ),
		);
	}

}
