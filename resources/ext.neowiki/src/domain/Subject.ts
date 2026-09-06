import { SubjectId } from '@/domain/SubjectId';
import { StatementList } from '@/domain/StatementList';
import type { SubjectLookup } from '@/domain/SubjectLookup';
import type { SchemaName } from '@/domain/Schema';
import type { SubjectMap } from '@/domain/SubjectMap';
import type { PropertyName } from '@/domain/PropertyDefinition';
import type { Value } from '@/domain/Value';

export class Subject {

	public constructor(
		private readonly id: SubjectId,
		private readonly label: string | null,
		private readonly schemaName: SchemaName,
		private readonly statements: StatementList,
	) {
	}

	public getId(): SubjectId {
		return this.id;
	}

	/**
	 * The label as stored, which is null for a Subject nobody named. What to show instead depends
	 * on where the Subject sits, which SubjectWithContext knows; displays go through
	 * presentation/subjectDisplayName.ts.
	 */
	public getLabel(): string | null {
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

	public async getReferencedSubjects( lookup: SubjectLookup ): Promise<SubjectMap> {
		return this.statements?.getReferencedSubjects( lookup );
	}

	// TODO: test
	public getNamesOfNonEmptyProperties(): PropertyName[] {
		return this.statements.withNonEmptyValues().getPropertyNames();
	}

	/** A copy under the given label, null clearing whatever label the Subject had. */
	public withLabel( label: string | null ): Subject {
		return new Subject( this.id, label, this.schemaName, this.statements );
	}

	public withStatements( statements: StatementList ): Subject {
		return new Subject( this.id, this.label, this.schemaName, statements );
	}

	public withSchemaName( schemaName: SchemaName ): Subject {
		return new Subject( this.id, this.label, schemaName, this.statements );
	}

}
