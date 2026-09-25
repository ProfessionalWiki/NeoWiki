import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { Statement } from '@/domain/Statement';
import { StatementList } from '@/domain/StatementList';
import { withRenamedPlaceholder } from '@/domain/LabelTemplate';

export type SchemaName = string;

export class Schema {

	public constructor(
		private readonly name: SchemaName,
		private readonly description: string,
		private readonly properties: PropertyDefinitionList,
		/**
		 * How the Schema labels a Subject nobody typed a label for, as `{Property name}`
		 * placeholders in text; null when it has none.
		 */
		private readonly labelTemplate: string | null,
	) {
	}

	public getName(): SchemaName {
		return this.name;
	}

	public getDescription(): string {
		return this.description;
	}

	public getLabelTemplate(): string | null {
		return this.labelTemplate;
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
		return new Schema( name, this.description, this.properties, this.labelTemplate );
	}

	public withDescription( description: string ): Schema {
		return new Schema( this.name, description, this.properties, this.labelTemplate );
	}

	public withAddedPropertyDefinition( property: PropertyDefinition ): Schema {
		return new Schema(
			this.name,
			this.description,
			new PropertyDefinitionList( [ ...this.properties, property ] ),
			this.labelTemplate,
		);
	}

	public withReorderedPropertyDefinitions( names: PropertyName[] ): Schema {
		return new Schema(
			this.name,
			this.description,
			this.properties.reordered( names ),
			this.labelTemplate,
		);
	}

	/**
	 * A rename carries over to the placeholders of the label template, which would otherwise name a
	 * property the Schema no longer has.
	 */
	public withReplacedPropertyDefinition( previousName: PropertyName, property: PropertyDefinition ): Schema {
		return new Schema(
			this.name,
			this.description,
			new PropertyDefinitionList(
				[ ...this.properties ].map( ( existing ) => existing.name.toString() === previousName.toString() ? property : existing ),
			),
			this.labelTemplate === null ?
				null :
				withRenamedPlaceholder( this.labelTemplate, previousName.toString(), property.name.toString() ),
		);
	}

	public withRemovedPropertyDefinition( propertyName: PropertyName ): Schema {
		return new Schema(
			this.name,
			this.description,
			this.properties.withoutNames( [ propertyName ] ),
			this.labelTemplate,
		);
	}

	public statementsFrom( existing: StatementList ): StatementList {
		return new StatementList(
			[ ...this.properties ].map( ( definition ) =>
				existing.has( definition.name ) ?
					existing.get( definition.name ) :
					new Statement( definition.name, definition.type, undefined ),
			),
		);
	}

	public blankStatements(): StatementList {
		return this.statementsFrom( new StatementList( [] ) );
	}

}
