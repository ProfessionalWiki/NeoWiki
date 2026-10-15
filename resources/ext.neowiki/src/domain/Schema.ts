import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { Statement } from '@/domain/Statement';
import { StatementList } from '@/domain/StatementList';

export type SchemaName = string;

export class Schema {

	public constructor(
		private readonly name: SchemaName,
		private readonly description: string,
		private readonly properties: PropertyDefinitionList,
		private readonly subjectsDependent: boolean,
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

	public subjectsAreDependent(): boolean {
		return this.subjectsDependent;
	}

	public getPropertyDefinition( propertyName: string|PropertyName ): PropertyDefinition {
		return this.properties.get(
			propertyName instanceof PropertyName ? propertyName : new PropertyName( propertyName ),
		);
	}

	public withName( name: SchemaName ): Schema {
		return new Schema( name, this.description, this.properties, this.subjectsDependent );
	}

	public withDescription( description: string ): Schema {
		return new Schema( this.name, description, this.properties, this.subjectsDependent );
	}

	public withSubjectsAreDependent( subjectsAreDependent: boolean ): Schema {
		return new Schema( this.name, this.description, this.properties, subjectsAreDependent );
	}

	public withAddedPropertyDefinition( property: PropertyDefinition ): Schema {
		return new Schema(
			this.name,
			this.description,
			new PropertyDefinitionList( [ ...this.properties, property ] ),
			this.subjectsDependent,
		);
	}

	public withReorderedPropertyDefinitions( names: PropertyName[] ): Schema {
		return new Schema(
			this.name,
			this.description,
			this.properties.reordered( names ),
			this.subjectsDependent,
		);
	}

	public withRemovedPropertyDefinition( propertyName: PropertyName ): Schema {
		return new Schema(
			this.name,
			this.description,
			this.properties.withoutNames( [ propertyName ] ),
			this.subjectsDependent,
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

	/**
	 * The edited statements, then those of `existing` whose property the Schema does not declare, which a form
	 * built from statementsFrom() leaves out.
	 */
	public statementsAfterEdit( existing: StatementList, edited: StatementList ): StatementList {
		return new StatementList( [
			...edited,
			...[ ...existing ].filter( ( statement ) => !this.properties.has( statement.propertyName ) ),
		] );
	}

}
