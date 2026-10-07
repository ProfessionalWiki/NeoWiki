import { SubjectId } from '@/domain/SubjectId';
import { StatementList } from '@/domain/StatementList';
import type { SchemaName } from '@/domain/Schema';
import type { PageIdentifiers } from '@/domain/PageIdentifiers';
import type { PropertyName } from '@/domain/PropertyDefinition';
import type { Value } from '@/domain/Value';

export class Subject {

	public constructor(
		private readonly id: SubjectId,
		private readonly label: string | null,
		private readonly displayName: string,
		private readonly displayNameIsGenerated: boolean,
		private readonly schemaName: SchemaName,
		private readonly statements: StatementList,
		private readonly pageIdentifiers: PageIdentifiers,
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
	 * The stored label, or the fallback the server derived from the page name or the Schema name when
	 * there is none. Displays go through presentation/subjectDisplayName.ts, which shows a Schema-name
	 * fallback as the Subject's id.
	 */
	public getDisplayName(): string {
		return this.displayName;
	}

	/**
	 * Whether the display name fell back to the Schema name, which is the one name nobody chose.
	 * Reported by the server: it cannot be recovered from the name, which may equal the Schema name
	 * because someone typed it. Displays go through presentation/subjectDisplayName.ts.
	 */
	public hasGeneratedDisplayName(): boolean {
		return this.displayNameIsGenerated;
	}

	public getSchemaName(): SchemaName {
		return this.schemaName;
	}

	public getStatements(): StatementList {
		return this.statements;
	}

	/**
	 * The page the Subject is stored on. A read that could not resolve it can leave both fields
	 * undefined, whatever their types say.
	 */
	public getPageIdentifiers(): PageIdentifiers {
		return this.pageIdentifiers;
	}

	public getStatementValue( propertyName: PropertyName ): Value | undefined {
		return this.statements.get( propertyName ).value;
	}

	// TODO: test
	public getNamesOfNonEmptyProperties(): PropertyName[] {
		return this.statements.withNonEmptyValues().getPropertyNames();
	}

	/**
	 * A stored label is its own display name, so setting one sets both, and a name someone just typed
	 * is not generated. Clearing one keeps the display name the server last derived, since only the
	 * server can derive a new one.
	 */
	public withLabel( label: string | null ): Subject {
		return new Subject(
			this.id,
			label,
			label ?? this.displayName,
			label === null && this.displayNameIsGenerated,
			this.schemaName,
			this.statements,
			this.pageIdentifiers,
		);
	}

	public withStatements( statements: StatementList ): Subject {
		return new Subject( this.id, this.label, this.displayName, this.displayNameIsGenerated, this.schemaName, statements, this.pageIdentifiers );
	}

}
