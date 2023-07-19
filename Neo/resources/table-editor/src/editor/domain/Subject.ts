import type { SubjectLookup } from '@/editor/application/SubjectLookup';
import type { PageIdentifiers } from '@/editor/domain/PageIdentifiers';
import type { SchemaName } from '@/editor/domain/Schema';
import type { SubjectMap } from '@/editor/domain/SubjectMap';
import type { SubjectId } from '@/editor/domain/SubjectId';
import type { StatementList } from '@/editor/domain/StatementList';
import type { PropertyName } from '@/editor/domain/PropertyDefinition';
import type { Value } from '@/editor/domain/Value';

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
		return this.statements.getReferencedSubjects( lookup );
	}

	// TODO: test
	public getNamesOfNonEmptyProperties(): PropertyName[] {
		return this.statements.withNonEmptyValues().getPropertyNames();
	}

}
