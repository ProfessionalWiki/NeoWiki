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
		private readonly displayName: string,
		private readonly schemaName: SchemaName,
		private readonly statements: StatementList,
	) {
	}

	public getId(): SubjectId {
		return this.id;
	}

	/**
	 * The label as stored, which is null for a Subject that has none. Displays want
	 * getDisplayName() instead; this is for editors, which must round-trip the stored value.
	 */
	public getLabel(): string | null {
		return this.label;
	}

	/**
	 * The name to show for this Subject: its stored label, or the fallback the server derived
	 * from the page name or the Schema name when there is none.
	 */
	public getDisplayName(): string {
		return this.displayName;
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

	/**
	 * A stored label is its own display name, so setting one sets both. Clearing one keeps the
	 * display name the server last derived, since only the server can derive a new one.
	 */
	public withLabel( label: string | null ): Subject {
		return new Subject( this.id, label, label ?? this.displayName, this.schemaName, this.statements );
	}

	public withStatements( statements: StatementList ): Subject {
		return new Subject( this.id, this.label, this.displayName, this.schemaName, statements );
	}

	public withSchemaName( schemaName: SchemaName ): Subject {
		return new Subject( this.id, this.label, this.displayName, schemaName, this.statements );
	}

}
