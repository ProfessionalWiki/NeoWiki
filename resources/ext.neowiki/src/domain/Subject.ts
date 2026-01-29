import { SubjectId } from '@/domain/SubjectId';
import { StatementList } from '@/domain/StatementList';
import type { SubjectLookup } from '@/domain/SubjectLookup';
import type { SchemaName } from '@/domain/Schema';
import type { SubjectMap } from '@/domain/SubjectMap';
import type { PropertyName } from '@/domain/PropertyName';
import type { Value } from '@/domain/Value';

export class Subject {

	public constructor(
		private readonly id: SubjectId,
		private readonly label: string,
		private readonly schemaName: SchemaName,
		private readonly statements: StatementList,
	) {
	}

	public static createNew( label: string, schemaName: SchemaName ): Subject {
		// TODO: The dummy ID is a temporary workaround.
		// Should we make ID optional in Subject or create a separate NewSubject DTO?
		return new Subject(
			new SubjectId( 's11111111111111' ),
			label,
			schemaName,
			new StatementList( [] ),
		);
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

	public async getReferencedSubjects( lookup: SubjectLookup ): Promise<SubjectMap> {
		return this.statements?.getReferencedSubjects( lookup );
	}

	// TODO: test
	public getNamesOfNonEmptyProperties(): PropertyName[] {
		return this.statements.withNonEmptyValues().getPropertyNames();
	}

	public withLabel( label: string ): Subject {
		return new Subject( this.id, label, this.schemaName, this.statements );
	}

	public withStatements( statements: StatementList ): Subject {
		return new Subject( this.id, this.label, this.schemaName, statements );
	}

	public withSchemaName( schemaName: SchemaName ): Subject {
		return new Subject( this.id, this.label, schemaName, this.statements );
	}

}
