import { Subject } from '@/domain/Subject';
import { chosenSubjectName } from '@/domain/chosenSubjectName';
import type { SubjectId } from '@/domain/SubjectId';
import type { SchemaName } from '@/domain/Schema';
import type { StatementList } from '@/domain/StatementList';
import type { PageIdentifiers } from '@/domain/PageIdentifiers';

export class SubjectWithContext extends Subject {

	public constructor(
		id: SubjectId,
		label: string | null,
		schemaName: SchemaName,
		statements: StatementList,
		private readonly pageIdentifiers: PageIdentifiers,
		private readonly mainSubject: boolean,
	) {
		super( id, label, schemaName, statements );
	}

	public getPageIdentifiers(): PageIdentifiers {
		return this.pageIdentifiers;
	}

	/** Whether the hosting page treats this Subject as its own topic. */
	public isMainSubject(): boolean {
		return this.mainSubject;
	}

	/** See chosenSubjectName(), which this answers for the Subject's own page context. */
	public getChosenName(): string | null {
		return chosenSubjectName( this.getLabel(), this.mainSubject, this.pageIdentifiers.getPageName() );
	}

	public withLabel( label: string | null ): SubjectWithContext {
		return this.copyWith( label, this.getSchemaName(), this.getStatements() );
	}

	public withStatements( statements: StatementList ): SubjectWithContext {
		return this.copyWith( this.getLabel(), this.getSchemaName(), statements );
	}

	public withSchemaName( schemaName: SchemaName ): SubjectWithContext {
		return this.copyWith( this.getLabel(), schemaName, this.getStatements() );
	}

	private copyWith( label: string | null, schemaName: SchemaName, statements: StatementList ): SubjectWithContext {
		return new SubjectWithContext( this.getId(), label, schemaName, statements, this.pageIdentifiers, this.mainSubject );
	}

}
