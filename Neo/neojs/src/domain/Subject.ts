import type { SubjectLookup } from '@neo/domain/SubjectLookup';
import type { PageIdentifiers } from '@neo/domain/PageIdentifiers';
import type { SchemaName } from '@neo/domain/Schema';
import type { SubjectMap } from '@neo/domain/SubjectMap';
import type { SubjectId } from '@neo/domain/SubjectId';
import type { StatementList } from '@neo/domain/StatementList';
import type { PropertyName } from '@neo/domain/PropertyDefinition';
import type { Value } from '@neo/domain/Value';

/**
 * @deprecated Use {@link StatementList} instead.
 */
export type SubjectProperties = Record<string, unknown>; // TODO: remove

export class Subject {

	public constructor(
		private readonly id: SubjectId,
		private readonly label: string,
		private readonly schemaName: SchemaName,
		private readonly statements: StatementList,
		private readonly pageIdentifiers: PageIdentifiers
	) {
	}

	public getId(): SubjectId {
		return this.id;
	}

	public getLabel(): string {
		return this.label;
	}

	public getSchemaName(): SchemaName {
		return this.schemaName;
	}

	public getStatements(): StatementList {
		return this.statements;
	}

	public getStatementValue( propertyName: PropertyName ): Value | undefined {
		return this.statements.get( propertyName ).value;
	}

	public getPageIdentifiers(): PageIdentifiers {
		return this.pageIdentifiers;
	}

	public async getReferencedSubjects( lookup: SubjectLookup ): Promise<SubjectMap> {
		return this.statements?.getReferencedSubjects( lookup );
	}

	// TODO: test
	public getNamesOfNonEmptyProperties(): PropertyName[] {
		return this.statements.withNonEmptyValues().getPropertyNames();
	}

	public withLabel( label: string ): Subject {
		return new Subject( this.id, label, this.schemaName, this.statements, this.pageIdentifiers );
	}

	public withStatements( statements: StatementList ): Subject {
		return new Subject( this.id, this.label, this.schemaName, statements, this.pageIdentifiers );
	}

	public withSchemaName( schemaName: SchemaName ): Subject {
		return new Subject( this.id, this.label, schemaName, this.statements, this.pageIdentifiers );
	}

}
